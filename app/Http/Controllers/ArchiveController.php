<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ArchiveController extends Controller
{
    private const ARCHIVE_TYPES = [
        'global' => ['permission' => 'archives.global.voir', 'column' => null],
        'company' => ['permission' => 'archives.company.voir', 'column' => null],
        'fabrication' => ['permission' => 'archives.fabrication.voir', 'column' => 'fabricated_at'],
        'montage' => ['permission' => 'archives.montage.voir', 'column' => 'montage_at'],
        'peinture' => ['permission' => 'archives.peinture.voir', 'column' => 'peinture_at'],
        'preparation' => ['permission' => 'archives.preparation.voir', 'column' => 'prepared_at'],
    ];

    public function globalArchive(Request $request) { return $this->getArchive($request, 'global'); }
    public function companyArchive(Request $request) { return $this->getArchive($request, 'company'); }
    public function fabricationArchive(Request $request) { return $this->getArchive($request, 'fabrication'); }
    public function montageArchive(Request $request) { return $this->getArchive($request, 'montage'); }
    public function peintureArchive(Request $request) { return $this->getArchive($request, 'peinture'); }
    public function preparationArchive(Request $request) { return $this->getArchive($request, 'preparation'); }

    

private function getArchive(Request $request, string $archiveType)
{
    $config = self::ARCHIVE_TYPES[$archiveType] ?? null;
    abort_if(!$config, 404, 'Type d’archive introuvable.');

    $user = $request->user();
    abort_unless($user, 401);
    abort_unless($user->can($config['permission']), 403, "Vous n'avez pas accès à cette archive.");

    $validated = $request->validate([
        'company' => ['nullable', 'string', 'regex:/^(all|[1-9][0-9]*)$/'],
        'company_id' => ['nullable', 'integer', 'min:1'],
        'type' => ['nullable', 'integer', 'in:1,2'],
        'date' => ['nullable', 'string', 'max:100'],
        'search' => ['nullable', 'string', 'max:255'],
        'page' => ['nullable', 'integer', 'min:1'],
        'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
    ]);

    $isGlobal = $archiveType === 'global';
    $userCompanyId = $user->company_id;
    $requestedCompany = $validated['company_id'] ?? $validated['company'] ?? null;
    $allCompanies = $isGlobal || $requestedCompany === 'all' || ($requestedCompany === null && !$userCompanyId);
    $companyId = $allCompanies ? null : (int) ($requestedCompany ?? $userCompanyId);
    $column = $config['column'];
    $perPage = (int) ($validated['per_page'] ?? 70);

    if (!$isGlobal && $userCompanyId && ($allCompanies || (string) $companyId !== (string) $userCompanyId)) {
        abort_unless($user->can('archives.global.voir'), 403, 'Accès interdit aux autres sociétés.');
    }

    $pivot = (new Document())->companies();
    $pivotTable = $pivot->getTable();
    $pivotForeign = $pivot->getForeignPivotKeyName();
    $pivotRelated = $pivot->getRelatedPivotKeyName();

    $query = Document::query()->with('status')->select('documents.*');

    if (!$allCompanies) {
        $query->whereExists(function ($sub) use ($pivotTable, $pivotForeign, $pivotRelated, $companyId) {
            $sub->selectRaw('1')->from($pivotTable)
                ->whereColumn("{$pivotTable}.{$pivotForeign}", 'documents.id')
                ->where("{$pivotTable}.{$pivotRelated}", $companyId);
        });
    }

    if ($column) {
        $query->whereExists(function ($sub) use ($column) {
            $sub->selectRaw('1')->from('lines')
                ->whereColumn('lines.document_id', 'documents.id')
                ->whereNotNull("lines.{$column}");
        });
    }

    $query->selectSub(function ($sub) use ($pivotTable, $pivotForeign, $pivotRelated, $companyId, $allCompanies) {
        $sub->from($pivotTable)->selectRaw('MAX([complation_date])')
            ->whereColumn("{$pivotTable}.{$pivotForeign}", 'documents.id');

        if (!$allCompanies) $sub->where("{$pivotTable}.{$pivotRelated}", $companyId);
    }, 'complation_date');

    if ($column) {
        $query->selectSub(function ($sub) use ($column) {
            $sub->from('lines')->selectRaw("MAX([{$column}])")->whereColumn('lines.document_id', 'documents.id');
        }, $column);
    }

    $this->applyArchiveFilters($query, $validated);

    $totalCount = (clone $query)->count();
    $onTimeCount = null;
    $lateCount = null;

    if ($archiveType === 'fabrication') {
        $fabricatedAt = "(SELECT MAX([l].[fabricated_at]) FROM [lines] AS [l] WHERE [l].[document_id] = [documents].[id])";
        $deadline = "(SELECT MAX([p].[complation_date]) FROM [{$pivotTable}] AS [p] WHERE [p].[{$pivotForeign}] = [documents].[id]";
        $deadlineBindings = [];

        if (!$allCompanies) {
            $deadline .= " AND [p].[{$pivotRelated}] = ?";
            $deadlineBindings[] = $companyId;
        }

        $deadline .= ')';

        $onTimeCount = (clone $query)->whereRaw("{$fabricatedAt} <= {$deadline}", $deadlineBindings)->count();
        $lateCount = (clone $query)->whereRaw("{$fabricatedAt} > {$deadline}", $deadlineBindings)->count();
    }

    if ($column) {
        $query->orderByRaw("(SELECT MAX([{$column}]) FROM [lines] WHERE [lines].[document_id] = [documents].[id]) DESC");
    }

    $query->orderByDesc('documents.id');

    $result = $query->paginate($perPage)->toArray();
    $result['total_count'] = $totalCount;
    $result['on_time'] = $onTimeCount;
    $result['late'] = $lateCount;
    $result['archive_type'] = $archiveType;
    $result['company_id'] = $companyId;

    return response()->json($result, 200, [], JSON_UNESCAPED_UNICODE);
}


    private function applyArchiveFilters($query, array $filters): void
    {
        // Date range
        if (!empty($filters['date'])) {
            $dates = explode(',', $filters['date'], 2);

            try {
                foreach ($dates as $date) {
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($date))) {
                        throw new \InvalidArgumentException('Invalid date format');
                    }
                }

                $start = Carbon::createFromFormat('!Y-m-d', trim($dates[0]));
                $end = Carbon::createFromFormat('!Y-m-d', trim($dates[1] ?? $dates[0]));

                if (!$start || !$end || $start->format('Y-m-d') !== trim($dates[0]) || $end->format('Y-m-d') !== trim($dates[1] ?? $dates[0])) {
                    throw new \InvalidArgumentException('Invalid date');
                }

                $start = $start->startOfDay();
                $end = $end->endOfDay();
            } catch (\Throwable $e) {
                throw ValidationException::withMessages(['date' => ['Format attendu : YYYY-MM-DD,YYYY-MM-DD.']]);
            }

            if ($end->lt($start)) {
                throw ValidationException::withMessages(['date' => ['La date de fin doit être après la date de début.']]);
            }

            $query->whereBetween('documents.created_at', [$start, $end]);
        }

        // Document type: 1 = PL, 2 = BL; omitted = all types
        if (isset($filters['type'])) {
            $type = (int) $filters['type'];

            if ($type === 1) {
                $query->where('documents.piece', 'like', '%PL%');
            }

            if ($type === 2) {
                $query->where(function ($q) {
                    $q->where('documents.piece_bl', 'like', '%BL%')
                        ->orWhere('documents.piece', 'like', '%BL%');
                });
            }
        }

        // Text search
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('documents.piece_fa', 'like', "%{$search}%")
                    ->orWhere('documents.piece', 'like', "%{$search}%")
                    ->orWhere('documents.piece_bl', 'like', "%{$search}%")
                    ->orWhere('documents.ref', 'like', "%{$search}%")
                    ->orWhere('documents.code', 'like', "%{$search}%")
                    ->orWhere('documents.client_id', 'like', "%{$search}%");
            });
        }
    }
}

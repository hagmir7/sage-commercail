<?php

namespace App\Imports;

use App\Models\Article;
use App\Models\Emplacement;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Carbon\Carbon;

class MovementImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Skip rows missing an article code
            if (empty($row['article_code'])) {
                continue;
            }

            $article = Article::where('code', $row['article_code'])->first();
            $oldEmplacement = Emplacement::where('code', $row['old_emplacement'])->first();

            if ($article && $oldEmplacement) {
                $newEmplacement = !empty($row['new_emplacement'])
                    ? Emplacement::where('code', $row['new_emplacement'])->first()
                    : null;

                StockMovement::create([
                    'article_stock_id'  => $article->id,
                    'movement_type'     => $row['type'] ?? null,
                    'code_article'      => $row['article_code'] ?? null,
                    'designation'       => $row['description'] ?? null,
                    'emplacement_id'    => $oldEmplacement->id,
                    'to_emplacement_id' => $newEmplacement->id ?? null,
                    'movement_date'     => $this->formatDate($row['date'] ?? null),
                    'quantity'          => (float) ($row['quantity'] ?? 0),
                    'moved_by'          => $row['user'] ?? null,
                    'old_emplacement'   => $row['old_emplacement'] ?? null,
                    'new_emplacement'   => $row['new_emplacement'] ?? null,
                    'company_id'        => $oldEmplacement->depot->company_id ?? null,
                ]);
            } else {
                Log::warning("Skipped: Article [{$row['article_code']}] or emplacement [{$row['old_emplacement']}] not found.");
            }
        }
    }

    private function formatDate($date)
    {
        if (empty($date)) {
            return null;
        }

        try {
            // Case 1: Excel numeric date
            if (is_numeric($date)) {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($date))->format('Y-m-d');
            }

            // Case 2: Text date (dd/mm/yyyy)
            return Carbon::createFromFormat('d/m/Y', trim($date))->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning("Invalid date format: {$date}");
            return null;
        }
    }
}

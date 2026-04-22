<?php

namespace App\Imports;

use App\Models\ArticleStock;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ArticleStockImport
{
    private array $companyMap = [
        'IC' => 1,
        'SM' => 2,
        'AD' => 3,
    ];

    private array $acceptedColumns = [
        'code', 'designation', 'nom',
        'hauteur', 'largeur', 'profondeur', 'epaisseur', 'couleur',
        'ref_four', 'condition', 'codefamille',
        'code_barre', 'conditionpalette', 'ref_four2', 'societe',
    ];

    private function parseValue($value, $default = null)
    {
        $value = trim((string) $value);
        return strtoupper($value) === 'NULL' || $value === '' ? $default : $value;
    }

    private function normalizeKey(string $key): string
    {
        return preg_replace('/\s+/', '_', mb_strtolower(trim($key)));
    }

    public function import(string $filePath): void
    {
        ini_set('max_execution_time', 7200);
        set_time_limit(7200);
        ini_set('memory_limit', '4G');

        $spreadsheet = IOFactory::load($filePath);
        $rows        = $spreadsheet->getActiveSheet()->toArray(
            null, true, true, false
        );

        if (empty($rows)) {
            Log::warning('Import: empty file');
            return;
        }

        $headers = array_map(
            fn($h) => $this->normalizeKey((string) $h),
            $rows[0]
        );

        $imported = 0;
        $updated  = 0;
        $skipped  = 0;

        foreach (array_slice($rows, 1) as $rawRow) {

            $mapped = [];
            foreach ($headers as $i => $key) {
                $mapped[$key] = $rawRow[$i] ?? null;
            }

            $mapped = array_intersect_key($mapped, array_flip($this->acceptedColumns));

            $refArticle  = trim((string) ($mapped['code'] ?? ''));
            $designation = trim((string) ($mapped['designation'] ?? ''));

            if ($refArticle === '' || $designation === '' || strtoupper($designation) === 'NULL') {
                Log::info('Skipped row', [
                    'code'        => $refArticle ?: 'EMPTY',
                    'designation' => $designation ?: 'EMPTY',
                ]);
                $skipped++;
                continue;
            }

            $article = ArticleStock::where('code', $refArticle)->first();

            if ($article) {
                $article->update([
                    'description' => $this->parseValue($mapped['designation']),
                    'name'        => $this->parseValue($mapped['nom'] ?? null),
                    'height'      => $this->parseValue($mapped['hauteur'] ?? null, 0),
                    'width'       => $this->parseValue($mapped['largeur'] ?? null, 0),
                    'depth'       => $this->parseValue($mapped['profondeur'] ?? null, 0),
                    'thickness'   => $this->parseValue($mapped['epaisseur'] ?? null, 0),
                    'color'       => $this->parseValue($mapped['couleur'] ?? null),
                ]);
                $article->touch();
                $updated++;
                continue;
            }

            $article = ArticleStock::create([
                'code'              => $refArticle,
                'description'       => $this->parseValue($mapped['designation']),
                'name'              => $this->parseValue($mapped['nom'] ?? null),
                'height'            => $this->parseValue($mapped['hauteur'] ?? null, 0),
                'width'             => $this->parseValue($mapped['largeur'] ?? null, 0),
                'depth'             => $this->parseValue($mapped['profondeur'] ?? null, 0),
                'thickness'         => $this->parseValue($mapped['epaisseur'] ?? null, 0),
                'color'             => $this->parseValue($mapped['couleur'] ?? null),
                'code_supplier'     => $this->parseValue($mapped['ref_four'] ?? null),
                'condition'         => $this->parseValue($mapped['condition'] ?? null),
                'category'          => $this->parseValue($mapped['codefamille'] ?? null),
                'qr_code'           => $this->parseValue($mapped['code_barre'] ?? null),
                'palette_condition' => $this->parseValue($mapped['conditionpalette'] ?? null),
                'code_supplier_2'   => $this->parseValue($mapped['ref_four2'] ?? null),
            ]);

            $societeRaw   = $mapped['societe'] ?? '';
            $societeCodes = explode('|', strtoupper(trim((string) $societeRaw)));

            $companyIds = collect($societeCodes)
                ->map(fn($code) => $this->companyMap[$code] ?? null)
                ->filter()
                ->unique()
                ->values();

            if ($companyIds->isNotEmpty()) {
                $article->companies()->attach($companyIds);
            }

            $imported++;
        }

        Log::info('Import complete', [
            'created' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'total'   => count($rows) - 1,
        ]);
    }
}
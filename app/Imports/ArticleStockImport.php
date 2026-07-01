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

    /**
     * Sanitize and cast a numeric field (height, width, depth, thickness).
     *
     * Handles:
     *  - Non-breaking spaces (U+00A0), both raw byte and UTF-8 encoded,
     *    which Excel/PDF-to-Excel copy-pastes commonly introduce
     *    (e.g. "108\xa0" instead of "108") and which PHP's trim()
     *    does NOT strip.
     *  - Regular whitespace.
     *  - French-style decimal commas (e.g. "108,5" -> "108.5").
     *  - Empty / "NULL" / non-numeric values -> fallback to $default.
     */
    private function parseNumeric($value, $default = 0)
    {
        if ($value === null) {
            return $default;
        }

        $value = (string) $value;

        // Strip non-breaking spaces (UTF-8 encoded \xc2\xa0 and raw \xa0)
        // and any regular whitespace.
        $value = str_replace(["\xc2\xa0", "\xa0", ' ', "\t", "\n", "\r"], '', $value);
        $value = trim($value);

        // Normalize decimal comma to decimal point.
        $value = str_replace(',', '.', $value);

        if ($value === '' || strtoupper($value) === 'NULL' || !is_numeric($value)) {
            if ($value !== '' && strtoupper($value) !== 'NULL') {
                Log::warning('Import: non-numeric value for numeric field, using default', [
                    'raw_value' => $value,
                    'default'   => $default,
                ]);
            }
            return $default;
        }

        return (float) $value;
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
                    'height'      => $this->parseNumeric($mapped['hauteur'] ?? null),
                    'width'       => $this->parseNumeric($mapped['largeur'] ?? null),
                    'depth'       => $this->parseNumeric($mapped['profondeur'] ?? null),
                    'thickness'   => $this->parseNumeric($mapped['epaisseur'] ?? null),
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
                'height'            => $this->parseNumeric($mapped['hauteur'] ?? null),
                'width'             => $this->parseNumeric($mapped['largeur'] ?? null),
                'depth'             => $this->parseNumeric($mapped['profondeur'] ?? null),
                'thickness'         => $this->parseNumeric($mapped['epaisseur'] ?? null),
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
<?php

namespace App\Imports;

use App\Models\ArticleStock;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ArticleStockImport implements ToModel, WithHeadingRow
{
    private array $companyMap = [
        'IC' => 1,
        'SM' => 2,
        'AD' => 3,
    ];

    /**
     * Converts "NULL", "null", or empty values to a PHP null or given default
     */
    private function parseValue($value, $default = null)
    {
        $value = trim((string) $value);
        return strtoupper($value) === 'NULL' || $value === '' ? $default : $value;
    }

    /**
     * The model importer for each row
     */
    public function model(array $row): ?ArticleStock
    {
        ini_set('max_execution_time', 3600);
        set_time_limit(3600);

        $refArticle = $row['code'] ?? null;

        $designation = trim((string) ($row['designation'] ?? ''));

        if (empty($designation) || strtoupper($designation) === 'NULL') {
            Log::info("Skipped row - empty or NULL designation", ['row' => $row]);
            return null;
        }

        if (
            !$refArticle ||
            ArticleStock::where('code', (string) $refArticle)->exists() || is_null($row['designation'])
        ) {
            Log::warning("Skipped row - missing or duplicate", $row);
            return null;
        }

        $article = new ArticleStock([
            'code'               => (string) $refArticle,
            'description'        => $this->parseValue($row['designation']),
            'name'               => $this->parseValue($row['nom'] ?? null),
            'height'             => $this->parseValue($row['hauteur'] ?? null, 0),
            'width'              => $this->parseValue($row['largeur'] ?? null, 0),
            'depth'              => $this->parseValue($row['profondeur'] ?? null, 0),
            'color'              => $this->parseValue($row['couleur'] ?? null),
            'thickness'          => $this->parseValue($row['epaisseur'] ?? null, 0),
            'code_supplier'      => $this->parseValue($row['ref_four'] ?? null),
            'condition'          => $this->parseValue($row['condition'] ?? null),
            'category'           => $this->parseValue($row['codefamille'] ?? null),
            'qr_code'            => $this->parseValue($row['code_barre'] ?? null),
            'palette_condition'  => $this->parseValue($row['conditionpalette'] ?? null),
            'code_supplier_2'    => $this->parseValue($row['ref_four2'] ?? null),
        ]);

        $article->save();


        $societeRaw = $row['societe'] ?? '';
        $societeCodes = explode('|', strtoupper(trim($societeRaw)));

        $companyIds = collect($societeCodes)
            ->map(fn($code) => $this->companyMap[$code] ?? null)
            ->filter()
            ->unique()
            ->values();

        if ($companyIds->isNotEmpty()) {
            $article->companies()->attach($companyIds);
        }

        return $article;
    }
}

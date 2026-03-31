<?php

namespace App\Imports;

use App\Models\ArticleStock;
use App\Models\Emplacement;
use App\Models\EmplacementLimit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas; // 👈 add this

class EmplacementLimitImport implements ToCollection, WithCalculatedFormulas // 👈 add this
{
    public function collection(Collection $rows)
    {
        $rows = $rows->slice(1);

        foreach ($rows as $row) {

            if (empty($row[0]) && empty($row[4])) {
                \Log::info("Skipped: empty row");
                continue;
            }

            $articleRef      = trim((string) $row[0]);
            $emplacementCode = trim((string) $row[4]);
            $stockMin        = isset($row[5]) && $row[5] !== null && $row[5] !== ''
                                ? (int) round((float) $row[5])
                                : 0;
            $capacity        = isset($row[7]) && $row[7] !== null && $row[7] !== ''
                                ? (int) round((float) $row[7])
                                : 0;

            \Log::info("Row data", [
                'articleRef'      => $articleRef,
                'emplacementCode' => $emplacementCode,
                'stockMin'        => $stockMin,
                'capacity'        => $capacity,
                'raw_row5'        => $row[5] ?? 'NULL',
                'raw_row7'        => $row[7] ?? 'NULL',
            ]);

            if (empty($articleRef) || empty($emplacementCode)) {
                continue;
            }

            $article = ArticleStock::where('code', $articleRef)->first();
            if (!$article) {
                \Log::warning("Article not found: " . $articleRef);
                continue;
            }

            $emplacement = Emplacement::where('code', $emplacementCode)->first();
            if (!$emplacement) {
                \Log::warning("Emplacement not found: " . $emplacementCode);
                continue;
            }

            $article->update(['stock_min' => $stockMin]);

            EmplacementLimit::updateOrCreate(
                [
                    'article_stock_id' => $article->id,
                    'emplacement_id'   => $emplacement->id,
                ],
                ['quantity' => $capacity]
            );
        }
    }
}
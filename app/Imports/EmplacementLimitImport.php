<?php

namespace App\Imports;

use App\Models\ArticleStock;
use App\Models\Emplacement;
use App\Models\EmplacementLimit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class EmplacementLimitImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        $rows = $rows->slice(1);

        foreach ($rows as $row) {

            if (empty($row[0]) && empty($row[4])) {
                \Log::info("Skipped: empty row");
                continue;
            }

            $articleRef      = trim($row[0]);
            $emplacementCode = trim($row[4]);
            $stockMin        = isset($row[5]) ? intval($row[5]) : 0;
            $capacity        = isset($row[7]) ? intval($row[7]) : 0;

            \Log::info("Row data", [
                'articleRef'      => $articleRef,
                'emplacementCode' => $emplacementCode,
                'stockMin'        => $stockMin,
                'capacity'        => $capacity,
            ]);

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

            \Log::info("Creating EmplacementLimit", [
                'article_stock_id' => $article->id,
                'emplacement_id'   => $emplacement->id,
                'quantity'         => $capacity,
            ]);

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

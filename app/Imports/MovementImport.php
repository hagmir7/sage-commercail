<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;

class MovementImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            if ($index === 0) continue;

            $articleRef = $row[1];
            $quantityToSubtract = (int) $row[2];
            $emplacement = $row[3];

            $lines = DB::connection('sqlsrv_logi')->select("
                SELECT A.[#Réf. Palette] AS palette_id, A.[Quantité]
                FROM T_Art A
                INNER JOIN T_Pal P ON P.[Réf. Palette] = A.[#Réf. Palette]
                WHERE P.[Emplacement] = ? AND A.[Réf. Article] = ?
                ORDER BY A.[#Réf. Palette] ASC
            ", [$emplacement, $articleRef]);

            foreach ($lines as $line) {
                if ($quantityToSubtract <= 0) break;

                $currentQty = (int) $line->Quantité;
                $toSubtract = min($currentQty, $quantityToSubtract);

                // Update this row
                DB::connection('sqlsrv_logi')->update("
                    UPDATE A
                    SET A.[Quantité] = A.[Quantité] - ?
                    FROM T_Art A
                    WHERE A.[#Réf. Palette] = ? AND A.[Réf. Article] = ?
                ", [$toSubtract, $line->palette_id, $articleRef]);

                // Log this update
                Log::info("Stock movement applied", [
                    'article'     => $articleRef,
                    'palette_id'  => $line->palette_id,
                    'emplacement' => $emplacement,
                    'subtracted'  => $toSubtract,
                    'remaining_request' => $quantityToSubtract - $toSubtract,
                ]);

                $quantityToSubtract -= $toSubtract;
            }

            if ($quantityToSubtract > 0) {
                // Not enough stock → log warning
                Log::warning("Not enough stock to fulfill request", [
                    'article'     => $articleRef,
                    'emplacement' => $emplacement,
                    'missing_qty' => $quantityToSubtract,
                ]);
            }
        }
    }
}

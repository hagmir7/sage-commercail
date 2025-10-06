<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class MovementImport implements ToCollection, WithCalculatedFormulas
{
    public function collection(Collection $rows)
    {
        Log::info('📥 MovementImport started');

        if ($rows->isEmpty()) {
            Log::warning('⚠️ No rows found in the imported Excel file.');
            return;
        }

        Log::info('✅ First 5 rows:', array_slice($rows->toArray(), 0, 5));

        // Start transaction
        DB::connection('sqlsrv_logi')->beginTransaction();

        foreach ($rows as $index => $row) {
            // Skip header row
            if ($index === 0) {
                Log::info('⏭️ Header skipped:', $row->toArray());
                continue;
            }

            // Get columns
            $date            = $row[0] ?? null;
            $refArticle      = $row[1] ?? null;
            $designation     = $row[2] ?? null;
            $quantite        = $row[3] ?? null;
            $oldEmplacement  = $row[4] ?? null;
            $newEmplacement  = $row[5] ?? null;
            $typeMouvement   = $row[6] ?? null;

            // Validation
            if (!$refArticle || !$designation) {
                Log::warning("⚠️ Missing required data at row {$index}", [
                    'refArticle' => $refArticle,
                    'designation' => $designation
                ]);
                continue;
            }

            // Parse date
            try {
                if (is_numeric($date)) {
                    // Excel numeric date
                    $formattedDate = ExcelDate::excelToDateTimeObject($date)->format('Y-m-d');
                } else {
                    // Excel string date (dd/mm/yyyy or dd-mm-yyyy)
                    $date = str_replace('-', '/', trim($date));
                    $parts = explode('/', $date);

                    if (count($parts) === 3) {
                        $formattedDate = sprintf('%04d-%02d-%02d', $parts[2], $parts[1], $parts[0]);
                    } else {
                        throw new \Exception("Invalid date format: $date");
                    }
                }
            } catch (\Throwable $e) {
                Log::error("❌ Invalid date at row {$index}: " . $e->getMessage(), ['date' => $date]);
                continue;
            }

            try {
                DB::connection('sqlsrv_logi')->statement("
                    INSERT INTO T_MouvementEntresSorties 
                    ([Type. Mouvement], [Réf. Article], Désignation, Date, Heure, Quantité, Utilisateur, OldEmplacement, NewEmplacement, OldDepot, NewDepot)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ", [
                    trim($typeMouvement),
                    trim($refArticle),
                    trim($designation),
                    $formattedDate,
                    now()->format('H:i:s.v'), 
                    (float) $quantite,
                    'Magasinier',
                    $oldEmplacement ?: null,
                    $newEmplacement ?: null,
                    "DEPOT 6",
                    "SERIE MOBLE"
                ]);

                Log::info("✅ Inserted row {$index} successfully", [
                    'refArticle' => $refArticle,
                    'typeMouvement' => $typeMouvement,
                    'date' => $formattedDate,
                    'quantite' => $quantite,
                ]);
            } catch (\Throwable $e) {
                Log::error("❌ Failed to insert row {$index}: " . $e->getMessage(), [
                    'row' => $row->toArray()
                ]);
            }
        }

        // Commit all inserts
        DB::connection('sqlsrv_logi')->commit();

        Log::info('🏁 MovementImport completed successfully.');
    }
}

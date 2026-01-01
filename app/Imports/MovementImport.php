<?php

namespace App\Imports;

use App\Models\ArticleStock;
use App\Models\Emplacement;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class MovementImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    public function collection(Collection $rows)
    {
        DB::beginTransaction();
        
        try {
            $importedCount = 0;
            $skippedCount = 0;
            
            foreach ($rows as $index => $row) {
                
                // Skip completely empty rows
                if ($row->filter()->isEmpty()) {
                    $skippedCount++;
                    continue;
                }

                $articleCode        = trim($row['article_code'] ?? '');
                $movementType       = trim($row['type'] ?? '');
                $oldEmplacementCode = trim($row['old_emplacement'] ?? '');

                // ⛔ Skip if article_code is missing
                if (empty($articleCode)) {
                    Log::error("Row " . ($index + 2) . ": Missing article_code - IMPORT FAILED");
                    throw new \Exception("Row " . ($index + 2) . ": Missing article_code");
                }

                // For "IN" movements, old_emplacement is optional
                // For other movements, it's required
                if ($movementType !== 'IN' && empty($oldEmplacementCode)) {
                    Log::error("Row " . ($index + 2) . ": Missing old_emplacement for {$movementType} movement - IMPORT FAILED");
                    throw new \Exception("Row " . ($index + 2) . ": Missing old_emplacement for {$movementType} movement");
                }

                // Article
                $article = ArticleStock::where('code', $articleCode)->first();
                if (!$article) {
                    Log::error("Row " . ($index + 2) . ": Article not found [{$articleCode}] - IMPORT FAILED");
                    throw new \Exception("Row " . ($index + 2) . ": Article not found [{$articleCode}]");
                }

                // Old emplacement (optional for IN movements)
                $oldEmplacement = null;
                if (!empty($oldEmplacementCode)) {
                    $oldEmplacement = Emplacement::where('code', $oldEmplacementCode)->first();
                    if (!$oldEmplacement) {
                        Log::error("Row " . ($index + 2) . ": Old emplacement not found [{$oldEmplacementCode}] - IMPORT FAILED");
                        throw new \Exception("Row " . ($index + 2) . ": Old emplacement not found [{$oldEmplacementCode}]");
                    }
                }

                // New emplacement
                $newEmplacementCode = trim($row['new_emplacement'] ?? '');
                $newEmplacement = null;
                
                if (!empty($newEmplacementCode)) {
                    $newEmplacement = Emplacement::where('code', $newEmplacementCode)->first();
                    if (!$newEmplacement) {
                        Log::error("Row " . ($index + 2) . ": New emplacement not found [{$newEmplacementCode}] - IMPORT FAILED");
                        throw new \Exception("Row " . ($index + 2) . ": New emplacement not found [{$newEmplacementCode}]");
                    }
                }

                // For IN movements without old_emplacement, use new_emplacement as the primary
                $primaryEmplacementId = $oldEmplacement?->id ?? $newEmplacement?->id;
                $companyId = $oldEmplacement?->depot?->company_id ?? $newEmplacement?->depot?->company_id;

                if (!$primaryEmplacementId) {
                    Log::error("Row " . ($index + 2) . ": No valid emplacement found - IMPORT FAILED");
                    throw new \Exception("Row " . ($index + 2) . ": No valid emplacement found");
                }

                StockMovement::create([
                    'article_stock_id'  => $article->id,
                    'movement_type'     => $movementType,
                    'code_article'      => $articleCode,
                    'designation'       => $row['description'] ?? null,
                    'emplacement_id'    => $primaryEmplacementId,
                    'to_emplacement_id' => $newEmplacement?->id,
                    'movement_date'     => $this->formatDate($row['date'] ?? null),
                    'quantity'          => (float) ($row['quantity'] ?? 0),
                    'moved_by'          => $row['user'] ?? null,
                    'old_emplacement'   => $oldEmplacementCode ?: null,
                    'new_emplacement'   => $newEmplacementCode ?: null,
                    'company_id'        => $companyId,
                ]);
                
                $importedCount++;
                Log::info("Row " . ($index + 2) . ": Successfully imported movement for article [{$articleCode}]");
            }
            
            DB::commit();
            
            Log::info("Import completed successfully: {$importedCount} rows imported, {$skippedCount} empty rows skipped");
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Import failed and rolled back: " . $e->getMessage());
            
            // Re-throw the exception so the calling code knows the import failed
            throw $e;
        }
    }

    private function formatDate($date)
    {
        if (!$date) {
            return null;
        }

        try {
            // Excel numeric date
            if (is_numeric($date)) {
                return Carbon::instance(
                    ExcelDate::excelToDateTimeObject($date)
                )->format('Y-m-d H:i:s');
            }

            // dd/mm/yyyy HH:ii
            if (str_contains($date, ':')) {
                return Carbon::createFromFormat('d/m/Y H:i', trim($date))
                    ->format('Y-m-d H:i:s');
            }

            // dd/mm/yyyy
            return Carbon::createFromFormat('d/m/Y', trim($date))
                ->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning("Invalid date format: {$date}");
            return null;
        }
    }
}
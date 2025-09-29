<?php

namespace App\Imports;

use App\Models\Article;
use App\Models\ArticleStock;
use App\Models\Emplacement;
use App\Models\Palette;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PalettesImport implements ToCollection, WithHeadingRow
{
    private function generatePaletteCode()
    {
        // Lock the table to prevent race conditions
        $lastCode = DB::table('palettes')
            ->where('code', 'like', 'PALS%')
            ->lockForUpdate()
            ->orderBy('id', 'desc')
            ->value('code');

        if (!$lastCode) {
            $nextNumber = 1;
        } else {
            $number = (int) substr($lastCode, 4);
            $nextNumber = $number + 1;
        }
        
        return 'PALS' . str_pad($nextNumber, 8, '0', STR_PAD_LEFT);
    }

    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                // Debug: Log the raw row data for first few rows
                if ($index < 3) {
                    Log::info("Row {$index} data", $row->toArray());
                }

                // Extract data from row - try multiple possible column names
                $articleCode     = $row['article_code'] ?? $row['article code'] ?? null;
                $qte             = $row['qte'] ?? $row['quantity'] ?? null;
                $emplacementCode = $row['emplacement_code'] ?? $row['emplacement code'] ?? $row['emplacement'] ?? null;

                // Trim whitespace from all values
                $articleCode     = $articleCode ? trim($articleCode) : null;
                $qte             = $qte ? trim($qte) : null;
                $emplacementCode = $emplacementCode ? trim($emplacementCode) : null;

                // Validate required fields
                if (!$articleCode || !$qte || !$emplacementCode) {
                    Log::warning("Skipping row {$index} with missing data", [
                        'article_code' => $articleCode,
                        'qte' => $qte,
                        'emplacement_code' => $emplacementCode,
                        'all_keys' => array_keys($row->toArray())
                    ]);
                    continue;
                }

                // Clean and validate quantity (handle comma as decimal separator)
                $qte = str_replace(',', '.', $qte);
                if (!is_numeric($qte) || $qte <= 0) {
                    Log::warning("Invalid quantity for article {$articleCode}: {$qte}");
                    continue;
                }

                // Find emplacement
                $emplacement = Emplacement::with('depot')->where('code', $emplacementCode)->first();

                if (!$emplacement) {
                    Log::alert("Emplacement not found: '{$emplacementCode}' (length: " . strlen($emplacementCode) . ") for article: {$articleCode}");
                    continue;
                }

                // Find article
                $article = ArticleStock::where('code', $articleCode)->first();

                if (!$article) {
                    Log::alert("Article not found: {$articleCode}");
                    continue;
                }

                // Create new palette with generated code
                $palette = Palette::create([
                    'code'           => $this->generatePaletteCode(),
                    'company_id'     => $emplacement->depot->company_id,
                    'emplacement_id' => $emplacement->id,
                    'type'           => 'Stock',
                    'user_id'        => 1,
                ]);

                // Attach article to palette with quantity
                $palette->articles()->attach($article->id, ['quantity' => $qte]);
            }

            DB::commit();
            
            Log::info("Palette import completed successfully. Processed {$rows->count()} rows.");
            
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Palette import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
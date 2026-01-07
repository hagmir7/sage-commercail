<?php

namespace App\Services;

use App\Models\Palette;
use App\Models\ArticleStock;
use App\Models\Emplacement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockMovementService
{

    /**
     * Remove quantity of an article from an emplacement (FIFO by palette)
     */
    public function stockOut(
        Emplacement $emplacement,
        ArticleStock $article,
        float $quantity,
        string $type = 'Stock'
    ): void {
        $palettes = Palette::query()
            ->where('emplacement_id', $emplacement->id)
            ->where('type', $type)
            ->whereHas(
                'articles',
                fn($q) => $q->where('article_stocks.id', $article->id)
            )
            ->with([
                'articles' => fn($q) => $q->where('article_stocks.id', $article->id)
            ])
            ->lockForUpdate()
            ->get();

        if ($palettes->isEmpty()) {
            throw new RuntimeException(
                "Article introuvable dans l'emplacement {$emplacement->code}."
            );
        }

        $availableQuantity = $palettes->sum(
            fn($palette) => $palette->articles->first()?->pivot->quantity ?? 0
        );

        if ($availableQuantity < $quantity) {
            throw new RuntimeException(
                "Stock insuffisant dans l'emplacement {$emplacement->code}."
            );
        }

        $remaining = $quantity;

        foreach ($palettes as $palette) {
            $pivot = $palette->articles->first()->pivot;
            $currentQty = (float) $pivot->quantity;

            if ($currentQty >= $remaining) {
                // Decrement the remaining quantity
                DB::table('article_palette')
                    ->where('palette_id', $palette->id)
                    ->where('article_stock_id', $article->id)
                    ->decrement('quantity', $remaining);
                break;
            }

            // Empty this palette
            DB::table('article_palette')
                ->where('palette_id', $palette->id)
                ->where('article_stock_id', $article->id)
                ->update(['quantity' => 0]);

            $remaining -= $currentQty;
        }
    }

    /**
     * Rollback a stock out operation (add stock back)
     */
    public function rollbackStockOut(
        Emplacement $emplacement,
        ArticleStock $article,
        float $quantity,
        string $type = 'Stock'
    ): void {
        DB::transaction(function () use ($emplacement, $article, $quantity, $type) {
            // Simply add the stock back using stockInsert
            $this->stockInsert($emplacement, $article, $quantity);
        });
    }

    /**
     * Insert quantity of an article into an emplacement
     */
    public function stockInsert(
        Emplacement $emplacement,
        ArticleStock $article,
        float $quantity,
        ?float $unitQuantity = null,
        ?string $packageType = null,
        ?int $packageCount = null
    ): void {
        DB::transaction(function () use (
            $emplacement,
            $article,
            $quantity,
            $unitQuantity,
            $packageType,
            $packageCount
        ) {
            if ($packageType === 'Palette' && $packageCount) {
                $this->insertByPalette(
                    $emplacement,
                    $article,
                    $unitQuantity ?? 0,
                    $packageCount
                );
                return;
            }

            $palette = Palette::firstOrCreate(
                [
                    'emplacement_id' => $emplacement->id,
                    'type'           => 'Stock',
                ],
                [
                    'code'       => $this->generatePaletteCode(),
                    'company_id' => $emplacement->depot->company_id ?? null,
                    'user_id'    => auth()->id(),
                ]
            );

            // Check if pivot exists and increment, otherwise attach
            $existingPivot = DB::table('article_palette')
                ->where('palette_id', $palette->id)
                ->where('article_stock_id', $article->id)
                ->first();

            if ($existingPivot) {
                DB::table('article_palette')
                    ->where('palette_id', $palette->id)
                    ->where('article_stock_id', $article->id)
                    ->increment('quantity', $quantity);
            } else {
                $article->palettes()->attach($palette->id, ['quantity' => $quantity]);
            }
        });
    }

    /**
     * Rollback a stock insert operation (remove stock)
     */
    public function rollbackStockInsert(
        Emplacement $emplacement,
        ArticleStock $article,
        float $quantity,
        string $type = 'Stock'
    ): void {
        DB::transaction(function () use ($emplacement, $article, $quantity, $type) {
            // Remove the stock using stockOut
            $this->stockOut($emplacement, $article, $quantity, $type);
        });
    }

    /**
     * Transfer article quantity between emplacements
     */
    public function transfer(
        Emplacement $source,
        Emplacement $destination,
        ArticleStock $article,
        float $quantity
    ): void {
        DB::transaction(function () use (
            $source,
            $destination,
            $article,
            $quantity
        ) {
            $this->stockOut($source, $article, $quantity);
            $this->stockInsert($destination, $article, $quantity);
        });
    }

    /**
     * Rollback a transfer operation (reverse the transfer)
     */
    public function rollbackTransfer(
        Emplacement $source,
        Emplacement $destination,
        ArticleStock $article,
        float $quantity
    ): void {
        DB::transaction(function () use (
            $source,
            $destination,
            $article,
            $quantity
        ) {
            // Reverse: remove from destination and add back to source
            $this->stockOut($destination, $article, $quantity);
            $this->stockInsert($source, $article, $quantity);
        });
    }

    /**
     * Delete stock completely from an emplacement (removes all quantities)
     */
    public function deleteStock(
        Emplacement $emplacement,
        ArticleStock $article,
        string $type = 'Stock'
    ): float {
        return DB::transaction(function () use ($emplacement, $article, $type) {
            $palettes = Palette::query()
                ->where('emplacement_id', $emplacement->id)
                ->where('type', $type)
                ->whereHas(
                    'articles',
                    fn($q) => $q->where('article_stocks.id', $article->id)
                )
                ->with([
                    'articles' => fn($q) => $q->where('article_stocks.id', $article->id)
                ])
                ->lockForUpdate()
                ->get();

            if ($palettes->isEmpty()) {
                throw new RuntimeException(
                    "Article introuvable dans l'emplacement {$emplacement->code}."
                );
            }

            // Calculate total quantity before deletion
            $totalQuantity = $palettes->sum(
                fn($palette) => $palette->articles->first()?->pivot->quantity ?? 0
            );

            // Delete all pivot records
            foreach ($palettes as $palette) {
                DB::table('article_palette')
                    ->where('palette_id', $palette->id)
                    ->where('article_stock_id', $article->id)
                    ->delete();
            }

            // Return the deleted quantity for potential rollback
            return $totalQuantity;
        });
    }

    /**
     * Delete a specific quantity from a palette
     */
    public function deletePaletteStock(
        Palette $palette,
        ArticleStock $article,
        float $quantity
    ): void {
        DB::transaction(function () use ($palette, $article, $quantity) {
            $pivot = DB::table('article_palette')
                ->where('palette_id', $palette->id)
                ->where('article_stock_id', $article->id)
                ->lockForUpdate()
                ->first();

            if (!$pivot) {
                throw new RuntimeException(
                    "Article introuvable dans la palette {$palette->code}."
                );
            }

            $currentQty = (float) $pivot->quantity;

            if ($currentQty < $quantity) {
                throw new RuntimeException(
                    "Quantité insuffisante dans la palette {$palette->code}."
                );
            }

            if ($currentQty == $quantity) {
                // Delete the pivot record completely
                DB::table('article_palette')
                    ->where('palette_id', $palette->id)
                    ->where('article_stock_id', $article->id)
                    ->delete();
            } else {
                // Decrement the quantity
                DB::table('article_palette')
                    ->where('palette_id', $palette->id)
                    ->where('article_stock_id', $article->id)
                    ->decrement('quantity', $quantity);
            }
        });
    }

    /**
     * Restore deleted stock (rollback deletion)
     */
    public function restoreDeletedStock(
        Emplacement $emplacement,
        ArticleStock $article,
        float $quantity,
        string $type = 'Stock'
    ): void {
        DB::transaction(function () use ($emplacement, $article, $quantity, $type) {
            $this->stockInsert($emplacement, $article, $quantity);
        });
    }

    /**
     * Insert stock by creating multiple palettes
     */
    private function insertByPalette(
        Emplacement $emplacement,
        ArticleStock $article,
        float $unitQuantity,
        int $count
    ): void {
        for ($i = 0; $i < $count; $i++) {
            $palette = Palette::create([
                'code'           => $this->generatePaletteCode(),
                'emplacement_id' => $emplacement->id,
                'company_id'     => $emplacement->depot->company_id ?? null,
                'user_id'        => auth()->id(),
                'type'           => 'Stock',
            ]);

            $article->palettes()->attach($palette->id, [
                'quantity' => $unitQuantity
            ]);
        }
    }

    /**
     * Generate unique palette code
     */
    public function generatePaletteCode()
    {
        $lastCode = DB::table('palettes')
            ->where('code', 'like', 'PALS%')
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
}
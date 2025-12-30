<?php

namespace App\Http\Controllers;

use App\Models\{
    InventoryMovement,
    InventoryStock,
    ArticleStock,
    Emplacement,
    Palette
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventoryMovementController extends Controller
{
    public function updateQuantity(Request $request, InventoryMovement $inventory_movement)
    {
        $validator = Validator::make($request->all(), [
            'quantity'     => 'required|numeric|min:0.1',
            'code_article' => 'exists:article_stocks,code',
            'emplacement'  => 'required|exists:emplacements,code'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422);
        }

        DB::transaction(function () use ($request, $inventory_movement) {

            $oldQty = (float) $inventory_movement->quantity;
            $newQty = (float) $request->quantity;
            $delta  = $newQty - $oldQty;

            $article   = ArticleStock::where('code', $request->code_article)->first();
            $newEmpl   = Emplacement::where('code', $request->emplacement)->first();
            $oldEmplId = $inventory_movement->emplacement_id;

            $inventoryStock = InventoryStock::where('code_article', $inventory_movement->code_article)
                ->where('inventory_id', $inventory_movement->inventory_id)
                ->lockForUpdate()
                ->firstOrFail();

            // ========== Quantity change ==========
            if ($delta > 0) {
                $this->increaseQuantity($inventoryStock, $delta);
                $inventoryStock->increment('quantity', $delta);
            } elseif ($delta < 0) {
                $this->decreaseQuantity($inventoryStock, abs($delta));
                $inventoryStock->decrement('quantity', abs($delta));
            }

            // ========== Emplacement change ==========
            if ($newEmpl->id !== $oldEmplId) {
                $this->movePalettesToEmplacement($inventoryStock, $newEmpl->id);
            }

            // ========== Update movement ==========
            $inventory_movement->update([
                'quantity'         => $newQty,
                'emplacement_id'   => $newEmpl->id,
                'emplacement_code' => $newEmpl->code,
                'code_article'     => $request->code_article ?? $inventory_movement->code_article,
                'designation'      => $request->code_article
                                        ? $article->description
                                        : $inventory_movement->designation
            ]);
        });

        return response()->json(['message' => 'Mouvement mis à jour avec succès']);
    }

    // ================= Helper Methods =================

    protected function decreaseQuantity(InventoryStock $stock, float $amount)
    {
        $palettes = $stock->palettes()
            ->wherePivot('quantity', '>', 0)
            ->orderBy('inventory_article_palette.created_at')
            ->lockForUpdate()
            ->get();

        $remaining = $amount;

        foreach ($palettes as $palette) {
            if ($remaining <= 0) break;

            $qty = $palette->pivot->quantity;

            if ($qty <= $remaining) {
                $stock->palettes()->detach($palette->id);
                $this->deletePaletteIfEmpty($palette);
                $remaining -= $qty;
            } else {
                $stock->palettes()->updateExistingPivot(
                    $palette->id,
                    ['quantity' => $qty - $remaining]
                );
                $remaining = 0;
            }
        }

        if ($remaining > 0) {
            throw new \Exception("Not enough quantity in palettes to decrease");
        }
    }

    protected function increaseQuantity(InventoryStock $stock, float $amount)
    {
        $palettes = $stock->palettes()
            ->wherePivot('quantity', '>', 0)
            ->orderBy('inventory_article_palette.created_at')
            ->lockForUpdate()
            ->get();

        if ($palettes->isEmpty()) {
            // Create a new palette if none exists
            $palette = Palette::create([
                'emplacement_id' => $stock->inventory_id // default emplacement
            ]);
            $stock->palettes()->attach($palette->id, ['quantity' => $amount]);
            return;
        }

        // Add quantity to first palette (FIFO)
        $firstPalette = $palettes->first();
        $stock->palettes()->updateExistingPivot(
            $firstPalette->id,
            ['quantity' => $firstPalette->pivot->quantity + $amount]
        );
    }

    protected function movePalettesToEmplacement(InventoryStock $stock, int $newEmplacementId)
    {
        $palettes = $stock->palettes()->get();

        foreach ($palettes as $palette) {
            $palette->update(['emplacement_id' => $newEmplacementId]);
        }
    }

    protected function deletePaletteIfEmpty(Palette $palette)
    {
        $exists = DB::table('inventory_article_palette')
            ->where('palette_id', $palette->id)
            ->exists();

        if (!$exists) {
            $palette->delete();
        }
    }
}

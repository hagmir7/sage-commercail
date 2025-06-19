<?php

namespace App\Http\Controllers;

use App\Models\Depot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepotController extends Controller
{
    public function list(){
        $depots = Depot::withCount('emplacements')
            ->with('company')
            ->orderByDesc('created_at')
            ->paginate(30);
        return $depots;
    }


    public function show(Depot $depot)
    {
        $raw = DB::table('emplacements')
            ->leftJoin('palettes', 'emplacements.id', '=', 'palettes.emplacement_id')
            ->leftJoin('article_palette', 'palettes.id', '=', 'article_palette.palette_id')
            ->select(
                'emplacements.id as emplacement_id',
                'emplacements.code',
                'palettes.id as palette_id',
                'article_palette.article_stock_id',
                'article_palette.quantity'
            )
            ->where('emplacements.depot_id', $depot->id)
            ->get();

        $grouped = $raw->groupBy('emplacement_id')->map(function ($rows, $emplacementId) {
            $code = $rows->first()->code;

            $uniquePaletteCount = $rows->pluck('palette_id')->filter()->unique()->count();

            $articleQuantities = $rows
                ->filter(fn($row) => $row->article_stock_id !== null)
                ->groupBy('article_stock_id')
                ->map(fn($articles) => $articles->sum('quantity'));

            return [
                'id' => $emplacementId,
                'code' => $code,
                'palette_count' => $uniquePaletteCount,
                'distinct_article_count' => $articleQuantities->count(),
                'total_articles_quantity' => $articleQuantities->sum(),
                'articles' => $articleQuantities, 
            ];
        })->values();

        return response()->json([
            "depot" => $depot,
            "emplacements" => $grouped
        ]);
    }





}

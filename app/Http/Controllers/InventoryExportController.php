<?php

namespace App\Http\Controllers;

use App\Exports\InventoryArticlesExport;
use App\Exports\InventoryMovementExport;
use App\Models\Company;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InventoryExportController extends Controller
{
    public function export(Inventory $inventory, Request $request)
    {

        $company_id = $request->company;

        $inventory->load(['palettes.company', 'palettes.inventoryArticles']);
        $palettes = $company_id
            ? $inventory->palettes->where('company_id', $company_id)
            : $inventory->palettes;

        $articles = collect();

        foreach ($palettes as $palette) {
            foreach ($palette->inventoryArticles as $article) {
                $articles->push([
                    'code_article' => $article->code_article,
                    'designation' => $article->designation,
                    'quantity' => (float) $article->pivot->quantity,
                    'palette_id' => $palette->id,
                    'company_name' => $palette->company->name,
                ]);
            }
        }

        $grouped = $articles->groupBy('code_article')->map(function ($items) {
            return [
                'code_article' => $items->first()['code_article'],
                'designation' => $items->first()['designation'],
                'quantity' => $items->sum('quantity'),
                'palettes_count' => $items->pluck('palette_id')->unique()->count(),
                'company_name' => $items->first()['company_name'],
            ];
        })->values();

        $inventory_date = \Carbon\Carbon::parse($inventory->date)->toDateString();


        $fileName = "inventaire-{$inventory_date}";

        if ($company_id) {
            $company = Company::find($company_id);
            if ($company) {
                $safeCompanyName = preg_replace('/[^A-Za-z0-9_\-]/', '', str_replace(' ', '_', $company->name));
                $fileName .= "-{$safeCompanyName}";
            }
        }

        $fileName .= ".xlsx";

        return Excel::download(new InventoryArticlesExport($grouped), $fileName);
    }

    public function movements(Inventory $inventory, Request $request)
    {
        $depots = $request->filled('depots') ? explode(',', $request->depots) : null;
        $users = $request->filled('users') ? explode(',', $request->users) : null;


        $inventory->load(['movements' => function ($query) use ($request, $depots, $users) {
            if ($request->filled('category')) {
                $query->filterByCategory($request->category);
            }

            if (!empty($depots)) {
                $query->filterByDepots($depots);
            }

            if (!empty($users)) {
                $query->filterByUsers($users);
            }
        }, 'movements.user', 'movements.company', 'movements.article']);

        $movements = $inventory->movements;


        $grouped = $movements->map(function ($item) {
            return [
                'movement_code' => $item->id,
                'code_article' => $item->code_article,
                'designation' => $item->designation,
                'article_name' => $item?->article?->name,
                'quantity' => $item->quantity,
                'emplacement' => $item->emplacement_code,
                'user' => $item->user->full_name,
                'date' => $item->created_at,
                'company_name' => $item?->company?->name,
            ];
        })->values();

        return Excel::download(new InventoryMovementExport($grouped), 'inventaire.xlsx');
    }
}

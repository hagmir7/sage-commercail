<?php

namespace App\Http\Controllers;

use App\Models\ArticleStock;
use App\Models\CompanyStock;
use Illuminate\Http\Request;

class CompanyStockController extends Controller
{
       public function index(Request $request)
    {
        CompanyStock::select('code_article', 'designation', 'company_id', 'quantity', 'min_quantity');
        $query = ArticleStock::query();

        // Apply filters
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%$search%")
                    ->orWhere('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%")
                    ->orWhere('color', 'like', "%$search%");
            });
        }


        $articles = $query->paginate(100);
        return response()->json($articles);
    }
}

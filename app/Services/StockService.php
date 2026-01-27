<?php

namespace App\Services;

use App\Models\ArticleStock;

class StockService
{
    public function calculateStock(string $ref_article, ?int $company_id = null): int
    {
        $article = ArticleStock::where('code', $ref_article)->first();

        if (! $article) {
            return 0;
        }

        $query = $article->palettes()
            ->where('type', 'Stock')
            ->whereDoesntHave('emplacement', function ($q) {
                $q->whereIn('code', ['K-3P', 'K-4P', 'K-4SP', 'K-3SP']);
            });

        if (! is_null($company_id)) {
            $query->where('company_id', $company_id);
        }

        return (int) $query->sum('article_palette.quantity');
    }
}

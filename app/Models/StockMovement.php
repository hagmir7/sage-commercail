<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use DateTime;
use Exception;

class StockMovement extends Model
{
    protected $fillable = [
        'designation',
        'emplacement_id',
        'to_emplacement_id',
        'article_stock_id',
        'quantity',
        'movement_type',
        'movement_date',
        'moved_by',
        'note',
        'code_article',
        'company_id',
        'to_company_id'
    ];

    public function articleStock()
    {
        return $this->belongsTo(ArticleStock::class, 'article_stock_id');
    }
    public function emplacement()
    {
        return $this->belongsTo(Emplacement::class, 'emplacement_id');
    }

    public function to_emplacement()
    {
        return $this->belongsTo(Emplacement::class, 'to_emplacement_id');
    }

    public function movedBy()
    {
        return $this->belongsTo(User::class, 'moved_by');
    }

    public function to_company()
    {
        return $this->belongsTo(Company::class, 'to_company_id');
    }

    public function scopeFilterByCategory($query, $category)
    {
        return $query->whereHas('articleStock', fn($q) => $q->where('category', 'like', $category));
    }
    public function scopeFilterByDepots($query, $depots)
    {
        return $query->whereHas('emplacement.depot', fn($q) => $q->whereIn('id', $depots));
    }

    public function scopeFilterByUsers($query, $users)
    {
        return $query->whereIn('moved_by', $users);
    }

    public function scopeFilterByEmplacement($query, $emplacement)
    {
        return $query->whereHas('emplacement', function ($q) use ($emplacement) {
            $q->where("code", 'like', "%$emplacement%");
        })
            ->orWhereHas('article', function ($q) use ($emplacement) {
                $q->where('code', 'like', "%$emplacement%");
            });
    }

    public function scopeSearch($query, $search)
    {
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('emplacement', function ($subQuery) use ($search) {
                    $subQuery->where('code', 'like', "%{$search}%");
                })
                    ->orWhere('code_article', 'like', "%{$search}%")
                    ->orWhere('designation', 'like', "%{$search}%");
            });
        }

        return $query;
    }


        public function scopeFilterByTypes($query, $types)
        {
            if (empty($types)) {
                return $query;
            }

            return $query->whereIn('movement_type', $types);
        }



    public function scopeFilterByDates(Builder $query, $dateRange)
    {
        if (!empty($dateRange) && $dateRange !== ',') {
            $dates = array_map('trim', explode(',', $dateRange));

            if (count($dates) === 2) {
                try {
                    $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dates[0])
                        ? $dates[0] . ' 00:00:00'
                        : DateTime::createFromFormat('d/m/Y', $dates[0])->format('Y-m-d 00:00:00');

                    $end = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dates[1])
                        ? $dates[1] . ' 23:59:59'
                        : DateTime::createFromFormat('d/m/Y', $dates[1])->format('Y-m-d 23:59:59');

                    $query->whereBetween('created_at', [$start, $end]);
                } catch (Exception $e) {
                    \Log::error('Date parsing error: ' . $e->getMessage());
                }
            }
        }

        return $query;
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = [
        'code_article',
        'designation',
        'emplacement_id',
        'emplacement_code',
        'controlled_by',
        'inventory_id',
        'type',
        'quantity',
        'user_id',
        'company_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function emplacement()
    {
        return $this->belongsTo(Emplacement::class);
    }

    public function inventory(){
        return $this->belongsTo(Inventory::class);
    }

    public function article(){
        return $this->belongsTo(ArticleStock::class, 'code_article', 'code');
    }


    public function company(){
        return $this->belongsTo(Company::class);
    }



    public function scopeFilterByCategory($query, $category)
    {
        return $query->whereHas('article', fn($q) => $q->where('category', 'like', $category));
    }

    public function scopeFilterByDepots($query, $depots)
    {
        return $query->whereHas('emplacement.depot', fn($q) => $q->whereIn('id', $depots));
    }

    public function scopeFilterByUsers($query, $users)
    {
        return $query->whereIn('user_id', $users);
    }

    public function scopeFilterByEmplacement($query, $emplacement)
    {
        return $query->where('emplacement_code', 'like', "%$emplacement%");
    }

    public function scopeSearch($query, $search)
    {
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('emplacement_code', 'like', "%{$search}%")
                    ->orWhere('code_article', 'like', "%{$search}%")
                    ->orWhere('designation', 'like', "%{$search}%");
            });
        }

        return $query;
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

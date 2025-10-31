<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseDocumentHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_document_id',
        'status',
        'changed_by',
        'comment',
    ];

    // related document
    public function document()
    {
        return $this->belongsTo(PurchaseDocument::class);
    }

    // who made the change
    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

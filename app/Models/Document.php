<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{

    protected $fillable = ['docentete_id', 'piece', 'type', 'ref', 'expedition', 'transfer_by', 'completed'];

    public function docentete()
    {
        return $this->belongsTo(Docentete::class, 'docentete_id');
    }


    public function transferBy()
    {
        return $this->belongsToMany(User::class, 'transfer_by');
    }

    
}

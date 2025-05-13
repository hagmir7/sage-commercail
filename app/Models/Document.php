<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{

    protected $fillable = ['docentete_id', 'piece', 'type', 'ref', 'expedition', 'transfer_by', 'completed', 'client_id'];
    
    protected $dateFormat = 'Y-d-m H:i:s.v';
    protected $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    
    public function docentete()
    {
        return $this->belongsTo(Docentete::class, 'docentete_id');
    }

    public function lines(){
        return $this->hasMany(Line::class);
    }


    public function transferBy()
    {
        return $this->belongsToMany(User::class, 'transfer_by');
    }
}

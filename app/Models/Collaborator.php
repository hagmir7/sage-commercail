<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collaborator extends Model
{
    protected $table = "F_COLLABORATEUR";

    protected $keyType = "stringe";
    public $incrementing = false;

    protected $primaryKey = "CO_Matricule";



    public function user()
    {
        return $this->hasOne(User::class, 'CO_Matricule');
    }
}

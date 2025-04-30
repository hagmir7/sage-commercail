<?php

namespace App\Http\Controllers;

use App\Models\Docentete;
use Illuminate\Http\Request;

class DocenteteController extends Controller
{
    public function show($id){
        $docentete = Docentete::with('doclignes')->find($id);
        return response(json_encode($docentete, JSON_INVALID_UTF8_IGNORE), 200, ['Content-Type' => 'application/json']);
    }
}

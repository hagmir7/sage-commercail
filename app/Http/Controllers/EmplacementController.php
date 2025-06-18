<?php

namespace App\Http\Controllers;

use App\Models\Emplacement;
use Illuminate\Http\Request;

class EmplacementController extends Controller
{
    public function show(Emplacement $emplacement)
    {
        $emplacement->load(['depot', 'palettes.articles']);
        return response()->json($emplacement);
    }
}

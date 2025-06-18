<?php

namespace App\Http\Controllers;

use App\Models\Emplacement;
use Illuminate\Http\Request;

class EmplacementController extends Controller
{
    public function show(Emplacement $emplacement)
    {
        // Chargement eager des relations
        $emplacement->load(['depte', 'articles']);

        // Optionnel : tu peux retourner une réponse JSON propre
        return response()->json($emplacement);
    }
}

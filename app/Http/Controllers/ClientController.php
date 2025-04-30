<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function show(Request $request, Client $client)
    {
        $perPage = $request->get('per_page', 10);
    
        $docentetes = $client->docentete()->orderByDesc('cbCreation')->paginate($perPage);
    
        // Eager load only selected columns from the remise relationship
        $client->load(['remise' => function ($query) {
            $query->select('FA_CodeFamille', 'CT_Num', 'FC_Remise', 'cbMarq');
        }]);
    
        return response()->json([
            'client' => $client,
            'docentete' => $docentetes,
        ], 200, [], JSON_INVALID_UTF8_IGNORE);
    }
    

}

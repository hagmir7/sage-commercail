<?php

namespace App\Http\Controllers;

use App\Models\Depot;
use Illuminate\Http\Request;

class DepotController extends Controller
{
    public function list(){
        $depots = Depot::withCount('emplacements')
            ->with('company')
            ->orderByDesc('created_at')
            ->paginate(30);
        return $depots;
    }


    public function show(Depot $depot){
        $depot->load(['emplacements', 'company']);
        return $depot;
    }
}

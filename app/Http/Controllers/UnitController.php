<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $units = Unit::select('U_Intitule', 'U_EdiCode', 'cbMarq')->whereNot('U_Intitule', '')->get();
        return response()->json($units);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'U_Intitule' => 'required|string|max:255',
            'U_EdiCode'  => 'required|string|max:50',
        ]);

        $unit = Unit::create($validated);

        return response()->json($unit, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Unit $unit)
    {
        return response()->json([
            'U_Intitule' => $unit->U_Intitule,
            'U_EdiCode'  => $unit->U_EdiCode,
            'cbMarq' => $unit->cbMarq
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'U_Intitule' => 'sometimes|required|string|max:255',
            'U_EdiCode'  => 'sometimes|required|string|max:50',
        ]);

        $unit->update($validated);

        return response()->json($unit);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Unit $unit)
    {
        $unit->delete();
        return response()->json(null, 204);
    }
}

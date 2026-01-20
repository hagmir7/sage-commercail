<?php

namespace App\Http\Controllers;

use App\Models\SupplierInterview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierInterviewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return SupplierInterview::with(['user', 'client'])
            ->latest()
            ->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id'   => 'required|exists:clients,id',
            'date'        => 'required|date',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $supplierInterview = SupplierInterview::create(
            array_merge(
                $validator->validated(),
                ['user_id' => auth()->id()]
            )
        );


        return response()->json($supplierInterview, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(SupplierInterview $supplierInterview)
    {
        return $supplierInterview->load(['user', 'client']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SupplierInterview $supplierInterview)
    {
        $validator = Validator::make($request->all(), [
            'client_id'   => 'required|exists:clients,id',
            'date'        => 'required|date',
            'description' => 'nullable|string',
            'note'        => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $supplierInterview->update($validator->validated());

        return response()->json($supplierInterview);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SupplierInterview $supplierInterview)
    {
        $supplierInterview->delete();

        return response()->json([
            'message' => 'Deleted successfully'
        ]);
    }
}

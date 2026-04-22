<?php

namespace App\Http\Controllers;

use App\Models\ShippingCriteria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShippingCriteriaController extends Controller
{
    public function index()
    {
        $criteria = ShippingCriteria::latest()->get();

        return response()->json($criteria);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255|unique:shipping_criteria,name',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $criteria = ShippingCriteria::create($validator->validated());

        return response()->json([
            'message'  => 'Criteria created successfully.',
            'criteria' => $criteria,
        ], 201);
    }

    public function show(ShippingCriteria $shippingCriteria)
    {
        return response()->json($shippingCriteria);
    }

    public function update(Request $request, ShippingCriteria $shippingCriteria)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|required|string|max:255|unique:shipping_criteria,name,' . $shippingCriteria->id,
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $shippingCriteria->update($validator->validated());

        return response()->json([
            'message'  => 'Criteria updated successfully.',
            'criteria' => $shippingCriteria,
        ]);
    }

    public function destroy(ShippingCriteria $shippingCriteria)
    {
        $shippingCriteria->delete();

        return response()->json([
            'message' => 'Criteria deleted successfully.',
        ]);
    }
}
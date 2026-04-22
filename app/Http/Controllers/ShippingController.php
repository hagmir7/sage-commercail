<?php

namespace App\Http\Controllers;

use App\Models\Shipping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ShippingController extends Controller
{


    private function generateReference(): string
    {
        $year    = now()->year;
        $last    = Shipping::whereYear('created_at', $year)->lockForUpdate()->count();
        $counter = str_pad($last + 1, 6, '0', STR_PAD_LEFT);
        return "CHL-{$counter}";
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipping_date'   => 'required|date',
            'document_id'     => 'required|exists:documents,id',
            'user_id'         => 'required|exists:users,id',
            'criteria'                        => 'nullable|array',
            'criteria.*.shipping_criteria_id' => 'required_with:criteria|exists:shipping_criterias,id',
            'criteria.*.status'               => 'required_with:criteria|string',
            'criteria.*.note'                 => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();

        try {
            $shipping = Shipping::create([
                'code'            => $this->generateReference( ),
                'shipping_date'   => $validated['shipping_date'],
                'document_id'     => $validated['document_id'],
                'user_id'         => $validated['user_id'],
                'validation_date' => now(),
            ]);

            if (!empty($validated['criteria'])) {
                $shipping->criteria()->createMany($validated['criteria']);
            }

            DB::commit();

            return response()->json([
                'message'  => 'Shipping created successfully.',
                'shipping' => $shipping->load('criteria', 'document', 'user'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create shipping.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
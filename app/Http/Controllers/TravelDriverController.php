<?php

namespace App\Http\Controllers;

use App\Models\TravelDriver;
use App\Models\TravelReception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class TravelDriverController extends Controller
{
    public function checkCIN(Request $request)
    {
        Validator::make($request->all(), [
            'cin' => 'required|string|max:20',
        ]);

        $driver = TravelDriver::where('cin', strtoupper($request->cin))?->first();

        return [
            'status' => $driver ? true : false
        ];
    }

    /**
     * Driver already exists → create reception only
     * POST /travel-receptions
     * Body: { cin, code, company_id }
     */
    public function storeReception(Request $request)
    {
        $validated = $request->validate([
            'cin'        => 'required|string|max:20|exists:travel_drivers,cin',
            'code'       => 'nullable|string',
            'company_id' => 'nullable|integer',
        ]);

        $driver = TravelDriver::where('cin', strtoupper($validated['cin']))->firstOrFail();

        $reception = TravelReception::create([
            'travel_driver_id' => $driver->id,
            'code'              => isset($validated['code']) ? $validated['code'] : null,
            'company_id'       => $validated['company_id'],
        ]);

        return response()->json($reception->load('driver'), 201);
    }

    /**
     * Driver does not exist → create driver + reception in one transaction
     * POST /travel-drivers
     * Body: { full_name, cin, code (driver code), travel_code, company_id }
     */
    public function storeDriverAndReception(Request $request)
    {
        $validated = $request->validate([
            'full_name'   => 'required|string|max:255',
            'cin'         => 'required|string|max:20|unique:travel_drivers,cin',
            'code'        => 'nullable|string',
            'travel_code' => 'nullable|string',
            'company_id'  => 'nullable|numeric',   // ← was 'integer', strings like "12" fail integer rule
        ]);
        DB::beginTransaction();

        try {
            $driver = TravelDriver::create([
                'full_name' => $validated['full_name'],
                'cin'       => strtoupper($validated['cin']),
                'code'      => isset($validated['code']) ? $validated['code'] : null,
            ]);

            $reception = TravelReception::create([
                'travel_driver_id' => $driver->id,
                'code'             => isset($validated['travel_code']) ? $validated['travel_code'] : null,
                'company_id'       => isset($validated['company_id']) ? $validated['company_id'] : null,
            ]);

            DB::commit();

            return response()->json([
                'driver'    => $driver,
                'reception' => $reception,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur serveur', 'error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        $query = TravelReception::with(['driver', 'company'])
            ->when(
                $request->cin,
                fn($q) =>
                $q->whereHas(
                    'driver',
                    fn($d) =>
                    $d->where('cin', 'like', '%' . $request->cin . '%')
                )
            )
            ->when(
                $request->year,
                fn($q) =>
                $q->whereYear('created_at', $request->year)
            )
            ->when(
                $request->date_from,
                fn($q) =>
                $q->whereDate('created_at', '>=', $request->date_from)
            )
            ->when(
                $request->date_to,
                fn($q) =>
                $q->whereDate('created_at', '<=', $request->date_to)
            )
            ->latest();

        return response()->json(
            $query->paginate($request->per_page ?? 30)
        );
    }
}

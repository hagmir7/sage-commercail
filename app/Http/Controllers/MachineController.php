<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MachineController extends Controller
{
    /**
     * GET /api/machines
     * List all machines with optional filters.
     *
     * Query params:
     *   ?family=PLACAGE         – filter by family
     *   ?search=SCM             – full-text search across key fields
     *   ?active=1               – only active machines (omit for all)
     *   ?per_page=20            – pagination size (default 20, max 100)
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'family'   => 'nullable|string',
            'search'   => 'nullable|string|max:100',
            'active'   => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Machine::query();

        if ($request->filled('family')) {
            $query->byFamily($request->family);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->has('active')) {
            $query->where('is_active', (bool) $request->active);
        }

        $perPage = (int) ($request->per_page ?? 20);
        $machines = $query->orderBy('family')->orderBy('machine_id')->paginate($perPage);

        return response()->json($machines);
    }

    /**
     * GET /api/machines/families
     * Return a distinct list of families for dropdown menus.
     */
    public function families(): JsonResponse
    {
        $families = Machine::distinct()
            ->orderBy('family')
            ->pluck('family');

        return response()->json(['data' => $families]);
    }

    /**
     * GET /api/machines/{machine_id}
     * Fetch a single machine by its machine_id (e.g. MAH1, GB1).
     */
    public function show(string $machineId): JsonResponse
    {
        $machine = Machine::where('machine_id', strtoupper($machineId))->firstOrFail();

        return response()->json(['data' => $machine]);
    }

    /**
     * POST /api/machines
     * Create a new machine.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'family'        => 'required|string|max:50',
            'ref_machine'   => 'nullable|string|max:50',
            'group_code'    => 'nullable|string|max:100',
            'machine_id'    => 'required|string|max:20|unique:machines,machine_id',
            'machine_name'  => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:100',
            'alias'         => 'nullable|string|max:255',
            'manufacturer'  => 'nullable|string|max:100',
            'is_active'     => 'sometimes|boolean',
            'notes'         => 'nullable|string',
        ]);

        $validated['machine_id'] = strtoupper($validated['machine_id']);
        $validated['family']     = strtoupper($validated['family']);

        $machine = Machine::create($validated);

        return response()->json(['data' => $machine, 'message' => 'Machine created successfully.'], 201);
    }

    /**
     * PUT /api/machines/{machine_id}
     * Update an existing machine.
     */
    public function update(Request $request, string $machineId): JsonResponse
    {
        $machine = Machine::where('machine_id', strtoupper($machineId))->firstOrFail();

        $validated = $request->validate([
            'family'        => 'sometimes|required|string|max:50',
            'ref_machine'   => 'nullable|string|max:50',
            'group_code'    => 'nullable|string|max:100',
            'machine_id'    => [
                'sometimes', 'required', 'string', 'max:20',
                Rule::unique('machines', 'machine_id')->ignore($machine->id),
            ],
            'machine_name'  => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:100',
            'alias'         => 'nullable|string|max:255',
            'manufacturer'  => 'nullable|string|max:100',
            'is_active'     => 'sometimes|boolean',
            'notes'         => 'nullable|string',
        ]);

        if (isset($validated['machine_id'])) {
            $validated['machine_id'] = strtoupper($validated['machine_id']);
        }
        if (isset($validated['family'])) {
            $validated['family'] = strtoupper($validated['family']);
        }

        $machine->update($validated);

        return response()->json(['data' => $machine, 'message' => 'Machine updated successfully.']);
    }

    /**
     * DELETE /api/machines/{machine_id}
     * Soft-delete a machine.
     */
    public function destroy(string $machineId): JsonResponse
    {
        $machine = Machine::where('machine_id', strtoupper($machineId))->firstOrFail();
        $machine->delete();

        return response()->json(['message' => 'Machine deleted successfully.']);
    }

    /**
     * PATCH /api/machines/{machine_id}/restore
     * Restore a soft-deleted machine.
     */
    public function restore(string $machineId): JsonResponse
    {
        $machine = Machine::withTrashed()
            ->where('machine_id', strtoupper($machineId))
            ->firstOrFail();

        $machine->restore();

        return response()->json(['data' => $machine, 'message' => 'Machine restored successfully.']);
    }
}
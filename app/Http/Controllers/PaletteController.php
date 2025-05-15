<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Docligne;
use App\Models\Document;
use App\Models\Line;
use App\Models\Palette;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaletteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Palette::query();

        // Apply filters
        if ($request->has('position_id')) {
            $query->where('position_id', $request->position_id);
        }

        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        $palettes = $query->get();
        return response()->json(['data' => $palettes]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function generatePaletteCode()
    {
        // Get the last inserted code
        $lastCode = DB::table('palettes')
            ->orderBy('id', 'desc')
            ->value('code');

        if (!$lastCode) {
            $nextNumber = 1;
        } else {
            // Extract numeric part (remove PB)
            $number = (int) substr($lastCode, 2);
            $nextNumber = $number + 1;
        }

        // Format with leading zeros and prefix
        return 'PB' . str_pad($nextNumber, 8, '0', STR_PAD_LEFT);
    }

    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_id' => 'required|exists:documents,piece',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $document = Document::where("piece", $request->document_id)?->first();

        $palette = Palette::create([
            'code' => $this->generatePaletteCode(),
            'type' => "Livraison",
            'document_id' => $document->id,
            'company_id' => auth()->user()->company_id || 1
        ]);

        return response()->json($palette, 201);
    }



    public function scan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document' => 'required|exists:documents,piece',
            'line' => 'required|exists:lines,id',
            'palette' => 'required|exists:palettes,code'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $line = Line::find($request->line);
            $palette = Palette::where('code', $request->palette)->first();
            $line->update([
                'palette_id' => $palette->id,
            ]);

            $document = Document::where("piece", $request->document)->first();

            $palette->update([
                'document_id' => $document->id
            ]);

            DB::commit();

            $docligne = $line->docligne;
            return response()->json($docligne);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'An error occurred while processing the scan.',
                'message' => $e->getMessage()
            ], 500);
        }
    }




    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
            'position_id' => 'required|exists:positions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $palette = Palette::create($request->all());
        return response()->json(['data' => $palette, 'message' => 'Palette created successfully'], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $palette = Palette::with(['position', 'company'])->findOrFail($id);
        return response()->json(['data' => $palette]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|required|string|max:255',
            'company_id' => 'sometimes|required|exists:companies,id',
            'position_id' => 'sometimes|required|exists:positions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $palette = Palette::findOrFail($id);
        $palette->update($request->all());

        return response()->json(['data' => $palette, 'message' => 'Palette updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $palette = Palette::findOrFail($id);
        $palette->delete();

        return response()->json(['message' => 'Palette deleted successfully']);
    }
}

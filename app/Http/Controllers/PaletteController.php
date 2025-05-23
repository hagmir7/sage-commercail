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

        $document = Document::where('piece', $request->document_id)->first();

        if (!$document) {
            return response()->json(['error' => 'Document not found.'], 404);
        }

        if ($document->palettes()->exists()) {
            $query = $document->palettes()->with('lines.article_stock');

            if (!empty($request->palette)) {
                $palette = $query->where('code', $request->palette)->first();
            } else {
                $palette = $query->first();
            }
        } else {
            $palette = Palette::create([
                'code'        => $this->generatePaletteCode(),
                'type'        => 'Livraison',
                'document_id' => $document->id,
                'company_id'  => auth()->user()->company_id ?? 1,
                'user_id'     => auth()->id(),
            ]);
            $palette->load('lines.article_stock');
        }

        $allPalettes = $document->palettes()->with('lines.article_stock')->get();

        return response()->json([
            "palette" => $palette,
            "palettes" => $allPalettes,
        ], 201);
    }



    public function create(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'document_id' => 'required|exists:documents,piece'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


        $document =  Document::where('piece', $request->document_id)->first();
        if (!$document) {
            return response()->json(['errors' => ['document_id' => "Document is not exits"]]);
        }

        $palette = Palette::create([
            'code'        => $this->generatePaletteCode(),
            'type'        => 'Livraison',
            'document_id' => $document->id,
            'company_id'  => auth()->user()->company_id ?? 1,
            'user_id'     => auth()->id(),
        ]);
        $palette->load(['lines.article_stock']);

        return response()->json($palette);
    }



    public function scan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'line' => 'required|exists:lines,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $line = Line::with('docligne')->find($request->line);
            // Calculate total qte from the pivot for this line
            $totalQte = $line->palettes->sum(function ($palette) {
                return $palette->pivot->quantity;
            });

           
            // return response()->json(['message' => $totalQte]);

            return response()->json($line);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the scan.',
                'message' => $e->getMessage()
            ], 500);
        }
    }


  public function validation($piece)
    {
        $document = Document::where('piece', $piece)->with('lines.palettes')->first();

        if (!$document) {
            return response()->json(['status' => false, 'message' => 'Document not found'], 404);
        }

        $invalidLines = [];

        foreach ($document->lines as $line) {
            // $line->update(['status_id' => 11 ]);
            $totalPaletteQuantity = $line->palettes->sum(function ($palette) {
                return $palette->pivot->quantity ?? 0;
            });

            // Compare with required_quantity
            if ($totalPaletteQuantity < $line->quantity) {
                $invalidLines[] = [
                    'line_id' => $line->id,
                    'quantity' => $line->quantity,
                    'total_palette_quantity' => $totalPaletteQuantity
                ];
            }
        }

        if (count($invalidLines)) {
            return false;
        }

        return true;
    }


    public function confirm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|max:1000|min:1',
            'line' => 'required|exists:lines,id',
            'palette' => 'required|exists:palettes,code'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $line = Line::find($request->line);
        $palette = Palette::where("code", $request->palette)->first();

        $line->load(['palettes', 'document']);

        $line_qte = $line->quantity;
        $totalQte = $line->palettes->sum(function ($palette) {
            return $palette->pivot->quantity;
        });

        if (intval($line_qte) < ($totalQte + intval($request->quantity))) {
            return response()->json(['message' => "Quantity is not valid"], 422);
        }

        if ($line->document_id !== $palette->document_id) {
            return response()->json(['errors' => ["document" => "The document does not match"]], 404);
        }

        $line->palettes()->attach($palette->id, ['quantity' => $request->quantity]);
        $line->update(['status_id' => 8]);
        $palette->load(['lines.article_stock']);

        if ($this->validation($line->document->piece)) {
            $line->document->update([
                'status_id' => 8
            ]);
        } elseif ($line->document->status_id != 7) {
            $line->document->update([
                'status_id' => 7
            ]);
        }

        return response()->json($palette);
    }

    public function documentPalettes($piece){
        $document = Document::with(['status', 'palettes' => function($query){
            $query->with('user')->withCount("lines");
        }])->where('piece', $piece)->first();
        if (!$document) {
            return response()->json(['message' => 'No documents found.'], 404);
        }
        return response()->json($document);
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
    public function show($code)
    {
        $palette = Palette::with(['lines.article_stock', 'document', 'user'])->where('code', $code)->first();
        if (!$palette) {
            return response()->json(['error' => "Palette not found"], 404);
        }

        $allConfirmed = !$palette->lines()->wherePivotNull('controlled_at')->exists();

        if ($allConfirmed) {
            $palette->update(['controlled' => true]);
        }

        return response()->json($palette);
    }
    

    public function controller($code, $lineId)
    {
        return DB::transaction(function () use ($code, $lineId) {
            $palette = Palette::with('document.palettes.lines')->where('code', $code)->firstOrFail();
            $palette->lines()->updateExistingPivot(intval($lineId), ['controlled_at' => now()]);

            $allConfirmed = !$palette->lines()->wherePivotNull('controlled_at')->exists();

            if ($allConfirmed) {
                $palette->update(['controlled' => true]);
            }


            $palettes = $palette->document->palettes;

            $controlled = $palettes->every(function ($docPalette) {
                return !$docPalette->lines()->wherePivotNull('controlled_at')->exists();
            });

            if ($controlled) {
                $palette->document->update([
                    'status_id' => 10,
                    'controlled_by' => auth()->id()
                ]);
            }

            return response()->json(['message' => "Article confirmed successfully"]);
        });
    }




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



    public function destroy($id)
    {
        $palette = Palette::findOrFail($id);
        $palette->delete();

        return response()->json(['message' => 'Palette deleted successfully']);
    }



    public function detach(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'line' => 'required|exists:lines,id',
            'palette' => 'required|exists:palettes,code'
        ]);

        // return response()->json(['data' => $request->palette]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $line = Line::find($request->line);
        $palette = Palette::where("code", $request->palette)->first();

        $line->palettes()->detach($palette->id);

        // Update document status if preparation validated_by
        $line->update([
            'status_id' => 7
        ]);
        $line->document->update([
            'status_id' => 7,
        ]);

        $palette->load(['lines.article_stock']);
        return response()->json($palette);
    }
}

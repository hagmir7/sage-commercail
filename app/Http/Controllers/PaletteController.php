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

        if ($document->palettes()->where('company_id', auth()->user()->company_id)->exists()) {
            $query = $document->palettes()->with('lines.article_stock')->where('company_id', auth()->user()->company_id);

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


        $allPalettes = $document->palettes()->with('lines.article_stock')
            ->where('company_id', auth()->user()->company_id)->get();

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
            return response()->json($line);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the scan.',
                'message' => $e->getMessage()
            ], 500);
        }
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
        $document = $line->document;
        $palette = Palette::where("code", $request->palette)->first();

        $line->load(['palettes', 'document']);

        try {
            DB::transaction(function () use ($document, $request, $line, $palette) {
                $totalQte = $line->palettes->sum(function ($palette) {
                    return $palette->pivot->quantity;
                });

                if (intval($line->quantity) < ($totalQte + intval($request->quantity))) {
                    throw new \Exception("Quantity is not valid", 422);
                }

                if ($line->document_id !== $palette->document_id) {
                    throw new \Exception("The document does not match", 404);
                }

                $line->palettes()->attach($palette->id, ['quantity' => $request->quantity]);

                $line->update(['status_id' => 8]);

                $palette->load(['lines.article_stock']);

                // Check if all line with status_id 8 (Prepare)
                if ($document->validation()) {
                    $document->update(['status_id' => 8]);

                } elseif ($document->status_id != 7) {
                    $document->update(['status_id' => 7]);
                }

                if ($document->validationCompany(auth()->user()->company_id)) {

                   $companyId = auth()->user()->company_id;

                    $alreadyAttached = $document->companies()->where('companies.id', $companyId)->exists();

                    if (!$alreadyAttached) {

                        $document->companies()->attach($companyId, [
                            'status_id' => 8,
                            'updated_at' => now()
                        ]);
                    }

                }
            });

            return response()->json($palette);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }


    public function documentPalettes($piece)
    {
       $document = Document::with([
            'status',
            'palettes' => function ($query) {
                $query->with('user')
                    ->where('company_id', auth()->user()->company_id)
                    ->withCount('lines');
            }
        ])->where('piece', $piece)->first();

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
            $palette = Palette::with(['document.palettes.lines', 'document.lines'])->where('code', $code)->firstOrFail();

            $palette->lines()->updateExistingPivot((int) $lineId, ['controlled_at' => now()]);

            $allConfirmed = !$palette->lines()->wherePivotNull('controlled_at')->exists();

            if ($allConfirmed) {
                $palette->update(['controlled' => true]);
            }

            $palette->load('lines'); // Refresh relationship after update


            $user_company = auth()->user()->company_id;

            $allControlledCompany = $palette->document->palettes
                ->where('company_id', $user_company)
                ->every(function ($docPalette) {
                    return !$docPalette->lines()->wherePivotNull('controlled_at')->exists();
                });

            if ($allControlledCompany) {

                $palette->document->companies()->updateExistingPivot($user_company, [
                    'status_id' => 10,
                    'controlled_by' => auth()->id(),
                    'controlled_at' => now()
                ]);

                foreach ($palette->document->lines->where('company_id', $user_company) as $line) {
                    $line->update([
                        'status_id' => 10,
                    ]);
                }

            } else {
                $palette->document->companies()->updateExistingPivot($user_company, [
                    'status_id' => 9,
                    'controlled_at' => now()
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


        if (!$line->document->status_id) {
            $line->document->update([
                'status_id' => 7,
            ]);
        }

        $palette->document->companies()->updateExistingPivot(auth()->user()->company_id, [
            'status_id' => 7,
        ]);


        $palette->load(['lines.article_stock']);
        return response()->json($palette);
    }
}

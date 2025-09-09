<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ArticleStock;
use App\Models\Docligne;
use App\Models\Document;
use App\Models\InventoryStock;
use App\Models\Line;
use App\Models\Palette;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        // Lock table or use transactions if needed for concurrency
        $lastCode = DB::table('palettes')
            ->where('code', 'like', 'PALL%')
            ->orderBy('id', 'desc')
            ->value('code');

        if (!$lastCode) {
            $nextNumber = 1;
        } else {
            // Use 4 because 'PALL' is 4 characters
            $number = (int) substr($lastCode, 4);
            $nextNumber = $number + 1;
        }

        return 'PALL' . str_pad($nextNumber, 8, '0', STR_PAD_LEFT);
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

        // Define the relationships to load consistently
       $relationships = [
        'lines',
        'lines.docligne:cbMarq,DO_Piece,DO_Ref,CT_Num,Hauteur,Largeur,Poignée,Chant,Description,Rotation,Couleur,AR_Ref,Episseur',
        'lines.docligne.article:AR_Ref,Nom,cbMarq,Hauteur,Largeur,Chant,Profonduer,Episseur,Description,AR_Design,Couleur',
        'lines.article_stock:code,name,height,width,depth,color,thickness,chant,description',
    ];


        if ($document->palettes()->where('company_id', auth()->user()->company_id)->exists()) {
            // Palette exists - retrieve it with relationships
            $query = $document->palettes()->with($relationships)->where('company_id', auth()->user()->company_id);

            if (!empty($request->palette)) {
                $palette = $query->where('code', $request->palette)->first();
            } else {
                $palette = $query->first();
            }
        } else {
            // Create new palette
            $palette = Palette::create([
                'code'             => $this->generatePaletteCode(),
                'type'             => 'Livraison',
                'document_id'      => $document->id,
                'company_id'       => auth()->user()->company_id ?? 1,
                'user_id'          => auth()->id(),
                'first_company_id' => auth()->user()->company_id ?? 1,
            ]);

            // Load relationships for the newly created palette
            $palette->load($relationships);
        }

        // Get all palettes for this document and company
        $allPalettes = $document->palettes()
            ->with($relationships)
            ->where('company_id', auth()->user()->company_id)
            ->get();

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
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $document = Document::where('piece', $request->document_id)
            ->with(['palettes.lines'])
            ->first();



        if (!$document) {
            return response()->json([
                'errors' => ['document_id' => "Le document n'existe pas."]
            ], 404);
        }


        if (in_array($document->status_id, [8, 9, 10, 11, 12, 13, 14])) {
            return response()->json([
                'message' => "Le document est en Préparé.",
            ], 400);
        }


        $emptyPalettes = $document->palettes->filter(function ($palette) {
            return $palette->lines->isEmpty();
        });

        if ($emptyPalettes->isNotEmpty()) {
            return response()->json([
                'message' => 'Il existe déjà une palette vide ' . $emptyPalettes->pluck('code'),
            ], 400);
        }

        $palette = Palette::create([
            'code'             => $this->generatePaletteCode(),
            'type'             => 'Livraison',
            'document_id'      => $document->id,
            'company_id'       => auth()->user()->company_id ?? 1,
            'user_id'          => auth()->id(),
            'first_company_id' => auth()->user()->company_id ?? 1,
        ]);

        $newDocument = $palette->document->load(['palettes.lines.article_stock']);

        return response()->json($newDocument, 201);
    }


    public function scanLine(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'line' => 'required|string|max:255',
            'document' => 'nullable|exists:documents,piece'
        ]);


        $document = Document::where('piece', $request->document)->first();

        if ($validator->fails()) {
            return response()->json([
                'message' => "L'article n'existe pas",
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $lineIdentifier = $request->line;
            $line = null;

            if (is_numeric($lineIdentifier)) {
                $line = Line::with([
                    'docligne' => function ($query) {
                        $query->select(
                            "cbMarq", "DO_Piece", "DO_Ref", "CT_Num",
                            "Hauteur", "Largeur", "Chant", "Poignée",
                            "Description", "Rotation", "Couleur", "AR_Ref", 'Profondeur'
                        )->with(['article' => function ($q) {
                            $q->select("AR_Ref", "Nom", 'cbMarq', 'Hauteur', 'Largeur', 'Chant', 'Profonduer', 'Episseur', 'Description', 'AR_Design', 'Couleur'); 
                        }]);
                    },
                    'article_stock' => function ($query) {
                        $query->select("code", "name", "height", "width", "depth", "color", "thickness", "chant");
                    }
                ])->find($lineIdentifier);
            }

        // If not found by ID, try to find by article stock reference
            if (!$line) {
                $line = Line::with([
                    'docligne' => function ($query) {
                        $query->select(
                            "cbMarq", "DO_Piece", "DO_Ref", "CT_Num",
                            "Hauteur", "Largeur", "Poignée", "Chant",
                            "Description", "Rotation", "Couleur", "AR_Ref", "Profondeur"
                        )->with(['article' => function ($q) {
                            $q->select("AR_Ref", "Nom", 'cbMarq', 'Hauteur', 'Largeur', 'Chant', 'Profonduer', 'Episseur', 'Description', 'AR_Design', "Couleur"); 
                        }]);
                    },
                    'article_stock' => function ($query) {
                        $query->select("code", "name", "height", "width", "depth", "color", "thickness", "chant", "description");
                    }
                ])->whereHas('article_stock', function ($query) use ($lineIdentifier) {
                    $query->where('code', $lineIdentifier);
                })->where("document_id", $document->id)->where('status_id', 7)->get();
            }

            // Check if line exists
            if (!$line) {
                return response()->json(['message' => "L'article n'existe pas"], 422);
            }

            // Check for special case SP000001
            // if (
            //     $line->ref === 'SP000001' &&
            //     (empty($line->design) || strtolower(trim($line->design)) === 'special')
            // ) {
            //     return response()->json(['message' => "L'article n'existe pas SP000001"], 422);
            // }

            return response()->json($line);
        } catch (\Exception $e) {
            \Log::error('Error in scanLine method', [
                'line_identifier' => $request->line ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'An error occurred while processing the scan.',
                'message' => $e->getMessage()
            ], 500);
        }
    }





    public function scanPalette($code)
    {
        try {
            $palette = Palette::where('code', $code)
                ->select("id", 'code', 'type', 'company_id', 'document_id')
                ->withCount('lines')
                ->with(['company', 'lines' => function ($query) {
                    $query->select('lines.id', 'lines.quantity');
                }, 'document'])
                ->first();

            if (!$palette) {
                return response()->json([
                    'error' => 'Palette not found',
                    'message' => 'No palette found with the provided code.'
                ], 404);
            }

            $palette['quantity'] = $palette->lines->sum('quantity');

            return response()->json($palette);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the scan.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function confirmPalette($code, $piece)
    {
        try {

            $document = Document::with('palettes')->where("piece", $piece)->first();

            if (!$document) {
                return response()->json([
                    'error' => 'Document not found',
                    'message' => 'Document non trouvée'
                ], 404);
            }

            $palette = $document->palettes->where('code', $code)->first();

            if (!$palette) {
                return response()->json([
                    'error' => 'Palette not found',
                    'message' => 'Aucune palette dans le document '
                ], 404);
            }

            // Update delivery timestamp
            $palette->delivered_at = now();
            $palette->save();

            // Check if all palettes are delivered
            $allPalettesDelivered = $document->palettes->every(fn($p) => !is_null($p->delivered_at));

            // Add status if all delivered
            if ($allPalettesDelivered) {
                $document->update([
                    'status_id' => 14
                ]);
            }

            // Add delivery status to palette response
            $palette->all_palettes_delivered = $allPalettesDelivered;

            return response()->json($palette);
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
            'palette' => 'required|exists:palettes,code',
            'emplacement' => 'nullable|exists:emplacements,code'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
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
                    throw new \Exception("La quantité n'est pas valide", 422);
                }

                if ($line->document_id !== $palette->document_id) {
                    throw new \Exception("Le document ne correspond pas", 404);
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
                    $document->companies()->updateExistingPivot(auth()->user()->company_id, [
                        'status_id' => 8,
                        'updated_at' => now()
                    ]);
                }


                $article_stock = ArticleStock::find($line->ref);
                if($article_stock){
                    $article_stock->quantity = ($article_stock->quantity + floatval($request->quantity));
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
  
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }


        $documents = Document::with([
            'status',
            'palettes' => function ($query) {
                $query->with('user')->withCount('lines');

                if (!auth()->user()->hasRole(['admin', 'super_admin', 'commercial'])) {
                    $query->where('company_id', auth()->user()->company_id);
                }
            }
        ])
            ->where('piece', $piece)
            ->orWhere('piece_bl', $piece)
            ->orWhere('piece_fa', $piece)
            ->get();

        // Log if more than one found
        if ($documents->count() > 1) {
            \Log::alert("There is more than one document for this piece: " . $piece);
        }


        $document = $documents->first();

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
     $palette = Palette::with([
            'lines.docligne:DL_No,cbMarq,AR_Ref,Nom,DL_Design,Description,Hauteur,Largeur,Profondeur,Couleur,Chant,Episseur,DL_Qte,Poignée',
            'document',
            'user',
            'lines.docligne.article:cbMarq,AR_Ref,Nom,Hauteur,Largeur,Couleur,Profonduer,Episseur,Chant'
        ])->where('code', $code)->first();

        if (!$palette) {
            return response()->json(['message' => "Palette not found"], 404);
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


            $document = Line::find($lineId)->document;
            if ($allConfirmed) {
                $palette->update(['controlled' => true]);

                $document->update([
                    'status_id' => 10
                ]);
            } else {
                $document->update([
                    'status_id' => 9
                ]);
            }

            $palette->load('lines');


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

    public function destroy(Palette $palette)
    {
        try {
            DB::transaction(function () use ($palette) {

                if ($palette->type == "Stock") {

                    if ($palette->articles()->exists()) {
                        $palette->articles()->detach();
                    }

                    if ($palette->inventoryArticles()->exists()) {

                        $inventoryArticles = DB::table('inventory_article_palette')
                            ->where('palette_id', $palette->id)
                            ->get();

                        foreach ($inventoryArticles as $item) {
                            $inventoryStock = \App\Models\InventoryStock::find($item->inventory_stock_id);
                            if ($inventoryStock) {
                                $inventoryStock->update([
                                    'quantity' => $inventoryStock->quantity - $item->quantity
                                ]);
                            }
                        }
                        $palette->inventoryArticles()->detach();
                    }
                }

                $palette->delete();
            });

            return response()->json(['message' => 'Palette supprimée avec succès'], 200);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de la palette: ' . $e->getMessage(), [
                'palette_id' => $palette->id,
                'palette_code' => $palette->code
            ]);

            return response()->json([
                'message' => 'Erreur lors de la suppression de la palette'
            ], 500);
        }
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

    public function resetPalette($code)
    {

        $palette = Palette::where("code", $code)->first();

        if (!$palette) {
            return response()->json([
                'error' => 'Palette not found',
                'message' => 'Aucune palette dans le document '
            ], 404);
        }

        $palette->update([
            'delivered_at' => null,
        ]);
        return ['message' => "Palette supprimée avec succès"];
    }


    // Article Palette function
    public function detachArticle($code, $article_id)
    {
        try {
            $palette = Palette::where('code', $code)->firstOrFail();
            $result = DB::selectOne(
                "SELECT quantity FROM article_palette WHERE article_stock_id = ? AND palette_id = ?",
                [$article_id, $palette->id]
            );
            if (!$result) {
                throw new \Exception("Article not found in this palette");
            }

            $quantity = $result->quantity;
            $article = ArticleStock::find($article_id);
            $inventoryStock = InventoryStock::where('code_article', $article->code)->first();

            if ($inventoryStock->quantity < $quantity) {
                throw new \Exception("Insufficient inventory quantity");
            }

            DB::transaction(function () use ($inventoryStock, $quantity, $palette, $article_id) {
                $inventoryStock->update([
                    'quantity' => $inventoryStock->quantity - $quantity
                ]);
                $palette->articles()->detach($article_id);
            });
            return [
                'success' => true,
                'message' => 'Article detached successfully',
                'detached_quantity' => $quantity
            ];
        } catch (\Exception $e) {
            Log::error('Error detaching article: ' . $e->getMessage(), [
                'code' => $code,
                'article_id' => $article_id
            ]);
            return [
                'success' => false,
                'message' => 'Failed to detach article: ' . $e->getMessage()
            ];
        }
    }


    public function updateArticleQuantity(Request $request, $code, $article_id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quantity' => "numeric|required|min:0.001"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => "La quantité n'est pas valide",
                    'errors' => $validator->errors()
                ], 422);
            }

            $newQuantity = $request->quantity;

            $palette = Palette::where('code', $code)->firstOrFail();

            $currentPivotData = DB::selectOne(
                "SELECT quantity FROM article_palette WHERE article_stock_id = ? AND palette_id = ?",
                [$article_id, $palette->id]
            );

            if (!$currentPivotData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Article non trouvé dans cette palette'
                ], 404);
            }

            $oldQuantity = $currentPivotData->quantity;
            $quantityDifference = $newQuantity - $oldQuantity;

            $article = ArticleStock::find($article_id);
            $inventoryStock = InventoryStock::where('code_article', $article->code)->first();

            DB::transaction(function () use ($inventoryStock, $quantityDifference, $palette, $article_id, $newQuantity) {
                if ($quantityDifference > 0) {

                    $inventoryStock->update([
                        'quantity' => $inventoryStock->quantity + $quantityDifference
                    ]);
                } elseif ($quantityDifference < 0) {
                    $inventoryStock->update([
                        'quantity' => $inventoryStock->quantity - abs($quantityDifference)
                    ]);
                }

                $palette->articles()->updateExistingPivot($article_id, [
                    'quantity' => $newQuantity
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Quantité mise à jour avec succès',
                'data' => [
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $newQuantity,
                    'quantity_difference' => $quantityDifference,
                    'inventory_updated' => $quantityDifference !== 0
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Model not found in updateArticleQuantity: ' . $e->getMessage(), [
                'code' => $code,
                'article_id' => $article_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Palette ou article non trouvé'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error updating article quantity: ' . $e->getMessage(), [
                'code' => $code,
                'article_id' => $article_id,
                'quantity' => $request->quantity ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la quantité'
            ], 500);
        }
    }


    public function detachArticleForInvenotry($code, $article_id)
    {
        try {
            $palette = Palette::where('code', $code)->firstOrFail();
            $result = DB::selectOne(
                "SELECT quantity FROM article_palette WHERE article_stock_id = ? AND palette_id = ?",
                [$article_id, $palette->id]
            );
            if (!$result) {
                throw new \Exception("Article not found in this palette");
            }

            $quantity = $result->quantity;
            $article = ArticleStock::find($article_id);
            $inventoryStock = InventoryStock::where('code_article', $article->code)->first();

            if ($inventoryStock->quantity < $quantity) {
                throw new \Exception("Insufficient inventory quantity");
            }

            DB::transaction(function () use ($inventoryStock, $quantity, $palette, $article_id) {
                $inventoryStock->update([
                    'quantity' => $inventoryStock->quantity - $quantity
                ]);
                $palette->articles()->detach($article_id);
            });
            return [
                'success' => true,
                'message' => 'Article detached successfully',
                'detached_quantity' => $quantity
            ];
        } catch (\Exception $e) {
            Log::error('Error detaching article: ' . $e->getMessage(), [
                'code' => $code,
                'article_id' => $article_id
            ]);
            return [
                'success' => false,
                'message' => 'Failed to detach article: ' . $e->getMessage()
            ];
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleSupplier;
use App\Models\Collaborator;
use App\Models\CurrentPiece;
use App\Models\PurchaseDocument;
use App\Models\PurchaseLine;
use App\Models\PurchaseLineFile;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Schema;

class PurchaseDocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = PurchaseDocument::with(['user', 'service'])
            ->withCount('lines');

        /* ---------------- Role-based visibility ---------------- */
        if ($user->hasRole(['admin', 'supper_admin'])) {
            // See all
        } elseif ($user->hasRole('chef_service')) {
            $query->where(function ($q) use ($user) {
                $q->where('service_id', $user->service_id)
                    ->orWhere('user_id', $user->id);
            });
        } else {
            $query->where('user_id', $user->id);
        }

        /* ---------------- Filters ---------------- */
        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('status', $request->status);
        });

        $query->when($request->filled('service'), function ($q) use ($request) {
            $q->where('service_id', $request->service);
        });

        $query->when($request->filled('user'), function ($q) use ($request) {
            $q->where('user_id', $request->user);
        });

        $query->when(
            is_array($request->date_filter) && count($request->date_filter) === 2,
            function ($q) use ($request) {
                $q->whereBetween('created_at', [
                    Carbon::parse($request->date_filter[0])->startOfDay(),
                    Carbon::parse($request->date_filter[1])->endOfDay(),
                ]);
            }
        );

        /* ---------------- Result ---------------- */
        return response()->json(
            $query->latest()->paginate(40)
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // --- Document fields ---
            'piece' => 'nullable|string',
            'note' => 'nullable|string',
            'urgent' => 'boolean',
            'reference' => 'required|string',
            'status' => 'nullable|integer|min:1|max:8',
            'service_id' => 'nullable|exists:services,id',
            'user_id' => 'nullable|exists:users,id',

            // --- Lines array ---
            'lines' => 'required|array|min:1',
            'lines.*.code' => 'nullable|string',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:1',
            'lines.*.unit' => 'nullable|string',
            'lines.*.estimated_price' => 'nullable|numeric',

            // --- Files for each line ---
            'lines.*.files' => 'nullable|array',
            'lines.*.files.*' => 'nullable|file|max:100240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,ods',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => $validator->errors()->first()
            ], 422);
        }

        $validated = $validator->validated();

            $validated['service_id'] = $validated['service_id']
                ?? auth()->user()?->service_id;

        DB::beginTransaction();

        try {
            // 1️⃣ Create Purchase Document
            $document = PurchaseDocument::create([
                'piece' => $validated['piece'] ?? null,
                'note' => $validated['note'] ?? null,
                'urgent' => $validated['urgent'] ?? false,
                'reference' => $validated['reference'],
                'status' => $validated['status'] ?? 1,
                'service_id' => $validated['service_id'] ?? null,
                'user_id' => $validated['user_id'] ?? null,
                'planned_at' => $request->planned_at,
                'sended_at' => intval($validated['status']) > 1 ? now() : null
            ]);

            // 2️⃣ Loop through lines and create them
            foreach ($validated['lines'] as $lineData) {
                $files = $lineData['files'] ?? [];
                unset($lineData['files']); 

                $lineData['purchase_document_id'] = $document->id;
                $line = PurchaseLine::create($lineData);

                // 3️⃣ Handle files for this line
                foreach ($files as $file) {
                    $path = $file->store('purchase_files', 'public');

                    PurchaseLineFile::create([
                        'purchase_line_id' => $line->id,
                        'file_path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                    ]);
                }
            }

            DB::commit();

            $document->load('lines.files');

            return response()->json([
                'message' => 'Document, lines, and files created successfully.',
                'document' => $document,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred during saving.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(PurchaseDocument $purchaseDocument)
    {
        $purchaseDocument->load(['user', 'service', 'lines.files']);
        return response()->json($purchaseDocument);
    }



    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            // --- Document fields ---
            'piece' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'reference' => 'required|string',
            'urgent' => 'boolean',
            'status' => 'nullable|numeric',

            // --- Lines ---
            'lines' => 'required|array|min:1',
            'lines.*.id' => 'nullable|integer|exists:purchase_lines,id',
            'lines.*.code' => 'nullable|string|max:100',
            'lines.*.description' => 'required|string|max:1000',
            'lines.*.quantity' => 'required|numeric|min:0',
            'lines.*.unit' => 'nullable|string|max:50',
            'lines.*.estimated_price' => 'nullable|numeric|min:0',
            'service_id' => 'nullable|exists:services,id',

            // --- Files per line ---
            'lines.*.files.*' => 'nullable|file|max:100240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,ods',
            'lines.*.existing_file_ids' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();

        try {
            // 1️⃣ Load document with related lines and files
            $document = PurchaseDocument::with('lines.files')->findOrFail($id);

            // 2️⃣ Update document fields
            $document->update([
                'piece' => $validated['piece'] ?? $document->piece,
                'note' => $validated['note'] ?? $document->note,
                'urgent' => $validated['urgent'] ?? false,
                'reference' => $validated['reference'] ??  $document->reference,
                'status' => $validated['status'] ?? $document->status,
                'service_id' => $validated['service_id'] ?? $document->status,
                'planned_at' => $request->planned_at ?? null
            ]);

            if (intval($document->status) == 2) {
                $document->update([
                    'sended_at' =>  now() 
                ]);
            }elseif(intval($document->status) == 1){
                $document->update([
                    'sended_at' =>  null
                ]);
            }

            $updatedLineIds = [];

            // 3️⃣ Loop over each line
            foreach ($validated['lines'] as $index => $lineData) {
                // Separate files from line data
                $lineFiles = $request->file("lines.$index.files", []);
                if (!is_array($lineFiles)) {
                    $lineFiles = [$lineFiles];
                }

                // Update or create line
                if (!empty($lineData['id'])) {
                    $line = PurchaseLine::find($lineData['id']);
                    if ($line && $line->purchase_document_id == $document->id) {
                        $line->update([
                            'code' => $lineData['code'] ?? $line->code,
                            'description' => $lineData['description'],
                            'quantity' => $lineData['quantity'],
                            'unit' => $lineData['unit'] ?? $line->unit,
                            'estimated_price' => $lineData['estimated_price'] ?? $line->estimated_price,
                        ]);
                    } else {
                        continue;
                    }
                } else {
                    $lineData['purchase_document_id'] = $document->id;
                    $line = PurchaseLine::create($lineData);
                }

                $updatedLineIds[] = $line->id;

                // 4️⃣ Add new uploaded files
                foreach ($lineFiles as $file) {
                    if ($file && $file->isValid()) {
                        $path = $file->store('purchase_files', 'public');
                        PurchaseLineFile::create([
                            'purchase_line_id' => $line->id,
                            'file_path' => $path,
                            'original_name' => $file->getClientOriginalName(),
                            'file_size' => $file->getSize(),
                            'mime_type' => $file->getMimeType(),
                        ]);
                    }
                }

                // 5️⃣ Delete old files that are not in existing_file_ids
                if (isset($lineData['existing_file_ids'])) {
                    $existingFileIds = array_filter($lineData['existing_file_ids']);
                    $filesToDelete = $line->files()
                        ->whereNotIn('id', $existingFileIds)
                        ->get();

                    foreach ($filesToDelete as $file) {
                        if (Storage::disk('public')->exists($file->file_path)) {
                            Storage::disk('public')->delete($file->file_path);
                        }
                        $file->delete();
                    }
                }
            }


            $linesToDelete = PurchaseLine::where('purchase_document_id', $document->id)
                ->whereNotIn('id', $updatedLineIds)
                ->get();

            foreach ($linesToDelete as $line) {
                foreach ($line->files as $file) {
                    if (Storage::disk('public')->exists($file->file_path)) {
                        Storage::disk('public')->delete($file->file_path);
                    }
                    $file->delete();
                }
                $line->delete();
            }

            DB::commit();

            // Reload document
            $document->load('lines.files', 'service', 'user');

            return response()->json([
                'message' => 'Document mis à jour avec succès.',
                'document' => $document,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Purchase document update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de la mise à jour.',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur serveur',
            ], 500);
        }
    }




    public function updateStatus(Request $request, PurchaseDocument $purchaseDocument)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => $validator->errors()->first()
            ], 422);
        }

        $validated = $validator->validated();

        $purchaseDocument->update($validated);

        return response()->json($purchaseDocument);
    }

    public function destroy(PurchaseDocument $purchaseDocument)
    {
        $purchaseDocument->delete();
        return response()->json(['message' => 'Document supprimé avec succès.']);
    }


    public function statusCount($status)
    {
        return PurchaseDocument::where('status', $status)->count();
    }

    public function checkArticles($company_db, $document_id)
    {
        $purchaseDocument = PurchaseDocument::find($document_id);

        $articleCodes = $purchaseDocument->lines->pluck('code')->filter()->unique();

        $existingCodes = DB::connection($company_db)
            ->table('F_ARTICLE')
            ->whereIn('AR_Ref', $articleCodes)
            ->pluck('AR_Ref')
            ->toArray();

        $missingCodes = $articleCodes->diff($existingCodes);

        if ($missingCodes->isNotEmpty()) {
            return response()->json([
                'message' => 'Some article codes do not exist in F_ARTICLE',
                'missing_codes' => $missingCodes->values(),
            ], 400);
        }
    }


    public function generatePiece($DO_Souche): string
    {
        $prefix = $DO_Souche == 2 ? 'BDA' : 'DA';
        $year   = date('y');
        $souche = $DO_Souche == 0 ? 19 : 36;

        $lastPiece = CurrentPiece::on('sqlsrv_inter')->where('cbMarq', $souche)->first()->DC_Piece;

        $nextNumber = 1;

        if ($lastPiece) {
            $pattern = '/^' . preg_quote($year . $prefix, '/') . '(\d{6})$/i';

            if (preg_match($pattern, $lastPiece, $matches)) {
                $nextNumber = (int)$matches[1];
            }
        }
        CurrentPiece::on('sqlsrv_inter')->where('cbMarq', $souche)
            ->first()
            ->update(['DC_Piece' => sprintf('%s%s%06d', $year, $prefix, $nextNumber + 1)]);
        return sprintf('%s%s%06d', $year, $prefix, $nextNumber);
    }

    public function transfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'souche' => 'required|integer|in:0,1,2',
            'reference' => 'required|string|max:17',
            'supplier' => 'required|string|max:10',
            'company_db' => 'required|string|max:30',
            'lines' => 'required|array|min:1',
            'devise' => 'required|integer',
        ]);



        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $purchaseDocument = PurchaseDocument::find($request->document_id);
            $piece = $this->generatePiece($request->souche);

            $this->checkArticles($request->company_db, $request->document_id);

            $DO_Date = $this->createDocentete(
                $piece,
                $request->supplier,
                $request->reference,
                $request->souche,
                $request->devise,
                $request->company_db,
                $purchaseDocument->planned_at,
                $purchaseDocument->urgent,
                $purchaseDocument->user
            );

            $documentArticles = $purchaseDocument->lines->pluck('code');

            $existingArticles = Article::on($request->company_db)
                ->whereIn('AR_Ref', $documentArticles)
                ->pluck('AR_Ref');


            $missing = $documentArticles->diff($existingArticles);

            if ($missing->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Certains articles sont manquants dans la base de données.' . $missing->values(),
                    'missing_codes' => $missing->values(),
                ], 404);
            }

            foreach ($request->lines as $line) {


                $this->createDocligne(
                    $request->company_db,
                    $piece,
                    $request->reference,
                    $request->supplier,
                    1,
                    10,
                    $line['code'] ?? '',
                    $line['description'],
                    $line['quantity'],
                    $line['unit'] ?? '',

                );
            }

            $lastDRNo = DB::connection($request->company_db)
                ->table('F_DOCREGL')
                ->max('DR_No');

            $newDRNo = ($lastDRNo ?? 0) + 1;

            DB::connection($request->company_db)->table('F_DOCREGL')->insert([
                'DR_No' => $newDRNo,
                'DO_Domaine' => 1,
                'DO_Type' => 10,
                'DO_Piece' => $piece,
                'DR_TypeRegl' => 2,
                'DR_Date' => $DO_Date,
                'DR_Pourcent' => 0.0,
                'DR_Montant' => 0.0,
                'DR_MontantDev' => 0.0,
                'DR_Equil' => 1,
                'EC_No' => 0,
                'DR_Regle' => 0,
                'N_Reglement' => 1,
                'CA_No' => 0,
                'DO_DocType' => 10,
                'cbProt' => 0,
                'cbCreateur' => 'ERP1',
                'cbModification' => DB::raw('GETDATE()'),
                'cbReplication' => 0,
                'cbFlag' => 0,
                'cbCreation' => DB::raw('GETDATE()'),
                'cbHashVersion' => 1,
                'DR_Libelle' => '',
                'DR_AdressePaiement' => '',
                'cbCreationUser' => "77384016-921F-472F-B56D-1D563B7DDF3C"
            ]);

            if ($purchaseDocument->status < 4) {
                $purchaseDocument->update([
                    'status' => 4,
                ]);
            }

            $existing = $purchaseDocument->document_pieces ?? '';

            $purchaseDocument->update([
                'document_pieces' => $existing
                    ? $existing . ',' . $piece
                    : $piece
            ]);

            
            return response()->json([
                'message' => 'Transfer successful',
                'piece' => $piece
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Transfer failed: " . $e->getMessage(), [
                'souche' => $request->souche,
                'reference' => $request->reference,
                'supplier' => $request->supplier,
                'company_db' => $request->company_db
            ]);

            return response()->json([
                'message' => 'Transfer failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function createDocentete(string $DO_Piece, string $DO_Tiers, string $DO_Ref, $DO_Souche, $DO_Devise, $company_db, $planned, $urgent, $user): string
    {
        try {

            if ($company_db == 'sqlsrv') {
                $DO_Date  = date('Y-m-d') . ' 00:00:00.000';
                $currentDateTime = date('Y-m-d H:i:s.000');
            } else {
                $DO_Date  = date('Y-d-m') . ' 00:00:00.000';
                $currentDateTime = date('Y-d-m H:i:s.000');
            }

            if ($planned) {
                $type = "Planifiée";
            } elseif ($urgent) {
                $type = "Urgente";
            } else {
                $type = "Normale";
            }


            $DO_Heure = $this->generateHeure();

            $collaborateur = Collaborator::on($company_db)->where('CO_Matricule', $user->code)->first();


            $data = [
                'DO_Domaine'            => 1,
                'DO_Type'               => 10,
                'DO_Piece'              => $DO_Piece,
                'DO_Date'               => $DO_Date,
                'DO_Ref'                => $DO_Ref,
                'DO_Tiers'              => $DO_Tiers,
                'CO_No'                 => $collaborateur ? $collaborateur->CO_No : null,
                'cbCO_No'               => $collaborateur ? $collaborateur->CO_No : null,
                'DO_Period'             => 1,
                'DO_Devise'             => $DO_Devise,
                'DO_Cours'              => 1.000000,
                'DE_No'                 => 1,
                'cbDE_No'               => 1,   
                'LI_No'                 => 0,
                'cbLI_No'               => null,
                'CT_NumPayeur'          => $DO_Tiers,
                'DO_Expedit'            => 1,
                'DO_NbFacture'          => 1,
                'DO_BLFact'             => 0,
                'DO_TxEscompte'         => 0.000000,
                'DO_Reliquat'           => 0,
                'DO_Imprim'             => 0,
                'CA_Num'                => '',
                'DO_Coord01'            => '',
                'DO_Coord02'            => '',
                'DO_Coord03'            => '',
                'DO_Coord04'            => '',
                'DO_Souche'             => $DO_Souche,
                'DO_DateLivr'           => $currentDateTime,
                'DO_Condition'          => 1,
                'DO_Tarif'              => 1,
                'DO_Colisage'           => 1,
                'DO_TypeColis'          => 1,
                'DO_Transaction'        => 11,
                'DO_Langue'             => 0,
                'DO_Ecart'              => 0.000000,
                'DO_Regime'             => 11,
                'N_CatCompta'           => 5,
                'DO_Ventile'            => 0,
                'AB_No'                 => 0,
                'DO_DebutAbo'           => '1753-01-01 00:00:00.000',
                'DO_FinAbo'             => '1753-01-01 00:00:00.000',
                'DO_DebutPeriod'        => '1753-01-01 00:00:00.000',
                'DO_FinPeriod'          => '1753-01-01 00:00:00.000',
                'CG_Num'                => '44110000',
                'DO_Statut'             => 0,
                'DO_Heure'              => $DO_Heure,
                'CA_No'                 => 0,
                'CO_NoCaissier'         => 0,
                'DO_Transfere'          => 0,
                'DO_Cloture'            => 0,
                'DO_NoWeb'              => '',
                'DO_Attente'            => 0,
                'DO_Provenance'         => 0,
                'CA_NumIFRS'            => '',
                'MR_No'                 => 0,
                'DO_TypeFrais'          => 0,
                'DO_ValFrais'           => 0.000000,
                'DO_TypeLigneFrais'     => 0,
                'DO_TypeFranco'         => 0,
                'DO_ValFranco'          => 0.000000,
                'DO_TypeLigneFranco'    => 0,
                'DO_Taxe1'              => 0.000000,
                'DO_TypeTaux1'          => 0,
                'DO_TypeTaxe1'          => 0,
                'DO_Taxe2'              => 0.000000,
                'DO_TypeTaux2'          => 0,
                'DO_TypeTaxe2'          => 0,
                'DO_Taxe3'              => 0.000000,
                'DO_TypeTaux3'          => 0,
                'DO_TypeTaxe3'          => 0,
                'DO_MajCpta'            => 0,
                'DO_Motif'              => '',
                'DO_Contact'            => '',
                'DO_FactureElec'        => 0,
                'DO_TypeTransac'        => 0,
                'DO_DateLivrRealisee'   => '1753-01-01 00:00:00.000',
                'DO_DateExpedition'     => '1753-01-01 00:00:00.000',
                'DO_FactureFrs'         => '',
                'DO_PieceOrig'          => '',
                'DO_EStatut'            => 0,
                'DO_DemandeRegul'       => 0,
                'ET_No'                 => 0,
                'DO_Valide'             => 0,
                'DO_Coffre'             => 0,
                'DO_TotalHT'            => 0.000000,
                'DO_StatutBAP'          => 0,
                'DO_Escompte'           => 0,
                'DO_DocType'            => 10,
                'DO_TypeCalcul'         => 0,
                'DO_FactureFile'        => null,
                'DO_TotalHTNet'         => 0.000000,
                'DO_TotalTTC'           => 0.000000,
                'DO_NetAPayer'          => 0.000000,
                'DO_MontantRegle'       => 0.000000,
                'DO_RefPaiement'        => null,
                'DO_AdressePaiement'    => '',
                'DO_PaiementLigne'      => 0,
                'DO_MotifDevis'         => 0,
                'DO_Conversion'         => 0,
                'cbProt'                => 0,
                'cbCreateur'            => 'ERP1',
                'cbModification'        => $currentDateTime,
                'cbReplication'         => 0,
                'cbFlag'                => 0,
                'cbCreation'            => $currentDateTime,
                'cbCreationUser'        => '77384016-921F-472F-B56D-1D563B7DDF3C',
                'cbHash'                => null,
                'cbHashVersion'         => 1,
                'cbHashDate'            => null,
                'cbHashOrder'           => null,
                // 'Priorité'              => $type,
            ];

            if (Schema::connection($company_db)->hasColumn('F_DOCENTETE', 'Priorité')) {
                $data['Priorité'] = $type;
            }


            DB::connection($company_db)->table('F_DOCENTETE')->insert($data);

            return $DO_Date;
        } catch (Exception $e) {
            \Log::error('Docentete creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function generateHeure(): string
    {
        $now = new DateTime();
        $timeString = $now->format('His'); // HHmmss
        $timeString = str_pad($timeString, 9, '0', STR_PAD_LEFT);
        return $timeString;
    }

    public function createDocligne($company_db, $DO_Piece, $DO_Ref, $CT_Num, $DO_Domaine, $DO_Type, $AR_Ref, $DL_Design, $DL_Qte, $EU_Enumere)
    {
        if ($company_db == 'sqlsrv') {
            $DO_Date  = date('Y-m-d') . ' 00:00:00.000';
            $currentDateTime = date('Y-m-d H:i:s.000');
        } else {
            $DO_Date  = date('Y-d-m') . ' 00:00:00.000';
            $currentDateTime = date('Y-d-m H:i:s.000');
        }

        $article_supplier = ArticleSupplier::on($company_db)->where('AR_Ref', $AR_Ref)->where('CT_Num', $CT_Num)->first();


        $nextDLNo = DB::connection($company_db)
            ->table('F_DOCLIGNE')
            ->max('DL_No') + 1;
        DB::connection($company_db)->table('F_DOCLIGNE')->insert([
            'DO_Domaine' => $DO_Domaine,
            'DO_Type' => $DO_Type,
            'CT_Num' => substr($CT_Num, 0, 17),
            'DO_Piece' => substr($DO_Piece, 0, 13),
            'DO_Date' => $DO_Date,
            'DL_DateBC' => '1753-01-01 00:00:00',
            'DL_DateBL' => '1753-01-01 00:00:00',
            'AF_RefFourniss' => $article_supplier?->AF_RefFourniss,
            'DL_Ligne' => 1000,
            'DO_Ref' => $DO_Ref,
            'DL_TNomencl' => 0,
            'DL_TRemPied' => 0,
            'DL_TRemExep' => 0,
            'AR_Ref' => $AR_Ref,
            'DL_Design' => substr($DL_Design, 0, 69),
            'DL_Qte' => $DL_Qte,
            'DL_QteBC' => $DL_Qte,
            'DL_QteBL' => 0.000000,
            'DL_PoidsNet' => 0.000000,
            'DL_PoidsBrut' => 0.000000,
            'DL_Remise01REM_Valeur' => 0.000000,
            'DL_Remise01REM_Type' => 0,
            'DL_Remise02REM_Valeur' => 0.000000,
            'DL_Remise02REM_Type' => 0,
            'DL_Remise03REM_Valeur' => 0.000000,
            'DL_Remise03REM_Type' => 0,
            'DL_PrixUnitaire' => 0.000000,
            'DL_PUBC' => 0.000000,
            'DL_Taxe1' => 0,
            'DL_TypeTaux1' => 0,
            'DL_TypeTaxe1' => 0,
            'DL_Taxe2' => 0,
            'DL_TypeTaux2' => 0,
            'DL_TypeTaxe2' => 0,
            // 'cbCO_No' => 0,
            'AG_No1' => 0,
            'AG_No2' => 0,
            'DL_PrixRU' => 0.000000,
            'DL_CMUP' => 0.000000,
            'DL_MvtStock' => 0,
            'DT_No' => 0,
            'cbDT_No' => 0,
            'EU_Enumere' => $EU_Enumere,
            'EU_Qte' => $DL_Qte,
            'DL_TTC' => 0,
            'DE_No' => 1,
            'cbDE_No' => 1,
            'DL_NoRef' => 0,
            'DL_TypePL' => 0,
            'DL_PUDevise' => 0.000000,
            'DL_PUTTC' => 0,
            'DL_No' => $nextDLNo,
            'DO_DateLivr' => null,
            'DL_Taxe3' => 0.000000,
            'DL_TypeTaux3' => 0,
            'DL_TypeTaxe3' => 0,
            'DL_Frais' => 0.000000,
            'DL_Valorise' => 1,
            'DL_NonLivre' => 0,
            'AC_RefClient' => '',
            'DL_MontantHT' => 0.000000,
            'DL_MontantTTC' => 0.000000,
            'DL_FactPoids' => 0,
            'DL_Escompte' => 0,
            'DL_DatePL' => '1753-01-01 00:00:00',
            'DL_QtePL' => 0.000000,
            'DL_NoColis' => '0',
            'DL_QteRessource' => 0.000000,
            'DL_DateAvancement' => '1753-01-01 00:00:00',
            'DL_PieceOFProd' => 0,
            'DL_DateDE' => $currentDateTime,
            'DL_QteDE' => $DL_Qte,
            'DL_Operation' => '0',
            'DL_NoSousTotal' => 0,
            'CA_No' => 0,
            'DO_DocType' => 10,
            'cbProt' => 0,
            'cbCreateur' => substr('ERP1', 0, 4),
            'cbModification' => $currentDateTime,
            'cbReplication' => 0,
            'cbFlag' => 0,
            'cbCreation' => $currentDateTime,
            'cbCreationUser' => '77384016-921F-472F-B56D-1D563B7DDF3C',
            'PF_Num' => 0,
        ]);
    }

    public function download($id)
    {
        $file = PurchaseLineFile::find($id);

        if (!$file) {
            return response()->json(['message' => 'Fichier non trouvé'], 404);
        }

        // Check if file exists in storage
        if (!Storage::disk('public')->exists($file->file_path)) {
            return response()->json(['message' => 'Fichier introuvable sur le serveur'], 404);
        }

        // Force download with original filename
        return Storage::disk('public')->download($file->file_path, $file->file_name);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\PurchaseDocument;
use App\Models\PurchaseLine;
use App\Models\PurchaseLineFile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PurchaseDocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = PurchaseDocument::with(['user', 'service'])->withCount('lines');

        if ($user->hasRole(['admin', 'supper_admin'])) {
            // Admins see all
        } elseif ($user->hasRole(['chef_service'])) {
            $query->whereHas('service')->where('service_id', $user->service_id)
                ->orWhere('user_id', $user->id);
        } else {
            $query->where('user_id', $user->id);
        }

        $query->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('date'), function ($q) use ($request) {
                $date = Carbon::parse($request->interview_date)->toDateString();
                $q->whereDate('created_at', $date);
            });

        $documents = $query->latest()->paginate(40);
        return response()->json($documents);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // --- Document fields ---
            'piece' => 'nullable|string',
            'note' => 'nullable|string',
            'urgent' => 'boolean',
            'reference' => 'required|string',

            // --- Lines array ---
            'lines' => 'required|array|min:1',
            'lines.*.code' => 'nullable|string',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:1',
            'lines.*.unit' => 'nullable|string',
            'lines.*.estimated_price' => 'nullable|numeric',

            // --- Files for each line ---
            'lines.*.files' => 'nullable|array',
            'lines.*.files.*' => 'nullable|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif',
        ]);



        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => $validator->errors()->first()
            ], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();

        try {
            // 1️⃣ Create Purchase Document
            $document = PurchaseDocument::create([
                'piece' => $validated['piece'] ?? null,
                'note' => $validated['note'] ?? null,
                'urgent' => $validated['urgent'] ?? false,
                'reference' => $validated['reference']
            ]);

            // 2️⃣ Loop through lines and create them
            foreach ($validated['lines'] as $lineData) {
                $files = $lineData['files'] ?? [];
                unset($lineData['files']); // Remove files before creating the line

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

            // --- Files per line ---
            'lines.*.files.*' => 'nullable|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif',
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
                'status' => $validated['status'] ?? $document->status

            ]);

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

            // 6️⃣ Delete removed lines
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


    public function statusCount($status){
        return PurchaseDocument::where('status', $status)->count();
    }
}

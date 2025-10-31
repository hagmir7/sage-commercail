<?php

namespace App\Http\Controllers;

use App\Models\PurchaseDocument;
use App\Models\PurchaseLine;
use App\Models\PurchaseLineFile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            $query->where('service_id', $user->service_id);
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

            // --- Lines array ---
            'lines' => 'required|array|min:1',
            'lines.*.code' => 'nullable|string',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:1',
            'lines.*.unit' => 'nullable|string',
            'lines.*.estimated_price' => 'nullable|numeric',

            // --- Files for each line ---
            'lines.*.files' => 'nullable|array',
            'lines.*.files.*' => 'file|max:10240', // 10 MB max per file
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

    public function update(Request $request, PurchaseDocument $purchaseDocument)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|numeric',
            'piece' => 'nullable|string',
            'note' => 'nullable|string',
            'urgent' => 'boolean',
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
}

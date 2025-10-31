<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PurchaseLineFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PurchaseLineFileController extends Controller
{

    public function index()
    {
        return response()->json(PurchaseLineFile::with('line')->latest()->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_line_id' => 'required|exists:purchase_lines,id',
            'file' => 'required|file|max:10240', // Max 10 MB
        ]);

        $path = $request->file('file')->store('purchase_files', 'public');

        $file = PurchaseLineFile::create([
            'purchase_line_id' => $validated['purchase_line_id'],
            'file_path' => $path,
            'file_name' => $request->file('file')->getClientOriginalName(),
        ]);

        return response()->json($file, 201);
    }


    public function show(PurchaseLineFile $purchaseLineFile)
    {
        return response()->json($purchaseLineFile);
    }


    public function destroy(PurchaseLineFile $purchaseLineFile)
    {
        if ($purchaseLineFile->file_path && Storage::disk('public')->exists($purchaseLineFile->file_path)) {
            Storage::disk('public')->delete($purchaseLineFile->file_path);
        }

        $purchaseLineFile->delete();
        return response()->json(['message' => 'Fichier supprimé avec succès.']);
    }
}
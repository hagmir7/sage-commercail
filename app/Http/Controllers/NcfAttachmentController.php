<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\NcfAttachment;
use App\Models\SupplierNonConformity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NcfAttachmentController extends Controller
{
    /**
     * POST /api/ncf/{id}/attachments
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'files'   => 'required|array|min:1',
            'files.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx',
        ]);

        $ncf         = SupplierNonConformity::findOrFail($id);
        $attachments = [];

        foreach ($request->file('files') as $file) {
            $path = $file->store("ncf/{$ncf->id}", 'public');

            $attachments[] = $ncf->attachments()->create([
                'filename'  => $file->getClientOriginalName(),
                'path'      => $path,
                'mime_type' => $file->getMimeType(),
                'size'      => $file->getSize(),
            ]);
        }

        return response()->json($attachments, 201);
    }

    /**
     * DELETE /api/ncf/{ncfId}/attachments/{id}
     */
    public function destroy(int $ncfId, int $id): JsonResponse
    {
        $attachment = NcfAttachment::where('non_conformity_id', $ncfId)->findOrFail($id);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return response()->json(['message' => 'Deleted']);
    }
}

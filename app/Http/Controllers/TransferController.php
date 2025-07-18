<?php

namespace App\Http\Controllers;

use App\Models\Palette;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransferController extends Controller
{
    public function index()
    {
        $transferes = Transfer::with(['form_company', 'to_company', 'user', 'palette.document', 'transfer_by'])
            ->orderByDesc('created_at')
            ->paginate(15);
        return response()->json($transferes);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'palette_code' => 'required|exists:palettes,code',
            'to_company' => 'required|exists:companies,id',
            'transfer_by' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $palette = Palette::where('code', $request->palette_code)->first();

        if (!$palette) {
            return response()->json([
                'message' => 'La palette avec le code ' . $request->palette_code . ' n\'existe pas !'
            ], 404);
        }

        if ($palette->company_id == $request->to_company) {
            return response()->json([
                'message' => 'La société de destination est identique à la société d\'origine.'
            ], 400);
        }

        DB::transaction(function () use ($palette, $request) {
            Transfer::create([
                'user_id' => auth()->id(),
                'palette_id' => $palette->id,
                'from_company' => $palette->company_id,
                'to_company' => $request->to_company,
                'transfer_by' => $request->transfer_by,
            ]);

            $palette->update([
                'company_id' => $request->to_company,
            ]);

            foreach ($palette->lines as $line) {
                $line->update([
                    'company_id' => $request->to_company,
                ]);
            }
        });

        return response()->json([
            'message' => 'Transfert effectué avec succès.'
        ], 200);
    }
}

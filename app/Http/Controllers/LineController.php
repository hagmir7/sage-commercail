<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Models\Palette;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LineController extends Controller
{

    public function generatePaletteCode()
    {

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


    public function prepare(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'line' => 'required|exists:lines,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422);
        }

        $line = Line::find($request->line);
        $document = $line->document;

        // update line status
        $line->update(['status_id' => 8]);

        // check if document already has palettes
        if ($document->palettes->count()) {
            $palette = $document->palettes->first(); 
        } else {
            $palette = Palette::create([
                'document_id' => $document->id,
                'company_id'  => auth()?->user()?->company_id ?? 1,
                'type'        => "Livraison",
                'user_id'     => auth()?->id() ?? 1,
                'code'        => $this->generatePaletteCode()
            ]);
        }

        // attach palette to line
        $line->update(['palette_id' => $palette->id]);

        $line->palettes()->attach($palette->id, ['quantity' => floatval($line->docligne->DL_Qte)]);

        // update document status depending on validation
        if ($document->validation()) {
            $document->update(['status_id' => 8]);
        } elseif ($document->status_id != 7) {
            $document->update(['status_id' => 7]);
        }

        // update pivot for company if validated
        if ($document->validationCompany(auth()?->user()?->company_id)) {
            $document->companies()->updateExistingPivot(auth()->user()->company_id, [
                'status_id'  => 8,
                'updated_at' => now()
            ]);
        }

        return response()->json($line);
    }
}

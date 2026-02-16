<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\SupplierCriteria;
use App\Models\SupplierInterview;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\LaravelPdf\Facades\Pdf;

class SupplierInterviewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $connection = $request->company_db ?? 'sqlsrv_inter';

        return SupplierInterview::on($connection)
            ->with(['user', 'client'])
            ->withSum('criterias as total_note', 'supplier_interview_criterias.note') // pivot column
            ->latest()
            ->get();
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $connection = $request->company_db;

        $validator = Validator::make($request->all(), [
            'CT_Num' => 'required',
            'date'          => 'required|date',
            'description'   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        
        $supplierInterview = DB::connection($connection)->insert(
            "INSERT INTO supplier_interviews 
            (CT_Num, [date], description, user_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, GETDATE(), GETDATE())",
            [
                $request->CT_Num,
                $request->date,   // must be '2026-02-16'
                $request->description,
                auth()->id(),
            ]
        );

        return response()->json($supplierInterview, 201);
    }


    /**
     * Display the specified resource.
     */
    public function show($supplierInterviewId, Request $request)
    {

        $supplierInterview = SupplierInterview::on($request->company_db)
            ->with(['user', 'client', 'criterias'])
            ->findOrFail($supplierInterviewId);

        $notes = $supplierInterview->criterias
            ->pluck('pivot.note', 'id');

        // Get all criterias from same connection
        $criterias = SupplierCriteria::on($request->company_db)
            ->get()
            ->map(function ($criteria) use ($notes) {
                return [
                    'id'          => $criteria->id,
                    'description' => $criteria->description,
                    'note'        => $notes[$criteria->id] ?? null,
                ];
            });

        return response()->json([
            'interview' => $supplierInterview,
            'criterias' => $criterias,
        ]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SupplierInterview $supplierInterview)
    {
        $validator = Validator::make($request->all(), [
            'CT_Num'   => 'required|exists:clients,CT_Num',
            'date'        => 'required|date',
            'description' => 'nullable|string',
            'note'        => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $supplierInterview->update($validator->validated());

        return response()->json($supplierInterview);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SupplierInterview $supplierInterview)
    {
        $supplierInterview->delete();

        return response()->json([
            'message' => 'Deleted successfully'
        ]);
    }


    public function addCriteria(Request $request, $supplierInterviewId)
    {
        $validator = Validator::make($request->all(), [
            'criteria_id' => 'required|exists:supplier_criterias,id',
            'note'        => 'required|integer|min:1|max:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $connection = $request->company_db ?? 'sqlsrv_inter';

        $supplierInterview = SupplierInterview::on($connection)
            ->findOrFail($supplierInterviewId);

        $supplierInterview->setConnection($connection);

        $supplierInterview->criterias()->syncWithoutDetaching([
            $request->criteria_id => [
                'note' => $request->note,
                'created_at' => null,
                'updated_at' => null
            ]
        ]);

        return response()->json([
            'message' => 'Note saved successfully',
            'criteria_id' => $request->criteria_id,
            'note' => $request->note,
        ]);
    }



    public function download($supplierInterviewId, Request $request)
    {
        $supplierInterview = SupplierInterview::on($request->company_db)
            ->with(['user', 'client', 'criterias'])
            ->findOrFail($supplierInterviewId);

        $notes = $supplierInterview->criterias
            ->pluck('pivot.note', 'id');


        $criterias = SupplierCriteria::on($request->company_db)
            ->get()
            ->map(function ($criteria) use ($notes) {
                return [
                    'id'          => $criteria->id,
                    'description' => $criteria->description,
                    'note'        => $notes[$criteria->id] ?? null,
                ];
            });

        return Pdf::view('pdfs.supplier-interview', [
            'interview' => $supplierInterview,
            'criterias' => $criterias,
        ])
            ->format('a4')
            // ->landscape()
            ->footerHtml('
                <div style="
                    font-size:15px;
                    text-align:center;
                    width:100%;
                    color:#555;
                ">
                    © Ce document ne doit être ni reproduit ni communiqué sans l’autorisation d’INTERCOCINA
                </div>
            ')->name(now()->format('Ymd_His') . '-grille-evaluation.pdf');
    }

}

<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\LaravelPdf\Facades\Pdf;

class ClientController extends Controller
{

    public function suppliers(Request $request)
    {
        $connection = $request->company_db ?? 'sqlsrv_inter';

        return Client::on($connection)
            ->select(
                'CT_Intitule',
                'CT_Num',
                'CT_Sommeil',
                'CT_EMail',
                'cbMarq',
                'CT_EMail',
                'CT_Adresse',
                'CT_Telephone',
                'CT_Telecopie',
                'CT_Ville',
                'Nature_Achat',
                'CT_Pays'
            )
            ->where('CT_Type', 1)
            ->where('CT_Sommeil', '0')
            ->get();
    }


    public function showSupplier(Request $request, $code)
    {
        $connection = $request->company_db ?? 'sqlsrv_inter';

        $supplier = Client::on($connection)
            ->where('CT_Num', $code)
            ->select(
                'CT_Intitule',
                'CT_Num',
                'CT_Sommeil',
                'CT_EMail',
                'cbMarq',
                'CT_Adresse',
                'CT_Telephone',
                'CT_Telecopie',
                'CT_Ville',
                'Nature_Achat',
                'CT_Pays'
            )
            ->first();

        if (!$supplier) {
            return response()->json([
                'message' => 'Supplier not found'
            ], 404);
        }

        return response()->json($supplier);
    }


    public function update(Request $request, $code)
    {
        $validator = Validator::make($request->all(), [
            'CT_Intitule'   => 'required|string|max:100',
            'CT_Num'        => 'required|string',
            // 'CT_EMail'      => 'required|email|max:50|email',
            // 'CT_Adresse'    => 'required|string|max:100',
            // 'CT_Telephone'  => 'required|string|max:20',
            // 'CT_Telecopie'  => 'required|string|max:20',
            // 'CT_Ville'      => 'required|string|max:20',
            'Nature_Achat'  => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $connection = $request->company_db ?? 'sqlsrv_inter';

        $client = Client::on($connection)->find($code);

        if (!$client) {
            return response()->json([
                'message' => 'Client not found'
            ], 404);
        }

        $client->update($validator->validated());

        return response()->json([
            'message' => 'Client updated successfully',
            'data' => $client
        ]);
    }




    public function show(Request $request, Client $client)
    {
        $perPage = $request->get('per_page', 10);

        $docentetes = $client->docentete()->orderByDesc('cbCreation')->paginate($perPage);

        // Eager load only selected columns from the remise relationship
        $client->load(['remise' => function ($query) {
            $query->select('FA_CodeFamille', 'CT_Num', 'FC_Remise', 'cbMarq');
        }]);

        return response()->json([
            'client' => $client,
            'docentete' => $docentetes,
        ], 200, [], JSON_INVALID_UTF8_IGNORE);
    }



    public function download(Request $request)
    {
        $connection = $request->company_db ?? 'sqlsrv_inter';

        $suppliers = Client::on($connection)
            ->select(
                'CT_Intitule',
                'CT_Num',
                'CT_Sommeil',
                'CT_EMail',
                'cbMarq',
                'CT_Adresse',
                'CT_Telephone',
                'CT_Telecopie',
                'CT_Ville',
                'Nature_Achat',
                'CT_Pays'
            )
            ->where('CT_Type', 1)
            ->where('CT_Sommeil', '0')
            ->get();

        return Pdf::view('pdfs.suppliers', [
            'suppliers' => $suppliers,
        ])
            ->format('a4')
            ->landscape()
            // ->headerView('pdfs.header')
            // ->footerView('pdfs.footer')
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

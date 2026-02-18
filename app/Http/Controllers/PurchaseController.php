<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\PurchaseDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function countDocumentStatus()
    {
        $results = PurchaseDocument::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get();

        return $results->map(function ($item) {
            return [
                'status' => $item->status,
                'status_label' => PurchaseDocument::STATUS_OPTIONS[$item->status] ?? 'Inconnu',
                'total' => $item->total,
            ];
        })->values();
    }



    public function countByService()
    {
        $totalDocuments = PurchaseDocument::count();

        $results = PurchaseDocument::select(
            'service_id',
            DB::raw('COUNT(*) as total')
        )
            ->with('service:id,name')
            ->groupBy('service_id')
            ->get();

        return $results->map(function ($item) use ($totalDocuments) {
            $percent = $totalDocuments > 0
                ? round(($item->total / $totalDocuments) * 100, 2)
                : 0;

            return [
                'service_id'   => $item->service_id,
                'service_name' => $item->service?->name ?? 'Sans service',
                'total'        => $item->total,
                'percent'      => $percent,
            ];
        })->values();
    }



    public function activeSuppliers()
    {
        $connections = [
            'sqlsrv_inter',
            'sqlsrv_serie',
            'sqlsrv_asti'
        ];

        $total = 0;

        foreach ($connections as $connection) {
            $count = Client::on($connection)
                ->where('CT_Sommeil', "0")
                ->count();

            $total += $count;
        }

        return ['count' => $total];
    }



    public function countSupplierNaturAchat()
    {
        $connections = [
            'sqlsrv_inter',
            'sqlsrv_serie',
            'sqlsrv_asti'
        ];

        $globalCounts = [];
        $grandTotal = 0;

        foreach ($connections as $connection) {

            $results = Client::on($connection)
                ->select('Nature_Achat', DB::raw('COUNT(*) as total'))
                ->where('CT_Sommeil', "0")
                ->groupBy('Nature_Achat')
                ->get();

            foreach ($results as $row) {

                $natur = $row->Nature_Achat ?? 'Non défini';

                if (!isset($globalCounts[$natur])) {
                    $globalCounts[$natur] = 0;
                }

                $globalCounts[$natur] += $row->total;
                $grandTotal += $row->total;
            }
        }

        // Format with percent
        $final = collect($globalCounts)->map(function ($total, $natur) use ($grandTotal) {

            $percent = $grandTotal > 0
                ? round(($total / $grandTotal) * 100, 2)
                : 0;

            return [
                'Nature_Achat' => $natur,
                'total'       => $total,
                'percent'     => $percent,
            ];
        })->values();

        return [
            'grand_total' => $grandTotal,
            'data'        => $final
        ];
    }

}

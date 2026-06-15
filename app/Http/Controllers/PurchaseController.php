<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Compte;
use App\Models\Docentete;
use App\Models\Docligne;
use App\Models\PurchaseDocument;
use App\Models\PurchaseLineNonCompliant;
use App\Models\Service;
use App\Models\SupplierInterview;
use Carbon\Carbon;
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


    public function states(Request $request)
    {
        $start = $request->start_date
            ? Carbon::parse($request->start_date)->format('Ymd')
            : Carbon::now()->startOfYear()->format('Ymd');

        $end = $request->end_date
            ? Carbon::parse($request->end_date)->format('Ymd')
            : Carbon::now()->endOfYear()->format('Ymd');

        return [
            "suppliers"            => $this->activeSuppliers(),
            "expenditure"          => $this->expenditure($start, $end),
            "documents_in_progress" => PurchaseDocument::whereIn('status', [2, 3, 4, 5, 6])->count(),
            "services"             => Service::count()
        ];
    }


    public function expenditure($start_date = null, $end_date = null)
    {
        $start = $start_date
            ? Carbon::parse($start_date)->format('Ymd')
            : '20260101';

        $end = $end_date
            ? Carbon::parse($end_date)->format('Ymd')
            : Carbon::now()->format('Ymd');

        return DB::connection('sqlsrv_inter')
            ->table('F_DOCENTETE')
            ->where('DO_Domaine', 1)
            ->whereBetween(
                DB::raw("CONVERT(varchar(8), cbCreation, 112)"),
                [$start, $end]
            )
            ->whereIn('DO_Type', [10, 11, 12, 13, 16, 17])
            ->sum('DO_TotalTTC');
    }

    public function monthlyPurchases()
    {
        $start = \Carbon\Carbon::now()
            ->subMonths(11)
            ->startOfMonth()
            ->format('Ymd');

        $end = \Carbon\Carbon::now()
            ->endOfMonth()
            ->format('Ymd');

        return DB::connection('sqlsrv_inter')
            ->table('F_DOCENTETE')
            ->selectRaw("
            YEAR(DO_Date) AS year,
            MONTH(DO_Date) AS month,
            COUNT(*) AS total_documents,
            SUM(DO_TotalTTC) AS total_ttc
        ")
            ->where('DO_Domaine', 1)
            ->whereRaw("CONVERT(int, CONVERT(varchar, DO_Date, 112)) BETWEEN ? AND ?", [$start, $end])
            ->whereIn('DO_Type', [10, 11, 12, 13, 16, 17])
            ->groupByRaw("YEAR(DO_Date), MONTH(DO_Date)")
            ->orderByRaw("YEAR(DO_Date), MONTH(DO_Date)")
            ->get()
            ->map(fn($row) => [
                'year'            => (int) $row->year,
                'month'           => (int) $row->month,
                'total_documents' => (int) $row->total_documents,
                'total_ttc'       => (float) $row->total_ttc,
            ]);
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



    public function serviceExpenditures(Request $request)
    {
        $start = $request->input('start', '20260101');
        $end   = $request->input('end', now()->format('Ymd'));

        return DB::connection('sqlsrv_inter')
            ->table('F_DOCENTETE as d')
            ->selectRaw("
            c.CO_Service       AS service_name,
            COUNT(*)           AS total_documents,
            SUM(d.DO_TotalTTC) AS total_ttc
        ")
            ->leftJoin('F_COLLABORATEUR as c', 'd.CO_No', '=', 'c.CO_No')
            ->where('d.DO_Domaine', 1)
            ->whereIn('d.DO_Type', [10, 11, 12, 13, 16,15])
            ->whereRaw("CONVERT(int, CONVERT(varchar, d.DO_Date, 112)) BETWEEN ? AND ?", [$start, $end])
            ->groupByRaw("c.CO_Service")
            ->orderByRaw("SUM(d.DO_TotalTTC) DESC")
            ->get()
            ->map(fn($row) => [
                'service_name'    => $row->service_name ?? 'Sans service',
                'total_documents' => (int)   $row->total_documents,
                'total_ttc'       => (float) $row->total_ttc,
            ]);
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

        return $total;
    }

public function deadlineSuppliers(): array
{
    $start = Carbon::now()->subMonths(11)->startOfMonth()->format('Ymd');
    $end   = Carbon::now()->endOfMonth()->format('Ymd');

    $totalSubquery = Docentete::on('sqlsrv_inter')
        ->where('F_DOCENTETE.DO_Domaine', 1)
        ->leftJoin('F_DOCLIGNE', 'F_DOCLIGNE.DO_Piece', '=', 'F_DOCENTETE.DO_Piece')
        ->selectRaw("
            F_DOCENTETE.DO_Piece,
            CASE
                WHEN F_DOCENTETE.DO_Piece LIKE '%BL%'
                    THEN FORMAT(F_DOCENTETE.DO_Date, 'yyyy-MM')
                WHEN F_DOCENTETE.DO_Piece LIKE '%FA%'
                    THEN FORMAT(MAX(F_DOCLIGNE.DL_DateBL), 'yyyy-MM')
                ELSE FORMAT(F_DOCENTETE.DO_Date, 'yyyy-MM')
            END AS month
        ")
        ->whereBetween('F_DOCENTETE.DO_DateLivr', [$start, $end])
        ->groupByRaw("
            F_DOCENTETE.DO_Piece,
            F_DOCENTETE.DO_Date,
            F_DOCENTETE.DO_DateLivr
        ")
        ->toBase(); // raw query builder, no Eloquent wrapping

    // Main query: late deliveries (DL_DateBL > DO_DateLivr) + join total subquery
    $rows = Docentete::on('sqlsrv_inter')
        ->where('F_DOCENTETE.DO_Domaine', 1)
        ->whereBetween('F_DOCENTETE.DO_DateLivr', [$start, $end])
        ->join('F_DOCLIGNE', function ($join) {
            $join->on('F_DOCLIGNE.DO_Piece', '=', 'F_DOCENTETE.DO_Piece')
                ->whereRaw('F_DOCLIGNE.DL_DateBL > F_DOCENTETE.DO_DateLivr');
        })
        // Join the totals subquery on the same month
        ->joinSub($totalSubquery, 'totals', function ($join) {
            $join->on('totals.DO_Piece', '=', 'F_DOCENTETE.DO_Piece');
        })
        ->selectRaw("
            FORMAT(F_DOCENTETE.DO_DateLivr, 'yyyy-MM') AS month,
            COUNT(DISTINCT F_DOCENTETE.DO_Piece)        AS count,
            COUNT(DISTINCT totals.DO_Piece)             AS total
        ")
        ->groupByRaw("FORMAT(F_DOCENTETE.DO_DateLivr, 'yyyy-MM')")
        ->get()
        ->keyBy('month');

    // However, since total is independent, we fetch it separately and merge
    $totals = \DB::connection('sqlsrv_inter')
        ->table(\DB::raw("({$totalSubquery->toSql()}) as t"))
        ->mergeBindings($totalSubquery)
        ->selectRaw("month, COUNT(DISTINCT DO_Piece) AS total")
        ->groupBy('month')
        ->get()
        ->keyBy('month');

    return collect(range(11, 0))->map(function ($i) use ($rows, $totals) {
        $month = Carbon::now()->subMonths($i)->format('Y-m');
        $row   = $rows[$month]   ?? null;
        $tot   = $totals[$month] ?? null;
        return [
            'month' => $month,
            'count' => (int) ($row->count ?? 0),
            'total' => (int) ($tot->total ?? 0),
        ];
    })->values()->all();
}


    public function deadlineSupplier(string $ctNum): array
    {
        $start = Carbon::now()->subMonths(11)->startOfMonth()->format('Ymd');
        $end   = Carbon::now()->endOfMonth()->format('Ymd');

        $totalSubquery = Docentete::on('sqlsrv_inter')
            ->where('F_DOCENTETE.DO_Domaine', 1)
            ->where('F_DOCENTETE.DO_Tiers', $ctNum)
            ->whereBetween('F_DOCENTETE.DO_DateLivr', [$start, $end])
            ->leftJoin('F_DOCLIGNE', 'F_DOCLIGNE.DO_Piece', '=', 'F_DOCENTETE.DO_Piece')
            ->selectRaw("
            F_DOCENTETE.DO_Piece,
            CASE
                WHEN F_DOCENTETE.DO_Piece LIKE '%BL%'
                    THEN FORMAT(F_DOCENTETE.DO_Date, 'yyyy-MM')
                WHEN F_DOCENTETE.DO_Piece LIKE '%FA%'
                    THEN FORMAT(MAX(F_DOCLIGNE.DL_DateBL), 'yyyy-MM')
                ELSE FORMAT(F_DOCENTETE.DO_Date, 'yyyy-MM')
            END AS month
        ")
            ->groupByRaw("
            F_DOCENTETE.DO_Piece,
            F_DOCENTETE.DO_Date,
            F_DOCENTETE.DO_DateLivr
        ")
            ->toBase();

        // Late-delivery count query (DL_DateBL > DO_DateLivr)
        $rows = Docentete::on('sqlsrv_inter')
            ->where('F_DOCENTETE.DO_Domaine', 1)
            ->where('F_DOCENTETE.DO_Tiers', $ctNum)
            ->whereBetween('F_DOCENTETE.DO_DateLivr', [$start, $end])
            ->join('F_DOCLIGNE', function ($join) {
                $join->on('F_DOCLIGNE.DO_Piece', '=', 'F_DOCENTETE.DO_Piece')
                    ->whereRaw('F_DOCLIGNE.DL_DateBL > F_DOCENTETE.DO_DateLivr');
            })
            ->selectRaw("
            FORMAT(F_DOCENTETE.DO_DateLivr, 'yyyy-MM') AS month,
            COUNT(DISTINCT F_DOCENTETE.DO_Piece)        AS count
        ")
            ->groupByRaw("FORMAT(F_DOCENTETE.DO_DateLivr, 'yyyy-MM')")
            ->get()
            ->keyBy('month');

        // Total documents per month (independent query)
        $totals = \DB::connection('sqlsrv_inter')
            ->table(\DB::raw("({$totalSubquery->toSql()}) as t"))
            ->mergeBindings($totalSubquery)
            ->selectRaw("month, COUNT(DISTINCT DO_Piece) AS total")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        return collect(range(11, 0))->map(function ($i) use ($rows, $totals) {
            $month = Carbon::now()->subMonths($i)->format('Y-m');
            $row   = $rows[$month]   ?? null;
            $tot   = $totals[$month] ?? null;
            return [
                'month' => $month,
                'count' => (int) ($row->count ?? 0),
                'total' => (int) ($tot->total ?? 0),
            ];
        })->values()->all();
    }

    public function nonCompliantLines(): array
    {
        $start = Carbon::now()->subMonths(11)->startOfMonth();
        $end   = Carbon::now()->endOfMonth();

        $nonCompliantRows = PurchaseLineNonCompliant::whereBetween('created_at', [$start, $end])
            ->selectRaw("
            FORMAT(created_at, 'yyyy-MM') AS month,
            COUNT(id) AS count
        ")
            ->groupByRaw("FORMAT(created_at, 'yyyy-MM')")
            ->get()
            ->keyBy('month');

        $docligneRows = Docligne::on('sqlsrv_inter')
            ->where('DO_Domaine', 1)
            ->whereBetween(
                DB::raw("CONVERT(datetime, cbCreation, 120)"),
                [$start->format('Ymd'), $end->format('Ymd')]
            )
            ->where(function ($q) {
                $q->where('DO_Piece', 'LIKE', '%FA%')
                    ->orWhere('DO_Piece', 'LIKE', '%BL%');
            })
            ->selectRaw("
                FORMAT(CONVERT(datetime, cbCreation, 120), 'yyyy-MM') AS month,
                COUNT(cbMarq) AS total
            ")
            ->groupByRaw("FORMAT(CONVERT(datetime, cbCreation, 120), 'yyyy-MM')")
            ->get()
            ->keyBy('month');

        return collect(range(11, 0))->map(function ($i) use ($nonCompliantRows, $docligneRows) {
            $month = Carbon::now()->subMonths($i)->format('Y-m');
            return [
                'month' => $month,
                'count_none_compliant_lines' => (int) ($nonCompliantRows[$month]->count ?? 0),
                'total_livraisons' => (int) ($docligneRows[$month]->total ?? 0),
            ];
        })->values()->all();
    }


    public function supplierInterviewsByYear(): array
    {
        $startYear = Carbon::now()->subYears(6)->year;
        $endYear   = Carbon::now()->subYears(1)->year;

        // ── SupplierInterview counts per year (local DB) ──────────────────────
        $interviewRows = SupplierInterview::on('sqlsrv_inter')->selectRaw('YEAR(created_at) AS year, COUNT(id) AS count')
            ->whereYear('created_at', '>=', $startYear)
            ->groupByRaw('YEAR(created_at)')
            ->get()
            ->keyBy('year');

        // ── Compte counts per year (SQL Server) ───────────────────────────────
        $compteRows = Compte::on('sqlsrv_inter')
            ->selectRaw('YEAR(CONVERT(datetime, cbCreation, 120)) AS year, COUNT(CT_Num) AS count')
            ->whereRaw('YEAR(CONVERT(datetime, cbCreation, 120)) >= ?', [$startYear])
            ->groupByRaw('YEAR(CONVERT(datetime, cbCreation, 120))')
            ->get()
            ->keyBy('year');

        // ── Build years range & apply cumulative totals ───────────────────────
        $cumulativeInterviews = 0;
        $cumulativeCompte     = 0;

        return collect(range($startYear, $endYear))->map(function ($year) use (
            $interviewRows,
            $compteRows,
            &$cumulativeInterviews,
            &$cumulativeCompte
        ) {
            $cumulativeInterviews += (int) ($interviewRows[$year]->count ?? 0);
            $cumulativeCompte     += (int) ($compteRows[$year]->count    ?? 0);

            return [
                'year'                => $year,
                'interviews_count'    => (int) ($interviewRows[$year]->count ?? 0),
                'interviews_total'    => $cumulativeInterviews,
                'suppliers_count'     => (int) ($compteRows[$year]->count    ?? 0),
                'suppliers_total'     => $cumulativeCompte,
            ];
        })->values()->all();
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

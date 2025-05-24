<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use DateTime;

class DocumentController extends Controller
{
    public function checkControlled($piece)
    {
        $document = Document::where('piece', $piece)->first();
        if (!$document) {
            return response()->json(['error' => "Document not found!"], 404);
        }

        $palettes = $document->palettes;

        $controlled = $palettes->every(function ($docPalette) {
            return !$docPalette->lines()->wherePivotNull('controlled_at')->exists();
        });

        return $controlled;
    }


    public function progress($piece)
    {
        $document = Document::with(['docentete.doclignes', 'lines.palettes'])->where("piece", $piece)->first();

        if (!$document) {
            return response()->json(["error" => "Document not found"], 404);
        }

        $required_qte = $document->lines->sum("quantity") ?? 0;

        $current_qte = 0;
        foreach ($document->lines as $line) {
            foreach ($line->palettes as $palette) {
                $current_qte += $palette->pivot->quantity;
            }
        }

        $progress = $required_qte > 0 ? round(($current_qte / $required_qte) * 100, 2) : 0;

        return response()->json([
            'current_qte' => $current_qte,
            'required_qte' => $required_qte,
            'progress' => intval($progress)
        ]);
    }


public function longList()
{
    $documents = Document::with(['status', 'lines.palettes'])
        ->withCount('lines')
        ->orderByDesc('updated_at')
        ->get();

    $documents = $documents->map(function ($document) {
        $required_qte = $document->lines->sum('quantity') ?? 0;

        $current_qte = 0;
        foreach ($document->lines as $line) {
            foreach ($line->palettes as $palette) {
                $current_qte += $palette->pivot->quantity;
            }
        }

        $progress = $required_qte > 0 ? round(($current_qte / $required_qte) * 100, 2) : 0;

        // Add progress details to each document
        $document->current_qte = $current_qte;
        $document->required_qte = $required_qte;
        $document->progress = intval($progress);

        return $document;
    });

    return response()->json($documents);
}

    public function list(Request $request)
    {
        $documents = Document::with(['status', 'lines.palettes'])
            ->withCount('lines')
            ->orderByDesc('updated_at');


        // Searche
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $documents->where(function ($q) use ($search) {
                $q->where('piece', 'like', "%$search%")
                    ->orWhere('ref', 'like', "%$search%")
                    ->orWhere('client_id', 'like', "%$search%");
            });
        }

        // Date filtter
        if ($request->dates) {
            $date_array = explode(',', $request->dates);
            $start_date = DateTime::createFromFormat('d/m/Y', trim($date_array[0]))->format('Y-m-d');
            $end_date = DateTime::createFromFormat('d/m/Y', trim($date_array[1]))->format('Y-m-d');
            $documents->whereBetween('created_at', [$start_date, $end_date]);
        }


        $documents = $documents->get()->map(function ($document) {
            $required_qte = $document->lines->sum(function ($line) {
                return floatval($line->quantity);
            });

            $current_qte = 0;
            $companies = [];

            foreach ($document->lines as $line) {
                if (!in_array($line->company_id, $companies)) {
                    $companies[] = $line->company_id;
                }
                foreach ($line->palettes as $palette) {
                    $current_qte += floatval($palette->pivot->quantity);
                }
            }

            $progress = $required_qte > 0 ? round(($current_qte / $required_qte) * 100, 2) : 0;


            $companyDisplay = '';
            if (count($companies) > 1) {
                $companyDisplay = 'Inter & Serie';
            } elseif (count($companies) === 1) {
                $company = \App\Models\Company::find($companies[0]);
                $companyDisplay = $company ? $company->name : 'Unknown Company';
            }

            return [
                'id' => $document->id,
                'name' => $document->name,
                'piece' => $document->piece,
                'ref' => $document->ref,
                'expedition' => $document->expedition,
                'company' => $companyDisplay,
                'client' => $document->client_id,
                'status' => [
                    'id' => $document->status->id ?? null,
                    'name' => $document->status->name ?? null,
                    'color' => $document->status->color ?? null,
                ],
                'lines_count' => $document->lines_count,
                'current_qte' => $current_qte,
                'required_qte' => $required_qte,
                'progress' => intval($progress),
                'updated_at' => $document->updated_at,
                'created_at' => $document->created_at,
            ];
        });
        return response()->json($documents);
    }
}

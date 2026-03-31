<?php

namespace App\Exports;

use App\Models\TravelReception;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TravelReceptionExport implements FromCollection, WithMapping, WithHeadings
{
    public function collection()
    {
        return TravelReception::with(['driver', 'company'])->get();
    }

    public function map($reception): array
    {
        return [
            $reception->code,
            $reception->driver?->full_name,
            $reception->driver?->code,
            $reception->company?->name,
            $reception->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            'Code Voyage',
            'Nom du chauffeur',
            'CIN du chauffeur',
            'Matricule',
            'Société',
            'Date de création',
        ];
    }
}
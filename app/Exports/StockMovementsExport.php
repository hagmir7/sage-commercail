<?php

namespace App\Exports;

use App\Models\StockMovement;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class StockMovementsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    protected function normalizeArray($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            return array_filter(explode(',', $value));
        }

        return [];
    }

    public function collection()
    {
        $query = StockMovement::with(['articleStock', 'emplacement', 'to_emplacement', 'movedBy', 'to_company']);

        // Optional filters (date, user, etc.)
        if (!empty($this->filters['dates'])) {
            $query->filterByDates($this->filters['dates']);
        }

        if (!empty($this->filters['users'])) {
            $query->filterByUsers($this->normalizeArray($this->filters['users']));
        }

        if (!empty($this->filters['category'])) {
            $query->filterByCategory($this->filters['category']);
        }

        if (!empty($this->filters['depots'])) {
            $query->filterByDepots($this->normalizeArray($this->filters['depots']));
        }

        if (!empty($this->filters['search'])) {
            $query->search($this->filters['search']);
        }

        if (!empty($this->filters['types'])) {
            $query->filterByTypes($this->normalizeArray($this->filters['types']));
        }


        return $query->get();
    }

    public function map($movement): array
    {
        return [
            $movement->id,
            $movement->code_article,
            $movement->designation,
            optional($movement->articleStock)->category,
            optional($movement->emplacement)->code,
            optional($movement->to_emplacement)->code,
            $movement->quantity,
            $movement->movement_type,
            $movement->movement_date,
            optional($movement->movedBy)->full_name,
            optional($movement->to_company)->name,
            $movement->note,
            $movement->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Code Article',
            'Désignation',
            'Catégorie',
            'Emplacement Source',
            'Emplacement Destination',
            'Quantité',
            'Type de Mouvement',
            'Date du Mouvement',
            'Déplacé Par',
            'Entreprise Destination',
            'Note',
            'Date de Création',
        ];
    }
}

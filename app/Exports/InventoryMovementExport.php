<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InventoryMovementExport implements FromCollection, WithHeadings
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return collect($this->data);
    }

    public function headings(): array
    {
        return [
            'Movement Ref',
            'Ref',
            'Désignation',
            'Nom',
            'Quantité',
            'Emplacement',
            'Responsable',
            'Date',
            'Société'
        ];
    }
}

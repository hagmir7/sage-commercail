<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ArticleStockExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    ShouldAutoSize
{
    public function __construct(private $articles) {}

    public function collection()
    {
        return $this->articles->map(fn($a) => [
            $a->code,
            $a->name,
            $a->description,
            number_format((float) $a->prix, 2, '.', ''),
            $a->category,
            $a->color,
            $a->stock,
            $a->stock_min,
            $a->max,
            $a->ecart,
            $a->stock_prepare,
            $a->stock_prepartion,
            match($a->urgency_level) {
                1 => 'Critique',
                2 => 'Bas',
                3 => 'Moyen',
                4 => 'OK',
            },
        ]);
    }

    public function headings(): array
    {
        return [
            'Code',
            'Nom',
            'Désignation',
            'Prix (HT)',
            'Catégorie',
            'Couleur',
            'Stock',
            'Stock Min',
            'Max',
            'Écart',
            'En Préparation',
            'Zone BL',
            'Urgence',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        // ── Header row ────────────────────────────────────────────────────
        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 11,
                'name'  => 'Arial',
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(22);

        // ── Zebra rows ────────────────────────────────────────────────────
        for ($row = 2; $row <= $lastRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:M{$row}")->applyFromArray([
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F1F5F9'],
                    ],
                ]);
            }

            // ── Urgency color (column M) ──────────────────────────────────
            $urgency = (string) $sheet->getCell("M{$row}")->getValue();
            $bgColor = match(true) {
                str_contains($urgency, 'Critique') => 'FEE2E2',
                str_contains($urgency, 'Bas')      => 'FFEDD5',
                str_contains($urgency, 'Moyen')    => 'FEF9C3',
                default                            => 'DCFCE7',
            };
            $textColor = match(true) {
                str_contains($urgency, 'Critique') => '991B1B',
                str_contains($urgency, 'Bas')      => '9A3412',
                str_contains($urgency, 'Moyen')    => '854D0E',
                default                            => '166534',
            };
            $sheet->getStyle("M{$row}")->applyFromArray([
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $bgColor],
                ],
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => $textColor],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ]);
        }

        // ── Number formats ────────────────────────────────────────────────
        $sheet->getStyle("D2:D{$lastRow}")->getNumberFormat()
            ->setFormatCode('#,##0.00 "DH"');

        $sheet->getStyle("G2:J{$lastRow}")->getNumberFormat()
            ->setFormatCode('#,##0.00');

        // ── Body font ─────────────────────────────────────────────────────
        $sheet->getStyle("A2:M{$lastRow}")->getFont()
            ->setName('Arial')
            ->setSize(10);

        // ── Center numeric columns ────────────────────────────────────────
        $sheet->getStyle("G2:M{$lastRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ── Freeze header ─────────────────────────────────────────────────
        $sheet->freezePane('A2');

        return [];
    }
}
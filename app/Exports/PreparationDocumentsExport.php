<?php

namespace App\Exports;

use App\Models\Document;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Events\AfterSheet;
use Carbon\Carbon;

class PreparationDocumentsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    protected Request $request;
    protected $user;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->user = auth()->user();
    }

    public function query()
    {
        $user = $this->user;
        $user_roles = $user->roles()->pluck('name', 'id');

        $query = Document::query()
            ->with(['companies', 'docentete'])
            ->join('document_companies as dc', function ($join) use ($user) {
                $join->on('documents.id', '=', 'dc.document_id')
                    ->where('dc.company_id', $user->company_id)
                    ->whereIn('dc.status_id', [1,2,3,4,5,6,7]);
            })
            ->whereHas('docentete', function ($q) {
                $q->where('DO_Domaine', 0)
                  ->whereIn('DO_Type', [1,2]);
            })
            ->whereHas('lines', function ($q) use ($user_roles, $user) {
                $q->where('company_id', (string) $user->company_id);

                $common = array_intersect(
                    $user_roles->toArray(),
                    [
                        'fabrication',
                        'montage',
                        'preparation_cuisine',
                        'preparation_trailer',
                        'magasinier'
                    ]
                );

                if (!empty($common)) {
                    $q->whereIn('role_id', $user_roles->keys());
                }
            });

        // 🔍 Search
        if ($this->request->filled('search')) {
            $search = $this->request->search;
            $query->where(function ($q) use ($search) {
                $q->where('documents.ref', 'like', "%{$search}%")
                  ->orWhere('documents.piece', 'like', "%{$search}%")
                  ->orWhere('documents.piece_bc', 'like', "%{$search}%");
            });
        }

        // 📅 Date
        if ($this->request->filled('date')) {
            $dates = explode(',', $this->request->date);
            $start = Carbon::parse($dates[0])->startOfDay();
            $end   = Carbon::parse($dates[1] ?? $dates[0])->endOfDay();

            $query->whereHas('docentete', fn ($q) =>
                $q->whereBetween('DO_Date', [$start, $end])
            );
        }

        return $query->select('documents.*');
    }

    public function headings(): array
    {
        return [
            'Référence',
            'Pièce',
            'Client',
            'Date',
            'Date Livraison',
            'Type',
            'Statut',
        ];
    }

    public function map($document): array
    {
        $company = $document->companies
            ->firstWhere('id', auth()->user()->company_id);

        $statusName = '';

        if ($company && $company->pivot?->status_id) {
            $statusName = \App\Models\Status::where(
                'id',
                $company->pivot->status_id
            )->value('name');
        }

        return [
            $document->ref,
            $document->piece,
            $document->client_id,
            optional($document->docentete)->DO_Date
                ? Carbon::parse($document->docentete->DO_Date)->format('d/m/Y')
                : '',
            optional($document->docentete)->DO_DateLivr
                ? Carbon::parse($document->docentete->DO_DateLivr)->format('d/m/Y')
                : '',
            optional($document->docentete)->Type,
            $statusName ?? '',
        ];
    }

    // ✅ Column widths
    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 18,
            'C' => 25,
            'D' => 15,
            'E' => 18,
            'F' => 15,
            'G' => 18,
        ];
    }

    // ✅ Header styles
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2E7D32'],
                ],
            ],
        ];
    }

    // ✅ Borders + zebra rows
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Borders
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
                    ->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                'color' => ['rgb' => 'CCCCCC'],
                            ],
                        ],
                    ]);

                // Zebra rows (light green)
                for ($row = 2; $row <= $highestRow; $row++) {
                    if ($row % 2 === 0) {
                        $sheet->getStyle("A{$row}:{$highestColumn}{$row}")
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setRGB('E8F5E9');
                    }
                }

                // Vertical center alignment
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
                    ->getAlignment()
                    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            },
        ];
    }
}

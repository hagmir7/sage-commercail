<?php

namespace App\Imports;

use App\Models\Emplacement;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmplacementImport implements ToModel, WithHeadingRow
{
    protected $depotId;

    public function __construct($depotId)
    {
        $this->depotId = $depotId;
    }

    public function model(array $row): ?Emplacement
    {
        $code = $row['code'] ?? null;

        if (!$code || Emplacement::where('code', (string) $code)->exists()) {
            Log::warning("Skipped row - missing or duplicate", $row);
            return null;
        }

        return new Emplacement([
            'code' => (string) $code,
            'depot_id' => $row['depot_id'] ?? $this->depotId
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNcfStep1Request;
use App\Http\Requests\UpdateNcfStep2Request;
use App\Http\Requests\UpdateNcfStep3Request;
use App\Http\Requests\UpdateNcfStep4Request;
use App\Http\Requests\UpdateNcfStep5Request;
use App\Http\Requests\UpdateNcfStep6Request;
use App\Models\SupplierNonConformity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\LaravelPdf\Facades\Pdf;


class SupplierNonConformityController extends Controller
{
    /**
     * GET /api/ncf
     */
    public function index(Request $request): JsonResponse
    {
        $query = SupplierNonConformity::with('signatures')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('reference', 'like', "%{$s}%")
                ->orWhere('fournisseur', 'like', "%{$s}%"))
            ->latest();

        return response()->json($query->paginate($request->per_page ?? 15));
    }

    /**
     * GET /api/ncf/{id}
     */
    public function show(int $id): JsonResponse
    {
        $ncf = SupplierNonConformity::with(['signatures', 'attachments'])->findOrFail($id);

        return response()->json($ncf);
    }

    /**
     * POST /api/ncf  — Step 1: Create with general info
     */
    public function store(StoreNcfStep1Request $request): JsonResponse
    {
        $ncf = SupplierNonConformity::create([
            ...$request->validated(),
            'reference'    => SupplierNonConformity::generateReference(),
            'date'         => now(),
            'status'       => 'draft',
            'current_step' => 1,
        ]);

        return response()->json($ncf, 201);
    }

    /**
     * PUT /api/ncf/{id}/step/2
     */
    public function updateStep2(UpdateNcfStep2Request $request, int $id): JsonResponse
    {
        $ncf = SupplierNonConformity::findOrFail($id);
        $ncf->update([...$request->validated(), 'current_step' => 2]);

        return response()->json($ncf);
    }

    /**
     * PUT /api/ncf/{id}/step/3
     */
    public function updateStep3(UpdateNcfStep3Request $request, int $id): JsonResponse
    {
        $ncf = SupplierNonConformity::findOrFail($id);
        $ncf->update([
            ...$request->validated(),
            'current_step' => 3,
            'status'       => 'pending_analysis',
        ]);

        return response()->json($ncf);
    }

    /**
     * PUT /api/ncf/{id}/step/4
     */
    public function updateStep4(UpdateNcfStep4Request $request, int $id): JsonResponse
    {
        $ncf = SupplierNonConformity::findOrFail($id);
        $ncf->update([...$request->validated(), 'current_step' => 4]);

        return response()->json($ncf);
    }

    /**
     * PUT /api/ncf/{id}/step/5
     */
    public function updateStep5(UpdateNcfStep5Request $request, int $id): JsonResponse
    {
        $ncf = SupplierNonConformity::findOrFail($id);
        $ncf->update([
            ...$request->validated(),
            'current_step' => 5,
            'status'       => 'pending_followup',
        ]);

        return response()->json($ncf);
    }

    /**
     * PUT /api/ncf/{id}/step/6  — Final decision + signatures
     */
    public function updateStep6(UpdateNcfStep6Request $request, int $id): JsonResponse
    {
        $ncf = SupplierNonConformity::findOrFail($id);

        $ncf->update([
            'decision_finale' => $request->decision_finale,
            'current_step'    => 6,
            'status'          => 'closed',
            'date_cloture'    => now(),
        ]);

        // Sync signatures
        $ncf->signatures()->delete();
        foreach ($request->signatures as $sig) {
            $ncf->signatures()->create($sig);
        }

        return response()->json($ncf->load('signatures'));
    }

    /**
     * DELETE /api/ncf/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        SupplierNonConformity::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted'], 200);
    }


    public function download(SupplierNonConformity $ncf)
    {
        $ncf->load(['signatures', 'attachments']);
 
        return Pdf::view('pdfs.ncf', ['ncf' => $ncf])
            ->format('a4')
            ->margins(10, 10, 10, 10)
            ->footerHtml('
                <div style="
                    font-size: 15px;
                    text-align: center;
                    width: 100%;
                    color: #555;
                ">
                    © Ce document ne doit être ni reproduit ni communiqué sans l\'autorisation d\'INTERCOCINA
                </div>
            ')
            ->name("ncf_{$ncf->reference}.pdf")
            ->download();
    }
 

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierNonConformity extends Model
{
    use SoftDeletes;

    protected $table = 'supplier_non_conformities';

    protected $fillable = [
        'reference', 'date', 'status', 'current_step',
        // Step 1
        'fournisseur', 'reference_commande', 'bon_livraison', 'date_reception',
        'code_article', 'produit_concerne', 'quantite_receptionnee',
        'quantite_non_conforme', 'detectee_par', 'detectee_par_autre', 'date_detection',
        // Step 2
        'nature_nc', 'types_ecart', 'type_ecart_autre', 'description_detaillee',
        'preuves_jointes', 'reference_lot', 'gravite',
        // Step 3
        'decision_provisoire', 'mesures_immediates', 'responsable_action', 'date_execution',
        // Step 4
        'causes_probables', 'cause_principale', 'action_corrective',
        'responsable_action_corrective', 'date_previsionnelle',
        // Step 5
        'responsable_suivi', 'date_verification', 'action_realisee',
        'action_efficace', 'fnc_reference', 'date_cloture',
        // Step 6
        'decision_finale',
    ];

    protected $casts = [
        'date'                  => 'date',
        'date_reception'        => 'date',
        'date_detection'        => 'date',
        'date_execution'        => 'date',
        'date_previsionnelle'   => 'date',
        'date_verification'     => 'date',
        'date_cloture'          => 'date',
        'types_ecart'           => 'array',
        'preuves_jointes'       => 'boolean',
        'action_realisee'       => 'boolean',
        'action_efficace'       => 'boolean',
        'quantite_receptionnee' => 'decimal:2',
        'quantite_non_conforme' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function signatures(): HasMany
    {
        return $this->hasMany(NcfSignature::class, 'non_conformity_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(NcfAttachment::class, 'non_conformity_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public static function generateReference(): string
    {
        $year  = now()->format('Y');
        $count = static::whereYear('created_at', $year)->count() + 1;

        return sprintf('NCF-%s-%04d', $year, $count);
    }

    public function canTransitionTo(string $status): bool
    {
        $transitions = [
            'draft'             => ['pending_analysis'],
            'pending_analysis'  => ['pending_followup'],
            'pending_followup'  => ['closed'],
        ];

        return in_array($status, $transitions[$this->status] ?? []);
    }
}
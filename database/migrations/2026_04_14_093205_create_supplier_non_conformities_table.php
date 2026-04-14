<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_non_conformities', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('date');
            $table->enum('status', ['draft', 'pending_analysis', 'pending_followup', 'closed'])->default('draft');
            $table->unsignedTinyInteger('current_step')->default(1);

            // Step 1: General Information
            $table->string('fournisseur');
            $table->string('reference_commande')->nullable();
            $table->string('bon_livraison')->nullable();
            $table->date('date_reception')->nullable();
            $table->string('code_article')->nullable();
            $table->string('produit_concerne');
            $table->decimal('quantite_receptionnee', 12, 2)->default(0);
            $table->decimal('quantite_non_conforme', 12, 2)->default(0);
            $table->enum('detectee_par', ['approvisionnement', 'controle_qualite', 'production', 'autre'])->default('controle_qualite');
            $table->string('detectee_par_autre')->nullable();
            $table->date('date_detection');

            // Step 2: Non-Conformity Description
            $table->enum('nature_nc', ['quantitative', 'qualitative', 'documentaire', 'autre'])->default('quantitative');
            $table->json('types_ecart')->nullable();
            $table->string('type_ecart_autre')->nullable();
            $table->text('description_detaillee')->nullable();
            $table->boolean('preuves_jointes')->default(false);
            $table->string('reference_lot')->nullable();
            $table->enum('gravite', ['mineure', 'majeure', 'critique'])->default('mineure');

            // Step 3: Immediate Treatment
            $table->enum('decision_provisoire', ['accepte', 'accepte_sous_reserve', 'refuse_retour'])->nullable();
            $table->text('mesures_immediates')->nullable();
            $table->string('responsable_action')->nullable();
            $table->date('date_execution')->nullable();

            // Step 4: Cause Analysis
            $table->text('causes_probables')->nullable();
            $table->text('cause_principale')->nullable();
            $table->text('action_corrective')->nullable();
            $table->string('responsable_action_corrective')->nullable();
            $table->date('date_previsionnelle')->nullable();

            // Step 5: Follow-up & Effectiveness
            $table->string('responsable_suivi')->nullable();
            $table->date('date_verification')->nullable();
            $table->boolean('action_realisee')->nullable();
            $table->boolean('action_efficace')->nullable();
            $table->string('fnc_reference')->nullable();
            $table->date('date_cloture')->nullable();

            // Step 6: Final Decision
            $table->enum('decision_finale', ['accepte_apres_correction', 'refuse_definitivement', 'accepte_avec_derogation'])->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_non_conformities');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ncf_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('non_conformity_id')
                  ->constrained('supplier_non_conformities')
                  ->cascadeOnDelete();
            $table->enum('entite', ['achats', 'direction']);
            $table->string('nom_prenom');
            $table->date('date');
            $table->string('visa')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ncf_signatures');
    }
};
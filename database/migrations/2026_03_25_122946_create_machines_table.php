<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('family');                        // FAMILLE: PLACAGE, DECOUPAGE, CNC, PERCAGE, ASSEMBLAGE, AUTRE
            $table->string('ref_machine')->nullable();       // REF-MACH
            $table->string('group_code')->nullable();        // Grouping code (e.g. LINEA, IDM, STEFANI...)
            $table->string('machine_id')->unique();          // Machine_ID (e.g. MAH1, LN1, GB1...)
            $table->string('machine_name')->nullable();      // Machine: full commercial name
            $table->string('serial_number')->nullable();     // Matricule
            $table->string('alias')->nullable();             // ALIAS: internal label
            $table->string('manufacturer')->nullable();      // Fabriqueur: brand/manufacturer
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('family');
            $table->index('machine_id');
            $table->index('ref_machine');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
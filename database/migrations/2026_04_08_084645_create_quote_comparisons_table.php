<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quote_comparisons', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('comparison_date');
            $table->string('department');
            $table->text('purchase_object');
            $table->string('selected_provider')->nullable();
            $table->text('selection_justification')->nullable();
            $table->string('purchasing_manager')->nullable();
            $table->date('purchasing_manager_date')->nullable();
            $table->string('general_director')->nullable();
            $table->date('general_director_date')->nullable();
            $table->enum('status', ['brouillon', 'soumis', 'valide', 'rejete'])->default('brouillon');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_comparisons');
    }
};
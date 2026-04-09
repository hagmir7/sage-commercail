<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quote_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_comparison_id')->constrained()->cascadeOnDelete();
            $table->string('provider_name');
            $table->tinyInteger('price_score')->default(0);       // 30%
            $table->tinyInteger('delivery_score')->default(0);    // 25%
            $table->tinyInteger('technical_score')->default(0);   // 25%
            $table->tinyInteger('reliability_score')->default(0); // 10%
            $table->tinyInteger('payment_score')->default(0);     // 10%
            $table->decimal('weighted_total', 5, 2)->default(0);
            $table->timestamps();
            $table->unique(['quote_comparison_id', 'provider_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_evaluations');
    }
};

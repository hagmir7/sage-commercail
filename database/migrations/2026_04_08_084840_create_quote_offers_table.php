<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quote_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_comparison_id')->constrained()->cascadeOnDelete();
            $table->string('provider_name');
            $table->string('quote_reference')->nullable();
            $table->date('quote_date')->nullable();
            $table->string('validity_period')->nullable();
            $table->text('product_designation')->nullable();
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 14, 2)->default(0);
            $table->string('payment_conditions')->nullable();
            $table->string('delivery_delay')->nullable();
            $table->string('warranty')->nullable();
            $table->text('technical_compliance')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_offers');
    }
};
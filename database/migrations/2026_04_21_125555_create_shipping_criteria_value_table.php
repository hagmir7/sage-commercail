<?php

use App\Models\Shipping;
use App\Models\ShippingCriteria;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_criteria_value', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Shipping::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ShippingCriteria::class)->constrained()->cascadeOnDelete();
            $table->enum('status', ['Oui', 'Non', 'N.A']);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_criteria_value');
    }
};
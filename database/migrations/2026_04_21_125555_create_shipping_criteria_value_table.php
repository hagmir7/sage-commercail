<?php

use App\Models\Document;
use App\Models\ShippingCriteria;
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
        Schema::create('shipping_criteria_value', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ShippingCriteria::class);
            $table->enum('status', ['Oui', 'Non', 'N.A']);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_criteria_value');
    }
};

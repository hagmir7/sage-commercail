<?php

use App\Models\InventoryStock;
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
        Schema::create('inventory_article_palette', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(InventoryStock::class);
            $table->foreignId('palette_id')->constrained()->onDelete('cascade');
            $table->float('quantity')->default(0.0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_article_palette');
    }
};

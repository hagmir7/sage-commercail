<?php

use App\Models\Inventory;
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
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('code_article');
            $table->string('designation');
            $table->foreignIdFor(Inventory::class);
            $table->float('stock_min')->nullable();
            $table->float('stock_init')->nullable();
            $table->float('stock_end')->nullable();
            $table->string('condition')->nullable();
            $table->float('quantity')->default(0);
            $table->float('price')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};

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
        Schema::table('emplacements', function (Blueprint $table) {
            $table->foreignIdFor(Inventory::class)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emplacements', function (Blueprint $table) {
            $table->dropColumn('inventory_id');
        });
    }
};

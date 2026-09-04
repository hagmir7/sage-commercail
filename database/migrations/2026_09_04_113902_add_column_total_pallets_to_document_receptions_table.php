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
        Schema::table('document_receptions', function (Blueprint $table) {
            $table->integer('total_palettes')->nullable()->after('depot_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_receptions', function (Blueprint $table) {
            $table->dropColumn('total_palettes');
        });
    }
};

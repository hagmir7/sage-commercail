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
        Schema::table('article_stocks', function (Blueprint $table) {
            $table->string('palette_condition')->nullable();
            $table->string('unit')->nullable();
            $table->string('gamme')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_stocks', function (Blueprint $table) {
            $table->dropColumn('palette_condition');
            $table->dropColumn('unit');
            $table->dropColumn('gamme');
        });
    }
};

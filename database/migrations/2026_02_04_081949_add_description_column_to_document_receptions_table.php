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
            $table->string('description')->nullable();
            $table->string('container_code')->nullable();
            $table->string('depot_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_receptions', function (Blueprint $table) {
            $table->dropColumn('description');
            $table->dropColumn('container_code');
            $table->dropColumn('depot_code');
        });
    }
};

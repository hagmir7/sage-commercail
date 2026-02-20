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
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->date('planned_at')->nullable();
            $table->date('sended_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropColumn(['planned_at', 'sended_at']);
        });
    }
};

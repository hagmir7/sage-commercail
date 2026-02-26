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
            $table->string('CT_Num')->nullable();
            $table->string('piece_bc')->nullable();
            $table->boolean('meet_deadline')->nullable();
            $table->string('document_pieces')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropColumn(['planned_at', 'sended_at', 'CT_Num', 'piece_bc', 'meet_deadline', 'document_pieces']);
        });
    }
};

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
        Schema::table('lines', function (Blueprint $table) {
            // Drop old foreign key
            $table->dropForeign(['docligne_id']);

            // Make column nullable
            $table->integer('docligne_id')->nullable()->change();

            // Add FK with ON DELETE SET NULL
            $table->foreign('docligne_id')
                ->references('cbMarq')
                ->on('F_DOCLIGNE')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lines', function (Blueprint $table) {
            // Drop new FK
            $table->dropForeign(['docligne_id']);

            // Make NOT NULL again
            $table->integer('docligne_id')->nullable(false)->change();

            // Re-add old FK (cascade or your previous behavior)
            $table->foreign('docligne_id')
                ->references('cbMarq')
                ->on('F_DOCLIGNE')
                ->onDelete('cascade');
        });
    }
};

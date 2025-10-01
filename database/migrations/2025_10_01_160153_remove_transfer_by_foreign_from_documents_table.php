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
        Schema::table('documents', function (Blueprint $table) {
             $table->dropForeign(['transfer_by']); // enlève la contrainte FK
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\User::class, 'transfer_by')
                  ->constrained('users')
                  ->onDelete('cascade');
        });
    }
};

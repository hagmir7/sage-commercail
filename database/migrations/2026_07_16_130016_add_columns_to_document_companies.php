<?php

use App\Models\User;
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
        Schema::table('document_companies', function (Blueprint $table) {
            $table->dateTime('fabricated_at')->nullable();
            $table->foreignIdFor(User::class, 'fabricated_by')->nullable();
            $table->dateTime('complation_date')->nullable();
            $table->dateTime('delivery_date')->nullable();
            $table->text('note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_companies', function (Blueprint $table) {
            $table->dropColumn(['fabricated_at', 'fabricated_by', 'complation_date', 'note']);
        });
    }
};

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
            $table->dateTime('cutted_at')->nullable();
            $table->foreignIdFor(User::class, 'cutted_by')->nullable();
            $table->dateTime('montage_at')->nullable();
            $table->foreignIdFor(User::class, 'montage_by')->nullable();
            $table->dateTime('peinture_at')->nullable();
            $table->foreignIdFor(User::class, 'peinture_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_companies', function (Blueprint $table) {
            $table->dropColumn(['cutted_by', 'cutted_at', 'montage_at', 'montage_by', 'peinture_at', 'peinture_by']);
        });
    }
};

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
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignIdFor(User::class, 'validated_by')->nullable();
            $table->foreignIdFor(User::class, 'controlled_by')->nullable();
            $table->dropColumn('completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('validated_by');
            $table->dropColumn('controlled_by');
            $table->boolean('completed')->default(false);
        });
    }
};

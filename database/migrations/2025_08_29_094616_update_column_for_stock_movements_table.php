<?php

use App\Models\Emplacement;
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
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('from_emplacement_id');
            $table->foreignIdFor(Emplacement::class, 'to_emplacement_id')->nullable()->change();
            $table->foreignIdFor(Emplacement::class, 'emplacement_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignIdFor(Emplacement::class, 'from_emplacement_id');
            $table->foreignIdFor(Emplacement::class, 'to_emplacement_id')->nullable(false)->change();
            $table->dropColumn('emplacement_id');
        });
    }
};

<?php

use App\Models\ArticleStock;
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
        Schema::create('emplacement_limit', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Emplacement::class);
            $table->foreignIdFor(ArticleStock::class);
            $table->bigInteger('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emplacement_limit');
    }
};

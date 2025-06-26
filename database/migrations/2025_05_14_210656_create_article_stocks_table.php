<?php

use App\Models\ArticleFamily;
use App\Models\Palette;
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
        Schema::create('article_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->text('description');
            $table->string('name')->nullable();
            $table->string('color')->nullable();
            $table->float('qte_inter')->default(0);
            $table->float('qte_serie')->default(0);
            $table->float('quantity')->default(0);
            $table->float('stock_min')->default(0);
            $table->float('price')->default();
            $table->float('thickness')->nullable();
            $table->float('height')->nullable();
            $table->float('width')->nullable();
            $table->float('depth')->nullable();
            $table->string('chant')->nullable();
            $table->string('condition')->nullable();
            $table->string('palette_condition')->nullable();
            $table->string('unit')->nullable();
            $table->string('code_supplier')->nullable();
            $table->string('qr_code')->nullable();
            $table->string('gamme')->nullable();
            $table->string('category')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_stocks');
    }
};

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
            $table->string('code_supplier')->nullable()->unique();
            $table->string('qr_code')->nullable()->unique();
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
            $table->integer('family_id');
            $table->integer('article_id');
            $table->string('condition')->nullable();

            $table->foreign('family_id')
                ->references('cbMarq')
                ->on('F_FAMILLE')
                ->onDelete('cascade');

            $table->foreign('article_id')
                ->references('cbMarq')
                ->on('F_ARTICLE')
                ->onDelete('cascade');

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

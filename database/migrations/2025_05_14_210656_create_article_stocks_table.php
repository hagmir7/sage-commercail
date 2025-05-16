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
            $table->integer('qte_inter')->default(0);
            $table->integer('qte_serie')->default(0);
            $table->foreignIdFor(ArticleFamily::class);
            $table->float('thickness')->nullable();
            $table->float('hieght')->nullable();
            $table->float('width')->nullable();
            $table->float('depth')->nullable();
            $table->string('chant')->nullable();
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

<?php

use App\Models\ArticleStock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('of_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('of_id')->constrained('ofs')->cascadeOnDelete();
            $table->foreignIdFor(ArticleStock::class);
            $table->string('article_code');             
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('quantity_produite', 10, 2)->default(0);
            $table->enum('statut', ['en_attente', 'en_cours', 'terminé'])->default('en_attente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('of_lines');
    }
};
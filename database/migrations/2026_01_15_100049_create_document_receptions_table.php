<?php

use App\Models\Document;
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
        Schema::create('document_receptions', function (Blueprint $table) {
            $table->id();
            $table->string('emplacement_code');
            $table->string('article_code');
            $table->float('quantity');
            $table->foreignIdFor(Document::class);
            $table->string('company')->nullable();
            $table->string('username');
            $table->string('colis_type')->nullable();
            $table->float('colis_quantity')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_receptions');
    }
};

<?php

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
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_document_id')->constrained()->onDelete('cascade');
            $table->string('code')->nullable();
            $table->text('description');
            $table->decimal('quantity', 10, 2)->default(1); 
            $table->string('unit')->nullable();
            $table->decimal('estimated_price', 10, 2)->nullable(); 
            $table->decimal('total', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_lines');
    }
};

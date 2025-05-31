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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->string('designation');
            $table->foreignId('from_emplacement_id')->nullable()->constrained('emplacements')->nullOnDelete();
            $table->foreignId('to_emplacement_id')->nullable()->constrained('emplacements')->nullOnDelete();
            $table->integer('quantity');
            $table->enum('movement_type', ['IN', 'OUT', 'TRANSFER']);
            $table->timestamp('movement_date');
            $table->string('moved_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};

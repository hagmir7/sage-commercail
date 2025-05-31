<?php

use App\Models\Emplacement;
use App\Models\Inventory;
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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('code_article');
            $table->string('designation');
            $table->foreignIdFor(Emplacement::class)->nullable();
            $table->foreignIdFor(Inventory::class);
            $table->enum('type', ['IN', 'OUT', 'TRANSFER'])->default('IN');
            $table->integer('quantity');
            $table->foreignIdFor(User::class)->nullable();
            $table->date('date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};

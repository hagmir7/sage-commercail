<?php

use App\Models\Line;
use App\Models\Role;
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
        Schema::create('role_quantity_line', function (Blueprint $table) {
            $table->id();
            $table->float('quantity');
            $table->foreignIdFor(Line::class);
            $table->foreignIdFor(Role::class);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_quantity_line');
    }
};

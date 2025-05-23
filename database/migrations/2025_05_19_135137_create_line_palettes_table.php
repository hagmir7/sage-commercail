<?php

use App\Models\Line;
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
        Schema::create('line_palettes', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Line::class);
            $table->foreignIdFor(Palette::class);
            $table->integer('quantity');
            $table->dateTime('controlled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_palettes');
    }
};

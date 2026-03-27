<?php

use App\Models\Company;
use App\Models\TravelDriver;
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
        Schema::create('travel_receptions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(TravelDriver::class);
            $table->string('code')->nullable();
            $table->foreignIdFor(Company::class)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_receptions');
    }
};

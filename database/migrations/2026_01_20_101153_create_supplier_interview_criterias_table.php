<?php

use App\Models\SupplierCriteria;
use App\Models\SupplierInterview;
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
        Schema::create('supplier_interview_criterias', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SupplierInterview::class);
            $table->foreignIdFor(SupplierCriteria::class);
            $table->integer('note');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_interview_criterias');
    }
};

<?php

use App\Models\Company;
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
        Schema::create('company_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('code_article');
            $table->string('designation');
            $table->foreignIdFor(Company::class);
            $table->float('quantity')->default(0);
            $table->float('min_quantity')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_stocks');
    }
};

<?php

use App\Models\Company;
use App\Models\Docligne;
use App\Models\Document;
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
        Schema::create('lines', function (Blueprint $table) {
            $table->id();
            $table->string('tiers');
            $table->string('ref');
            $table->string('quantity');
            $table->string('design');
            $table->string('dimensions')->nullable();
            $table->foreignIdFor(Document::class, 'document_id');
            $table->foreignIdFor(Docligne::class, 'docligne_id');
            $table->foreignIdFor(Company::class);
            $table->foreignIdFor(Role::class)->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lines');
    }
};

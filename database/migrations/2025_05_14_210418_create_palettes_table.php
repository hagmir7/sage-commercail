<?php

use App\Models\Company;
use App\Models\Document;
use App\Models\Position;
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
        Schema::create('palettes', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->foreignIdFor(Company::class);
            $table->foreignIdFor(Position::class)->nullable();
            $table->enum('type', ["Livraison", 'Stock'])->nullable();
            $table->foreignIdFor(Document::class)->nullable();
            $table->foreignIdFor(User::class);
             $table->boolean('controlled')->default(false);
            $table->timestamps();
            $table->unique(['code', 'company_id']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('palettes');
    }
};

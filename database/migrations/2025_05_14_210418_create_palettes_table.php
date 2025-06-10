<?php

use App\Models\Company;
use App\Models\Document;
use App\Models\Emplacement;
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
            $table->foreignIdFor(Emplacement::class)->nullable();
            $table->enum('type', ["Livraison", 'Stock'])->nullable();
            $table->foreignIdFor(Document::class)->nullable();
            $table->float('weight')->nullable();
            $table->foreignIdFor(User::class); // creted by
            $table->boolean('controlled')->default(false);
            $table->dateTime('delivered_at')->nullable();
            $table->foreignIdFor(User::class, 'delivered_by')->nullable();
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

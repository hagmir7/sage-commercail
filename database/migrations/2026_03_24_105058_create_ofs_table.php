<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofs', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('date_lancement');
            $table->date('date_demarrage');
            $table->string('reference_machine')->nullable();
            $table->enum('type_commande', ['standard', 'speciale'])->default('standard');
            $table->enum('statut', ['brouillon', 'lancé', 'en_cours', 'terminé', 'annulé'])->default('brouillon');
            $table->foreignIdFor(User::class)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofs');
    }
};
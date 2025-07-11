<?php

use App\Models\Docentete;
use App\Models\Status;
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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->integer('docentete_id');
            $table->string('piece');
            $table->string('type');
            $table->string('ref');
            $table->integer('expedition');
            $table->string('client_id', 17);
            $table->foreignIdFor(User::class, 'transfer_by')->constrained('users')->onDelete('cascade');
            $table->foreignIdFor(User::class, 'validated_by')->nullable();
            $table->foreignIdFor(User::class, 'controlled_by')->nullable();
            $table->foreignIdFor(User::class)->nullable(); // chargement
            $table->foreignIdFor(Status::class)->default(1);
            $table->string('piece_bl')->nullable();
            $table->string('piece_fa')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

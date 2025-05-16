<?php

use App\Models\Company;
use App\Models\Docligne;
use App\Models\Document;
use App\Models\Palette;
use App\Models\Role;
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
        Schema::create('lines', function (Blueprint $table) {
            $table->id();
            $table->string('ref');
            $table->string('name')->nullable();
            $table->string('quantity');
            $table->string('design');
            $table->string('dimensions')->nullable();
            $table->boolean('validated')->default(false);
            $table->boolean('completed')->default(false);
            $table->dateTime('complation_date')->nullable();
            $table->integer('docligne_id');
            $table->foreign('docligne_id')->references('cbMarq')->on('F_DOCLIGNE')->onDelete('cascade');
            $table->foreignIdFor(Document::class, 'document_id');
            $table->foreignIdFor(Palette::class)->nullable();
            $table->foreignIdFor(Company::class);
            $table->foreignIdFor(Role::class)->nullable();
            $table->foreignIdFor(Role::class, 'next_role_id')->nullable();
            $table->foreignIdFor(User::class, 'received')->nullable();
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

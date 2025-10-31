<?php

use App\Models\PurchaseDocument;
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
        Schema::create('purchase_document_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(PurchaseDocument::class); // related document
            $table->string('status'); // status at that moment
            $table->foreignId('changed_by')->constrained('users')->onDelete('cascade'); // who changed the status
            $table->text('comment')->nullable(); // optional comment or reason
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_document_histories');
    }
};

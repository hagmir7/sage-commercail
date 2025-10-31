<?php

use App\Models\Service;
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
        Schema::create('purchase_documents', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // unique purchase document code
            $table->string('reference')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // creator user
            $table->foreignIdFor(Service::class)->nullable(); // related department/service
            $table->integer('status')->default(1); // document status
            $table->string('piece')->nullable(); // accounting or ERP reference (ex: Sage)
            $table->text('note')->nullable(); // internal note
            $table->boolean('urgent')->default(false); // mark as urgent
            $table->timestamp('submitted_at')->nullable(); // date submitted
            $table->timestamp('approved_at')->nullable(); // date approved
            $table->timestamp('rejected_at')->nullable(); // date rejected
            $table->timestamp('ordered_at')->nullable(); // date ordered
            $table->timestamp('received_at')->nullable(); // date received
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_documents');
    }
};

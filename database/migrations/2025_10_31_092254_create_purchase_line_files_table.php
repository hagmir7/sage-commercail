<?php

use App\Models\PurchaseDocument;
use App\Models\PurchaseLine;
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
        Schema::create('purchase_line_files', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(PurchaseLine::class); // related document
            $table->string('file_path'); // file storage path or URL
            $table->string('file_name')->nullable(); // original filename
            $table->foreignIdFor(User::class); // who uploaded
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_line_files');
    }
};

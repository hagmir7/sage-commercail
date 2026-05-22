<?php

use App\Models\PurchaseLine;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\call;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_line_non_compliant', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(PurchaseLine::class);
            $table->foreignIdFor(User::class);
            $table->integer('quantity');
            $table->text('note')->nullable();
            $table->text('file')->nullable();
            $table->string('supplier_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_line_non_compliant');
    }
};

<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lines', function (Blueprint $table) {

            $table->string('piece_bc')->nullable();
            $table->string('piece_pl')->nullable();
            $table->string('piece_bl')->nullable();
            $table->string('piece_fa')->nullable();

            $table->foreignIdFor(User::class, 'fabricated_by')
            ->nullable();

            $table->foreignIdFor(User::class, 'mounted_by')
                ->nullable();

            $table->foreignIdFor(User::class, 'prepared_by')->nullable();

            $table->dateTime('fabricated_at')->nullable();
            $table->dateTime('mounted_at')->nullable();
            $table->dateTime('prepared_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lines', function (Blueprint $table) {
            $table->dropColumn([
                'piece_bc',
                'piece_pl',
                'piece_bl',
                'piece_fa',
                'fabricated_by',
                'fabricated_at',
                'mounted_by',
                'mounted_at',
                'prepared_by',
                'prepared_at',
            ]);
        });
    }
};
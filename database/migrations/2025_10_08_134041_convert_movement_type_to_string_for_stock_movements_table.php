<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First drop the constraint
        DB::statement("
            DECLARE @sql NVARCHAR(MAX);
            SELECT @sql = 'ALTER TABLE stock_movements DROP CONSTRAINT ' + name 
            FROM sys.check_constraints 
            WHERE OBJECT_NAME(parent_object_id) = 'stock_movements' 
            AND definition LIKE '%movement_type%';
            EXEC(@sql);
        ");

        // Then change the column type
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('movement_type')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->enum('movement_type', ['IN', 'OUT', 'TRANSFER'])->change();
        });

        // Recreate the constraint in down method if needed
        DB::statement("
            ALTER TABLE stock_movements 
            ADD CONSTRAINT CK_stock_movements_movement_type 
            CHECK (movement_type IN ('IN', 'OUT', 'TRANSFER'))
        ");
    }
};
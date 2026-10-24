<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify transactions.type
        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM('income', 'expense', 'transfer', 'saving', 'withdraw_saving') NOT NULL");
        
        // Modify categories.type
        DB::statement("ALTER TABLE categories MODIFY COLUMN type ENUM('income', 'expense', 'saving', 'withdraw_saving') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM('income', 'expense', 'transfer') NOT NULL");
        DB::statement("ALTER TABLE categories MODIFY COLUMN type ENUM('income', 'expense') NOT NULL");
    }
};

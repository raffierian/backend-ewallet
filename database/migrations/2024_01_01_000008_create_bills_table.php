<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->enum('recurrence', ['daily', 'weekly', 'monthly', 'yearly']);
            $table->tinyInteger('due_day');
            $table->date('next_due_date');
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_create_transaction')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};

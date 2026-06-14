<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('book_id')->constrained('books')->onDelete('restrict');
            $table->foreignUuid('customer_id')->constrained('customers')->onDelete('restrict');
            $table->string('loan_number', 50);
            $table->decimal('loan_amount', 12, 2);
            $table->decimal('interest_amount', 12, 2)->default(0);
            $table->enum('loan_type', ['daily', 'weekly', 'monthly'])->default('daily');
            $table->string('line', 10)->default('line1');
            $table->date('issued_date');
            $table->date('completed_date')->nullable();
            $table->decimal('total_collected', 12, 2)->default(0);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->index('book_id');
            $table->index('customer_id');
            $table->index(['book_id', 'is_deleted']);
            $table->unique(['book_id', 'loan_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};

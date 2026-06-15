<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('book_id')->constrained('books')->onDelete('restrict');
            $table->foreignUuid('loan_id')->constrained('loans')->onDelete('cascade');
            $table->date('entry_date');
            $table->decimal('amount', 12, 2);
            $table->enum('mode', ['cash', 'gpay'])->default('cash');
            $table->timestamps();

            // One collection per loan per day.
            $table->unique(['loan_id', 'entry_date']);
            $table->index(['book_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_entries');
    }
};

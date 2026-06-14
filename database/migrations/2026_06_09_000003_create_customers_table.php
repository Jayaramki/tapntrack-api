<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('book_id')->constrained('books')->onDelete('restrict');
            $table->string('name', 150);
            $table->string('father_name', 150)->nullable();
            $table->string('phone', 20);
            $table->string('address', 255)->nullable();
            $table->string('profession', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('book_id');
            $table->index(['book_id', 'name']);
            $table->unique(['book_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

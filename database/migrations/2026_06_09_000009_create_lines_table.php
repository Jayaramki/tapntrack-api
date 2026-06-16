<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('book_id')->constrained('books')->onDelete('restrict');
            $table->string('name', 50);
            $table->string('color', 20)->default('#546E7A');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('book_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lines');
    }
};

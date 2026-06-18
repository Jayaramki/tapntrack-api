<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // URL-safe handle carried in the path (app.tapntrack.in/<slug>/...).
            $table->string('slug', 50)->unique();
            $table->string('name', 150);
            $table->string('owner_name', 150)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            // trial | active | past_due | suspended (string, not enum, for SQLite/MySQL parity)
            $table->string('status', 20)->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

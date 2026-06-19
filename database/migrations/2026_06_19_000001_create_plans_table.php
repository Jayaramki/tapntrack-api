<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->string('code', 30)->primary();           // trial | basic | standard | premium | enterprise
            $table->string('label', 50);
            $table->unsignedInteger('max_active_loans')->nullable();  // null = unlimited
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_books')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed initial tiers from config/plans.php. After this, the DB is the
        // source of truth (editable from the admin UI); config is just defaults.
        $order = 0;
        foreach ((array) config('plans') as $code => $p) {
            DB::table('plans')->insert([
                'code' => $code,
                'label' => $p['label'] ?? ucfirst((string) $code),
                'max_active_loans' => $p['max_active_loans'] ?? null,
                'max_users' => $p['max_users'] ?? null,
                'max_books' => $p['max_books'] ?? null,
                'sort_order' => $order++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

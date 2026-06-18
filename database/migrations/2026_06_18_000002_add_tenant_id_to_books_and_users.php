<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fixed sentinel for the one default tenant that existing (pre-SaaS) data is
     * folded under. Mirrors the book sentinel pattern. The seeder reuses it so a
     * fresh install and a backfilled live DB converge on the same tenant id.
     */
    private const DEFAULT_TENANT_ID = '11111111-1111-1111-1111-111111111111';

    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('id');
        });

        // Backfill: fold any pre-existing data under one default tenant. On a
        // fresh migrate (no rows yet) this is a no-op — the seeder creates the
        // tenant instead.
        if (DB::table('books')->exists() || DB::table('users')->exists()) {
            DB::table('tenants')->updateOrInsert(
                ['id' => self::DEFAULT_TENANT_ID],
                [
                    'slug' => 'balaji',
                    'name' => 'Balaji Finance',
                    'status' => 'active',
                    'is_deleted' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            DB::table('books')->whereNull('tenant_id')->update(['tenant_id' => self::DEFAULT_TENANT_ID]);
            DB::table('users')->whereNull('tenant_id')->update(['tenant_id' => self::DEFAULT_TENANT_ID]);
        }

        Schema::table('books', function (Blueprint $table) {
            $table->index('tenant_id');
        });

        // Username is unique PER TENANT, not globally: drop the global unique,
        // add a composite (tenant_id, username).
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->index('tenant_id');
            $table->unique(['tenant_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'username']);
            $table->dropIndex(['tenant_id']);
            $table->unique('username');
            $table->dropColumn('tenant_id');
        });

        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plan', 30)->default('trial')->after('status');
        });

        // Existing live tenants are real (pre-billing) accounts — put active ones
        // on a generous paid tier so their data isn't retroactively over-limit.
        DB::table('tenants')->where('status', 'active')->update(['plan' => 'premium']);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('plan');
        });
    }
};

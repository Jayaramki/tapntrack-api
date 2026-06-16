<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Lines are now user-defined masters (free names), so widen the stored value.
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('line', 50)->default('Line 1')->change();
        });
        Schema::table('archived_loans', function (Blueprint $table) {
            $table->string('line', 50)->default('Line 1')->change();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('line', 10)->default('line1')->change();
        });
        Schema::table('archived_loans', function (Blueprint $table) {
            $table->string('line', 10)->default('line1')->change();
        });
    }
};

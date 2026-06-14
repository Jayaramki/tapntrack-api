<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('email');
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('email');
            $table->string('role')->default('field_agent')->after('remember_token');
            $table->uuid('book_id')->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('book_id');
            $table->json('permissions')->nullable()->after('is_active');
            $table->string('security_question')->nullable()->after('permissions');
            $table->string('security_answer')->nullable()->after('security_question');
            $table->string('api_token', 80)->nullable()->unique()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['api_token']);
            $table->dropColumn([
                'username',
                'first_name',
                'last_name',
                'phone',
                'role',
                'book_id',
                'is_active',
                'permissions',
                'security_question',
                'security_answer',
                'api_token',
            ]);
        });
    }
};

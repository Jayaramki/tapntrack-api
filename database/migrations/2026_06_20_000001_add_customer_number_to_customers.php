<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('customer_number')->nullable()->after('book_id');
        });

        // Backfill: assign a sequential number per book (oldest first). Done in
        // PHP for SQLite/MySQL parity.
        foreach (DB::table('customers')->distinct()->pluck('book_id') as $bookId) {
            $n = 1;
            $ids = DB::table('customers')->where('book_id', $bookId)
                ->orderBy('created_at')->orderBy('id')->pluck('id');
            foreach ($ids as $id) {
                DB::table('customers')->where('id', $id)->update(['customer_number' => $n++]);
            }
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->unique(['book_id', 'customer_number']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['book_id', 'customer_number']);
            $table->dropColumn('customer_number');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel's `enum()` builds a CHECK constraint named
        // `<table>_<column>_check`. Drop it and re-create with the
        // expanded value set.
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check");
        DB::statement("
            ALTER TABLE orders ADD CONSTRAINT orders_status_check
            CHECK (status IN (
                'draft','pending','confirmed','checked_in','checked_out',
                'completed','cancelled','refunded'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check");
        DB::statement("
            ALTER TABLE orders ADD CONSTRAINT orders_status_check
            CHECK (status IN (
                'draft','confirmed','checked_in','checked_out',
                'completed','cancelled','refunded'
            ))
        ");
    }
};

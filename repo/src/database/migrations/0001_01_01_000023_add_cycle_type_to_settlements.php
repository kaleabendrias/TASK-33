<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            // Operational requirement: settlements are no longer hardcoded to a
            // weekly cadence. Persisting cycle_type lets reports + downstream
            // commissions stay consistent for the lifetime of each row.
            $table->enum('cycle_type', ['weekly', 'biweekly'])
                ->default('weekly')
                ->after('status');
            $table->index('cycle_type');
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropIndex(['cycle_type']);
            $table->dropColumn('cycle_type');
        });
    }
};

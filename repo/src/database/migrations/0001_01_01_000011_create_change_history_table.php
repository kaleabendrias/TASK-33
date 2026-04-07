<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only change history for critical records
        Schema::create('change_history', function (Blueprint $table) {
            $table->id();
            $table->string('trackable_type', 100);            // polymorphic
            $table->unsignedBigInteger('trackable_id');
            $table->string('field_name', 100);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['trackable_type', 'trackable_id'], 'ch_poly_idx');
            $table->index('changed_by');
        });

        // Append-only protection
        DB::statement("
            CREATE OR REPLACE FUNCTION prevent_change_history_mutation() RETURNS trigger AS \$\$
            BEGIN
                RAISE EXCEPTION 'change_history is append-only: % operations are forbidden', TG_OP;
                RETURN NULL;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER change_history_immutable
            BEFORE UPDATE OR DELETE ON change_history
            FOR EACH ROW EXECUTE FUNCTION prevent_change_history_mutation();
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS change_history_immutable ON change_history');
        DB::statement('DROP FUNCTION IF EXISTS prevent_change_history_mutation()');
        Schema::dropIfExists('change_history');
    }
};

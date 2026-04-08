<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 100);                     // login, logout, create, update, delete, revoke, password_reset …
            $table->string('entity_type', 100)->nullable();     // polymorphic: "User", "Resource", …
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->jsonb('metadata')->nullable();              // extra context
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index('actor_id');
            $table->index('action');
            $table->index('created_at');
        });

        // Make audit_logs append-only: revoke UPDATE, DELETE, TRUNCATE for the app role
        DB::statement("
            CREATE OR REPLACE FUNCTION prevent_audit_mutation() RETURNS trigger AS \$\$
            BEGIN
                RAISE EXCEPTION 'audit_logs is append-only: % operations are forbidden', TG_OP;
                RETURN NULL;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER audit_logs_immutable
            BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION prevent_audit_mutation();
        ");

        // Row-level triggers do NOT fire for TRUNCATE — Postgres skips
        // them entirely. Without an explicit guard, a malicious or buggy
        // code path could wipe the entire append-only audit history with
        // a single statement and leave no trace. Defence in depth here
        // is mandatory:
        //
        //   1. STATEMENT-level BEFORE TRUNCATE trigger that re-uses the
        //      same prevent_audit_mutation() function so any TRUNCATE on
        //      audit_logs raises immediately, regardless of who runs it.
        //   2. REVOKE TRUNCATE (and other destructive statement-level
        //      privileges) from the application role. The owner of the
        //      database bypasses GRANT/REVOKE in Postgres, so the trigger
        //      above is the load-bearing protection — but the REVOKE is
        //      still required to satisfy least-privilege audits and to
        //      block any non-owner role that ever connects with the same
        //      credentials profile.
        DB::statement("
            CREATE TRIGGER audit_logs_no_truncate
            BEFORE TRUNCATE ON audit_logs
            FOR EACH STATEMENT EXECUTE FUNCTION prevent_audit_mutation();
        ");

        $appRole = config('database.connections.pgsql.username') ?: env('DB_USERNAME', 'app_user');
        // Quote-safe: identifier is sourced from config, not user input.
        $quoted = '"' . str_replace('"', '""', $appRole) . '"';
        DB::statement("REVOKE TRUNCATE, DELETE, UPDATE ON TABLE audit_logs FROM {$quoted}");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_truncate ON audit_logs');
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_immutable ON audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS prevent_audit_mutation()');
        Schema::dropIfExists('audit_logs');
    }
};

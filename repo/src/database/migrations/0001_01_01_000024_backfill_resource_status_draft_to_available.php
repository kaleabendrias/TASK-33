<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill any legacy `resources.status = 'draft'` rows to the canonical
     * initial value `'available'`.
     *
     * Pre-fix the column default was `'draft'`, which was NOT in
     * `Resource::allowedTransitions()`. Any rows created under that
     * default are stuck in a non-transitionable state — every staff
     * action against them throws ValidationException at the lifecycle
     * boundary.
     *
     * This migration:
     *   1. Updates every `status = 'draft'` row to `'available'`
     *   2. Records the count in the audit log so an operator can verify
     *      the backfill ran exactly once and on the right rows.
     */
    public function up(): void
    {
        $affected = DB::table('resources')
            ->where('status', 'draft')
            ->update(['status' => 'available']);

        // Audit trail: only write a marker row when we actually rewrote
        // data, so fresh installs (where no 'draft' rows ever existed)
        // remain a strict no-op and don't pollute test fixtures or
        // production audit dashboards with empty notices.
        if ($affected > 0) {
            try {
                DB::table('audit_logs')->insert([
                    'action'      => 'resource_status_backfill',
                    'entity_type' => 'Resource',
                    'entity_id'   => null,
                    'metadata'    => json_encode([
                        'from'      => 'draft',
                        'to'        => 'available',
                        'rows'      => $affected,
                        'reason'    => 'Lifecycle contract enforcement: draft was not a valid initial state.',
                    ]),
                    'created_at'  => now(),
                ]);
            } catch (\Throwable) {
                // Best-effort marker — never block the data fix.
            }
        }
    }

    /**
     * Down is intentionally a no-op. Reverting `'available'` rows to
     * `'draft'` would re-introduce the lifecycle defect this migration
     * exists to fix, and there is no way to distinguish backfilled rows
     * from rows that were always `'available'`.
     */
    public function down(): void
    {
        // no-op
    }
};

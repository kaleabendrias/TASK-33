<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `refunded_at` idempotency guard to the orders table.
 *
 * Without this column, two concurrent calls to POST /orders/{id}/refund
 * could both observe `status != 'refunded'`, both insert a Refund row,
 * and double-pay the customer. The new column is set inside the same
 * row-locked transaction that creates the Refund, so the duplicate
 * branch is rejected by a NULL/NOT-NULL check rather than by the
 * (eventually-consistent) status enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('cancelled_at');
            $table->index('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['refunded_at']);
            $table->dropColumn('refunded_at');
        });
    }
};

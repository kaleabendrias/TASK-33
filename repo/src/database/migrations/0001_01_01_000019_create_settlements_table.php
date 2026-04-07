<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->decimal('original_amount', 12, 2);
            $table->decimal('cancellation_fee', 12, 2)->default(0);
            $table->decimal('refund_amount', 12, 2);
            $table->string('reason', 500)->nullable();
            $table->boolean('is_full_refund')->default(false);
            $table->boolean('staff_unavailable_override')->default(false);
            $table->enum('status', ['pending', 'approved', 'processed', 'disputed'])->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('status');
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('refund_total', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->integer('order_count')->default(0);
            $table->integer('refund_count')->default(0);
            $table->enum('status', ['draft', 'reconciled', 'finalized', 'disputed'])->default('draft');
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['period_start', 'period_end']);
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_leader_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained('settlements')->nullOnDelete();
            $table->date('cycle_start');
            $table->date('cycle_end');
            $table->enum('cycle_type', ['weekly', 'biweekly'])->default('weekly');
            $table->decimal('attributed_revenue', 14, 2)->default(0);
            $table->decimal('commission_rate', 5, 4)->default(0.1000);    // 10% default
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->integer('order_count')->default(0);
            $table->enum('status', ['pending', 'held', 'approved', 'paid'])->default('pending');
            $table->timestamp('hold_until')->nullable();                    // 3 business-day dispute hold
            $table->timestamps();

            $table->index(['group_leader_id', 'cycle_start']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('refunds');
    }
};

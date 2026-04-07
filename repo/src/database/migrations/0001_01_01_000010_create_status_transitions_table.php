<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracks every lifecycle status change for any entity
        Schema::create('status_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('transitionable_type', 100);       // polymorphic
            $table->unsignedBigInteger('transitionable_id');
            $table->string('from_status', 50)->nullable();     // null = initial state
            $table->string('to_status', 50);
            $table->text('reason')->nullable();
            $table->foreignId('transitioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['transitionable_type', 'transitionable_id'], 'st_poly_idx');
            $table->index('to_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_transitions');
    }
};

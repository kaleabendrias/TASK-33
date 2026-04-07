<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            // Hierarchical self-reference
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained('resources')
                ->nullOnDelete();

            // Status lifecycle.
            //
            // The default MUST be a valid initial state in the model's
            // allowedTransitions() map, otherwise newly-created rows can
            // never legally transition out and the lifecycle contract is
            // immediately violated. The canonical initial value is 'available'.
            $table->string('status', 50)
                ->default('available')
                ->after('is_available');

            $table->index('parent_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'status']);
        });
    }
};

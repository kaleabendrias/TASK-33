<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Add member_tier to users (drives tier-based pricing)
        Schema::table('users', function (Blueprint $table) {
            $table->enum('member_tier', ['standard', 'silver', 'gold', 'platinum'])
                ->default('standard')
                ->after('role');
            $table->index('member_tier');
        });

        // 2) Pricing rules — multi-dimensional rule table.
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->foreignId('bookable_item_id')->nullable()->constrained()->cascadeOnDelete();

            // Rule dimensions — any may be NULL meaning "any"
            // Time slot
            $table->time('time_slot_start')->nullable();
            $table->time('time_slot_end')->nullable();
            // Day-of-week mask: stored as comma-separated 1..7 (Mon..Sun) or NULL = any
            $table->string('days_of_week', 20)->nullable();

            // Headcount range
            $table->integer('min_headcount')->nullable();
            $table->integer('max_headcount')->nullable();

            // Member tier
            $table->enum('member_tier', ['standard', 'silver', 'gold', 'platinum'])->nullable();

            // Package
            $table->string('package_code', 50)->nullable();

            // Effective window
            $table->date('effective_from');
            $table->date('effective_until')->nullable();

            // Pricing payload
            $table->enum('adjustment_type', ['fixed_price', 'multiplier', 'discount_pct'])->default('multiplier');
            $table->decimal('adjustment_value', 10, 4);

            // Determinism: lower priority wins ties; specificity computed at resolution time.
            $table->integer('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['bookable_item_id', 'is_active']);
            $table->index(['effective_from', 'effective_until']);
            $table->index('priority');
        });

        // 3) Pricing packages (named bundles e.g. 'STANDARD', 'PEAK', 'SCHOOL_DAY')
        Schema::create('pricing_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_packages');
        Schema::dropIfExists('pricing_rules');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['member_tier']);
            $table->dropColumn('member_tier');
        });
    }
};

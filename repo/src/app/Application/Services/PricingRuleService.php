<?php

namespace App\Application\Services;

use App\Domain\Contracts\AuditLogRepositoryInterface;
use App\Domain\Models\PricingRule;
use Illuminate\Validation\ValidationException;

/**
 * Application service for the multi-dimensional pricing rule catalog.
 *
 * Centralises validation, persistence, and audit logging so that both the
 * REST API and the Livewire admin UI go through the same code path.
 */
class PricingRuleService
{
    private const ALLOWED_TIERS = ['standard', 'silver', 'gold', 'platinum'];
    private const ALLOWED_TYPES = ['fixed_price', 'multiplier', 'discount_pct'];

    public function __construct(
        private readonly AuditLogRepositoryInterface $audit,
    ) {}

    /** List rules with optional filtering. */
    public function list(array $filters = [])
    {
        $q = PricingRule::query();
        if (!empty($filters['bookable_item_id'])) $q->where('bookable_item_id', $filters['bookable_item_id']);
        if (!empty($filters['member_tier']))      $q->where('member_tier', $filters['member_tier']);
        if (!empty($filters['package_code']))     $q->where('package_code', $filters['package_code']);
        if (isset($filters['is_active']))         $q->where('is_active', (bool) $filters['is_active']);
        return $q->orderBy('priority', 'asc')->orderByDesc('id')->paginate($filters['per_page'] ?? 25);
    }

    public function find(int $id): PricingRule
    {
        return PricingRule::findOrFail($id);
    }

    public function create(array $data): PricingRule
    {
        $clean = $this->validate($data);
        $rule = PricingRule::create($clean);
        $this->audit->log('pricing_rule_created', 'PricingRule', $rule->id, null, $clean);
        return $rule;
    }

    public function update(int $id, array $data): PricingRule
    {
        $rule = $this->find($id);
        // For partial updates, compose the EFFECTIVE state by merging the
        // existing row with the patch so cross-field validation (e.g. an
        // adjustment_value bound by adjustment_type) can run against the
        // values that will actually exist after the write.
        $effective = array_merge($rule->only([
            'name', 'bookable_item_id',
            'time_slot_start', 'time_slot_end',
            'days_of_week',
            'min_headcount', 'max_headcount',
            'member_tier', 'package_code',
            'effective_from', 'effective_until',
            'adjustment_type', 'adjustment_value',
            'priority', 'is_active',
        ]), $data);
        $clean = $this->validate($effective, partial: false);
        // Persist only the keys the caller actually provided (preserves intent).
        $patch = array_intersect_key($clean, $data);
        $rule->update($patch);
        $this->audit->log('pricing_rule_updated', 'PricingRule', $rule->id, null, $patch);
        return $rule->refresh();
    }

    public function delete(int $id): void
    {
        $rule = $this->find($id);
        $rule->delete();
        $this->audit->log('pricing_rule_deleted', 'PricingRule', $id);
    }

    /**
     * Validate + normalise rule input. Throws ValidationException with field errors.
     *
     * Rules:
     *  - name required
     *  - adjustment_type ∈ {fixed_price, multiplier, discount_pct}
     *  - adjustment_value: > 0 (for fixed_price/multiplier), 0..100 (for discount_pct)
     *  - effective_from required, ISO date
     *  - effective_until optional but must be >= effective_from
     *  - priority integer >= 0
     *  - member_tier (if set) ∈ ALLOWED_TIERS
     *  - days_of_week (if set) is comma-separated 1..7
     *  - time_slot_start < time_slot_end (if both set)
     *  - min_headcount <= max_headcount (if both set)
     */
    private function validate(array $data, bool $partial = false): array
    {
        $errors = [];

        if (!$partial || array_key_exists('name', $data)) {
            if (empty($data['name'])) $errors['name'] = 'Name is required.';
        }

        if (!$partial || array_key_exists('adjustment_type', $data)) {
            $type = $data['adjustment_type'] ?? null;
            if (!in_array($type, self::ALLOWED_TYPES, true)) {
                $errors['adjustment_type'] = 'Adjustment type must be one of: ' . implode(', ', self::ALLOWED_TYPES);
            }
        }

        if (!$partial || array_key_exists('adjustment_value', $data)) {
            $val = $data['adjustment_value'] ?? null;
            if (!is_numeric($val) || (float) $val < 0) {
                $errors['adjustment_value'] = 'Adjustment value must be a non-negative number.';
            } elseif (($data['adjustment_type'] ?? null) === 'discount_pct' && (float) $val > 100) {
                $errors['adjustment_value'] = 'Discount percent must be between 0 and 100.';
            }
        }

        if (!$partial || array_key_exists('effective_from', $data)) {
            if (empty($data['effective_from']) || strtotime((string) $data['effective_from']) === false) {
                $errors['effective_from'] = 'Effective from date is required (YYYY-MM-DD).';
            }
        }

        if (!empty($data['effective_until'])) {
            if (strtotime((string) $data['effective_until']) === false) {
                $errors['effective_until'] = 'Effective until must be a valid date.';
            } elseif (!empty($data['effective_from']) && $data['effective_until'] < $data['effective_from']) {
                $errors['effective_until'] = 'Effective until must be on or after effective from.';
            }
        }

        if (isset($data['priority']) && (!is_numeric($data['priority']) || (int) $data['priority'] < 0)) {
            $errors['priority'] = 'Priority must be a non-negative integer.';
        }

        if (!empty($data['member_tier']) && !in_array($data['member_tier'], self::ALLOWED_TIERS, true)) {
            $errors['member_tier'] = 'Member tier must be one of: ' . implode(', ', self::ALLOWED_TIERS);
        }

        if (!empty($data['days_of_week'])) {
            $parts = array_map('trim', explode(',', (string) $data['days_of_week']));
            foreach ($parts as $p) {
                if (!ctype_digit($p) || (int) $p < 1 || (int) $p > 7) {
                    $errors['days_of_week'] = 'days_of_week must be comma-separated integers 1..7.';
                    break;
                }
            }
        }

        if (!empty($data['time_slot_start']) && !empty($data['time_slot_end'])) {
            if (strtotime((string) $data['time_slot_end']) <= strtotime((string) $data['time_slot_start'])) {
                $errors['time_slot_end'] = 'time_slot_end must be after time_slot_start.';
            }
        }

        if (isset($data['min_headcount'], $data['max_headcount'])
            && $data['min_headcount'] !== null && $data['max_headcount'] !== null
            && (int) $data['min_headcount'] > (int) $data['max_headcount']) {
            $errors['max_headcount'] = 'max_headcount must be >= min_headcount.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        // Whitelist the columns we persist.
        return array_intersect_key($data, array_flip([
            'name', 'bookable_item_id',
            'time_slot_start', 'time_slot_end',
            'days_of_week',
            'min_headcount', 'max_headcount',
            'member_tier', 'package_code',
            'effective_from', 'effective_until',
            'adjustment_type', 'adjustment_value',
            'priority', 'is_active',
        ]));
    }
}

<?php

namespace App\Livewire\Pricing;

use App\Application\Services\PricingRuleService;
use App\Livewire\Concerns\UsesApiClient;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Pricing Rules')]
class PricingRuleManager extends Component
{
    use WithPagination, UsesApiClient;

    // Form state
    public ?int $editingId = null;
    public string $name = '';
    public ?int $bookable_item_id = null;
    public ?string $time_slot_start = null;
    public ?string $time_slot_end = null;
    public ?string $days_of_week = null;
    public ?int $min_headcount = null;
    public ?int $max_headcount = null;
    public ?string $member_tier = null;
    public ?string $package_code = null;
    public string $effective_from = '';
    public ?string $effective_until = null;
    public string $adjustment_type = 'multiplier';
    public string $adjustment_value = '1.00';
    public int $priority = 100;
    public bool $is_active = true;

    public string $message = '';
    public array $errors_bag = [];

    public function mount(): void
    {
        $user = auth()->user() ?? request()->attributes->get('auth_user');
        if (!$user || !$user->isAdmin()) abort(403);
        $this->effective_from = now()->toDateString();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->bookable_item_id = null;
        $this->time_slot_start = null;
        $this->time_slot_end = null;
        $this->days_of_week = null;
        $this->min_headcount = null;
        $this->max_headcount = null;
        $this->member_tier = null;
        $this->package_code = null;
        $this->effective_from = now()->toDateString();
        $this->effective_until = null;
        $this->adjustment_type = 'multiplier';
        $this->adjustment_value = '1.00';
        $this->priority = 100;
        $this->is_active = true;
        $this->errors_bag = [];
    }

    public function loadForEdit(int $id, PricingRuleService $svc): void
    {
        $rule = $svc->find($id);
        $this->editingId = $rule->id;
        $this->name = $rule->name;
        $this->bookable_item_id = $rule->bookable_item_id;
        $this->time_slot_start = $rule->time_slot_start;
        $this->time_slot_end = $rule->time_slot_end;
        $this->days_of_week = $rule->days_of_week;
        $this->min_headcount = $rule->min_headcount;
        $this->max_headcount = $rule->max_headcount;
        $this->member_tier = $rule->member_tier;
        $this->package_code = $rule->package_code;
        $this->effective_from = $rule->effective_from?->toDateString() ?? now()->toDateString();
        $this->effective_until = $rule->effective_until?->toDateString();
        $this->adjustment_type = $rule->adjustment_type;
        $this->adjustment_value = (string) $rule->adjustment_value;
        $this->priority = $rule->priority;
        $this->is_active = $rule->is_active;
    }

    public function save(): void
    {
        $payload = $this->payload();
        $resp = $this->editingId
            ? $this->api()->put("/admin/pricing-rules/{$this->editingId}", $payload)
            : $this->api()->post('/admin/pricing-rules', $payload);

        if ($resp->failed()) {
            $this->errors_bag = $resp->json('errors') ?? [];
            $this->message = $resp->json('message') ?? 'Save failed.';
            return;
        }

        $this->message = $resp->json('message') ?? 'Saved.';
        $this->errors_bag = [];
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $resp = $this->api()->delete("/admin/pricing-rules/{$id}");
        $this->message = $resp->successful() ? 'Rule deleted.' : ($resp->json('message') ?? 'Delete failed.');
    }

    private function payload(): array
    {
        return [
            'name'             => $this->name,
            'bookable_item_id' => $this->bookable_item_id,
            'time_slot_start'  => $this->time_slot_start,
            'time_slot_end'    => $this->time_slot_end,
            'days_of_week'     => $this->days_of_week,
            'min_headcount'    => $this->min_headcount,
            'max_headcount'    => $this->max_headcount,
            'member_tier'      => $this->member_tier,
            'package_code'     => $this->package_code,
            'effective_from'   => $this->effective_from,
            'effective_until'  => $this->effective_until,
            'adjustment_type'  => $this->adjustment_type,
            'adjustment_value' => $this->adjustment_value,
            'priority'         => $this->priority,
            'is_active'        => $this->is_active,
        ];
    }

    public function render(PricingRuleService $svc)
    {
        $user = auth()->user() ?? request()->attributes->get('auth_user');
        if (!$user || !$user->isAdmin()) abort(403);

        // Read through the application service to keep the architectural boundary.
        $rules = $svc->list(['per_page' => 25]);
        return view('livewire.pricing.pricing-rule-manager', ['rules' => $rules]);
    }
}

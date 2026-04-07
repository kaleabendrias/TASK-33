<?php

namespace App\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Whitelisted JSON shape for Order responses.
 *
 * Only safe, non-PII fields cross the network boundary. Sensitive joined
 * data (raw user records, encrypted columns, hash indexes) is filtered out.
 * Nested relationships are emitted only as scalar IDs unless explicitly
 * loaded by the controller.
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'order_number'        => $this->order_number,
            'user_id'             => $this->user_id,
            'group_leader_id'     => $this->group_leader_id,
            'service_area_id'     => $this->service_area_id,
            'status'              => $this->status,
            'subtotal'            => (float) $this->subtotal,
            'tax_amount'          => (float) $this->tax_amount,
            'discount_amount'     => (float) $this->discount_amount,
            'total'               => (float) $this->total,
            'coupon_id'           => $this->coupon_id,
            'confirmed_at'        => $this->confirmed_at?->toIso8601String(),
            'checked_in_at'       => $this->checked_in_at?->toIso8601String(),
            'checked_out_at'      => $this->checked_out_at?->toIso8601String(),
            'cancelled_at'        => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'staff_marked_unavailable' => (bool) $this->staff_marked_unavailable,
            'notes'               => $this->notes,
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
            // Line items: emit only the safe fields, never raw bookable item joins.
            'line_items' => $this->whenLoaded('lineItems', fn () => $this->lineItems->map(fn ($li) => [
                'id'              => $li->id,
                'bookable_item_id'=> $li->bookable_item_id,
                'item_name'       => $li->item_name,
                'booking_date'    => $li->booking_date?->toDateString() ?? $li->booking_date,
                'start_time'      => $li->start_time,
                'end_time'        => $li->end_time,
                'quantity'        => $li->quantity,
                'unit_price'      => (float) $li->unit_price,
                'tax_rate'        => (float) $li->tax_rate,
                'line_subtotal'   => (float) $li->line_subtotal,
                'line_tax'        => (float) $li->line_tax,
                'line_total'      => (float) $li->line_total,
            ])),
            'refunds' => $this->whenLoaded('refunds', fn () => $this->refunds->map(fn ($r) => [
                'id'              => $r->id,
                'original_amount' => (float) $r->original_amount,
                'cancellation_fee'=> (float) $r->cancellation_fee,
                'refund_amount'   => (float) $r->refund_amount,
                'is_full_refund'  => (bool) $r->is_full_refund,
                'status'          => $r->status,
                'processed_at'    => $r->processed_at?->toIso8601String(),
            ])),
        ];
    }
}

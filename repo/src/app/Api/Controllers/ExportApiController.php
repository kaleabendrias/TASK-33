<?php

namespace App\Api\Controllers;

use App\Application\Services\SettlementService;
use App\Domain\Models\Commission;
use App\Domain\Models\Order;
use App\Domain\Models\Settlement;
use App\Infrastructure\Export\CsvExporter;
use App\Infrastructure\Export\PdfExporter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class ExportApiController extends Controller
{
    public function __construct(private readonly SettlementService $settlements) {}

    public function export(Request $request): Response
    {
        $request->validate([
            'type' => 'required|in:orders,settlements,commissions',
            'format' => 'required|in:csv,pdf',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $user = $request->attributes->get('auth_user');
        $type = $request->input('type');
        $from = $request->input('date_from');
        $to = $request->input('date_to');

        // Export pipeline failures (filesystem, streamer, PDF renderer)
        // bubble up to the global exception handler in bootstrap/app.php
        // which logs them through the 'errors' channel with full
        // stack-trace context. No per-controller catch is required.
        [$headers, $rows] = match ($type) {
            'orders' => $this->ordersData($user, $from, $to),
            'settlements' => $this->settlementsData($user, $from, $to),
            'commissions' => $this->commissionsData($user, $from, $to),
        };

        $filename = "{$type}_{$from}_{$to}";

        if ($request->input('format') === 'pdf') {
            return PdfExporter::export("{$filename}.pdf", ucfirst($type) . ' Report', $headers, $rows);
        }

        return CsvExporter::export("{$filename}.csv", $headers, $rows);
    }

    private function ordersData($user, string $from, string $to): array
    {
        $headers = ['Order #', 'Date', 'Status', 'Subtotal', 'Tax', 'Discount', 'Total', 'User'];
        $orders = Order::with('user')
            ->when(!$user->isAdmin(), fn ($q) => $q->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('group_leader_id', $user->id);
            }))
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->orderByDesc('created_at')->get();

        $rows = $orders->map(fn ($o) => [
            $o->order_number, $o->created_at->toDateString(), $o->status,
            $o->subtotal, $o->tax_amount, $o->discount_amount, $o->total,
            $o->user->full_name ?? '',
        ])->toArray();

        return [$headers, $rows];
    }

    private function settlementsData($user, string $from, string $to): array
    {
        $headers = ['Reference', 'Period Start', 'Period End', 'Gross', 'Refunds', 'Net', 'Status', 'Cycle Type'];

        // Use the SAME service method that powers the API and the Livewire UI.
        // This guarantees the exported dataset matches what the user actually
        // sees on screen and includes the staff scoping rule (orders they
        // personally placed within the settlement period).
        $rows = $this->settlements->listSettlementsForExport($user, $from, $to)
            ->map(fn (Settlement $s) => [
                $s->reference,
                $s->period_start?->toDateString(),
                $s->period_end?->toDateString(),
                $s->gross_amount,
                $s->refund_total,
                $s->net_amount,
                $s->status,
                $s->cycle_type,
            ])
            ->toArray();

        return [$headers, $rows];
    }

    private function commissionsData($user, string $from, string $to): array
    {
        $headers = ['Leader', 'Cycle Start', 'Cycle End', 'Type', 'Revenue', 'Rate', 'Commission', 'Status'];

        // Same centralisation: commissions are filtered through the service so
        // the staff "settlements I touched" rule applies here too.
        $rows = $this->settlements->listCommissionsForUser($user, $from, $to)
            ->map(fn (Commission $c) => [
                $c->groupLeader->full_name ?? '',
                $c->cycle_start?->toDateString(),
                $c->cycle_end?->toDateString(),
                $c->cycle_type,
                $c->attributed_revenue,
                ($c->commission_rate * 100) . '%',
                $c->commission_amount,
                $c->status,
            ])
            ->toArray();

        return [$headers, $rows];
    }
}

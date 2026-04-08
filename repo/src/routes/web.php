<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard\DashboardPage;
use App\Livewire\Booking\BookingIndex;
use App\Livewire\Booking\BookingCreate;
use App\Livewire\Orders\OrderIndex;
use App\Livewire\Orders\OrderShow;
use App\Livewire\Settlement\SettlementIndex;
use App\Livewire\Settlement\CommissionReport;
use App\Livewire\Profile\StaffProfilePage;
use App\Livewire\Export\ExportPage;
use App\Livewire\Pricing\PricingRuleManager;

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/
Route::get('/login', Login::class)->name('login');

Route::post('/logout', function () {
    $token = session('jwt_token');
    $userId = session('auth_user_id');

    if ($token) {
        // JWT payloads are URL-safe base64 ("base64url") — they swap '+' / '/'
        // for '-' / '_' and drop padding. Calling plain base64_decode() on a
        // URL-safe payload silently produces garbage for some valid tokens,
        // which is why the previous implementation hid the failure inside
        // an empty catch block. The decoder below is RFC 4648 §5 correct.
        $jti = null;
        try {
            $segments = explode('.', $token);
            if (count($segments) >= 2) {
                $b64 = strtr($segments[1], '-_', '+/');
                $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
                $payload = json_decode((string) base64_decode($b64, true), true);
                $jti = is_array($payload) ? ($payload['jti'] ?? null) : null;
            }
        } catch (\Throwable $e) {
            Log::channel('security')->warning('logout.jwt_decode_failed', [
                'user_id' => $userId,
                'reason'  => $e->getMessage(),
            ]);
        }

        if ($jti) {
            try {
                app(\App\Infrastructure\Auth\JwtService::class)->revokeByJti($jti, 'user');
            } catch (\Throwable $e) {
                // Don't swallow silently — log with enough context to chase
                // down a stuck session in production. We still flush the
                // session so the user perceives a successful logout, but the
                // operations team can audit and force-revoke later.
                Log::channel('errors')->error('logout.jwt_revoke_failed', [
                    'user_id' => $userId,
                    'jti'     => $jti,
                    'reason'  => $e->getMessage(),
                    'class'   => $e::class,
                ]);
            }
        } else {
            Log::channel('security')->warning('logout.jwt_jti_missing', [
                'user_id' => $userId,
                'token_segments' => isset($segments) ? count($segments) : 0,
            ]);
        }
    }

    session()->flush();
    return redirect('/login');
})->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated routes (web session wrapping JWT)
|--------------------------------------------------------------------------
*/
Route::middleware('web.auth')->group(function () {
    Route::get('/', fn () => redirect('/dashboard'));
    Route::get('/dashboard', DashboardPage::class)->name('dashboard');
    Route::get('/profile', StaffProfilePage::class)->name('profile');

    // ── Any authenticated user can browse and book ──────────────────
    Route::get('/bookings', BookingIndex::class)->name('bookings.index');
    Route::get('/bookings/create', BookingCreate::class)->name('bookings.create');
    Route::get('/orders', OrderIndex::class)->name('orders.index');
    Route::get('/orders/{orderId}', OrderShow::class)->name('orders.show');
    Route::get('/exports', ExportPage::class)->name('exports');

    // ── Settlement summaries: staff+ (read-only, role gate only) ────
    // Profile-complete is intentionally NOT applied here — viewing one's
    // own financial summary is a read-only action and must work even when
    // a staff member's onboarding is unfinished. Row-level scoping inside
    // SettlementService keeps cross-tenant data isolated.
    Route::middleware('role:staff')->group(function () {
        Route::get('/settlements', SettlementIndex::class)->name('settlements.index');
    });

    // Commissions remain group-leader+ since regular staff don't earn them.
    Route::middleware('role:group-leader')->group(function () {
        Route::get('/commissions', CommissionReport::class)->name('commissions.index');
    });

    // ── Admin-only routes ──────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/pricing-rules', PricingRuleManager::class)->name('admin.pricing-rules');
    });
});

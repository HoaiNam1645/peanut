<?php

namespace App\Console\Commands;

use App\Enums\OrderFulfillStatus as F;
use App\Enums\OrderPaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\BuyLabelService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BuyLabelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:buy-label
                            {--limit=50 : Max orders to process per branch per run}
                            {--mode=all : Which branch to run: all | buy | forward}
                            {--min-age=60 : (buy only) minimum order age in minutes before auto-buy}
                            {--max-age-days=7 : (buy only) ignore orders older than this many days}
                            {--dry-run : List eligible orders without sending anything to ShipDVX}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto create-shipping via ShipDVX (luồng như mua thuần): FORWARD shipped HAS_LABEL orders (tạo vận chuyển, miễn phí) + BUY labels for eligible NO_LABEL orders (mua label, tốn tiền).';

    /**
     * Execute the console command.
     */
    public function handle(BuyLabelService $buyLabelService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $mode = strtolower((string) $this->option('mode'));
        $minAge = max(0, (int) $this->option('min-age'));
        $maxAgeDays = max(1, (int) $this->option('max-age-days'));
        $dry = (bool) $this->option('dry-run');

        if (! in_array($mode, ['all', 'buy', 'forward'], true)) {
            $this->error("Invalid --mode={$mode} (use all|buy|forward)");

            return self::FAILURE;
        }

        $this->info('=== Auto Buy-Label / Create-Shipping (ShipDVX) ===');
        $this->line("mode={$mode} limit={$limit} min-age={$minAge}m max-age={$maxAgeDays}d dry-run=".($dry ? 'yes' : 'no'));

        // Run as an admin user so BuyLabelService doesn't scope to a single seller.
        $admin = User::whereHas('role', fn ($q) => $q->whereRaw('LOWER(name) = ?', ['admin']))->first();
        if (! $admin) {
            $this->error('No admin user found — cannot run auto buy-label.');
            Log::error('Auto buy-label: no admin user found');

            return self::FAILURE;
        }

        // Build the two disjoint candidate sets.
        $candidates = [];
        if ($mode === 'all' || $mode === 'forward') {
            foreach ($this->forwardCandidates($limit) as $id) {
                $candidates[] = ['id' => $id, 'type' => 'forward'];
            }
        }
        if ($mode === 'all' || $mode === 'buy') {
            foreach ($this->buyCandidates($limit, $minAge, $maxAgeDays) as $id) {
                $candidates[] = ['id' => $id, 'type' => 'buy'];
            }
        }

        $forwardCount = count(array_filter($candidates, fn ($c) => $c['type'] === 'forward'));
        $buyCount = count($candidates) - $forwardCount;
        $this->info("Eligible — forward (tạo VC, shipped): {$forwardCount} | buy (mua label, tốn tiền): {$buyCount}");

        if (empty($candidates)) {
            $this->info('No eligible orders.');
            Log::info('Auto buy-label: no eligible orders', ['mode' => $mode]);

            return self::SUCCESS;
        }

        if ($dry) {
            foreach ($candidates as $c) {
                $order = Order::find($c['id']);
                $this->line(sprintf('  [%s] #%d  ref=%s  fulfill=%s', $c['type'], $c['id'], $order?->ref_id ?? '?', $order?->fulfill_status ?? '?'));
            }
            $this->info('Dry-run: '.count($candidates).' order(s) would be processed (nothing sent).');

            return self::SUCCESS;
        }

        // Process per-order — identical to a manual single "Mua label / Tạo vận chuyển"
        // click. Per-order (not batch) so one bad order can't fail the others, and a
        // failure leaves label_status NULL so it's retried next run (no double charge:
        // success flips label_status to PENDING, excluding it from later runs).
        $ok = 0;
        $fail = 0;
        foreach ($candidates as $c) {
            try {
                $res = $buyLabelService->buyLabelViaShipDvx([$c['id']], $admin);
                if (($res['status'] ?? false) === true) {
                    $ok++;
                    $this->info("  ✓ [{$c['type']}] #{$c['id']}");
                } else {
                    $fail++;
                    $this->warn("  ✗ [{$c['type']}] #{$c['id']}: ".($res['message'] ?? 'unknown'));
                    Log::warning('Auto buy-label: order not dispatched', [
                        'order_id' => $c['id'],
                        'type' => $c['type'],
                        'message' => $res['message'] ?? null,
                        'ineligible' => $res['data']['ineligible'] ?? null,
                    ]);
                }
            } catch (\Throwable $e) {
                $fail++;
                $this->error("  ✗ [{$c['type']}] #{$c['id']}: ".$e->getMessage());
                Log::error('Auto buy-label: order errored', [
                    'order_id' => $c['id'],
                    'type' => $c['type'],
                    'error' => $e->getMessage(),
                ]);
            }
            // Gentle pacing — ShipDVX rate-limits rapid calls.
            usleep(400000);
        }

        $this->info("Done. ok={$ok} fail={$fail}");
        Log::info('Auto buy-label run complete', [
            'mode' => $mode,
            'forward' => $forwardCount,
            'buy' => $buyCount,
            'ok' => $ok,
            'fail' => $fail,
        ]);

        return self::SUCCESS;
    }

    /**
     * FORWARD set — orders that already carry a label + tracking (TikTok/HAS_LABEL)
     * and are SHIPPED. ShipDVX forwards the existing label (no charge). Only shipped
     * orders, per the requirement. label_status NULL = not yet sent (avoids re-sending
     * PENDING/GENERATED/ERROR → no ORDER_NUMBER_ALREADY_EXISTS).
     *
     * @return int[]
     */
    private function forwardCandidates(int $limit): array
    {
        return Order::query()
            ->where('fulfill_status', F::SHIPPED)
            ->whereNotNull('tracking_id')->where('tracking_id', '!=', '')
            ->whereNotNull('shipping_label')->where('shipping_label', '!=', '')
            ->where(fn ($q) => $q->whereNull('label_status')->orWhere('label_status', ''))
            ->where('ref_id', 'not like', 'TEST%') // skip test orders (ref TEST-*)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();
    }

    /**
     * BUY set — eligible NO_LABEL orders (seller-ship) that need a real label bought
     * (costs money). Mirrors the long-standing eligibility: seller has production,
     * order is PAID, not in a terminal/hold/shipped state, no existing label/tracking,
     * has an address, aged into the [min-age, max-age] window, and never sent
     * (label_status NULL).
     *
     * @return int[]
     */
    private function buyCandidates(int $limit, int $minAge, int $maxAgeDays): array
    {
        return Order::query()
            ->join('users', 'users.id', '=', 'orders.seller_id')
            ->join('user_profiles', 'user_profiles.user_id', '=', 'users.id')
            ->where('user_profiles.production', 1)
            ->where('orders.payment_status', OrderPaymentStatus::PAID)
            ->whereNotIn('orders.fulfill_status', [
                F::ON_HOLD,
                F::SHIPPED,
                F::RETURN_TO_SUPPORT,
                F::CANCELLED,
                F::CANCELLED_REFUND_SHIPPING,
                F::CLOSED,
                F::TEST_ORDER,
            ])
            ->where(fn ($q) => $q->whereNull('orders.shipping_label')->orWhere('orders.shipping_label', ''))
            ->where(fn ($q) => $q->whereNull('orders.tracking_id')->orWhere('orders.tracking_id', ''))
            ->where('orders.address_1', '!=', '')
            ->where(fn ($q) => $q->whereNull('orders.label_status')->orWhere('orders.label_status', ''))
            ->where('orders.ref_id', 'not like', 'TEST%') // skip test orders (ref TEST-*)
            ->whereBetween('orders.created_at', [
                Carbon::now()->subDays($maxAgeDays),
                Carbon::now()->subMinutes($minAge),
            ])
            ->orderBy('orders.id')
            ->limit($limit)
            ->pluck('orders.id')
            ->all();
    }
}

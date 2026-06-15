<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderIssue;
use App\Services\OrderValidationService;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ValidateOrdersCommand extends Command
{
    protected $signature = 'orders:validate
                            {--batch=10 : Số đơn xử lý mỗi lần}
                            {--min-age=10 : Đơn phải tạo cách đây ít nhất bao nhiêu phút}
                            {--max-age-days=7 : Chỉ check đơn trong N ngày gần đây}
                            {--dry-run : Không gửi Telegram, chỉ log}';

    protected $description = 'Quét batch đơn hàng, phát hiện issue (thiếu items/metas/qr/address...) và gửi Telegram alert';

    public function handle(OrderValidationService $validator, TelegramNotifier $notifier): int
    {
        $batch = (int) $this->option('batch');
        $minAge = (int) $this->option('min-age');
        $maxAgeDays = (int) $this->option('max-age-days');
        $dryRun = (bool) $this->option('dry-run');

        // Lấy danh sách order_id đã có issue open → loại ra khỏi batch
        $excludedOrderIds = OrderIssue::open()->pluck('order_id')->unique()->all();

        $orders = Order::query()
            ->where('created_at', '<=', now()->subMinutes($minAge))
            ->where('created_at', '>=', now()->subDays($maxAgeDays))
            ->whereNotIn('fulfill_status', ['cancelled', 'delivered'])
            ->when(!empty($excludedOrderIds), fn($q) => $q->whereNotIn('id', $excludedOrderIds))
            ->orderBy('created_at', 'asc')
            ->limit($batch)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No orders to validate.');
            return self::SUCCESS;
        }

        $this->info("Validating {$orders->count()} orders...");

        $alertedCount = 0;
        $cleanCount = 0;

        foreach ($orders as $order) {
            $issues = $validator->checkOrder($order);

            if (empty($issues)) {
                $cleanCount++;
                continue;
            }

            // Severity của issue = severity cao nhất trong list
            $severity = collect($issues)->contains(fn($i) => ($i['severity'] ?? '') === OrderIssue::SEVERITY_CRITICAL)
                ? OrderIssue::SEVERITY_CRITICAL
                : OrderIssue::SEVERITY_WARN;

            $this->warn("Order #{$order->id}: " . count($issues) . " issues, severity={$severity}");

            if ($dryRun) {
                $this->line(json_encode($issues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                continue;
            }

            $issueRecord = OrderIssue::create([
                'order_id' => $order->id,
                'status' => OrderIssue::STATUS_OPEN,
                'severity' => $severity,
                'info_error' => $issues,
            ]);

            try {
                $sent = $notifier->sendOrderIssue($order, $issueRecord);
                if ($sent) {
                    $alertedCount++;
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send order issue alert', [
                    'order_id' => $order->id,
                    'issue_id' => $issueRecord->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Clean: {$cleanCount}, Alerted: {$alertedCount}, Skipped (already in issue): " . count($excludedOrderIds));
        return self::SUCCESS;
    }
}

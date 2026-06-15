<?php

namespace App\Console\Commands;

use App\Constants\OrderStatus;
use App\Constants\OrderItemStatus;
use App\Jobs\PayOrderJob;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessOrderCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'orders:process
                            {ids?* : Danh sách Order ID cần xử lý (cách nhau bằng khoảng trắng)}
                            {--all : Xử lý toàn bộ đơn hàng đủ điều kiện}
                            {--action=stock : Hành động: stock, pay, hoặc both}
                            {--dry-run : Chạy thử không thay đổi dữ liệu}
                            {--force : Bỏ qua kiểm tra điều kiện}
                            {--ignore-time : Bỏ qua điều kiện thời gian 40 phút}';

    /**
     * The console command description.
     */
    protected $description = 'Xử lý các đơn hàng cụ thể hoặc toàn bộ để phân bổ tồn kho hoặc thanh toán';

    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        parent::__construct();
        $this->orderService = $orderService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $orderIds = $this->argument('ids');
        $action = $this->option('action');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $processAll = $this->option('all');

        // Validate: either provide IDs or use --all
        if (empty($orderIds) && !$processAll) {
            $this->error('Vui lòng cung cấp Order IDs hoặc sử dụng --all để xử lý toàn bộ');
            return 1;
        }

        $this->info('═══════════════════════════════════════════');
        $this->info('         CÔNG CỤ XỬ LÝ ĐƠN HÀNG');
        $this->info('═══════════════════════════════════════════');
        $this->newLine();

        if ($processAll) {
            $this->info('📋 Phạm vi: TOÀN BỘ ĐƠN HÀNG ĐỦ ĐIỀU KIỆN');
        } else {
            $this->info('📋 Mã đơn hàng: ' . implode(', ', $orderIds));
        }

        $this->info('⚙️  Hành động: ' . strtoupper($action));
        $this->info('🔍 Chế độ: ' . ($dryRun ? 'CHẠY THỬ' : 'THỰC TẾ'));
        $this->info('💪 Bỏ qua điều kiện: ' . ($force ? 'CÓ' : 'KHÔNG'));
        $this->newLine();

        // Validate action
        if (!in_array($action, ['stock', 'pay', 'both'])) {
            $this->error('Hành động không hợp lệ. Sử dụng: stock, pay, hoặc both');
            return 1;
        }

        // Fetch orders
        if ($processAll) {
            // Get all eligible orders (similar to stock:allocate)
            $query = Order::with(['items', 'seller.profile'])
                ->whereIn('fulfill_status', OrderStatus::getEligibleForAllocation())
                ->where('payment_status', OrderStatus::PAYMENT_PAID);

            // Apply time filter unless --ignore-time is set
            if (!$this->option('ignore-time')) {
                $query->where('created_at', '<=', now()->subMinutes(40));
            }

            $orders = $query->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $this->info("Tìm thấy {$orders->count()} đơn hàng đủ điều kiện");
        } else {
            $orders = Order::with(['items', 'seller.profile'])
                ->whereIn('id', $orderIds)
                ->get();

            if ($orders->isEmpty()) {
                $this->error('Không tìm thấy đơn hàng với các mã đã nhập');
                return 1;
            }

            $this->info("Tìm thấy {$orders->count()} đơn hàng");
        }

        $this->newLine();

        // Display order info (limit to 10 for --all to avoid clutter)
        if ($processAll && $orders->count() > 10) {
            $this->info("Hiển thị 10/{$orders->count()} đơn hàng đầu tiên:");
            $this->displayOrderInfo($orders->take(10));
        } else {
            $this->displayOrderInfo($orders);
        }

        $this->newLine();

        // Confirm action
        if (!$dryRun && !$this->confirm('Bạn có muốn tiếp tục?')) {
            $this->info('Đã hủy.');
            return 0;
        }

        // Process based on action
        if ($action === 'stock' || $action === 'both') {
            $this->processStock($orders, $dryRun, $force);
        }

        if ($action === 'pay' || $action === 'both') {
            $this->processPay($orders, $dryRun, $force);
        }

        $this->newLine();
        $this->info('✅ Hoàn tất xử lý!');
        return 0;
    }

    /**
     * Display order information
     */
    protected function displayOrderInfo($orders): void
    {
        $headers = ['ID', 'Mã tham chiếu', 'Trạng thái', 'Thanh toán', 'Tổng tiền', 'Sản phẩm'];

        $rows = $orders->map(function ($order) {
            return [
                $order->id,
                $order->ref_id ?? '-',
                $order->fulfill_status,
                $order->payment_status,
                '$' . number_format($order->total_cost, 2),
                $order->items->count()
            ];
        })->toArray();

        $this->table($headers, $rows);
    }

    /**
     * Process stock allocation for orders
     */
    protected function processStock($orders, bool $dryRun, bool $force): void
    {
        $this->newLine();
        $this->info('📦 PHÂN BỔ TỒN KHO');
        $this->info('───────────────────────────────────────────');

        // Create stock snapshot
        $stockSnapshot = $this->createStockSnapshot();

        // Statistics
        $stats = [
            'total' => 0,
            'enough_stock' => 0,
            'shortage' => 0,
        ];
        $shortageOrders = [];

        foreach ($orders as $order) {
            $stats['total']++;

            $this->newLine();
            $this->line("🔍 Đơn hàng #{$order->id} ({$order->ref_id})");
            $this->line("   Trạng thái hiện tại: {$order->fulfill_status}");
            $this->line("   Thanh toán: {$order->payment_status}");

            // Check eligibility
            $eligibleStatuses = OrderStatus::getEligibleForAllocation();
            $isEligible = in_array($order->fulfill_status, $eligibleStatuses)
                && $order->payment_status === OrderStatus::PAYMENT_PAID;

            if (!$isEligible && !$force) {
                $this->warn("   ⚠️  Không đủ điều kiện phân bổ tồn kho (dùng --force để bỏ qua)");
                $this->line("   Trạng thái yêu cầu: " . implode(', ', $eligibleStatuses));
                $this->line("   Thanh toán yêu cầu: " . OrderStatus::PAYMENT_PAID);
                continue;
            }

            if (!$isEligible && $force) {
                $this->warn("   ⚠️  Bỏ qua kiểm tra điều kiện (--force)");
            }

            // Check each item
            $allAllocated = true;
            $workingStock = $stockSnapshot;
            $shortageItems = [];

            foreach ($order->items as $item) {
                $variantId = $item->variant_id;
                $quantity = (int) $item->quantity;
                $currentStock = $workingStock[$variantId] ?? 0;

                if ($currentStock >= $quantity) {
                    $this->line("   ✓ Sản phẩm #{$item->id}: {$item->product_name}");
                    $this->line("     Mã biến thể: {$variantId}, SL cần: {$quantity}, Tồn kho: {$currentStock} → " . ($currentStock - $quantity));
                    $workingStock[$variantId] = $currentStock - $quantity;
                } else {
                    $shortage = $quantity - $currentStock;
                    $this->line("   ✗ Sản phẩm #{$item->id}: {$item->product_name}");
                    $this->line("     Mã biến thể: {$variantId}, SL cần: {$quantity}, Tồn kho: {$currentStock}, Thiếu: {$shortage}");
                    $allAllocated = false;

                    $shortageItems[] = [
                        'item_id' => $item->id,
                        'product_name' => $item->product_name,
                        'variant_id' => $variantId,
                        'quantity' => $quantity,
                        'stock' => $currentStock,
                        'shortage' => $shortage,
                    ];
                }
            }

            // Track statistics
            if ($allAllocated) {
                $stats['enough_stock']++;
            } else {
                $stats['shortage']++;
                $shortageOrders[] = [
                    'order_id' => $order->id,
                    'ref_id' => $order->ref_id,
                    'items' => $shortageItems,
                ];
            }

            // Determine new status
            $currentStatus = $order->fulfill_status;
            $newStatus = $allAllocated
                ? OrderStatus::getNextStatusWhenAllocated($currentStatus)
                : OrderStatus::getNextStatusWhenPending($currentStatus);

            $this->newLine();
            $this->line("   📊 Kết quả: " . ($allAllocated ? '✓ ĐỦ HÀNG' : '✗ THIẾU HÀNG'));
            $this->line("   📈 Trạng thái: {$currentStatus} → {$newStatus}");

            // Apply changes
            if (!$dryRun && $newStatus !== $currentStatus) {
                DB::transaction(function () use ($order, $newStatus, $allAllocated) {
                    $order->update(['fulfill_status' => $newStatus]);

                    $statusText = strtoupper(str_replace('_', ' ', $newStatus));
                    $reason = $allAllocated ? 'đủ hàng' : 'thiếu hàng';

                    $this->orderService->createTimeline(
                        $order,
                        'stock_allocation',
                        "Kiểm tra thủ công: cập nhật thành {$statusText} ({$reason})"
                    );
                });
                $this->info("   💾 Đã cập nhật trạng thái!");
            } elseif ($dryRun) {
                $this->comment("   [CHẠY THỬ] Không thay đổi dữ liệu");
            }
        }

        // Display summary statistics
        $this->newLine();
        $this->newLine();
        $this->info('═══════════════════════════════════════════');
        $this->info('              THỐNG KÊ KẾT QUẢ');
        $this->info('═══════════════════════════════════════════');
        $this->newLine();
        $this->line("📊 Tổng số đơn xử lý: {$stats['total']}");
        $this->line("✅ Đơn đủ hàng: {$stats['enough_stock']}");
        $this->line("⚠️  Đơn thiếu hàng: {$stats['shortage']}");

        // Display shortage orders list
        if (!empty($shortageOrders)) {
            $this->newLine();
            $this->info('───────────────────────────────────────────');
            $this->warn('        DANH SÁCH ĐƠN THIẾU HÀNG');
            $this->info('───────────────────────────────────────────');

            foreach ($shortageOrders as $shortageOrder) {
                $this->newLine();
                $this->line("🔴 Đơn #{$shortageOrder['order_id']} ({$shortageOrder['ref_id']})");

                foreach ($shortageOrder['items'] as $item) {
                    $this->line("   • {$item['product_name']}");
                    $this->line("     Variant: {$item['variant_id']} | Cần: {$item['quantity']} | Kho: {$item['stock']} | Thiếu: {$item['shortage']}");
                }
            }
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════════');
    }

    /**
     * Process payment for orders
     */
    protected function processPay($orders, bool $dryRun, bool $force): void
    {
        $this->newLine();
        $this->info('💳 XỬ LÝ THANH TOÁN');
        $this->info('───────────────────────────────────────────');

        foreach ($orders as $order) {
            $this->newLine();
            $this->line("🔍 Đơn hàng #{$order->id} ({$order->ref_id})");
            $this->line("   Trạng thái hiện tại: {$order->fulfill_status}");
            $this->line("   Thanh toán: {$order->payment_status}");
            $this->line("   Tổng tiền: $" . number_format($order->total_cost, 2));

            // Check eligibility
            $isEligible = $order->payment_status === OrderStatus::PAYMENT_PENDING
                && !in_array($order->fulfill_status, [
                    OrderStatus::CANCELLED,
                    OrderStatus::TEST_ORDER,
                    OrderStatus::ON_HOLD
                ])
                && $order->total_cost > 0;

            if (!$isEligible && !$force) {
                $this->warn("   ⚠️  Không đủ điều kiện thanh toán (dùng --force để bỏ qua)");
                continue;
            }

            if (!$isEligible && $force) {
                $this->warn("   ⚠️  Bỏ qua kiểm tra điều kiện (--force)");
            }

            // Check seller credit
            if (!$order->seller || !$order->seller->profile) {
                $this->error("   ❌ Không tìm thấy thông tin người bán");
                continue;
            }

            $walletBalance = (float) $order->seller->profile->wallet_balance;
            $maxDebit = (float) $order->seller->profile->max_debit;
            $totalCost = (float) $order->total_cost;
            $newBalance = $walletBalance - $totalCost;
            $canPay = $newBalance >= -$maxDebit;

            $this->line("   💰 Số dư ví: $" . number_format($walletBalance, 2));
            $this->line("   📊 Hạn mức nợ: $" . number_format($maxDebit, 2));
            $this->line("   💵 Chi phí: $" . number_format($totalCost, 2));
            $this->line("   📈 Số dư sau: $" . number_format($newBalance, 2));

            if (!$canPay && !$force) {
                $shortage = abs($newBalance + $maxDebit);
                $this->error("   ❌ Không đủ số dư (thiếu: $" . number_format($shortage, 2) . ")");
                continue;
            }

            if (!$canPay && $force) {
                $this->warn("   ⚠️  Bỏ qua kiểm tra số dư (--force)");
            }

            // Dispatch payment job
            if (!$dryRun) {
                PayOrderJob::dispatch($order->id);
                $this->info("   ✓ Đã gửi job thanh toán!");
            } else {
                $this->comment("   [CHẠY THỬ] Sẽ gửi job thanh toán");
            }
        }
    }

    /**
     * Create stock snapshot
     */
    protected function createStockSnapshot(): array
    {
        $snapshot = [];

        $variants = ProductVariant::select('variant_id', 'stock')
            ->where('active', true)
            ->get();

        foreach ($variants as $variant) {
            $snapshot[$variant->variant_id] = (int) $variant->stock;
        }

        return $snapshot;
    }
}

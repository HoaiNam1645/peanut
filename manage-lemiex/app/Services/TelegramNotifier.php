<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderIssue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    private string $apiBase = 'https://api.telegram.org';

    private function botToken(): ?string
    {
        return config('services.telegram.bot_token');
    }

    private function chatId(): ?string
    {
        return (string) config('services.telegram.chat_id');
    }

    /**
     * Send a new alert for an OrderIssue. Saves telegram_message_id back to record.
     */
    public function sendOrderIssue(Order $order, OrderIssue $issue): bool
    {
        $token = $this->botToken();
        $chatId = $this->chatId();

        if (!$token || !$chatId) {
            Log::warning('Telegram config missing — skipping alert', ['order_id' => $order->id]);
            return false;
        }

        $text = $this->formatMessage($order, $issue);
        $keyboard = $this->buildKeyboard($issue->id);

        try {
            $response = Http::timeout(8)->post("{$this->apiBase}/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
                'reply_markup' => json_encode($keyboard),
            ]);

            if (!$response->successful()) {
                Log::error('Telegram send failed', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            $data = $response->json();
            $messageId = $data['result']['message_id'] ?? null;

            $issue->update([
                'telegram_chat_id' => $chatId,
                'telegram_message_id' => $messageId,
                'notified_at' => now(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Telegram exception', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Update existing message after issue is resolved (called from webhook).
     */
    public function markMessageResolved(OrderIssue $issue, string $resolvedBy): bool
    {
        $token = $this->botToken();
        if (!$token || !$issue->telegram_chat_id || !$issue->telegram_message_id) {
            return false;
        }

        $order = $issue->order;
        $text = $this->formatMessage($order, $issue, resolvedBy: $resolvedBy);

        try {
            $response = Http::timeout(8)->post("{$this->apiBase}/bot{$token}/editMessageText", [
                'chat_id' => $issue->telegram_chat_id,
                'message_id' => $issue->telegram_message_id,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
                // Bỏ inline keyboard sau khi resolved
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Telegram editMessage exception', [
                'issue_id' => $issue->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Reply to callback query (small popup confirm to user).
     */
    public function answerCallback(string $callbackQueryId, string $text = ''): void
    {
        $token = $this->botToken();
        if (!$token) return;

        try {
            Http::timeout(5)->post("{$this->apiBase}/bot{$token}/answerCallbackQuery", [
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram answerCallback failed', ['error' => $e->getMessage()]);
        }
    }

    private function buildKeyboard(int $issueId): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '☑ Đã xử lý', 'callback_data' => "resolve:{$issueId}"],
                    ['text' => '✕ Bỏ qua', 'callback_data' => "ignore:{$issueId}"],
                ],
            ],
        ];
    }

    private function formatMessage(Order $order, OrderIssue $issue, ?string $resolvedBy = null): string
    {
        $frontendUrl = rtrim(config('services.telegram.frontend_url', 'https://manage.lemiex.us'), '/');
        $orderUrl = "{$frontendUrl}/lemiex/orders/{$order->id}";

        $sellerName = $order->seller->username ?? 'N/A';
        $storeName = $order->store->name ?? 'N/A';
        $createdAt = optional($order->created_at)->format('Y-m-d H:i') ?? 'N/A';
        $count = count($issue->info_error ?? []);

        $sevIcon = $issue->severity === OrderIssue::SEVERITY_CRITICAL ? '⛔' : '⚠️';
        $head = $resolvedBy
            ? "☑ <b>Đơn #{$order->id}</b> · đã xử lý bởi {$resolvedBy}"
            : "{$sevIcon} <b>Đơn #{$order->id}</b> · {$count} vấn đề";

        $issuesList = $this->formatIssuesList($issue->info_error ?? []);

        return <<<TEXT
{$head}
Ref: <code>{$order->ref_id}</code>
Seller: {$sellerName}  ·  Store: {$storeName}
Tạo: {$createdAt}

{$issuesList}

<a href="{$orderUrl}">› Mở đơn trên dashboard</a>
TEXT;
    }

    /**
     * Group issues by item_id để gộp gọn. Issue chung của order in cuối.
     */
    private function formatIssuesList(array $issues): string
    {
        $itemIssues = [];   // [itemId => [humanized msg, ...]]
        $orderIssues = [];  // [humanized msg, ...]

        foreach ($issues as $i) {
            $type = $i['type'] ?? '';
            $msg = $i['message'] ?? '';

            // Extract "Item #5: rest..."
            if (preg_match('/^Item #(\d+):\s*(.+)$/u', $msg, $m)) {
                $itemIssues[$m[1]][] = $this->humanize($type, $m[2]);
            } else {
                $orderIssues[] = $this->humanize($type, $msg);
            }
        }

        $lines = [];
        foreach ($itemIssues as $itemId => $msgs) {
            $lines[] = "<b>Item #{$itemId}</b>: " . htmlspecialchars(implode(', ', array_unique($msgs)));
        }
        foreach ($orderIssues as $msg) {
            $lines[] = '• ' . htmlspecialchars($msg);
        }

        return implode("\n", $lines);
    }

    /**
     * Convert internal issue type/message → text Vietnamese gọn cho người đọc.
     */
    private function humanize(string $type, string $msg): string
    {
        return match ($type) {
            // Item-level
            'meta_missing_pdf' => 'thiếu file PDF',
            'meta_missing_qr' => 'thiếu QR code',
            'meta_empty_value' => 'meta có giá trị trống',
            'item_no_metas' => 'không có order_item_metas',
            'item_missing_mockup' => 'thiếu mockup',
            'item_variant_orphan' => 'variant không tồn tại',
            // Order-level: keep original message (đã đủ rõ)
            'pricing_zero' => "Pricing sai: {$msg}",
            'paid_exceeds_total' => "Thanh toán vượt total: {$msg}",
            'no_items' => 'Đơn không có items nào',
            'missing_shipping_label' => 'Thiếu shipping_label (label_ship)',
            default => $msg,
        };
    }
}

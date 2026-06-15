<?php

namespace App\Http\Controllers;

use App\Models\OrderIssue;
use App\Services\TelegramNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(private TelegramNotifier $notifier) {}

    /**
     * Telegram sẽ POST tới endpoint này khi user click inline button.
     * Setup webhook:
     *   curl -F "url=https://YOUR_DOMAIN/api/telegram/webhook?secret=XXX" \
     *        https://api.telegram.org/bot<TOKEN>/setWebhook
     */
    public function handle(Request $request): JsonResponse
    {
        Log::info('[TG-WEBHOOK] Received', [
            'query' => $request->query(),
            'payload' => $request->all(),
        ]);

        // Optional secret check để chống ai cũng gọi được
        $expectedSecret = config('services.telegram.webhook_secret');
        if ($expectedSecret && $request->query('secret') !== $expectedSecret) {
            Log::warning('[TG-WEBHOOK] Invalid secret');
            return response()->json(['ok' => false, 'error' => 'Invalid secret'], 403);
        }

        $callback = $request->input('callback_query');
        if (!$callback) {
            Log::info('[TG-WEBHOOK] No callback_query in payload', [
                'keys' => array_keys($request->all()),
            ]);
            return response()->json(['ok' => true, 'note' => 'No callback_query']);
        }

        $callbackId = $callback['id'] ?? null;
        $data = $callback['data'] ?? '';
        $fromUser = $callback['from']['username']
            ?? trim(($callback['from']['first_name'] ?? '') . ' ' . ($callback['from']['last_name'] ?? ''))
            ?: 'unknown';

        Log::info('[TG-WEBHOOK] Callback', [
            'data' => $data,
            'from' => $fromUser,
            'callback_id' => $callbackId,
        ]);

        // Parse: "resolve:123" hoặc "ignore:123"
        if (!preg_match('/^(resolve|ignore):(\d+)$/', $data, $m)) {
            Log::warning('[TG-WEBHOOK] Invalid callback_data', ['data' => $data]);
            $this->notifier->answerCallback($callbackId, 'Action không hợp lệ');
            return response()->json(['ok' => true]);
        }

        $action = $m[1];
        $issueId = (int) $m[2];

        $issue = OrderIssue::find($issueId);
        if (!$issue) {
            Log::warning('[TG-WEBHOOK] Issue not found', ['issue_id' => $issueId]);
            $this->notifier->answerCallback($callbackId, 'Issue không tồn tại');
            return response()->json(['ok' => true]);
        }

        if ($issue->status !== OrderIssue::STATUS_OPEN) {
            Log::info('[TG-WEBHOOK] Issue already processed', [
                'issue_id' => $issueId,
                'current_status' => $issue->status,
            ]);
            $this->notifier->answerCallback($callbackId, "Issue đã ở trạng thái '{$issue->status}'");
            return response()->json(['ok' => true]);
        }

        $issue->update([
            'status' => $action === 'resolve' ? OrderIssue::STATUS_RESOLVED : OrderIssue::STATUS_IGNORED,
            'resolved_at' => now(),
            'resolved_by' => '@' . $fromUser,
        ]);

        $editOk = $this->notifier->markMessageResolved($issue, '@' . $fromUser);
        $this->notifier->answerCallback($callbackId, $action === 'resolve' ? 'Đã đánh dấu xử lý ✅' : 'Đã bỏ qua');

        Log::info('[TG-WEBHOOK] Resolved', [
            'issue_id' => $issueId,
            'action' => $action,
            'by' => $fromUser,
            'edit_message_ok' => $editOk,
        ]);

        return response()->json(['ok' => true]);
    }
}

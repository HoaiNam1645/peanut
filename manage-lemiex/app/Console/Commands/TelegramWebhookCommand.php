<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook
                            {action : set|info|delete}
                            {--url= : Public base URL (vd https://abc.ngrok.io)}';

    protected $description = 'Quản lý Telegram webhook cho bot (set / info / delete)';

    public function handle(): int
    {
        $token = config('services.telegram.bot_token');
        if (!$token) {
            $this->error('TELEGRAM_BOT_TOKEN chưa config');
            return self::FAILURE;
        }

        $apiBase = "https://api.telegram.org/bot{$token}";
        $action = $this->argument('action');

        return match ($action) {
            'set' => $this->set($apiBase),
            'info' => $this->info_($apiBase),
            'delete' => $this->delete($apiBase),
            default => $this->failWith("Unknown action: {$action}. Use: set|info|delete"),
        };
    }

    private function set(string $apiBase): int
    {
        $baseUrl = $this->option('url') ?: rtrim(config('app.url') ?: '', '/');
        if (!$baseUrl) {
            $this->error('Cần --url hoặc set APP_URL trong .env (sau đó php artisan config:clear)');
            return self::FAILURE;
        }

        $secret = config('services.telegram.webhook_secret');
        $webhookUrl = "{$baseUrl}/api/telegram/webhook" . ($secret ? "?secret={$secret}" : '');

        $this->line("Setting webhook → {$webhookUrl}");

        $resp = Http::post("{$apiBase}/setWebhook", [
            'url' => $webhookUrl,
            'allowed_updates' => ['callback_query', 'message'],
            'drop_pending_updates' => true,
        ]);

        $data = $resp->json();
        if (!($data['ok'] ?? false)) {
            $this->error('Set webhook failed: ' . json_encode($data));
            return self::FAILURE;
        }

        $this->line('<fg=green>✓ Webhook đã set</>');
        return $this->info_($apiBase);
    }

    private function info_(string $apiBase): int
    {
        $resp = Http::get("{$apiBase}/getWebhookInfo");
        $this->line(json_encode($resp->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }

    private function delete(string $apiBase): int
    {
        $resp = Http::post("{$apiBase}/deleteWebhook", ['drop_pending_updates' => true]);
        $this->line(json_encode($resp->json()));
        return self::SUCCESS;
    }

    private function failWith(string $msg): int
    {
        $this->error($msg);
        return self::FAILURE;
    }
}

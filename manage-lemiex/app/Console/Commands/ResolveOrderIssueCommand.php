<?php

namespace App\Console\Commands;

use App\Models\OrderIssue;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;

class ResolveOrderIssueCommand extends Command
{
    protected $signature = 'orders:resolve-issue
                            {issue_id : ID của order_issue}
                            {--action=resolve : resolve|ignore}
                            {--by=manual : Tên người resolve}';

    protected $description = 'Manually resolve một order_issue (test webhook flow không cần Telegram)';

    public function handle(TelegramNotifier $notifier): int
    {
        $issueId = (int) $this->argument('issue_id');
        $action = $this->option('action');
        $by = $this->option('by');

        $issue = OrderIssue::find($issueId);
        if (!$issue) {
            $this->error("Issue #{$issueId} không tồn tại");
            return self::FAILURE;
        }

        if ($issue->status !== OrderIssue::STATUS_OPEN) {
            $this->warn("Issue #{$issueId} đã ở status: {$issue->status}");
            return self::SUCCESS;
        }

        $issue->update([
            'status' => $action === 'resolve' ? OrderIssue::STATUS_RESOLVED : OrderIssue::STATUS_IGNORED,
            'resolved_at' => now(),
            'resolved_by' => "@{$by}",
        ]);

        $editOk = $notifier->markMessageResolved($issue, "@{$by}");

        $this->info("✓ Issue #{$issueId} → {$issue->status} bởi @{$by}");
        $this->line("  Telegram message edit: " . ($editOk ? 'OK' : 'FAILED (check log)'));

        return self::SUCCESS;
    }
}

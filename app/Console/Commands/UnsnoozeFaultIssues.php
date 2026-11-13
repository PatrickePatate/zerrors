<?php

namespace App\Console\Commands;

use App\Models\FaultIssue;
use App\Models\FaultIssueActivity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fault:unsnooze-issues')]
#[Description('Un-ignore issues whose time-based snooze has expired.')]
class UnsnoozeFaultIssues extends Command
{
    public function handle(): int
    {
        $count = 0;

        FaultIssue::where('status', 'ignored')
            ->whereNotNull('snoozed_until')
            ->where('snoozed_until', '<=', now())
            ->each(function (FaultIssue $issue) use (&$count): void {
                $issue->forceFill(['status' => 'unresolved', 'snoozed_until' => null, 'snooze_until_count' => null])->saveQuietly();
                FaultIssueActivity::record($issue, 'resurfaced', ['reason' => 'snooze_expired']);
                $count++;
            });

        $this->info("Un-ignored {$count} issue(s).");

        return self::SUCCESS;
    }
}

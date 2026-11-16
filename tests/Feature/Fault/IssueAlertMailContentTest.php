<?php

namespace Tests\Feature\Fault;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\User;
use App\Notifications\Fault\IssueAlertNotification;
use App\Notifications\Fault\IssueCreatedNotification;
use App\Notifications\Fault\IssueRegressedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueAlertMailContentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, array<string, mixed>>  $frames  Outermost first, as SDKs send them.
     */
    protected function issueWithFrames(array $frames, array $attributes = []): FaultIssue
    {
        $issue = FaultIssue::factory()->create($attributes);

        FaultEvent::factory()->create([
            'fault_project_id' => $issue->fault_project_id,
            'fault_issue_id' => $issue->id,
            'exception' => ['values' => [[
                'type' => 'RuntimeException',
                'value' => 'boom',
                'stacktrace' => ['frames' => $frames],
            ]]],
        ]);

        return $issue;
    }

    protected function renderCreatedMail(FaultIssue $issue): string
    {
        return (string) (new IssueCreatedNotification($issue))->toMail(User::factory()->make())->render();
    }

    public function test_triage_verdict_is_shown_once_the_issue_has_been_triaged(): void
    {
        $issue = FaultIssue::factory()->create([
            'triage_category' => 'code_bug',
            'triage_priority' => 'high',
            'triaged_at' => now(),
        ]);

        $html = $this->renderCreatedMail($issue);

        $this->assertStringContainsString('AI triage', $html);
        $this->assertStringContainsString('Code bug', $html);
        $this->assertStringContainsString('High priority', $html);
    }

    public function test_triage_verdict_is_left_out_when_the_issue_has_not_been_triaged(): void
    {
        $issue = FaultIssue::factory()->create(['triage_error' => 'Unknown model']);

        $html = $this->renderCreatedMail($issue);

        $this->assertStringNotContainsString('AI triage', $html);
        $this->assertStringNotContainsString('Unknown model', $html);
    }

    public function test_in_app_frames_are_listed_innermost_first_with_the_failing_line(): void
    {
        $issue = $this->issueWithFrames([
            ['filename' => 'app/Http/Controllers/OrderController.php', 'lineno' => 18, 'function' => 'store', 'in_app' => true],
            ['filename' => 'vendor/laravel/framework/Pipeline.php', 'lineno' => 200, 'function' => 'then', 'in_app' => false],
            ['filename' => 'app/Services/Billing.php', 'lineno' => 42, 'function' => 'charge', 'in_app' => true, 'context_line' => '    return $total / $count;'],
        ]);

        $html = $this->renderCreatedMail($issue);

        $this->assertStringContainsString('<pre', $html);
        $this->assertStringNotContainsString('Pipeline.php', $html);
        $this->assertStringContainsString('&gt; return $total / $count;', $html);
        $this->assertStringContainsString(' in charge', $html);
        $this->assertLessThan(
            strpos($html, 'app/Http/Controllers/OrderController.php:18'),
            strpos($html, 'app/Services/Billing.php:42'),
        );
    }

    public function test_innermost_frames_are_shown_when_none_is_in_app(): void
    {
        $frames = [];

        foreach (range(1, 7) as $depth) {
            $frames[] = ['filename' => "vendor/lib/Frame{$depth}.php", 'lineno' => $depth, 'in_app' => false];
        }

        $html = $this->renderCreatedMail($this->issueWithFrames($frames));

        $this->assertStringContainsString('vendor/lib/Frame7.php:7', $html);
        $this->assertStringContainsString('vendor/lib/Frame3.php:3', $html);
        $this->assertStringNotContainsString('vendor/lib/Frame2.php', $html);
    }

    public function test_frame_content_cannot_break_out_of_the_code_block(): void
    {
        $issue = $this->issueWithFrames([
            ['filename' => "app/x.php\n\n</pre><img src=x onerror=alert(1)>", 'lineno' => 1, 'function' => '<script>evil</script>', 'in_app' => true],
        ]);

        $html = $this->renderCreatedMail($issue);

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<script>evil', $html);
        $this->assertStringContainsString('&lt;img src=x', $html);
    }

    public function test_issue_title_and_culprit_are_escaped(): void
    {
        $issue = FaultIssue::factory()->create([
            'title' => 'Bad <script>alert(1)</script> title',
            'culprit' => '<b>culprit</b>',
        ]);

        $html = $this->renderCreatedMail($issue);

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('Bad &lt;script&gt;alert(1)&lt;/script&gt; title', $html);
        $this->assertStringContainsString('&lt;b&gt;culprit&lt;/b&gt;', $html);
    }

    public function test_mail_renders_without_stack_or_triage(): void
    {
        $issue = FaultIssue::factory()->create(['title' => 'Plain log issue']);

        $html = $this->renderCreatedMail($issue);

        $this->assertStringContainsString('Plain log issue', $html);
        $this->assertStringNotContainsString('AI triage', $html);
        $this->assertStringNotContainsString('<pre', $html);
    }

    public function test_regression_and_channel_alert_mails_carry_the_same_details(): void
    {
        $issue = $this->issueWithFrames(
            [['filename' => 'app/Services/Billing.php', 'lineno' => 42, 'function' => 'charge', 'in_app' => true]],
            ['triage_category' => 'database', 'triage_priority' => 'critical', 'triaged_at' => now()],
        );
        $recipient = User::factory()->make();

        $mails = [
            (new IssueRegressedNotification($issue))->toMail($recipient),
            (new IssueAlertNotification($issue, 'New occurrence'))->toMail($recipient),
        ];

        foreach ($mails as $mail) {
            $html = (string) $mail->render();

            $this->assertStringContainsString('Critical priority', $html);
            $this->assertStringContainsString('app/Services/Billing.php:42', $html);
        }
    }
}

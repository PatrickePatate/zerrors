<?php

namespace App\Notifications\Fault\Concerns;

use App\Enums\TriageCategory;
use App\Enums\TriagePriority;
use App\Models\FaultIssue;
use App\Support\Fault\StacktraceExceptionValue;
use App\Support\Fault\StacktraceFrame;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

trait DescribesIssueInMail
{
    /**
     * How many stack frames an alert email shows at most.
     */
    private const int MAIL_STACK_FRAME_LIMIT = 5;

    private const string MAIL_MONOSPACE = 'font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;';

    /**
     * Vivid solid colors per tone, matching the tones the triage enums
     * expose through color(), with the text color readable on each.
     *
     * @var array<string, array{background: string, text: string}>
     */
    private const array MAIL_TONES = [
        'red' => ['background' => '#ff2d55', 'text' => '#ffffff'],
        'amber' => ['background' => '#ffcc00', 'text' => '#0a0a0a'],
        'indigo' => ['background' => '#5e5ce6', 'text' => '#ffffff'],
        'blue' => ['background' => '#0a84ff', 'text' => '#ffffff'],
        'green' => ['background' => '#30d158', 'text' => '#0a0a0a'],
        'gray' => ['background' => '#0a0a0a', 'text' => '#ffffff'],
    ];

    /**
     * The issue-specific part of an alert email: a callout with the title
     * and culprit, the AI triage verdict once the issue has been triaged,
     * and the most relevant frames of the latest event's stack trace when
     * it has one.
     *
     * The blocks are inline-styled HTML (single-line, so Markdown passes
     * them through untouched); everything coming from the issue is escaped.
     *
     * @param  string  $tone  One of the MAIL_TONES keys, coloring the callout.
     * @return array<int, Htmlable>
     */
    protected function issueMailLines(FaultIssue $issue, string $tone = 'red'): array
    {
        return array_values(array_filter([
            $this->issueCallout($issue, $tone),
            $this->triageBadges($issue),
            $this->stackExcerpt($issue),
        ]));
    }

    protected function issueCallout(FaultIssue $issue, string $tone): Htmlable
    {
        $colors = self::MAIL_TONES[$tone] ?? self::MAIL_TONES['red'];

        $culprit = $issue->culprit
            ? '<div style="'.self::MAIL_MONOSPACE.' font-size: 12px; color: #71717a; margin-top: 8px; word-break: break-all;">'.$this->singleLine($issue->culprit).'</div>'
            : '';

        return new HtmlString(
            '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 16px;"><tr>'
            .'<td style="background-color: #ffffff; border: 1px solid #e4e4e7; border-left: 6px solid '.$colors['background'].'; border-radius: 12px; padding: 16px 18px;">'
            .'<div style="font-size: 17px; font-weight: 800; letter-spacing: -0.02em; line-height: 1.35; color: #0a0a0a; word-break: break-word;">'.$this->singleLine((string) $issue->title, 300).'</div>'
            .$culprit
            .'</td></tr></table>'
        );
    }

    protected function triageBadges(FaultIssue $issue): ?Htmlable
    {
        if (! $issue->triaged_at) {
            return null;
        }

        $category = TriageCategory::tryFrom((string) $issue->triage_category);
        $priority = TriagePriority::tryFrom((string) $issue->triage_priority);

        $badges = implode(' ', array_filter([
            $category ? $this->badge($category->label(), $category->color()) : null,
            $priority ? $this->badge("{$priority->label()} priority", $priority->color()) : null,
        ]));

        if ($badges === '') {
            return null;
        }

        return new HtmlString(
            '<p style="margin: 0 0 16px; font-size: 13px; line-height: 2; color: #71717a;">'
            .$this->badge('AI triage', 'gray').' '.$badges.'</p>'
        );
    }

    private function badge(string $label, string $tone): string
    {
        $colors = self::MAIL_TONES[$tone] ?? self::MAIL_TONES['gray'];

        return '<span style="display: inline-block; background-color: '.$colors['background'].'; border-radius: 999px; color: '.$colors['text'].'; font-size: 11px; font-weight: 700; letter-spacing: 0.04em; line-height: 1.4; padding: 4px 10px; text-transform: uppercase;">'.e($label).'</span>';
    }

    /**
     * A dark code block listing the innermost in-app frames of the thrown
     * exception (or the innermost frames when none is flagged in-app), with
     * the failing source line under the first one. Long frames wrap rather
     * than stretch the email layout.
     */
    protected function stackExcerpt(FaultIssue $issue): ?Htmlable
    {
        $values = $issue->latestEvent?->exception['values'] ?? null;

        if (! is_array($values) || ! is_array($thrown = end($values))) {
            return null;
        }

        $frames = collect(StacktraceExceptionValue::fromPayload($thrown)->frames);
        $inAppFrames = $frames->filter(fn (StacktraceFrame $frame): bool => $frame->inApp);

        $lines = ($inAppFrames->isNotEmpty() ? $inAppFrames : $frames)
            ->take(self::MAIL_STACK_FRAME_LIMIT)
            ->values()
            ->flatMap(fn (StacktraceFrame $frame, int $index): array => array_filter([
                $this->frameLine($frame),
                $index === 0 ? $this->failingCodeLine($frame) : null,
            ]));

        if ($lines->isEmpty()) {
            return null;
        }

        return new HtmlString(
            '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 16px;"><tr>'
            .'<td style="background-color: #0a0a0a; border-radius: 12px; padding: 16px 18px;">'
            .'<pre style="'.self::MAIL_MONOSPACE.' font-size: 12px; line-height: 1.7; color: #fafafa; margin: 0; white-space: pre-wrap; word-break: break-all;">'
            .$lines->implode('&#10;')
            .'</pre></td></tr></table>'
        );
    }

    private function frameLine(StacktraceFrame $frame): string
    {
        $location = $this->singleLine($frame->filename.($frame->lineNumber ? ":{$frame->lineNumber}" : ''));

        return $frame->functionName
            ? $location.'<span style="color: #71717a;"> in '.$this->singleLine($frame->functionName).'</span>'
            : $location;
    }

    private function failingCodeLine(StacktraceFrame $frame): ?string
    {
        $code = trim((string) (collect($frame->codeLines)->firstWhere('highlighted', true)['code'] ?? ''));

        return $code === ''
            ? null
            : '<span style="color: #ffcc00;">    &gt; '.$this->singleLine($code).'</span>';
    }

    /**
     * Issue and frame content comes from the reporting SDK: keep each on a
     * single, escaped line so it can neither inject markup nor end the HTML
     * block early.
     */
    private function singleLine(string $text, int $limit = 200): string
    {
        return e(Str::limit(Str::squish($text), $limit));
    }
}

<?php

namespace App\Support\Fault;

use App\Models\FaultEvent;
use App\Models\FaultIssue;

class StacktraceMarkdownFormatter
{
    /**
     * Render an event's exception as Markdown suitable for pasting into an AI
     * agent or issue tracker: title/metadata, the exception chain, and each
     * frame with its file:line and surrounding code context.
     */
    public static function format(FaultIssue $issue, FaultEvent $event): string
    {
        $lines = [
            "# {$issue->title}",
            '',
            "- **Level:** {$issue->level}",
            '- **Environment:** '.($event->environment ?? '—'),
            '- **Release:** '.($event->release ?? '—'),
            "- **Occurred at:** {$event->occurred_at}",
        ];

        if ($issue->culprit) {
            $lines[] = "- **Culprit:** `{$issue->culprit}`";
        }

        $values = array_map(
            fn (array $value) => StacktraceExceptionValue::fromPayload($value),
            array_reverse($event->exception['values'] ?? []),
        );

        foreach ($values as $value) {
            $lines[] = '';
            $lines[] = "## {$value->type}";

            if ($value->message) {
                $lines[] = '';
                $lines[] = $value->message;
            }

            if ($value->frames === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = '```';

            foreach (array_reverse($value->frames) as $frame) {
                $location = $frame->filename.($frame->lineNumber ? ":{$frame->lineNumber}" : '');
                $function = $frame->functionName ? " in {$frame->functionName}()" : '';
                $lines[] = ($frame->inApp ? '' : '[vendor] ').$location.$function;

                foreach ($frame->codeLines as $codeLine) {
                    $marker = $codeLine['highlighted'] ? '>' : ' ';
                    $lines[] = "    {$marker} {$codeLine['line']}| {$codeLine['code']}";
                }
            }

            $lines[] = '```';
        }

        if ($event->request) {
            $lines[] = '';
            $lines[] = '## Request';
            $lines[] = '';
            $lines[] = trim(($event->request['method'] ?? 'GET').' '.($event->request['url'] ?? ''));

            if (! empty($event->request['query_string'])) {
                $lines[] = "Query: `{$event->request['query_string']}`";
            }
        }

        if ($event->contexts) {
            $lines[] = '';
            $lines[] = '## Context';

            foreach ($event->contexts as $group => $values) {
                if (! is_array($values)) {
                    continue;
                }

                foreach ($values as $key => $value) {
                    if (is_array($value)) {
                        continue;
                    }

                    $lines[] = "- **{$group}.{$key}:** {$value}";
                }
            }
        }

        return implode("\n", $lines)."\n";
    }
}

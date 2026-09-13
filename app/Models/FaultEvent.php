<?php

namespace App\Models;

use Database\Factories\FaultEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaultEvent extends Model
{
    /** @use HasFactory<FaultEventFactory> */
    use HasFactory;

    /**
     * Table-qualified columns needed to render the Livewire/queue/bot badges off a
     * single event, for eager-loading FaultIssue::latestEvent() in issue lists without
     * pulling the full (potentially large) exception/request/contexts/breadcrumbs payload
     * per row. Table-qualified because latestOfMany()'s subquery join would otherwise
     * make `fault_issue_id` ambiguous.
     */
    public const BADGE_COLUMNS = 'fault_events.id,fault_events.fault_issue_id,fault_events.exception,fault_events.request';

    protected $fillable = [
        'fault_project_id', 'fault_issue_id', 'event_id', 'level', 'message', 'culprit',
        'environment', 'release', 'transaction', 'server_name', 'exception', 'sdk',
        'tags', 'extra', 'contexts', 'request', 'breadcrumbs', 'log_context', 'payload', 'occurred_at',
    ];

    protected $casts = [
        'exception' => 'array',
        'sdk' => 'array',
        'tags' => 'array',
        'extra' => 'array',
        'contexts' => 'array',
        'request' => 'array',
        'breadcrumbs' => 'array',
        'log_context' => 'array',
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(FaultProject::class, 'fault_project_id');
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(FaultIssue::class, 'fault_issue_id');
    }

    /**
     * @return array{id?: mixed, username?: mixed, email?: mixed, ip_address?: mixed}|null
     */
    public function contextUser(): ?array
    {
        return $this->payload['user'] ?? null;
    }

    /**
     * The FQCN of the Livewire component the exception was thrown in, detected by
     * walking the stack frames for the first one that looks like a component: either
     * a file under app/Livewire, or a frame whose function is reported as "Class::method"
     * where Class sits under a `Livewire` namespace segment (how PHP's exception trace
     * reports calls made through Livewire's internal dispatcher, e.g. anonymous/compiled
     * component classes that don't live under app/Livewire on disk).
     */
    public function livewireComponent(): ?string
    {
        return $this->classFromFrames('Livewire');
    }

    /**
     * The FQCN of the queued job the exception was thrown in, detected the same
     * way as livewireComponent(): a file under app/Jobs, or a frame reported as
     * "Class::method" where Class sits under a `Jobs` namespace segment.
     */
    public function queueJob(): ?string
    {
        return $this->classFromFrames('Jobs');
    }

    /**
     * Whether this event happened while a queued job was being processed, even
     * when the job class itself couldn't be pinned down (e.g. a queued Closure).
     * Detected by the presence of Laravel's queue worker in the stack frames.
     */
    public function isQueuedJob(): bool
    {
        if ($this->queueJob() !== null) {
            return true;
        }

        foreach ($this->exception['values'] ?? [] as $value) {
            foreach ($value['stacktrace']['frames'] ?? [] as $frame) {
                if (str_contains((string) ($frame['filename'] ?? ''), 'Illuminate/Queue/')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Walks the exception's stack frames for the first one that looks like it
     * belongs to a class under the app/{$namespaceSegment} directory: either a
     * file at that path, or a frame reported as "Class::method" where Class
     * sits under that namespace segment (how PHP reports calls made through a
     * framework's internal dispatcher, e.g. anonymous/compiled classes that
     * don't live on disk where the namespace would suggest).
     */
    private function classFromFrames(string $namespaceSegment): ?string
    {
        foreach ($this->exception['values'] ?? [] as $value) {
            foreach ($value['stacktrace']['frames'] ?? [] as $frame) {
                if ($class = $this->classFromFrame($frame, $namespaceSegment)) {
                    return $class;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private function classFromFrame(array $frame, string $namespaceSegment): ?string
    {
        if (preg_match('#^app/'.$namespaceSegment.'/(.+)\.php$#i', (string) ($frame['filename'] ?? ''), $matches)) {
            return 'App\\'.$namespaceSegment.'\\'.str_replace('/', '\\', $matches[1]);
        }

        $function = (string) ($frame['function'] ?? '');

        if (preg_match('#^(.+\\\\'.$namespaceSegment.'\\\\.+)::#i', $function, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * The page the visitor was actually on, for apps where the captured
     * request is an internal API/XHR call rather than the browser URL.
     * Falls back to the request URL when the frontend didn't report one.
     */
    public function contextUrl(): ?string
    {
        return $this->headerValue('x-current-page-url') ?? ($this->request['url'] ?? null);
    }

    /**
     * Whether this event captured enough of the HTTP call to be reproduced as a
     * curl command: a URL, plus at least the headers or the request body.
     */
    public function hasReproducibleRequest(): bool
    {
        return ! empty($this->request['url'] ?? null)
            && (! empty($this->request['headers'] ?? null) || ! empty($this->request['data'] ?? null));
    }

    /**
     * Named crawlers to recognize before falling back to the generic
     * "bot"/"crawl"/"spider" patterns. Order matters: the first match wins,
     * so specific names must be listed before the generic fallbacks.
     */
    private const BOT_LABELS = [
        'googlebot' => 'Googlebot',
        'bingbot' => 'Bingbot',
        'bingpreview' => 'Bing Preview',
        'slurp' => 'Yahoo Slurp',
        'duckduckbot' => 'DuckDuckBot',
        'baiduspider' => 'Baiduspider',
        'yandexbot' => 'YandexBot',
        'facebookexternalhit' => 'Facebook',
        'twitterbot' => 'Twitterbot',
        'linkedinbot' => 'LinkedInBot',
        'whatsapp' => 'WhatsApp',
        'semrushbot' => 'SemrushBot',
        'ahrefsbot' => 'AhrefsBot',
        'mj12bot' => 'Majestic',
        'dotbot' => 'DotBot',
        'blexbot' => 'BLEXBot',
        'petalbot' => 'PetalBot',
        'gptbot' => 'GPTBot',
        'claudebot' => 'ClaudeBot',
        'headlesschrome' => 'Headless Chrome',
        'crawl' => 'Crawler',
        'spider' => 'Spider',
        'bot' => 'Bot',
    ];

    public function browserLabel(): ?string
    {
        $userAgent = $this->headerValue('user-agent');

        if (! $userAgent) {
            return null;
        }

        if ($botLabel = $this->botLabel($userAgent)) {
            return $botLabel;
        }

        return match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Unknown browser',
        };
    }

    /**
     * A lightweight heuristic, not a guarantee: it only catches user agents
     * that self-identify as a crawler and won't detect one that spoofs a
     * regular browser string.
     */
    public function isLikelyBot(): bool
    {
        $userAgent = $this->headerValue('user-agent');

        return $userAgent !== null && $this->botLabel($userAgent) !== null;
    }

    private function botLabel(string $userAgent): ?string
    {
        $haystack = strtolower($userAgent);

        foreach (self::BOT_LABELS as $needle => $label) {
            if (str_contains($haystack, $needle)) {
                return $label;
            }
        }

        return null;
    }

    private function headerValue(string $key): ?string
    {
        $value = $this->request['headers'][$key] ?? null;

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}

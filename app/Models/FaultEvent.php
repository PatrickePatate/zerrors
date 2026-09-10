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

    protected $fillable = [
        'fault_project_id', 'fault_issue_id', 'event_id', 'level', 'message', 'culprit',
        'environment', 'release', 'transaction', 'server_name', 'exception', 'sdk',
        'tags', 'extra', 'contexts', 'request', 'breadcrumbs', 'payload', 'occurred_at',
    ];

    protected $casts = [
        'exception' => 'array',
        'sdk' => 'array',
        'tags' => 'array',
        'extra' => 'array',
        'contexts' => 'array',
        'request' => 'array',
        'breadcrumbs' => 'array',
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
     * The page the visitor was actually on, for apps where the captured
     * request is an internal API/XHR call rather than the browser URL.
     * Falls back to the request URL when the frontend didn't report one.
     */
    public function contextUrl(): ?string
    {
        return $this->headerValue('x-current-page-url') ?? ($this->request['url'] ?? null);
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

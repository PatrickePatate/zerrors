<?php

namespace App\Models;

use App\Enums\FaultPlatform;
use Database\Factories\FaultProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FaultProject extends Model
{
    /** @use HasFactory<FaultProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id', 'name', 'slug', 'platform', 'public_key', 'secret_key',
        'retention_days', 'github_repo', 'github_token', 'production_branch', 'github_webhook_secret',
        'slack_webhook_url', 'telegram_bot_token', 'telegram_chat_id', 'notify_email',
    ];

    protected $casts = [
        'platform' => FaultPlatform::class,
        'github_token' => 'encrypted',
        'github_webhook_secret' => 'encrypted',
        'telegram_bot_token' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $project): void {
            $project->slug ??= Str::slug($project->name).'-'.Str::lower(Str::random(6));
            $project->public_key ??= Str::random(32);
            $project->production_branch ??= 'main';
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(FaultIssue::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(FaultEvent::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    /**
     * Builds the DSN to give to sentry/sentry-laravel's config/sentry.php.
     * Example: https://<public_key>@yourdomain.com/<project_id>
     */
    public function dsn(?string $host = null): string
    {
        $host = $host ?? request()->getHost().(request()->getPort() && ! in_array(request()->getPort(), [80, 443]) ? ':'.request()->getPort() : '');

        return "https://{$this->public_key}@{$host}/{$this->id}";
    }

    public function hasGithubConfigured(): bool
    {
        return ! empty($this->github_repo) && ! empty($this->github_token);
    }

    public function hasGithubWebhookConfigured(): bool
    {
        return ! empty($this->github_repo) && ! empty($this->github_webhook_secret);
    }

    /**
     * The URL to register as a "push" webhook on the GitHub repository, so that
     * a release is created automatically whenever the production branch moves.
     */
    public function githubWebhookUrl(?string $host = null): string
    {
        $host = $host ?? request()->getSchemeAndHttpHost();

        return "{$host}/api/webhooks/github/{$this->public_key}";
    }

    public function hasSlackConfigured(): bool
    {
        return ! empty($this->slack_webhook_url);
    }

    public function hasTelegramConfigured(): bool
    {
        return ! empty($this->telegram_bot_token) && ! empty($this->telegram_chat_id);
    }

    public function hasEmailAlertConfigured(): bool
    {
        return ! empty($this->notify_email);
    }
}

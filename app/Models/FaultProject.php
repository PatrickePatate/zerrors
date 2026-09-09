<?php

namespace App\Models;

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

    const PLATFORMS = [
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'symfony' => 'Symfony',
        'wordpress' => 'WordPress',
        'nodejs' => 'Node.js',
        'other' => 'Other',
    ];

    protected $fillable = [
        'organization_id', 'name', 'slug', 'platform', 'public_key', 'secret_key',
        'retention_days', 'github_repo', 'github_token',
        'slack_webhook_url', 'telegram_bot_token', 'telegram_chat_id', 'notify_email',
    ];

    protected $casts = [
        'github_token' => 'encrypted',
        'telegram_bot_token' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $project): void {
            $project->slug ??= Str::slug($project->name).'-'.Str::lower(Str::random(6));
            $project->public_key ??= Str::random(32);
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

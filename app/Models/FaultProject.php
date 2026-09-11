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
        'retention_days', 'github_repo', 'production_branch',
        'forward_enabled', 'forward_dsn',
    ];

    protected $casts = [
        'platform' => FaultPlatform::class,
        'forward_enabled' => 'boolean',
        'forward_dsn' => 'encrypted',
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

    public function notificationChannels(): HasMany
    {
        return $this->hasMany(NotificationChannel::class);
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
        return ! empty($this->github_repo) && ! empty($this->organization?->github_installation_id);
    }

    public function isForwardingConfigured(): bool
    {
        return $this->forward_enabled && ! empty($this->forward_dsn);
    }
}

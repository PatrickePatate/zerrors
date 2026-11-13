<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'ai_provider', 'ai_api_key', 'ai_model', 'ai_deep_model', 'ai_triage_enabled', 'ai_triage_model', 'ai_triage_api_key', 'alerts_enabled',
        'require_2fa', 'github_installation_id', 'github_account_login', 'github_account_type',
        'github_connected_at', 'slack_team_id', 'slack_team_name', 'slack_bot_token',
        'slack_authed_user_id', 'slack_connected_at',
    ];

    protected $hidden = ['ai_api_key', 'ai_triage_api_key', 'slack_bot_token'];

    protected $casts = [
        'ai_api_key' => 'encrypted',
        'ai_triage_api_key' => 'encrypted',
        'ai_triage_enabled' => 'boolean',
        'alerts_enabled' => 'boolean',
        'require_2fa' => 'boolean',
        'github_connected_at' => 'datetime',
        'slack_bot_token' => 'encrypted',
        'slack_connected_at' => 'datetime',
    ];

    /**
     * Providers supported by laravel/ai that we expose in the UI.
     *
     * @var array<string, string>
     */
    public const AI_PROVIDERS = [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic',
        'mistral' => 'Mistral',
        'openrouter' => 'OpenRouter',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $organization): void {
            $organization->slug ??= Str::slug($organization->name).'-'.Str::lower(Str::random(5));
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role', 'digest_frequency')->withTimestamps();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(FaultProject::class);
    }

    public function monitors(): HasMany
    {
        return $this->hasMany(Monitor::class);
    }

    public function statusPage(): HasOne
    {
        return $this->hasOne(StatusPage::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(OrganizationInvite::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function roleFor(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return $this->users->firstWhere('id', $user->id)?->pivot->role
            ?? $this->users()->where('user_id', $user->id)->value('role');
    }

    public function roleEnumFor(?User $user): ?OrganizationRole
    {
        $role = $this->roleFor($user);

        return $role === null ? null : OrganizationRole::tryFrom($role);
    }

    public function hasAiConfigured(): bool
    {
        return ! empty($this->ai_provider) && ! empty($this->ai_api_key);
    }

    /**
     * The model to use for the deeper AI analysis, falling back to the
     * organization's standard model when no dedicated one is configured.
     */
    public function aiDeepModel(): ?string
    {
        return $this->ai_deep_model ?: $this->ai_model ?: null;
    }

    /**
     * The OpenRouter key used for triage: the organization's own provider key
     * when it is on OpenRouter, otherwise the dedicated triage key.
     */
    public function aiTriageKey(): ?string
    {
        $key = $this->ai_provider === 'openrouter' ? $this->ai_api_key : $this->ai_triage_api_key;

        return filled($key) ? $key : null;
    }

    public function canTriageIssues(): bool
    {
        return $this->ai_triage_enabled && $this->aiTriageKey() !== null;
    }

    /**
     * The model ID sent to OpenRouter as-is for triage.
     */
    public function aiTriageModelId(): string
    {
        return $this->ai_triage_model ?: (string) config('fault.ai_triage_model');
    }

    public function hasGithubConnected(): bool
    {
        return ! empty($this->github_installation_id);
    }

    public function hasSlackConnected(): bool
    {
        return ! empty($this->slack_bot_token);
    }
}

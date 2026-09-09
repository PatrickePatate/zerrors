<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'ai_provider', 'ai_api_key', 'ai_model', 'alerts_enabled',
        'require_2fa',
    ];

    protected $casts = [
        'ai_api_key' => 'encrypted',
        'alerts_enabled' => 'boolean',
        'require_2fa' => 'boolean',
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
    ];

    protected static function booted(): void
    {
        static::creating(function (self $organization): void {
            $organization->slug ??= Str::slug($organization->name).'-'.Str::lower(Str::random(5));
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(FaultProject::class);
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

    public function hasAiConfigured(): bool
    {
        return ! empty($this->ai_provider) && ! empty($this->ai_api_key);
    }
}

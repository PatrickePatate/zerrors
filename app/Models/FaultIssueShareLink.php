<?php

namespace App\Models;

use Database\Factories\FaultIssueShareLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FaultIssueShareLink extends Model
{
    /** @use HasFactory<FaultIssueShareLinkFactory> */
    use HasFactory;

    protected $fillable = [
        'fault_issue_id', 'created_by_user_id', 'token', 'visibility',
        'password_hash', 'expires_at', 'revoked_at',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $link): void {
            $link->token ??= self::generateUniqueToken();
        });
    }

    public static function generateUniqueToken(): string
    {
        do {
            $token = Str::random(40);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(FaultIssue::class, 'fault_issue_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPasswordProtected(): bool
    {
        return $this->visibility === 'password';
    }

    public function isAccessible(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public function checkPassword(string $password): bool
    {
        return $this->password_hash !== null && Hash::check($password, $this->password_hash);
    }
}

<?php

namespace App\Models;

use App\Enums\NotificationChannelType;
use Database\Factories\NotificationChannelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationChannel extends Model
{
    /** @use HasFactory<NotificationChannelFactory> */
    use HasFactory;

    protected $fillable = [
        'fault_project_id', 'type', 'name', 'config', 'enabled',
    ];

    protected $casts = [
        'type' => NotificationChannelType::class,
        'config' => 'encrypted:array',
        'enabled' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(FaultProject::class, 'fault_project_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(NotificationRule::class);
    }

    /**
     * A masked, user-facing summary of this channel's destination.
     */
    public function label(): string
    {
        return match ($this->type) {
            NotificationChannelType::Slack => '#'.($this->config['channel_name'] ?? '?'),
            NotificationChannelType::Telegram => 'Chat '.($this->config['chat_id'] ?? '?'),
            NotificationChannelType::Email => $this->config['email'] ?? '',
        };
    }
}

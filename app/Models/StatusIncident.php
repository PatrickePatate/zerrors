<?php

namespace App\Models;

use Database\Factories\StatusIncidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatusIncident extends Model
{
    /** @use HasFactory<StatusIncidentFactory> */
    use HasFactory;

    protected $fillable = ['status_page_id', 'monitor_id', 'title', 'started_at', 'resolved_at', 'auto'];

    protected $casts = [
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
        'auto' => 'boolean',
    ];

    public function statusPage(): BelongsTo
    {
        return $this->belongsTo(StatusPage::class);
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(StatusIncidentUpdate::class, 'incident_id')->orderBy('created_at')->orderBy('id');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}

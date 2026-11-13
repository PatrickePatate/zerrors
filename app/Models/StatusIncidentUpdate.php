<?php

namespace App\Models;

use App\Enums\StatusIncidentState;
use Database\Factories\StatusIncidentUpdateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusIncidentUpdate extends Model
{
    /** @use HasFactory<StatusIncidentUpdateFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['incident_id', 'body', 'status'];

    protected $casts = [
        'status' => StatusIncidentState::class,
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(StatusIncident::class, 'incident_id');
    }
}

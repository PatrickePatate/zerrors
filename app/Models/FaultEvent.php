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
}

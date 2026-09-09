<?php

namespace App\Models;

use Database\Factories\FaultIssueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaultIssue extends Model
{
    /** @use HasFactory<FaultIssueFactory> */
    use HasFactory;

    protected $fillable = [
        'fault_project_id', 'fingerprint', 'type', 'title', 'culprit',
        'level', 'status', 'times_seen', 'first_seen_at', 'last_seen_at',
        'ai_analysis', 'ai_analyzed_at', 'assigned_to_user_id', 'regressed_at',
        'first_seen_release', 'github_issue_url', 'github_issue_number',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'ai_analyzed_at' => 'datetime',
        'regressed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(FaultProject::class, 'fault_project_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(FaultEvent::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}

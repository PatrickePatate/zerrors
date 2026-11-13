<?php

namespace App\Models;

use Database\Factories\FaultFeedbackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaultFeedback extends Model
{
    /** @use HasFactory<FaultFeedbackFactory> */
    use HasFactory;

    protected $table = 'fault_feedback';

    protected $fillable = [
        'fault_project_id', 'fault_issue_id', 'fault_event_id', 'event_id', 'name', 'email', 'comments',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(FaultProject::class, 'fault_project_id');
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(FaultIssue::class, 'fault_issue_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FaultEvent::class, 'fault_event_id');
    }
}

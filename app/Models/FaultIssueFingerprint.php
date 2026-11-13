<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaultIssueFingerprint extends Model
{
    protected $fillable = ['fault_project_id', 'fingerprint', 'fault_issue_id'];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(FaultIssue::class, 'fault_issue_id');
    }
}

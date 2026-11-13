<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaultIssueActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['fault_issue_id', 'user_id', 'type', 'data'];

    protected $casts = ['data' => 'array', 'created_at' => 'datetime'];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(FaultIssue::class, 'fault_issue_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function record(FaultIssue $issue, string $type, array $data = [], ?User $user = null): self
    {
        return static::create([
            'fault_issue_id' => $issue->id,
            'user_id' => $user?->id,
            'type' => $type,
            'data' => $data === [] ? null : $data,
        ]);
    }
}

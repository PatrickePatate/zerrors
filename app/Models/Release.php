<?php

namespace App\Models;

use Database\Factories\ReleaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Release extends Model
{
    /** @use HasFactory<ReleaseFactory> */
    use HasFactory;

    protected $fillable = [
        'fault_project_id', 'version', 'notes', 'deployed_at',
        'commit_sha', 'commit_url', 'commit_message', 'commit_author', 'committed_at',
        'commit_files', 'commit_additions', 'commit_deletions',
    ];

    protected $casts = [
        'deployed_at' => 'datetime',
        'committed_at' => 'datetime',
        'commit_files' => 'array',
    ];

    public function hasCommit(): bool
    {
        return ! empty($this->commit_sha);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(FaultProject::class, 'fault_project_id');
    }
}

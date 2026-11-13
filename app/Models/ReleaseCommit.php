<?php

namespace App\Models;

use Database\Factories\ReleaseCommitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseCommit extends Model
{
    /** @use HasFactory<ReleaseCommitFactory> */
    use HasFactory;

    protected $fillable = ['release_id', 'sha', 'message', 'author', 'url', 'files', 'committed_at'];

    protected $casts = [
        'files' => 'array',
        'committed_at' => 'datetime',
    ];

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function shortSha(): string
    {
        return substr($this->sha, 0, 7);
    }

    public function firstLineOfMessage(): string
    {
        return strtok((string) $this->message, "\n") ?: '';
    }
}

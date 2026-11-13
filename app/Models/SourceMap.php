<?php

namespace App\Models;

use Database\Factories\SourceMapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SourceMap extends Model
{
    /** @use HasFactory<SourceMapFactory> */
    use HasFactory;

    protected $fillable = [
        'fault_project_id', 'release', 'artifact_name', 'artifact_hash', 'path', 'size', 'sha1',
    ];

    protected static function booted(): void
    {
        static::deleted(function (self $sourceMap): void {
            Storage::delete($sourceMap->path);
            Cache::forget(self::existenceCacheKey($sourceMap->fault_project_id, $sourceMap->release));
        });
    }

    public static function existenceCacheKey(int $projectId, string $release): string
    {
        return "source-maps:{$projectId}:".sha1($release);
    }

    public static function hashArtifactName(string $artifactName): string
    {
        return sha1($artifactName);
    }

    public static function storagePath(int $projectId, string $release, string $artifactName): string
    {
        return "source-maps/{$projectId}/".sha1($release).'/'.sha1($artifactName).'.map';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(FaultProject::class, 'fault_project_id');
    }
}

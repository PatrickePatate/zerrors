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

    protected $fillable = ['fault_project_id', 'version', 'notes', 'deployed_at'];

    protected $casts = [
        'deployed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(FaultProject::class, 'fault_project_id');
    }
}

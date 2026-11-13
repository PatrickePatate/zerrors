<?php

namespace App\Models;

use Database\Factories\FaultAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaultAttachment extends Model
{
    /** @use HasFactory<FaultAttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'fault_project_id', 'fault_event_id', 'event_id', 'filename', 'content_type',
        'attachment_type', 'size', 'path',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(FaultProject::class, 'fault_project_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FaultEvent::class, 'fault_event_id');
    }
}

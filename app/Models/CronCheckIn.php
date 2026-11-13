<?php

namespace App\Models;

use Database\Factories\CronCheckInFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronCheckIn extends Model
{
    /** @use HasFactory<CronCheckInFactory> */
    use HasFactory;

    protected $fillable = ['cron_monitor_id', 'check_in_id', 'status', 'duration', 'environment'];

    protected $casts = ['duration' => 'float'];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(CronMonitor::class, 'cron_monitor_id');
    }
}

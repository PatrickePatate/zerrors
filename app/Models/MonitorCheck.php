<?php

namespace App\Models;

use App\Enums\MonitorStatus;
use Database\Factories\MonitorCheckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorCheck extends Model
{
    /** @use HasFactory<MonitorCheckFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'monitor_id', 'status', 'response_time_ms', 'dns_time_ms',
        'connect_time_ms', 'ssl_time_ms', 'ttfb_ms', 'download_time_ms',
        'status_code', 'error_message', 'health_checks',
        'certificate_expires_at', 'certificate_error', 'checked_at',
    ];

    protected $casts = [
        'status' => MonitorStatus::class,
        'health_checks' => 'array',
        'certificate_expires_at' => 'datetime',
        'checked_at' => 'datetime',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}

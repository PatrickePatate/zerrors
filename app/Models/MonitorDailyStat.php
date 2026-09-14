<?php

namespace App\Models;

use Database\Factories\MonitorDailyStatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorDailyStat extends Model
{
    /** @use HasFactory<MonitorDailyStatFactory> */
    use HasFactory;

    protected $fillable = [
        'monitor_id', 'date', 'total_checks', 'up_count',
        'avg_response_time_ms', 'avg_dns_time_ms', 'avg_connect_time_ms',
        'avg_ssl_time_ms', 'avg_ttfb_ms', 'avg_download_time_ms',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function uptimePercentage(): float
    {
        if ($this->total_checks === 0) {
            return 0.0;
        }

        return round(($this->up_count / $this->total_checks) * 100, 2);
    }
}

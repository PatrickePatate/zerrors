<?php

namespace App\Models;

use Database\Factories\CronMonitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CronMonitor extends Model
{
    /** @use HasFactory<CronMonitorFactory> */
    use HasFactory;

    protected $fillable = [
        'fault_project_id', 'slug', 'name', 'schedule_type', 'schedule_value', 'schedule_unit',
        'checkin_margin', 'max_runtime', 'timezone', 'status', 'last_check_in_at', 'next_expected_at',
    ];

    protected $casts = [
        'checkin_margin' => 'integer',
        'max_runtime' => 'integer',
        'last_check_in_at' => 'datetime',
        'next_expected_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(FaultProject::class, 'fault_project_id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CronCheckIn::class);
    }

    public function scheduleLabel(): string
    {
        return match ($this->schedule_type) {
            'crontab' => (string) $this->schedule_value,
            'interval' => "every {$this->schedule_value} {$this->schedule_unit}(s)",
            default => 'unscheduled',
        };
    }
}

<?php

namespace App\Models;

use App\Enums\MonitorHttpMethod;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use Carbon\Carbon;
use Database\Factories\MonitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Monitor extends Model
{
    /** @use HasFactory<MonitorFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id', 'project_id', 'name', 'type', 'url', 'http_method', 'headers',
        'check_interval_minutes', 'timeout_seconds', 'expected_status_code',
        'check_certificate', 'certificate_expiry_warning_days',
        'is_active', 'current_status', 'last_checked_at', 'consecutive_failures',
    ];

    protected $casts = [
        'type' => MonitorType::class,
        'current_status' => MonitorStatus::class,
        'http_method' => MonitorHttpMethod::class,
        'is_active' => 'boolean',
        'check_certificate' => 'boolean',
        // Encrypted at rest, like FaultProject's secret_key/forward_dsn — these
        // headers commonly carry bearer tokens or basic-auth credentials for
        // the monitored endpoint.
        'headers' => 'encrypted:array',
        'last_checked_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(FaultProject::class, 'project_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(MonitorCheck::class);
    }

    public function dailyStats(): HasMany
    {
        return $this->hasMany(MonitorDailyStat::class);
    }

    /**
     * Percentage of checks in the trailing $hours window that were Up,
     * blending raw checks with compacted daily stats for any part of the
     * window that has already been rolled up (see CompactMonitorChecks).
     */
    public function uptimePercentage(int $hours): float
    {
        $since = now()->subHours($hours);

        $counts = $this->checks()
            ->where('checked_at', '>=', $since)
            ->selectRaw('count(*) as total, sum(case when status = ? then 1 else 0 end) as up_count', [MonitorStatus::Up->value])
            ->first();

        $total = (int) ($counts->total ?? 0);
        $upCount = (int) ($counts->up_count ?? 0);

        $dailyStats = $this->dailyStats()
            ->where('date', '>=', $since->toDateString())
            ->selectRaw('sum(total_checks) as total, sum(up_count) as up_count')
            ->first();

        $total += (int) ($dailyStats->total ?? 0);
        $upCount += (int) ($dailyStats->up_count ?? 0);

        if ($total === 0) {
            return 0.0;
        }

        return round(($upCount / $total) * 100, 2);
    }

    /**
     * The most recently observed certificate expiry, from the latest check
     * that managed to read one (a transient network failure on the most
     * recent check shouldn't blank out an otherwise-known expiry date).
     */
    public function latestCertificateExpiry(): ?Carbon
    {
        return $this->checks()
            ->whereNotNull('certificate_expires_at')
            ->orderByDesc('checked_at')
            ->first(['certificate_expires_at'])
            ?->certificate_expires_at;
    }

    public function isDue(): bool
    {
        if ($this->last_checked_at === null) {
            return true;
        }

        return $this->last_checked_at->lte(now()->subMinutes($this->check_interval_minutes));
    }
}

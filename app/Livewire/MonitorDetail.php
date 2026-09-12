<?php

namespace App\Livewire;

use App\Models\Monitor;
use App\Models\Organization;
use Livewire\Component;

class MonitorDetail extends Component
{
    public Organization $organization;

    public Monitor $monitor;

    public function mount(Organization $organization, Monitor $monitor): void
    {
        $this->organization = $organization;
        $this->monitor = $monitor;
    }

    public function render()
    {
        $checks = $this->monitor->checks()
            ->orderByDesc('checked_at')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        $recentChecks = $checks->slice(-50)->values();

        $chartChecks = $checks->map(fn ($check) => [
            'checked_at' => $check->checked_at->toIso8601String(),
            'response_time_ms' => $check->response_time_ms,
            'status' => $check->status->value,
        ]);

        $certificateExpiresAt = $this->monitor->check_certificate
            ? $this->monitor->latestCertificateExpiry()
            : null;

        // The most recent check that actually returned a parsed health_checks
        // payload — a transient failure on the very latest check (e.g. a
        // timeout) shouldn't blank out the last known set of check results.
        $latestHealthCheck = $checks->last(fn ($check) => $check->health_checks !== null);

        return view('livewire.monitor-detail', [
            'recentChecks' => $recentChecks,
            'chartChecks' => $chartChecks,
            'uptime24h' => $this->monitor->uptimePercentage(24),
            'uptime7d' => $this->monitor->uptimePercentage(24 * 7),
            'uptime30d' => $this->monitor->uptimePercentage(24 * 30),
            'certificateExpiresAt' => $certificateExpiresAt,
            'certificateDaysRemaining' => $certificateExpiresAt ? (int) now()->diffInDays($certificateExpiresAt, false) : null,
            'healthChecks' => $latestHealthCheck?->health_checks,
            'healthChecksCheckedAt' => $latestHealthCheck?->checked_at,
        ]);
    }
}

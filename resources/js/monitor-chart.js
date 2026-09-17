import ApexCharts from 'apexcharts';

// Backs the response-time area chart on the monitor detail page
// (resources/views/livewire/monitor-detail.blade.php). Colors mirror
// App\Enums\MonitorStatus::color() — green for up, red for down.

// Phase breakdown shown in the tooltip, in the order a request actually
// happens. Each check only carries the phases that apply to it (e.g.
// ssl_time_ms stays null for a plain HTTP target), so the tooltip skips
// whatever's missing rather than showing a stray "0ms" line.
const PHASES = [
    { key: 'dns_time_ms', label: 'DNS Lookup time', color: '#ec4899' },
    { key: 'connect_time_ms', label: 'TCP Connection time', color: '#14b8a6' },
    { key: 'ssl_time_ms', label: 'SSL Handshake', color: '#a855f7' },
    { key: 'ttfb_ms', label: 'Remote server processing', color: '#3b82f6' },
    { key: 'download_time_ms', label: 'Content download', color: '#f59e0b' },
];

// Ping checks have no DNS/connect/SSL/TTFB/download breakdown — there's just
// the one round-trip time — so they get a single "Response time" series
// instead of the stacked phase breakdown HTTP(S) and Laravel Health checks use.
const PING_PHASES = [{ key: 'response_time_ms', label: 'Response time', color: '#3b82f6' }];

export default function monitorChart(checks, monitorType) {
    return {
        chart: null,

        init() {
            // Guards against a second chart stacking inside the same container —
            // ApexCharts.render() appends rather than replaces, so if init() ever
            // fires twice on this element (e.g. a Vite HMR reload during
            // development) the old SVG must be torn down first.
            this.$el.innerHTML = '';

            const phases = monitorType === 'ping' ? PING_PHASES : PHASES;

            const timestamps = checks.map((check) => new Date(check.checked_at).getTime());
            const statuses = checks.map((check) => check.status);

            // One series per phase, stacked, so the layered fills show how much
            // of the total response time each phase actually took.
            const series = phases.map((phase) => ({
                name: phase.label,
                data: checks.map((check, index) => ({
                    x: timestamps[index],
                    y: check[phase.key] ?? null,
                })),
            }));

            this.chart = new ApexCharts(this.$el, {
                chart: {
                    type: 'area',
                    height: '100%',
                    stacked: true,
                    toolbar: { show: false },
                    fontFamily: 'inherit',
                },
                series,
                colors: phases.map((phase) => phase.color),
                stroke: { curve: 'smooth', width: 2 },
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.55, opacityTo: 0.15, stops: [0, 100] },
                },
                markers: {
                    size: 0,
                },
                xaxis: { type: 'datetime', labels: { datetimeUTC: false } },
                yaxis: {
                    title: { text: 'ms' },
                    labels: { formatter: (value) => (value === null ? '—' : Math.round(value)) },
                },
                legend: { show: true, position: 'top' },
                tooltip: {
                    shared: true,
                    custom: ({ dataPointIndex, w }) => {
                        const date = new Date(timestamps[dataPointIndex]).toLocaleString(undefined, {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                        });

                        if (statuses[dataPointIndex] === 'down') {
                            return `<div class="p-3 text-sm">
                                <div class="font-semibold mb-1">${date}</div>
                                <div>No response</div>
                            </div>`;
                        }

                        const phaseRows = w.config.series
                            .map((s, index) => ({ label: s.name, color: phases[index].color, value: s.data[dataPointIndex]?.y }))
                            .filter((phase) => phase.value !== null && phase.value !== undefined)
                            .map(
                                (phase) => `<div class="flex items-center gap-1.5">
                                    <span class="inline-block w-2 h-2 rounded-full" style="background-color: ${phase.color}"></span>
                                    <span>${phase.label}: ${Math.round(phase.value)}ms</span>
                                </div>`
                            )
                            .join('');

                        const total = w.config.series.reduce((sum, s) => sum + (s.data[dataPointIndex]?.y ?? 0), 0);

                        return `<div class="p-3 text-sm space-y-1">
                            <div class="font-semibold mb-1">${date}</div>
                            <div>Total: ${Math.round(total)}ms</div>
                            ${phaseRows}
                        </div>`;
                    },
                },
                noData: { text: 'No checks recorded yet.' },
            });

            this.chart.render();
        },

        destroy() {
            this.chart?.destroy();
        },
    };
}

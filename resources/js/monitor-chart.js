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

export default function monitorChart(checks) {
    return {
        chart: null,

        init() {
            // Guards against a second chart stacking inside the same container —
            // ApexCharts.render() appends rather than replaces, so if init() ever
            // fires twice on this element (e.g. a Vite HMR reload during
            // development) the old SVG must be torn down first.
            this.$el.innerHTML = '';

            const seriesData = checks.map((check) => ({
                x: new Date(check.checked_at).getTime(),
                y: check.response_time_ms,
                status: check.status,
                phases: PHASES.filter((phase) => check[phase.key] !== null && check[phase.key] !== undefined).map((phase) => ({
                    label: phase.label,
                    color: phase.color,
                    value: check[phase.key],
                })),
            }));

            this.chart = new ApexCharts(this.$el, {
                chart: {
                    type: 'area',
                    height: '100%',
                    toolbar: { show: false },
                    fontFamily: 'inherit',
                },
                series: [{ name: 'Response time', data: seriesData }],
                colors: ['#16a34a'],
                stroke: { curve: 'smooth', width: 2 },
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05 },
                },
                markers: {
                    size: 3,
                    colors: seriesData.map((point) => (point.status === 'down' ? '#dc2626' : '#16a34a')),
                    strokeWidth: 0,
                },
                xaxis: { type: 'datetime', labels: { datetimeUTC: false } },
                yaxis: {
                    title: { text: 'ms' },
                    labels: { formatter: (value) => (value === null ? '—' : Math.round(value)) },
                },
                tooltip: {
                    custom: ({ seriesIndex, dataPointIndex, w }) => {
                        const point = w.config.series[seriesIndex].data[dataPointIndex];
                        const date = new Date(point.x).toLocaleString(undefined, {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                        });

                        if (point.y === null) {
                            return `<div class="p-3 text-sm">
                                <div class="font-semibold mb-1">${date}</div>
                                <div>No response</div>
                            </div>`;
                        }

                        const phaseRows = point.phases
                            .map(
                                (phase) => `<div class="flex items-center gap-1.5">
                                    <span class="inline-block w-2 h-2 rounded-full" style="background-color: ${phase.color}"></span>
                                    <span>${phase.label}: ${Math.round(phase.value)}ms</span>
                                </div>`
                            )
                            .join('');

                        return `<div class="p-3 text-sm space-y-1">
                            <div class="font-semibold mb-1">${date}</div>
                            <div>Total: ${Math.round(point.y)}ms</div>
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

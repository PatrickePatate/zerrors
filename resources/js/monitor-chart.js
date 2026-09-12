import ApexCharts from 'apexcharts';

// Backs the response-time area chart on the monitor detail page
// (resources/views/livewire/monitor-detail.blade.php). Colors mirror
// App\Enums\MonitorStatus::color() — green for up, red for down.
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
                    y: { formatter: (value) => (value === null ? 'no response' : `${Math.round(value)} ms`) },
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

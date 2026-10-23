import ApexCharts from 'apexcharts';

// Backs the compact events-over-time bar chart on the issue page
// (resources/views/fault/issues/_detail.blade.php). Buckets come from
// IssueController::eventHistogram() as unix seconds; the bucket holding the
// currently displayed event is highlighted.

const BAR_COLOR = '#c7d2fe';
const CURRENT_COLOR = '#4f46e5';

export default function eventHistogramChart(buckets, currentTimestamp) {
    return {
        chart: null,

        init() {
            // ApexCharts.render() appends rather than replaces — see monitor-chart.js.
            this.$el.innerHTML = '';

            const colors = buckets.map((bucket) =>
                currentTimestamp !== null && currentTimestamp >= bucket.start && currentTimestamp < bucket.end
                    ? CURRENT_COLOR
                    : BAR_COLOR
            );

            const format = (seconds) =>
                new Date(seconds * 1000).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });

            this.chart = new ApexCharts(this.$el, {
                chart: {
                    type: 'bar',
                    height: '100%',
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    animations: { enabled: false },
                    sparkline: { enabled: false },
                    fontFamily: 'inherit',
                    parentHeightOffset: 0,
                },
                series: [{ name: 'Events', data: buckets.map((bucket) => ({ x: bucket.start * 1000, y: bucket.count })) }],
                colors,
                plotOptions: { bar: { distributed: true, columnWidth: '85%', borderRadius: 1 } },
                states: { hover: { filter: { type: 'darken', value: 0.85 } } },
                dataLabels: { enabled: false },
                legend: { show: false },
                grid: { show: false, padding: { top: -10, bottom: -6, left: 0, right: 0 } },
                xaxis: {
                    type: 'datetime',
                    labels: { datetimeUTC: false, style: { fontSize: '10px', colors: '#9ca3af' } },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    tooltip: { enabled: false },
                },
                yaxis: { show: false, min: 0 },
                tooltip: {
                    custom: ({ dataPointIndex }) => {
                        const bucket = buckets[dataPointIndex];

                        return `<div class="px-2 py-1 text-xs">
                            <div class="font-semibold">${bucket.count} event${bucket.count === 1 ? '' : 's'}</div>
                            <div class="text-gray-500">${format(bucket.start)} → ${format(bucket.end)}</div>
                        </div>`;
                    },
                },
            });

            this.chart.render();
        },

        destroy() {
            this.chart?.destroy();
        },
    };
}

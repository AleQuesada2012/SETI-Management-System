import { Chart, DoughnutController, ArcElement, BarController, BarElement, CategoryScale, LinearScale, Legend, Tooltip } from 'chart.js';

Chart.register(DoughnutController, ArcElement, BarController, BarElement, CategoryScale, LinearScale, Legend, Tooltip);

// type 'doughnut' shows proportions (RF_80/81); 'bar' ranks rows horizontally, one color per row (RF_82/83).
// The rows come from the element's data-series (components/chart.blade.php).
const register = () => window.Alpine.data('setiChart', (type = 'doughnut') => {
    let chart;
    let observer;
    return {
        init() {
            const series = JSON.parse(this.$el.dataset.series);
            const colors = () => {
                const style = getComputedStyle(this.$el);
                return series.map((row, index) => style.getPropertyValue(`--color-chart-${row.color ?? index % 5 + 1}`).trim());
            };
            const ink = () => getComputedStyle(this.$el).color;
            const axis = () => ({ ticks: { color: ink(), precision: 0 }, grid: { display: false } });
            chart = new Chart(this.$refs.canvas, {
                type,
                data: { labels: series.map(row => row.label), datasets: [{ data: series.map(row => row.count), backgroundColor: colors(), borderWidth: 0 }] },
                options: {
                    responsive: true, maintainAspectRatio: false, animation: false,
                    // Long names are shortened on the axis; the tooltip and the page's table keep them whole.
                    ...(type === 'bar' ? { indexAxis: 'y', layout: { padding: { left: 8 } }, scales: { x: axis(), y: { ...axis(), ticks: { color: ink(), callback(value) {
                        const label = this.getLabelForValue(value);
                        return label.length > 18 ? `${label.slice(0, 17)}…` : label;
                    } } } } } : {}),
                    plugins: { legend: { display: type !== 'bar', position: 'bottom', labels: { color: ink() } } },
                },
            });
            observer = new MutationObserver(() => {
                chart.data.datasets[0].backgroundColor = colors();
                chart.options.plugins.legend.labels.color = ink();
                if (type === 'bar') {
                    chart.options.scales.x.ticks.color = ink();
                    chart.options.scales.y.ticks.color = ink();
                }
                chart.update('none');
            });
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'style'] });
        },
        destroy() {
            observer?.disconnect();
            chart?.destroy();
        },
    };
});

if (window.Alpine) register();
else document.addEventListener('alpine:init', register, { once: true });

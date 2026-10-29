import { Chart, DoughnutController, ArcElement, Legend, Tooltip } from 'chart.js';

Chart.register(DoughnutController, ArcElement, Legend, Tooltip);

const register = () => window.Alpine.data('setiChart', (series) => {
    let chart;
    let observer;
    return {
        init() {
            const colors = () => {
                const style = getComputedStyle(this.$el);
                return series.map((row, index) => style.getPropertyValue(`--color-chart-${row.color ?? index % 5 + 1}`).trim());
            };
            chart = new Chart(this.$refs.canvas, {
                type: 'doughnut',
                data: { labels: series.map(row => row.label), datasets: [{ data: series.map(row => row.count), backgroundColor: colors(), borderWidth: 0 }] },
                options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { position: 'bottom', labels: { color: getComputedStyle(this.$el).color } } } },
            });
            observer = new MutationObserver(() => {
                chart.data.datasets[0].backgroundColor = colors();
                chart.options.plugins.legend.labels.color = getComputedStyle(this.$el).color;
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

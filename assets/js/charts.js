(function (window) {
    'use strict';

    window.DokenOxCharts = window.DokenOxCharts || {
        renderLineChart(canvas, labels, dataset) {
            if (!window.Chart || !canvas) {
                return;
            }

            return new window.Chart(canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Sales',
                            data: dataset,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.15)',
                            tension: 0.4,
                            fill: true,
                        },
                    ],
                },
                options: {
                    plugins: {
                        legend: { display: false },
                    },
                    scales: {
                        y: { beginAtZero: true },
                    },
                },
            });
        },
    };
})(window);


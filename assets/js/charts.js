/**
 * SAMAPE - Charts JavaScript
 * Handles charts and data visualization on the dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
    // Service Orders Status Chart
    const serviceOrdersCtx = document.getElementById('serviceOrdersChart');
    if (serviceOrdersCtx) {
        fetch('/api/service_orders.php?action=status_counts')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    createServiceOrdersChart(serviceOrdersCtx, data.counts);
                } else {
                    console.error('Error fetching service orders data:', data.message);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
            });
    }
    
    // Monthly Financial Chart
    const financialCtx = document.getElementById('financialChart');
    if (financialCtx) {
        fetch('/api/financial.php?action=monthly_data')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    createFinancialChart(financialCtx, data.months);
                } else {
                    console.error('Error fetching financial data:', data.message);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
            });
    }
});

/**
 * Create a pie chart for service order status distribution
 * @param {HTMLElement} canvas - The canvas element for the chart
 * @param {Object} counts - Object containing counts for each status
 */
function createServiceOrdersChart(canvas, counts) {
    new Chart(canvas, {
        type: 'pie',
        data: {
            labels: ['Abertas', 'Em Andamento', 'Concluídas', 'Canceladas'],
            datasets: [{
                data: [
                    counts.aberta || 0,
                    counts.em_andamento || 0,
                    counts.concluida || 0,
                    counts.cancelada || 0
                ],
                backgroundColor: [
                    '#0d6efd', // Primary - Abertas
                    '#ffc107', // Warning - Em Andamento
                    '#198754', // Success - Concluídas
                    '#dc3545'  // Danger - Canceladas
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                title: {
                    display: true,
                    text: 'Distribuição de Ordens de Serviço por Status',
                    font: {
                        size: 16
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

/**
 * Create a bar chart for monthly financial data
 * @param {HTMLElement} canvas - The canvas element for the chart
 * @param {Array} months - Array of objects with monthly financial data
 */
function createFinancialChart(canvas, months) {
    const labels = months.map(m => m.month);
    const incomeData = months.map(m => m.income);
    const expenseData = months.map(m => m.expense);
    const netData = months.map(m => m.income - m.expense);
    
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Receitas',
                    data: incomeData,
                    backgroundColor: 'rgba(25, 135, 84, 0.5)',
                    borderColor: 'rgb(25, 135, 84)',
                    borderWidth: 1,
                    order: 2
                },
                {
                    label: 'Despesas',
                    data: expenseData,
                    backgroundColor: 'rgba(220, 53, 69, 0.5)',
                    borderColor: 'rgb(220, 53, 69)',
                    borderWidth: 1,
                    order: 1
                },
                {
                    label: 'Lucro/Prejuízo',
                    data: netData,
                    type: 'line',
                    backgroundColor: 'rgba(13, 110, 253, 0.5)',
                    borderColor: 'rgb(13, 110, 253)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgb(13, 110, 253)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgb(13, 110, 253)',
                    pointRadius: 4,
                    order: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Mês'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Valor (R$)'
                    },
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR');
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom'
                },
                title: {
                    display: true,
                    text: 'Desempenho Financeiro Mensal',
                    font: {
                        size: 16
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const value = context.raw;
                            return `${label}: R$ ${value.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
                        }
                    }
                }
            }
        }
    });
}

/**
 * Create a line chart for the trend of service orders over time
 * @param {HTMLElement} canvas - The canvas element for the chart
 * @param {Array} data - Array of objects with service order data by period
 */
function createServiceOrdersTrendChart(canvas, data) {
    const labels = data.map(d => d.period);
    const openedData = data.map(d => d.opened);
    const closedData = data.map(d => d.closed);
    
    new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'OS Abertas',
                    data: openedData,
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderColor: 'rgb(13, 110, 253)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'OS Fechadas',
                    data: closedData,
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    borderColor: 'rgb(25, 135, 84)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Período'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Quantidade de OS'
                    },
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    position: 'bottom'
                },
                title: {
                    display: true,
                    text: 'Tendência de Ordens de Serviço por Período',
                    font: {
                        size: 16
                    }
                }
            }
        }
    });
}

/**
 * Create a doughnut chart for client distribution by service orders
 * @param {HTMLElement} canvas - The canvas element for the chart
 * @param {Array} data - Array of objects with client service order data
 */
function createClientDistributionChart(canvas, data) {
    // Sort data by order count (descending)
    data.sort((a, b) => b.count - a.count);
    
    // Take top 5 clients, aggregate the rest as "Others"
    let topClients = data.slice(0, 5);
    let otherClients = data.slice(5);
    
    let labels = topClients.map(c => c.name);
    let counts = topClients.map(c => c.count);
    
    // Add "Others" category if there are more than 5 clients
    if (otherClients.length > 0) {
        const otherCount = otherClients.reduce((sum, client) => sum + client.count, 0);
        labels.push('Outros');
        counts.push(otherCount);
    }
    
    // Generate colors for each segment
    const colors = [
        '#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545',
        '#fd7e14', '#ffc107', '#28a745', '#20c997', '#17a2b8'
    ];
    
    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: colors.slice(0, labels.length),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                title: {
                    display: true,
                    text: 'Distribuição de OS por Cliente',
                    font: {
                        size: 16
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} OS (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// charts.js (Modified content)

let incomeExpensesChartInstance = null;
let expensesChartInstance = null;
let goalChartInstance = null;
let spendingBreakdownChartInstance = null; // NEW: Declare chart instance

// Declare context variables globally but do not initialize them yet
let ctx = null;
let expensesCtx = null;
let goalCtx = null;
let spendingBreakdownCtx = null; // NEW: Declare context variable

function fetchData(month = '') {
  fetch(`charts.php?month=${month}`)
    .then((response) => {
      if (!response.ok) {
        return response.text().then(text => { throw new Error(text) });
      }
      return response.json();
    })
    .then((data) => {
      if (data.error) {
        console.error("Error from charts.php:", data.error);
        return;
      }
      createOrUpdateIncomeExpenseChart(data.income_expense);
      createOrUpdateExpensesChart(data.expenses_by_category);
      createOrUpdateGoalChart(data.config, data.income_expense, data.monthly_savings); // Pass monthly savings
      createOrUpdateSpendingBreakdownChart(data.spending_breakdown); // NEW: Call for new chart

      updateTopRowStats(data.config, data.monthly_total_expenses, data.income_expense);
      updateMaxExpenseCategory(data.expenses_by_category); // NEW: Update max expense category text
    })
    .catch((error) => console.error("Error fetching data:", error));
  loadRecentTransactions(month); 
}

document.addEventListener('DOMContentLoaded', () => {
  // Initialize context variables INSIDE DOMContentLoaded to ensure elements exist
  ctx = document.getElementById('incomeExpensesChart')?.getContext('2d'); // Use optional chaining
  expensesCtx = document.getElementById('expensesChart')?.getContext('2d'); // Use optional chaining
  goalCtx = document.getElementById('goalChart')?.getContext('2d'); // Use optional chaining
  spendingBreakdownCtx = document.getElementById('spendingBreakdownChart')?.getContext('2d'); // NEW: Initialize new context

  fetchData();
  
  // Call the function to load recent transactions
  loadRecentTransactions(); // NEW LINE

  document.getElementById('monthSelect')?.addEventListener('change', function () {
    fetchData(this.value);
  });
});

function destroyChart(chartInstance) {
    if (chartInstance) {
        chartInstance.destroy();
    }
}

// Helper function to show/hide "No data" message
function toggleNoDataMessage(chartId, hasData) {
    const chartCanvas = document.getElementById(chartId);
    if (!chartCanvas) return; // Ensure canvas exists

    let noDataMessage = document.getElementById(`${chartId}-no-data`);

    // If no-data message element doesn't exist, create it
    if (!noDataMessage) {
        noDataMessage = document.createElement('div');
        noDataMessage.id = `${chartId}-no-data`;
        noDataMessage.className = 'no-data-message'; // Add a class for styling
        noDataMessage.textContent = '';
        // Insert it after the canvas or wherever appropriate
        chartCanvas.parentNode.insertBefore(noDataMessage, chartCanvas.nextSibling);
    }

    if (hasData) {
        chartCanvas.style.display = 'block';
        if (noDataMessage) noDataMessage.style.display = 'none';
    } else {
        chartCanvas.style.display = 'none';
        if (noDataMessage) noDataMessage.style.display = 'block';
    }
}

function createOrUpdateIncomeExpenseChart(chartData) {
    destroyChart(incomeExpensesChartInstance);

    const labels = chartData.map(item => item.dates);
    const incomeData = chartData.map(item => parseFloat(item.income));
    const expenseData = chartData.map(item => parseFloat(item.expense));

    // Check if there is any data to display
    const hasData = incomeData.some(val => val > 0) || expenseData.some(val => val > 0);
    const noDataMessageElement = document.getElementById('incomeExpensesChart-no-data');
    if (noDataMessageElement) {
        noDataMessageElement.textContent = 'No income or expense data for the selected period.';
    }
    toggleNoDataMessage('incomeExpensesChart', hasData);

    if (!hasData) {
        return; // Exit if no data
    }
    
    if (ctx) { // Check if ctx is defined
        incomeExpensesChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Income',
                        data: incomeData,
                        backgroundColor: '#2ecc71',
                        borderRadius: 4,
                        order: 2
                    },
                    {
                        label: 'Expense',
                        data: expenseData,
                        backgroundColor: '#e74c3c',
                        borderRadius: 4,
                        order: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        align: 'end'
                    },
                    tooltip: {
                        yAlign: 'bottom',
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += '₱' + context.parsed.y.toLocaleString();
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => `₱${value.toLocaleString()}`
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
}

function createOrUpdateExpensesChart(expenseData) {
    destroyChart(expensesChartInstance);

    const labels = expenseData.map(item => item.category);
    const data = expenseData.map(item => parseFloat(item.total));

    // Check if there is any data to display
    const hasData = data.some(val => val > 0);
    const noDataMessageElement = document.getElementById('expensesChart-no-data');
    if (noDataMessageElement) {
        noDataMessageElement.textContent = 'No expense data by category for the selected period.';
    }
    toggleNoDataMessage('expensesChart', hasData);

    if (!hasData) {
        return; // Exit if no data
    }
    
    if (expensesCtx) { // Check if expensesCtx is defined
        expensesChartInstance = new Chart(expensesCtx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [
                    {
                        data: data,
                        backgroundColor: ['#e74c3c', '#f1c40f', '#3498db', '#9b59b6', '#2ecc71'],
                        borderWidth: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => `${ctx.label}: ₱${ctx.raw.toLocaleString()}`
                        }
                    }
                }
            }
        });
    }
}

// Custom Chart.js Plugin for drawing text in the center of a doughnut chart
const centerTextPlugin = {
    id: 'centerText',
    beforeDraw: function(chart) {
        if (chart.options.plugins.centerText && chart.options.plugins.centerText.text) {
            const {ctx, width, height} = chart;
            const text = chart.options.plugins.centerText.text;
            const font = chart.options.plugins.centerText.font || '24px Poppins';
            const color = chart.options.plugins.centerText.color || '#34495e';

            ctx.restore();
            const fontSize = (height / 120).toFixed(2); // Responsive font size
            ctx.font = `bold ${fontSize}em Poppins`; // Use bold for the percentage
            ctx.textBaseline = 'middle';
            ctx.fillStyle = color;

            const textX = Math.round((width - ctx.measureText(text).width) / 2);
            const textY = height / 2; // Center vertically

            ctx.fillText(text, textX, textY);
            ctx.save();
        }
    }
};

// Register the plugin globally once
Chart.register(centerTextPlugin);

function createOrUpdateGoalChart(configData, incomeExpenseData, monthlySavings) {
    destroyChart(goalChartInstance);

    const saved = monthlySavings; // Use the new monthly savings value
    const goal = parseFloat(configData.monthly_saving || 0); // Ensure it's a number
    let percentCloser = 0;

    if (goal > 0) {
        percentCloser = ((saved / goal) * 100).toFixed(0);
        if (percentCloser > 100) percentCloser = 100; // Cap at 100%
    }

    const remaining = goal - saved;

    // Check if there is any data to display for the goal
    const hasData = goal > 0 || saved >= 0;
    toggleNoDataMessage('goalChart', hasData);

    if (!hasData) {
        const goalStatusTextElement = document.getElementById('goalStatusText');
        if (goalStatusTextElement) {
            goalStatusTextElement.textContent = `Set a saving goal to track your progress!`;
        }
        return; // Exit if no data
    }
    
    if (goalCtx) {
        goalChartInstance = new Chart(goalCtx, {
            type: 'doughnut',
            data: {
                labels: ['Saved', 'Remaining'],
                datasets: [
                    {
                        data: [saved, remaining > 0 ? remaining : 0],
                        backgroundColor: ['#007bff', '#e0e0e0'],
                        borderWidth: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    centerText: {
                        text: `${percentCloser}%`,
                        font: 'bold 24px Poppins',
                        color: '#34495e'
                    }
                }
            },
            plugins: [centerTextPlugin]
        });
    }

    const goalStatusTextElement = document.getElementById('goalStatusText');
    const goalStatusTextElement2 = document.getElementById('goalStatusText2');
    if (goalStatusTextElement) {
        goalStatusTextElement.textContent = `You are ${percentCloser}% closer to your goal`;
        goalStatusTextElement2.textContent = `Monthly Saving Goal: ₱${goal}`;
    }
}

// NEW FUNCTION: Update max expense category text
function updateMaxExpenseCategory(expensesByCategory) {
    if (expensesByCategory.length === 0) {
        document.getElementById('maxExpenseCategoryText').textContent = 'No expenses recorded this month.';
        return;
    }

    let maxExpense = { category: '', total: 0 };

    expensesByCategory.forEach(expense => {
        if (parseFloat(expense.total) > maxExpense.total) {
            maxExpense = { category: expense.category, total: parseFloat(expense.total) };
        }
    });

    document.getElementById('maxExpenseCategoryText').textContent = `Most spent on: ${maxExpense.category} - ₱${maxExpense.total.toLocaleString()}`;
}

function createOrUpdateSpendingBreakdownChart(chartData) {
    destroyChart(spendingBreakdownChartInstance);

    const labels = chartData.map(item => item.category);
    const data = chartData.map(item => parseFloat(item.total));

    // Check if there is any data to display
    const hasData = data.some(val => val > 0);
    const noDataMessageElement = document.getElementById('spendingBreakdownChart-no-data');
    if (noDataMessageElement) {
        noDataMessageElement.textContent = 'No spending breakdown data for the selected period.';
    }
    toggleNoDataMessage('spendingBreakdownChart', hasData);

    if (!hasData) {
        console.error("One or more budget progress elements not found.");
        return; // Exit if no data
    }

    if (spendingBreakdownCtx) {
        spendingBreakdownChartInstance = new Chart(spendingBreakdownCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Amount Spent',
                    data: data,
                    backgroundColor: '#3498db',
                    borderColor: '#217dbb',
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'x',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ₱${context.raw.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => `₱${value.toLocaleString()}`
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
}

// NEW FUNCTION: Update Budget Progress Bar
function updateTopRowStats(configData, monthlyExpenses, incomeExpenseData) {
  const incomeElement = document.getElementById('totalFunds');
  const expenseElement = document.getElementById('totalMonthlyExpenses');
  const savingsElement = document.getElementById('totalMonthlySavings'); // Added savings element
  const loansElement = document.getElementById('totalMonthlyLoans'); // Added loans element

  const totalIncome = parseFloat(configData.balance || 0);
  const totalExpenses = parseFloat(monthlyExpenses || 0);
  const totalSavings = parseFloat(configData.savings || 0); // Get savings from config
  const totalLoans = parseFloat(configData.loan || 0); // Assuming total_loans exists in configData

  if (incomeElement) {
    incomeElement.textContent = `₱${totalIncome.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }

  if (expenseElement) {
    expenseElement.textContent = `₱${totalExpenses.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }

  if (savingsElement) {
    savingsElement.textContent = `₱${totalSavings.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }

  if (loansElement) {
    loansElement.textContent = `₱${totalLoans.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }
}

// NEW FUNCTION: Load Recent Transactions into the table
async function loadRecentTransactions(month = '') { // Added month parameter
    const tableBody = document.getElementById('recentTransactionsTableBody');
    if (!tableBody) {
        console.error('Recent transactions table body not found!');
        return;
    }

    // Show a loading message
    tableBody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px;">Loading transactions...</td></tr>';

    try {
        // Ensure the path to your PHP file is correct and pass the month parameter
        const response = await fetch(`get_recent_transactions.php?month=${month}`); // MODIFIED THIS LINE
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const transactions = await response.json();

        tableBody.innerHTML = ''; // Clear loading message

        if (transactions.error) {
            tableBody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: red; padding: 20px;">Error: ${transactions.error}</td></tr>`;
            return;
        }

        if (transactions.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px;">No recent transactions found for this period.</td></tr>'; // Updated no data message
            return;
        }

        transactions.forEach((transaction, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${index + 1}</td>
                <td>${transaction.category}</td>
                <td>₱${parseFloat(transaction.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                <td>${transaction.date_issued_formatted}</td>
                <td>${transaction.type}</td>
            `;
            tableBody.appendChild(row);
        });

    } catch (error) {
        console.error('Failed to load recent transactions:', error);
        tableBody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: red; padding: 20px;">Failed to load transactions. Please check console.</td></tr>`;
    }
}

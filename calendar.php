<?php
include 'db_connect.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: log-in.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dynamic Calendar</title>
  <link rel="stylesheet" href="style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    /* General day styling for the calendar grid */
    .calendar-grid .day {
      padding: 10px;
      border: 1px solid #ddd;
      text-align: center;
      cursor: pointer;
      transition: background-color 0.2s ease;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 80px; /* Ensure a minimum height for days */
      position: relative;
    }

    /* Styling for empty days in the grid */
    .calendar-grid div:empty {
      background-color: #f8f8f8;
      border: 1px solid #eee;
      cursor: default;
    }

    /* Hover effect for days */
    .calendar-grid .day:hover:not(.empty):not(.selected-day) {
      filter: brightness(95%); /* Slightly darken on hover */
    }

    /* Current day styling */
    .day.current-day {
      background-color: #e0f7fa;
      border-color: #00bcd4;
      color: #1D2A62;
      font-weight: bold;
    }

    /* Selected day styling - ensures it overrides heatmap colors */
    .day.selected-day {
      background-color: #007bff !important; /* Blue for selected */
      color: white !important;
      border: 2px solid #0056b3; /* Darker border for selected day */
      box-shadow: 0 0 5px rgba(0, 0, 0, 0.3); /* Subtle shadow for selected day */
    }

    /* Selected current day styling */
    .day.selected-day.current-day {
        background-color: #00bcd4 !important; /* Example: Selected current day */
        color: white !important;
    }

    /* HEATMAP STYLES */
    .day.level-0 { background-color: #ffffff; /* No transactions */ }
    .day.level-1 { background-color: #cfe2f3; /* Light blue, 1-2 transactions */ }
    .day.level-2 { background-color: #9fc5e8; /* Medium blue, 3-5 transactions */ }
    .day.level-3 { background-color: #6fa8dc; /* Darker blue, 6-9 transactions */ }
    .day.level-4 { background-color: #3d85c6; /* Darkest blue, 10+ transactions */ }

    /* TRANSACTION AMOUNT COLORS */
    .income-amount {
        color: #28a745; /* Green */
        font-weight: bold;
        flex-shrink: 0;
    }

    .expense-amount {
        color: #dc3545; /* Red */
        font-weight: bold;
        flex-shrink: 0;
    }

    .savings-amount {
        color: #007bff; /* Blue */
        font-weight: bold;
        flex-shrink: 0;
    }

    /* Styles for individual transaction items in the list */
    .transaction-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px dashed #e0e0e0;
        font-size: 0.9rem;
    }

    .transaction-item:last-child {
        border-bottom: none; /* No border for the last item */
    }

    .transaction-details {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        flex-grow: 1;
        margin-right: 10px;
    }

    .transaction-title {
        font-weight: 600;
        color: #333;
    }

    .transaction-category {
        font-size: 0.8em;
        color: #666;
    }

    .no-transactions, .error-message {
      text-align: center;
      color: #777;
      margin-top: 20px;
    }

    .due-dot {
      position: absolute;
      bottom: 6px;
      right: 6px;
      width: 8px;
      height: 8px;
      background-color: red !important;
      border-radius: 50%;
    }
  </style>
</head>
<body>
<div class="page">
  <div class="sidebarspace"><?php include 'sidebar.php'?> </div>
  <div class="calendar-wrapper-container">
    <h2 class="calendar-title">Calendar</h2>
    <div class="calendar-main-box">
      <div class="calendar-box">
        <div class="calendar-header">
          <button id="prev">&lt;</button>
          <span id="month-year"></span>
          <button id="next">&gt;</button>
        </div>
        <div class="calendar-days">
          <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
        </div>
        <div class="calendar-grid" id="calendar-days-grid"></div>
      </div>
      <div class="transaction-box">
        <div class="transaction-header" id="transaction-date-display">
          Select a date to view transactions
        </div>
        <div class="transaction-body">
          <div id="transactions-list">
            <p class="no-transactions">No transactions for this date.</p>
          </div>
          <a href="transaction.php" id="add-transaction-button" class="add-btn">Add Transaction</a>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
const calendarDaysGrid = document.getElementById('calendar-days-grid');
const monthYearSpan = document.getElementById('month-year');
const prevBtn = document.getElementById('prev');
const nextBtn = document.getElementById('next');
const transactionDateDisplay = document.getElementById('transaction-date-display');
const transactionsList = document.getElementById('transactions-list');
const addTransactionButton = document.getElementById('add-transaction-button');

let currentDate = new Date();
let selectedDate = null;
let selectedDayDiv = null;

async function getLoanDueDates(year, month) {
  const response = await fetch(`fetch_loan_dues.php?year=${year}&month=${month + 1}`);
  return await response.json();
}

async function getTransactionCounts(year, month) {
  try {
    const response = await fetch(`fetch_transaction_counts.php?year=${year}&month=${month + 1}`);
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    return await response.json();
  } catch (error) {
    console.error('Failed to fetch transaction counts:', error);
    return {};
  }
}

async function renderCalendar() {
  calendarDaysGrid.innerHTML = '';
  transactionsList.innerHTML = '<p class="no-transactions">No transactions for this date.</p>';
  addTransactionButton.href = 'transaction.php';
  transactionDateDisplay.textContent = 'Select a date to view transactions';

  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();
  monthYearSpan.textContent = `${currentDate.toLocaleString('default', { month: 'long' })} ${year}`;

  const transactionCounts = await getTransactionCounts(year, month);
  const loanDueMap = await getLoanDueDates(year, month); // now an object: { "YYYY-MM-DD": [{name, amount}] }
  const dueDates = Object.keys(loanDueMap); // list of dates

  const dueDateSet = new Set(dueDates); // for fast checking


  const firstDayOfMonth = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  for (let i = 0; i < firstDayOfMonth; i++) {
    const emptyDiv = document.createElement('div');
    emptyDiv.classList.add('empty');
    calendarDaysGrid.appendChild(emptyDiv);
  }

  for (let day = 1; day <= daysInMonth; day++) {
    const dayDiv = document.createElement('div');
    dayDiv.textContent = day;
    dayDiv.classList.add('day');
    const fullDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    dayDiv.dataset.fullDate = fullDate;

    const today = new Date();
    if (day === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
      dayDiv.classList.add('current-day');
    }

    const count = transactionCounts[day] || 0;
    let level = 0;
    if (count > 0 && count <= 2) level = 1;
    else if (count > 2 && count <= 5) level = 2;
    else if (count > 5 && count <= 9) level = 3;
    else if (count >= 10) level = 4;
    dayDiv.classList.add(`level-${level}`);

    if (dueDateSet.has(fullDate)) {
    const dot = document.createElement('div');
    dot.classList.add('due-dot');

    // Tooltip with name and amount
    const tooltipText = loanDueMap[fullDate]
      .map(loan => `Loan: ${loan.name}\nAmount: ₱${loan.amount}`)
      .join('\n\n');

    dot.setAttribute('data-tooltip', tooltipText);
    dayDiv.appendChild(dot);
  }


    dayDiv.addEventListener('click', () => {
      if (selectedDayDiv) selectedDayDiv.classList.remove('selected-day');
      dayDiv.classList.add('selected-day');
      selectedDayDiv = dayDiv;
      selectedDate = fullDate;
      displayTransactionsForDate(selectedDate);
    });

    calendarDaysGrid.appendChild(dayDiv);
  }
}

async function displayTransactionsForDate(date) {
  transactionDateDisplay.textContent = `Transactions for ${new Date(date).toDateString()}`;
  transactionsList.innerHTML = '<p>Loading transactions...</p>';
  addTransactionButton.href = `transaction.php?date=${date}`;

  try {
    const res = await fetch(`Workspace_transaction_by_date.php?date=${date}`);
    const data = await res.json();
    transactionsList.innerHTML = '';

    if (data.error) {
      transactionsList.innerHTML = `<p class="error-message">Error: ${data.error}</p>`;
    } else if (data.length === 0) {
      transactionsList.innerHTML = '<p class="no-transactions">No transactions for this date.</p>';
    } else {
      data.forEach(transaction => {
        const transactionDiv = document.createElement('div');
        transactionDiv.classList.add('transaction-item');

        let amountClass = '';
        const type = transaction.type.toLowerCase();
        if (type === 'income') amountClass = 'income-amount';
        else if (type === 'expense') amountClass = 'expense-amount';
        else if (type === 'savings') amountClass = 'savings-amount';

        transactionDiv.innerHTML = `
          <div class="transaction-details">
            <span class="transaction-title">${transaction.title}</span>
            <span class="transaction-category">(${transaction.category})</span>
          </div>
          <span class="${amountClass}">₱${parseFloat(transaction.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
        `;
        transactionsList.appendChild(transactionDiv);
      });
    }
  } catch (error) {
    console.error('Error fetching transactions:', error);
    transactionsList.innerHTML = '<p class="error-message">Failed to load transactions.</p>';
  }
}

prevBtn.addEventListener('click', () => {
  currentDate.setMonth(currentDate.getMonth() - 1);
  renderCalendar();
});

nextBtn.addEventListener('click', () => {
  currentDate.setMonth(currentDate.getMonth() + 1);
  renderCalendar();
});

document.addEventListener('DOMContentLoaded', () => {
  renderCalendar();
});
</script>
</body>
<?php include 'chatbot_widget.php'; ?>
</html>
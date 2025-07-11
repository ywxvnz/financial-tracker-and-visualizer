<?php include 'db_connect.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: log-in.php"); // Redirect if not logged in
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&amp;display=swap" rel="stylesheet" />
  <title>Finance Dashboard</title>
  <style>
    /* Existing styles remain unchanged */

    .dashboard {
      padding: 3vb;
      width: 100%;
    }

    /* Container for H1 and dashboard2 to align them horizontally */
    .dashboard-header-row {
      display: block;
      align-items: center;
      justify-content: space-between;
      background: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .dashboard2 {
      padding-top: 10px;
      padding-right: 0;
      padding-left: 0;
      display: flex;
      align-items: center;
      text-align: justify;
      -ms-text-justify: distribute-all-lines;
      text-justify: distribute-all-lines;
    }

    h1 {
      font-size: 28px;
      font-weight: 600;
      color: #1d2a62;
      margin-bottom: 0;
      margin-top: 0;
      flex-shrink: 0;
    }

    .grid-container {
      display: grid;
      grid-template-areas:
        "recent-transactions recent-transactions goal"
        "expenses chart chart"
        "spending-breakdown spending-breakdown budget-progress";
      grid-template-columns: 1.5fr 1.5fr 1fr;
      grid-gap: 20px;
      margin-top: 20px;
    }

    .spending-breakdown{
      max-height:50vh;
      padding-bottom: 20px;
      margin-bottom: 20px;
    }
    
    .card {
      background: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);

      font-weight: 600;
      transition: transform 0.2s ease;
    }

    .card:hover {
        transform: translateY(-4px);
    }

    .card h2 {
      margin-top: 0;
      font-size: 20px;
      margin-bottom: 10px;
      color: #1d2a62;
      font-weight: 600;
    }

    h3{
      font-size: 16px;
      font-weight: 600;
      color:  #555555;
      margin-bottom: 7px;
    }
    .card h4 {
      font-size: 16px;
      margin-top: 10px;
      color: #1d2a62;
      font-weight: 400;
    }

    h2{
      font-size: 20px;
      color: #1d2a62;
      font-weight: 600;
      margin: 0;
    }

    .chart {
      grid-area: chart;
      max-height: 500px;
      min-height: 280px;
      padding-bottom: 60px;
      position: relative;
    }

    .chart canvas {
      width: 100% !important;
      height: 100% !important;
    }

    h4#budgetUsedText,
    h4#goalStatusText,
    h4#goalStatusText2 {
        text-align: center;
    }

    
    .recent-transactions{
      grid-area: recent-transactions;
      min-height: 30vh;
      max-height: 50vh;
      display: flex;
      flex-direction: column;
    }
    .recent-transactions .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0px 0px 15px 0px;
        border-bottom: 1px solid #f0f0f0;
        margin-bottom: 10px;
    }

    .recent-transactions .card-header h2 {
        font-size: 18px;
        font-weight: 600;
        color: #34495e;
        margin: 0;
    }

    .recent-transactions .card-body {
        flex-grow: 1;
        overflow-y: auto;
        padding: 0;
    }

    .recent-transactions-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin-top: 0;
    }

    .recent-transactions-table thead th {
        text-align: left;
        padding: 15px 20px;
        font-size: 14px;
        font-weight: 500;
        color: #8898aa;
        text-transform: uppercase;
        position: sticky;
        top: 0;
        background-color: #ffffff;
        z-index: 1;
    }

    .recent-transactions-table thead th:first-child,
    .recent-transactions-table tbody td:first-child {
        width: 10%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .recent-transactions-table tbody td {
        padding: 15px 20px;
        font-size: 14px;
        font-weight: 400;
        color: #34495e;
        border-bottom: 1px solid #f8f8f8;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        text-align: left;
    }

    .recent-transactions-table tbody tr:last-child td {
        border-bottom: none;
    }

    .recent-transactions-table tbody tr:nth-child(even) {
        background-color: #fcfcfc;
    }

    .recent-transactions-table tbody td:nth-child(3) {
      text-align: center;
      font-weight: 500;
      width: 25%;
    }
    .recent-transactions-table thead th:nth-child(3){
      text-align: center;
    }
    .recent-transactions-table tbody td:nth-child(4) {
      text-align: center;
      font-weight: 500;
      width: 35%;
    }
    .recent-transactions-table thead th:nth-child(4){
      text-align: center;
    }
    .recent-transactions-table thead th:nth-child(5){
        text-align: right;
        padding-right: 60px;
    }
    .recent-transactions-table tbody td:nth-child(5) {
        text-align: right;
        padding-right: 48px;
    }

    .goal {
      grid-area: goal;
      flex-grow: 1;
      min-height: 280px;
    }

    .expenses {
      grid-area: expenses;
      min-height: 220px;
      position: relative;
      padding-bottom: 60px;
    }

    /* New text element below expenses chart */
    #maxExpenseCategoryText {
      position: absolute;
      bottom: 10px;
      width: 100%;
      text-align: center;
      font-weight: 500;
      font-size: 0.95rem;
      color: #1d2a62;
      user-select: none;
      pointer-events: none;
    }

    .expenses-chart-wrapper {
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .expenses-chart-wrapper canvas {
      max-width: 100%;
      max-height: 100%;
    }
    .goal-chart-wrapper {
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .goal-chart-wrapper canvas {
      max-width: 70%;
      max-height: 100%;
    }

    /* Styles for messaging divs */
    div#expensesChart-no-data,
    div#incomeExpensesChart-no-data,
    div#spendingBreakdownChart-no-data {
      font-size: 16px;
      font-weight: 330;
      text-align: center;
      color: #555555;
      padding-top: 14vb;
    }

    .add-transaction-button {
        background-color: #1abc9c;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 1em;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background-color 0.3s ease;
    }

    .add-transaction-button:hover {
        background-color: #16a085;
    }

    .add-transaction-button img {
        width: 20px;
        height: 20px;
    }

    .filter-bar {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .filter-bar label {
        margin: 0;
        font-size: 1em;
        color: #1d2a62;
    }
    .filter-bar select {
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid #ccc;
        background-color: white;
        font-size: 1em;
        color: #333;
    } 

    @media (max-width: 1024px) {
        .grid-container {
            grid-template-areas:
                "recent-transactions recent-transactions"
                "expenses expenses"
                "chart chart"
                "spending-breakdown spending-breakdown"
                "budget-progress budget-progress"
                "goal goal";
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 768px) {
        .grid-container {
            grid-template-areas:
                "recent-transactions"
                "expenses"
                "chart"
                "spending-breakdown"
                "budget-progress"
                "goal";
            grid-template-columns: 1fr;
        }

        .recent-transactions-table thead th,
        .recent-transactions-table tbody td {
            font-size: 12px;
            padding: 10px 15px;
        }
    }
  </style>
</head>

<body>
   
   <form action="dashboard.php" method="post">
    <div class="page">
      <div class="sidebarspace"><?php include 'sidebar.php'?> </div>
      <div class="dashboard">
        <div class="dashboard-header-row">
            <h1>Overview</h1>
            <div class="filter-bar">
                <label for="monthSelect">Filter by Month:</label>
                <select id="monthSelect" name="monthSelect">
                  <option value="">All</option>
                  <option value="01">January</option>
                  <option value="02">February</option>
                  <option value="03">March</option>
                  <option value="04">April</option>
                  <option value="05">May</option>
                  <option value="06">June</option>
                  <option value="07">July</option>
                  <option value="08">August</option>
                  <option value="09">September</option>
                  <option value="10">October</option>
                  <option value="11">November</option>
                  <option value="12">December</option>
                </select>
            </div>
            <div class="dashboard2">
                <div style="text-align: left; margin-bottom: 1rem;"></div>

                <div class="stat-box">
                  <h3>Current Balance</h3>
                  <p id="totalFunds">₱0.00</p>
                </div>
                <div class="stat-box">
                  <h3>Expenses</h3>
                  <p id="totalMonthlyExpenses">₱0.00</p>
                </div>
                <div class="stat-box">
                  <h3>Savings</h3>
                  <p id="totalMonthlySavings">₱0.00</p>
                </div>
                <div class="stat-box">
                  <h3>Loans</h3>
                  <p id="totalMonthlyLoans">₱0.00</p>
                </div>
            </div>
        </div>

        <div class="grid-container">
          <div class="card recent-transactions">
            <h2>Recent Transactions</h2>
            <div class="card-body">
              <table class="recent-transactions-table">
                <thead>
                    <tr>
                        <th>SL</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody id="recentTransactionsTableBody"></tbody>
              </table>
            </div>
          </div>

          <div class="card chart">
            <h2>Income vs. Expenses</h2> 
            <canvas id="incomeExpensesChart"></canvas>
          </div>

          <div class="card goal">
            <h2>Saving Goal</h2>
            <div class="goal-chart-wrapper">
                <canvas id="goalChart"></canvas>
            </div>
            <h4 id="goalStatusText">You are 0% closer to your goal</h4>
            <h4 id="goalStatusText2">Set your monthly saving goal to get started</h4>
          </div>

          <div class="card expenses" style="position:relative;">
            <h2>Expenses</h2>
            <div class="expenses-chart-wrapper">
              <canvas id="expensesChart"></canvas>
              <div id="maxExpenseCategoryText">Loading...</div>
            </div>
            
          </div>

          <div class="card spending-breakdown">
            <h2>Spending Breakdown</h2>
            <canvas id="spendingBreakdownChart"></canvas>
            <div id="spendingBreakdownChart-no-data" style="display: none;">No spending breakdown data for the selected period.</div>
          </div>

        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script src="charts.js"></script>
  </form>
  <?php include 'chatbot_widget.php'; ?>
</body>
</html>


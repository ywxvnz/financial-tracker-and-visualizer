<?php
include 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

function getTransactionsAndTotal($conn, $user_id, $type = null, $period = 'month') {
    $transactions = [];
    $total_amount = 0;
    $where_clause = "WHERE user_id = ?";
    $params = [$user_id];
    $param_types = "i";

    if ($type) {
        $where_clause .= " AND type = ?";
        $params[] = $type;
        $param_types .= "s";
    }

    switch ($period) {
        case 'week':
            $where_clause .= " AND YEARWEEK(date_issued, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'month':
            $where_clause .= " AND YEAR(date_issued) = YEAR(CURDATE()) AND MONTH(date_issued) = MONTH(CURDATE())";
            break;
        case 'year':
            $where_clause .= " AND YEAR(date_issued) = YEAR(CURDATE())";
            break;
        case 'all_time':
            break;
        default:
            $where_clause .= " AND YEAR(date_issued) = YEAR(CURDATE()) AND MONTH(date_issued) = MONTH(CURDATE())";
            break;
    }

    $sql = "SELECT date_issued, title, type, category, amount FROM transactions $where_clause ORDER BY date_issued DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
        $total_amount += $row['amount'];
    }
    $stmt->close();

    return ['transactions' => $transactions, 'total' => $total_amount];
}

// Function to get loan data with correct table name 'loan_types'
function getLoanData($conn, $user_id, $period = 'month') {
    $loans = [];
    $total_borrowed = 0;
    $total_repaid = 0;
    $total_balance = 0;

    $where_clause = "WHERE loans.user_id = ?";
    $params = [$user_id];
    $param_types = "i";

    switch ($period) {
        case 'week':
            $where_clause .= " AND YEARWEEK(loans.date, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'month':
            $where_clause .= " AND YEAR(loans.date) = YEAR(CURDATE()) AND MONTH(loans.date) = MONTH(CURDATE())";
            break;
        case 'year':
            $where_clause .= " AND YEAR(loans.date) = YEAR(CURDATE())";
            break;
        case 'all_time':
            break;
        default:
            $where_clause .= " AND YEAR(loans.date) = YEAR(CURDATE()) AND MONTH(loans.date) = MONTH(CURDATE())";
            break;
    }

    $sql = "
        SELECT 
            loans.date, 
            loans.name, 
            loan_types.type_name AS type, 
            loans.amount, 
            loans.balance, 
            loans.status 
        FROM loans
        JOIN loan_types ON loans.loan_type_id = loan_types.id
        $where_clause
        ORDER BY loans.date DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $loans[] = $row;
        $total_borrowed += $row['amount'];
        $total_balance += $row['balance'];
        $total_repaid += ($row['amount'] - $row['balance']);
    }

    $stmt->close();

    return [
        'loans' => $loans,
        'total_borrowed' => $total_borrowed,
        'total_balance' => $total_balance,
        'total_repaid' => $total_repaid
    ];
}




$filter_period = $_GET['filter'] ?? 'month';

$incomeData = getTransactionsAndTotal($conn, $user_id, 'Income', $filter_period);
$expensesData = getTransactionsAndTotal($conn, $user_id, 'Expense', $filter_period);
$savingsData = getTransactionsAndTotal($conn, $user_id, 'Savings', $filter_period);
$loanData = getLoanData($conn, $user_id, $filter_period); // Fetch loan data

$summaryData = getTransactionsAndTotal($conn, $user_id, null, $filter_period); // All types

function getDisplayPeriodText($filter) {
    switch ($filter) {
        case 'week': return 'This Week';
        case 'year': return 'This Year';
        case 'all_time': return 'All Time';
        default: return 'This Month';
    }
}

$display_period_text_income = getDisplayPeriodText($filter_period);
$display_period_text_expenses = getDisplayPeriodText($filter_period);
$display_period_text_savings = getDisplayPeriodText($filter_period);
$display_period_text_loan = getDisplayPeriodText($filter_period); 
$display_period_text_summary = getDisplayPeriodText($filter_period); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="style.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <title>Reports</title>
</head>
<body>
  <div class="page">
      <div class="sidebarspace"><?php include 'sidebar.php'?> </div>
  <div class="report-content">
    <h2>Reports</h2>
    <div class="report-section" onclick="toggleSection(this)">
      <div class="income">Income</div>
      <div class="filter-dropdown">
        <button class="dropdown-toggle" id="income-dropdown-toggle">
          <?= htmlspecialchars($display_period_text_income); ?> <span class="arrow">&#9662;</span>
        </button>
        <ul class="dropdown-menu">
          <li onclick="filterBy('income', 'week')">This Week</li>
          <li onclick="filterBy('income', 'month')">This Month</li>
          <li onclick="filterBy('income', 'year')">This Year</li>
          <li onclick="filterBy('income', 'all_time')">All Time</li>
        </ul>
      </div>
      <table class="auto-action-table">
        <thead>
          <tr><th>Date & Time</th><th>Name</th><th>Category</th><th>Amount</th></tr>
        </thead>
        <tbody>
          <?php if (empty($incomeData['transactions'])): ?>
            <tr><td colspan="5">No income transactions found for this period.</td></tr>
          <?php else: ?>
            <?php foreach ($incomeData['transactions'] as $transaction): ?>
              <tr>
                <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($transaction['date_issued']))) ?></td>
                <td><?= htmlspecialchars($transaction['title']) ?></td>
                <td><?= htmlspecialchars($transaction['category']) ?></td> <td>₱<?= htmlspecialchars(number_format($transaction['amount'], 2)) ?></td>
                </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
      <div class="total-amount">Total Income for <?= htmlspecialchars($display_period_text_income); ?>: <span class="amount-value">₱<?= number_format($incomeData['total'], 2) ?></span></div>
    </div>

    <div class="report-section" onclick="toggleSection(this)">
      <div class="expenses">Expenses</div>
      <div class="filter-dropdown">
        <button class="dropdown-toggle" id="expenses-dropdown-toggle">
            <?= htmlspecialchars($display_period_text_expenses); ?> <span class="arrow">&#9662;</span>
        </button>
        <ul class="dropdown-menu">
          <li onclick="filterBy('expenses', 'week')">This Week</li>
          <li onclick="filterBy('expenses', 'month')">This Month</li>
          <li onclick="filterBy('expenses', 'year')">This Year</li>
          <li onclick="filterBy('expenses', 'all_time')">All Time</li>
        </ul>
      </div>
      <table class="auto-action-table">
        <thead>
          <tr><th>Date & Time</th><th>Name</th><th>Category</th><th>Amount</th></tr>
        </thead>
        <tbody>
          <?php if (empty($expensesData['transactions'])): ?>
            <tr><td colspan="5">No expense transactions found for this period.</td></tr>
          <?php else: ?>
            <?php foreach ($expensesData['transactions'] as $transaction): ?>
              <tr>
                <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($transaction['date_issued']))) ?></td>
                <td><?= htmlspecialchars($transaction['title']) ?></td>
                <td><?= htmlspecialchars($transaction['category']) ?></td> <td>₱<?= htmlspecialchars(number_format($transaction['amount'], 2)) ?></td>
               </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
      <div class="total-amount">Total Expenses for <?= htmlspecialchars($display_period_text_expenses); ?>: <span class="amount-value">₱<?= number_format($expensesData['total'], 2) ?></span></div>
    </div>

    <div class="report-section" onclick="toggleSection(this)">
      <div class="services">Savings</div>
      <div class="filter-dropdown">
        <button class="dropdown-toggle" id="savings-dropdown-toggle">
            <?= htmlspecialchars($display_period_text_savings); ?> <span class="arrow">&#9662;</span>
        </button>
        <ul class="dropdown-menu">
          <li onclick="filterBy('savings', 'week')">This Week</li>
          <li onclick="filterBy('savings', 'month')">This Month</li>
          <li onclick="filterBy('savings', 'year')">This Year</li>
          <li onclick="filterBy('savings', 'all_time')">All Time</li>
        </ul>
      </div>
      
      <table class="auto-action-table">
        <thead>
          <tr><th>Date & Time</th><th>Name</th><th>Category</th><th>Amount</th></tr>
        </thead>
        <tbody>
          <?php if (empty($savingsData['transactions'])): ?>
            <tr><td colspan="5">No savings transactions found for this period.</td></tr>
          <?php else: ?>
            <?php foreach ($savingsData['transactions'] as $transaction): ?>
              <tr>
                <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($transaction['date_issued']))) ?></td>
                <td><?= htmlspecialchars($transaction['title']) ?></td>
                <td><?= htmlspecialchars($transaction['category']) ?></td> <td>₱<?= htmlspecialchars(number_format($transaction['amount'], 2)) ?></td>
                <td></td> </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
      <div class="total-amount">Total Savings for <?= htmlspecialchars($display_period_text_savings); ?>: <span class="amount-value">₱<?= number_format($savingsData['total'], 2) ?></span></div>
    </div>
    
    <!-- LOAN SECTION -->
<div class="report-section" onclick="toggleSection(this)">
  <div class="summary-tab loan-tab">Loan</div>

  <div class="summary-controls">
    <div class="filter-dropdown">
      <button class="dropdown-toggle" id="loan-dropdown-toggle">
        <?= htmlspecialchars($display_period_text_loan); ?> <span class="arrow">&#9662;</span>
      </button>
      <ul class="dropdown-menu">
        <li onclick="filterBy('loan', 'week')">This Week</li>
        <li onclick="filterBy('loan', 'month')">This Month</li>
        <li onclick="filterBy('loan', 'year')">This Year</li>
        <li onclick="filterBy('loan', 'all_time')">All Time</li>
      </ul>
    </div>
  </div>

  <table class="auto-action-table loan-table">
    <thead>
      <tr>
        <th>Date</th>
        <th>Name</th>
        <th>Type</th>
        <th>Amount</th>
        <th>Balance</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($loanData['loans'])): ?>
        <tr><td colspan="6">No loans found for this period.</td></tr>
      <?php else: ?>
        <?php foreach ($loanData['loans'] as $loan): ?>
          <tr>
            <td><?= htmlspecialchars(date('Y-m-d', strtotime($loan['date']))) ?></td>
            <td><?= htmlspecialchars($loan['name']) ?></td>
            <td><?= htmlspecialchars($loan['type']) ?></td>
            <td>₱<?= number_format($loan['amount'], 2) ?></td>
            <td>₱<?= number_format($loan['balance'], 2) ?></td>
            <td><?= htmlspecialchars($loan['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="total-amount1">
    <p>Total Borrowed for <?= htmlspecialchars($display_period_text_loan); ?>: 
      <span class="amount-value">₱<?= number_format($loanData['total_borrowed'], 2) ?></span></p>
    <p>Total Repaid for <?= htmlspecialchars($display_period_text_loan); ?>: 
      <span class="amount-value">₱<?= number_format($loanData['total_repaid'], 2) ?></span></p>
    <p>Total Balance for <?= htmlspecialchars($display_period_text_loan); ?>: 
      <span class="amount-value">₱<?= number_format($loanData['total_balance'], 2) ?></span></p>
  </div>
</div> <!-- END OF LOAN SECTION -->


<!-- SUMMARY SECTION -->
<div class="report-section" onclick="toggleSection(this)">
  <div class="summary-tab">Summary</div>

  <div class="summary-controls">
    <div class="filter-dropdown">
      <button class="dropdown-toggle" id="summary-dropdown-toggle">
        <?= htmlspecialchars($display_period_text_summary); ?> <span class="arrow">&#9662;</span>
      </button>
      <ul class="dropdown-menu">
        <li onclick="filterBy('summary', 'week')">This Week</li>
        <li onclick="filterBy('summary', 'month')">This Month</li>
        <li onclick="filterBy('summary', 'year')">This Year</li>
        <li onclick="filterBy('summary', 'all_time')">All Time</li>
      </ul>
    </div>

    <form method="get" action="generate_pdf.php" target="_blank" class="pdf-download hidden">
      <input type="hidden" name="period" id="pdf-period" value="<?= htmlspecialchars($filter_period) ?>">
      <button type="submit" class="download-btn">Download Report as PDF</button>
    </form>
  </div>

  
  <table class="auto-action-table">
    <thead>
      <tr><th>Date & Time</th><th>Name</th><th>Type</th><th>Category</th><th>Amount</th></tr>
    </thead>
    <tbody>
      <?php if (empty($summaryData['transactions'])): ?>
        <tr><td colspan="5">No transactions found for this period.</td></tr>
      <?php else: ?>
        <?php foreach ($summaryData['transactions'] as $transaction): ?>
          <tr>
            <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($transaction['date_issued']))) ?></td>
            <td><?= htmlspecialchars($transaction['title']) ?></td>
            <td><?= htmlspecialchars($transaction['type']) ?></td>
            <td><?= htmlspecialchars($transaction['category']) ?></td>
            <td>₱<?= htmlspecialchars(number_format($transaction['amount'], 2)) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
    <div class="total-summary-row hidden">
    <div class="summary-box income-box">
      <p class="summary-label">Total Income (<?= htmlspecialchars($display_period_text_summary); ?>)</p>
      <p class="summary-value">₱<?= number_format($incomeData['total'], 2) ?></p>
    </div>
    <div class="summary-box expense-box">
      <p class="summary-label">Total Expenses (<?= htmlspecialchars($display_period_text_summary); ?>)</p>
      <p class="summary-value">₱<?= number_format($expensesData['total'], 2) ?></p>
    </div>
    <div class="summary-box savings-box">
      <p class="summary-label">Total Savings (<?= htmlspecialchars($display_period_text_summary); ?>)</p>
      <p class="summary-value">₱<?= number_format($savingsData['total'], 2) ?></p>
    </div>
  </div>
</div>
  </table>
  

  <div class="loan-summary-container">
  <h1 class ="loan-summary-title">Loan Summary</h1>
  <table class="auto-action-table loan-table">
    <thead>
      <tr>
        <th>Date</th>
        <th>Name</th>
        <th>Type</th>
        <th>Amount</th>
        <th>Balance</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($loanData['loans'])): ?>
        <tr><td colspan="6">No loans found for this period.</td></tr>
      <?php else: ?>
        <?php foreach ($loanData['loans'] as $loan): ?>
          <tr>
            <td><?= htmlspecialchars(date('Y-m-d', strtotime($loan['date']))) ?></td>
            <td><?= htmlspecialchars($loan['name']) ?></td>
            <td><?= htmlspecialchars($loan['type']) ?></td>
            <td>₱<?= number_format($loan['amount'], 2) ?></td>
            <td>₱<?= number_format($loan['balance'], 2) ?></td>
            <td><?= htmlspecialchars($loan['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
  
  <div class="total-amount1">
    <p>Total Borrowed for <?= htmlspecialchars($display_period_text_loan); ?>: 
      <span class="amount-value">₱<?= number_format($loanData['total_borrowed'], 2) ?></span></p>
    <p>Total Repaid for <?= htmlspecialchars($display_period_text_loan); ?>: 
      <span class="amount-value">₱<?= number_format($loanData['total_repaid'], 2) ?></span></p>
    <p>Total Balance for <?= htmlspecialchars($display_period_text_loan); ?>: 
      <span class="amount-value">₱<?= number_format($loanData['total_balance'], 2) ?></span></p>
  </div>
</div> <!-- END OF LOAN SECTION -->
  



<script>
  function downloadPDF(period) {
    window.open("generate_pdf.php?period=" + period, "_blank");
  }
</script>

<script>
  function toggleSection(section) {
    section.classList.toggle('active');

    const totalRow = section.querySelector('.total-summary-row');
    const downloadForm = section.querySelector('.pdf-download');

    if (totalRow) {
      totalRow.classList.toggle('hidden', !section.classList.contains('active'));
    }

    if (downloadForm) {
      downloadForm.classList.toggle('hidden', !section.classList.contains('active'));
    }
}


  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('.auto-action-table').forEach(table => {
      const rows = table.querySelectorAll('tbody tr');

      rows.forEach(row => {
        if (!row.querySelector('td[colspan="5"]') && !row.querySelector('.actions')) {
          const actionCell = document.createElement('td');
          actionCell.className = 'actions';
          actionCell.innerHTML = ``;
          row.appendChild(actionCell);
        }
      });
    });

    document.querySelectorAll(".dropdown-toggle").forEach(button => {
      button.addEventListener("click", (event) => {
        event.stopPropagation();
        const menu = button.nextElementSibling;
        menu.style.display = menu.style.display === "block" ? "none" : "block";
      });
    });

    document.addEventListener("click", (e) => {
      document.querySelectorAll(".dropdown-menu").forEach(menu => {
        if (!menu.parentElement.contains(e.target)) {
          menu.style.display = "none";
        }
      });
    });
  });
  

  function filterBy(category, period) {
    window.location.href = `reports.php?filter=${period}`;
  }
</script>

</body>
<?php include 'chatbot_widget.php'; ?>
</html>
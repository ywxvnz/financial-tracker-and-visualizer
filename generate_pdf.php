<?php
require_once 'dompdf/autoload.inc.php';
include 'db_connect.php';
session_start();

use Dompdf\Dompdf;

if (!isset($_SESSION['user_id'])) {
    die("Access denied");
}

$user_id = $_SESSION['user_id'];
$period = $_GET['period'] ?? 'month';

// Function to get transactions
function getTransactions($conn, $user_id, $period) {
    $where_clause = "WHERE user_id = ?";
    $params = [$user_id];
    $param_types = "i";

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

    $transactions = [];
    $total_income = 0;
    $total_expenses = 0;
    $total_savings = 0;

    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
        switch (strtolower($row['type'])) {
            case 'income': $total_income += $row['amount']; break;
            case 'expense': $total_expenses += $row['amount']; break;
            case 'savings': $total_savings += $row['amount']; break;
        }
    }

    $stmt->close();
    return [
        'transactions' => $transactions,
        'totals' => [
            'income' => $total_income,
            'expenses' => $total_expenses,
            'savings' => $total_savings,
        ]
    ];
}

// Function to get loan data
function getLoans($conn, $user_id, $period) {
    $where_clause = "WHERE user_id = ?";
    $params = [$user_id];
    $param_types = "i";

    switch ($period) {
        case 'week':
            $where_clause .= " AND YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'month':
            $where_clause .= " AND YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())";
            break;
        case 'year':
            $where_clause .= " AND YEAR(date) = YEAR(CURDATE())";
            break;
        case 'all_time':
            break;
        default:
            $where_clause .= " AND YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())";
            break;
    }

    $sql = "SELECT l.date, l.name, l.amount, l.balance, l.status, lt.type_name 
            FROM loans l
            LEFT JOIN loan_types lt ON l.loan_type_id = lt.id
            $where_clause
            ORDER BY l.date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $loans = [];
    $totalBorrowed = 0;
    $totalBalance = 0;

    while ($row = $result->fetch_assoc()) {
        $loans[] = $row;
        $totalBorrowed += $row['amount'];
        $totalBalance += $row['balance'];
    }

    $stmt->close();

    return [
        'loans' => $loans,
        'total_borrowed' => $totalBorrowed,
        'total_balance' => $totalBalance,
        'total_repaid' => $totalBorrowed - $totalBalance,
    ];
}

// Fetch data
$data = getTransactions($conn, $user_id, $period);
$loanData = getLoans($conn, $user_id, $period);

// ✅ Set up Dompdf BEFORE HTML
$dompdf = new Dompdf();
$dompdf->setPaper('A4', 'portrait');
$dompdf->set_option('isHtml5ParserEnabled', true);
$dompdf->set_option('isFontSubsettingEnabled', true);

// ✅ Start buffering the HTML output
ob_start();
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
    th { background-color: #f0f0f0; }
    .summary-table { margin-top: 30px; }
    .summary-header { text-align: center; font-weight: bold; background-color: #e0e0e0; }
    .summary-income { background-color: #d4edda; color: #155724; }
    .summary-expense { background-color: #f8d7da; color: #721c24; }
    .summary-savings { background-color: #d1ecf1; color: #0c5460; }
    .summary-value { font-weight: bold; text-align: center; }
  </style>
</head>
<body>
  <h2>Financial Report (<?= ucfirst($period) ?>)</h2>

  <h3>Transactions</h3>
  <table>
    <thead><tr><th>Date</th><th>Name</th><th>Type</th><th>Category</th><th>Amount</th></tr></thead>
    <tbody>
      <?php if (empty($data['transactions'])): ?>
        <tr><td colspan="5">No transactions found.</td></tr>
      <?php else: ?>
        <?php foreach ($data['transactions'] as $t): ?>
        <tr>
          <td><?= date('Y-m-d H:i', strtotime($t['date_issued'])) ?></td>
          <td><?= htmlspecialchars($t['title']) ?></td>
          <td><?= htmlspecialchars($t['type']) ?></td>
          <td><?= htmlspecialchars($t['category']) ?></td>
          <td>₱<?= number_format($t['amount'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <table class="summary-table">
    <tr class="summary-header"><td>Total Income</td><td>Total Expenses</td><td>Total Savings</td></tr>
    <tr>
      <td class="summary-income summary-value">₱<?= number_format($data['totals']['income'], 2) ?></td>
      <td class="summary-expense summary-value">₱<?= number_format($data['totals']['expenses'], 2) ?></td>
      <td class="summary-savings summary-value">₱<?= number_format($data['totals']['savings'], 2) ?></td>
    </tr>
  </table>

  <h3>Loan Summary</h3>
  <table>
    <thead><tr><th>Date</th><th>Name</th><th>Type</th><th>Amount</th><th>Balance</th><th>Status</th></tr></thead>
    <tbody>
      <?php if (empty($loanData['loans'])): ?>
        <tr><td colspan="6">No loans found.</td></tr>
      <?php else: ?>
        <?php foreach ($loanData['loans'] as $loan): ?>
        <tr>
          <td><?= $loan['date'] ?></td>
          <td><?= htmlspecialchars($loan['name']) ?></td>
          <td><?= htmlspecialchars($loan['type_name']) ?></td>
          <td>₱<?= number_format($loan['amount'], 2) ?></td>
          <td>₱<?= number_format($loan['balance'], 2) ?></td>
          <td><?= htmlspecialchars($loan['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <table class="summary-table">
    <tr class="summary-header"><td>Total Borrowed</td><td>Total Repaid</td><td>Total Balance</td></tr>
    <tr>
      <td class="loan-yellow ">₱<?= number_format($loanData['total_borrowed'], 2) ?></td>
      <td class="loan-yellow ">₱<?= number_format($loanData['total_repaid'], 2) ?></td>
      <td class="loan-yellow ">₱<?= number_format($loanData['total_balance'], 2) ?></td>
    </tr>

  </table>
</body>
</html>

<?php
$html = ob_get_clean();
$dompdf->loadHtml($html);
$dompdf->render();
$dompdf->stream("Financial_Report_" . $period . ".pdf", ["Attachment" => false]);
exit;
?>

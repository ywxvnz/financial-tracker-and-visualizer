<?php
// charts.php

session_start();
require 'db_connect.php'; // Correctly includes your mysqli connection

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$month = isset($_GET['month']) ? $_GET['month'] : '';
$monthInt = !empty($month) ? intval($month) : 0;
$current_year = date('Y');

function buildDateFilter($month) {
    return !empty($month) ? "AND MONTH(date_issued) = ?" : ""; // Use '?' for mysqli prepared statements
}

$stmtBalance = $conn->prepare("SELECT SUM(balance) AS total_balance FROM loans WHERE user_id = ?");
$stmtBalance->bind_param("i", $user_id);
$stmtBalance->execute();
$resultBalance = $stmtBalance->get_result();
$totalBalance = $resultBalance->fetch_assoc()['total_balance'] ?? 0;
$stmtBalance->close();

$stmt = $conn->prepare("UPDATE config SET
        loan = ?
        WHERE user_id = ?");
$stmt->bind_param("di",  $totalBalance, $user_id);
$stmt->execute();
$stmt->close();

function getIncomeExpenses($conn, $user_id, $month) {
    $sql = "SELECT
              DATE_FORMAT(date_issued, '%Y-%m-%d') AS dates,
              SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income,
              SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS expense,
              SUM(CASE WHEN type = 'savings' AND  goal_id is null THEN amount ELSE 0 END) AS savings
            FROM transactions
            WHERE user_id = ? " . buildDateFilter($month) .
            " GROUP BY dates
              ORDER BY dates";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("MySQLi Prepare Error (getIncomeExpenses): " . $conn->error);
        return [];
    }

    if (!empty($month)) {
        $stmt->bind_param('ii', $user_id, $month);
    } else {
        $stmt->bind_param('i', $user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getDailyBalances($conn, $user_id, $month, $year) {
    if (empty($month) || empty($year)) {
        return [];
    }

    $initial_balance_sql = "SELECT balance FROM config WHERE user_id = ?";
    $stmt_initial = $conn->prepare($initial_balance_sql);
    if ($stmt_initial === false) {
        error_log("MySQLi Prepare Error (getDailyBalances initial balance): " . $conn->error);
        return [];
    }
    $stmt_initial->bind_param('i', $user_id);
    $stmt_initial->execute();
    $result_initial = $stmt_initial->get_result();
    $initial_config = $result_initial->fetch_assoc();
    $current_balance = $initial_config['balance'] ?? 0;
    $stmt_initial->close();

    $transactions_sql = "SELECT date_issued,
                                SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) AS net_amount
                         FROM transactions
                         WHERE user_id = ? AND MONTH(date_issued) = ? AND YEAR(date_issued) = ?
                         GROUP BY date_issued
                         ORDER BY date_issued ASC";

    $stmt_transactions = $conn->prepare($transactions_sql);
    if ($stmt_transactions === false) {
        error_log("MySQLi Prepare Error (getDailyBalances transactions): " . $conn->error);
        return [];
    }
    $stmt_transactions->bind_param('iii', $user_id, $month, $year);
    $stmt_transactions->execute();
    $result_transactions = $stmt_transactions->get_result();
    $daily_transactions = $result_transactions->fetch_all(MYSQLI_ASSOC);
    $stmt_transactions->close();

    $daily_balances = [];
    $num_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    $transaction_map = [];
    foreach ($daily_transactions as $trans) {
        $transaction_map[$trans['date_issued']] = $trans['net_amount'];
    }

    for ($day = 1; $day <= $num_days; $day++) {
        $date = sprintf('%d-%02d-%02d', $year, $month, $day);
        $net_amount = $transaction_map[$date] ?? 0;
        $current_balance += $net_amount; // Accumulate balance daily
        $daily_balances[] = ['date' => $date, 'balance' => $current_balance];
    }

    return $daily_balances;
}

function getExpensesByCategory($conn, $user_id, $month) {
    $sql = "SELECT category, SUM(amount) AS total
            FROM transactions
            WHERE user_id = ? AND type = 'expense' " . buildDateFilter($month) .
           " GROUP BY category";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("MySQLi Prepare Error (getExpensesByCategory): " . $conn->error);
        return [];
    }

    if (!empty($month)) {
        $stmt->bind_param('ii', $user_id, $month);
    } else {
        $stmt->bind_param('i', $user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getSpendingBreakdown($conn, $user_id, $month) {
    $sql = "SELECT category, SUM(amount) AS total
            FROM transactions
            WHERE user_id = ? AND type = 'expense' " . buildDateFilter($month) .
           " GROUP BY category
           ORDER BY total DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("MySQLi Prepare Error (getSpendingBreakdown): " . $conn->error);
        return [];
    }

    if (!empty($month)) {
        $stmt->bind_param('ii', $user_id, $month);
    } else {
        $stmt->bind_param('i', $user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getTotalMonthlyExpenses($conn, $user_id, $month) {
    $sql = "SELECT SUM(amount) AS total_expenses
            FROM transactions
            WHERE user_id = ? AND type = 'expense' " . buildDateFilter($month);

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("MySQLi Prepare Error (getTotalMonthlyExpenses): " . $conn->error);
        return 0;
    }

    if (!empty($month)) {
        $stmt->bind_param('ii', $user_id, $month);
    } else {
        $stmt->bind_param('i', $user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total_expenses'] ?? 0;
}

function getUserConfig($conn, $user_id) {
    $sql = "SELECT balance, income, expenses, savings, loan, expensebudget, monthly_saving
            FROM config
            WHERE user_id = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("MySQLi Prepare Error (getUserConfig): " . $conn->error);
        return null;
    }

    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Sum of savings transactions for given month excluding those with goal_id or loan_id (i.e., true savings)
function getMonthlySavingsSum($conn, $user_id, $month) {
    if (empty($month)) {
        return 0; // No filter for month returns 0 because we want monthly savings
    }
    $sql = "SELECT SUM(amount) AS total_savings
            FROM transactions
            WHERE user_id = ?
              AND type = 'savings'
              AND goal_id IS NULL
              AND loan_id IS NULL
              AND MONTH(date_issued) = ?
              AND YEAR(date_issued) = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("MySQLi Prepare Error (getMonthlySavingsSum): " . $conn->error);
        return 0;
    }
    $year = date('Y');
    $stmt->bind_param("iii", $user_id, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total_savings'] ?? 0;
}

if (isset($_GET['get_transaction_dates']) && $_GET['get_transaction_dates'] === 'true') {
    $target_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
    $target_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

    function getDatesWithTransactionsForMonth($conn, $user_id, $month, $year) {
        $dates = [];
        $sql = "SELECT DISTINCT DATE_FORMAT(date_issued, '%Y-%m-%d') as transaction_date
                FROM transactions
                WHERE user_id = ? AND MONTH(date_issued) = ? AND YEAR(date_issued) = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("MySQLi Prepare Error (getDatesWithTransactionsForMonth): " . $conn->error);
            return [];
        }
        $stmt->bind_param("iii", $user_id, $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $dates[] = $row['transaction_date'];
        }
        $stmt->close();
        return $dates;
    }

    $response['dates_with_transactions'] = getDatesWithTransactionsForMonth($conn, $user_id, $target_month, $target_year);
    echo json_encode(['dates_with_transactions' => $response['dates_with_transactions']]);
    exit;
}

$response = [];

$current_month = !empty($monthInt) ? $monthInt : date('n');
$current_year = date('Y');

$response['income_expense'] = getIncomeExpenses($conn, $user_id, $current_month);
$response['expenses_by_category'] = getExpensesByCategory($conn, $user_id, $current_month);
$response['daily_balances'] = getDailyBalances($conn, $user_id, $current_month, $current_year);
$response['spending_breakdown'] = getSpendingBreakdown($conn, $user_id, $current_month);

$config = getUserConfig($conn, $user_id);
$response['config'] = $config;
$response['monthly_total_expenses'] = getTotalMonthlyExpenses($conn, $user_id, $current_month);

// New: monthly savings sum excluding goal/loan transactions for accuracy in goal progress
$response['monthly_savings'] = floatval(getMonthlySavingsSum($conn, $user_id, $current_month));

echo json_encode($response);

$conn->close();
?>


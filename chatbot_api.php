<?php
include 'db_connect.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Please log in to use the chatbot.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$user_message = strtolower(trim($input['query'] ?? ''));

if (empty($user_message)) {
    echo json_encode(['response' => 'How can I assist you?']);
    exit();
}

$stmt = $conn->prepare("SELECT balance, expensebudget FROM config WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$balance = $user_data['balance'] ?? 0;
$expense_budget = $user_data['expensebudget'] ?? 0;
$low_balance_threshold = $expense_budget * 0.5;

$response_message = processChatbotQuery($user_message, $user_id, $balance, $low_balance_threshold, $expense_budget, $conn);
echo json_encode(['response' => $response_message]);
$keywordMap = [
    'getLoanSummary' => ['loan balance', 'total loans', 'summary of loans', 'loans i owe'],
    'checkLowBalance' => ['low balance', 'threshold', 'balance alert', 'is my balance low'],
    'getLoanReminders' => ['due', 'remind', 'upcoming payments', 'reminders', 'loan schedule'],
    'canAffordLoans' => ['afford', 'can i pay', 'enough balance', 'able to pay'],
    'getBiggestExpenses' => ['biggest expenses', 'top expenses', 'where i spent the most', 'highest spending'],
    'getTopSpendingCategory' => ['category costs me', 'top category', 'which category', 'spending by category'],
    'checkIfLoanShouldBePaidNow' => ['should i pay my loan', 'pay loan now', 'is it time to pay my loan'],
    'getCurrentBalanceMessage' => ['check my balance', 'current balance', 'how much money do i have', 'show my balance'],
    'getSpendingAdvice' => ['cut back', 'spending advice', 'spending too much', 'save more money', 'help with overspending'],
    'calculatePotentialSavings' => ['how much can i save', 'expected savings', 'potential savings', 'savings estimate'],
    'checkBudgetUsage' => ['how much of my budget', 'used budget', 'budget status'],
    'getTodayReminders' => ['what do i need to do today', 'today\'s reminders', 'tasks today', 'what\'s due today'],
    'getFinancialSummary' => ['show my financial summary', 'budget summary', 'overall spending'],
    'getSpendingHealthStatus' => ['is my spending healthy', 'spending health', 'spending evaluation', 'am i spending wisely']
];

function processChatbotQuery($message, $user_id, $balance, $threshold, $expense_budget, $conn) {
    $tipResponse = getTipResponse($message);
    if ($tipResponse !== null) {
        return $tipResponse;
    }

    if (preg_match('/\b(hello|hi)\b/', $message)) {
        return "Hello! I can check your loans, balance, or send reminders. Tap a prompt to begin.";
    }

    if (strpos($message, 'loan balance') !== false || strpos($message, 'total loans') !== false) {
        return getLoanSummary($user_id, $conn);
    }

    if (strpos($message, 'low balance', ) !== false || strpos($message, 'threshold') !== false) {
        return checkLowBalance($balance, $threshold);
    }

    if (strpos($message, 'due') !== false || strpos($message, 'remind') !== false) {
        return getLoanReminders($user_id, $conn);
    }

    if (strpos($message, 'afford') !== false || strpos($message, 'can i pay') !== false) {
        return canAffordLoans($user_id, $balance, $conn);
    }

    if (strpos($message, 'biggest expenses') !== false || strpos($message, 'top expenses') !== false) {
        return getBiggestExpenses($user_id, $conn);
    }

    if (strpos($message, 'category costs me') !== false || strpos($message, 'top category') !== false || strpos($message, 'which category') !== false) {
        return getTopSpendingCategory($user_id, $conn);
    }
    if (strpos($message, 'should i pay my loan') !== false) {
    return checkIfLoanShouldBePaidNow($conn, $user_id);
    }
    if (strpos($message, 'check my balance') !== false || strpos($message, 'current balance') !== false) {
    return getCurrentBalanceMessage($balance);
    }
    if (strpos($message, 'cut back') !== false || strpos($message, 'spending advice') !== false || strpos($message, 'spending too much') !== false) {
    return getSpendingAdvice($user_id, $expense_budget, $conn);
    }
    if (strpos($message, 'how much can i save') !== false) {
        return calculatePotentialSavings($user_id, $conn);
    }

    if (strpos($message, 'how much of my budget') !== false) {
        return checkBudgetUsage($user_id, $conn);
    }
    if (strpos($message, 'what do i need to do today') !== false || strpos($message, 'reminders') !== false) {
    return getTodayReminders($user_id, $conn);
    }
    if (stripos($message, 'show my financial summary') !== false) {
    echo json_encode(["response" => getFinancialSummary($user_id, $conn)]);
    exit;
    }

    if (stripos($message, 'is my spending healthy') !== false) {
        echo json_encode(["response" => getSpendingHealthStatus($user_id, $conn)]);
        exit;
    }





    return "I can help with:\n\n\u{1F4CA} Loan summary\n\u{1F4C5} Due date reminders\n\u{1F4B8} Low balance alerts\n\u{1F914} Can you afford your loans?\n\nTry:\n- Can I afford my loans?\n- Do I have loans due?\n- Is my balance low?";
}


function getLoanSummary($user_id, $conn) {
    $stmt = $conn->prepare("SELECT SUM(balance) as total, COUNT(*) as count FROM loans WHERE user_id = ? AND status = 'Ongoing'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result['count'] == 0) return "✅ You have no ongoing loans.";

    $stmt = $conn->prepare("SELECT name as loan_name, balance FROM loans WHERE user_id = ? AND status = 'Ongoing' LIMIT 3");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $loans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $response = "📊 You have {$result['count']} ongoing loans totaling ₱" . number_format($result['total'], 2) . ":\n";
    foreach ($loans as $loan) {
        $response .= "- {$loan['loan_name']}: ₱" . number_format($loan['balance'], 2) . "\n";
    }
    return $response;
}

function checkLowBalance($balance, $threshold) {
    if ($balance <= 50) {
        return "🔴 Critical Balance Warning: ₱" . number_format($balance, 2) . " left. Limit expenses immediately.";
    } elseif ($balance <= $threshold) {
        return "⚠️ Low Balance Alert: ₱" . number_format($balance, 2) . ". Consider reviewing expenses.";
    } else {
        return "✅ Balance is healthy: ₱" . number_format($balance, 2);
    }
}

function getLoanReminders($user_id, $conn) {
    $today = new DateTime();
    $stmt = $conn->prepare("SELECT name, amount, due_date FROM loans WHERE user_id = ? AND status = 'Ongoing' AND due_date IS NOT NULL");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $loans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $upcoming = [];
    $overdue = [];

    foreach ($loans as $loan) {
        $due = new DateTime($loan['due_date']);
        $days = (int)$today->diff($due)->format("%r%a");

        if ($days < 0) {
            $overdue[] = "- {$loan['name']}: ₱" . number_format($loan['amount'], 2) . " ({$days} days overdue)";
        } elseif ($days <= 7) {
            $upcoming[] = "- {$loan['name']}: ₱" . number_format($loan['amount'], 2) . " (due in {$days} day(s))";
        }
    }

    if (empty($upcoming) && empty($overdue)) return "✅ No upcoming or overdue loans.";

    $msg = "";
    if (!empty($upcoming)) $msg .= "📅 Upcoming Loans:\n" . implode("\n", $upcoming) . "\n";
    if (!empty($overdue)) $msg .= "\n⚠️ Overdue Loans:\n" . implode("\n", $overdue);

    return $msg;
}

function canAffordLoans($user_id, $balance, $conn) {
    $stmt = $conn->prepare("SELECT SUM(balance) as total FROM loans WHERE user_id = ? AND status = 'Ongoing'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $loan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $due = $loan['total'] ?? 0;

    if ($due == 0) return "🎉 You have no outstanding loans.";
    if ($balance >= $due) return "✅ You can afford your loans. Balance: ₱" . number_format($balance, 2);
    return "⚠️ You are short by ₱" . number_format($due - $balance, 2) . " to cover all loans.";
}

function getBiggestExpenses($user_id, $conn) {
    $stmt = $conn->prepare("SELECT category, amount, date_issued FROM transactions WHERE user_id = ? AND type = 'Expense' AND MONTH(date_issued) = MONTH(CURDATE()) AND YEAR(date_issued) = YEAR(CURDATE()) ORDER BY amount + 0 DESC LIMIT 3");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$rows) return "📭 No expenses recorded this month.";

    $msg = "📊 Top 3 Expenses This Month:\n";
    foreach ($rows as $r) {
        $msg .= "- {$r['category']}: ₱" . number_format($r['amount'], 2) . " on {$r['date_issued']}\n";
    }
    return $msg;
}

function getTopSpendingCategory($user_id, $conn) {
    $stmt = $conn->prepare("SELECT category, SUM(amount + 0) as total FROM transactions WHERE user_id = ? AND type = 'Expense' AND MONTH(date_issued) = MONTH(CURDATE()) AND YEAR(date_issued) = YEAR(CURDATE()) GROUP BY category ORDER BY total DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $top = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$top) return "No expenses found to analyze.";

    return "📂 Most spent category: {$top['category']} (₱" . number_format($top['total'], 2) . ")";
}

function checkIfLoanShouldBePaidNow($conn, $user_id) {
    $today = new DateTime();
    $today->setTime(0, 0); // Set time to the start of the day

    // Get user's balance from config table
    $stmt = $conn->prepare("SELECT balance FROM config WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $balance = isset($row['balance']) ? (float)$row['balance'] : 0;

    // Get active loans with due dates
    $stmt = $conn->prepare("SELECT name, amount, due_date FROM loans WHERE user_id = ? AND status = 'Ongoing' AND due_date IS NOT NULL");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $loans = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($loans)) {
        return "✅ You currently have no active loans with upcoming due dates.";
    }

    $response = "";
    foreach ($loans as $loan) {
        $due = new DateTime($loan['due_date']);
        $due->setTime(0, 0); // Set time to the start of the day for comparison
        $days_left = (int)$today->diff($due)->format('%r%a');
        $amount = (float)$loan['amount'];
        $dueStr = $due->format('F j');
        $formattedAmount = "₱" . number_format($amount, 2);
        $formattedBalance = "₱" . number_format($balance, 2);

        if ($days_left <= 5 && $balance >= $amount) {
            $response .= "✅ Your loan **{$loan['name']}** amounting to {$formattedAmount} is due in **{$days_left} day(s)** (on {$dueStr}). You have sufficient funds ({$formattedBalance}) to make the payment. It is advisable to settle this loan now.\n\n";
        } elseif ($days_left <= 5 && $balance < $amount) {
            $response .= "⚠️ Your loan **{$loan['name']}** is due in **{$days_left} day(s)** (on {$dueStr}). However, your current balance ({$formattedBalance}) is insufficient to cover the amount due ({$formattedAmount}). Please consider allocating funds as soon as possible.\n\n";
        } elseif ($days_left > 5 && $balance >= $amount * 2) {
            $response .= "💡 Your loan **{$loan['name']}** is due in **{$days_left} day(s)** (on {$dueStr}). Your available balance ({$formattedBalance}) is more than sufficient. You may consider settling the loan early to avoid future obligations.\n\n";
        } else {
            $response .= "⏳ Your loan **{$loan['name']}** is due in **{$days_left} day(s)** (on {$dueStr}). There is no immediate need to pay, but we recommend monitoring your finances closely.\n\n";
        }
    }

    return trim($response);
}

function getCurrentBalanceMessage($balance) {
    if ($balance === null) {
        return "⚠️ We couldn't retrieve your current balance. Please try again later.";
    }

    return "💼 Your current available balance is ₱" . number_format($balance, 2) . ".";
}
function getSpendingAdvice($user_id, $unused_budget, $conn) {
    // 1. Get user's monthly expense baseline from config table
    $stmt = $conn->prepare("SELECT expenses FROM config WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $monthly_expense_limit = isset($result['expenses']) ? (float)$result['expenses'] : 0;

    if ($monthly_expense_limit === 0) {
        return "⚠️ You haven't set a monthly expense goal yet. Set one to track your spending more effectively.";
    }

    // 2. Get current month total expenses
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count, SUM(amount + 0) as total 
        FROM transactions 
        WHERE user_id = ? 
        AND type = 'Expense'
        AND MONTH(date_issued) = MONTH(CURDATE()) 
        AND YEAR(date_issued) = YEAR(CURDATE())
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total_spent = (float)($data['total'] ?? 0);
    $expense_count = (int)($data['count'] ?? 0);
    $percent = ($total_spent / $monthly_expense_limit) * 100;

    // 3. Generate advice based on spending
    if ($percent >= 100) {
        return "🚨 You've spent ₱" . number_format($total_spent, 2) . " this month — exceeding your typical monthly spending (₱" . number_format($monthly_expense_limit, 2) . "). Consider cutting back.";
    } elseif ($percent >= 75) {
        return "⚠️ You're at " . number_format($percent, 1) . "% of your monthly spending goal. Total spent: ₱" . number_format($total_spent, 2) . " from {$expense_count} transactions.";
    } elseif ($percent >= 50) {
        return "🔍 You've spent half your usual monthly expenses (₱" . number_format($total_spent, 2) . "). Keep tracking carefully!";
    } else {
        return "✅ You're managing well so far — ₱" . number_format($total_spent, 2) . " spent this month. Keep it up!";
    }
}
function calculatePotentialSavings($user_id, $conn) {
    $stmt = $conn->prepare("SELECT expenses, income FROM config WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $income = $row['income'] ?? 0;
    $expenses = $row['expenses'] ?? 0;
    $savings = $income - $expenses;

    if ($income == 0) return "🛑 Income not set. Unable to estimate savings.";
    if ($savings <= 0) return "⚠️ You're currently overspending. No savings expected this month.";

    return "💰 Based on your income (₱" . number_format($income, 2) . ") and expenses (₱" . number_format($expenses, 2) . "), you could save approximately ₱" . number_format($savings, 2) . " this month.";
}


function checkBudgetUsage($user_id, $conn) {
    $stmt = $conn->prepare("SELECT expenses FROM config WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $monthly = $stmt->get_result()->fetch_assoc()['expenses'] ?? 0;
    $stmt->close();

    $stmt = $conn->prepare("SELECT SUM(amount + 0) as spent FROM transactions WHERE user_id = ? AND type = 'Expense' AND MONTH(date_issued) = MONTH(CURDATE()) AND YEAR(date_issued) = YEAR(CURDATE())");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $spent = $stmt->get_result()->fetch_assoc()['spent'] ?? 0;
    $stmt->close();

    if ($monthly == 0) return "📝 No monthly expense reference set.";
    $usage = ($spent / $monthly) * 100;

    if ($usage >= 100) return "🚨 Budget fully used (₱" . number_format($spent, 2) . "). You're over budget!";
    if ($usage >= 75) return "⚠️ You've used " . number_format($usage, 1) . "% of your monthly budget (₱" . number_format($spent, 2) . " out of ₱" . number_format($monthly, 2) . "). Watch your spending!";
    return "✅ You've only used " . number_format($usage, 1) . "% of your ₱" . number_format($monthly, 2) . " monthly budget.";
}

function getTodayReminders($user_id, $conn) {
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $in7Days = date('Y-m-d', strtotime('+7 days'));
    $month = date('m');
    $year = date('Y');
    $reminders = [];

    // 1. Loans due TODAY
    $stmt = $conn->prepare("SELECT name, status, due_date, amount FROM loans WHERE user_id = ? AND DATE(due_date) = ?");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $loan_name = $row['name'];
        $status = $row['status'];
        $amount = number_format($row['amount'], 2);

        if ($status === 'Paid') {
            $reminders[] = "✅ Loan **{$loan_name}** was already paid before its due date today.";
        } else {
            $reminders[] = "📌 **Today is the due date** for loan **{$loan_name}** (₱{$amount}). Please make the payment.";
        }
    }
    $stmt->close();

    // 2. Loans due TOMORROW
    $stmt = $conn->prepare("SELECT name, status, due_date, amount FROM loans WHERE user_id = ? AND DATE(due_date) = ?");
    $stmt->bind_param("is", $user_id, $tomorrow);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'Ongoing') {
            $loan_name = $row['name'];
            $amount = number_format($row['amount'], 2);
            $reminders[] = "🔔 **Heads up!** Loan **{$loan_name}** is due **tomorrow** (₱{$amount}).";
        }
    }
    $stmt->close();

    // 3. Loans due within next 7 days (excluding today/tomorrow)
    $stmt = $conn->prepare("
        SELECT name, due_date, amount 
        FROM loans 
        WHERE user_id = ? 
          AND status = 'Ongoing' 
          AND DATE(due_date) > ? 
          AND DATE(due_date) <= ?
    ");
    $stmt->bind_param("iss", $user_id, $tomorrow, $in7Days);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $loan_name = $row['name'];
        $due_date = $row['due_date'];
        $amount = number_format($row['amount'], 2);

        $days_left = (new DateTime($due_date))->diff(new DateTime($today))->days;

        $reminders[] = "📅 Loan **{$loan_name}** is due in **{$days_left} day" . ($days_left > 1 ? 's' : '') . "** (₱{$amount}). Plan ahead!";
    }
    $stmt->close();

    // 4. Expenses today
    $stmt = $conn->prepare("SELECT SUM(amount + 0) AS total_expense FROM transactions WHERE user_id = ? AND type = 'Expense' AND DATE(date_issued) = ?");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $expense_today = ($row = $stmt->get_result()->fetch_assoc()) ? (float)$row['total_expense'] : 0;
    $stmt->close();

    if ($expense_today > 0) {
        $reminders[] = "💸 You've spent ₱" . number_format($expense_today, 2) . " today.";
    }

    // 5. Savings today
    $stmt = $conn->prepare("SELECT SUM(amount + 0) AS savings_today FROM transactions WHERE user_id = ? AND type = 'Savings' AND DATE(date_issued) = ?");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $savings_today = ($row = $stmt->get_result()->fetch_assoc()) ? (float)$row['savings_today'] : 0;
    $stmt->close();

    if ($savings_today > 0) {
        $reminders[] = "🏦 You've saved ₱" . number_format($savings_today, 2) . " today.";
    }

    // 6. Monthly savings total
    $stmt = $conn->prepare("SELECT SUM(amount + 0) AS monthly_savings FROM transactions WHERE user_id = ? AND type = 'Savings' AND MONTH(date_issued) = ? AND YEAR(date_issued) = ?");
    $stmt->bind_param("iii", $user_id, $month, $year);
    $stmt->execute();
    $monthly_savings = ($row = $stmt->get_result()->fetch_assoc()) ? (float)$row['monthly_savings'] : 0;
    $stmt->close();

    if ($monthly_savings > 0) {
        $reminders[] = "📆 Total savings today: ₱" . number_format($monthly_savings, 2);
    }

    // Final fallback if no data
    if (empty($reminders)) {
        return "📋 **Today’s Reminders**:\nYou have no loan dues, savings, or expenses recorded today.";
    }

    return "📋 **Today’s Reminders**:\n" . implode("\n", $reminders);
}

function getFinancialSummary($user_id, $conn) {
    $stmt = $conn->prepare("SELECT income, expenses, savings FROM config WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $income = (float)$data['income'];
    $expenses = (float)$data['expenses'];
    $savings = (float)$data['savings'];

    $savingsPercent = $income > 0 ? ($savings / $income) * 100 : 0;
    $expensesPercent = $income > 0 ? ($expenses / $income) * 100 : 0;

    return "📊 **Financial Summary**:\n" .
           "- Income: ₱" . number_format($income, 2) . "\n" .
           "- Expenses: ₱" . number_format($expenses, 2) . " (" . number_format($expensesPercent, 1) . "% of income)\n" .
           "- Savings: ₱" . number_format($savings, 2) . " (" . number_format($savingsPercent, 1) . "% of income)";
}
function getSpendingHealthStatus($user_id, $conn) {
    // Get current balance from config
    $stmt = $conn->prepare("SELECT balance FROM config WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $balanceData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $balance = isset($balanceData['balance']) ? (float)$balanceData['balance'] : 0;

    // Get total expenses
    $stmt = $conn->prepare("SELECT SUM(CAST(amount AS DECIMAL(10,2))) AS total_expenses FROM transactions WHERE user_id = ? AND type = 'Expense'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $expenseData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $expenses = isset($expenseData['total_expenses']) ? (float)$expenseData['total_expenses'] : 0;

    if ($balance <= 0) {
        return "⚠️ Your balance is zero or missing, so I can't assess your spending health.";
    }

    $spendingRatio = ($expenses / $balance) * 100;
    $spendingRatio = min($spendingRatio, 100);

    if ($spendingRatio === 0) {
        return "🟢 You haven’t spent anything yet. Great job holding off!";
    } elseif ($spendingRatio < 50) {
        return "✅ You're spending wisely! You've used " . number_format($spendingRatio, 1) . "% of your balance.";
    } elseif ($spendingRatio < 75) {
        return "⚠️ Caution: You've used " . number_format($spendingRatio, 1) . "% of your balance.";
    } else {
        return "🚨 Warning! You've used " . number_format($spendingRatio, 1) . "% of your balance. Time to slow down.";
    }
}
function getTipResponse($message) {
    $message = strtolower(trim($message)); // normalize

    if (strpos($message, 'money-saving tip') !== false) {
        return "💡 Money-saving tip: Try the 52-week savings challenge! Start with ₱50 in week 1, ₱100 in week 2, and keep going. It builds up nicely.";
    }

    if (strpos($message, 'budget strategy') !== false) {
        return "📊 Budget strategy: Use the 50/30/20 rule – 50% for needs, 30% for wants, and 20% for savings or debt payments.";
    }

    if (strpos($message, 'improve my finances') !== false || strpos($message, 'improve finances') !== false) {
        return "📈 To improve your finances, track spending weekly, cancel unused subscriptions, and build an emergency fund worth 3 months of expenses.";
    }

    return null;
}





$conn->close();
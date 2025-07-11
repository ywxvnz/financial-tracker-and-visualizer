<?php
date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL); // Report all PHP errors
ini_set('display_errors', 1); // Display all PHP errors

include('db_connect.php');
session_start();

$message = null;
if (isset($_SESSION['transaction_success'])) {
    $message = $_SESSION['transaction_success'];
    unset($_SESSION['transaction_success']);
}
?>
<?php if ($message): ?>
    <div id="custom-success-message" style="
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: linear-gradient(135deg, #cce5ff, #d4edda);
        color: #004085;
        padding: 20px 30px;
        border-radius: 10px;
        border: 2px solid #b8daff;
        font-size: 18px;
        font-weight: bold;
        z-index: 9999;
        box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        text-align: center;
        max-width: 90%;
        animation: fadeOut 0.5s ease-in-out 2.5s forwards;
    ">
        <?= htmlspecialchars($message) ?>
    </div>
    <script>
        setTimeout(function() {
            var el = document.getElementById('custom-success-message');
            if (el) {
                el.style.opacity = '0';
                setTimeout(function() {
                    el.remove();
                }, 500);
            }
        }, 3000);
    </script>
<?php endif; 

if (!isset($_SESSION['user_id'])) {
    header("Location: log-in.php");
    exit();
}
$user_id = $_SESSION['user_id'];

$transactionDates = [];

$stmt = $conn->prepare("SELECT DISTINCT DATE(date_issued) as trans_date FROM transactions WHERE user_id = ?");
if ($stmt === false) {
    die("Error preparing transaction dates statement: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $transactionDates[] = $row['trans_date'];
}
$stmt->close();

$query = $conn->query("SELECT balance, savings FROM config WHERE user_id = $user_id LIMIT 1");
$row = $query->fetch_assoc();
$_SESSION['currentbalance'] = $row['balance'];
$_SESSION['currentsavings'] = $row['savings'] ?? 0;

// Store in session
$_SESSION['transaction_dates'] = $transactionDates;

$prefill_loan_id = $_GET['loan_id'] ?? '';
$prefill_loan_name = $_GET['loan_name'] ?? '';

$monthlyexpenses;
$expensesforthemonth;

$stmt = $conn->prepare("SELECT expensebudget, expenses FROM config WHERE user_id = ?");
if ($stmt === false) {
    die("Error preparing transaction dates statement: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $monthlyexpenses = $row['expenses'];
    $expensesforthemonth = $row['expensebudget'];
}

$stmt->close();

// Fetch sum of expenses for this user
$stmt_expense_sum = $conn->prepare("SELECT IFNULL(SUM(amount),0) AS total_expenses FROM transactions WHERE user_id = ? AND LOWER(type) = 'expense'");
$stmt_expense_sum->bind_param("i", $user_id);
$stmt_expense_sum->execute();
$res_expense_sum = $stmt_expense_sum->get_result();
$expenseSumRow = $res_expense_sum->fetch_assoc();
$totalExpenses = $expenseSumRow['total_expenses'] ?? 0;
$stmt_expense_sum->close();

// Fetch sum of savings for this user
$stmt_savings_sum = $conn->prepare("SELECT savings FROM config WHERE user_id = ?");
$stmt_savings_sum->bind_param("i", $user_id);
$stmt_savings_sum->execute();
$res_savings_sum = $stmt_savings_sum->get_result();
$savingsSumRow = $res_savings_sum->fetch_assoc();
$totalSavings = $savingsSumRow['savings'] ?? 0;
$stmt_savings_sum->close();

// Fetch sum of loan balances from loans table where status is 'Ongoing'
$stmt_loan_bal = $conn->prepare("SELECT IFNULL(SUM(balance),0) AS total_loans FROM loans WHERE user_id = ? AND status = 'Ongoing'");
$stmt_loan_bal->bind_param("i", $user_id);
$stmt_loan_bal->execute();
$res_loan_bal = $stmt_loan_bal->get_result();
$loanBalRow = $res_loan_bal->fetch_assoc();
$totalLoans = $loanBalRow['total_loans'] ?? 0;
$stmt_loan_bal->close();


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// DELETE logic
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    // First, get the amount, type, and goal_id of the transaction being deleted
    $stmt_get_deleted_trans = $conn->prepare("SELECT amount, type, goal_id, loan_id FROM transactions WHERE id=? AND user_id=?");
    if ($stmt_get_deleted_trans === false) {
        die("Error preparing delete transaction (get details) statement: " . $conn->error);
    }
    $stmt_get_deleted_trans->bind_param("ii", $delete_id, $user_id);
    $stmt_get_deleted_trans->execute();
    $result_deleted_trans = $stmt_get_deleted_trans->get_result();
    $deleted_transaction = $result_deleted_trans->fetch_assoc();
    $stmt_get_deleted_trans->close();

    if ($deleted_transaction) {
        $deleted_amount = $deleted_transaction['amount'];
        $deleted_type = $deleted_transaction['type'];
        $deleted_goal_id = $deleted_transaction['goal_id'];
        $deleted_load_id = $deleted_transaction['loan_id'];

        // Now delete the transaction
        $stmt = $conn->prepare("DELETE FROM transactions WHERE id=? AND user_id=?");
        if ($stmt === false) {
            die("Error preparing delete transaction statement: " . $conn->error);
        }
        $stmt->bind_param("ii", $delete_id, $user_id);
            if ($stmt->execute()) {
                $_SESSION['transaction_success'] = "Transaction successfully deleted!";
    header("Location: transaction.php");
    exit();
            // Update the balance and/or savings in the config table based on the deleted transaction type
            if (strtolower($deleted_type) == 'income') {
                $update_balance_sql = "UPDATE config SET balance = balance - ? WHERE user_id = ?";
                $stmt_update_balance = $conn->prepare($update_balance_sql);
                if ($stmt_update_balance === false) { throw new Exception("Error preparing balance update statement (income): " . $conn->error); }
                $stmt_update_balance->bind_param("di", $deleted_amount, $user_id);
                $stmt_update_balance->execute();
                $stmt_update_balance->close();
            } elseif (strtolower($deleted_type) == 'expense') {
                $update_balance_sql = "UPDATE config SET balance = balance + ? WHERE user_id = ?";
                $stmt_update_balance = $conn->prepare($update_balance_sql);
                if ($stmt_update_balance === false) { throw new Exception("Error preparing balance update statement (expense): " . $conn->error); }
                $stmt_update_balance->bind_param("di", $deleted_amount, $user_id);
                $stmt_update_balance->execute();
                $stmt_update_balance->close();
            } elseif (strtolower($deleted_type) == 'savings') {
                // When a 'savings' transaction is deleted, decrease savings and increase balance
                $stmt_update_savings = $conn->prepare("UPDATE config SET savings = savings - ?, balance = balance + ? WHERE user_id = ?");
                if ($stmt_update_savings === false) { throw new Exception("Error preparing savings update statement (delete): " . $conn->error); }
                $stmt_update_savings->bind_param("ddi", $deleted_amount, $deleted_amount, $user_id);
                $stmt_update_savings->execute();
                $stmt_update_savings->close();

                // If this savings was for a goal, decrease the goal's saved_amount
                if ($deleted_goal_id) {
                    $stmt_update_goal_savings = $conn->prepare("UPDATE goals SET saved_amount = saved_amount - ? WHERE id = ? AND user_id = ?");
                    if ($stmt_update_goal_savings === false) { throw new Exception("Error preparing goal savings update statement (delete): " . $conn->error); }
                    $stmt_update_goal_savings->bind_param("dii", $deleted_amount, $deleted_goal_id, $user_id);
                    $stmt_update_goal_savings->execute();
                    $stmt_update_goal_savings->close();
                }
            }
        } else {
            echo "Error deleting transaction: " . $stmt->error;
        }
        $stmt->close();
    }

    // Redirect to avoid issues if the user refreshes the page
    // header("Location: " . strtok($_SERVER["REQUEST_URI"], '?')); // Uncomment after debugging
    // exit(); // Uncomment after debugging
}



// ADD logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['update_id'])) {
    // Temporarily add this line to see POST data
    // var_dump($_POST);

    $title = $_POST['title'];
    $type = $_POST['type'];
    $category = $_POST['category'];
    $date = $_POST['date'];
    $amount = $_POST['amount'];
    $goal_id = isset($_POST['goal_id']) && $_POST['goal_id'] !== '' ? intval($_POST['goal_id']) : NULL;

    // Start a transaction to ensure atomicity
    $conn->begin_transaction();

    try {
        // 1. Insert the new transaction
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, title, type, category, date_issued, amount, goal_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            throw new Exception("Error preparing transaction insertion statement: " . $conn->error);
        }
        
        // Corrected bind_param logic: only bind inside the if/else block
        if ($goal_id === NULL) {
            if (!$stmt->bind_param("issssis", $user_id, $title, $type, $category, $date, $amount, $goal_id)) {
                throw new Exception("Error binding parameters (NULL goal_id): " . $stmt->error);
            }
        } else {
            if (!$stmt->bind_param("issssii", $user_id, $title, $type, $category, $date, $amount, $goal_id)) {
                throw new Exception("Error binding parameters (INT goal_id): " . $stmt->error);
            }
        }
        if (!$stmt->execute()) {
            throw new Exception("Error adding transaction: " . $stmt->error);
        }
        $stmt->close();

        // 2. Update the balance and/or savings in the config table
        if (strtolower($type) == 'income') {
            $update_balance_sql = "UPDATE config SET balance = balance + ? WHERE user_id = ?";
            $stmt_update_balance = $conn->prepare($update_balance_sql);
            if ($stmt_update_balance === false) {
                throw new Exception("Error preparing balance update statement: " . $conn->error);
            }
            $stmt_update_balance->bind_param("di", $amount, $user_id);
            if (!$stmt_update_balance->execute()) {
                throw new Exception("Error updating balance: " . $stmt_update_balance->error);
            }
            $stmt_update_balance->close();
        } elseif (strtolower($type) == 'expense') {
            $update_balance_sql = "UPDATE config SET balance = balance - ? WHERE user_id = ?";
            $stmt_update_balance = $conn->prepare($update_balance_sql);
            if ($stmt_update_balance === false) {
                throw new Exception("Error preparing balance update statement: " . $conn->error);
            }
            $stmt_update_balance->bind_param("di", $amount, $user_id);
            if (!$stmt_update_balance->execute()) {
                throw new Exception("Error updating balance: " . $stmt_update_balance->error);
            }
            $stmt_update_balance->close();

            // Loan payment related update: only if expense category is "Loan" and loan_id provided
            if ($category === 'Loan' && !empty($_POST['loan_id'])) {
                $loan_id = intval($_POST['loan_id']);
                $stmt_loan = $conn->prepare("UPDATE loans SET balance = GREATEST(balance - ?, 0) WHERE id = ? AND user_id = ?");
                if ($stmt_loan === false) throw new Exception("Error preparing loan update: " . $conn->error);
                $stmt_loan->bind_param("dii", $amount, $loan_id, $user_id);
                $stmt_loan->execute();
                $stmt_loan->close();

                $stmt_check = $conn->prepare("SELECT balance FROM loans WHERE id = ? AND user_id = ?");
                $stmt_check->bind_param("ii", $loan_id, $user_id);
                $stmt_check->execute();
                $result = $stmt_check->get_result();
                $loan = $result->fetch_assoc();
                if ($loan && floatval($loan['balance']) <= 0) {
                    $stmt_status = $conn->prepare("UPDATE loans SET status = 'Paid' WHERE id = ? AND user_id = ?");
                    $stmt_status->bind_param("ii", $loan_id, $user_id);
                    $stmt_status->execute();
                    $stmt_status->close();
                }
                $stmt_check->close();
            }
        } elseif (strtolower($type) == 'savings') {
            // For savings, decrease balance and increase savings
            $stmt_update_savings = $conn->prepare("UPDATE config SET balance = balance - ?, savings = savings + ? WHERE user_id = ?");
            if ($stmt_update_savings === false) {
                throw new Exception("Error preparing savings update statement: " . $conn->error);
            }
            $stmt_update_savings->bind_param("ddi", $amount, $amount, $user_id);
            if (!$stmt_update_savings->execute()) {
                throw new Exception("Error updating savings: " . $stmt_update_savings->error);
            }
            $stmt_update_savings->close();

            // If a goal is selected, update the goal's saved_amount
            if ($goal_id) {
                $stmt_update_goal_savings = $conn->prepare("UPDATE goals SET saved_amount = saved_amount + ? WHERE id = ? AND user_id = ?");
                if ($stmt_update_goal_savings === false) {
                    throw new Exception("Error preparing goal savings update statement: " . $conn->error);
                }
                $stmt_update_goal_savings->bind_param("dii", $amount, $goal_id, $user_id);
                if (!$stmt_update_goal_savings->execute()) {
                    throw new Exception("Error updating goal savings: " . $stmt_update_goal_savings->error);
                }
                $stmt_update_goal_savings->close();
            }
        }

        $conn->commit(); // Commit the transaction if all is successful
      $_SESSION['transaction_success'] = "Transaction successfully recorded!";
header("Location: transaction.php");
exit();

    } catch (Exception $e) {
        $conn->rollback(); // Rollback if any error occurs
        echo "<p>Error: " . $e->getMessage() . "</p>"; // Display the error message
    }

    // Redirect to avoid form resubmission
     header("Location: " . $_SERVER['PHP_SELF']); // Uncomment after debugging
    exit(); // Uncomment after debugging
}


// UPDATE logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $id = $_POST['update_id'];
    $title = $_POST['title'];
    $type = $_POST['type'];
    $category = $_POST['category'];
    $date = $_POST['date'];
    $amount = $_POST['amount'];
    $goal_id = isset($_POST['goal_id']) && $_POST['goal_id'] !== '' ? intval($_POST['goal_id']) : NULL;

    // Start a transaction for atomicity
    $conn->begin_transaction();

    try {
        // 1. Get the old transaction details to calculate the balance/savings change
        $stmt_old = $conn->prepare("SELECT amount, type, goal_id FROM transactions WHERE id=? AND user_id=?");
        if ($stmt_old === false) {
            throw new Exception("Error preparing old transaction details statement: " . $conn->error);
        }
        $stmt_old->bind_param("ii", $id, $user_id);
        $stmt_old->execute();
        $result_old = $stmt_old->get_result();
        $old_transaction = $result_old->fetch_assoc();
        $stmt_old->close();

        if (!$old_transaction) {
            throw new Exception("Old transaction not found.");
        }

        $old_amount = $old_transaction['amount'];
        $old_type = $old_transaction['type'];
        $old_goal_id = $old_transaction['goal_id'];

        // 2. Update the transaction
        $stmt_update_transaction = $conn->prepare("UPDATE transactions SET title=?, type=?, category=?, date_issued=?, amount=?, goal_id=? WHERE id=? AND user_id=?");
        if ($stmt_update_transaction === false) {
            throw new Exception("Error preparing update transaction statement: " . $conn->error);
        }
        if ($goal_id === NULL) {
             if (!$stmt_update_transaction->bind_param("ssssiiii", $title, $type, $category, $date, $amount, $goal_id, $id, $user_id)) {
                 throw new Exception("Error binding update parameters (NULL goal_id): " . $stmt_update_transaction->error);
             }
        } else {
             if (!$stmt_update_transaction->bind_param("ssssiiii", $title, $type, $category, $date, $amount, $goal_id, $id, $user_id)) {
                 throw new Exception("Error binding update parameters (INT goal_id): " . $stmt_update_transaction->error);
             }
        }

        if (!$stmt_update_transaction->execute()) {
            throw new Exception("Error updating transaction: " . $stmt_update_transaction->error);
        }
        $stmt_update_transaction->close();
$conn->commit();

$_SESSION['transaction_success'] = "Transaction successfully updated!";
header("Location: transaction.php");
exit();
        $stmt_update_transaction->close();

        // 3. Recalculate and update the balance and/or savings in the config table
        // Revert old transaction's effect
        if (strtolower($old_type) == 'income') {
            $stmt_revert = $conn->prepare("UPDATE config SET balance = balance - ? WHERE user_id = ?");
            if ($stmt_revert === false) { throw new Exception("Error preparing revert income statement: " . $conn->error); }
            $stmt_revert->bind_param("di", $old_amount, $user_id);
            $stmt_revert->execute();
            $stmt_revert->close();
        } elseif (strtolower($old_type) == 'expense') {
            $stmt_revert = $conn->prepare("UPDATE config SET balance = balance + ? WHERE user_id = ?");
            if ($stmt_revert === false) { throw new Exception("Error preparing revert expense statement: " . $conn->error); }
            $stmt_revert->bind_param("di", $old_amount, $user_id);
            $stmt_revert->execute();
            $stmt_revert->close();
        } elseif (strtolower($old_type) == 'savings') {
            $stmt_revert = $conn->prepare("UPDATE config SET savings = savings - ?, balance = balance + ? WHERE user_id = ?");
            if ($stmt_revert === false) { throw new Exception("Error preparing revert savings statement: " . $conn->error); }
            $stmt_revert->bind_param("ddi", $old_amount, $old_amount, $user_id);
            $stmt_revert->execute();
            $stmt_revert->close();
            // Revert goal savings if applicable
            if ($old_goal_id) {
                $stmt_revert_goal_savings = $conn->prepare("UPDATE goals SET saved_amount = saved_amount - ? WHERE id = ? AND user_id = ?");
                if ($stmt_revert_goal_savings === false) { throw new Exception("Error preparing revert goal savings statement: " . $conn->error); }
                $stmt_revert_goal_savings->bind_param("dii", $old_amount, $old_goal_id, $user_id);
                $stmt_revert_goal_savings->execute();
                $stmt_revert_goal_savings->close();
            }
        }

        // Apply new transaction's effect
        if (strtolower($type) == 'income') {
            $stmt_apply = $conn->prepare("UPDATE config SET balance = balance + ? WHERE user_id = ?");
            if ($stmt_apply === false) { throw new Exception("Error preparing apply income statement: " . $conn->error); }
            $stmt_apply->bind_param("di", $amount, $user_id);
            $stmt_apply->execute();
            $stmt_apply->close();
        } elseif (strtolower($type) == 'expense') {
            $stmt_apply = $conn->prepare("UPDATE config SET balance = balance - ? WHERE user_id = ?");
            if ($stmt_apply === false) { throw new Exception("Error preparing apply expense statement: " . $conn->error); }
            $stmt_apply->bind_param("di", $amount, $user_id);
            $stmt_apply->execute();
            $stmt_apply->close();
        } elseif (strtolower($type) == 'savings') {
            $stmt_apply = $conn->prepare("UPDATE config SET savings = savings + ?, balance = balance - ? WHERE user_id = ?");
            if ($stmt_apply === false) { throw new Exception("Error preparing apply savings statement: " . $conn->error); }
            $stmt_apply->bind_param("ddi", $amount, $amount, $user_id);
            $stmt_apply->execute();
            $stmt_apply->close();
            // Apply new goal savings if applicable
            if ($goal_id) {
                $stmt_apply_goal_savings = $conn->prepare("UPDATE goals SET saved_amount = saved_amount + ? WHERE id = ? AND user_id = ?");
                if ($stmt_apply_goal_savings === false) { throw new Exception("Error preparing apply goal savings statement: " . $conn->error); }
                $stmt_apply_goal_savings->bind_param("dii", $amount, $goal_id, $user_id);
                $stmt_apply_goal_savings->execute();
                $stmt_apply_goal_savings->close();
            }
        }

        $conn->commit(); // Commit the transaction
    } catch (Exception $e) {
        $conn->rollback(); // Rollback if any error occurs
        echo "<p>Error: " . $e->getMessage() . "</p>"; // Display the error message
    }

    // Redirect to avoid form resubmission and remove ?edit_id param
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?')); // Uncomment after debugging
     exit(); // Uncomment after debugging
}

// FETCH transaction data if edit_id is set
$editTransaction = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE id=? AND user_id=?");
    if ($stmt === false) {
        die("Error preparing edit transaction fetch statement: " . $conn->error);
    }
    $stmt->bind_param("ii", $edit_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $editTransaction = $result->fetch_assoc();
    }
    $stmt->close();
}

// Fetch active goals for the dropdown in Savings form
$active_goals = [];
$stmt_goals = $conn->prepare("SELECT id, title, target_amount, saved_amount FROM goals WHERE user_id = ? AND status = 'active'");
if ($stmt_goals === false) {
    die("Error preparing active goals fetch statement: " . $conn->error);
}
$stmt_goals->bind_param("i", $user_id);
$stmt_goals->execute();
$result_goals = $stmt_goals->get_result();
while ($row_goal = $result_goals->fetch_assoc()) {
    $active_goals[] = $row_goal;
}
$stmt_goals->close();

$active_loans = [];
$stmt_goals = $conn->prepare("SELECT id, name, balance FROM loans WHERE user_id = ? AND status = 'Ongoing'");
if ($stmt_goals === false) {
    die("Error preparing active goals fetch statement: " . $conn->error);
}
$stmt_goals->bind_param("i", $user_id);
$stmt_goals->execute();
$result_goals = $stmt_goals->get_result();
while ($row_goal = $result_goals->fetch_assoc()) {
    $active_loans[] = $row_goal;
}
$stmt_goals->close();


$today = date('Y-m-d');

// Initialize totals
$income = 0;
$total_expenses = 0;
$savings = 0;

// Fetch all today's transactions
$stmt = $conn->prepare("SELECT type, amount FROM transactions WHERE user_id = ? AND DATE(date_issued) = ?");
if ($stmt === false) {
    die("Error preparing transaction aggregation: " . $conn->error);
}
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $type = strtolower($row['type']);
    $amount = floatval($row['amount']);

    if ($type === 'income') {
        $income += $amount;
    } elseif ($type === 'expense') {
        $total_expenses += $amount;
    } elseif ($type === 'savings') {
        $savings += $amount;
    }
}
$stmt->close();

$balance = $_SESSION['currentbalance'] ?? ($income - $total_expenses);
$messages = [];

if ($income === 0) {
    $messages[] = "📭 You have no income recorded yet today.";
} else {
    $percent_spent = ($total_expenses / $income) * 100;

    if ($percent_spent >= 100) {
        $messages[] = "🚨 You've spent <strong>₱" . number_format($total_expenses, 2) . "</strong>, which is <strong>100% or more</strong> of your monthly expenses today.";
    } elseif ($percent_spent >= 90) {
        $messages[] = "⚠️ You've spent <strong>" . number_format($percent_spent, 1) . "%</strong> of your income today. Be cautious!";
    } elseif ($percent_spent >= 70) {
        $messages[] = "💸 You've spent <strong>" . number_format($percent_spent, 1) . "%</strong> of your income today.";
    } elseif ($percent_spent >= 50) {
        $messages[] = "🧾 You've used <strong>" . number_format($percent_spent, 1) . "%</strong> of your income today.";
    } else {
        $messages[] = "✅ Great job! You're spending only <strong>" . number_format($percent_spent, 1) . "%</strong> of your income today.";
    }

    // Balance feedback
    if ($balance > 2000) {
        $messages[] = "✅ You have a healthy balance. Keep it up!";
    } elseif ($balance > 0 && $balance <= 2000) {
        $messages[] = "⚠️ Your balance is getting low. Consider adjusting your spending.";
    } elseif ($balance <= 0) {
        $messages[] = "🔴 Warning: Your balance is depleted. You may be overspending.";
    }
}

// Savings message
if ($savings === 0) {
    $messages[] = "💡 You haven’t added any savings today. Try to save even a small amount.";
} else {
    $messages[] = "🎯 Good job! You've saved <strong>₱" . number_format($savings, 2) . "</strong> today.";
}

// Output the reminder box
echo <<<HTML
<style>
.reminder-box {
    margin-top: 20px;
    border: 1px solid #ddd;
    border-radius: 10px;
    background: #fdfdfd;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    overflow: hidden;
    transition: all 0.3s ease;
}

.reminder-header {
    padding: 10px 15px;
    
    color: Black;
    cursor: pointer;
    font-weight: bold;
    user-select: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.reminder-body {
    display: none;
    padding: 10px 15px;
    
}

.reminder-item {
    margin-bottom: 8px;
    font-size: 14px;
}
</style>

<div class='reminder-box'>
    <div class='reminder-header' onclick='toggleReminderBody()'>
        📌 Today's Financial Reminders
        <span id="reminder-arrow">▼</span>
    </div>
    <div class='reminder-body' id='reminder-body'>
HTML;

foreach ($messages as $msg) {
    echo "<div class='reminder-item'>$msg</div>";
}

echo <<<HTML
    </div>
</div>

<script>
function toggleReminderBody() {
    const body = document.getElementById('reminder-body');
    const arrow = document.getElementById('reminder-arrow');
    const isVisible = body.style.display === 'block';

    body.style.display = isVisible ? 'none' : 'block';
    arrow.textContent = isVisible ? '▼' : '▲';
}
</script>
HTML;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Advisor</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Overview horizontal rectangle container */
        .overview-container {
            display: flex;
            justify-content: space-between;
            background: #fff;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin: 20px 0;
            max-width: 100%;
            margin-left: auto;
            margin-right: auto;
            font-family: 'Poppins', sans-serif;
        }

        .stat-box {
            flex: 1;
            background: #f9fafb;
            border-radius: 10px;
            text-align: center;
            padding: 16px 24px;
            margin: 0 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.07);
            min-width: 0;
        }

        .stat-box:first-child {
            margin-left: 0;
        }

        .stat-box:last-child {
            margin-right: 0;
        }

        .stat-box h3 {
            font-weight: 600;
            color: #1d2a62;
            margin-bottom: 10px;
            font-size: 20px;
        }

        .stat-box p {
            font-weight: 700;
            font-size: 24px;
            color: #2ecc71; /* green for positive balances */
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
    <div class="page">
      <div class="sidebarspace"><?php include 'sidebar.php'?> </div>

      <div class="transaction-header-container">
          <div class="headerpos">

          <h2>Transactions</h2>
              

              <!-- Overview horizontal rectangle with summary stats -->
              <div class="overview-container" role="region" aria-label="Financial overview summary">
                  <div class="stat-box">
                      <h3>Current Balance</h3>
                      <p>₱<?= number_format($_SESSION['currentbalance'] ?? 0, 2) ?></p>
                  </div>
                  <div class="stat-box">
                      <h3>Total Expenses</h3>
                      <p>₱<?= number_format($totalExpenses, 2) ?></p>
                  </div>
                  <div class="stat-box">
                      <h3>Total Savings</h3>
                      <p>₱<?= number_format($totalSavings, 2) ?></p>
                  </div>
                  <div class="stat-box">
                      <h3>Outstanding Loans</h3>
                      <p>₱<?= number_format($totalLoans, 2) ?></p>
                  </div>
              </div>

              <div class="transaction-container dashboard-header-row">
                  
              
              <div class="form-content">
                  <form id="income" class="transaction-form" style="display:none;" method="post">
                      <input type="hidden" name="type" value="Income">
                      <h5>Add Income</h5>
                      <h5>Name</h5>
                      <input name="title" type="text" placeholder="Income Name" required>
                      <h5>Category</h5>
                      <select name="category" required>
                          <option value="" disabled selected>Select Income Category</option>
                          <option value="Allowance">Allowance</option>
                          <option value="Salary">Salary</option>
                          <option value="Other">Other</option>
                      </select>
                      <h5 for="date"><h5>Date & Time</h5>
                      <input id="date" type="datetime-local" value="<?= date('Y-m-d\TH:i') ?>"  name="date" required>
                      <h5>Amount</h5>
                      <input name="amount" type="number" step="0.01" placeholder="₱ 0.00" required>
                      <button type="submit">Add</button>
                  </form>

                  <form id="expenses" class="transaction-form" style="display:none;" method="post">
                      <input type="hidden" name="type" value="Expense">
                      <h5>Add Expense</h5>
                      <h5>Name</h5>
                      <input name="title" type="text" placeholder="Expense Name" required>
                      <h5>Category</h5>
                      <select name="category" id="expensecategory" required>
                          </option>
                          <option value="" disabled selected>Select Expense Category</option>
                          <option value="Food">Food</option>
                          <option value="Transportation">Transportation</option>
                          <option value="Utilities">Utilities</option>
                          <option value="Entertainment">Entertainment</option>
                          <option value="Medical">Medical</option>
                          <option value="Education">Education</option>
                          <option value="Loan">Loan Payment</option>
                          <option value="Other">Other</option>
                      </select>
                      <div id="loan-selection" style="display: none;">
                          <label for="loan_id">Select Loan:</label>
                          <select name="loan_id" id="loan_id">
                              <option value="" disabled>No specific loan</option>
                              <?php foreach ($active_loans as $loan): ?>
                                  <option value="<?= $loan['id'] ?>" data-balance="<?= $loan['balance'] ?>">
  <?= htmlspecialchars($loan['name']) ?> (₱<?= number_format($loan['balance'], 2) ?>)
</option>
                              <?php endforeach; ?>
                          </select>
                      </div>
                      <label for="date"><h5>Date & Time</h5></label>
                      <input id="date" type="datetime-local" value="<?= date('Y-m-d\TH:i') ?>" name="date" required>
                      <h5>Amount</h5>
                      <input name="amount" id="amountexpense" type="number" step="0.01" placeholder="₱ 0.00" required>
                      <button id='addTransactionBtn' type="submit">Add</button>
                  </form>

                  <form id="savings" class="transaction-form" style="display:none;" method="post">
                      <input type="hidden" name="type" value="Savings">
                      <h5>Add Savings</h5>
                      <h5>Name</h5>
                      <input name="title" type="text" placeholder="Savings Name" required>
                      <h5 for="savings-category">Category</h5>
                      <select name="category" id="savings-category" required>
                          <option value="" disabled selected>Select Savings Category</option>
                          <option value="General Savings">General Savings</option>
                          <option value="Goal">Fund a Goal</option>
                      </select>

                      <div id="goal-selection" style="display: none;">
                          <label for="goal_id">Select Goal:</label>
                          <select name="goal_id" id="goal_id">
                              <option value="">No specific goal</option>
                              <?php foreach ($active_goals as $goal): ?>
                                  <option value="<?= $goal['id'] ?>" data-balance2="<?= ((float)$goal['target_amount']- (float)$goal['saved_amount']) ?>"><?= htmlspecialchars($goal['title']) ?></option>
                              <?php endforeach; ?>
                          </select>
                      </div>

                      <label for="date"><h5>Date & Time</h5></label>
                      <input id="date" type="datetime-local" name="date" value="<?= date('Y-m-d\TH:i') ?>" required>
                      <h5>Amount</h5>
                      <input name="amount" id="amountsavings"  type="number" step="0.01" placeholder="₱ 0.00" required>
                      <button id="savingAddButton" type="submit">Add</button>
                  </form>
              </div>

              <?php
              // Modified query to also fetch goal_title if goal_id is present
              $sql = "SELECT t.*, g.title AS goal_title FROM transactions t LEFT JOIN goals g ON t.goal_id = g.id WHERE t.user_id = ? ORDER BY t.date_issued DESC";
              $stmt = $conn->prepare($sql);
              if ($stmt === false) {
                  die("Error preparing transaction display query: " . $conn->error);
              }
              $stmt->bind_param("i", $user_id);
              $stmt->execute();
              $result = $stmt->get_result();
              ?>
              <table class="transaction-table">
                  <thead>
                      <tr>
                          <td style="font-weight: bold;font-size: 2rem ; color: #1D2A62;text-align: center">Date</td>
                          <td style="font-weight: bold;font-size: 2rem ; color: #1D2A62;text-align: center">Name</td>
                          <td style="font-weight: bold;font-size: 2rem ; color: #1D2A62;text-align: center">Category</td>
                          <td style="font-weight: bold;font-size: 2rem ; color: #1D2A62;text-align: center">Type</td>
                          <td style="font-weight: bold;font-size: 2rem ; color: #1D2A62;text-align: center">Amount</td>
                       <!-- <td style="font-weight: bold;font-size: 2rem ; color: #1D2A62;text-align: center">Goal/Loan ID</td> <td></td> -->
                      </tr>
                  </thead>
                  <tbody>
                      <?php while ($row = $result->fetch_assoc()): ?>
                          <?php if ($editTransaction && $editTransaction['id'] == $row['id']): ?>
                              <tr>
                                  <form method="post" action="">
                                      <input type="hidden" name="update_id" value="<?= $editTransaction['id'] ?>">
                                      <td><input type="datetime-local" name="date" value="<?= htmlspecialchars(str_replace(' ', 'T', $editTransaction['date_issued'])) ?>" required></td>
                                      <td><input type="text" name="title" value="<?= htmlspecialchars($editTransaction['title']) ?>" required></td>
                                      <td>
                                          <input type="text" name="category" value="<?= htmlspecialchars($editTransaction['category']) ?>" required>
                                      </td>
                                      <td>
                                          <select name="type" required>
                                              <option value="Income" <?= $editTransaction['type'] == 'Income' ? 'selected' : '' ?>>Income</option>
                                              <option value="Expense" <?= $editTransaction['type'] == 'Expense' ? 'selected' : '' ?>>Expense</option>
                                              <option value="Savings" <?= $editTransaction['type'] == 'Savings' ? 'selected' : '' ?>>Savings</option>
                                          </select>
                                      </td>
                                      <td><input type="number" step="0.01" name="amount" value="<?= htmlspecialchars($editTransaction['amount']) ?>" required></td>
                                      <td>
                                          <?php if ($editTransaction['type'] == 'Savings'): ?>
                                          <select name="goal_id">
                                              <option value="">No specific goal</option>
                                              <?php foreach ($active_goals as $goal): ?>
                                                  <option value="<?= $goal['id'] ?>" <?= ($editTransaction['goal_id'] == $goal['id']) ? 'selected' : '' ?>><?= htmlspecialchars($goal['title']) ?></option>
                                              <?php endforeach; ?>
                                          </select>
                                          <?php else: ?>
                                              N/A <input type="hidden" name="goal_id" value="">
                                          <?php endif; ?>
                                      </td>
                                      <td>
                                          <button type="submit">Save</button>
                                          <button class= "cancel-button" ><a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" class="cancels">Cancel</a> </button>
                                      </td>
                                  </form>
                              </tr>
                          <?php else: ?>
                              <tr>
                                  <td><?= htmlspecialchars($row['date_issued']) ?></td>
                                  <td><?= htmlspecialchars($row['title']) ?></td>
                                  <td><?= htmlspecialchars($row['category']) ?></td>
                                  <td><?= htmlspecialchars($row['type']) ?></td>
                                  <td><?= htmlspecialchars($row['amount']) ?></td>
                                  <!-- <td><?= htmlspecialchars($row['goal_title'] ?? 'N/A') ?></td> -->
                                  <td class="table-actions">
                                      <a href="?edit_id=<?= $row['id'] ?>"><img src="images/edit3.png" alt="Edit"></a>
                                      <a href="?delete_id=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this transaction?')"><img src="images/deletes.png" alt="Delete"></a>
                                  </td>
                              </tr>
                          <?php endif; ?>
                      <?php endwhile; ?>
                  </tbody>
              </table>
              <?php
              $stmt->close();
              ?>
          </div>
      </div>
    </div>
    </div>
    <div class="fab-container">
        <div class="fab-button" id="mainFab">
            +
            <span class="fabtooltip">Add Transaction</span>
        </div>

        <div class="fab-options" id="fabOptions">
            <button class="fab-option-button tab-button" onclick="showForm('income')">Income</button>
            <button class="fab-option-button tab-button" onclick="showForm('expenses')">Expenses</button>
            <button class="fab-option-button tab-button" onclick="showForm('savings')">Savings</button>
        </div>
    </div>
</body>

<script>
 const currentBalance = <?= json_encode($_SESSION['currentbalance'] ?? 0); ?>;

document.addEventListener("DOMContentLoaded", function () {
    const amountInput = document.getElementById('amountexpense');
    const amountSavingInput = document.getElementById('amountsavings');
    const categorySelect = document.getElementById('expensecategory');
    const loanIdSelect = document.getElementById('loan_id');
    const categorySelect2 = document.getElementById('savings-category');
    const goalIdSelect= document.getElementById('goal_id');
    const addButton = document.getElementById('addTransactionBtn');
    const savingaddButton = document.getElementById('savingAddButton');

    function updateMaxAmountFromLoan() {
        validateForm()
        if (!loanIdSelect || !categorySelect) return;

        const selectedOption = loanIdSelect.options[loanIdSelect.selectedIndex];
        const loanBalance = parseFloat(selectedOption?.dataset?.balance || 0);

        if (categorySelect.value === 'Loan' && loanBalance > 0) {
            amountInput.max = loanBalance;
        } else {
            amountInput.removeAttribute('max');
        }

    }
    function updateMaxAmountFromGoal() {
        validateForm()
        if (!goalIdSelect || !categorySelect2) return;

        const selectedOption2 = goalIdSelect.options[goalIdSelect.selectedIndex];
        const loanBalance = parseFloat(selectedOption2?.dataset?.balance2 || 0);

        if (categorySelect2.value === 'Goal' && loanBalance > 0) {
            amountSavingInput.max = loanBalance;
        } else {
            amountSavingInput.removeAttribute('max');
        }

    }

    function validateForm() {
        const amount = parseFloat(amountInput.value) || 0;
        const savingamount = parseFloat(amountSavingInput.value) || 0;
        const category = categorySelect.value;
        const loanId = loanIdSelect?.value;
        const category2 = categorySelect2.value;
        const goalId = goalIdSelect?.value;

        let disable = false;
        let disable2 = false;

        if (amount > currentBalance) {
            disable = true;
        }

        if (savingamount > currentBalance) {
            disable2 = true;
        }

        if (category2 === 'Goal' && (!goalId || goalId === '')) {
            disable2 = true;
        }

        if (category === 'Loan' && (!loanId || loanId === 'no specific loan')) {
            disable = true;
        }

        addButton.disabled = disable;
        addButton.classList.toggle('disabled', disable);

        savingaddButton.disabled = disable2;
        savingaddButton.classList.toggle('disabled', disable2);
    }

    amountSavingInput.addEventListener('input', function () {
        const max = parseFloat(amountSavingInput.max);
        const current = parseFloat(amountSavingInput.value);
        if (!isNaN(max) && current > max) {
            amountSavingInput.value = max;
        }
        validateForm();
    });

    categorySelect2?.addEventListener('change', () => {
        updateMaxAmountFromGoal();
        validateForm();
    });

    goalIdSelect?.addEventListener('change', () => {
        updateMaxAmountFromGoal();
        validateForm();
    });


    // Ensure event listeners are attached once
    amountInput.addEventListener('input', function () {
        const max = parseFloat(amountInput.max);
        const current = parseFloat(amountInput.value);
        if (!isNaN(max) && current > max) {
            amountInput.value = max;
        }
        validateForm();
    });

    categorySelect?.addEventListener('change', () => {
        updateMaxAmountFromLoan();
        validateForm();
    });

    loanIdSelect?.addEventListener('change', () => {
        updateMaxAmountFromLoan();
        validateForm();
    });

    // Initial run (in case values are pre-filled)
    updateMaxAmountFromLoan();
    updateMaxAmountFromGoal();
    validateForm();
});
  function showForm(formId) {
    const forms = document.querySelectorAll('.transaction-form');
    forms.forEach(form => form.style.display = 'none');
    document.getElementById(formId).style.display = 'flex';

    // Show/hide goal selection for Savings form
    if (formId === 'savings') {
        document.getElementById('savings-category').addEventListener('change', function() {
            if (this.value === 'Goal') {
                document.getElementById('goal-selection').style.display = 'block';
            } else {
                document.getElementById('goal-selection').style.display = 'none';
                document.getElementById('goal_id').value = ''; // Reset selected goal
            }
        });
        // Initial check in case "Fund a Goal" is pre-selected (e.g., after an edit or refresh)
        if (document.getElementById('savings-category').value === 'Goal') {
             document.getElementById('goal-selection').style.display = 'block';
        } else {
            document.getElementById('goal-selection').style.display = 'none';
        }

        // If coming from a "Fund Goal" link, pre-select the goal
        const urlParams = new URLSearchParams(window.location.search);
        const fundGoalId = urlParams.get('fund_goal_id');
        if (fundGoalId) {
            document.getElementById('savings-category').value = 'Goal';
            document.getElementById('goal-selection').style.display = 'block';
            document.getElementById('goal_id').value = fundGoalId;
        }
    } else {
        // Ensure goal selection is hidden for other forms
        const goalSelection = document.getElementById('goal-selection');
        if (goalSelection) goalSelection.style.display = 'none';
        const savingsCategory = document.getElementById('savings-category');
        if (savingsCategory) savingsCategory.value = ''; // Reset category
    }

    if (formId === 'expenses') {
        document.getElementById('expensecategory').addEventListener('change', function() {
            if (this.value === 'Loan') {
                document.getElementById('loan-selection').style.display = 'block';
            } else {
                document.getElementById('loan-selection').style.display = 'none';
                document.getElementById('loan-id').value = ''; // Reset selected goal
            }
        });
        // Initial check in case "Fund a Goal" is pre-selected (e.g., after an edit or refresh)
        if (document.getElementById('expensecategory').value === 'Goal') {
             document.getElementById('loan-selection').style.display = 'block';
        } else {
            document.getElementById('loan-selection').style.display = 'none';
        }

        // If coming from a "Fund Goal" link, pre-select the goal
        const urlParams = new URLSearchParams(window.location.search);
        const fundLoadId = urlParams.get('fund_loan_id');
        if (fundLoanId) {
            document.getElementById('expensecategory').value = 'Goal';
            document.getElementById('loan-selection').style.display = 'block';
            document.getElementById('loan_id').value = fundLoadId;
        }
    } else {
        // Ensure goal selection is hidden for other forms
        const loanSelection = document.getElementById('loan-selection');
        if (loanSelection) loanSelection.style.display = 'none';
        const expenseCategory = document.getElementById('expensecategory');
        if (expenseCategory) expenseCategory.value = ''; // Reset category
    }
  }




  document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const prefillDate = urlParams.get('date');
    const fundGoalId = urlParams.get('fund_goal_id');
    const editId = urlParams.get('edit_id');


    if (prefillDate) {
        // Find ALL date input fields by their type or name
        const dateInputs = document.querySelectorAll('input[type="datetime-local"]'); // Changed to datetime-local
        dateInputs.forEach(input => {
            input.value = prefillDate;
        });
    }

    if (fundGoalId) {
        showForm('savings');
    } else if (!editId) {
        // If not funding a goal and not editing, default to income form or hide all
        // You might want to default to 'income' or leave all hidden
        // showForm('income'); // Uncomment to default to income form on page load
    }

    // Handle edit form display on load
    if (editId) {
        // The PHP already pre-fills the edit form row
        // You might need to adjust the display logic for the goal selection in the edit row
        const editTypeSelect = document.querySelector(`input[type="hidden"][name="update_id"][value="${editId}"]`).closest('form').querySelector('select[name="type"]');
        if (editTypeSelect && editTypeSelect.value === 'Savings') {
            // No direct goal selection div in edit row, but ensure the logic for the select itself is sound
            // This part is mostly handled by PHP rendering the correct select element
        }
    }
});
// FAB functionality
        document.addEventListener('DOMContentLoaded', () => {
            const mainFab = document.getElementById('mainFab');
            const fabOptions = document.getElementById('fabOptions');

            if (mainFab && fabOptions) {
                mainFab.addEventListener('click', () => {
                    fabOptions.classList.toggle('show');
                    // Optional: Rotate the plus icon on click
                    
                });
            }
        });


    
</script>
<?php include 'chatbot_widget.php'; ?>
</html>
<?php
$conn->close();
?>
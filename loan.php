<?php 
date_default_timezone_set('Asia/Manila'); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Advisor - Loans</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php
session_start();
include 'db_connect.php';

// Handle loan updates
if (isset($_POST['update_loan'])) {
    $id = $_POST['update_id'];
    $date = $_POST['date'];
    $name = $_POST['name'];
    $category = $_POST['category'];
    $type = $_POST['type'];
    $amount = $_POST['amount'];
    $due_date = $_POST['due_date']; 

    $stmt = $conn->prepare("UPDATE loans SET date = ?, name = ?, category = ?, type = ?, amount = ?, due_date = ? WHERE id = ?");
    $stmt->bind_param("ssssdsi", $date, $name, $category, $type, $amount, $due_date, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: loan.php");
    exit;
}

// DELETE LOAN
if (isset($_GET['delete_loan_id'])) {
    $loan_id = (int) $_GET['delete_loan_id'];
    $user_id = $_SESSION['user_id'];
    
    // Get the balance of the loan
    $stmt = $conn->prepare("SELECT balance FROM loans WHERE id = ?");
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($loan = $result->fetch_assoc()) {
        $new_balance = $loan['balance'];
        $stmt->close();

        // Update user's balance
        $updatebalance = $conn->prepare("UPDATE config SET balance = balance - ? WHERE user_id = ?");
        $updatebalance->bind_param("di", $new_balance, $user_id);
        $updatebalance->execute();
        $updatebalance->close();
    }

    // Delete the loan
    $stmt = $conn->prepare("DELETE FROM loans WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $loan_id, $user_id);
    $stmt->execute();
    $stmt->close();

    echo "<script>window.location.href='loan.php';</script>";
    exit;
}

// EDIT LOAN
if (isset($_POST['edit_loan'])) {
    $loan_id      = (int) $_POST['loan_id'];
    $loan_name    = $_POST['loan_name'];
    $loan_type_id = (int) $_POST['loan_type_id'];
    $loan_date    = $_POST['loan_date'];   // YYYY‑MM‑DD
    $amount       = (float) $_POST['amount'];      // original amount
    $balance      = (float) $_POST['balance'];     // current balance
    $due_date     = $_POST['due_date'];             // New due date field

    $status = ($balance == 0) ? 'Paid' : 'Ongoing';

    if ($amount < $balance) {
        echo "<script>alert('Error: Loan amount cannot be less than the balance.'); window.location.href = 'loan.php';</script>";
        exit;
    }

    $sql = "UPDATE loans
            SET name         = ?,
                loan_type_id = ?,
                date         = ?,
                amount       = ?,
                balance      = ?,
                status       = ?,
                due_date     = ?
            WHERE id = ? AND user_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sisddssii", $loan_name, $loan_type_id, $loan_date, $amount, $balance, $status, $due_date, $loan_id, $_SESSION['user_id']);

    if ($stmt->execute()) {
        // success
        echo "<script>
                alert('Loan updated successfully.');
                window.location.href = 'loan.php';
              </script>";
    } else {
        // failure
        echo "<script>alert('Error updating loan.');</script>";
    }
    $stmt->close();
    exit;
}

// Handle loan payments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['loan_id'], $_POST['payment_amount'])) {
    $loan_id = (int) $_POST['loan_id'];
    $payment_amount = (float) $_POST['payment_amount'];

    // Get current loan balance
    $stmt = $conn->prepare("SELECT balance FROM loans WHERE id = ?");
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($loan = $result->fetch_assoc()) {
        $new_balance = $loan['balance'] - $payment_amount;
        if ($new_balance < 0) $new_balance = 0;

        // Update loan balance
        $update = $conn->prepare("UPDATE loans SET balance = ?, status = ? WHERE id = ?");
        $status = $new_balance === 0 ? "Paid" : "Ongoing";
        $update->bind_param("isi", $new_balance, $status, $loan_id);
        $update->execute();

        // Insert transaction record
        $user_id = $_SESSION['user_id']; 
        $type = "Loan Payment";
        $date_issued = date('Y-m-d H:i:s');
        $insert = $conn->prepare("INSERT INTO transactions (user_id, type, amount, date_issued, title, loan_id)
                                  VALUES (?, ?, ?, ?, ?, ?)");
        $title = "Loan Payment for Loan ID $loan_id";
        $insert->bind_param("issssi", $user_id, $type, $payment_amount, $date_issued, $title, $loan_id);
        $insert->execute();
    }
}

$loggedInUserId = $_SESSION['user_id'] ?? null; 
if ($loggedInUserId === null) {
    die("User  not logged in. Please log in to view your loans.");
}

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $today = new DateTime();
    $today->setTime(0, 0);
    $threeDaysFromNow = (clone $today)->modify('+3 days')->format('Y-m-d');

    $stmt = $conn->prepare("SELECT name, due_date FROM loans 
                            WHERE user_id = ? AND status = 'Ongoing' 
                            AND due_date IS NOT NULL 
                            AND due_date BETWEEN CURDATE() AND ?
                            ORDER BY due_date ASC");
    $stmt->bind_param("is", $userId, $threeDaysFromNow);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $loanReminders = [];

        while ($row = $result->fetch_assoc()) {
            $loanName = htmlspecialchars($row['name']);
            $dueDate = new DateTime($row['due_date']);
            $dueDate->setTime(0, 0);
            $daysLeft = (int)$today->diff($dueDate)->format('%r%a');

            if ($daysLeft === 0) {
                $loanReminders[] = "📅 <strong>$loanName</strong> is <strong>due today</strong>. Don’t forget to pay!";
            } elseif ($daysLeft > 0) {
                $plural = $daysLeft === 1 ? 'day' : 'days';
                $loanReminders[] = "📅 <strong>$loanName</strong> is due in <strong>$daysLeft $plural</strong>. Pay soon to avoid overdue.";
            }
        }

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
  
    color: black;
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
    color: #333;
}
</style>

<div class='reminder-box'>
    <div class='reminder-header' onclick='toggleLoanReminderBody()'>
        📌 Loan Due Reminders
        <span id="loan-reminder-arrow">▼</span>
    </div>
    <div class='reminder-body' id='loan-reminder-body'>
HTML;

        foreach ($loanReminders as $reminder) {
            echo "<div class='reminder-item'>$reminder</div>";
        }

        echo <<<HTML
    </div>
</div>

<script>
function toggleLoanReminderBody() {
    const body = document.getElementById('loan-reminder-body');
    const arrow = document.getElementById('loan-reminder-arrow');
    const isVisible = body.style.display === 'block';

    body.style.display = isVisible ? 'none' : 'block';
    arrow.textContent = isVisible ? '▼' : '▲';
}
</script>
HTML;
    }

    $stmt->close();
}
?>


<div class="page">
  <div class="sidebarspace"><?php include 'sidebar.php'; ?></div>
  <div class="loan-header-container">
    <div class="loan-headerpos">
      <h2>Loan</h2>
      <p class="subtitle">Manage your loan.</p>

      <div class="loan-summary">
        <?php
        $totalBorrowed = 0;
        $totalRepaid = 0;
        $totalBalance = 0;

        if ($conn) {
            try {
                $stmtBorrowed = $conn->prepare("SELECT SUM(amount) AS total_borrowed FROM loans WHERE user_id = ?");
                $stmtBorrowed->bind_param("i", $loggedInUserId);
                $stmtBorrowed->execute();
                $resultBorrowed = $stmtBorrowed->get_result();
                $totalBorrowed = $resultBorrowed->fetch_assoc()['total_borrowed'] ?? 0;
                $stmtBorrowed->close();

                $stmtBalance = $conn->prepare("SELECT SUM(balance) AS total_balance FROM loans WHERE user_id = ?");
                $stmtBalance->bind_param("i", $loggedInUserId);
                $stmtBalance->execute();
                $resultBalance = $stmtBalance->get_result();
                $totalBalance = $resultBalance->fetch_assoc()['total_balance'] ?? 0;
                $stmtBalance->close();

                $totalRepaid = $totalBorrowed - $totalBalance;
            } catch (Exception $e) {
                echo "Error fetching summary: " . $e->getMessage();
            }
        }
        ?>
        <div class="loan-summary-item">
        <h4>Total borrowed: </h4> <span><?php echo 'P' . number_format($totalBorrowed, 2); ?></span></div>
        <div class="loan-summary-item">
            <h4>Total repaid: </h4>
            <span><?php echo 'P' . number_format($totalRepaid, 2); ?></span>
          </div>
          <div class="loan-summary-item">
            <h4>Total balance: </h4>
            <span><?php echo 'P' . number_format($totalBalance, 2); ?></span>
          </div>
        </div>
      </div>

      <div class="loan-container">
        <div class="loan-tabs">
          <button class="loan-tab-button" onclick="showForm('loan')">Add Loan</button>
        </div>
        <div class="loan-form-content">
          <form id="loan" class="loan-form" style="display:none;" method="POST" action="">
            <h6>Add Loan</h6>
            <span class="close-btn" onclick="closeForm('loan')">×</span>
            <h5><br>Loan Name</h5>
            <input type="text" name="loan_name" placeholder="Loan Name" required>
            <h5>Loan Type<br></h5>
            <select name="loan_type_id" required>
              <option value="">Select Loan Type</option>
              <?php
              $typeQuery = "SELECT id, type_name FROM loan_types ORDER BY type_name ASC";
              $typeResult = $conn->query($typeQuery);
              if ($typeResult && $typeResult->num_rows > 0) {
                  while ($row = $typeResult->fetch_assoc()) {
                      echo "<option value=\"" . $row['id'] . "\">" . htmlspecialchars($row['type_name']) . "</option>";
                  }
              }
              ?>
            </select>
            <label><br><h5>Loan Date</h5></label>
            <input type="datetime-local" value="<?= date('Y-m-d\TH:i') ?>" name="loan_date" required>
            <label><br><h5>Due Date</h5></label> 
            <input type="date" name="due_date" class="form-control" required min="<?= date('Y-m-d') ?>">
            <label><br><h5>Loan Amount</h5></label>
            <input type="number" name="amount" placeholder="Amount" step="0.01" min="0" required>
            <button type="submit" name="add_loan">Add</button>
          </form>
          

          <?php
          if (isset($_POST['add_loan'])) {
            $loanName = $_POST['loan_name'];
            $loanTypeId = $_POST['loan_type_id'];
            $loanDate = $_POST['loan_date'];
            $dueDate = $_POST['due_date']; 
            $amount = round((float) $_POST['amount'], 2);
            $initialBalance = $amount;
            $status = 'Ongoing';

            if ($conn) {
                $stmtIncome = $conn->prepare("SELECT income FROM config WHERE user_id = ?");
                $stmtIncome->bind_param("i", $loggedInUserId);
                $stmtIncome->execute();
                $resultIncome = $stmtIncome->get_result();
                $incomeData = $resultIncome->fetch_assoc();
                $stmtIncome->close();

                if (!$incomeData) {
                    echo "<script>alert('Unable to determine your income. Cannot proceed.');</script>";
                    return;
                }

                $monthlyIncome = (float) $incomeData['income'];
                $maxAllowedBalance = $monthlyIncome * 0.4;

                $stmtOverdue = $conn->prepare("SELECT COUNT(*) AS overdue_count FROM loans WHERE user_id = ? AND balance > 0 AND due_date IS NOT NULL AND due_date <= CURDATE()");
                $stmtOverdue->bind_param("i", $loggedInUserId);
                $stmtOverdue->execute();
                $resultOverdue = $stmtOverdue->get_result();
                $overdueCount = (int) ($resultOverdue->fetch_assoc()['overdue_count'] ?? 0);
                $stmtOverdue->close();

                if ($overdueCount > 0) {
                    echo "<script>
                        alert('You have overdue loan(s). Please settle them before taking a new loan.');
                        window.location.href='loan.php';
                    </script>";
                    return;
                }
                
                $stmtBalance = $conn->prepare("SELECT SUM(balance) AS total_balance FROM loans WHERE user_id = ?");
                $stmtBalance->bind_param("i", $loggedInUserId);
                $stmtBalance->execute();
                $resultBalance = $stmtBalance->get_result();
                $existingBalance = (float) ($resultBalance->fetch_assoc()['total_balance'] ?? 0);
                $stmtBalance->close();

                if (($existingBalance + $initialBalance) > $maxAllowedBalance) {
                    echo "<script>
                        alert('Sorry, you can’t take a new loan right now. Your unpaid loans already exceed 40% of your income. Try paying off some loans first.');
                        window.location.href = 'loan.php'; // optional redirect AFTER alert
                    </script>";
                    exit; 
                }

                $stmt = $conn->prepare("INSERT INTO loans (user_id, name, loan_type_id, amount, balance, status, date, due_date)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("isiddsss", $loggedInUserId, $loanName, $loanTypeId, $amount, $initialBalance, $status, $loanDate, $dueDate);
                    if ($stmt->execute()) {
                        echo "<script>window.location.href='loan.php';</script>";
                    } else {
                        echo "<p style='color:red;'>Error adding loan: " . $stmt->error . "</p>";
                    }
                    $stmt->close();

                    $updatebalance = $conn->prepare("UPDATE config SET balance = balance + ? WHERE user_id = ?");
                    if ($updatebalance) {
                        $updatebalance->bind_param("di", $amount, $loggedInUserId);
                        $updatebalance->execute();
                        $updatebalance->close();
                    }

                } else {
                    echo "<p style='color:red;'>Error preparing statement: " . $conn->error . "</p>";
                }
            }
        }

          ?>
           
          <div class="loan-records">
            <table class="auto-action-table">
              <thead>
                <tr>
                    <th>Date</th>         <!-- 0 -->
                    <th>Name</th>         <!-- 1 -->
                    <th>Type</th>         <!-- 2 -->
                    <th>Amount</th>       <!-- 3 -->
                    <th>Balance</th>      <!-- 4 -->
                    <th>Status</th>       <!-- 5 -->
                    <th>Remarks</th>      <!-- 6 -->
                    <th>Due Date</th>     <!-- 7 -->
                    <th>Actions</th>    <!-- 8 -->
                </tr>
              </thead>
              <tbody>
              <?php
              if ($conn) {
                  $stmt = $conn->prepare("SELECT loans.id, loans.date, loans.name, loan_types.type_name AS type, loans.amount, loans.balance, loans.status, loans.due_date
                                          FROM loans 
                                          JOIN loan_types ON loans.loan_type_id = loan_types.id 
                                          WHERE loans.user_id = ? 
                                          ORDER BY loans.date DESC");
                  if ($stmt) {
                      $stmt->bind_param("i", $loggedInUserId);
                      $stmt->execute();
                      $result = $stmt->get_result();

                      if ($result->num_rows > 0) {
                          while ($loan = $result->fetch_assoc()) {
                              echo "<tr>";
                                echo "<td>" . htmlspecialchars($loan['date']) . "</td>";                
                                echo "<td>" . htmlspecialchars($loan['name']) . "</td>";                 
                                echo "<td>" . htmlspecialchars($loan['type']) . "</td>";                 
                                echo "<td>P" . number_format($loan['amount'], 2) . "</td>";             
                                echo "<td>P" . number_format($loan['balance'], 2) . "</td>";            
                                echo "<td>" . htmlspecialchars($loan['status']) . "</td>";              
                                echo "<td data-balance='" . htmlspecialchars($loan['balance']) . "'></td>"; 
                                echo "<td>" . htmlspecialchars($loan['due_date']) . "</td>";            
                                echo "<td class='loan-actions'>                                         
                                        <button class='loan-edit-btn' title='Edit' data-loan-id='" . $loan['id'] . "'>
                                        <img src='images/edit3.png' alt='Edit' class='loan-action-icon'>
                                        </button>
                                        <button class='loan-delete-btn' title='Delete' data-loan-id='" . $loan['id'] . "'>
                                        <img src='images/deletes.png' alt='Delete' class='loan-action-icon'>
                                        </button>
                                    </td>";                                                            
                                echo "</tr>";

                          }
                      } else {
                          echo "<tr><td colspan='9'>No loan records found for this user.</td></tr>";
                      }
                      $stmt->close();
                  } else {
                      echo "<tr><td colspan='9'>Error preparing statement: " . $conn->error . "</td></tr>";
                  }
              }
              $conn->close();
              ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>

function closeForm(formId) {
    document.getElementById(formId).style.display = 'none';
}
function showForm(formId) {
    document.querySelectorAll('.loan-form').forEach(form => form.style.display = 'none');
    document.getElementById(formId).style.display = 'block';
}

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('.auto-action-table tbody tr').forEach(row => {
        const remarksCell = row.cells[6];
        const balance = parseFloat(remarksCell.dataset.balance);
        const loanId = row.querySelector('.loan-edit-btn')?.dataset.loanId;
        const name = row.cells[1].innerText;

        if (balance === 0) {
            remarksCell.innerHTML = `<i class="fas fa-check-circle" title="Paid" style="color: green; font-size: 1.3rem;"></i>`;
        } else {
            remarksCell.innerHTML = `<a href="transaction.php?pay_loan=1&loan_id=${loanId}&loan_name=${encodeURIComponent(name)}">
                                        <button class="pay-btn">Pay</button>
                                     </a>`;
        }

        // EDIT
        row.querySelector('.loan-edit-btn').addEventListener('click', () => {
            const originalData = {
                date: row.cells[0].innerText,
                name: row.cells[1].innerText,
                type: row.cells[2].innerText,
                amount: row.cells[3].innerText.replace(/[^\d.]/g, ''),
                balance: row.cells[4].innerText.replace(/[^\d.]/g, ''),
                due_date: row.cells[6].innerText 
            };

            row.cells[0].innerHTML = `<input type="date" value="${originalData.date}" class="inline-input">`;
            row.cells[1].innerHTML = `<input type="text" value="${originalData.name}" class="inline-input">`;
            row.cells[2].innerHTML = `<select class="inline-select">${
                [...document.querySelectorAll('select[name="loan_type_id"] option')].map(opt => {
                    const selected = opt.text.trim() === originalData.type.trim() ? 'selected' : '';
                    return `<option value="${opt.value}" ${selected}>${opt.text}</option>`;
                }).join('')
            }</select>`;
            row.cells[3].innerHTML = `<input type="number" value="${originalData.amount}" class="inline-input">`;
            row.cells[4].innerHTML = `<input type="number" value="${originalData.balance}" class="inline-input">`;
            row.cells[5].innerText = '-'; 
            row.cells[5].innerText = '-'; 
            row.cells[6].innerHTML = `<input type="date" value="${originalData.due_date}" class="inline-input">`; 

            row.cells[7].innerHTML = `
                <button class="btn-save-inline">Save</button>
                <button class="btn-cancel-inline">Cancel</button>
            `;

            row.querySelector('.btn-cancel-inline').addEventListener('click', () => {
                location.reload();
            });

            row.querySelector('.btn-save-inline').addEventListener('click', () => {
                const updated = {
                    date: row.cells[0].querySelector('input').value,
                    name: row.cells[1].querySelector('input').value,
                    typeId: row.cells[2].querySelector('select').value,
                    amount: parseFloat(row.cells[3].querySelector('input').value),
                    balance: parseFloat(row.cells[4].querySelector('input').value),
                    dueDate: row.cells[6].querySelector('input').value 
                };

                if (updated.amount < updated.balance) {
                    alert("Loan amount cannot be less than the loan balance.");
                    return;
                }
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                form.innerHTML = `
                    <input type="hidden" name="edit_loan" value="1">
                    <input type="hidden" name="loan_id" value="${loanId}">
                    <input type="hidden" name="loan_name" value="${updated.name}">
                    <input type="hidden" name="loan_type_id" value="${updated.typeId}">
                    <input type="hidden" name="loan_date" value="${updated.date}">
                    <input type="hidden" name="amount" value="${updated.amount}">
                    <input type="hidden" name="balance" value="${updated.balance}">
                    <input type="hidden" name="due_date" value="${updated.dueDate}"> <!-- Add due date here -->
                `;
                document.body.appendChild(form);
                form.submit();
            });
        });

        // DELETE
        row.querySelector('.loan-delete-btn').addEventListener('click', () => {
            if (confirm("Are you sure you want to delete this loan?")) {
                window.location.href = `loan.php?delete_loan_id=${loanId}`;
            }
        });
    });
});
</script>
</body>
<?php include 'chatbot_widget.php'; ?>
</html>
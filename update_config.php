<?php date_default_timezone_set('Asia/Manila'); ?>
<?php
error_reporting(E_ALL); 
ini_set('display_errors', 1); 

include 'db_connect.php';
session_start();

var_dump($_FILES); 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $user_id = $_SESSION['user_id'];
   
    $current_balance = mysqli_real_escape_string($conn, $_POST['current_balance'] ?? '0.00');
    $income_amount = mysqli_real_escape_string($conn, $_POST['income_amount'] ?? '0.00');
    $expense_amount = mysqli_real_escape_string($conn, $_POST['expense_amount'] ?? '0.00');
    $savings = mysqli_real_escape_string($conn, $_POST['savings'] ?? '0.00');
    $loan_balance = mysqli_real_escape_string($conn, $_POST['loan_balance'] ?? '0.00');
   
    $savinggoal = ((float)$income_amount - (float)$expense_amount) * .20;

    $_SESSION['currentbalance'] = $current_balance;
    $_SESSION['totalexpenses'] =$expense_amount;
    

    echo "DEBUG: User ID: " . htmlspecialchars($user_id) . "<br>";
    $accountQuery = "SELECT * FROM config WHERE user_id = '$user_id'";
    $accountResult = mysqli_query($conn, $accountQuery);

    if (!$accountResult) {
        // Handle database query error
        $_SESSION['error_message'] = "Error executing query: " . mysqli_error($conn);
        header("Location: profile.php"); 
        exit();
    }

    $accountInfo = mysqli_fetch_assoc($accountResult);
    echo "DEBUG: Account Info from DB: "; 
    var_dump($accountInfo); 

    // Initialize profile picture path with existing one or default
    $profile_picture_path = $accountInfo['profile_picture'] ?? 'images/jem.jpg';

    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/"; // Directory where images will be saved
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); 
        }

        $file_name = uniqid() . '_' . basename($_FILES['profile_picture']['name']); 
        $target_file = $target_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $check = getimagesize($_FILES['profile_picture']['tmp_name']);
        if ($check === false) {
            $_SESSION['error_message'] = "File is not an image.";
            header("Location: profile.php"); 
            exit();
        }

        if ($_FILES['profile_picture']['size'] > 5000000) {
            $_SESSION['error_message'] = "Sorry, your file is too large (max 5MB).";
            header("Location: profile.php"); 
            exit();
        }

        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
            $_SESSION['error_message'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            header("Location: profile.php");
            exit();
        }

        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
            $profile_picture_path = $target_file; 
        } else {
            $_SESSION['error_message'] = "Sorry, there was an error uploading your file.";
            header("Location: profile.php"); 
            exit();
        }
    }

    echo "Path about to be saved to DB: " . htmlspecialchars($profile_picture_path) . "<br>";

    try {
        if (!$accountInfo) { 
            echo "DEBUG: Entering INSERT block.<br>"; 
            $stmt = $conn->prepare("INSERT INTO config (user_id,  balance, income, expenses, savings, loan,  profile_picture, monthly_saving) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("idddddsd", $user_id,  $current_balance, $income_amount, $expense_amount, $savings, $loan_balance, $profile_picture_path,$savinggoal);
            $stmt->execute();

            if ((float)$loan_balance > 0){
                    $stmt2 = $conn->prepare("INSERT INTO loans (user_id, name, loan_type_id, amount, balance, status, date) VALUES (?, ?, ?, ?, ?, ?, ? )");
                    $init_name = "Initial Loan";
                    $loanTypeId = 6;
                    $status = "Ongoing";
                    $today = date("Y/m/d");
                  if ($stmt2) {
                      $stmt2->bind_param("isiidss", $user_id, $init_name, $loanTypeId, $loan_balance, $loan_balance, $status, $today);
            

                      if ($stmt2->execute() ) {
                          echo "<p style='color:green;'>Loan added successfully!</p>";
                          echo "<script>window.location.href='loan.php';</script>";
                      } else {
                          echo "<p style='color:red;'>Error adding loan: " . $stmt->error . "</p>";
                      }
                      $stmt2->close();
                    }}


            if ($stmt->error) { 
                echo "DEBUG: INSERT Statement execution error: " . htmlspecialchars($stmt->error) . "<br>"; 
            }
            $_SESSION['success_message'] = "Account setup complete!";
            header("Location: setup_secques.php"); 
            exit();
        } else { 
            echo "DEBUG: Entering UPDATE block.<br>"; 
            // Update existing record
            $stmt = $conn->prepare("UPDATE config SET
                balance = ?,
                income = ?,
                expenses = ?,
                savings = ?,
                loan = ?,
                profile_picture = ?,
                monthly_saving = ?
                WHERE user_id = ?");
            $stmt->bind_param("dddddsdi",  $current_balance, $income_amount, $expense_amount, $savings, $loan_balance,  $profile_picture_path,$savinggoal, $user_id);
            $stmt->execute();
            if ($stmt->error) { 
                echo "DEBUG: UPDATE Statement execution error: " . htmlspecialchars($stmt->error) . "<br>"; 
            }
            $_SESSION['success_message'] = "Profile updated successfully!";
            header("Location: profile.php"); 
            exit();
        }

    } catch (mysqli_sql_exception $e) {
        $_SESSION['error_message'] = "Database Error: " . $e->getMessage();
        echo "DEBUG: Catch block entered - Database Error: " . htmlspecialchars($e->getMessage()) . "<br>"; 
        if (!$accountInfo) {
            header("Location: config.php");
        } else {
            header("Location: profile.php"); 
        }
        exit();
    } finally {
        if (isset($stmt)) {
            $stmt->close(); 
        }
    }
}
?>
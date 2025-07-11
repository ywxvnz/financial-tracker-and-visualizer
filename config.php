<?php
include('db_connect.php');
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: log-in.php");
    exit();
}

// Fetch existing data for the user from the 'config' table if it exists
$user_id = $_SESSION['user_id'];
$accountQuery = "SELECT profile_picture FROM config WHERE user_id = '$user_id'";
$accountResult = mysqli_query($conn, $accountQuery);
$financials = mysqli_fetch_assoc($accountResult);

// Get the profile picture path, defaulting if not set
$profile_picture_display_path = $financials['profile_picture'] ?? 'images/jem.jpg';

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Financial Advisor</title>
  <link rel="stylesheet" href="style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="account-setup-container">
          
    <div class="profile-section">
      <h2>Set Up Your Account</h2>
      <h4>In order for us to help you, Please provide the following data:</h4>
      <h2></h2><h2></h2>
      <div class="profile-info">
        <form action="update_config.php" method="POST" onsubmit="return validateFinancialForm();" enctype="multipart/form-data">
        <label for="profile_picture_upload" class="profile-picture-label">
          <img src="<?php echo htmlspecialchars($profile_picture_display_path); ?>" alt="Profile Picture" id="profile-picture-display" />
        </label>
        <input type="file" id="profile_picture_upload" name="profile_picture" accept="image/*" style="display: none;" />
        <div>
          <h3><?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
          <p><?php echo htmlspecialchars($_SESSION['email']); ?></p>
        </div>
      </div>


      </div>

      <div class="form-section">
        <label>Current Balance</label>
        <input type="number" id="current_balance" name="current_balance" placeholder="₱0.00" required/>

        <label>Current Savings</label>
        <input type="number" id="savings" name="savings" placeholder="₱0.00" required/>

        <label>Estimated Monthly Income</label>
        <input type="number" id="income_amount" name="income_amount" placeholder="Monthly - ₱0.00" required/>

        <label>Estimated Monthly Expenses</label>
        <input type="number" id="expense_amount" name="expense_amount" placeholder="Monthly - ₱0.00" required/>


        <label>Loan balance</label>
        <input type="number" id="loan_balance" name="loan_balance" placeholder="₱0.00" required/>

        <p id="form-error-msg" style="color: red; font-weight: bold;"></p>
        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<p style="color: green; font-weight: bold;">' . htmlspecialchars($_SESSION['success_message']) . '</p>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<p style="color: red; font-weight: bold;">' . htmlspecialchars($_SESSION['error_message']) . '</p>';
            unset($_SESSION['error_message']);
        }
        ?>
        <button type="submit">Next</button>
      </div>
      </form>
  </div>


  <script>
  function isPositiveNumber(value) {
    return /^\d+(\.\d{1,2})?$/.test(value);
  }

  function validateFinancialForm() {
    const balance = document.getElementById("current_balance").value.trim();
    const income = document.getElementById("income_amount").value.trim();
    const expenses = document.getElementById("expense_amount").value.trim();
    const savings = document.getElementById("savings").value.trim();
    const loan = document.getElementById("loan_balance").value.trim();
    const errorMsg = document.getElementById("form-error-msg");

    // Validate numeric fields
    if (balance && !isPositiveNumber(balance)) {
      errorMsg.textContent = "Current balance must be a valid number.";
      return false;
    }

    if (income && !isPositiveNumber(income)) {
      errorMsg.textContent = "Monthly income must be a valid number.";
      return false;
    }

    if (expenses && !isPositiveNumber(expenses)) {
      errorMsg.textContent = "Monthly expenses must be a valid number.";
      return false;
    }

    if (savings && !isPositiveNumber(savings)) {
      errorMsg.textContent = "Savings must be a valid number.";
      return false;
    }

    if (loan && !isPositiveNumber(loan)) {
      errorMsg.textContent = "Loan balance must be a valid number.";
      return false;
    }

    const incomenum = parseFloat(income);
    const expensenum = parseFloat(expenses);

    if (incomenum < expensenum){
      errorMsg.textContent = "Expenses must not be greater than Income.";
      return false;
    }

    errorMsg.textContent = "";
    return true;
  }

  // JavaScript for immediate image preview
  const profilePictureUpload = document.getElementById('profile_picture_upload');
  const profilePictureDisplay = document.getElementById('profile-picture-display');

  if (profilePictureUpload && profilePictureDisplay) {
    profilePictureUpload.addEventListener('change', function() {
      const file = this.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
          profilePictureDisplay.src = e.target.result;
        };
        reader.readAsDataURL(file);
      }
    });
  }
  </script>
</body>
</html>
<?php
include('db_connect.php');
session_start();

// Set default form to signup
$defaultForm = 'signup';
if (isset($_GET['form'])) {
    $defaultForm = $_GET['form'];
}

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if database connection is established
    if (isset($conn)) {
        // Sanitize and escape user inputs
        $fname = mysqli_real_escape_string($conn, $_POST['fname']);
        $lname = mysqli_real_escape_string($conn, $_POST['lname']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $raw_password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate name fields to contain only letters, spaces, and periods
        if (!preg_match("/^[a-zA-Z\s\.]+$/", $fname) || !preg_match("/^[a-zA-Z\s\.]+$/", $lname)) {
            echo '<script>alert("Name must only contain letters, spaces, or periods."); window.history.back();</script>';
            exit();
        }

        // Validate email to be valid and start with a letter (any domain allowed)
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match("/^[a-zA-Z][a-zA-Z0-9._%+-]*@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/", $email)) {
            echo '<script>alert("Please enter a valid email address."); window.history.back();</script>';
            exit();
        }

        // Validate password length
        if (strlen($raw_password) < 8 || strlen($raw_password) > 20) {
            echo '<script>alert("Password must be 8–20 characters."); window.history.back();</script>';
            exit();
        }

        // Check if passwords match
        if ($raw_password !== $confirm_password) {
            echo '<script>alert("Passwords do not match."); window.history.back();</script>';
            exit();
        }

        // Hash the password
        $password = password_hash($raw_password, PASSWORD_BCRYPT);

        // Check if email already exists in the database
        $checkEmailSql = "SELECT id FROM users WHERE email = ?";
        $stmt_check_email = $conn->prepare($checkEmailSql);
        if ($stmt_check_email === false) {
            die("Error preparing email check statement: " . $conn->error);
        }
        $stmt_check_email->bind_param("s", $email);
        $stmt_check_email->execute();
        $result = $stmt_check_email->get_result();
        $stmt_check_email->close();

        // If email exists, show error and stop
        if ($result->num_rows > 0) {
            echo '<script>alert("This email has already been used. Please use a different email."); window.location.href = "index.php";</script>';
        } else {
            // Begin transaction for user creation
            $conn->begin_transaction();

            try {
                // Prepare statement for inserting user details
                $stmt_user = $conn->prepare("INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)");
                if ($stmt_user === false) {
                    throw new Exception("Error preparing user insertion statement: " . $conn->error);
                }
                $stmt_user->bind_param("ssss", $fname, $lname, $email, $password);
                if (!$stmt_user->execute()) {
                    throw new Exception("Error creating user account: " . $stmt_user->error);
                }
                $stmt_user->close();

                // Commit transaction after successful insertion
                $conn->commit();

                // Store session variable for new user
                $_SESSION['new_user_just_registered'] = true;
                echo '<script>alert("Account created successful!"); window.location.href = "log-in.php";</script>';
                exit();

            } catch (Exception $e) {
                // Rollback transaction if an error occurs
                $conn->rollback();
                echo "Error: " . $e->getMessage();
            }
        }
    } else {
        echo "Database connection failed";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Financial Manager</title>
  <link rel="stylesheet" href="style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>
        Online <br />
        Financial <br />
        Tracker and <br />
        Visualizer
      </h1>
      <p>
        Web-based financial management tool for students and young adults to manage finances and stay on top of cash flow.
      </p>
      <button class="get-started">Get Started</button>
    </div>

    <div class="sign-up" id="signup-section">
      <h2>Sign up</h2>
      <p>Already have an account? <a href="log-in.php">Log in here</a></p>

      <form id="signupForm" action="index.php" method="post">
        <div class="name-fields">
          <input id="fname" name="fname" type="text" placeholder="First name" required pattern = "^[a-zA-Z\s\.]+$" onkeydown="return onlyLetters(event)"/>
          <input id="lname" name="lname" type="text" placeholder="Surname" required pattern = "^[a-zA-Z\s\.]+$" onkeydown="return onlyLetters(event)" />
        </div>
        <!-- Email pattern updated -->
        <input id="email" name="email" type="email" placeholder="Email" required pattern="^[a-zA-Z][a-zA-Z0-9._%+-]*@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"/>
        <input id="password" name="password" type="password" placeholder="Password"  required pattern="[a-zA-Z0-9]+" required minlength="8" maxlength="20"/>
        <input id="confirm_password" name="confirm_password" type="password" required pattern="[a-zA-Z0-9]+" placeholder="Confirm password" required minlength="8" maxlength="20"/>

        <button type="submit" class="sign-up-btn">Sign up</button>
      </form>
    </div>
  </div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Restrict name inputs to letters, spaces, and periods
  function onlyLetters(event) {
    const key = event.key;
    const isLetter = /^[a-zA-Z\s.]$/.test(key);
    const isControlKey = ['Backspace', 'ArrowLeft', 'ArrowRight', 'Tab', 'Delete'].includes(key);
    return isLetter || isControlKey;
  }

  // Hook name fields
  const fnameInput = document.getElementById('fname');
  const lnameInput = document.getElementById('lname');
  if (fnameInput && lnameInput) {
    fnameInput.onkeydown = onlyLetters;
    lnameInput.onkeydown = onlyLetters;
  }

  // Client-side validation
  const signupForm = document.getElementById('signupForm');
  if (signupForm) {
    signupForm.addEventListener('submit', function (e) {
      const fname = document.getElementById('fname');
      const lname = document.getElementById('lname');
      const email = document.getElementById('email');
      const password = document.getElementById('password');
      const confirmPassword = document.getElementById('confirm_password');

      [fname, lname, email, password, confirmPassword].forEach(field => {
        field.style.borderColor = '#ccc';
      });

      let valid = true;
      let errorMessage = '';

      const namePattern = /^[a-zA-Z\s\.]+$/;
      if (!namePattern.test(fname.value)) {
        valid = false;
        errorMessage = 'First name contains invalid characters.';
        fname.style.borderColor = 'red';
      } else if (!namePattern.test(lname.value)) {
        valid = false;
        errorMessage = 'Surname contains invalid characters.';
        lname.style.borderColor = 'red';
      }

      const emailPattern = /^[a-zA-Z][a-zA-Z0-9._%+-]*@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
      if (!emailPattern.test(email.value)) {
        valid = false;
        errorMessage = 'Please enter a valid email address.';
        email.style.borderColor = 'red';
      }

      if (password.value.length < 8 || password.value.length > 20) {
        valid = false;
        errorMessage = 'Password must be 8–20 characters.';
        password.style.borderColor = 'red';
      }

      if (password.value !== confirmPassword.value) {
        valid = false;
        errorMessage = 'Passwords do not match.';
        confirmPassword.style.borderColor = 'red';
      }

      if (!valid) {
        e.preventDefault();
        alert(errorMessage);
      }
    });
  }

  // Smooth scroll for "Get Started" button
  const btn = document.querySelector('.get-started');
  const signupSection = document.getElementById('signup-section');

  if (btn && signupSection) {
    btn.addEventListener('click', function (e) {
      e.preventDefault(); // Prevent default if it's a form button or link
      signupSection.scrollIntoView({ behavior: 'smooth' });
      signupSection.classList.add('highlight-flash');
      setTimeout(() => {
        signupSection.classList.remove('highlight-flash');
      }, 1000);
    });
  }
});
</script>
</body>
</html>

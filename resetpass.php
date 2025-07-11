<?php 
include('db_connect.php');
session_start();

  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $raw_password = $_POST['password'];
    $confirm_password = $_POST['confirmpassword'];
    if ($raw_password !== $confirm_password) {
            echo '<script>
                    alert("Passwords do not match.");
                    window.history.back();
                  </script>';
            exit();
        }

    $password = password_hash($raw_password, PASSWORD_BCRYPT);
    $email = $_SESSION['reset_email'];
    $sql = "UPDATE users SET password = '$password' WHERE email = '$email'";

            if ($conn->query($sql) === TRUE) {
                // Redirect to login page after successful registration
                echo '<script>
                    alert("Password Change Sucessful!");
                    window.location.href = "log-in.php";
                </script>';
                exit();
            } else {
                echo "Error: " . $sql . "<br>" . $conn->error;
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
        Manager and <br />
        Visualizer
      </h1>
      <p>
        Web-based financial management tool for students / young adults to manage finances and stay on top of cash flow.
      </p>
      <button class="get-started">Get Started</button>
    </div>

    <div class="reset">
      <h2>Forgot Password</h2>
    <a href="sign-in.html">Sign in here</a>
        <h3>Reset Password</h3>

      <form action="resetpass.php" method="post">
        <input type="password" id="password" name="password" placeholder="Password"required />
        <input type="password" id="confirmpassword" name="confirmpassword"placeholder="Confirm Password" required />

        <div class = "button-column1">
            <button type="submit" id="submit" name="submit" class="forpass-btn">Submit</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>

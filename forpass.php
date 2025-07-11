<?php 
include('db_connect.php');
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['submit']) && isset($_POST['email'])) {

    $email = trim($_POST['email']);

    // Validate email format using filter_var + regex
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || 
        !preg_match("/^[a-zA-Z][a-zA-Z0-9._%+-]*@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/", $email)) {
        echo "<script>alert('Invalid email format.'); window.location.href='forpass.php';</script>";
        exit();
    }

    // Escape string for database use
    $email = mysqli_real_escape_string($conn, $email);

    // Prepare and execute query
    $stmt = $conn->prepare("SELECT security_question FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Fetch more user details
        $stmt = $conn->prepare("SELECT password, security_question, security_answer FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user_result = $stmt->get_result();
        $user_data = $user_result->fetch_assoc();

        if (empty($user_data['password']) || empty($user_data['security_question']) || empty($user_data['security_answer'])) {
            echo "<script>alert('You cannot reset your password because your account setup is incomplete. Log in to complete account setup.'); window.location.href='log-in.php';</script>";
            exit();
        }

        // Proceed if the account is properly set up
        $_SESSION['reset_email'] = $email;
        $_SESSION['security_question'] = $user_data['security_question'];
        header("Location: sec-question.php");
        exit();
    } else {
        echo "<script>alert('Email not found.'); window.location.href='forpass.php';</script>";
    }
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

    <div class="forpass">
      <h2>Forgot Password</h2>
    <a href="log-in.php">Log in here</a>

      <form action="forpass.php" method="post">
        <h4>Please enter your email to reset your password</h4>
        <input type="email" id="email" name="email" placeholder="Email"required />

       <div class="button-column">
            <button type="submit" id="submit" name="submit" class="forpass-btn">Submit</button>
            <button type="button" class="cancel-btn" onclick="window.location.href='log-in.php'">Cancel</button>
        </div>
    </div>
  </div>
</body>
</html>

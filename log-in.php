<?php
include('db_connect.php');
session_start();
if ($_SERVER['REQUEST_METHOD'] == 'POST'){
  $email = $_POST['email'];
  $password = $_POST['password'];

  $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? ");
  $stmt->bind_param("s",$email);
  $stmt->execute(); 
  $result=$stmt->get_result();

  if($result->num_rows > 0){
    $row = $result->fetch_assoc();

    if (password_verify($password, $row['password'])) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_name'] = $row['first_name'] . " " . $row['last_name'];
        $_SESSION['email'] = $email;

        if (!empty($row['security_question']) && !empty($row['security_answer'])) {
            echo '<script>
                    alert("Login successful!");
                    window.location.href = "dashboard.php";
                  </script>';
        } else {
            echo '<script>
                    alert("Login successful! Please complete your security question setup.");
                    window.location.href = "config.php";
                  </script>';
        }
        exit();
    }
        else{ 
            echo '<script> alert("Incorrect Password."); </script>';
          }
}
  else{ 
    echo '<script> alert("No user found with this email."); </script>';
  }

  $stmt->close();

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
      <button class="get-started" onclick="window.location.href='index.php';">Get Started</button>
    </div>

    <div class="sign-up">
      <h2>Log in</h2>
      <p>Dont have an account? <a href="index.php">Sign up here</a></p>

      <form action="log-in.php" method="post">
        <div class="sign-in">
            <input id="email" name="email" type="email" placeholder="Email" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" title="Please enter a valid email address"/>
            <input id="password" name="password" type="password" placeholder="Password" requiredrequired minlength="8" maxlength="20"/>
            <p><a href="forpass.php">Forgot password?</a></p>
        </div>
        
        <button type="submit" class="sign-up-btn">Log In</button>
      </form>
    </div>
  </div>
</body>
</html>

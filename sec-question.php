<?php 
include('db_connect.php');
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST'){
  if (isset($_POST['submit']) && isset($_POST['answer'])) {

  
    $answer = mysqli_real_escape_string($conn, $_POST['answer']);
    $email = $_SESSION['reset_email'];
    $stmt = $conn->prepare("SELECT security_answer FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();


     if ($row = $result->fetch_assoc()) {
      if (password_verify($answer, $row['security_answer'])){
          header("Location: resetpass.php");
            exit();
      }
        
         else {
            echo "<script>alert('Answers do not Match.'); window.location.href='sec-question.php';</script>";
        }
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

    <div class="security">
      <h2>Forgot Password</h2>
    <a href="index.php">Sign in here</a>
        <h3>Security Question:</h3>

      <form action="sec-question.php" method="post">
        <h4><?php echo htmlspecialchars($_SESSION['security_question']); ?></h4>
       <input type="text" id="answer" name="answer" placeholder="Answer" required pattern="^[a-zA-Z\s\.]+$" onkeydown="return onlyLetters(event)" />


      <div class="button-column">
          <button type="submit" id="submit" name="submit" class="forpass-btn">Submit</button>
          <button type="button" class="cancel-btn" onclick="window.location.href='forpass.php'">Cancel</button> 
      </div>

      </form>
    </div>
  </div>
</body>
</html>

<?php
include('db_connect.php');
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: log-in.php");
    exit();
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST'){
  $secques = $_POST['security_question'];
  $rawsecans = $_POST['security_answer'];
  $confirmsecans= $_POST['confirm_secAnswer'];
    if ($rawsecans !== $confirmsecans) {
                echo '<script>
                        alert("Answers do not match.");
                        window.history.back();
                    </script>';
                exit();
            }

  $secans= password_hash($rawsecans, PASSWORD_BCRYPT);
  $user_id = $_SESSION['user_id'];
  try{
    $stmt = $conn->prepare("UPDATE users SET security_question = ? , security_answer = ?  WHERE id = '$user_id';");
    $stmt->bind_param("ss", $secques,$secans);
    $stmt->execute();
  }catch(mysqli_sql_exception $e){
    die("Error: " . $e->getMessage());
  }

echo '<script>
                    alert("Submitted Sucessfully!");
                    window.location.href = "dashboard.php";
                </script>';
exit();

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
  <div class="setsecquecontainer">
    
    <div class="sign-up">
      <h2>Set up your Account</h2>
      <h3>Security Questions </h3>
      <p>The question that will be asked in case you forgot your password to reset it.</p>

    <form action="setup_secques.php" method="post">
    <div class="input-control">
        <label for="security_question">Security Question</label>
        <select id="security_question" name="security_question" required onchange="toggleCustomQuestion()">
        <option value="" disabled selected>Select a Security Question</option>
        <option value="What is your pet’s name?">What is your pet’s name?</option>
        <option value="What is your mother’s maiden name?">What is your mother’s maiden name?</option>
        <option value="What was the name of your first school?">What was the name of your first school?</option>
        <option value="other">Other (write your own question)</option>
        </select>
    </div>

    <!-- Hidden custom question input -->
    <div class="input-control" id="custom-question-div" style="display: none;">
        <label for="custom_question">Your Custom Question</label>
        <input type="text" id="custom_question" name="custom_question" placeholder="Enter your custom security question">
    </div>

    <div class="input-control">
        <label for="security_answer">Answer</label>
        <input type="password" id="security_answer" name="security_answer" placeholder="Your answer" required>
    </div>

    <div class="input-control">
        <label for="confirm_secAnswer">Confirm Answer</label>
        <input type="password" id="confirm_secAnswer" name="confirm_secAnswer" placeholder="Confirm your answer" required>
    </div>

    <button type="submit" class="sec-ans-btn">Done</button>
    </form>

    </div>
  </div>
  <script>
    function toggleCustomQuestion() {
        const select = document.getElementById('security_question');
        const customDiv = document.getElementById('custom-question-div');
        
        if (select.value === 'other') {
        customDiv.style.display = 'block';
        document.getElementById('custom_question').setAttribute('required', 'required');
        } else {
        customDiv.style.display = 'none';
        document.getElementById('custom_question').removeAttribute('required');
        }
    }
    </script>
</body>
</html>

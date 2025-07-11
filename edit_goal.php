<?php
include 'db_connect.php'; // Include your database connection file
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login page if not logged in
    exit();
}

$user_id = $_SESSION['user_id'];
$goal = null; // Initialize goal variable

// ----------------------------------------------------------------------
// 1. Handle fetching goal data for display when the page loads
// ----------------------------------------------------------------------
if (isset($_GET['id'])) {
    $goal_id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

    $stmt = $conn->prepare("SELECT id, title, target_amount, saved_amount, duration, image_path, status FROM goals WHERE id = ? AND user_id = ?");
    if ($stmt === false) {
        die("Error preparing statement: " . $conn->error);
    }
    $stmt->bind_param("ii", $goal_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $goal = $result->fetch_assoc();
    } else {
        $_SESSION['error_message'] = "Goal not found or you don't have permission to edit it.";
        header("Location: goals.php");
        exit();
    }
    $stmt->close();
} else if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // This block handles the form submission (update logic)
    // No need to fetch goal data again here, as we're processing an update.
    // The goal_id will be coming from a hidden input.
} else {
    $_SESSION['error_message'] = "No goal ID provided for editing.";
    header("Location: goals.php");
    exit();
}

// ----------------------------------------------------------------------
// 2. Handle form submission for updating the goal
// ----------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $goal_id = filter_var($_POST['goal-id'], FILTER_SANITIZE_NUMBER_INT); // Get goal ID from hidden input
    $goal_name = filter_var($_POST['goal-name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $target_amount = filter_var($_POST['goal-amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $duration = filter_var($_POST['duration'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
   
  
    $current_image_path = filter_var($_POST['current-image-path'], FILTER_SANITIZE_URL); // Get current image path

    $image_path = $current_image_path; // Assume current image path, unless new one is uploaded

    // Handle new image upload
    if (isset($_FILES['goal-image']) && $_FILES['goal-image']['error'] == 0) {
        $target_dir = "images/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $image_file_type = strtolower(pathinfo($_FILES['goal-image']['name'], PATHINFO_EXTENSION));
        $unique_file_name = uniqid('goal_') . '.' . $image_file_type;
        $target_file = $target_dir . $unique_file_name;

        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($image_file_type, $allowed_extensions)) {
            if (move_uploaded_file($_FILES['goal-image']['tmp_name'], $target_file)) {
                $image_path = $target_file;
                // Optionally, delete the old image if it's not the default one
                if ($current_image_path != 'images/default_goal.jpeg' && file_exists($current_image_path)) {
                    unlink($current_image_path);
                }
            } else {
                $_SESSION['error_message'] = "Sorry, there was an error uploading the new image.";
                header("Location: goals.php");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed for images.";
            header("Location: goals.php");
            exit();
        }
    }

    // Update goal in the database
    // Note: saved_amount is not updated via this form; it's updated via funding transactions.
    $stmt = $conn->prepare("UPDATE goals SET title = ?, target_amount = ?, duration = ?, image_path = ? WHERE id = ? AND user_id = ?");

    if ($stmt === false) {
        error_log("Failed to prepare update statement: " . $conn->error);
        $_SESSION['error_message'] = "An error occurred during goal update setup.";
        header("Location: goals.php");
        exit();
    }

    // Bind parameters: s=string, d=double (float), s=string, s=string, s=string, s=string, i=integer, i=integer
    $stmt->bind_param("sddsii", $goal_name, $target_amount, $duration, $image_path, $goal_id, $user_id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Goal updated successfully!";
    } else {
        error_log("Failed to update goal: " . $stmt->error);
        $_SESSION['error_message'] = "Failed to update goal: " . $stmt->error;
    }

    $stmt->close();
    header("Location: goals.php"); // Redirect back to goals page after update
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Goal</title>
  <link rel="stylesheet" href="style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="edit-goal-wrapper">
    <div class="edit-goal-content">
      <span class="close-button" onclick="window.location.href='goals.php'">&times;</span>
      <h2>Edit Goal</h2>
      <?php if ($goal): // Display form only if goal data was fetched ?>
      <form method="POST" action="edit_goal.php" enctype="multipart/form-data">
        <input type="hidden" name="goal-id" value="<?= htmlspecialchars($goal['id']) ?>">
        <input type="hidden" name="current-image-path" value="<?= htmlspecialchars($goal['image_path']) ?>">

        <div class="form-columns">
          <div class="calendar-side">
            <label class="upload-button" for="goal-image">
              <i class="fa fa-camera"></i> Change Image
            </label>
            <input type="file" name="goal-image" id="goal-image" accept="image/*" />
            <?php if (!empty($goal['image_path']) && $goal['image_path'] != 'images/default_goal.jpeg'): ?>
                <img src="<?= htmlspecialchars($goal['image_path']) ?>" alt="Current Goal Image" style="max-width: 150px; max-height: 150px; object-fit: cover; border-radius: 8px; margin-top: 10px; display: block; margin-left: auto; margin-right: auto;">
                <p style="text-align:center; font-size: 0.9em; color: #666;">Current Image</p>
            <?php endif; ?>
            
          </div>

          <div class="details-side">
            <label for="goal-name">Name:</label>
            <input type="text" name="goal-name" id="goal-name" placeholder="e.g. New Phone" value="<?= htmlspecialchars($goal['title']) ?>" required />
            <label for="goal-amount">Target Amount (₱):</label>
            <input type="number" name="goal-amount" id="goal-amount" placeholder="₱0.00" step="0.01" value="<?= htmlspecialchars($goal['target_amount']) ?>" required />
            
            <!--<label for="duration">Duration (Days): <span id="durationLabel">1</span> day(s)</label>
        
          <input type="range" id="duration" name="duration" min="1" max="365" value="<?= htmlspecialchars($goal['duration'])?>" oninput='updateDuration()' />
            --> <button type="submit">Update Goal</button>
        </div>
        </div>
      </form>
      <?php else: ?>
        <p style="text-align: center; color: red;">Error: Could not load goal for editing.</p>
      <?php endif; ?>
    </div>
  </div>

  <?php include('sidebar.php'); ?>

  <script>
    window.onload = () => {
      updateDuration()
      const today = new Date().toISOString().split("T")[0];
      const now = new Date().toISOString().slice(0, 16);

      
    };
    function updateDuration() {
    const days = document.getElementById('duration').value;
    document.getElementById('durationLabel').innerText = days;

  }

  </script>
</body>
</html>
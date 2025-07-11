<?php
include 'db_connect.php'; // Include your database connection file
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login page if not logged in
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and retrieve form data
    $goal_name = filter_var($_POST['goal-name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $target_amount = filter_var($_POST['goal-amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
   $duration = filter_var($_POST['duration'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    $image_path = 'images/default_goal.jpeg'; // Default image path

    // Handle image upload
    if (isset($_FILES['goal-image']) && $_FILES['goal-image']['error'] == 0) {
        $target_dir = "images/"; // Directory where images will be saved
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); // Create directory if it doesn't exist
        }

        $image_file_type = strtolower(pathinfo($_FILES['goal-image']['name'], PATHINFO_EXTENSION));
        // Generate a unique file name to prevent overwrites
        $unique_file_name = uniqid('goal_') . '.' . $image_file_type;
        $target_file = $target_dir . $unique_file_name;

        // Allow certain file formats
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($image_file_type, $allowed_extensions)) {
            if (move_uploaded_file($_FILES['goal-image']['tmp_name'], $target_file)) {
                $image_path = $target_file; // Set image path to the uploaded file
            } else {
                $_SESSION['error_message'] = "Sorry, there was an error uploading your image.";
            }
        } else {
            $_SESSION['error_message'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed for images.";
        }
    }

    // Insert goal into the database
    $stmt = $conn->prepare("INSERT INTO goals (user_id, title, target_amount, saved_amount, duration ,image_path, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");

    if ($stmt === false) {
        error_log("Failed to prepare statement: " . $conn->error);
        $_SESSION['error_message'] = "An error occurred during goal creation setup.";
        header("Location: goals.php");
        exit();
    }

    $saved_amount = 0.00; // New goals start with 0 saved amount

    // Bind parameters: i = integer, s = string, d = double (float)
    $stmt->bind_param("isddds", $user_id, $goal_name, $target_amount, $saved_amount, $duration, $image_path);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "New goal added successfully!";
    } else {
        error_log("Failed to add goal: " . $stmt->error);
        $_SESSION['error_message'] = "Failed to add goal: " . $stmt->error;
    }

    $stmt->close();
} else {
    $_SESSION['error_message'] = "Invalid request method.";
}

// Redirect back to the goals page
header("Location: goals.php");
exit();
?>
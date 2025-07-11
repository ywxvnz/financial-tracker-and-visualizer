<?php
include 'db_connect.php'; // Include your database connection file
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login page if not logged in
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if goal ID is provided in the URL
if (isset($_GET['id'])) {
    $goal_id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT); // Sanitize the input

    // Prepare a DELETE statement to prevent SQL injection
    // Ensure that only the goal belonging to the logged-in user can be deleted
    $stmt = $conn->prepare("DELETE FROM goals WHERE id = ? AND user_id = ?");

    if ($stmt === false) {
        // Handle error if prepare fails
        error_log("Failed to prepare statement: " . $conn->error);
        $_SESSION['error_message'] = "An error occurred during deletion setup.";
        header("Location: goals.php");
        exit();
    }

    // Bind parameters and execute the statement
    $stmt->bind_param("ii", $goal_id, $user_id);

    if ($stmt->execute()) {
        // Deletion successful
        $_SESSION['success_message'] = "Goal successfully deleted!";
    } else {
        // Deletion failed
        error_log("Failed to delete goal: " . $stmt->error);
        $_SESSION['error_message'] = "Failed to delete goal: " . $stmt->error;
    }

    $stmt->close();
} else {
    // If no goal ID is provided
    $_SESSION['error_message'] = "No goal ID provided for deletion.";
}

// Redirect back to the goals page
header("Location: goals.php");
exit();
?>
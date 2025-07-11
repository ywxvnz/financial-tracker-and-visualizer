<?php
include 'db_connect.php'; // Include your database connection
session_start();

header('Content-Type: application/json'); // Respond with JSON

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if goal_id and status are provided via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['goal_id']) && isset($_POST['status'])) {
    $goal_id = filter_var($_POST['goal_id'], FILTER_SANITIZE_NUMBER_INT);
    $status = $_POST['status']; // Should be 'finished'

    // Validate status to prevent arbitrary updates
    if ($status !== 'finished') {
        echo json_encode(['success' => false, 'message' => 'Invalid status provided.']);
        exit();
    }

    // Prepare and execute the update statement
    // Ensure that only the goal belonging to the logged-in user can be updated
    $stmt = $conn->prepare("UPDATE goals SET status = ? WHERE id = ? AND user_id = ?");

    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("sii", $status, $goal_id, $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Goal status updated successfully.']);
        } else {
            // This could mean the goal_id or user_id didn't match, or status was already 'finished'
            echo json_encode(['success' => false, 'message' => 'Goal not found, or status already updated.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update goal status: ' . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}

$conn->close();
?>
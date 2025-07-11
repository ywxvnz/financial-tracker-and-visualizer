<?php
include 'db_connect.php'; // Your database connection
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT balance FROM config WHERE user_id = ?"); // Assuming 'users' table has 'current_balance'
if ($stmt === false) {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit();
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    echo json_encode(['balance' => $user['balance']]);
} else {
    echo json_encode(['error' => 'User not found.']);
}

$stmt->close();
$conn->close();
?>
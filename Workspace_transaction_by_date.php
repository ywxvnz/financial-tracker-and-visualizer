<?php
session_start();
require 'db_connect.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$date = isset($_GET['date']) ? $_GET['date'] : '';

if (empty($date)) {
    echo json_encode(['error' => 'No date provided.']);
    exit;
}

if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
    echo json_encode(['error' => 'Invalid date format. Expected YYYY-MM-DD.']);
    exit;
}

$transactions = [];

$sql = "SELECT id, title, amount, type, category, date_issued
        FROM transactions
        WHERE user_id = ? AND DATE(date_issued) = ?
        ORDER BY date_issued ASC";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(['error' => 'Database prepare error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('is', $user_id, $date);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($transactions);
?>
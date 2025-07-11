<?php
session_start();
header('Content-Type: application/json');
include 'db_connect.php'; // Ensure this path is correct for your setup

$user_id = $_SESSION['user_id']; // Use user session

// Define the number of recent transactions to fetch
$limit = 5;

$month = isset($_GET['month']) ? $_GET['month'] : '';

$current_month = !empty($month) ? (int)$month : 0;

function buildDateFilter($month) {
    return !empty($month) ? "AND MONTH(date_issued) = ?" : "";
}

$sql = "SELECT category, amount, date_issued, type
        FROM transactions
        WHERE user_id = ? " . buildDateFilter($current_month) . "
        ORDER BY date_issued DESC
        LIMIT $limit";   // limit as literal, NOT bind param

// Prepare the statement
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log('MySQL prepare error: ' . $conn->error);
    echo json_encode(['error' => 'Database query preparation failed.']);
    exit();
}

// Bind parameters
if (!empty($current_month)) {
    $stmt->bind_param('ii', $user_id, $current_month);
} else {
    $stmt->bind_param('i', $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    // Format the date to DD/MM/YYYY for display
    $row['date_issued_formatted'] = date('d/m/Y', strtotime($row['date_issued']));
    $transactions[] = $row;
}

echo json_encode($transactions);

$stmt->close();
$conn->close();
?>


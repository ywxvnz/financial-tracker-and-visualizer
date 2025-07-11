<?php
session_start();
require 'db_connect.php'; // Your database connection file

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n'); // 'n' for month without leading zeros

if ($year < 1900 || $year > 2100 || $month < 1 || $month > 12) {
    echo json_encode(['error' => 'Invalid year or month provided.']);
    exit;
}

// Prepare SQL statement to fetch transaction counts for each day in the month
$sql = "SELECT DAY(date_issued) as day_of_month, COUNT(*) as transaction_count
        FROM transactions
        WHERE user_id = ? AND YEAR(date_issued) = ? AND MONTH(date_issued) = ?
        GROUP BY DAY(date_issued)
        ORDER BY day_of_month ASC";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(['error' => 'Database prepare error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('iii', $user_id, $year, $month);
$stmt->execute();
$result = $stmt->get_result();

$transactionCounts = [];
while ($row = $result->fetch_assoc()) {
    $transactionCounts[$row['day_of_month']] = (int)$row['transaction_count'];
}

$stmt->close();
$conn->close();

echo json_encode($transactionCounts);
?>
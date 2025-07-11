<?php
include 'db_connect.php';
session_start();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$year = $_GET['year'] ?? null;
$month = $_GET['month'] ?? null;

if (!$year || !$month) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$month = str_pad($month, 2, '0', STR_PAD_LEFT);
$startDate = "$year-$month-01";
$endDate = date("Y-m-t", strtotime($startDate)); // last day of month

$stmt = $conn->prepare("SELECT due_date FROM loans WHERE user_id = ? AND due_date BETWEEN ? AND ?");
$stmt->bind_param("iss", $user_id, $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

$dueDates = [];
while ($row = $result->fetch_assoc()) {
    $day = (int) date('j', strtotime($row['due_date']));
    $dueDates[] = $day;
}

echo json_encode($dueDates);

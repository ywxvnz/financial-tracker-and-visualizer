<?php
include 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT name, amount, due_date FROM loans WHERE user_id = ? AND status = 'Ongoing' AND due_date IS NOT NULL");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$dueLoans = [];
while ($row = $result->fetch_assoc()) {
    $date = $row['due_date'];
    $dueLoans[$date][] = [
        'name' => $row['name'],
        'amount' => number_format($row['amount'], 2)
    ];
}

header('Content-Type: application/json');
echo json_encode($dueLoans);
?>

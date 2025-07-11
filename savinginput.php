<?php include 'db_connect.php';
session_start();
if ($_SERVER['REQUEST_METHOD'] == 'POST'){
    $type = $_POST['type'];
    $title = $_POST['title'];
    $amount = $_POST['amount'];
    $date = date('Y-m-d', time());
    $user_id = $_SESSION['user_id'];

    try{
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, category, amount, title,date_issued) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("ississ", $user_id,$type,$type,$amount,$title,$date);
    $stmt->execute();
    

    }catch(mysqli_sql_exception $e){
        die("Error: " . $e->getMessage());
    }
}
header("Location: transaction.php");
exit();
?>

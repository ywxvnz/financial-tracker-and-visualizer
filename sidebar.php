<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Advisor</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
       <div class="side-nav">
        <div class="user">
            <img src="images/logogogo.png" class="user-img">
            <div class="user-info"> 
                <h2>Online Financial</h2>
            </div>
        </div>
        <ul>
            <li><a href="dashboard.php"><img src="images/menu1.png"> <p>Dashboard</p></a></li>
            <li><a href="profile.php"><img src="images/Profile.png"> <p>Profile</p></a></li>
            <li><a href="transaction.php"><img src="images/delete-card1.png"> <p>Add Transaction</p></a></li>
            <li><a href="loan.php"><img src="images/save-money1.png"> <p>Loans</p></a></li>
            <li><a href="goals.php"><img src="images/target1.png"> <p>Goals</p></a></li>
            <li><a href="calendar.php"><img src="images/schedule1.png"> <p>Calendar</p></a></li>
            <li><a href="reports.php"><img src="images/statistics1.png"> <p>Reports</p></a></li>

            
        </ul>

        <ul class="logout-section">
            <li><a href="index.php" onclick="return confirm('Are you sure?')"> <img src="images/logout1.png"> <p>Log Out</p></a></li>
            
        </ul>
       </div>


</body>

</html>
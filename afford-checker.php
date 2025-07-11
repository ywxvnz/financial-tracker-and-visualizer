
<?php
include 'db_connect.php';

$user_id = $_SESSION['user_id'];
$query = "SELECT balance, income, expenses FROM config WHERE user_id = $user_id";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

$balance = $row['balance'];
$income = $row['income'];
$expenses = $row['expenses'];
$daily_savings = max(0, ($income - $expenses) / 30);
?>

<style>
#afford-btn {
  position: fixed;
  bottom: 30px;
  right: 30px;
  background: #38a169;
  color: white;
  padding: 12px 18px;
  border-radius: 50px;
  font-weight: bold;
  border: none;
  z-index: 999;
  box-shadow: 0 4px 10px rgba(0,0,0,0.2);
  cursor: pointer;
}

#afford-box {
  display: none;
  position: fixed;
  bottom: 90px;
  right: 30px;
  background: white;
  border: 1px solid #ddd;
  padding: 20px;
  width: 300px;
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.2);
  z-index: 999;
}
</style>

<button id="afford-btn" onclick="toggleAffordBox()">💸 Can I Afford?</button>

<div id="afford-box">
  <strong>Check Affordability</strong>
  <input type="text" id="itemName" placeholder="Item name" style="width:100%; margin-top: 10px;" />
  <input type="number" id="itemCost" placeholder="Cost (₱)" style="width:100%; margin-top: 5px;" />
  <button onclick="checkAfford()" style="margin-top:10px; width:100%; background:#38a169; color:white; border:none; padding:8px;">Check</button>
  <div id="result" style="margin-top:10px;"></div>
</div>

<script>
  const balance = <?= $balance ?>;
  const dailySavings = <?= $daily_savings ?>;

  function toggleAffordBox() {
    const box = document.getElementById('afford-box');
    box.style.display = box.style.display === 'block' ? 'none' : 'block';
  }

  function checkAfford() {
    const item = document.getElementById('itemName').value;
    const cost = parseFloat(document.getElementById('itemCost').value);
    const result = document.getElementById('result');

    if (!item || isNaN(cost) || cost <= 0) {
      result.innerHTML = "❗ Please enter valid item and cost.";
      return;
    }

    if (cost <= balance) {
      const remaining = (balance - cost).toFixed(2);
      result.innerHTML = `✅ You can afford "<strong>${item}</strong>". Remaining balance after purchase: ₱${remaining}`;
    } else {
      const needed = (cost - balance).toFixed(2);
      let daysNeeded = "∞";
      let requiredDaily = "-";
      if (dailySavings > 0) {
        daysNeeded = Math.ceil(needed / dailySavings);
        requiredDaily = (needed / daysNeeded).toFixed(2);
        result.innerHTML = `❌ You can't afford "<strong>${item}</strong>". You need ₱${needed} more.<br>💡 At your current savings rate (₱${dailySavings}/day), you can afford this in <strong>${daysNeeded} day(s)</strong>.<br>📌 You need to save approximately ₱${requiredDaily} per day to reach your goal in that time.`;
      } else {
        result.innerHTML = `❌ You can't afford "<strong>${item}</strong>". You need ₱${needed} more.<br>💡 You're not currently saving. To afford this item, you would need to save ₱${needed} over your desired number of days.`;
      }
    }
  }
</script>

<?php
include 'db_connect.php'; // Include your database connection
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect if not logged in
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch active goals
$active_goals = [];
// Ensure 'saved_amount' is in your goals table
$stmt_active_goals = $conn->prepare("SELECT id, title, target_amount, saved_amount, duration, image_path, created_at FROM goals WHERE user_id = ? AND status = 'active'");
if ($stmt_active_goals === false) {
    die("Error preparing active goals statement: " . $conn->error);
}
$stmt_active_goals->bind_param("i", $user_id);
$stmt_active_goals->execute();
$result_active_goals = $stmt_active_goals->get_result();
while ($row = $result_active_goals->fetch_assoc()) {
    $active_goals[] = $row;
}
$stmt_active_goals->close();

// Fetch finished goals (for "View finished goals")
$finished_goals = [];
$stmt_finished_goals = $conn->prepare("SELECT id, title, target_amount, saved_amount, duration, image_path, status,  created_at FROM goals WHERE user_id = ? AND status = 'finished' ORDER BY created_at DESC");
if ($stmt_finished_goals === false) {
    die("Error preparing finished goals statement: " . $conn->error);
}
$stmt_finished_goals->bind_param("i", $user_id);
$stmt_finished_goals->execute();
$result_finished_goals = $stmt_finished_goals->get_result();
while ($row = $result_finished_goals->fetch_assoc()) {
    // Calculate duration for finished goals
    $start_date_obj = new DateTime($row['start_date']);
    $finish_date_obj = new DateTime($row['target_date']); // Assuming target_date is the finish date when status is 'finished'
    $interval = $start_date_obj->diff($finish_date_obj);
    $row['duration'] = $interval->days . " days";
    $finished_goals[] = $row;
}
$stmt_finished_goals->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Goals</title>
  <link rel="stylesheet" href="style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="page">
      <div class="sidebarspace"><?php include 'sidebar.php'?> </div>
  

  <div class="goals-container">
    <div class="goals-header">
    <div class="left-header">
      <h1>Goals</h1>
      <button class="plus-button" title="Add New Goal">+</button>
    </div>
    <!--<a href="#" class="view-finished">View finished goals</a>-->
  </div>
  <div class="GoalStorage">
    <?php if (empty($active_goals)): ?>
        <p style="width:100%; text-align:center; margin-top: 50px; font-size:1.2rem; color:#666;">No active goals found. Click the '+' button to add a new goal!</p>
    <?php else: ?>
        <?php foreach ($active_goals as $goal):
            $saved_percentage = ($goal['target_amount'] > 0) ? round(($goal['saved_amount'] / $goal['target_amount']) * 100) : 0;
            $remaining_percentage = 100 - $saved_percentage;
            // Ensure image path is correct, add a default if empty
            $image_path = !empty($goal['image_path']) ? htmlspecialchars($goal['image_path']) : 'images/default_goal.png'; // Make sure you have a default image
            // Format dates for display
            
        ?>
            <div class="goal-item" onclick="showGoalDetails(this)">
                <div class="goal-circle">
                    <div class="circle-progress" style="background: conic-gradient(var(--saved-color) 0% <?= $saved_percentage ?>%, var(--remaining-color) <?= $saved_percentage ?>% 100%);">
                        <div class="circle-inner">
                            <img src="<?= $image_path ?>" alt="<?= htmlspecialchars($goal['title']) ?> Image">
                        </div>
                    </div>
                </div>
                <div class="goal-text-details">
                    <h3><?= htmlspecialchars($goal['title']) ?></h3>
                    <p>Since: <span class="start-date"><?= date('F j, Y', strtotime($goal['created_at']))?></span></p>
                    <p><?= $saved_percentage ?>% Saved</p>
                    <p><?= $remaining_percentage ?>% Remaining</p>
                    <input type="hidden" class="goal-id" value="<?= htmlspecialchars($goal['id']) ?>">
                    <input type="hidden" class="goal-title" value="<?= htmlspecialchars($goal['title']) ?>">
                    <input type="hidden" class="goal-date" value="<?=  date('F j, Y', strtotime($goal['created_at'])) ?>">
                    <input type="hidden" class="goal-image-path" value="<?= $image_path ?>">
                    <input type="hidden" class="goal-target-amount" value="<?= htmlspecialchars($goal['target_amount']) ?>">
                    <input type="hidden" class="goal-saved-amount" value="<?= htmlspecialchars($goal['saved_amount']) ?>">
                    <input type="hidden" class="goal-duration" value="<?= htmlspecialchars($goal['duration']) ?>">
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
  </div>
  </div>

  <div id="goal-details-modal" class="modal hidden">
    <div class="modal-content">
        <div class="modal-left">
            <span class="close-button" onclick="closeModal()">&times;</span>
            <img id="modal-image" src="" alt="Goal Image" / src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/a0cda002-7f39-4c4c-8a57-407c4ce772da.png">
            <h3 id="modal-title">Goal Name</h3>
            <p id="modal-start">Started: --</p>
            <p id="modal-amount">Target Amount: ₱---</p>
            <p id="modal-saved-amount">Saved Amount: ₱---</p>
            <p id="modal-saved-percent">Saved: --%</p>
            <p id="modal-remaining-percent">Remaining: --%</p>
            <div class="goal-actions">
                <a id="edit-link" href="#" title="Edit Goal">
                  <i class="fa-solid fa-pen-to-square"></i><br><small>Edit</small>
                </a>
                <a id="delete-link" href="#" title="Delete Goal" onclick="return confirm('Are you sure you want to delete this goal?');">
                  <i class="fa-solid fa-trash"></i><br><small>Delete</small>
                </a>
                <a id="fund-link" href="#" title="Fund this Goal">
                  <i class="fa-solid fa-money-bill-transfer"></i><br><small>Fund</small>
                </a>
            </div>
        </div>
    </div>
  </div>

 <div id="add-goal-container" class="goal-form">
  <div class="add-goal-content"> <span class="close-button" onclick="closeModal()">&times;</span>
           <h2>Add New Goal</h2>
  <form action="add_goal.php" method="POST" enctype="multipart/form-data">


   <div class="form-columns">
          <div class="calendar-side">
        <label class="upload-button" for="goal-image">
              <i class="fa fa-camera"></i> Upload Image
            </label>
            <input type="file" name="goal-image" id="goal-image" accept="image/*"onchange="previewImage(event)" />
        <img id="goalImagePreview"  style="display:none; max-width: 10vw; margin: 10px 0; border-radius: 8px; " />
          </div>
        <!-- Goal Form -->
        
        <div class="details-side">
          <label for="goal-name">Name:</label>
          <input type="text" name="goal-name" id="goal-name" placeholder="e.g. New Phone" required />
          <label for="goal-amount">Target Amount (₱):</label> <input type="number" name="goal-amount" id="goal-amount" placeholder="₱0.00" step="0.01" required />

          

          <!-- Duration Slider 
          <label for="duration">Duration (Days): <span id="durationLabel">1</span> day(s)</label>
          <input type="range" id="duration" name="duration" min="1" max="365" value="1" oninput="updateDuration()"> -->

          <!-- Submit Button -->
          <button type="submit">Add Goal</button>
          </div>
          </div>
  </form>
</div>
</div>

  <div id="finished-goals-container" class="hidden finished-goals-wrapper">
    <h2>Finished Goals</h2>
    <div id="finished-goals-table" class="finished-goals-table">
      <div class="finished-header">
        <span class="close-button" onclick="closeModal()">&times;</span>
        <div>Date Started</div>
        <div>Date Finished</div>
        <div>Goal Name</div>
        <div>Duration</div>
        <div>Amount (₱)</div>
      </div>
        <?php if (empty($finished_goals)): ?>
            <div class="finished-row"><div colspan="5" style="text-align: center; padding: 20px;">No finished goals yet.</div></div>
        <?php else: ?>
            <?php foreach ($finished_goals as $finished_goal): ?>
                <div class="finished-row" id="finished-goal-<?= $finished_goal['id'] ?>">
                    <div><?= (new DateTime($finished_goal['start_date']))->format('m/d/Y') ?></div>
                    <div><?= (new DateTime($finished_goal['target_date']))->format('m/d/Y') ?></div>
                    <div><?= htmlspecialchars($finished_goal['title']) ?></div>
                    <div><?= htmlspecialchars($finished_goal['duration']) ?></div>
                    <div>₱<?= number_format($finished_goal['target_amount'], 2) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
  </div>
  </div>





  <script>
    closeModal();
    let isFundingInProgress = false; 

    function showGoalDetails(goalElement) {
        const goalId = goalElement.querySelector('.goal-id').value;
        const goalTitle = goalElement.querySelector('.goal-title').value;
        const goalImagePath = goalElement.querySelector('.goal-image-path').value;
        const created = goalElement.querySelector('.goal-date').value;
        const goalTargetAmount = parseFloat(goalElement.querySelector('.goal-target-amount').value);
        const goalSavedAmount = parseFloat(goalElement.querySelector('.goal-saved-amount').value);
        const goalDuration = parseInt(goalElement.querySelector('.goal-duration').value); // Make sure 'duration' is available from PHP

        const savedPercentage = (goalTargetAmount > 0) ? ((goalSavedAmount / goalTargetAmount) * 100).toFixed(0) : 0;
        const remainingPercentage = (100 - savedPercentage).toFixed(0);

        document.getElementById('modal-title').textContent = goalTitle;
        document.getElementById('modal-image').src = goalImagePath;
        document.getElementById('modal-start').textContent = `Started: ${created}`;
        document.getElementById('modal-amount').textContent = `Target Amount: ₱${goalTargetAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        document.getElementById('modal-saved-amount').textContent = `Saved Amount: ₱${goalSavedAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        document.getElementById('modal-saved-percent').textContent = `Saved: ${savedPercentage}%`;
        document.getElementById('modal-remaining-percent').textContent = `Remaining: ${remainingPercentage}%`;

        document.getElementById('edit-link').href = `edit_goal.php?id=${goalId}`;
        document.getElementById('delete-link').href = `delete_goal.php?id=${goalId}`;
        const today = new Date().toISOString().slice(0, 16);
        document.getElementById('fund-link').href = `transaction.php?fund_goal_id=${goalId}&date=${today}`;

        // Hide fund button if goal is fully funded (100%)
        const fundLink = document.getElementById('fund-link');
        if (parseInt(savedPercentage) >= 100) {
          fundLink.style.display = 'none';
        } else {
          fundLink.style.display = 'inline-block';
        }

        // Calculate daily amount
        const remainingAmountToFund = goalTargetAmount - goalSavedAmount;
        let dailyAmount = 0;
        if (goalDuration > 0) {
          dailyAmount = remainingAmountToFund / goalDuration;
        }

        // Determine how many boxes have conceptually been "funded" based on saved_amount
        const numberOfFundedBoxes = Math.floor(goalSavedAmount / dailyAmount);

        // (Other functionality can remain the same, omitted here for brevity...)

        const modal = document.getElementById('goal-details-modal');
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function updateDuration() {
        const days = document.getElementById('duration').value;
        document.getElementById('durationLabel').innerText = days;

      }
    // Keep your existing handleFundBoxClick function as is for the daily boxes


    function previewImage(event) {
      const reader = new FileReader();
      reader.onload = function() {
        const preview = document.getElementById('goalImagePreview');
        preview.src = reader.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(event.target.files[0]);
    }
    // Function to close all modals/overlays
    function closeModal() {
      document.getElementById('goal-details-modal').classList.add('hidden');
      document.getElementById('add-goal-container').classList.add('hidden');
      document.getElementById('add-goal-container').classList.remove('fullscreen-overlay'); // Ensure overlay class is removed
      document.getElementById('finished-goals-container').classList.add('hidden');
      document.getElementById('finished-goals-container').classList.remove('fullscreen-overlay'); // Ensure overlay class is removed
      document.body.style.overflow = ''; // Restore scrolling
    }

    const addGoalContainer = document.getElementById("add-goal-container");
    const finishedGoalsContainer = document.getElementById("finished-goals-container");
    const plusButton = document.querySelector(".plus-button");
    const viewFinishedLink = document.querySelector(".view-finished");

    let isAddGoalVisible = false;
    let isFinishedGoalsVisible = false;

    plusButton.addEventListener("click", () => {
      // Close other modals if open
      document.getElementById('goal-details-modal').classList.add('hidden');
      toggleOverlay(finishedGoalsContainer, false); // Explicitly close finished goals

      isAddGoalVisible = !isAddGoalVisible;
      toggleOverlay(addGoalContainer, isAddGoalVisible);
    });

    viewFinishedLink.addEventListener("click", (event) => {
      event.preventDefault(); // Prevent default link behavior
      // Close other modals if open
      document.getElementById('goal-details-modal').classList.add('hidden');
      toggleOverlay(addGoalContainer, false); // Explicitly close add goal

      isFinishedGoalsVisible = !isFinishedGoalsVisible;
      toggleOverlay(finishedGoalsContainer, isFinishedGoalsVisible);
    });

    function toggleOverlay(container, show) {
      if (show) {
        container.classList.remove("hidden");
        container.classList.add("fullscreen-overlay");
        document.body.style.overflow = 'hidden';
      } else {
        container.classList.add("hidden");
        container.classList.remove("fullscreen-overlay");
        document.body.style.overflow = '';
      }
    }

    window.onload = () => {
      const today = new Date().toISOString().split("T")[0];
      const now = new Date().toISOString().slice(0, 16);

      document.getElementById("goal-date-start").setAttribute("min", today);
      document.getElementById("goal-date-target").setAttribute("min", today);


      // Select all close buttons that might be active
      const closeButtons = document.querySelectorAll("#add-goal-container .close-button, #goal-details-modal .close-button, #finished-goals-container .close-button");
      closeButtons.forEach(btn => {
        btn.addEventListener("click", closeModal); // Assign the single closeModal function
      });
    };
    window.onload = updateDuration;
  </script>
</body>
<?php include 'chatbot_widget.php'; ?>
</html>


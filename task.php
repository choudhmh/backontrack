<?php
include("includes/auth.php");
include("config/db.php");
include("includes/header.php");
include("includes/navbar.php");

$user_id = $_SESSION["user_id"];
?>

<h2>My Tasks</h2>

<!-- ADD TASK -->
<form method="POST" action="actions/create-task.php">
  <input type="text" name="title" placeholder="Task title" required>
  <input type="date" name="deadline" required>
  <button type="submit">Add Task</button>
</form>

<hr>

<h3>Your Tasks</h3>

<?php
$sql = "SELECT * FROM tasks WHERE user_id = $user_id ORDER BY deadline ASC";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
  echo "<div style='margin-bottom:10px;'>";

  echo "<strong>" . $row["title"] . "</strong><br>";
  echo "Deadline: " . $row["deadline"] . "<br>";
  echo "Status: " . $row["status"] . "<br>";

  if ($row["status"] == "pending") {
    echo "<a href='actions/complete-task.php?id=" . $row["id"] . "'>✔ Complete</a> ";
  }

  echo "<a href='actions/delete-task.php?id=" . $row["id"] . "'>❌ Delete</a>";

  echo "</div><hr>";
}
?>

<?php include("includes/footer.php"); ?>
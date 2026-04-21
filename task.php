<?php
include("includes/auth.php");
include("config/db.php");
include("includes/header.php");
include("includes/navbar.php");

$user_id = $_SESSION["user_id"];
$class_id = $_SESSION["class_id"] ?? 0;

/* =========================
   CHECK CLASS
========================= */
if (!$class_id) {
    echo "<p>Please join a class first.</p>";
    include("includes/footer.php");
    exit();
}

$class = $conn->query("SELECT name FROM classes WHERE id=$class_id")->fetch_assoc();
?>

<h2>My Tasks</h2>

<h3>Class: <?php echo htmlspecialchars($class['name'] ?? ''); ?></h3>

<!-- ADD TASK -->
<form method="POST" action="actions/create-task.php" class="task-form">
  <input type="text" name="title" placeholder="Task title" required>
  <input type="text" name="description" placeholder="Task description">
  <input type="date" name="deadline" required>
  <button type="submit">Add Task</button>
</form>

<hr>

<h3>Your Tasks</h3>

<?php
$sql = "SELECT * FROM tasks 
        WHERE user_id=$user_id AND class_id=$class_id 
        ORDER BY deadline ASC";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {

    echo "<table class='task-table'>";
    echo "<tr>
            <th>Task</th>
            <th>Description</th>
            <th>Deadline</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>";

    while ($row = $result->fetch_assoc()) {

        $status = $row["status"] ?? 'pending';

        $badge = ($status == "done")
            ? "<span class='badge done'>Completed</span>"
            : "<span class='badge pending'>Pending</span>";

        echo "<tr>";

        echo "<td>" . htmlspecialchars($row["title"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["description"] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row["deadline"]) . "</td>";
        echo "<td>" . $badge . "</td>";

        echo "<td>";

        if ($status == "pending") {
            echo "<a href='actions/complete-task.php?id=" . $row["id"] . "'>✔</a> ";
        }

        echo "<a href='actions/delete-task.php?id=" . $row["id"] . "'>❌</a>";

        echo "</td>";

        echo "</tr>";
    }

    echo "</table>";

} else {
    echo "<p>No tasks found for this class.</p>";
}
?>

<?php include("includes/footer.php"); ?>
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

/* =========================
   GET CLASS NAME
========================= */
$stmt = $conn->prepare("SELECT name FROM classes WHERE id=?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();
?>

<h2>My Tasks</h2>

<h3>Class: <?= htmlspecialchars($class['name'] ?? ''); ?></h3>

<!-- ADD TASK -->
<form method="POST" action="actions/create-task.php" class="task-form">
  <input type="text" name="title" placeholder="Task title" required>
  <input type="text" name="description" placeholder="Task description">
  <input type="text" name="notes" placeholder="Task note (optional)">
  <input type="date" name="deadline" required>
  <button type="submit">Add Task</button>
</form>

<hr>

<h3>Your Tasks</h3>

<?php
$sql = "SELECT * FROM tasks 
WHERE user_id=? AND class_id=? 
ORDER BY 
    CASE 
        WHEN status='done' THEN 5
        WHEN deadline < CURDATE() THEN 1
        WHEN deadline = CURDATE() THEN 2
        WHEN deadline <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 3
        ELSE 4
    END,
    deadline ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $class_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {

    echo "<table class='task-table'>";
    echo "<tr>
            <th>Task</th>
            <th>Description</th>
            <th>Notes</th>
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

        // NEW NOTE COLUMN
        echo "<td>" . htmlspecialchars($row["notes"] ?? '') . "</td>";

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
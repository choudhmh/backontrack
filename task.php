<?php
include("includes/auth.php");
include("config/db.php");
include("includes/lang.php");
include("includes/header.php");
include("includes/navbar.php");

$user_id = $_SESSION["user_id"];
$class_id = $_SESSION["class_id"] ?? 0;

/* =========================
   CHECK CLASS
========================= */
if (!$class_id) {
    echo "<p>" . t("join_class_first") . "</p>";
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

<h2><?= t("my_tasks") ?></h2>

<h3><?= t("class") ?>: <?= htmlspecialchars($class['name'] ?? '') ?></h3>

<!-- ADD TASK -->
<form method="POST" action="actions/create-task.php" class="task-form">
  <input type="text" name="title" placeholder="<?= t("task_title") ?>" required>
  <input type="text" name="description" placeholder="<?= t("task_description") ?>">
  <input type="text" name="notes" placeholder="<?= t("task_notes") ?>">
  <input type="date" name="deadline" required>
  <button type="submit"><?= t("add_task") ?></button>
</form>

<hr>

<h3><?= t("Your_Tasks") ?></h3>

<?php
$sql = "
SELECT * FROM tasks 
WHERE user_id=? AND class_id=? 
ORDER BY 
    CASE 
        WHEN status='done' THEN 5
        WHEN deadline < CURDATE() THEN 1
        WHEN deadline = CURDATE() THEN 2
        WHEN deadline <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 3
        ELSE 4
    END,
    deadline ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $class_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {

    echo "<table class='task-table'>";
    echo "<tr>
            <th>" . t("task") . "</th>
            <th>" . t("description") . "</th>
            <th>" . t("notes") . "</th>
            <th>" . t("deadline") . "</th>
            <th>" . t("status") . "</th>
            <th>" . t("actions") . "</th>
          </tr>";

    while ($row = $result->fetch_assoc()) {

        $status = $row["status"] ?? 'pending';
        $deadline = $row["deadline"];

        $isOverdue = ($status != "done" && $deadline < date("Y-m-d"));

        if ($status == "done") {
            $badge = "<span class='badge done'>" . t("completed") . "</span>";
        } elseif ($isOverdue) {
            $badge = "<span class='badge overdue'>" . t("overdue") . "</span>";
        } else {
            $badge = "<span class='badge pending'>" . t("pending") . "</span>";
        }

        echo "<tr>";

        // ⚠️ DB content (NOT translated automatically)
        echo "<td>" . htmlspecialchars($row["title"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["description"] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row["notes"] ?? '') . "</td>";

        echo "<td>" . htmlspecialchars($deadline) . "</td>";
        echo "<td>" . $badge . "</td>";

        echo "<td>";

        if ($status == "pending") {
            echo "<a href='actions/complete-task.php?id=" . $row["id"] . "' title='".t("mark_done")."'>✔</a> ";
        }

        echo "<a href='actions/delete-task.php?id=" . $row["id"] . "' title='".t("delete")."'>❌</a>";

        echo "</td>";

        echo "</tr>";
    }

    echo "</table>";

} else {
    echo "<p>" . t("no_tasks") . "</p>";
}
?>

<?php include("includes/footer.php"); ?>
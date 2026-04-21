<?php
include("includes/auth.php");
include("config/db.php");
include("includes/header.php");
include("includes/navbar.php");

$user_id = $_SESSION["user_id"];

/* =========================
   GET USER CLASSES
========================= */
$stmt = $conn->prepare("
    SELECT c.id, c.name 
    FROM classes c
    JOIN class_members cm ON c.id = cm.class_id
    WHERE cm.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$classes = $stmt->get_result();

/* =========================
   AUTO RESTORE CLASS IF MISSING
========================= */
$class_id = $_SESSION["class_id"] ?? null;

if (!$class_id) {

    $stmt = $conn->prepare("
        SELECT class_id 
        FROM class_members 
        WHERE user_id = ? 
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        $class_id = $row['class_id'];
        $_SESSION['class_id'] = $class_id;
    }
}

/* =========================
   SAFETY CHECK
========================= */
if (!$class_id) {
    echo "<p>Please join a class first.</p>";
    include("includes/footer.php");
    exit();
}

/* =========================
   CLASS SWITCH
========================= */
if (isset($_POST['switch_class'])) {
    $_SESSION['class_id'] = (int)$_POST['class_id'];
    header("Location: dashboard.php");
    exit();
}

/* =========================
   CLASS INFO
========================= */
$stmt = $conn->prepare("SELECT name FROM classes WHERE id=?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();

$class_name = $class['name'] ?? 'Unknown Class';

/* =========================
   TASK STATS
========================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) as c 
    FROM tasks 
    WHERE user_id=? AND class_id=?
");
$stmt->bind_param("ii", $user_id, $class_id);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()["c"] ?? 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) as c 
    FROM tasks 
    WHERE user_id=? AND class_id=? AND status='done'
");
$stmt->bind_param("ii", $user_id, $class_id);
$stmt->execute();
$done = $stmt->get_result()->fetch_assoc()["c"] ?? 0;

$pending = $total - $done;
$progress = ($total > 0) ? round(($done / $total) * 100) : 0;
?>

<h2>Dashboard</h2>

<!-- CLASS SWITCH -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">

<form method="POST">
    <select name="class_id" onchange="this.form.submit()">
        <?php while ($c = $classes->fetch_assoc()) { ?>
            <option value="<?= $c['id']; ?>"
                <?= ($c['id'] == $class_id) ? "selected" : ""; ?>>
                <?= htmlspecialchars($c['name']); ?>
            </option>
        <?php } ?>
    </select>
    <input type="hidden" name="switch_class" value="1">
</form>

</div>

<h3>Class: <?= htmlspecialchars($class_name); ?></h3>

<!-- STATS -->
<div class="dashboard-grid">

  <div class="stats">
    <div class="box">Total Tasks: <?= $total; ?></div>
    <div class="box">Completed: <?= $done; ?></div>
    <div class="box">Pending: <?= $pending; ?></div>
    <div class="box">Progress: <?= $progress; ?>%</div>
  </div>

  <div class="chart-container">
    <canvas id="progressChart"></canvas>
  </div>

</div>

<!-- TASK TABLE -->
<h3>Your Tasks</h3>

<?php
$stmt = $conn->prepare("
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
");
$stmt->bind_param("ii", $user_id, $class_id);
$stmt->execute();
$tasks = $stmt->get_result();
$stmt->bind_param("ii", $user_id, $class_id);
$stmt->execute();
$tasks = $stmt->get_result();

if ($tasks && $tasks->num_rows > 0) {

    echo "<table class='task-table'>";
    echo "<tr>
            <th>Task</th>
            <th>Description</th>
            <th>Notes</th>
            <th>Deadline</th>
            <th>Status</th>
          </tr>";

    while ($t = $tasks->fetch_assoc()) {

        $status = $t["status"] ?? 'pending';
        $deadline = $t["deadline"];

        $isOverdue = ($status != "done" && $deadline < date("Y-m-d"));

        if ($status == "done") {
            $badge = "<span class='badge done'>Completed</span>";
        } elseif ($isOverdue) {
            $badge = "<span class='badge overdue'>Overdue</span>";
        } else {
            $badge = "<span class='badge pending'>Pending</span>";
        }

        echo "<tr>";
        echo "<td>" . htmlspecialchars($t["title"]) . "</td>";
        echo "<td>" . htmlspecialchars($t["description"] ?? '') . "</td>";

        // ✅ FIXED NOTES COLUMN
        echo "<td>" . htmlspecialchars($t["notes"] ?? '') . "</td>";

        echo "<td>" . htmlspecialchars($deadline) . "</td>";
        echo "<td>" . $badge . "</td>";
        echo "</tr>";
    }

    echo "</table>";

} else {
    echo "<p>No tasks found for this class.</p>";
}
?>

<!-- CHART -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const ctx = document.getElementById('progressChart').getContext('2d');

new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Completed', 'Pending'],
        datasets: [{
            data: [<?= $done; ?>, <?= $pending; ?>]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>

<?php include("includes/footer.php"); ?>
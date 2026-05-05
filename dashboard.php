<?php
include("includes/auth.php");
include("config/db.php");
include("includes/lang.php"); // MUST come before UI
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
   AUTO RESTORE CLASS
========================= */
$class_id = $_SESSION["class_id"] ?? null;

if (!$class_id) {
    $stmt = $conn->prepare("
        SELECT class_id 
        FROM class_members 
        WHERE user_id=? LIMIT 1
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
    echo "<p>" . t("join_class_first") . "</p>";
    include("includes/footer.php");
    exit();
}

/* =========================
   SWITCH CLASS
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

$class_name = $class['name'] ?? 'Unknown';

/* =========================
   TASK STATS
========================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) as c FROM tasks 
    WHERE user_id=? AND class_id=?
");
$stmt->bind_param("ii", $user_id, $class_id);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()["c"] ?? 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) as c FROM tasks 
    WHERE user_id=? AND class_id=? AND status='done'
");
$stmt->bind_param("ii", $user_id, $class_id);
$stmt->execute();
$done = $stmt->get_result()->fetch_assoc()["c"] ?? 0;

$pending = $total - $done;
$progress = ($total > 0) ? round(($done / $total) * 100) : 0;

/* =========================
   DEADLINE NOTIFICATIONS
========================= */
$upcoming_stmt = $conn->prepare("
    SELECT title, deadline 
    FROM tasks
    WHERE user_id=? 
    AND class_id=? 
    AND status='pending'
    AND deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 45 HOUR)
    ORDER BY deadline ASC
");
$upcoming_stmt->bind_param("ii", $user_id, $class_id);
$upcoming_stmt->execute();
$notifications = $upcoming_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<h2><?= t("dashboard") ?></h2>

<!-- 🔔 NOTIFICATIONS -->
<?php if (!empty($notifications)) { ?>
<div id="deadlineAlert" style="
    position:fixed; top:20px; right:20px;
    background:#fff; border-left:6px solid #ff9800;
    padding:15px; width:300px;
    box-shadow:0 5px 15px rgba(0,0,0,0.2);
    border-radius:10px; z-index:9999;
">
    <strong><?= t("upcoming_deadlines") ?></strong><br><br>

    <?php foreach ($notifications as $n): ?>
        <div style="margin-bottom:8px;">
            📌 <?= htmlspecialchars($n['title']); ?><br>
            <small><?= t("due") ?>: <?= htmlspecialchars($n['deadline']); ?></small>
        </div>
    <?php endforeach; ?>

    <button onclick="closeAlert()" style="
        margin-top:10px; padding:5px 10px;
        border:none; background:#f44336;
        color:white; border-radius:5px; cursor:pointer;
    "><?= t("dismiss") ?></button>
</div>

<script>
function closeAlert() {
    document.getElementById("deadlineAlert").style.display = "none";
}
</script>
<?php } ?>

<!-- CLASS SWITCH -->
<div style="display:flex; justify-content:space-between; margin-bottom:15px;">
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

<h3><?= t("class") ?>: <?= htmlspecialchars($class_name); ?></h3>

<!-- STATS -->
<div class="dashboard-grid">
  <div class="stats">
    <div class="box"><?= t("total_tasks") ?>: <?= $total; ?></div>
    <div class="box"><?= t("completed") ?>: <?= $done; ?></div>
    <div class="box"><?= t("pending") ?>: <?= $pending; ?></div>
    <div class="box"><?= t("progress") ?>: <?= $progress; ?>%</div>
  </div>
  <div class="chart-container">
    <canvas id="progressChart"></canvas>
  </div>
</div>

<!-- TASK TABLE -->
<h3><?= t("Your_Tasks") ?></h3>

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

if ($tasks->num_rows > 0) {

    echo "<table class='task-table'>";
    echo "<tr>
            <th>".t("task")."</th>
            <th>".t("description")."</th>
            <th>".t("notes")."</th>
            <th>".t("deadline")."</th>
            <th>".t("status")."</th>
          </tr>";

    while ($t = $tasks->fetch_assoc()) {

        $status = $t["status"];
        $deadline = $t["deadline"];

        $isOverdue = ($status != "done" && $deadline < date("Y-m-d"));

        if ($status == "done") {
            $badge = "<span class='badge done'>".t("completed")."</span>";
        } elseif ($isOverdue) {
            $badge = "<span class='badge overdue'>".t("overdue")."</span>";
        } else {
            $badge = "<span class='badge pending'>".t("pending")."</span>";
        }

        echo "<tr>";
        echo "<td>" . htmlspecialchars($t["title"]) . "</td>";
        echo "<td>" . htmlspecialchars($t["description"] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($t["notes"] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($deadline) . "</td>";
        echo "<td>" . $badge . "</td>";
        echo "</tr>";
    }

    echo "</table>";

} else {
    echo "<p>" . t("no_tasks") . "</p>";
}
?>

<!-- CHART -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('progressChart'), {
    type: 'doughnut',
    data: {
        labels: ['<?= t("completed") ?>', '<?= t("pending") ?>'],
        datasets: [{
            data: [<?= $done ?>, <?= $pending ?>]
        }]
    }
});
</script>

<?php include("includes/footer.php"); ?>
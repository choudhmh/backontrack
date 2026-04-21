<?php
include("includes/auth.php");
include("config/db.php");
include("includes/header.php");
include("includes/navbar.php");

$user_id = $_SESSION["user_id"];

/* =========================
   CLASS SWITCH HANDLER
========================= */
if (isset($_POST['switch_class'])) {
    $_SESSION['class_id'] = $_POST['class_id'];
    header("Location: dashboard.php");
    exit();
}

/* =========================
   GET USER CLASSES
========================= */
$classes = $conn->query("
    SELECT c.id, c.name 
    FROM classes c
    JOIN class_members cm ON c.id = cm.class_id
    WHERE cm.user_id = $user_id
");

/* =========================
   CURRENT CLASS
========================= */
$class_id = $_SESSION["class_id"] ?? 0;

if (!$class_id) {
    echo "<p>Please join a class first.</p>";
    include("includes/footer.php");
    exit();
}



/* =========================
   CLASS INFO
========================= */
$class = $conn->query("SELECT name FROM classes WHERE id=$class_id")->fetch_assoc();
$class_name = $class['name'] ?? 'Unknown Class';

/* =========================
   TASK STATS
========================= */
$total = $conn->query("
    SELECT COUNT(*) as c 
    FROM tasks 
    WHERE user_id=$user_id AND class_id=$class_id
")->fetch_assoc()["c"] ?? 0;

$done = $conn->query("
    SELECT COUNT(*) as c 
    FROM tasks 
    WHERE user_id=$user_id AND class_id=$class_id AND status='done'
")->fetch_assoc()["c"] ?? 0;

$pending = $total - $done;
$progress = ($total > 0) ? round(($done / $total) * 100) : 0;
?>

<h2>Dashboard</h2>

<!-- =========================
     CLASS SWITCH + USER INFO
========================= -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">

  <!-- CLASS SWITCH -->
  <form method="POST">
    <select name="class_id" onchange="this.form.submit()">
      <?php while ($c = $classes->fetch_assoc()) { ?>
        <option value="<?php echo $c['id']; ?>"
          <?php if ($c['id'] == $class_id) echo "selected"; ?>>
          <?php echo htmlspecialchars($c['name']); ?>
        </option>
      <?php } ?>
    </select>
    <input type="hidden" name="switch_class" value="1">
  </form>


</div>

<h3>Class: <?php echo htmlspecialchars($class_name); ?></h3>

<!-- =========================
     STATS + CHART
========================= -->
<div class="dashboard-grid">

  <div class="stats">
    <div class="box">Total Tasks: <?php echo $total; ?></div>
    <div class="box">Completed: <?php echo $done; ?></div>
    <div class="box">Pending: <?php echo $pending; ?></div>
    <div class="box">Progress: <?php echo $progress; ?>%</div>
  </div>

  <div class="chart-container">
    <canvas id="progressChart"></canvas>
  </div>

</div>

<!-- =========================
     TASK TABLE
========================= -->
<h3>Your Tasks</h3>

<?php
$tasks = $conn->query("
    SELECT * FROM tasks 
    WHERE user_id=$user_id AND class_id=$class_id 
    ORDER BY deadline ASC
");

if ($tasks && $tasks->num_rows > 0) {

    echo "<table class='task-table'>";
    echo "<tr>
            <th>Task</th>
            <th>Description</th>
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
        echo "<td>" . htmlspecialchars($deadline) . "</td>";
        echo "<td>" . $badge . "</td>";
        echo "</tr>";
    }

    echo "</table>";

} else {
    echo "<p>No tasks found for this class.</p>";
}
?>

<!-- =========================
     CHART
========================= -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<script>
const ctx = document.getElementById('progressChart').getContext('2d');

new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Completed', 'Pending'],
        datasets: [{
            data: [<?php echo $done; ?>, <?php echo $pending; ?>]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php include("includes/footer.php"); ?>
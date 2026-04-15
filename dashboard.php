<?php
include("includes/auth.php");
include("config/db.php");
include("includes/header.php");
include("includes/navbar.php");

$user_id = $_SESSION["user_id"];


/* =========================
   TASK STATS
========================= */
$total = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE user_id=$user_id")
->fetch_assoc()["c"] ?? 0;

$done = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE user_id=$user_id AND status='done'")
->fetch_assoc()["c"] ?? 0;

$pending = $total - $done;

$progress = ($total > 0) ? round(($done / $total) * 100) : 0;
?>

<h2>Recovery Progress Dashboard</h2>


<!-- STATS -->
<div class="stats">
  <div class="box">Total Tasks: <?php echo $total; ?></div>
  <div class="box">Completed: <?php echo $done; ?></div>
  <div class="box">Pending: <?php echo $pending; ?></div>
  <div class="box">Progress: <?php echo $progress; ?>%</div>
</div>

<!-- TASK TABLE -->
<h3>Your Tasks</h3>

<?php
$tasks = $conn->query("SELECT * FROM tasks WHERE user_id=$user_id ORDER BY deadline ASC");

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

        $badge = ($status == "done")
            ? "<span class='badge done'>Completed</span>"
            : "<span class='badge pending'>Pending</span>";

        echo "<tr>";
        echo "<td>" . htmlspecialchars($t["title"]) . "</td>";
        echo "<td>" . htmlspecialchars($t["description"] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($t["deadline"]) . "</td>";
        echo "<td>" . $badge . "</td>";
        echo "</tr>";
    }

    echo "</table>";

} else {
    echo "<p>No tasks found.</p>";
}
?>

<!-- CHART -->
<div class="chart-container">
  <canvas id="progressChart"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<script>
const ctx = document.getElementById('progressChart').getContext('2d');

new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Completed', 'Pending'],
        datasets: [{
            data: [<?php echo $done; ?>, <?php echo $pending; ?>],
            backgroundColor: ['#4CAF50', '#FF9800']
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
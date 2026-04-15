<?php
// safety check (important if session not started)
if (!isset($_SESSION)) {
    session_start();
}

$user_id = $_SESSION["user_id"] ?? null;

$username = "User";

if ($user_id && isset($conn)) {
    $result = $conn->query("SELECT * FROM users WHERE id=$user_id");

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $username = $user['name'] ?? $user['email'] ?? 'User';
    }
}
?>

<nav class="navbar">

  <div class="nav-left">
    <a href="dashboard.php">Dashboard</a>
    <a href="task.php">Tasks</a>
    <a href="recovery-plan.php">AI Recommendations</a>
    <a href="materials.php">Materials</a>
    <a href="notes.php">Notes</a>
  </div>

  <div class="nav-right">
    <span class="user-name">
      👤 <?php echo htmlspecialchars($username); ?>
    </span>

    <a class="logout" href="logout.php">Logout</a>
  </div>

</nav>

<hr>
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
  <a href="dashboard.php"><?= t('dashboard') ?></a>
<a href="join-class.php"><?= t('join_class') ?></a>
<a href="task.php"><?= t('tasks') ?></a>
<a href="recovery-plan.php"><?= t('ai') ?></a>
<a href="materials.php"><?= t('materials') ?></a>
  
  </div>

  <div style="display:flex; gap:10px;">
  <a href="?lang=en">🇬🇧 EN</a>
<a href="?lang=ru">🇷🇺 RU</a>
<a href="?lang=kz">🇰🇿 KZ</a>
</div>
  <div class="nav-right">
    <span class="user-name">
      👤 <?php echo htmlspecialchars($username); ?>
    </span>

 <a class="logout" href="logout.php"><?= t('logout') ?></a>
  </div>

</nav>

<hr>
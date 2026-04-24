<?php
session_start();
include("config/db.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

  $email = $_POST["email"];
  $password = $_POST["password"];

  $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();

  $result = $stmt->get_result();

  if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user["password"])) {
      $_SESSION["user_id"] = $user["id"];
      header("Location: dashboard.php");
      exit();
    } else {
      $error = "Wrong password";
    }
  } else {
    $error = "User not found";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>
<script src="https://cdn.tailwindcss.com"></script>

<style>
body {
  background: radial-gradient(circle at top, #1e293b, #020617);
}
</style>
</head>

<body class="text-white min-h-screen flex items-center justify-center">

<div class="w-full max-w-5xl grid md:grid-cols-2 gap-10 items-center px-6">

  <!-- LEFT SIDE (branding) -->
  <div class="hidden md:block">
    <h1 class="text-5xl font-bold mb-6">
      Welcome Back 
    </h1>

    <p class="text-gray-300 mb-6">
      Log in to continue managing your tasks, track progress, and stay ahead of deadlines.
    </p>

    <ul class="space-y-3 text-gray-400">
      <li>✔ Smart task tracking</li>
      <li>✔ AI-powered study help</li>
      <li>✔ Deadline reminders</li>
    </ul>
  </div>

  <!-- RIGHT SIDE (form) -->
  <div class="backdrop-blur-lg bg-white/10 border border-white/20 rounded-2xl p-8 shadow-xl">

    <h2 class="text-2xl font-semibold mb-6 text-center">
      Login to your account
    </h2>

    <?php if ($error): ?>
      <div class="bg-red-500/20 border border-red-400 text-red-300 p-3 rounded mb-4 text-sm">
        <?= htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">

      <div>
        <label class="text-sm text-gray-300">Email</label>
        <input type="email" name="email" required
          class="w-full mt-1 px-4 py-2 bg-white/10 border border-white/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>

      <div>
        <label class="text-sm text-gray-300">Password</label>
        <input type="password" name="password" required
          class="w-full mt-1 px-4 py-2 bg-white/10 border border-white/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>

      <button type="submit"
        class="w-full py-2 bg-blue-600 hover:bg-blue-700 rounded-lg font-semibold transition">
        Login
      </button>

    </form>

    <p class="text-center text-gray-400 mt-6 text-sm">
      Don’t have an account?
      <a href="register.php" class="text-blue-400 hover:underline">Register</a>
    </p>

  </div>

</div>

</body>
</html>
<?php
session_start();
include("config/db.php");

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

  $email = trim($_POST["email"]);
  $password_raw = $_POST["password"];
  $confirm = $_POST["confirm_password"];

  if (!$email || !$password_raw || !$confirm) {
    $error = "All fields are required.";
  } elseif ($password_raw !== $confirm) {
    $error = "Passwords do not match.";
  } else {

    // check if user already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
      $error = "Email already registered.";
    } else {

      $password = password_hash($password_raw, PASSWORD_DEFAULT);

      $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
      $stmt->bind_param("ss", $email, $password);

      if ($stmt->execute()) {
        $success = "Account created successfully!";
      } else {
        $error = "Something went wrong. Try again.";
      }
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register - BackOnTrack</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 text-white min-h-screen flex items-center justify-center">

<div class="w-full max-w-md p-8 rounded-2xl bg-white/10 backdrop-blur-lg border border-white/20 shadow-xl">

    <h2 class="text-3xl font-bold text-center mb-6">Create Account</h2>

    <?php if ($error): ?>
        <div class="bg-red-500/20 text-red-300 p-3 rounded mb-4 text-sm">
            <?= htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-green-500/20 text-green-300 p-3 rounded mb-4 text-sm text-center">
            <?= htmlspecialchars($success); ?><br>
            <a href="login.php" class="underline">Go to Login →</a>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">

        <input 
            type="email" 
            name="email" 
            placeholder="Email" 
            required
            class="w-full p-3 rounded-lg bg-white/10 border border-white/20 focus:outline-none focus:ring-2 focus:ring-green-400"
        >

        <input 
            type="password" 
            name="password" 
            placeholder="Password" 
            required
            class="w-full p-3 rounded-lg bg-white/10 border border-white/20 focus:outline-none focus:ring-2 focus:ring-green-400"
        >

        <input 
            type="password" 
            name="confirm_password" 
            placeholder="Confirm Password" 
            required
            class="w-full p-3 rounded-lg bg-white/10 border border-white/20 focus:outline-none focus:ring-2 focus:ring-green-400"
        >

        <button 
            type="submit"
            class="w-full bg-green-500 hover:bg-green-600 transition p-3 rounded-lg font-semibold"
        >
            Register
        </button>

    </form>

    <p class="text-center text-sm mt-6 text-gray-300">
        Already have an account?
        <a href="login.php" class="text-green-400 hover:underline">Login</a>
    </p>

</div>

</body>
</html>
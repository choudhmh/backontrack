<?php
session_start();
include("config/db.php");
include("includes/header.php");

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

<h2>Login</h2>

<?php if ($error) echo "<p style='color:red;'>$error</p>"; ?>

<form method="POST">
  <input type="email" name="email" placeholder="Email" required>
  <input type="password" name="password" placeholder="Password" required>
  <button type="submit">Login</button>
</form>

<p><a href="register.php">Create account</a></p>

<?php include("includes/footer.php"); ?>
<?php
include("config/db.php");
include("includes/header.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

  $email = $_POST["email"];
  $password = password_hash($_POST["password"], PASSWORD_DEFAULT);

  $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
  $stmt->bind_param("ss", $email, $password);

  if ($stmt->execute()) {
    echo "Account created. <a href='login.php'>Login</a>";
  } else {
    echo "Error: " . $conn->error;
  }
}
?>

<h2>Register</h2>

<form method="POST">
  <input type="email" name="email" placeholder="Email" required>
  <input type="password" name="password" placeholder="Password" required>
  <button type="submit">Register</button>
</form>

<?php include("includes/footer.php"); ?>
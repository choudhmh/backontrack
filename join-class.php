<?php
include("includes/auth.php");
include("config/db.php");
include("includes/header.php");
include("includes/navbar.php");

$user_id = $_SESSION["user_id"];
$message = "";

if (isset($_POST['code'])) {

    $code = $_POST['code'];

    // SAFE QUERY
    $stmt = $conn->prepare("SELECT * FROM classes WHERE join_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $class = $result->fetch_assoc();

    if ($class) {

        $class_id = $class['id'];

        // CHECK IF ALREADY JOINED
        $check = $conn->prepare("SELECT * FROM class_members WHERE user_id=? AND class_id=?");
        $check->bind_param("ii", $user_id, $class_id);
        $check->execute();
        $exists = $check->get_result();

        if ($exists->num_rows == 0) {

            // INSERT
            $stmt = $conn->prepare("INSERT INTO class_members (user_id, class_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $class_id);
            $stmt->execute();

            // SAVE ACTIVE CLASS
            $_SESSION['class_id'] = $class_id;

            $message = "✅ Joined successfully!";
        } else {
            $message = "⚠️ You already joined this class.";
        }

    } else {
        $message = "❌ Invalid class code.";
    }
}
?>

<!-- FORM -->
<form method="POST">
  <input type="text" name="code" placeholder="Enter Class Code" required>
  <button type="submit">Join Class</button>
</form>

<p><?php echo $message; ?></p>
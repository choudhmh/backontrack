<?php
include("includes/auth.php");
include("config/db.php");
include("includes/header.php");
include("includes/navbar.php");

$user_id = $_SESSION["user_id"];
$message = "";

if (isset($_POST['class_name'])) {

    // sanitize input
    $name = trim($_POST['class_name']);

    if (!empty($name)) {

        // better unique join code
        $join_code = strtoupper(substr(md5(uniqid()), 0, 6));

        // insert class
        $stmt = $conn->prepare("INSERT INTO classes (name, join_code) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $join_code);
        $stmt->execute();

        // get new class ID
        $class_id = $conn->insert_id;

        // auto-add creator to class
        $stmt = $conn->prepare("INSERT INTO class_members (user_id, class_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $class_id);
        $stmt->execute();

        // set current class
        $_SESSION['class_id'] = $class_id;

        $message = "✅ Class created! Join Code: <strong>$join_code</strong>";
    } else {
        $message = "❌ Class name cannot be empty.";
    }
}
?>

<h2>Create a Class</h2>

<form method="POST" class="class-form">
  <input type="text" name="class_name" placeholder="Enter Class Name" required>
  <button type="submit">Create Class</button>
</form>

<p><?php echo $message; ?></p>

<?php include("includes/footer.php"); ?>
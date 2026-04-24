<?php
include("includes/auth.php");
include("config/db.php");
include("includes/header.php");
include("includes/navbar.php");

$user_id = $_SESSION["user_id"];
$message = "";

if (isset($_POST['class_name'])) {

    $name = trim($_POST['class_name']);

    if (!empty($name)) {

        /* =========================
           GENERATE UNIQUE JOIN CODE
        ========================== */
        do {
            $join_code = strtoupper(substr(md5(uniqid()), 0, 6));

            $check = $conn->prepare("SELECT id FROM classes WHERE join_code=?");
            $check->bind_param("s", $join_code);
            $check->execute();
            $exists = $check->get_result()->num_rows;

        } while ($exists > 0);

        /* =========================
           INSERT CLASS
        ========================== */
        $stmt = $conn->prepare("INSERT INTO classes (name, join_code) VALUES (?, ?)");

        if (!$stmt) {
            $message = "❌ Error preparing class insert.";
        } else {

            $stmt->bind_param("ss", $name, $join_code);

            if ($stmt->execute()) {

                $class_id = $conn->insert_id;

                /* =========================
                   ADD USER TO CLASS
                ========================== */
                $stmt2 = $conn->prepare("INSERT INTO class_members (user_id, class_id) VALUES (?, ?)");

                if ($stmt2) {
                    $stmt2->bind_param("ii", $user_id, $class_id);
                    $stmt2->execute();
                }

                /* =========================
                   SET SESSION CLASS
                ========================== */
                $_SESSION['class_id'] = $class_id;

                $message = "✅ Class created! Join Code: <strong>" . htmlspecialchars($join_code) . "</strong>";

            } else {
                $message = "❌ Failed to create class.";
            }
        }

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

<?php if ($message): ?>
  <p><?= $message; ?></p>
<?php endif; ?>

<?php include("includes/footer.php"); ?>
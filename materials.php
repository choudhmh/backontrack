<?php
include("includes/auth.php");
include("config/db.php");
include("includes/lang.php");
include("includes/header.php");
include("includes/navbar.php");

$user_id = $_SESSION["user_id"];
$class_id = $_SESSION["class_id"] ?? 0;

if (!$class_id) {
    echo "<p>Please join a class first.</p>";
    include("includes/footer.php");
    exit();
}

$message = "";

/* =========================
   CURRENT SUBJECT
========================= */
$current_subject = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

/* =========================
   ADD SUBJECT (WITH DUPLICATE CHECK)
========================= */
if (isset($_POST['add_subject'])) {

    $name = trim($_POST['subject_name']);

    if (!empty($name)) {

        $check = $conn->prepare("
            SELECT id 
            FROM subjects
            WHERE class_id=? 
            AND LOWER(name)=LOWER(?)
        ");

        $check->bind_param("is", $class_id, $name);
        $check->execute();

        $existing = $check->get_result()->fetch_assoc();

        if ($existing) {
            $message = "❌ Subject already exists.";
        } else {

            $stmt = $conn->prepare("
                INSERT INTO subjects (class_id, name)
                VALUES (?, ?)
            ");

            $stmt->bind_param("is", $class_id, $name);

            if ($stmt->execute()) {
                $message = "✅ Subject added!";
            } else {
                $message = "❌ Error adding subject.";
            }
        }

    } else {
        $message = "❌ Subject name cannot be empty.";
    }
}

/* =========================
   UPLOAD MATERIAL (FIXED - THIS WAS MISSING)
========================= */
if (isset($_POST['upload']) && $current_subject) {

    $title = trim($_POST['title']);

    if (!empty($title) && !empty($_FILES['files']['name'][0])) {

        // 1. INSERT MATERIAL
        $stmt = $conn->prepare("
            INSERT INTO materials (user_id, class_id, subject_id, title)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param("iiis", $user_id, $class_id, $current_subject, $title);

        if ($stmt->execute()) {

            $material_id = $stmt->insert_id;

            // 2. HANDLE FILE UPLOADS
            $files = $_FILES['files'];

            $upload_dir = "assets/uploads/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $allowed = ['pdf','doc','docx','png','jpg','jpeg','txt'];

            for ($i = 0; $i < count($files['name']); $i++) {

                $original_name = $files['name'][$i];
                $tmp_name = $files['tmp_name'][$i];
                $size = $files['size'][$i];

                $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed)) continue;
                if ($size > 5 * 1024 * 1024) continue;

                $file_name = uniqid() . "_" . $original_name;
                $path = $upload_dir . $file_name;

                if (move_uploaded_file($tmp_name, $path)) {

                    $stmt2 = $conn->prepare("
                        INSERT INTO material_files (material_id, file_path)
                        VALUES (?, ?)
                    ");

                    $stmt2->bind_param("is", $material_id, $path);
                    $stmt2->execute();
                }
            }

            $message = "✅ Material uploaded successfully!";

        } else {
            $message = "❌ Failed to create material.";
        }

    } else {
        $message = "❌ Please provide title and files.";
    }
}

/* =========================
   GET SUBJECTS
========================= */
$subjects = $conn->query("SELECT * FROM subjects WHERE class_id=$class_id");
?>

<h2><?= t("study_materials") ?></h2>

<?php if ($message) { ?>
  <p style="color:green;"><?php echo $message; ?></p>
<?php } ?>

<!-- ADD SUBJECT -->
<form method="POST" style="margin-bottom:15px;">
  <input type="text" name="subject_name" placeholder="Add Subject (e.g. Physics)" required>
  <button type="submit" name="add_subject">Add Subject</button>
</form>

<!-- SELECT SUBJECT -->
<form method="GET">
  <select name="subject_id" onchange="this.form.submit()">
    <option value="">Select Subject</option>

    <?php while ($s = $subjects->fetch_assoc()) { ?>
        <option value="<?php echo $s['id']; ?>"
          <?php if ($current_subject == $s['id']) echo "selected"; ?>>
          <?php echo htmlspecialchars($s['name']); ?>
        </option>
    <?php } ?>

  </select>
</form>

<hr>

<?php if ($current_subject) { ?>

<!-- UPLOAD MATERIAL -->
<h3>Upload Material</h3>

<form method="POST" enctype="multipart/form-data">
  <input type="text" name="title" placeholder="Material title" required>
  <input type="file" name="files[]" multiple required>
  <button type="submit" name="upload">Upload</button>
</form>

<hr>

<!-- DISPLAY MATERIALS -->
<h3>Materials</h3>

<div class="materials-grid">

<?php
$materials = $conn->query("
    SELECT * FROM materials 
    WHERE class_id=$class_id AND subject_id=$current_subject
    ORDER BY created_at DESC
");

if ($materials && $materials->num_rows > 0) {

    while ($m = $materials->fetch_assoc()) {

        echo "<div class='material-card'>";
        echo "<h4>" . htmlspecialchars($m['title']) . "</h4>";

        $files = $conn->query("
            SELECT * FROM material_files 
            WHERE material_id=" . (int)$m['id']
        );

        while ($f = $files->fetch_assoc()) {

            $fileName = basename($f['file_path']);

            echo "<a href='" . htmlspecialchars($f['file_path']) . "' target='_blank'>📄 " . htmlspecialchars($fileName) . "</a><br>";
        }

        echo "<small>Uploaded: " . htmlspecialchars($m['created_at']) . "</small><br><br>";
        echo "<a href='material-detail.php?id=" . $m['id'] . "' class='btn-ai'>🤖 Study</a>";

        echo "</div>";
    }

} else {
    echo "<p>No materials yet.</p>";
}
?>

</div>

<?php } ?>

<?php include("includes/footer.php"); ?>
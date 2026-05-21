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
   FILE CONTENT EXTRACTOR
========================= */
function extractFileContent($filePath, $ext) {

    $ext = strtolower($ext);

    if (!file_exists($filePath)) return "";

    try {

        // PDF
        if ($ext === "pdf") {
            require_once __DIR__ . '/vendor/autoload.php';
            $parser = new \Smalot\PdfParser\Parser();
            return $parser->parseFile($filePath)->getText();
        }

        // TXT
        if ($ext === "txt") {
            return file_get_contents($filePath);
        }

        // DOCX
        if ($ext === "docx") {
            $zip = new ZipArchive;
            if ($zip->open($filePath) === true) {
                $xml = $zip->getFromName("word/document.xml");
                $zip->close();
                return strip_tags($xml);
            }
        }

        // Images (optional OCR)
        if (in_array($ext, ["jpg", "jpeg", "png"])) {
            return shell_exec("tesseract " . escapeshellarg($filePath) . " stdout");
        }

    } catch (Exception $e) {
        return "";
    }

    return "";
}

/* =========================
   CURRENT SUBJECT
========================= */
$current_subject = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

/* =========================
   ADD SUBJECT
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
   UPLOAD MATERIAL
========================= */
if (isset($_POST['upload']) && $current_subject) {

    $title = trim($_POST['title']);

    if (!empty($title) && !empty($_FILES['files']['name'][0])) {

        $stmt = $conn->prepare("
            INSERT INTO materials (user_id, class_id, subject_id, title)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param("iiis", $user_id, $class_id, $current_subject, $title);

        if ($stmt->execute()) {

            $material_id = $stmt->insert_id;

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

<form method="POST" style="margin-bottom:15px;">
  <input type="text" name="subject_name" placeholder="Add Subject (e.g. Physics)" required>
  <button type="submit" name="add_subject">Add Subject</button>
</form>

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

<h3>Upload Material</h3>

<form method="POST" enctype="multipart/form-data">
  <input type="text" name="title" placeholder="Material title" required>
  <input type="file" name="files[]" multiple required>
  <button type="submit" name="upload">Upload</button>
</form>

<hr>

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

        /* =========================
           IMPORTANT FIX: reset per material
        ========================= */
        $fullContent = "";
        $hasReadableText = false;

        $files = $conn->query("
            SELECT * FROM material_files 
            WHERE material_id=" . (int)$m['id']
        );

        while ($f = $files->fetch_assoc()) {

            $filePath = $f['file_path'];
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            $fileText = extractFileContent($filePath, $ext);

            if (strlen(trim($fileText)) > 20) {
                $hasReadableText = true;
                $fullContent .= "\n" . $fileText;
            }

            $fileName = basename($filePath);

            echo "<a href='" . htmlspecialchars($filePath) . "' target='_blank'>📄 " . htmlspecialchars($fileName) . "</a><br>";
        }

        echo "<small>Uploaded: " . htmlspecialchars($m['created_at']) . "</small><br><br>";

        /* =========================
           FINAL FIX: ONLY SHOW AI IF VALID
        ========================= */
        if ($hasReadableText) {
            echo "<a href='material-detail.php?id=" . $m['id'] . "' class='btn-ai'>🤖 Study</a>";
        } else {
            echo "<span style='color:#999;'>⚠️ No readable text found</span>";
        }

        echo "</div>";
    }

} else {
    echo "<p>No materials yet.</p>";
}
?>

</div>

<?php } ?>

<?php include("includes/footer.php"); ?>
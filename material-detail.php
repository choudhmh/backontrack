<?php
include("includes/auth.php");
include("config/db.php");
include("includes/lang.php");
include("includes/header.php");
include("includes/navbar.php");
include("config/env.php");


require 'vendor/autoload.php';
use Smalot\PdfParser\Parser;

$user_id = $_SESSION["user_id"];
$material_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$material_id) {
    echo "<p>Invalid material.</p>";
    exit();
}

/* =========================
   GET MATERIAL
========================= */
$stmt = $conn->prepare("SELECT * FROM materials WHERE id=?");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$material = $stmt->get_result()->fetch_assoc();

if (!$material) {
    echo "<p>Material not found.</p>";
    exit();
}

$class_id = $material['class_id'];

/* =========================
   GET FILES + EXTRACT TEXT
========================= */
$stmt = $conn->prepare("SELECT * FROM material_files WHERE material_id=?");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$result = $stmt->get_result();

$filesList = [];
$fileText = "";

$parser = new Parser();

while ($f = $result->fetch_assoc()) {

    $filesList[] = $f;
    $path = $f['file_path'];

    if (file_exists($path)) {

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        try {
            if ($ext === "pdf") {
                $pdf = $parser->parseFile($path);
                $fileText .= $pdf->getText() . "\n\n";
            } elseif ($ext === "txt") {
                $fileText .= file_get_contents($path) . "\n\n";
            }
        } catch (Exception $e) {
            $fileText .= "[Error reading file]\n";
        }
    }
}

/* limit text for AI */
$fileText = substr($fileText, 0, 3000);

/* =========================
   AI CACHE
========================= */
$ai_output = $_SESSION['ai_' . $material_id] ?? null;

/* =========================
   RUN AI
========================= */
if (!$ai_output) {

    $apiKey = $_ENV['OPENAI_API_KEY'] ?? '';

    if (!$apiKey) {
        $ai_output = "❌ API key missing.";
    } else {

        $prompt = "
You are a study assistant.

Analyze this content:

$fileText

Return:

EXPLANATION:


TASKS:
1. Task one
2. Task two
3. Task three
4. Task four
5. Task five
";

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.openai.com/v1/chat/completions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                "model" => "gpt-4o-mini",
                "messages" => [
                    ["role" => "user", "content" => $prompt]
                ]
            ]),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer " . $apiKey
            ]
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $ai_output = "❌ cURL Error: " . curl_error($ch);
        } else {
            $data = json_decode($response, true);
            $ai_output = $data["choices"][0]["message"]["content"] ?? "❌ AI error";
        }

        curl_close($ch);

        $_SESSION['ai_' . $material_id] = $ai_output;
    }
}

/* =========================
   PARSE TASKS (NO HEADERS)
========================= */
$ai_tasks = [];

$lines = preg_split('/\r\n|\r|\n/', $ai_output);

$inTasks = false;

foreach ($lines as $line) {

    $line = trim($line);

    if (stripos($line, "TASKS") !== false) {
        $inTasks = true;
        continue;
    }

    if ($inTasks) {

        if ($line === "") continue;

        // remove numbering, bullets, etc.
        $task = preg_replace('/^[-•*\d\.\)\s]+/', '', $line);

        // stop if explanation starts again
        if (stripos($task, "EXPLANATION") !== false) break;

        if (strlen($task) > 3) {
            $ai_tasks[] = $task;
        }
    }
}

/* =========================
   ADD SELECTED TASKS (FIXED)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tasks'])) {

    if (!empty($_POST['selected_tasks'])) {

        foreach ($_POST['selected_tasks'] as $task) {

            $task = trim($task);
            if (strlen($task) < 3) continue;

            // prevent duplicates
            $check = $conn->prepare("
                SELECT id FROM tasks 
                WHERE user_id=? AND class_id=? AND title=?
            ");
            $check->bind_param("iis", $user_id, $class_id, $task);
            $check->execute();

            if ($check->get_result()->num_rows > 0) continue;

            $deadline = date('Y-m-d', strtotime('+7 days'));

            $description = "";
            $notes = "";
            $status = "pending";

            $stmt = $conn->prepare("
                INSERT INTO tasks 
                (user_id, class_id, title, description, notes, status, deadline)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "iisssss",
                $user_id,
                $class_id,
                $task,
                $description,
                $notes,
                $status,
                $deadline
            );

            $stmt->execute();
        }

        echo "<p style='color:green;'>✅ Tasks added successfully!</p>";
    } else {
        echo "<p style='color:red;'>❌ No tasks selected.</p>";
    }
}
?>

<div class="page-shell material-detail-page">

  <div class="page-hero">
    <span class="page-kicker">AI Study</span>
    <h2>AI Study Page</h2>
    <p>Review uploaded study materials, read AI-generated explanations, and add suggested tasks to your plan.</p>
  </div>

  <section class="detail-card">
    <div class="section-head">
      <h3><?= htmlspecialchars($material['title']); ?></h3>
      <span class="chip">Material</span>
    </div>

    <h4>Files</h4>

    <div class="file-list">
      <?php foreach ($filesList as $f): ?>
        <a class="file-link" href="<?= htmlspecialchars($f['file_path']); ?>" target="_blank">
          <?= htmlspecialchars(basename($f['file_path'])); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="detail-card explanation-card">
    <div class="section-head">
      <h3>AI Explanation & Notes</h3>
      <span class="chip">Generated</span>
    </div>

    <div class="ai-explanation">
      <?= nl2br(htmlspecialchars($ai_output)); ?>
    </div>
  </section>

  <?php if (!empty($ai_tasks)) { ?>

    <section class="detail-card">
      <div class="section-head">
        <h3>AI Task Suggestions</h3>
        <span class="chip">Selectable</span>
      </div>

      <form method="POST" class="ai-task-form">
        <?php foreach ($ai_tasks as $task): ?>
          <label class="task-check">
            <input type="checkbox" name="selected_tasks[]" value="<?= htmlspecialchars($task); ?>" checked>
            <span><?= htmlspecialchars($task); ?></span>
          </label>
        <?php endforeach; ?>

        <button type="submit" name="add_tasks">
          Add Selected Tasks
        </button>
      </form>
    </section>

  <?php } else { ?>

    <section class="detail-card">
      <p class="empty-message">No tasks detected from AI output.</p>
    </section>

  <?php } ?>

</div>

<?php include("includes/footer.php"); ?>
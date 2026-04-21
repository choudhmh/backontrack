<?php
include("includes/auth.php");
include("config/db.php");
include("includes/header.php");
include("includes/navbar.php");
include("config/env.php");

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
   GET FILES
========================= */
$stmt = $conn->prepare("SELECT * FROM material_files WHERE material_id=?");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$filesResult = $stmt->get_result();

/* =========================
   AI CACHE
========================= */
$ai_output = $_SESSION['ai_' . $material_id] ?? null;

/* =========================
   RUN AI ONCE
========================= */
if (!$ai_output) {

    $apiKey = $_ENV['OPENAI_API_KEY'] ?? '';

    if (!$apiKey) {
        $ai_output = "❌ API key missing.";
    } else {

        $prompt = "
You are a study assistant.

Topic: {$material['title']}

Provide:

EXPLANATION:
- simple bullet points

TASKS:
1. Task one
2. Task two
3. Task three
4. Task four
5. Task five
";

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/chat/completions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "model" => "gpt-4o-mini",
            "messages" => [
                ["role" => "user", "content" => $prompt]
            ]
        ]));

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $apiKey
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
   PARSE AI TASKS
========================= */
$ai_tasks = [];

if (strpos($ai_output, "TASKS:") !== false) {

    $parts = explode("TASKS:", $ai_output);
    $lines = explode("\n", trim($parts[1]));

    foreach ($lines as $line) {

        $task = preg_replace('/^\d+\.\s*/', '', trim($line));

        if (strlen($task) > 3) {
            $ai_tasks[] = $task;
        }
    }
}

/* =========================
   ADD SELECTED TASKS
   + PREVENT DUPLICATES
========================= */
if (isset($_POST['add_tasks']) && !empty($_POST['selected_tasks'])) {

    foreach ($_POST['selected_tasks'] as $task) {

        $task = trim($task);

        // check duplicate
        $check = $conn->prepare("
            SELECT id FROM tasks 
            WHERE user_id=? AND class_id=? AND title=?
        ");
        $check->bind_param("iis", $user_id, $class_id, $task);
        $check->execute();
        $exists = $check->get_result()->num_rows;

        if ($exists > 0) continue;

        // FIXED DEADLINE
        $deadline = date('Y-m-d', strtotime('+7 days'));

        $stmt = $conn->prepare("
            INSERT INTO tasks (user_id, class_id, title, description, status, deadline)
            VALUES (?, ?, ?, '', 'pending', ?)
        ");

        $stmt->bind_param("iiss", $user_id, $class_id, $task, $deadline);
        $stmt->execute();
    }

    echo "<p style='color:green;'>✅ Selected tasks added successfully!</p>";
}
?>

<h2>🤖 AI Study Page</h2>

<h3><?= htmlspecialchars($material['title']); ?></h3>

<hr>

<h3>📄 Files</h3>
<?php while ($f = $filesResult->fetch_assoc()): ?>
    <a href="<?= htmlspecialchars($f['file_path']); ?>" target="_blank">
        📄 <?= htmlspecialchars(basename($f['file_path'])); ?>
    </a><br>
<?php endwhile; ?>

<hr>

<h3>AI Explanation & Notes</h3>

<div style="background:#fff;padding:20px;border-radius:10px;white-space:pre-wrap;">
    <?= htmlspecialchars($ai_output); ?>
</div>

<hr>

<!-- TASK PREVIEW -->
<?php if (!empty($ai_tasks)) { ?>

<h3>🧠 AI Task Preview</h3>

<form method="POST">

    <?php foreach ($ai_tasks as $task): ?>
        <label style="display:block;margin:6px 0;">
            <input type="checkbox" name="selected_tasks[]" value="<?= htmlspecialchars($task); ?>" checked>
            <?= htmlspecialchars($task); ?>
        </label>
    <?php endforeach; ?>

    <button type="submit" name="add_tasks" style="
        margin-top:15px;
        padding:10px 15px;
        background:#4CAF50;
        color:white;
        border:none;
        border-radius:8px;
        cursor:pointer;
    ">
        ➕ Add Selected Tasks
    </button>

</form>

<?php } ?>

<?php include("includes/footer.php"); ?>
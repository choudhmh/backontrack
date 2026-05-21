<?php
include("includes/auth.php");
include("config/db.php");
include("includes/lang.php");
include("includes/header.php");
include("includes/navbar.php");
include("config/env.php");

require 'vendor/autoload.php';
use Smalot\PdfParser\Parser;

/* =========================
   SAFE MATERIAL ID
========================= */
$material_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($material_id <= 0) {
    die("Invalid material ID.");
}

$user_id = $_SESSION["user_id"];

/* =========================
   GET MATERIAL
========================= */
$stmt = $conn->prepare("SELECT * FROM materials WHERE id=?");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$material = $stmt->get_result()->fetch_assoc();

if (!$material) {
    die("Material not found.");
}

/* =========================
   EXTRACT FUNCTION (FIXED PROPERLY)
========================= */
function extractText($path, $parser) {

    if (!file_exists($path)) return "";

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    try {

        if ($ext === "pdf") {
            return $parser->parseFile($path)->getText();
        }

        if ($ext === "txt") {
            return file_get_contents($path);
        }

        if ($ext === "docx") {
            $zip = new ZipArchive;
            if ($zip->open($path) === true) {
                $xml = $zip->getFromName("word/document.xml");
                $zip->close();
                return strip_tags($xml);
            }
        }

    } catch (Exception $e) {
        return "";
    }

    return "";
}

/* =========================
   GET FILES + BUILD TEXT
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

    $text = extractText($f["file_path"], $parser);

    // IMPORTANT: only add meaningful text
    if (strlen(trim($text)) > 20) {
        $fileText .= "\n" . $text;
    }
}

$fileText = trim($fileText);

/* =========================
   HARD FIX: NEVER ALLOW EMPTY INPUT
========================= */
if (strlen($fileText) < 50) {
    $fileText = "The document contains limited readable text. It may include diagrams, images, or scanned content.";
}

/* =========================
   SAFE CACHE KEY (FIXED)
========================= */
$cacheKey = "ai_" . md5($material_id . $fileText);
$ai_output = $_SESSION[$cacheKey] ?? null;

/* =========================
   RUN AI
========================= */
if (!$ai_output) {

    $apiKey = $_ENV["OPENAI_API_KEY"] ?? '';

    if (!$apiKey) {
        $ai_output = "❌ Missing API key.";
    } else {

        $systemPrompt = "
You are a strict academic tutor.

Rules:
- Use ONLY the provided material
- Do NOT say 'limited text'
- If content is small, focus on explaining it deeply
- Do NOT hallucinate outside information
";

        $userPrompt = "
STUDY MATERIAL:
----------------
$fileText
----------------

TASK:
- Explain everything important in detail
- Break into clear sections
- Extract key ideas and meaning
- Give 5 learning tasks
";

        $payload = [
            "model" => "gpt-4o-mini",
            "temperature" => 0.2,
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => $userPrompt]
            ]
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.openai.com/v1/chat/completions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
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
            $ai_output = $data["choices"][0]["message"]["content"] ?? "AI failed.";
        }

        curl_close($ch);

        $_SESSION[$cacheKey] = $ai_output;
    }
}

/* =========================
   TASK PARSING (SAFE)
========================= */
$ai_tasks = [];

foreach (preg_split('/\r\n|\r|\n/', $ai_output) as $line) {

    $line = trim($line);

    if (preg_match('/^\d+\./', $line)) {
        $task = preg_replace('/^\d+\.\s*/', '', $line);
        if (strlen($task) > 5) {
            $ai_tasks[] = $task;
        }
    }
}

$ai_tasks = array_slice($ai_tasks, 0, 5);
?>

<div class="page-shell material-detail-page">

<div class="page-hero">
    <h2><?= htmlspecialchars($material["title"]) ?></h2>
    <p>AI-powered deep analysis of your uploaded material</p>
</div>

<section class="detail-card">

<h3>Files</h3>

<?php foreach ($filesList as $f): ?>
    <a href="<?= htmlspecialchars($f['file_path']) ?>" target="_blank">
        📄 <?= htmlspecialchars(basename($f['file_path'])) ?>
    </a><br>
<?php endforeach; ?>

</section>

<section class="detail-card">

<h3>AI Explanation</h3>

<div>
    <?= nl2br(htmlspecialchars($ai_output)) ?>
</div>

</section>

<?php if (!empty($ai_tasks)): ?>

<section class="detail-card">

<h3>AI Tasks</h3>

<form method="POST">

<?php foreach ($ai_tasks as $task): ?>
<label>
<input type="checkbox" name="selected_tasks[]" value="<?= htmlspecialchars($task) ?>" checked>
<?= htmlspecialchars($task) ?>
</label><br>
<?php endforeach; ?>

<button type="submit" name="add_tasks">Add Tasks</button>

</form>

</section>

<?php endif; ?>

</div>

<?php include("includes/footer.php"); ?>
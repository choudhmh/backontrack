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
    $path = $f["file_path"];

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
            // ignore
        }
    }
}

$fileText = trim($fileText);
$fileText = substr($fileText, 0, 12000);

/* =========================
   FALLBACK (IMPORTANT FIX)
========================= */
if (strlen($fileText) < 200) {
    $fileText = "DOCUMENT CONTAINS LIMITED TEXT BUT MAY INCLUDE KEYWORDS OR TOPIC INDICATORS:
" . $fileText;
}

/* =========================
   SMART CACHE (BETTER KEY)
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

        /* =========================
           SYSTEM PROMPT (IMPROVED)
        ========================= */
        $systemPrompt = "
You are an ELITE ACADEMIC ANALYST.

Your job is to deeply understand study materials and produce structured learning output.

RULES:
- ONLY use concepts found in the document
- You MAY expand explanations using those same concepts
- NEVER hallucinate or invent topics
- NEVER say 'limited text' or 'cannot analyze'

OUTPUT REQUIREMENTS:

EXPLANATION:
- MUST contain 12–20 bullet points
- Each bullet must explain ONE real concept from the document
- Each bullet must be 2–4 sentences long
- Must explain meaning + function + relationship
- Must group related ideas

TASKS:
- Exactly 5 tasks
- Each task must reference at least ONE real document keyword
- Must be actionable (not generic)
- Must be different from each other
";

        /* =========================
           USER PROMPT (FIXED)
        ========================= */
        $userPrompt = "
STUDY MATERIAL:

========================
$fileText
========================

INSTRUCTIONS:

1. Identify all important concepts
2. Expand each concept into detailed explanation
3. Show relationships between concepts
4. Do NOT be brief
5. Do NOT add outside knowledge

OUTPUT FORMAT:

EXPLANATION:
• (12–20 detailed bullets)

TASKS:
1.
2.
3.
4.
5.
";

        $payload = [
            "model" => "gpt-4o-mini",
            "temperature" => 0.25,
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
   PARSE TASKS
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

        $task = preg_replace('/^[-•*\d\.\)\s]+/', '', $line);

        if (strlen($task) > 3) {
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
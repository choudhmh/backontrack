<?php
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

include("includes/auth.php");
include("config/db.php");
include("includes/header.php");
include("includes/navbar.php");

$user_id = $_SESSION["user_id"] ?? null;

if (!$user_id) {
    die("User not logged in");
}

$ai_response = "";
$plan = [];


$tasks = [];

$stmt = $conn->prepare("SELECT title, deadline FROM tasks WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}


if (isset($_POST["generate"])) {

    $task_text = "";

    foreach ($tasks as $t) {
        $task_text .= "- {$t['title']} (deadline: {$t['deadline']})\n";
    }

    $prompt = "
You are an academic recovery assistant.

A student has these tasks:
$task_text

Create a recovery plan for the next 3 days.

STRICT RULES:
- Return ONLY valid JSON
- No explanations
- No markdown
- No extra text

Format exactly like this:

{
  \"plan\": [
    {
      \"day\": \"Day 1\",
      \"task\": \"Task name here\",
      \"time\": \"2 hours\"
    },
    {
      \"day\": \"Day 2\",
      \"task\": \"Task name here\",
      \"time\": \"1.5 hours\"
    }
  ]
}
";

    $apiKey = $_ENV['OPENAI_API_KEY'] ?? null;

    if (!$apiKey) {
        $ai_response = "Error: API key not found in .env";
    } else {

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.openai.com/v1/chat/completions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer " . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode([
                "model" => "gpt-4o-mini",
                "messages" => [
                    ["role" => "user", "content" => $prompt]
                ]
            ])
        ]);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            $ai_response = "cURL Error: " . curl_error($ch);
        } else {

            $response = json_decode($result, true);
            $raw = $response["choices"][0]["message"]["content"] ?? "";

            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($decoded["plan"])) {
                $plan = $decoded["plan"];
            } else {
                $ai_response = "Invalid JSON returned from AI:\n" . $raw;
            }
        }

        curl_close($ch);

   
        if (!empty($plan)) {
            $plan_json = json_encode($plan);

            $stmt = $conn->prepare("INSERT INTO recovery_plans (user_id, plan) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $plan_json);
            $stmt->execute();
        }
    }
}
?>

<h2>AI Recovery Plan</h2>

<form method="POST">
  <button type="submit" name="generate">Generate Recovery Plan</button>
</form>

<hr>

<h3>Latest AI Plan</h3>

<div class="plan-cards">

<?php if (!empty($plan)) : ?>
    <?php foreach ($plan as $item) : ?>
        <div class="card">
            <div class="card-day"><?= htmlspecialchars($item["day"] ?? "") ?></div>
            <div class="card-task"><?= htmlspecialchars($item["task"] ?? "") ?></div>
            <div class="card-time">⏱ <?= htmlspecialchars($item["time"] ?? "") ?></div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

</div>

<?php if (!empty($ai_response)) : ?>
<pre><?= htmlspecialchars($ai_response) ?></pre>
<?php endif; ?>

<?php include("includes/footer.php"); ?>
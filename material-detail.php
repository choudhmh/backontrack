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
    die("<p>Invalid material.</p>");
}

/* =========================
   GET MATERIAL
========================= */

$stmt = $conn->prepare("
SELECT *
FROM materials
WHERE id=?
");

$stmt->bind_param("i",$material_id);
$stmt->execute();

$material = $stmt
->get_result()
->fetch_assoc();

if(!$material){
    die("<p>Material not found.</p>");
}

$class_id = $material["class_id"];


/* =========================
   GET FILES + EXTRACT TEXT
========================= */

$stmt=$conn->prepare("
SELECT *
FROM material_files
WHERE material_id=?
");

$stmt->bind_param(
"i",
$material_id
);

$stmt->execute();

$result=$stmt->get_result();

$filesList=[];

$fileText="";

$parser=new Parser();

while($f=$result->fetch_assoc()){

$filesList[]=$f;

$path=$f["file_path"];

if(file_exists($path)){

try{

$ext=strtolower(
pathinfo(
$path,
PATHINFO_EXTENSION
)
);

if($ext=="pdf"){

$pdf=$parser->parseFile($path);

$text=$pdf->getText();

$fileText .= $text . "\n\n";

}

elseif($ext=="txt"){

$fileText .=
file_get_contents($path)
."\n\n";

}

}catch(Exception $e){

$fileText .=
"[Unable to extract document]\n";

}

}

}


/* =========================
   CLEAN DOCUMENT
========================= */

$fileText = preg_replace(
'/\s+/',
' ',
$fileText
);

$fileText = preg_replace(
'/[^\P{C}\n]+/u',
'',
$fileText
);

$fileText = trim($fileText);

$fileText = substr(
$fileText,
0,
12000
);


/* fallback */

if(strlen($fileText)<100){

$fileText="
Very little readable content was extracted.

The document may be scanned or image-based.

Please provide best effort analysis.
";

}


/* =========================
   SMART CACHE
========================= */

$cacheKey=
"ai_".
md5(
$material_id .
$fileText
);

$ai_output=
$_SESSION[$cacheKey]
?? null;


/* =========================
   RUN AI
========================= */

if(!$ai_output){

$apiKey=
$_ENV["OPENAI_API_KEY"]
?? '';

if(!$apiKey){

$ai_output=
"❌ Missing API key";

}else{


$systemPrompt="

You are an expert academic tutor.

Analyze ONLY the uploaded study material.

RULES:

- Determine main topic
- Determine subtopics
- Extract definitions
- Extract formulas
- Extract key concepts
- Explain difficult concepts simply
- Mention relationships

TASK RULES:

- Create exactly 5 tasks
- Must reference document concepts
- Must be measurable
- Must be actionable
- Must NOT be generic

Forbidden:

review notes
study chapter
practice topic
read material

";


$userPrompt="

Uploaded document:

-----------------

$fileText

-----------------

Return EXACTLY:

EXPLANATION:

• Explain concepts simply
• Mention key terms
• Mention formulas if present
• Mention concept relationships

TASKS:

1.
2.
3.
4.
5.

";



$payload=[

"model"=>"gpt-4o-mini",

"temperature"=>0.2,

"messages"=>[

[
"role"=>"system",
"content"=>$systemPrompt
],

[
"role"=>"user",
"content"=>$userPrompt
]

]

];


$ch=curl_init();

curl_setopt_array($ch,[

CURLOPT_URL=>
"https://api.openai.com/v1/chat/completions",

CURLOPT_RETURNTRANSFER=>true,

CURLOPT_POST=>true,

CURLOPT_POSTFIELDS=>
json_encode($payload),

CURLOPT_HTTPHEADER=>[

"Content-Type: application/json",

"Authorization: Bearer ".$apiKey

]

]);


$response=
curl_exec($ch);


if(curl_errno($ch)){

$ai_output=
"❌ cURL Error: "
.
curl_error($ch);

}else{

$data=
json_decode(
$response,
true
);

$ai_output=
$data["choices"][0]["message"]["content"]
?? "AI generation failed.";

}

curl_close($ch);

$_SESSION[$cacheKey]=$ai_output;

}

}


/* =========================
   PARSE TASKS
========================= */

$ai_tasks=[];

$lines=
preg_split(
'/\r\n|\r|\n/',
$ai_output
);

$inTasks=false;

foreach($lines as $line){

$line=trim($line);

if(
stripos(
$line,
"TASKS"
)!==false
){

$inTasks=true;

continue;

}

if($inTasks){

if(empty($line))
continue;

$task=
preg_replace(
'/^[-•*\d\.\)\s]+/',
'',
$line
);

if(strlen($task)>5){

$ai_tasks[]=$task;

}

}

}

$ai_tasks=array_slice(
$ai_tasks,
0,
5
);


/* =========================
   ADD TASKS
========================= */

if(
$_SERVER["REQUEST_METHOD"]==="POST"
&& isset($_POST["add_tasks"])
){

if(!empty($_POST["selected_tasks"])){

foreach(
$_POST["selected_tasks"]
as $task
){

$task=trim($task);

if(strlen($task)<3)
continue;


/* duplicate check */

$check=
$conn->prepare("
SELECT id
FROM tasks
WHERE user_id=?
AND class_id=?
AND title=?
");

$check->bind_param(
"iis",
$user_id,
$class_id,
$task
);

$check->execute();

if(
$check
->get_result()
->num_rows>0
){
continue;
}

$deadline=date(
"Y-m-d",
strtotime("+7 days")
);

$status="pending";

$description="";

$notes="";


$stmt=
$conn->prepare("
INSERT INTO tasks
(
user_id,
class_id,
title,
description,
notes,
status,
deadline
)
VALUES
(
?,
?,
?,
?,
?,
?,
?
)
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

echo "<p style='color:lightgreen'>
✅ Tasks added successfully
</p>";

}

}

?>

<div class="page-shell material-detail-page">

<div class="page-hero">

<span class="page-kicker">
AI Study
</span>

<h2>
AI Study Page
</h2>

<p>
Review uploaded materials and generate AI learning tasks.
</p>

</div>


<section class="detail-card">

<div class="section-head">

<h3>
<?= htmlspecialchars($material["title"]) ?>
</h3>

<span class="chip">
Material
</span>

</div>

<h4>Files</h4>

<div class="file-list">

<?php foreach($filesList as $f): ?>

<a
class="file-link"
href="<?= htmlspecialchars($f['file_path']) ?>"
target="_blank"
>

<?= htmlspecialchars(
basename(
$f['file_path']
)
) ?>

</a>

<?php endforeach; ?>

</div>

</section>



<section class="detail-card">

<div class="section-head">

<h3>
AI Explanation
</h3>

<span class="chip">
Generated
</span>

</div>

<div class="ai-explanation">

<?= nl2br(
htmlspecialchars(
$ai_output
)
) ?>

</div>

</section>



<?php if(!empty($ai_tasks)): ?>

<section class="detail-card">

<div class="section-head">

<h3>
AI Task Suggestions
</h3>

<span class="chip">
Selectable
</span>

</div>

<form
method="POST"
class="ai-task-form"
>

<?php foreach($ai_tasks as $task): ?>

<label class="task-check">

<input
type="checkbox"
name="selected_tasks[]"
value="<?= htmlspecialchars($task) ?>"
checked
>

<span>
<?= htmlspecialchars($task) ?>
</span>

</label>

<?php endforeach; ?>

<button
type="submit"
name="add_tasks"
>
Add Selected Tasks
</button>

</form>

</section>

<?php endif; ?>

</div>

<?php include("includes/footer.php"); ?>
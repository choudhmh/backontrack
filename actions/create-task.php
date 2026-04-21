<?php
include("../config/db.php");
session_start();

if (!isset($_SESSION['class_id'])) {
    echo "Please join a class first.";
    exit();
}

$user_id = $_SESSION["user_id"];
$class_id = $_SESSION['class_id'];

$title = $_POST["title"] ?? '';
$description = $_POST["description"] ?? '';
$deadline = $_POST["deadline"] ?? date('Y-m-d', strtotime('+7 days'));

// IMPORTANT: always define notes explicitly
$notes = $_POST["notes"] ?? "";   // <-- FIX
$status = "pending";

if (empty($title)) {
    echo "Title is required.";
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO tasks 
    (user_id, class_id, title, description, notes, deadline, status)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param(
    "iisssss",
    $user_id,
    $class_id,
    $title,
    $description,
    $notes,
    $deadline,
    $status
);

$stmt->execute();

header("Location: ../task.php");
exit();
?>
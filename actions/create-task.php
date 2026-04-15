<?php
include("../config/db.php");
session_start();

if (!isset($_SESSION['class_id'])) {
    echo "Please join a class first.";
    exit();
  }

$user_id = $_SESSION["user_id"];
$title = $_POST["title"];
$description = $_POST["description"];
$deadline = $_POST["deadline"];

$class_id = $_SESSION['class_id'] ?? 0;

$stmt = $conn->prepare("INSERT INTO tasks (user_id, class_id, title, description, deadline, status) VALUES (?, ?, ?, ?, ?, 'pending')");
$stmt->bind_param("iisss", $user_id, $class_id, $title, $description, $deadline);

$stmt->execute();

header("Location: ../task.php");
exit();
?>
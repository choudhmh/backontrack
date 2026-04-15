<?php
include("../config/db.php");
session_start();

$user_id = $_SESSION["user_id"];
$title = $_POST["title"];
$description = $_POST["description"];
$deadline = $_POST["deadline"];

$stmt = $conn->prepare("INSERT INTO tasks (user_id, title, description, deadline, status) VALUES (?, ?, ?, ?, 'pending')");
$stmt->bind_param("isss", $user_id, $title, $description, $deadline);

$stmt->execute();

header("Location: ../task.php");
exit();
?>
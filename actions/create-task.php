<?php
include("../config/db.php");
session_start();

$user_id = $_SESSION["user_id"];
$title = $_POST["title"];
$deadline = $_POST["deadline"];

$stmt = $conn->prepare("INSERT INTO tasks (user_id, title, deadline) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $user_id, $title, $deadline);

$stmt->execute();

header("Location: ../task.php");
exit();
?>
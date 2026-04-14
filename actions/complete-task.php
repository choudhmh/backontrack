<?php
include("../config/db.php");
session_start();

$user_id = $_SESSION["user_id"];
$id = $_GET["id"];

// only update tasks that belong to the user
$stmt = $conn->prepare("UPDATE tasks SET status='done' WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $id, $user_id);

$stmt->execute();

header("Location: ../task.php");
exit();
?>
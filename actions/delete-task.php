<?php
include("../config/db.php");
session_start();

$user_id = $_SESSION["user_id"];
$id = $_GET["id"];

$stmt = $conn->prepare("DELETE FROM tasks WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $id, $user_id);

$stmt->execute();

header("Location: ../task.php");
exit();
?>
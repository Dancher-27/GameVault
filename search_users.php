<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo '[]'; exit(); }

require_once 'connection.php';

$q    = '%' . trim($_GET['q'] ?? '') . '%';
$self = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id, username FROM account WHERE username LIKE ? AND id != ? LIMIT 8");
$stmt->bind_param("si", $q, $self);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($rows);

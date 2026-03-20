<?php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit(); }
require_once 'connection.php';

header('Content-Type: application/json');

$title  = trim($_GET['title'] ?? '');
$uid    = (int)$_SESSION['user_id'];
$excludeId = (int)($_GET['exclude'] ?? 0);

if ($title === '') { echo json_encode(['found' => false]); exit(); }

$stmt = $conn->prepare(
    "SELECT id, title, status, cover_image FROM games
     WHERE user_id = ? AND LOWER(title) = LOWER(?) AND id != ?
     LIMIT 1"
);
$stmt->bind_param("isi", $uid, $title, $excludeId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row) {
    echo json_encode(['found' => true, 'id' => $row['id'], 'title' => $row['title'], 'status' => $row['status']]);
} else {
    echo json_encode(['found' => false]);
}
?>

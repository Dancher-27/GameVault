<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'not_logged_in']); exit();
}

require_once 'connection.php';
require_once 'classes/Game.php';

$selfId   = (int)$_SESSION['user_id'];
$targetId = (int)($_POST['target_id'] ?? 0);
$action   = $_POST['action'] ?? ''; // 'follow' or 'unfollow'

if ($targetId <= 0 || $targetId === $selfId || !in_array($action, ['follow','unfollow'])) {
    echo json_encode(['error' => 'invalid']); exit();
}

$gameObj = new Game($conn, $selfId);

if ($action === 'follow') {
    $gameObj->follow($targetId);
} else {
    $gameObj->unfollow($targetId);
}

$followers = $gameObj->getFollowerCount($targetId);
echo json_encode(['ok' => true, 'action' => $action, 'followers' => $followers]);

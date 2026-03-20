<?php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
require_once 'connection.php';
require_once 'classes/Game.php';

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? 'rank';
$ids  = array_map('intval', $data['ids'] ?? []);

if (empty($ids)) { echo json_encode(['ok' => false]); exit; }

$gameObj = new Game($conn, (int)$_SESSION["user_id"]);
foreach ($ids as $order => $id) {
    if ($type === 'wishlist') {
        $gameObj->updateWishlistRank($id, $order + 1);
    } else {
        $gameObj->updateRankOrder($id, $order + 1);
    }
}
echo json_encode(['ok' => true]);

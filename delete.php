<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'connection.php';
require_once 'classes/Game.php';

$gameObj   = new Game($conn, (int)$_SESSION["user_id"]);
$alertMsg  = '';
$alertType = '';

// Verwijder actie via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id   = (int)$_POST['id'];
    $game = $gameObj->getGameById($id);

    if ($game) {
        // Verwijder de cover afbeelding als die bestaat
        if (!empty($game['cover_image'])) {
            $imgPath = 'uploads/' . $game['cover_image'];
            if (file_exists($imgPath)) @unlink($imgPath);
        }
        if ($gameObj->deleteGame($id)) {
            $gameObj->logActivity('game_removed', null, $game['title'], '');
            $alertMsg  = '"' . htmlspecialchars($game['title']) . '" is verwijderd.';
            $alertType = 'success';
        } else {
            $alertMsg  = 'Fout bij verwijderen.';
            $alertType = 'error';
        }
    } else {
        $alertMsg  = 'Game niet gevonden.';
        $alertType = 'error';
    }
}

$allGames = $gameObj->getAllGames();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Verwijderen</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-wrapper">
    <div class="form-container">
        <h1>🗑️ Game verwijderen</h1>
        <p class="form-subtitle">Verwijder een game permanent uit je vault.</p>

        <?php if ($alertMsg): ?>
        <div class="alert alert-<?= $alertType ?>">
            <?= $alertType === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($alertMsg) ?>
        </div>
        <?php endif; ?>

        <?php if (empty($allGames)): ?>
        <div class="empty-state">
            <span class="empty-state-icon">📭</span>
            <p>Geen games om te verwijderen.</p>
            <a href="add_game.php" class="btn btn-primary">➕ Game toevoegen</a>
        </div>
        <?php else: ?>
        <div class="delete-list">
            <?php foreach ($allGames as $g): ?>
            <div class="delete-item">
                <?php $img = 'uploads/' . $g['cover_image'];
                if (!empty($g['cover_image']) && file_exists(__DIR__ . '/' . $img)): ?>
                    <img src="<?= htmlspecialchars($img) ?>" alt="cover" class="delete-item-thumb" loading="lazy">
                <?php else: ?>
                    <div class="delete-item-thumb-placeholder">🎮</div>
                <?php endif; ?>

                <div class="delete-item-info">
                    <span class="delete-item-title"><?= htmlspecialchars($g['title']) ?></span>
                    <span class="delete-item-meta">
                        <?= htmlspecialchars($g['platform'] ?? '—') ?>
                        <?= $g['release_year'] ? '· ' . $g['release_year'] : '' ?>
                        · ⭐ <?= number_format((float)$g['rating'], 1) ?>/10
                    </span>
                </div>


                <form method="POST" action="delete.php"
                      onsubmit="return confirm('Weet je zeker dat je \'<?= addslashes($g['title']) ?>\' wilt verwijderen? Dit kan niet ongedaan worden gemaakt.')">
                    <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                    <button type="submit" class="btn btn-danger">🗑️ Verwijder</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

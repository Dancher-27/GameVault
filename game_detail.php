<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'connection.php';
require_once 'classes/Game.php';
require_once 'classes/Character.php';
require_once 'classes/PlaySession.php';

$gameObj     = new Game($conn, (int)$_SESSION["user_id"]);
$charObj     = new Character($conn);
$sessionObj  = new PlaySession($conn);

$id = (int)($_GET['id'] ?? 0);
$game = $gameObj->getGameById($id);
if (!$game) {
    header('Location: index.php');
    exit();
}

$alertMsg  = '';
$alertType = '';

// Karakter toevoegen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_character') {
        $name        = trim($_POST['char_name']  ?? '');
        $description = trim($_POST['char_desc']  ?? '');
        $is_top      = isset($_POST['is_top_pick']) ? 1 : 0;
        $char_image  = null;

        if (empty($name)) {
            $alertMsg  = 'Karakternaam is verplicht.';
            $alertType = 'error';
        } else {
            // Afbeelding karakter
            if (isset($_FILES['char_image']) && $_FILES['char_image']['error'] === UPLOAD_ERR_OK) {
                $targetDir = "uploads/";
                if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                $ext = strtolower(pathinfo($_FILES['char_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                    $safe      = preg_replace('/[^a-z0-9_-]/i', '_', strtolower($name));
                    $filename  = 'char_' . $safe . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['char_image']['tmp_name'], $targetDir . $filename)) {
                        $char_image = $filename;
                    }
                }
            }

            if ($charObj->addCharacter($id, $name, $description, $char_image, $is_top)) {
                $alertMsg  = '"' . htmlspecialchars($name) . '" toegevoegd als favoriet karakter!';
                $alertType = 'success';
            } else {
                $alertMsg  = 'Fout bij opslaan.';
                $alertType = 'error';
            }
        }
    }

    if ($action === 'delete_character' && isset($_POST['char_id'])) {
        $charId = (int)$_POST['char_id'];
        if ($charObj->deleteCharacter($charId)) {
            $alertMsg  = 'Karakter verwijderd.';
            $alertType = 'success';
        }
    }

    if ($action === 'toggle_top' && isset($_POST['char_id'])) {
        $charObj->toggleTopPick((int)$_POST['char_id']);
        header('Location: game_detail.php?id=' . $id);
        exit();
    }

    if ($action === 'add_session') {
        $date = $_POST['session_date'] ?? '';
        $note = trim($_POST['session_note'] ?? '');
        if ($date) {
            $sessionObj->addSession($id, $date, $note);
            $alertMsg  = 'Sessie toegevoegd!';
            $alertType = 'success';
        }
    }

    if ($action === 'delete_session' && isset($_POST['session_id'])) {
        $sessionObj->deleteSession((int)$_POST['session_id']);
        header('Location: game_detail.php?id=' . $id);
        exit();
    }
}

$characters  = $charObj->getByGame($id);
$sessions    = $sessionObj->getSessions($id);
$dlcs        = $gameObj->getDLCsForGame($id);

// Helper: sterren
function starsHtml(float $rating): string {
    $filled = (int) round($rating / 2);
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $filled
            ? '<span class="star filled">★</span>'
            : '<span class="star empty">★</span>';
    }
    return $html;
}

$statusLabels = [
    'played'  => '✅ Gespeeld',
    'playing' => '🎮 Bezig',
    'backlog' => '📋 Backlog',
];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — <?= htmlspecialchars($game['title']) ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-wrapper">

    <!-- ===== GAME DETAIL ===== -->
    <div class="detail-hero">
        <div class="detail-cover">
            <?php $img = 'uploads/' . $game['cover_image'];
            if (!empty($game['cover_image']) && file_exists(__DIR__ . '/' . $img)): ?>
                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($game['title']) ?>">
            <?php else: ?>
                <div class="detail-cover-placeholder">🎮</div>
            <?php endif; ?>
        </div>

        <div class="detail-info">
            <h1 class="detail-title"><?= htmlspecialchars($game['title']) ?></h1>

            <div class="detail-meta-row">
                <?php if ($game['platform']): ?>
                <span class="meta-tag">🖥️ <?= htmlspecialchars($game['platform']) ?></span>
                <?php endif; ?>
                <?php if ($game['genre']): ?>
                <span class="meta-tag">🎯 <?= htmlspecialchars($game['genre']) ?></span>
                <?php endif; ?>
                <?php if ($game['release_year']): ?>
                <span class="meta-tag">📅 <?= (int)$game['release_year'] ?></span>
                <?php endif; ?>
                <?php if ($game['playtime']): ?>
                <span class="meta-tag">⏱️ <?= (int)$game['playtime'] ?> uur</span>
                <?php endif; ?>
                <span class="meta-tag"><?= $statusLabels[$game['status']] ?? $game['status'] ?></span>
            </div>

            <div class="detail-rating">
                <div class="stars-display stars-lg"><?= starsHtml((float)$game['rating']) ?></div>
                <span class="detail-rating-value"><?= number_format((float)$game['rating'], 1) ?> / 10</span>
            </div>

            <?php if (!empty($game['review'])): ?>
            <div class="detail-review">
                <h3>📝 Review</h3>
                <p><?= nl2br(htmlspecialchars($game['review'])) ?></p>
            </div>
            <?php endif; ?>

            <div class="detail-actions">
                <a href="edit_game.php?id=<?= $id ?>" class="btn btn-secondary">✏️ Bewerken</a>
                <a href="index.php" class="btn btn-ghost">← Terug naar ranking</a>
            </div>
        </div>
    </div>

    <!-- ===== FAVORIETE KARAKTERS ===== -->
    <div class="section-block">
        <div class="section-header">
            <h2>⭐ Favoriete Karakters</h2>
            <span class="section-count"><?= count($characters) ?></span>
        </div>

        <?php if ($alertMsg): ?>
        <div class="alert alert-<?= $alertType ?>" style="margin-bottom:20px;">
            <?= $alertType === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($alertMsg) ?>
        </div>
        <?php endif; ?>

        <!-- Karakters weergeven -->
        <?php if (!empty($characters)): ?>
        <div class="character-grid">
            <?php foreach ($characters as $char): ?>
            <div class="character-card <?= $char['is_top_pick'] ? 'char-top-pick' : '' ?>">
                <?php if ($char['is_top_pick']): ?>
                <span class="top-pick-badge">⭐ Top Pick</span>
                <?php endif; ?>

                <?php $cImg = 'uploads/' . $char['image'];
                if (!empty($char['image']) && file_exists(__DIR__ . '/' . $cImg)): ?>
                    <img src="<?= htmlspecialchars($cImg) ?>"
                         alt="<?= htmlspecialchars($char['name']) ?>"
                         class="character-img"
                         loading="lazy">
                <?php else: ?>
                    <div class="character-img-placeholder">👤</div>
                <?php endif; ?>

                <div class="character-overlay">
                    <div class="character-overlay-name"><?= htmlspecialchars($char['name']) ?></div>
                    <?php if ($char['description']): ?>
                    <p class="character-overlay-desc"><?= htmlspecialchars($char['description']) ?></p>
                    <?php endif; ?>
                    <div class="char-actions">
                        <form method="POST" action="game_detail.php?id=<?= $id ?>" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_top">
                            <input type="hidden" name="char_id" value="<?= $char['id'] ?>">
                            <button type="submit" class="btn-icon" title="<?= $char['is_top_pick'] ? 'Verwijder top pick' : 'Maak top pick' ?>">
                                <?= $char['is_top_pick'] ? '⭐' : '☆' ?>
                            </button>
                        </form>
                        <form method="POST" action="game_detail.php?id=<?= $id ?>" style="display:inline;"
                              onsubmit="return confirm('Verwijder <?= addslashes($char['name']) ?>?')">
                            <input type="hidden" name="action" value="delete_character">
                            <input type="hidden" name="char_id" value="<?= $char['id'] ?>">
                            <button type="submit" class="btn-icon btn-icon-danger" title="Verwijder karakter">🗑️</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <span class="empty-state-icon">👤</span>
            <p>Nog geen favoriete karakters toegevoegd.</p>
        </div>
        <?php endif; ?>

        <!-- Karakter toevoegen formulier -->
        <div class="char-add-form">
            <h3>👤 Karakter toevoegen</h3>
            <form method="POST" action="game_detail.php?id=<?= $id ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_character">

                <div class="form-row">
                    <div class="form-group">
                        <label for="char_name">Naam <span class="req">*</span></label>
                        <input type="text" name="char_name" id="char_name"
                               placeholder="Bijv. Geralt of Rivia" required>
                    </div>
                    <div class="form-group">
                        <label for="char_image">Afbeelding (optioneel)</label>
                        <input type="file" name="char_image" id="char_image"
                               accept=".jpg,.jpeg,.png,.gif,.webp">
                    </div>
                </div>

                <div class="form-group">
                    <label for="char_desc">Omschrijving</label>
                    <input type="text" name="char_desc" id="char_desc"
                           placeholder="Waarom is dit jouw favoriet?">
                </div>

                <div class="form-group form-check">
                    <input type="checkbox" name="is_top_pick" id="is_top_pick" value="1">
                    <label for="is_top_pick">⭐ Maak dit mijn absolute top pick</label>
                </div>

                <button type="submit" class="btn btn-primary">➕ Karakter toevoegen</button>
            </form>
        </div>
    </div>

    <!-- ===== DLC's ===== -->
    <?php if (!empty($dlcs)): ?>
    <div class="section-block">
        <div class="section-header">
            <h2>📦 DLC's</h2>
            <span class="section-count"><?= count($dlcs) ?></span>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:12px;">
            <?php foreach ($dlcs as $dlc):
                $dlcImg    = 'uploads/' . $dlc['cover_image'];
                $dlcHasImg = !empty($dlc['cover_image']) && file_exists(__DIR__ . '/' . $dlcImg);
                $dlcStatus = ['played'=>'✅ Gespeeld','playing'=>'🎮 Bezig','backlog'=>'📋 Backlog','wishlist'=>'🎯 Verlanglijst'][$dlc['status']] ?? $dlc['status'];
            ?>
            <div style="background:var(--white);border-radius:var(--radius-sm);box-shadow:var(--card-shadow);display:flex;align-items:center;gap:14px;padding:12px 16px;">
                <?php if ($dlcHasImg): ?>
                    <img src="<?= htmlspecialchars($dlcImg) ?>" style="width:40px;height:54px;object-fit:cover;object-position:center top;border-radius:5px;flex-shrink:0;" loading="lazy" alt="">
                <?php else: ?>
                    <div style="width:40px;height:54px;background:var(--purple-light);border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">📦</div>
                <?php endif; ?>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:700;font-size:0.9rem;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($dlc['title']) ?></div>
                    <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;"><?= $dlcStatus ?><?= $dlc['playtime'] > 0 ? ' · ' . (int)$dlc['playtime'] . 'u' : '' ?></div>
                </div>
                <?php if ($dlc['rating'] > 0): ?>
                <div style="font-weight:800;color:var(--purple);font-size:0.95rem;flex-shrink:0;">⭐ <?= number_format((float)$dlc['rating'],1) ?></div>
                <?php endif; ?>
                <a href="edit_game.php?id=<?= (int)$dlc['id'] ?>" style="font-size:0.8rem;color:var(--purple);flex-shrink:0;text-decoration:none;">✏️</a>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:12px;">
            <a href="add_game.php" class="btn btn-secondary" style="width:auto;font-size:0.82rem;">+ DLC toevoegen</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== REPLAY LOG ===== -->
    <div class="section-block">
        <div class="section-header">
            <h2>📅 Speellog</h2>
            <span class="section-count"><?= count($sessions) ?> sessie<?= count($sessions) !== 1 ? 's' : '' ?></span>
        </div>

        <?php if (!empty($sessions)): ?>
        <div class="session-timeline">
            <?php foreach ($sessions as $s): ?>
            <div class="session-entry">
                <div class="session-dot"></div>
                <div class="session-body">
                    <div class="session-date">
                        <?= date('d F Y', strtotime($s['played_date'])) ?>
                    </div>
                    <?php if ($s['note']): ?>
                    <p class="session-note"><?= htmlspecialchars($s['note']) ?></p>
                    <?php endif; ?>
                </div>
                <form method="POST" action="game_detail.php?id=<?= $id ?>" style="margin-left:auto;flex-shrink:0;"
                      onsubmit="return confirm('Sessie verwijderen?')">
                    <input type="hidden" name="action" value="delete_session">
                    <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                    <button type="submit" class="btn-icon btn-icon-danger" title="Verwijder sessie">🗑️</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:20px 0;">
            <span class="empty-state-icon">📅</span>
            <p>Nog geen sessies gelogd.</p>
        </div>
        <?php endif; ?>

        <!-- Sessie toevoegen -->
        <div class="char-add-form" style="margin-top:20px;">
            <h3>➕ Sessie toevoegen</h3>
            <form method="POST" action="game_detail.php?id=<?= $id ?>">
                <input type="hidden" name="action" value="add_session">
                <div class="form-row">
                    <div class="form-group">
                        <label for="session_date">Datum <span class="req">*</span></label>
                        <input type="date" name="session_date" id="session_date"
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="session_note">Notitie (optioneel)</label>
                        <input type="text" name="session_note" id="session_note"
                               placeholder="Bijv. eerste playthrough afgerond">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">💾 Opslaan</button>
            </form>
        </div>
    </div>

</div><!-- /page-wrapper -->

</body>
</html>

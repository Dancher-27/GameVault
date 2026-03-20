<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'connection.php';
require_once 'classes/Game.php';
require_once 'classes/Tag.php';
require_once 'classes/Series.php';

$gameObj    = new Game($conn, (int)$_SESSION["user_id"]);
$tagObj     = new Tag($conn, (int)$_SESSION["user_id"]);
$seriesObj  = new Series($conn, (int)$_SESSION["user_id"]);
$alertMsg  = '';
$alertType = '';
$game      = null;

// Game ophalen voor bewerken
if (isset($_GET['id'])) {
    $game = $gameObj->getGameById((int)$_GET['id']);
    if (!$game) {
        header('Location: index.php');
        exit();
    }
}

$uid = (int)$_SESSION['user_id'];
// Alle games voor de keuzelijst (inclusief verlanglijst)
$allGames = $conn->query(
    "SELECT * FROM games WHERE user_id = $uid AND (game_type = 'game' OR game_type IS NULL) ORDER BY rating DESC, title ASC"
)->fetch_all(MYSQLI_ASSOC);

// Verwerken van het formulier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id             = (int)($_POST['id']            ?? 0);
    $title          = trim($_POST['title']          ?? '');
    $platform       = trim($_POST['platform']       ?? '');
    $genre          = trim($_POST['genre']          ?? '');
    $release_year   = (int)($_POST['release_year']  ?? 0) ?: null;
    $status         = trim($_POST['status']         ?? 'played');
    $review         = trim($_POST['review']         ?? '');
    $playtime       = max(0, (int)($_POST['playtime'] ?? 0));
    $replay_status  = trim($_POST['replay_status']  ?? 'not_replayed');
    $game_type      = in_array($_POST['game_type'] ?? '', ['game','dlc']) ? $_POST['game_type'] : 'game';
    $parent_game_id = $game_type === 'dlc' ? ((int)($_POST['parent_game_id'] ?? 0) ?: null) : null;

    $allowedStatus = ['played', 'playing', 'backlog', 'wishlist'];
    if (!in_array($status, $allowedStatus)) $status = 'played';

    $allowedReplay = ['not_replayed', 'replayed', 'will_replay', 'wont_replay'];
    if (!in_array($replay_status, $allowedReplay)) $replay_status = 'not_replayed';

    if ($status === 'wishlist') {
        $rating        = 0.0;
        $enjoyability  = 0.0;
        $replayability = 0.0;
        $replay_status = 'not_replayed';
        $playtime      = 0;
        $review        = '';
        // Behoud bestaande wishlist_rank als de game al op de wishlist staat
        $existing = $gameObj->getGameById($id);
        $wishlist_rank = ($existing && $existing['status'] === 'wishlist' && (int)$existing['wishlist_rank'] > 0)
            ? (int)$existing['wishlist_rank']
            : $gameObj->getNextWishlistRank();
    } else {
        $rating        = max(0, min(10, (float)($_POST['rating']        ?? 0)));
        $enjoyability  = max(0, min(10, (float)($_POST['enjoyability']  ?? 0)));
        $replayability = max(0, min(10, (float)($_POST['replayability'] ?? 0)));
        $wishlist_rank = 0;
    }

    // Haal huidige game op
    $game = $gameObj->getGameById($id);
    if (!$game) {
        $alertMsg  = 'Game niet gevonden.';
        $alertType = 'error';
    } else {
        $cover_image = null;

        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $targetDir = 'uploads/';
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $alertMsg  = 'Ongeldig formaat. Gebruik JPG, PNG, GIF of WebP.';
                $alertType = 'error';
            } else {
                $safe     = preg_replace('/[^a-z0-9_-]/i', '_', strtolower($title));
                $filename = $safe . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['cover']['tmp_name'], $targetDir . $filename)) {
                    $cover_image = $filename;
                } else {
                    $alertMsg  = 'Upload mislukt.';
                    $alertType = 'error';
                }
            }
        }

        if (empty($alertMsg)) {
            $ok = $gameObj->updateGame(
                $id, $title, $platform, $genre, $release_year, $cover_image,
                $rating, $enjoyability, $replayability,
                $status, $review, $playtime, $replay_status, $wishlist_rank,
                $game_type, $parent_game_id
            );
            if ($ok) {
                $selectedTags = array_map('intval', $_POST['tags'] ?? []);
                $tagObj->setTagsForGame($id, $selectedTags);

                // Series assignment
                $seriesId    = (int)($_POST['series_id'] ?? 0);
                $seriesOrder = (int)($_POST['series_order'] ?? 0);
                if ($seriesId > 0) {
                    $seriesObj->assignGame($id, $seriesId, $seriesOrder);
                } else {
                    $seriesObj->removeGame($id);
                }

                if ($status !== 'wishlist') {
                    $gameObj->reRankByRating($status);
                }

                // Log what changed
                if ($game['status'] !== $status) {
                    $gameObj->logActivity('status_changed', $id, $title, $game['status'] . '→' . $status);
                } elseif ((float)$game['rating'] !== $rating && $rating > 0) {
                    $gameObj->logActivity('rating_changed', $id, $title, number_format((float)$game['rating'],1) . '→' . number_format($rating,1));
                }

                $alertMsg  = '"' . htmlspecialchars($title) . '" bijgewerkt!';
                $alertType = 'success';
                $game      = $gameObj->getGameById($id); // refresh
            } else {
                $alertMsg  = 'Fout bij opslaan.';
                $alertType = 'error';
            }
        }
    }
}

$platforms      = ['PC', 'PlayStation 5', 'PlayStation 4', 'Xbox Series X/S', 'Xbox One', 'Nintendo Switch', 'Mobile', 'Other'];
$genres         = ['Action', 'Adventure', 'RPG', 'FPS', 'Strategy', 'Sports', 'Horror', 'Puzzle', 'Simulation', 'Racing', 'Fighting', 'Platformer', 'Stealth', 'Open World', 'Other'];
$allTags        = $tagObj->getAllTags();
$currentTags    = $game ? array_column($tagObj->getTagsByGame((int)$game['id']), 'id') : [];
$allSeries      = $seriesObj->getAllSeries();
$currentSeries  = $game ? $seriesObj->getSeriesForGame((int)$game['id']) : null;
// All base games for parent-game dropdown (exclude current game and other DLCs)
$baseGames = $conn->query("SELECT id, title FROM games WHERE user_id = $uid AND (game_type = 'game' OR game_type IS NULL)" . ($game ? " AND id != " . (int)$game['id'] : "") . " ORDER BY title ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Bewerken</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-wrapper">

    <!-- ===== GAME KIEZEN ===== -->
    <?php if (!$game): ?>
    <h1 class="page-title">✏️ Game bewerken</h1>
    <p class="page-subtitle">Kies een game om te bewerken.</p>

    <?php if ($alertMsg): ?>
    <div class="alert alert-<?= $alertType ?>" style="margin-bottom:20px;">
        <?= $alertType === 'success' ? '✅' : '⚠️' ?>
        <?= htmlspecialchars($alertMsg) ?>
    </div>
    <?php endif; ?>

    <?php if (empty($allGames)): ?>
    <div class="empty-state" style="margin-top:20px;">
        <span class="empty-state-icon">🎯</span>
        <p>Geen games gevonden. Voeg eerst een game toe.</p>
        <a href="add_game.php" class="btn btn-primary" style="width:auto;">➕ Game toevoegen</a>
    </div>
    <?php else: ?>
    <div class="edit-search-wrap">
        <span class="edit-search-icon">🔍</span>
        <input type="text" id="selectSearch"
               placeholder="Zoek een game..."
               oninput="filterSelectCards(this.value)">
    </div>
    <div class="edit-select-grid" id="editSelectGrid">
        <?php foreach ($allGames as $g):
            $img = 'uploads/' . $g['cover_image'];
            $hasImg = !empty($g['cover_image']) && file_exists(__DIR__ . '/' . $img);
        ?>
        <a href="edit_game.php?id=<?= (int)$g['id'] ?>"
           class="edit-select-card"
           data-title="<?= strtolower(htmlspecialchars($g['title'])) ?>">
            <?php if ($hasImg): ?>
            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($g['title']) ?>"
                 class="edit-select-img" loading="lazy">
            <?php else: ?>
            <div class="edit-select-placeholder">🎮</div>
            <?php endif; ?>
            <div class="edit-select-overlay">
                <span class="edit-select-rating">⭐ <?= number_format((float)$g['rating'], 1) ?></span>
                <span class="edit-select-title"><?= htmlspecialchars($g['title']) ?></span>
                <span class="edit-select-meta"><?= htmlspecialchars($g['platform'] ?? '') ?></span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <script>
    function filterSelectCards(q) {
        q = q.toLowerCase();
        document.querySelectorAll('.edit-select-card').forEach(function(c) {
            c.style.display = c.dataset.title.includes(q) ? '' : 'none';
        });
    }
    </script>
    <?php endif; ?>

    <?php else: ?>
    <div class="form-container">
        <h1>✏️ Game bewerken</h1>

        <?php if ($alertMsg): ?>
        <div class="alert alert-<?= $alertType ?>">
            <?= $alertType === 'success' ? '✅' : '⚠️' ?>
            <?= htmlspecialchars($alertMsg) ?>
        </div>
        <?php endif; ?>

        <!-- ===== BEWERK FORMULIER ===== -->
        <p class="form-subtitle">Je bewerkt: <strong><?= htmlspecialchars($game['title']) ?></strong></p>

        <form action="edit_game.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= (int)$game['id'] ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="title">Titel <span class="req">*</span></label>
                    <input type="text" name="title" id="title" required
                           value="<?= htmlspecialchars($_POST['title'] ?? $game['title']) ?>">
                </div>
                <div class="form-group">
                    <label for="platform">Platform</label>
                    <select name="platform" id="platform">
                        <option value="">Kies platform...</option>
                        <?php
                        $selPlat = $_POST['platform'] ?? $game['platform'];
                        foreach ($platforms as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>"
                            <?= $selPlat === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="genre">Genre</label>
                    <select name="genre" id="genre">
                        <option value="">Kies genre...</option>
                        <?php
                        $selGenre = $_POST['genre'] ?? $game['genre'];
                        foreach ($genres as $g): ?>
                        <option value="<?= htmlspecialchars($g) ?>"
                            <?= $selGenre === $g ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="release_year">Jaar van uitgave</label>
                    <input type="number" name="release_year" id="release_year"
                           min="1970" max="<?= date('Y') + 2 ?>"
                           value="<?= htmlspecialchars((string)($_POST['release_year'] ?? $game['release_year'] ?? '')) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" onchange="toggleWishlistFields(this.value)">
                        <?php $curStatus = $_POST['status'] ?? $game['status']; ?>
                        <option value="played"   <?= $curStatus === 'played'   ? 'selected' : '' ?>>✅ Gespeeld</option>
                        <option value="playing"  <?= $curStatus === 'playing'  ? 'selected' : '' ?>>🎮 Bezig</option>
                        <option value="backlog"  <?= $curStatus === 'backlog'  ? 'selected' : '' ?>>📋 Backlog</option>
                        <option value="wishlist" <?= $curStatus === 'wishlist' ? 'selected' : '' ?>>🎯 Verlanglijst</option>
                    </select>
                </div>
                <div class="form-group" id="field-playtime">
                    <label for="playtime">Speeltijd (uren)</label>
                    <input type="number" name="playtime" id="playtime"
                           min="0" max="9999"
                           value="<?= (int)($_POST['playtime'] ?? $game['playtime'] ?? 0) ?>">
                </div>
            </div>

            <!-- Sliders -->
            <div id="field-rating" class="slider-group">
                <label for="rating">Rating (0–10)</label>
                <div class="slider-row">
                    <?php $curRating = $_POST['rating'] ?? $game['rating'] ?? 5; ?>
                    <input type="range" name="rating" id="rating"
                           min="0" max="10" step="0.1"
                           value="<?= htmlspecialchars((string)$curRating) ?>"
                           oninput="document.getElementById('rating-display').textContent = this.value">
                    <span class="slider-val" id="rating-display"><?= htmlspecialchars((string)$curRating) ?></span>
                </div>
            </div>

            <div id="field-enjoyability" class="slider-group">
                <label for="enjoyability">Enjoyability (0–10)</label>
                <div class="slider-row">
                    <?php $curEnjoy = $_POST['enjoyability'] ?? $game['enjoyability'] ?? 5; ?>
                    <input type="range" name="enjoyability" id="enjoyability"
                           min="0" max="10" step="0.1"
                           value="<?= htmlspecialchars((string)$curEnjoy) ?>"
                           oninput="document.getElementById('enjoy-display').textContent = this.value">
                    <span class="slider-val" id="enjoy-display"><?= htmlspecialchars((string)$curEnjoy) ?></span>
                </div>
            </div>

            <div id="field-replayability" class="slider-group">
                <label for="replayability">Replayability (0–10)</label>
                <div class="slider-row">
                    <?php $curReplayability = $_POST['replayability'] ?? $game['replayability'] ?? 5; ?>
                    <input type="range" name="replayability" id="replayability"
                           min="0" max="10" step="0.1"
                           value="<?= htmlspecialchars((string)$curReplayability) ?>"
                           oninput="document.getElementById('replay-val-display').textContent = this.value">
                    <span class="slider-val" id="replay-val-display"><?= htmlspecialchars((string)$curReplayability) ?></span>
                </div>
            </div>

            <!-- Replay status -->
            <div id="field-replay-status" class="form-group">
                <label>Replay status</label>
                <div class="replay-options">
                    <?php $curReplay = $_POST['replay_status'] ?? $game['replay_status'] ?? 'not_replayed'; ?>
                    <input type="radio" name="replay_status" id="rs-not" value="not_replayed"
                           <?= $curReplay === 'not_replayed' ? 'checked' : '' ?>>
                    <label for="rs-not">➖ Niet hergespeeld</label>

                    <input type="radio" name="replay_status" id="rs-replayed" value="replayed"
                           <?= $curReplay === 'replayed' ? 'checked' : '' ?>>
                    <label for="rs-replayed">🔁 Hergespeeld</label>

                    <input type="radio" name="replay_status" id="rs-will" value="will_replay"
                           <?= $curReplay === 'will_replay' ? 'checked' : '' ?>>
                    <label for="rs-will">🔜 Wil herspelen</label>

                    <input type="radio" name="replay_status" id="rs-wont" value="wont_replay"
                           <?= $curReplay === 'wont_replay' ? 'checked' : '' ?>>
                    <label for="rs-wont">🚫 Niet herspelen</label>
                </div>
            </div>

            <!-- Game type / DLC -->
            <div class="form-group">
                <label class="form-label">Type</label>
                <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
                    <?php $selType = $_POST['game_type'] ?? $game['game_type'] ?? 'game'; ?>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="radio" name="game_type" value="game" <?= $selType === 'game' ? 'checked' : '' ?> onchange="toggleDlcParent(this.value)">
                        🎮 Game (zelfstandig)
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="radio" name="game_type" value="dlc" <?= $selType === 'dlc' ? 'checked' : '' ?> onchange="toggleDlcParent(this.value)">
                        📦 DLC (hoort bij een game)
                    </label>
                </div>
            </div>
            <div id="dlc-parent-field" class="form-group" style="<?= ($selType !== 'dlc') ? 'display:none;' : '' ?>">
                <label for="parent_game_id">Hoort bij welke game? <span class="req">*</span></label>
                <select name="parent_game_id" id="parent_game_id">
                    <option value="0">— Kies een game —</option>
                    <?php
                    $selParent = (int)($_POST['parent_game_id'] ?? $game['parent_game_id'] ?? 0);
                    foreach ($baseGames as $bg): ?>
                    <option value="<?= (int)$bg['id'] ?>" <?= $selParent === (int)$bg['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($bg['title']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-hint">DLC's worden niet meegeteld in de ranking maar zijn wel zichtbaar op de detailpagina van de game.</p>
            </div>

            <!-- Cover -->
            <div class="form-group">
                <label>Huidige cover</label>
                <?php
                $img = 'uploads/' . $game['cover_image'];
                if (!empty($game['cover_image']) && file_exists(__DIR__ . '/' . $img)): ?>
                    <img src="<?= htmlspecialchars($img) ?>" alt="cover"
                         style="width:80px; height:110px; object-fit:cover; border-radius:8px; display:block; margin-bottom:8px; box-shadow:var(--card-shadow);">
                <?php else: ?>
                    <p class="form-hint">Geen cover ingesteld.</p>
                <?php endif; ?>
                <label for="cover">Nieuwe cover uploaden (optioneel)</label>
                <input type="file" name="cover" id="cover" accept=".jpg,.jpeg,.png,.gif,.webp">
            </div>

            <div id="field-review" class="form-group">
                <label for="review">Review / Notities</label>
                <textarea name="review" id="review" rows="4"><?= htmlspecialchars($_POST['review'] ?? $game['review'] ?? '') ?></textarea>
            </div>

            <?php if (!empty($allTags)): ?>
            <div class="form-group">
                <label class="form-label">🏷️ Tags</label>
                <div class="tag-picker">
                    <?php
                    $activeTags = isset($_POST['tags']) ? array_map('intval', $_POST['tags']) : $currentTags;
                    foreach ($allTags as $t): ?>
                    <label class="tag-option">
                        <input type="checkbox" name="tags[]" value="<?= $t['id'] ?>"
                               <?= in_array((int)$t['id'], $activeTags) ? 'checked' : '' ?>>
                        <span class="tag-pill" style="--tag-color:<?= htmlspecialchars($t['color']) ?>">
                            <?= htmlspecialchars($t['name']) ?>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <a href="tags.php" target="_blank" style="font-size:0.8rem;color:var(--purple);margin-top:6px;display:inline-block;">+ Nieuwe tag aanmaken</a>
            </div>
            <?php endif; ?>

            <!-- Series -->
            <?php if (!empty($allSeries)): ?>
            <div class="form-group">
                <label class="form-label">🎮 Reeks / Franchise</label>
                <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                    <div style="flex:2;min-width:160px;">
                        <label for="series_id" style="font-size:0.8rem;color:var(--text-muted);margin-bottom:4px;display:block;">Reeks</label>
                        <select name="series_id" id="series_id">
                            <option value="0">— Geen reeks —</option>
                            <?php
                            $selSeriesId = isset($_POST['series_id']) ? (int)$_POST['series_id'] : (int)($currentSeries['id'] ?? 0);
                            foreach ($allSeries as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= $selSeriesId === (int)$s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:1;min-width:100px;">
                        <label for="series_order" style="font-size:0.8rem;color:var(--text-muted);margin-bottom:4px;display:block;">Deel #</label>
                        <input type="number" name="series_order" id="series_order" min="0" max="999"
                               value="<?= (int)(isset($_POST['series_order']) ? $_POST['series_order'] : ($currentSeries['series_order'] ?? 0)) ?>"
                               placeholder="0">
                    </div>
                </div>
                <p class="form-hint" style="margin-top:6px;">Selecteer "Geen reeks" om de game te ontkoppelen. <a href="series.php" target="_blank" style="color:var(--purple);">Beheer reeksen →</a></p>
            </div>
            <?php elseif (empty($allSeries)): ?>
            <div class="form-group">
                <label class="form-label">🎮 Reeks / Franchise</label>
                <p class="form-hint">Nog geen reeksen aangemaakt. <a href="series.php" target="_blank" style="color:var(--purple);">Maak een reeks aan →</a></p>
            </div>
            <?php endif; ?>

            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary" style="width:auto; flex:1;">💾 Opslaan</button>
                <a href="game_detail.php?id=<?= (int)$game['id'] ?>" class="btn btn-secondary">← Terug naar game</a>
            </div>
        </form>

    </div><!-- /form-container -->
    <?php endif; ?>
</div><!-- /page-wrapper -->

<script>
function toggleDlcParent(type) {
    var el = document.getElementById('dlc-parent-field');
    if (el) el.style.display = type === 'dlc' ? '' : 'none';
}

function toggleWishlistFields(status) {
    var wishlistOnly = status === 'wishlist';
    var fields = ['field-rating', 'field-enjoyability', 'field-replayability',
                  'field-replay-status', 'field-playtime', 'field-review'];
    fields.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = wishlistOnly ? 'none' : '';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    var statusEl = document.getElementById('status');
    if (statusEl) toggleWishlistFields(statusEl.value);
});
</script>

</body>
</html>

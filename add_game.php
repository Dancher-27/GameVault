<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'connection.php';
require_once 'classes/Game.php';
require_once 'classes/Tag.php';

$gameObj   = new Game($conn, (int)$_SESSION["user_id"]);
$tagObj    = new Tag($conn, (int)$_SESSION["user_id"]);
$alertMsg  = '';
$alertType = '';

// Pre-fill vanuit search.php (RAWG data via GET)
$allowedPrefillStatus = ['played', 'playing', 'backlog', 'wishlist'];
$prefillStatus = trim($_GET['status'] ?? 'played');
if (!in_array($prefillStatus, $allowedPrefillStatus)) $prefillStatus = 'played';

$prefill = [
    'title'     => trim($_GET['title']     ?? ''),
    'platform'  => trim($_GET['platform']  ?? ''),
    'genre'     => trim($_GET['genre']     ?? ''),
    'year'      => trim($_GET['year']      ?? ''),
    'cover_url' => trim($_GET['cover_url'] ?? ''),
    'status'    => $prefillStatus,
];
if (!filter_var($prefill['cover_url'], FILTER_VALIDATE_URL)) {
    $prefill['cover_url'] = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // Wishlist: scores op 0 zetten en rank bepalen
    if ($status === 'wishlist') {
        $rating        = 0.0;
        $enjoyability  = 0.0;
        $replayability = 0.0;
        $replay_status = 'not_replayed';
        $playtime      = 0;
        $review        = '';
        $wishlist_rank = $gameObj->getNextWishlistRank();
    } else {
        $rating        = max(0, min(10, (float)($_POST['rating']        ?? 0)));
        $enjoyability  = max(0, min(10, (float)($_POST['enjoyability']  ?? 0)));
        $replayability = max(0, min(10, (float)($_POST['replayability'] ?? 0)));
        $wishlist_rank = 0;
    }

    // Afbeelding verwerken
    $cover_image = null;
    $imageError  = null;
    $coverUrl    = trim($_POST['cover_url'] ?? '');
    $targetDir   = 'uploads/';
    $safe        = preg_replace('/[^a-z0-9_-]/i', '_', strtolower($title));

    $hasUpload = isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK;

    if ($hasUpload) {
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $imageError = 'Ongeldig formaat. Gebruik JPG, PNG, GIF of WebP.';
        } else {
            $filename = $safe . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['cover']['tmp_name'], $targetDir . $filename)) {
                $cover_image = $filename;
            } else {
                $imageError = 'Upload mislukt. Controleer maprechten.';
            }
        }
    } elseif (!empty($coverUrl) && filter_var($coverUrl, FILTER_VALIDATE_URL)) {
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        $filename = $safe . '_' . time() . '.jpg';
        $content  = @file_get_contents($coverUrl);
        if ($content !== false && file_put_contents($targetDir . $filename, $content)) {
            $cover_image = $filename;
        }
    }

    if ($imageError) {
        $alertMsg  = $imageError;
        $alertType = 'error';
    } elseif (empty($title)) {
        $alertMsg  = 'Titel is verplicht.';
        $alertType = 'error';
    } else {
        $ok = $gameObj->addGame(
            $title, $platform, $genre, $release_year, $cover_image,
            $rating, $enjoyability, $replayability,
            $status, $review, $playtime, $replay_status, $wishlist_rank,
            $game_type, $parent_game_id
        );
        if ($ok) {
            $newId = (int)$conn->insert_id;
            $selectedTags = array_map('intval', $_POST['tags'] ?? []);
            if (!empty($selectedTags)) $tagObj->setTagsForGame($newId, $selectedTags);
            if ($status !== 'wishlist') {
                $gameObj->reRankByRating($status);
            }
            $gameObj->logActivity('game_added', $newId, $title, $status);
            $alertMsg  = '"' . htmlspecialchars($title) . '" succesvol toegevoegd!';
            $alertType = 'success';
        } else {
            $alertMsg  = 'Fout bij opslaan: ' . $conn->error;
            $alertType = 'error';
        }
    }
}

$platforms = ['PC', 'PlayStation 5', 'PlayStation 4', 'Xbox Series X/S', 'Xbox One', 'Nintendo Switch', 'Mobile', 'Other'];
$allTags   = $tagObj->getAllTags();
$genres    = ['Action', 'Adventure', 'RPG', 'FPS', 'Strategy', 'Sports', 'Horror', 'Puzzle', 'Simulation', 'Racing', 'Fighting', 'Platformer', 'Stealth', 'Open World', 'Other'];
$baseGames = $conn->query("SELECT id, title FROM games WHERE (game_type = 'game' OR game_type IS NULL) ORDER BY title ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Game toevoegen</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-wrapper">
    <div class="form-container">
        <h1>➕ Game toevoegen</h1>
        <p class="form-subtitle">Voeg een gespeelde, lopende, backlog of verlanglijst game toe aan je vault.</p>

        <?php if ($alertMsg): ?>
        <div class="alert alert-<?= $alertType ?>">
            <?= $alertType === 'success' ? '✅' : '⚠️' ?>
            <?= htmlspecialchars($alertMsg) ?>
            <?php if ($alertType === 'success'): ?>
            <a href="index.php" style="margin-left:12px; color:inherit; font-weight:700;">→ Terug naar ranking</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- RAWG cover preview -->
        <?php if (!empty($prefill['cover_url'])): ?>
        <div class="prefill-banner">
            <img src="<?= htmlspecialchars($prefill['cover_url']) ?>" alt="Cover" class="prefill-cover">
            <div>
                <p class="prefill-label">🎮 Gevonden via RAWG</p>
                <p class="prefill-sub">Cover wordt automatisch opgeslagen. Je kunt ook zelf uploaden om te overschrijven.</p>
            </div>
        </div>
        <?php endif; ?>

        <form action="add_game.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="cover_url" value="<?= htmlspecialchars($prefill['cover_url'] ?? '') ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="title">Titel <span class="req">*</span></label>
                    <input type="text" name="title" id="title" required
                           placeholder="Bijv. The Last of Us Part II"
                           value="<?= htmlspecialchars($_POST['title'] ?? $prefill['title']) ?>">
                    <div id="duplicate-warning" style="display:none;margin-top:6px;padding:8px 12px;background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;font-size:.82rem;color:#92400e;">
                        ⚠️ Je hebt al een game met deze naam: <a id="dup-link" href="#" style="color:#92400e;font-weight:700;text-decoration:underline;"></a> (<span id="dup-status"></span>)
                    </div>
                </div>
                <div class="form-group">
                    <label for="platform">Platform</label>
                    <select name="platform" id="platform">
                        <option value="">Kies platform...</option>
                        <?php
                        $selPlat = $_POST['platform'] ?? $prefill['platform'];
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
                        $selGenre = $_POST['genre'] ?? $prefill['genre'];
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
                           placeholder="Bijv. 2023"
                           value="<?= htmlspecialchars($_POST['release_year'] ?? $prefill['year']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="status">Status <span class="req">*</span></label>
                    <select name="status" id="status" required onchange="toggleWishlistFields(this.value)">
                        <?php $curStatus = $_POST['status'] ?? $prefill['status']; ?>
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
                           placeholder="Bijv. 42"
                           value="<?= htmlspecialchars((string)($_POST['playtime'] ?? '')) ?>">
                </div>
            </div>

            <!-- Sliders (verborgen bij wishlist) -->
            <div id="field-rating" class="slider-group">
                <label for="rating">Rating (0–10)</label>
                <div class="slider-row">
                    <input type="range" name="rating" id="rating"
                           min="0" max="10" step="0.1"
                           value="<?= htmlspecialchars((string)($_POST['rating'] ?? '5')) ?>"
                           oninput="document.getElementById('rating-display').textContent = this.value">
                    <span class="slider-val" id="rating-display"><?= htmlspecialchars((string)($_POST['rating'] ?? '5')) ?></span>
                </div>
            </div>

            <div id="field-enjoyability" class="slider-group">
                <label for="enjoyability">Enjoyability (0–10)</label>
                <div class="slider-row">
                    <input type="range" name="enjoyability" id="enjoyability"
                           min="0" max="10" step="0.1"
                           value="<?= htmlspecialchars((string)($_POST['enjoyability'] ?? '5')) ?>"
                           oninput="document.getElementById('enjoy-display').textContent = this.value">
                    <span class="slider-val" id="enjoy-display"><?= htmlspecialchars((string)($_POST['enjoyability'] ?? '5')) ?></span>
                </div>
            </div>

            <div id="field-replayability" class="slider-group">
                <label for="replayability">Replayability (0–10)</label>
                <div class="slider-row">
                    <input type="range" name="replayability" id="replayability"
                           min="0" max="10" step="0.1"
                           value="<?= htmlspecialchars((string)($_POST['replayability'] ?? '5')) ?>"
                           oninput="document.getElementById('replay-display').textContent = this.value">
                    <span class="slider-val" id="replay-display"><?= htmlspecialchars((string)($_POST['replayability'] ?? '5')) ?></span>
                </div>
            </div>

            <!-- Replay status (verborgen bij wishlist) -->
            <div id="field-replay-status" class="form-group">
                <label>Replay status</label>
                <div class="replay-options">
                    <?php $curReplay = $_POST['replay_status'] ?? 'not_replayed'; ?>
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
                    <?php $selType = $_POST['game_type'] ?? 'game'; ?>
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
            <div id="dlc-parent-field" class="form-group" style="<?= $selType !== 'dlc' ? 'display:none;' : '' ?>">
                <label for="parent_game_id">Hoort bij welke game? <span class="req">*</span></label>
                <select name="parent_game_id" id="parent_game_id">
                    <option value="0">— Kies een game —</option>
                    <?php
                    $selParent = (int)($_POST['parent_game_id'] ?? 0);
                    foreach ($baseGames as $bg): ?>
                    <option value="<?= (int)$bg['id'] ?>" <?= $selParent === (int)$bg['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($bg['title']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-hint">DLC's worden niet meegeteld in de ranking maar zijn zichtbaar op de detailpagina van de game.</p>
            </div>

            <div class="form-group">
                <label for="cover">Cover afbeelding</label>
                <input type="file" name="cover" id="cover" accept=".jpg,.jpeg,.png,.gif,.webp">
                <p class="form-hint">JPG, PNG, GIF of WebP. Optioneel — zonder cover verschijnt een standaard icoon.</p>
            </div>

            <div id="field-review" class="form-group">
                <label for="review">Review / Notities</label>
                <textarea name="review" id="review" rows="4"
                          placeholder="Wat vond je van deze game?"><?= htmlspecialchars($_POST['review'] ?? '') ?></textarea>
            </div>

            <?php if (!empty($allTags)): ?>
            <div class="form-group">
                <label class="form-label">🏷️ Tags</label>
                <div class="tag-picker">
                    <?php foreach ($allTags as $t): ?>
                    <label class="tag-option">
                        <input type="checkbox" name="tags[]" value="<?= $t['id'] ?>">
                        <span class="tag-pill" style="--tag-color:<?= htmlspecialchars($t['color']) ?>">
                            <?= htmlspecialchars($t['name']) ?>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <a href="tags.php" target="_blank" style="font-size:0.8rem;color:var(--purple);margin-top:6px;display:inline-block;">+ Nieuwe tag aanmaken</a>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary">➕ Game toevoegen</button>
        </form>
    </div>
</div>

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

// Run on load to handle POST-back with wishlist selected
document.addEventListener('DOMContentLoaded', function() {
    var statusEl = document.getElementById('status');
    if (statusEl) toggleWishlistFields(statusEl.value);
});
</script>

<script>
// Duplicate title check
(function() {
    const titleInput = document.getElementById('title');
    const warning    = document.getElementById('duplicate-warning');
    const dupLink    = document.getElementById('dup-link');
    const dupStatus  = document.getElementById('dup-status');
    if (!titleInput) return;

    const statusLabels = { played:'Gespeeld', playing:'Bezig', backlog:'Backlog', wishlist:'Verlanglijst' };
    let timer;

    titleInput.addEventListener('input', function() {
        clearTimeout(timer);
        const val = this.value.trim();
        if (val.length < 2) { warning.style.display = 'none'; return; }
        timer = setTimeout(() => {
            fetch('check_duplicate.php?title=' + encodeURIComponent(val))
                .then(r => r.json())
                .then(data => {
                    if (data.found) {
                        dupLink.textContent = data.title;
                        dupLink.href = 'game_detail.php?id=' + data.id;
                        dupStatus.textContent = statusLabels[data.status] ?? data.status;
                        warning.style.display = '';
                    } else {
                        warning.style.display = 'none';
                    }
                });
        }, 400);
    });
})();
</script>

</body>
</html>

<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'connection.php';
require_once 'classes/Game.php';

$gameObj  = new Game($conn, (int)$_SESSION["user_id"]);
$alertMsg = '';
$alertType = '';

// POST acties verwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = trim($_POST['action'] ?? '');
    $game_id = (int)($_POST['game_id'] ?? 0);

    if ($action === 'rank_up' && $game_id > 0) {
        // Wissel met de game erboven (lagere rank nummer)
        $current = $gameObj->getGameById($game_id);
        if ($current && $current['status'] === 'wishlist') {
            $currentRank = (int)$current['wishlist_rank'];
            if ($currentRank > 1) {
                // Zoek game met rank = currentRank - 1
                $stmt = $conn->prepare(
                    "SELECT id FROM games WHERE status='wishlist' AND wishlist_rank = ? LIMIT 1"
                );
                $targetRank = $currentRank - 1;
                $stmt->bind_param("i", $targetRank);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $gameObj->updateWishlistRank((int)$row['id'], $currentRank);
                }
                $gameObj->updateWishlistRank($game_id, $targetRank);
            }
        }
        header('Location: wishlist.php');
        exit();

    } elseif ($action === 'rank_down' && $game_id > 0) {
        $current = $gameObj->getGameById($game_id);
        if ($current && $current['status'] === 'wishlist') {
            $currentRank = (int)$current['wishlist_rank'];
            // Zoek game met rank = currentRank + 1
            $stmt = $conn->prepare(
                "SELECT id FROM games WHERE status='wishlist' AND wishlist_rank = ? LIMIT 1"
            );
            $targetRank = $currentRank + 1;
            $stmt->bind_param("i", $targetRank);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $gameObj->updateWishlistRank((int)$row['id'], $currentRank);
                $gameObj->updateWishlistRank($game_id, $targetRank);
            }
        }
        header('Location: wishlist.php');
        exit();

    } elseif ($action === 'rate_and_move' && $game_id > 0) {
        $rating        = max(0, min(10, (float)($_POST['rating']        ?? 0)));
        $enjoyability  = max(0, min(10, (float)($_POST['enjoyability']  ?? 0)));
        $replayability = max(0, min(10, (float)($_POST['replayability'] ?? 0)));
        $replay_status = trim($_POST['replay_status'] ?? 'not_replayed');
        $review        = trim($_POST['review']        ?? '');
        $playtime      = max(0, (int)($_POST['playtime'] ?? 0));

        $allowedReplay = ['not_replayed', 'replayed', 'will_replay', 'wont_replay'];
        if (!in_array($replay_status, $allowedReplay)) $replay_status = 'not_replayed';

        $ok = $gameObj->moveToRanking(
            $game_id, $rating, $enjoyability, $replayability,
            $replay_status, $review, $playtime
        );

        if ($ok) {
            header('Location: index.php');
            exit();
        } else {
            $alertMsg  = 'Fout bij verplaatsen naar ranking.';
            $alertType = 'error';
        }
    }
}

$wishlist = $gameObj->getWishlist();
$count    = count($wishlist);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Verlanglijst</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-wrapper">
    <h1 class="page-title">🎯 Verlanglijst</h1>
    <p class="page-subtitle">Games die je wilt gaan spelen, gesorteerd op prioriteit.</p>

    <?php if ($alertMsg): ?>
    <div class="alert alert-<?= $alertType ?>">
        <?= $alertType === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($alertMsg) ?>
    </div>
    <?php endif; ?>

    <div class="section-block">
        <div class="section-header">
            <h2>🎯 Verlanglijst</h2>
            <span class="section-count"><?= $count ?></span>
        </div>

        <?php if (empty($wishlist)): ?>
        <div class="empty-state">
            <span class="empty-state-icon">🎮</span>
            <p>Je verlanglijst is leeg. Voeg games toe via Zoeken of Toevoegen.</p>
            <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                <a href="search.php" class="btn btn-secondary" style="width:auto;">🔍 Zoeken</a>
                <a href="add_game.php" class="btn btn-primary" style="width:auto;">➕ Toevoegen</a>
            </div>
        </div>
        <?php else: ?>

        <div class="wishlist-grid" id="wishlist-grid">
            <?php foreach ($wishlist as $i => $game): ?>
            <?php
            $rank    = (int)$game['wishlist_rank'];
            $isFirst = ($i === 0);
            $isLast  = ($i === $count - 1);
            $rankClass = '';
            if ($rank === 1)      $rankClass = 'rank-1';
            elseif ($rank === 2)  $rankClass = 'rank-2';
            elseif ($rank === 3)  $rankClass = 'rank-3';
            ?>
            <div class="wishlist-item" data-id="<?= $game['id'] ?>">
                <!-- Rank nummer -->
                <div class="wishlist-rank-num <?= $rankClass ?>">
                    <?php
                    if ($rank === 1)     echo '🥇';
                    elseif ($rank === 2) echo '🥈';
                    elseif ($rank === 3) echo '🥉';
                    else                 echo '#' . $rank;
                    ?>
                </div>

                <!-- Cover -->
                <?php
                $img = 'uploads/' . $game['cover_image'];
                if (!empty($game['cover_image']) && file_exists(__DIR__ . '/' . $img)): ?>
                    <img src="<?= htmlspecialchars($img) ?>"
                         alt="<?= htmlspecialchars($game['title']) ?>"
                         class="wishlist-cover"
                         loading="lazy">
                <?php else: ?>
                    <div class="wishlist-cover-placeholder">🎮</div>
                <?php endif; ?>

                <!-- Info -->
                <div class="wishlist-info">
                    <div class="wishlist-title"><?= htmlspecialchars($game['title']) ?></div>
                    <div class="wishlist-meta">
                        <?php if (!empty($game['platform'])): ?>
                        <span class="meta-tag">🖥️ <?= htmlspecialchars($game['platform']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($game['genre'])): ?>
                        <span class="meta-tag">🎯 <?= htmlspecialchars($game['genre']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($game['release_year'])): ?>
                        <span class="meta-tag">📅 <?= (int)$game['release_year'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Acties -->
                <div class="wishlist-actions">
                    <?php if (!$isFirst): ?>
                    <form method="POST" action="wishlist.php" style="margin:0;">
                        <input type="hidden" name="action"  value="rank_up">
                        <input type="hidden" name="game_id" value="<?= (int)$game['id'] ?>">
                        <button type="submit" class="btn btn-icon btn-sm" title="Omhoog">▲</button>
                    </form>
                    <?php endif; ?>

                    <?php if (!$isLast): ?>
                    <form method="POST" action="wishlist.php" style="margin:0;">
                        <input type="hidden" name="action"  value="rank_down">
                        <input type="hidden" name="game_id" value="<?= (int)$game['id'] ?>">
                        <button type="submit" class="btn btn-icon btn-sm" title="Omlaag">▼</button>
                    </form>
                    <?php endif; ?>

                    <button class="btn btn-blue btn-sm"
                            onclick="openRateModal(<?= (int)$game['id'] ?>, '<?= htmlspecialchars(addslashes($game['title'])) ?>')">
                        🎮 Gespeeld!
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <p style="margin-top:20px; color:var(--text-muted); font-size:0.88rem;">
            Voeg meer games toe via
            <a href="search.php" style="color:var(--purple); font-weight:600;">🔍 Zoeken</a>
            of
            <a href="add_game.php" style="color:var(--purple); font-weight:600;">➕ Toevoegen</a>.
        </p>
        <?php endif; ?>
    </div>
</div>

<!-- ===== RATING MODAL ===== -->
<div class="modal-overlay" id="rate-modal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeRateModal()">✕</button>
        <h2 id="modal-title">🎮 Game beoordelen</h2>

        <form method="POST" action="wishlist.php">
            <input type="hidden" name="action"  value="rate_and_move">
            <input type="hidden" name="game_id" id="modal-game-id" value="">

            <div class="slider-group">
                <label for="m-rating">Rating (0–10)</label>
                <div class="slider-row">
                    <input type="range" name="rating" id="m-rating"
                           min="0" max="10" step="0.5" value="7"
                           oninput="document.getElementById('m-rating-val').textContent = this.value">
                    <span class="slider-val" id="m-rating-val">7</span>
                </div>
            </div>

            <div class="slider-group">
                <label for="m-enjoyability">Enjoyability (0–10)</label>
                <div class="slider-row">
                    <input type="range" name="enjoyability" id="m-enjoyability"
                           min="0" max="10" step="0.5" value="7"
                           oninput="document.getElementById('m-enjoy-val').textContent = this.value">
                    <span class="slider-val" id="m-enjoy-val">7</span>
                </div>
            </div>

            <div class="slider-group">
                <label for="m-replayability">Replayability (0–10)</label>
                <div class="slider-row">
                    <input type="range" name="replayability" id="m-replayability"
                           min="0" max="10" step="0.5" value="5"
                           oninput="document.getElementById('m-replay-val').textContent = this.value">
                    <span class="slider-val" id="m-replay-val">5</span>
                </div>
            </div>

            <div class="form-group">
                <label>Replay status</label>
                <div class="replay-options">
                    <input type="radio" name="replay_status" id="m-rs-not" value="not_replayed" checked>
                    <label for="m-rs-not">➖ Niet hergespeeld</label>

                    <input type="radio" name="replay_status" id="m-rs-replayed" value="replayed">
                    <label for="m-rs-replayed">🔁 Hergespeeld</label>

                    <input type="radio" name="replay_status" id="m-rs-will" value="will_replay">
                    <label for="m-rs-will">🔜 Wil herspelen</label>

                    <input type="radio" name="replay_status" id="m-rs-wont" value="wont_replay">
                    <label for="m-rs-wont">🚫 Niet herspelen</label>
                </div>
            </div>

            <div class="form-group">
                <label for="m-playtime">Speeltijd (uren)</label>
                <input type="number" name="playtime" id="m-playtime"
                       min="0" max="9999" placeholder="Bijv. 25" value="0">
            </div>

            <div class="form-group">
                <label for="m-review">Review / Notities</label>
                <textarea name="review" id="m-review" rows="3"
                          placeholder="Wat vond je ervan?"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">✅ Verplaats naar Ranking</button>
        </form>
    </div>
</div>

<script>
function openRateModal(gameId, gameTitle) {
    document.getElementById('modal-game-id').value = gameId;
    document.getElementById('modal-title').textContent = '🎮 ' + gameTitle + ' beoordelen';
    document.getElementById('rate-modal').classList.add('open');
}

function closeRateModal() {
    document.getElementById('rate-modal').classList.remove('open');
}

// Sluit modal bij klik buiten de box
document.getElementById('rate-modal').addEventListener('click', function(e) {
    if (e.target === this) closeRateModal();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
var wishlistGrid = document.getElementById('wishlist-grid');
if (wishlistGrid) {
    new Sortable(wishlistGrid, {
        animation: 150,
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        onEnd: function() {
            var ids = Array.from(wishlistGrid.querySelectorAll('[data-id]')).map(function(el) {
                return el.dataset.id;
            });
            fetch('save_order.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({type: 'wishlist', ids: ids})
            });
        }
    });
}
</script>

</body>
</html>

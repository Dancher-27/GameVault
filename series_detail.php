<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once 'connection.php';
require_once 'classes/Series.php';

$seriesObj = new Series($conn, (int)$_SESSION["user_id"]);
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: series.php'); exit(); }

$series = $seriesObj->getSeriesById($id);
if (!$series) { header('Location: series.php'); exit(); }

$alert     = '';
$alertType = '';

// Handle edits
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name !== '') {
            $seriesObj->updateSeries($id, $name, $desc);
            $series    = $seriesObj->getSeriesById($id);
            $alert     = 'Reeks bijgewerkt!';
            $alertType = 'success';
        }
    } elseif ($action === 'remove_game') {
        $gameId = (int)($_POST['game_id'] ?? 0);
        if ($gameId > 0) $seriesObj->removeGame($gameId);
        $alert     = 'Game verwijderd uit reeks.';
        $alertType = 'success';
    }
}

$games  = $seriesObj->getGamesInSeries($id);
$total  = count($games);
$played = count(array_filter($games, fn($g) => $g['status'] === 'played'));
$pct    = $total > 0 ? round($played / $total * 100) : 0;

// Find "next up": first non-played entry
$nextUp = null;
foreach ($games as $g) {
    if ($g['status'] !== 'played') { $nextUp = $g; break; }
}

$avgRating   = $total > 0 ? round(array_sum(array_column(array_filter($games, fn($g) => $g['rating'] > 0), 'rating')) / max(1, count(array_filter($games, fn($g) => $g['rating'] > 0))), 1) : null;
$totalHours  = array_sum(array_column($games, 'playtime'));

$statusLabels = [
    'played'   => ['label' => 'Gespeeld',    'color' => '#10b981'],
    'playing'  => ['label' => 'Bezig',       'color' => '#3b82f6'],
    'backlog'  => ['label' => 'Backlog',     'color' => '#f59e0b'],
    'wishlist' => ['label' => 'Verlanglijst','color' => '#8b5cf6'],
];

$current = 'series.php';
?>
<!DOCTYPE html>
<html lang="nl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — <?= htmlspecialchars($series['name']) ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .series-hero {
            background: linear-gradient(135deg, var(--navbar-bg) 0%, #3b0764 100%);
            border-radius: var(--radius);
            padding: 36px 32px;
            color: #fff;
            margin-bottom: 28px;
        }
        .series-hero-title { font-size: 2rem; font-weight: 900; margin-bottom: 6px; }
        .series-hero-desc { color: rgba(255,255,255,0.65); font-size: 0.95rem; margin-bottom: 20px; }
        .hero-stats { display: flex; gap: 28px; flex-wrap: wrap; margin-bottom: 20px; }
        .hero-stat-val { font-size: 2rem; font-weight: 900; color: #c4b5fd; display: block; }
        .hero-stat-lbl { font-size: 0.75rem; color: rgba(255,255,255,0.55); }
        .hero-progress-label { font-size: 0.8rem; color: rgba(255,255,255,0.7); margin-bottom: 6px; display: flex; justify-content: space-between; }
        .hero-progress-track { height: 10px; background: rgba(255,255,255,0.15); border-radius: 8px; overflow: hidden; max-width: 480px; }
        .hero-progress-fill { height: 100%; background: #c4b5fd; border-radius: 8px; }

        .next-up-banner {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--card-shadow);
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            border-left: 4px solid var(--purple);
        }
        .next-up-cover { width: 50px; height: 68px; object-fit: cover; object-position: center top; border-radius: 6px; flex-shrink: 0; }
        .next-up-cover-placeholder { width: 50px; height: 68px; background: var(--purple-light); border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
        .next-up-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: .06em; color: var(--purple); font-weight: 700; margin-bottom: 2px; }
        .next-up-title { font-weight: 800; font-size: 1rem; color: var(--text); }
        .next-up-meta { font-size: 0.8rem; color: var(--text-muted); }

        .games-list { display: flex; flex-direction: column; gap: 12px; }
        .game-row {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 18px;
            transition: box-shadow var(--transition);
        }
        .game-row:hover { box-shadow: var(--card-hover); }
        .game-row.is-next { border-left: 4px solid var(--purple); }
        .entry-num { font-size: 1.4rem; font-weight: 900; color: var(--purple-border); min-width: 36px; text-align: center; flex-shrink: 0; }
        .game-row-cover { width: 46px; height: 62px; object-fit: cover; object-position: center top; border-radius: 6px; flex-shrink: 0; }
        .game-row-cover-ph { width: 46px; height: 62px; background: var(--purple-light); border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        .game-row-info { flex: 1; min-width: 0; }
        .game-row-title { font-weight: 700; font-size: 0.95rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .game-row-meta { font-size: 0.78rem; color: var(--text-muted); margin-top: 2px; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; color: #fff; margin-left: 6px; }
        .game-row-rating { font-size: 1rem; font-weight: 800; color: var(--purple); min-width: 40px; text-align: right; flex-shrink: 0; }
        .game-row-actions { display: flex; gap: 8px; flex-shrink: 0; }

        .edit-panel { background: var(--white); border-radius: var(--radius); box-shadow: var(--card-shadow); padding: 20px 24px; margin-bottom: 24px; }
        .edit-panel h3 { font-size: 0.95rem; font-weight: 700; margin-bottom: 14px; }
        .edit-row { display: flex; gap: 12px; flex-wrap: wrap; }
        .edit-row .form-group { flex: 1; min-width: 160px; margin-bottom: 0; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">

    <?php if ($alert): ?>
    <div class="alert alert-<?= $alertType ?>" style="margin-bottom:16px;">
        <?= $alertType === 'success' ? '✅' : '⚠️' ?> <?= $alert ?>
    </div>
    <?php endif; ?>

    <!-- Hero -->
    <div class="series-hero">
        <div class="series-hero-title">🎮 <?= htmlspecialchars($series['name']) ?></div>
        <?php if (!empty($series['description'])): ?>
        <div class="series-hero-desc"><?= htmlspecialchars($series['description']) ?></div>
        <?php endif; ?>
        <div class="hero-stats">
            <div>
                <span class="hero-stat-val"><?= $total ?></span>
                <span class="hero-stat-lbl">Games</span>
            </div>
            <div>
                <span class="hero-stat-val"><?= $played ?></span>
                <span class="hero-stat-lbl">Gespeeld</span>
            </div>
            <?php if ($avgRating): ?>
            <div>
                <span class="hero-stat-val"><?= $avgRating ?></span>
                <span class="hero-stat-lbl">Gem. rating</span>
            </div>
            <?php endif; ?>
            <?php if ($totalHours > 0): ?>
            <div>
                <span class="hero-stat-val"><?= $totalHours ?>u</span>
                <span class="hero-stat-lbl">Speeltijd</span>
            </div>
            <?php endif; ?>
        </div>
        <div class="hero-progress-label">
            <span>Voortgang</span>
            <span><?= $played ?>/<?= $total ?> (<?= $pct ?>%)</span>
        </div>
        <div class="hero-progress-track">
            <div class="hero-progress-fill" style="width:<?= $pct ?>%"></div>
        </div>
    </div>

    <!-- Edit series info -->
    <div class="edit-panel">
        <h3>✏️ Reeks bewerken</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <div class="edit-row">
                <div class="form-group">
                    <label>Naam</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($series['name']) ?>" required>
                </div>
                <div class="form-group" style="flex:2;">
                    <label>Beschrijving</label>
                    <input type="text" name="description" value="<?= htmlspecialchars($series['description'] ?? '') ?>">
                </div>
                <div class="form-group" style="flex:0;align-self:flex-end;">
                    <button type="submit" class="btn btn-primary" style="width:auto;white-space:nowrap;">💾 Opslaan</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Next up -->
    <?php if ($nextUp): ?>
    <?php
        $nuImg    = 'uploads/' . $nextUp['cover_image'];
        $nuHasImg = !empty($nextUp['cover_image']) && file_exists(__DIR__ . '/' . $nuImg);
    ?>
    <div class="next-up-banner">
        <?php if ($nuHasImg): ?>
            <img src="<?= htmlspecialchars($nuImg) ?>" class="next-up-cover" alt="">
        <?php else: ?>
            <div class="next-up-cover-placeholder">🎮</div>
        <?php endif; ?>
        <div>
            <div class="next-up-label">⏭️ Volgende in de reeks</div>
            <div class="next-up-title"><?= htmlspecialchars($nextUp['title']) ?></div>
            <div class="next-up-meta">
                <?= htmlspecialchars($nextUp['platform'] ?? '') ?>
                <?php if ($nextUp['release_year']): ?> · <?= $nextUp['release_year'] ?><?php endif; ?>
                · Deel <?= (int)$nextUp['series_order'] ?>
            </div>
        </div>
        <a href="game_detail.php?id=<?= (int)$nextUp['id'] ?>" class="btn btn-primary" style="width:auto;margin-left:auto;">🎮 Bekijken</a>
    </div>
    <?php endif; ?>

    <!-- Games list -->
    <div class="section-block">
        <div class="section-header">
            <h2>📋 Games in deze reeks</h2>
            <span class="section-count"><?= $total ?></span>
        </div>

        <?php if (empty($games)): ?>
        <div class="empty-state" style="padding:40px 0;">
            <span class="empty-state-icon">🎮</span>
            <p>Nog geen games toegewezen.<br>Bewerk een game en kies deze reeks.</p>
            <a href="edit_game.php" class="btn btn-primary" style="width:auto;">✏️ Game bewerken</a>
        </div>
        <?php else: ?>
        <div class="games-list" style="margin-top:16px;">
            <?php foreach ($games as $g):
                $img    = 'uploads/' . $g['cover_image'];
                $hasImg = !empty($g['cover_image']) && file_exists(__DIR__ . '/' . $img);
                $st     = $statusLabels[$g['status']] ?? ['label' => $g['status'], 'color' => '#6b7280'];
                $isNext = $nextUp && $g['id'] === $nextUp['id'];
            ?>
            <div class="game-row <?= $isNext ? 'is-next' : '' ?>">
                <div class="entry-num"><?= (int)$g['series_order'] ?: '—' ?></div>
                <?php if ($hasImg): ?>
                    <img src="<?= htmlspecialchars($img) ?>" class="game-row-cover" alt="" loading="lazy">
                <?php else: ?>
                    <div class="game-row-cover-ph">🎮</div>
                <?php endif; ?>
                <div class="game-row-info">
                    <div class="game-row-title">
                        <?= htmlspecialchars($g['title']) ?>
                        <span class="status-badge" style="background:<?= $st['color'] ?>;"><?= $st['label'] ?></span>
                        <?php if ($isNext): ?><span class="status-badge" style="background:var(--purple);">⏭️ Volgende</span><?php endif; ?>
                    </div>
                    <div class="game-row-meta">
                        <?= htmlspecialchars($g['platform'] ?? '') ?>
                        <?php if ($g['release_year']): ?> · <?= $g['release_year'] ?><?php endif; ?>
                        <?php if ($g['playtime'] > 0): ?> · <?= (int)$g['playtime'] ?>u<?php endif; ?>
                    </div>
                </div>
                <div class="game-row-rating">
                    <?= $g['rating'] > 0 ? '⭐ ' . number_format((float)$g['rating'], 1) : '—' ?>
                </div>
                <div class="game-row-actions">
                    <a href="game_detail.php?id=<?= (int)$g['id'] ?>" class="btn btn-secondary" style="width:auto;font-size:0.8rem;padding:6px 12px;">🔍</a>
                    <a href="edit_game.php?id=<?= (int)$g['id'] ?>" class="btn btn-secondary" style="width:auto;font-size:0.8rem;padding:6px 12px;">✏️</a>
                    <form method="POST" onsubmit="return confirm('Game uit reeks verwijderen?');" style="display:inline;">
                        <input type="hidden" name="action" value="remove_game">
                        <input type="hidden" name="game_id" value="<?= (int)$g['id'] ?>">
                        <button type="submit" class="btn btn-danger" style="width:auto;font-size:0.8rem;padding:6px 10px;">✕</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div style="margin-top:20px;">
        <a href="series.php" class="btn btn-secondary" style="width:auto;">← Alle reeksen</a>
    </div>

</div>
<script>
(function(){var t=localStorage.getItem('gv_theme')||'light';document.documentElement.setAttribute('data-theme',t);var b=document.getElementById('darkToggle');if(b)b.textContent=t==='dark'?'☀️ Light':'🌙 Dark';})();
</script>
</body>
</html>

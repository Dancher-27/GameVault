<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once 'connection.php';
require_once 'classes/Series.php';
require_once 'classes/Game.php';

$seriesObj = new Series($conn, (int)$_SESSION["user_id"]);
$gameObj   = new Game($conn, (int)$_SESSION["user_id"]);
$alert     = '';
$alertType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name !== '') {
            $seriesObj->createSeries($name, $desc);
            $alert     = "Reeks \"" . htmlspecialchars($name) . "\" aangemaakt!";
            $alertType = 'success';
        } else {
            $alert     = 'Naam is verplicht.';
            $alertType = 'error';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['series_id'] ?? 0);
        if ($id > 0) {
            $s = $seriesObj->getSeriesById($id);
            $seriesObj->deleteSeries($id);
            $alert     = "Reeks verwijderd.";
            $alertType = 'success';
        }
    }
}

$allSeries = $seriesObj->getAllSeries();
$current   = 'series.php';
?>
<!DOCTYPE html>
<html lang="nl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Reeksen</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .series-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }
        .series-card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: box-shadow var(--transition), transform var(--transition);
            display: flex;
            flex-direction: column;
        }
        .series-card:hover { box-shadow: var(--card-hover); transform: translateY(-2px); }
        .series-covers {
            display: flex;
            height: 110px;
            overflow: hidden;
            background: var(--purple-light);
        }
        .series-covers img {
            flex: 1;
            object-fit: cover;
            object-position: center top;
            min-width: 0;
        }
        .series-covers .cover-placeholder {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            background: var(--purple-light);
            color: var(--purple-border);
        }
        .series-body { padding: 16px 18px; flex: 1; }
        .series-name { font-size: 1.05rem; font-weight: 800; color: var(--text); margin-bottom: 4px; }
        .series-desc { font-size: 0.82rem; color: var(--text-muted); margin-bottom: 12px; line-height: 1.4; }
        .series-meta { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 12px; }
        .series-meta-item { font-size: 0.8rem; color: var(--text-muted); }
        .series-meta-item strong { color: var(--purple); font-weight: 700; }
        .progress-bar-wrap { margin-bottom: 12px; }
        .progress-label { font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px; display: flex; justify-content: space-between; }
        .progress-track { height: 7px; background: var(--border); border-radius: 6px; overflow: hidden; }
        .progress-fill { height: 100%; background: var(--purple); border-radius: 6px; transition: width 0.4s; }
        .series-footer { display: flex; gap: 8px; padding: 12px 18px; border-top: 1px solid var(--border); }
        .series-footer a { flex: 1; }

        /* Create form */
        .create-panel {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--card-shadow);
            padding: 24px 28px;
            margin-bottom: 28px;
        }
        .create-panel h3 { font-size: 1rem; font-weight: 700; margin-bottom: 16px; color: var(--text); }
        .create-row { display: flex; gap: 12px; flex-wrap: wrap; }
        .create-row .form-group { flex: 1; min-width: 180px; margin-bottom: 0; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">

    <div class="section-header" style="margin-bottom:8px;">
        <h1 class="page-title" style="margin:0;">🎮 Reeksen & Franchises</h1>
        <span class="section-count"><?= count($allSeries) ?></span>
    </div>
    <p class="page-subtitle" style="margin-bottom:24px;">Groepeer je games per reeks en volg je voortgang per franchise.</p>

    <?php if ($alert): ?>
    <div class="alert alert-<?= $alertType ?>" style="margin-bottom:20px;">
        <?= $alertType === 'success' ? '✅' : '⚠️' ?> <?= $alert ?>
    </div>
    <?php endif; ?>

    <!-- Create new series -->
    <div class="create-panel">
        <h3>➕ Nieuwe reeks aanmaken</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="create-row">
                <div class="form-group">
                    <label for="name">Naam reeks <span class="req">*</span></label>
                    <input type="text" name="name" id="name" placeholder="bijv. The Witcher, Dark Souls..." required>
                </div>
                <div class="form-group" style="flex:2;">
                    <label for="description">Beschrijving (optioneel)</label>
                    <input type="text" name="description" id="description" placeholder="Korte omschrijving...">
                </div>
                <div class="form-group" style="flex:0; align-self:flex-end;">
                    <button type="submit" class="btn btn-primary" style="width:auto; white-space:nowrap;">✅ Aanmaken</button>
                </div>
            </div>
        </form>
    </div>

    <?php if (empty($allSeries)): ?>
    <div class="empty-state">
        <span class="empty-state-icon">🎮</span>
        <p>Nog geen reeksen aangemaakt.<br>Maak een reeks aan en wijs games toe via de bewerkpagina.</p>
    </div>
    <?php else: ?>
    <div class="series-grid">
        <?php foreach ($allSeries as $s):
            $games     = $seriesObj->getGamesInSeries((int)$s['id']);
            $total     = (int)$s['game_count'];
            $played    = (int)$s['played_count'];
            $pct       = $total > 0 ? round($played / $total * 100) : 0;
            $covers    = array_slice($games, 0, 3);
        ?>
        <div class="series-card">
            <div class="series-covers">
                <?php if (empty($covers)): ?>
                    <div class="cover-placeholder">🎮</div>
                <?php else:
                    foreach ($covers as $cg):
                        $img = 'uploads/' . $cg['cover_image'];
                        $hasImg = !empty($cg['cover_image']) && file_exists(__DIR__ . '/' . $img);
                    ?>
                    <?php if ($hasImg): ?>
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($cg['title']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="cover-placeholder">🎮</div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="series-body">
                <div class="series-name"><?= htmlspecialchars($s['name']) ?></div>
                <?php if (!empty($s['description'])): ?>
                <div class="series-desc"><?= htmlspecialchars($s['description']) ?></div>
                <?php endif; ?>
                <div class="series-meta">
                    <div class="series-meta-item">🎮 <strong><?= $total ?></strong> games</div>
                    <?php if ($s['avg_rating']): ?>
                    <div class="series-meta-item">⭐ <strong><?= $s['avg_rating'] ?></strong>/10</div>
                    <?php endif; ?>
                    <?php if ($s['total_hours']): ?>
                    <div class="series-meta-item">⏱️ <strong><?= (int)$s['total_hours'] ?></strong>u</div>
                    <?php endif; ?>
                </div>
                <div class="progress-bar-wrap">
                    <div class="progress-label">
                        <span>Voortgang</span>
                        <span><?= $played ?>/<?= $total ?> gespeeld (<?= $pct ?>%)</span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="series-footer">
                <a href="series_detail.php?id=<?= (int)$s['id'] ?>" class="btn btn-primary" style="text-align:center;font-size:0.85rem;">🔍 Bekijken</a>
                <form method="POST" onsubmit="return confirm('Reeks verwijderen? Games blijven bewaard.');" style="flex:0;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="series_id" value="<?= (int)$s['id'] ?>">
                    <button type="submit" class="btn btn-danger" style="width:auto;">🗑️</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
<script>
(function(){var t=localStorage.getItem('gv_theme')||'light';document.documentElement.setAttribute('data-theme',t);var b=document.getElementById('darkToggle');if(b)b.textContent=t==='dark'?'☀️ Light':'🌙 Dark';})();
</script>
</body>
</html>

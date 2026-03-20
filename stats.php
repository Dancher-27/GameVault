<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once 'connection.php';
require_once 'classes/Game.php';

$gameObj      = new Game($conn, (int)$_SESSION["user_id"]);
$stats        = $gameObj->getStats();
$byGenre      = $gameObj->getStatsByGenre();
$byPlatform   = $gameObj->getStatsByPlatform();
$bestThisYear = $gameObj->getBestGameOfYear((int)date('Y'));
$topGenre     = $gameObj->getMostPlayedGenre();
$year         = (int)date('Y');

$maxGenreRating = !empty($byGenre) ? max(array_column($byGenre, 'avg_rating')) : 10;
$maxPlatCount   = !empty($byPlatform) ? max(array_column($byPlatform, 'count')) : 1;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Stats <?= $year ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .wrapped-hero {
            background: linear-gradient(135deg, var(--navbar-bg) 0%, #3b0764 100%);
            border-radius: var(--radius);
            padding: 40px 36px;
            color: #fff;
            margin-bottom: 32px;
            display: flex;
            gap: 32px;
            flex-wrap: wrap;
            align-items: center;
        }
        .wrapped-title { font-size: 2rem; font-weight: 900; margin-bottom: 4px; }
        .wrapped-sub { color: rgba(255,255,255,0.65); font-size:0.95rem; }
        .wrapped-stats { display: flex; gap: 28px; flex-wrap: wrap; margin-top: 24px; }
        .wrapped-stat { text-align: center; }
        .wrapped-stat-val { font-size: 2.2rem; font-weight: 900; color: #c4b5fd; display: block; }
        .wrapped-stat-lbl { font-size: 0.78rem; color: rgba(255,255,255,0.6); }
        .wrapped-best { background: rgba(255,255,255,0.08); border-radius: var(--radius); padding: 18px 22px; display: flex; gap: 16px; align-items: center; }
        .wrapped-best-cover { width: 60px; height: 80px; object-fit: cover; border-radius: 8px; }
        .wrapped-best-info strong { font-size: 1.05rem; display: block; margin-bottom: 4px; }
        .wrapped-best-info span { font-size: 0.82rem; color: rgba(255,255,255,0.65); }

        .stats-table { width: 100%; border-collapse: collapse; }
        .stats-table th { text-align: left; padding: 8px 12px; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted); border-bottom: 2px solid var(--border); }
        .stats-table td { padding: 12px 12px; border-bottom: 1px solid var(--border); font-size: 0.88rem; color: var(--text); vertical-align: middle; }
        .stats-table tr:last-child td { border-bottom: none; }
        .stats-table tr:hover td { background: var(--purple-light); }

        .bar-cell { display: flex; align-items: center; gap: 10px; }
        .bar-track { flex: 1; height: 8px; background: var(--border); border-radius: 6px; overflow: hidden; min-width: 60px; }
        .bar-fill-purple { height: 100%; background: var(--purple); border-radius: 6px; }
        .bar-fill-blue   { height: 100%; background: var(--blue);   border-radius: 6px; }
        .bar-fill-gold   { height: 100%; background: var(--gold);   border-radius: 6px; }
        .stat-num { font-weight: 700; color: var(--purple); min-width: 32px; text-align: right; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-wrapper">

    <!-- GAMING WRAPPED HERO -->
    <div class="wrapped-hero">
        <div style="flex:1;">
            <div class="wrapped-title">🎮 Gaming <?= $year ?></div>
            <div class="wrapped-sub">Jouw jaar in games — <?= htmlspecialchars($_SESSION['username']) ?></div>
            <div class="wrapped-stats">
                <div class="wrapped-stat">
                    <span class="wrapped-stat-val"><?= (int)($stats['played'] ?? 0) ?></span>
                    <span class="wrapped-stat-lbl">Games gespeeld</span>
                </div>
                <div class="wrapped-stat">
                    <span class="wrapped-stat-val"><?= (int)($stats['total_hours'] ?? 0) ?>u</span>
                    <span class="wrapped-stat-lbl">Totale speeltijd</span>
                </div>
                <div class="wrapped-stat">
                    <span class="wrapped-stat-val"><?= $stats['avg_rating'] ?? '—' ?></span>
                    <span class="wrapped-stat-lbl">Gem. rating</span>
                </div>
                <div class="wrapped-stat">
                    <span class="wrapped-stat-val"><?= htmlspecialchars($topGenre) ?></span>
                    <span class="wrapped-stat-lbl">Meest gespeeld genre</span>
                </div>
            </div>
        </div>

        <?php if ($bestThisYear): ?>
        <?php $bImg = 'uploads/' . $bestThisYear['cover_image'];
              $hasBImg = !empty($bestThisYear['cover_image']) && file_exists(__DIR__ . '/' . $bImg); ?>
        <div class="wrapped-best">
            <?php if ($hasBImg): ?>
            <img src="<?= htmlspecialchars($bImg) ?>" class="wrapped-best-cover" loading="lazy">
            <?php endif; ?>
            <div class="wrapped-best-info">
                <span style="font-size:0.72rem;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:.05em;">🏆 Beste game van <?= $year ?></span>
                <strong><?= htmlspecialchars($bestThisYear['title']) ?></strong>
                <span>⭐ <?= number_format($bestThisYear['rating'],1) ?>/10</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- GENRE VERGELIJKING -->
    <?php if (!empty($byGenre)): ?>
    <div class="section-block">
        <div class="section-header">
            <h2>🎯 Genres vergelijken</h2>
            <span class="section-count"><?= count($byGenre) ?></span>
        </div>
        <div style="overflow-x:auto;">
        <table class="stats-table">
            <thead>
                <tr>
                    <th>Genre</th>
                    <th>Games</th>
                    <th style="min-width:180px;">Gem. rating</th>
                    <th style="min-width:180px;">Gem. fun</th>
                    <th style="min-width:180px;">Gem. replay</th>
                    <th>Uren</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($byGenre as $g):
                $rPct  = $maxGenreRating > 0 ? ((float)$g['avg_rating']  / 10 * 100) : 0;
                $ePct  = $maxGenreRating > 0 ? ((float)$g['avg_enjoy']   / 10 * 100) : 0;
                $rpPct = $maxGenreRating > 0 ? ((float)$g['avg_replay']  / 10 * 100) : 0;
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($g['genre']) ?></strong></td>
                <td><?= (int)$g['count'] ?></td>
                <td>
                    <div class="bar-cell">
                        <div class="bar-track"><div class="bar-fill-purple" style="width:<?= $rPct ?>%"></div></div>
                        <span class="stat-num"><?= $g['avg_rating'] ?? '—' ?></span>
                    </div>
                </td>
                <td>
                    <div class="bar-cell">
                        <div class="bar-track"><div class="bar-fill-blue" style="width:<?= $ePct ?>%"></div></div>
                        <span class="stat-num"><?= $g['avg_enjoy'] ?? '—' ?></span>
                    </div>
                </td>
                <td>
                    <div class="bar-cell">
                        <div class="bar-track"><div class="bar-fill-purple" style="width:<?= $rpPct ?>%"></div></div>
                        <span class="stat-num"><?= $g['avg_replay'] ?? '—' ?></span>
                    </div>
                </td>
                <td><?= (int)$g['total_hours'] ?>u</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- PLATFORM VERGELIJKING -->
    <?php if (!empty($byPlatform)): ?>
    <div class="section-block">
        <div class="section-header">
            <h2>🖥️ Platforms vergelijken</h2>
            <span class="section-count"><?= count($byPlatform) ?></span>
        </div>
        <div style="overflow-x:auto;">
        <table class="stats-table">
            <thead>
                <tr>
                    <th>Platform</th>
                    <th style="min-width:180px;">Aantal games</th>
                    <th style="min-width:180px;">Gem. rating</th>
                    <th>Totaal uren</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($byPlatform as $p):
                $cPct = $maxPlatCount > 0 ? ((int)$p['count'] / $maxPlatCount * 100) : 0;
                $rPct = (float)$p['avg_rating'] / 10 * 100;
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($p['platform']) ?></strong></td>
                <td>
                    <div class="bar-cell">
                        <div class="bar-track"><div class="bar-fill-gold" style="width:<?= $cPct ?>%"></div></div>
                        <span class="stat-num"><?= (int)$p['count'] ?></span>
                    </div>
                </td>
                <td>
                    <div class="bar-cell">
                        <div class="bar-track"><div class="bar-fill-purple" style="width:<?= $rPct ?>%"></div></div>
                        <span class="stat-num"><?= $p['avg_rating'] ?? '—' ?></span>
                    </div>
                </td>
                <td><?= (int)$p['total_hours'] ?>u</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

</div>
</body>
</html>

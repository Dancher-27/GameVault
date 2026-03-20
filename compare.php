<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once 'connection.php';
require_once 'classes/Game.php';

$gameObj = new Game($conn, (int)$_SESSION["user_id"]);
$all     = $gameObj->getAllGames();

$id1 = (int)($_GET['a'] ?? 0);
$id2 = (int)($_GET['b'] ?? 0);

$gameA = $id1 ? $gameObj->getGameById($id1) : null;
$gameB = $id2 ? $gameObj->getGameById($id2) : null;

function coverUrl($game): string {
    if (!$game || empty($game['cover_image'])) return '';
    $p = 'uploads/' . $game['cover_image'];
    return file_exists(__DIR__ . '/' . $p) ? $p : '';
}

function cmpClass(float $a, float $b): array {
    if ($a > $b) return ['win','lose'];
    if ($b > $a) return ['lose','win'];
    return ['tie','tie'];
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Vergelijken</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* ---- SELECT FORM ---- */
        .cmp-select-bar {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--card-shadow);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }
        .cmp-select-bar select { flex:1; min-width:200px; }
        .cmp-vs-badge {
            background: linear-gradient(135deg, var(--purple), var(--blue));
            color: #fff;
            font-weight: 900;
            font-size: 1rem;
            border-radius: 50%;
            width: 44px; height: 44px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(124,58,237,0.4);
        }

        /* ---- HERO SPLIT ---- */
        .cmp-hero {
            display: grid;
            grid-template-columns: 1fr 120px 1fr;
            min-height: 420px;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin-bottom: 28px;
        }
        .cmp-side {
            position: relative;
            overflow: hidden;
        }
        .cmp-side-bg {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center top;
            filter: blur(2px) brightness(0.55);
            transform: scale(1.05);
        }
        .cmp-side-content {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 32px 20px 28px;
            height: 100%;
        }
        .cmp-poster {
            width: 160px;
            aspect-ratio: 2/3;
            object-fit: cover;
            object-position: center top;
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.6);
            margin-bottom: 20px;
        }
        .cmp-poster-placeholder {
            width: 160px;
            aspect-ratio: 2/3;
            border-radius: 12px;
            background: rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: center;
            font-size: 3rem;
            margin-bottom: 20px;
        }
        .cmp-side-title {
            font-size: 1.3rem;
            font-weight: 900;
            color: #fff;
            text-align: center;
            text-shadow: 0 2px 8px rgba(0,0,0,0.6);
            margin-bottom: 6px;
        }
        .cmp-side-meta {
            font-size: 0.78rem;
            color: rgba(255,255,255,0.65);
            text-align: center;
            margin-bottom: 16px;
        }
        .cmp-side-score {
            font-size: 2.8rem;
            font-weight: 900;
            color: #fff;
            text-shadow: 0 2px 12px rgba(0,0,0,0.5);
            line-height: 1;
        }
        .cmp-side-score-lbl {
            font-size: 0.72rem;
            color: rgba(255,255,255,0.55);
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-top: 4px;
        }
        .cmp-side.side-a { background: #1a0f3a; }
        .cmp-side.side-b { background: #0c1a3a; }

        .cmp-winner-side::after {
            content: '🏆';
            position: absolute;
            top: 12px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 1.8rem;
            filter: drop-shadow(0 2px 6px rgba(0,0,0,0.5));
            z-index: 3;
        }

        /* ---- MIDDLE DIVIDER ---- */
        .cmp-middle {
            background: linear-gradient(180deg, var(--navbar-bg) 0%, #2d1b69 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            padding: 24px 8px;
        }
        .cmp-middle-vs {
            font-size: 1.8rem;
            font-weight: 900;
            color: rgba(255,255,255,0.9);
            letter-spacing: .05em;
        }
        .cmp-winner-pill {
            background: var(--gold);
            color: #1a0f3a;
            font-size: 0.72rem;
            font-weight: 800;
            border-radius: 20px;
            padding: 4px 12px;
            text-align: center;
            white-space: nowrap;
            max-width: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .cmp-tie-pill {
            background: rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.7);
            font-size: 0.7rem;
            font-weight: 700;
            border-radius: 20px;
            padding: 4px 10px;
            text-align: center;
        }
        .cmp-winner-banner {
            background: linear-gradient(90deg, var(--purple) 0%, var(--blue) 100%);
            border-radius: var(--radius);
            padding: 14px 24px;
            text-align: center;
            color: #fff;
            font-size: 1rem;
            font-weight: 800;
            margin-bottom: 16px;
            box-shadow: 0 4px 16px rgba(124,58,237,0.3);
        }

        /* ---- STAT ROWS ---- */
        .cmp-stats {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        .cmp-stat-row {
            display: grid;
            grid-template-columns: 1fr 100px 1fr;
            align-items: center;
            padding: 14px 24px;
            border-bottom: 1px solid var(--border);
            gap: 16px;
        }
        .cmp-stat-row:last-child { border-bottom: none; }
        .cmp-stat-label {
            text-align: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .cmp-stat-bar-a { display: flex; align-items: center; gap: 10px; justify-content: flex-end; }
        .cmp-stat-bar-b { display: flex; align-items: center; gap: 10px; }
        .cmp-stat-num { font-size: 1.05rem; font-weight: 800; width: 38px; text-align: center; flex-shrink: 0; }
        .cmp-stat-track { flex: 1; height: 10px; background: var(--border); border-radius: 6px; overflow: hidden; min-width: 80px; }
        .cmp-stat-fill { height: 100%; border-radius: 6px; }

        .stat-win .cmp-stat-num { color: var(--purple); }
        .stat-win .cmp-stat-fill { background: var(--purple); }
        .stat-lose .cmp-stat-num { color: var(--text-light); }
        .stat-lose .cmp-stat-fill { background: var(--border); }
        .stat-tie .cmp-stat-num { color: var(--text-muted); }
        .stat-tie .cmp-stat-fill { background: var(--blue); }

        .cmp-stat-row-fun .cmp-stat-fill { background: var(--blue) !important; }
        .cmp-stat-row-fun.stat-lose .cmp-stat-fill { background: var(--border) !important; }
        .cmp-stat-row-time .cmp-stat-fill { background: var(--gold) !important; }
        .cmp-stat-row-time.stat-lose .cmp-stat-fill { background: var(--border) !important; }

        .cmp-stat-row-a-wrap { display: contents; }
        .cmp-stat-row-b-wrap { display: contents; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-wrapper">
    <h1 class="page-title">⚔️ Games vergelijken</h1>
    <p class="page-subtitle">Zet twee games naast elkaar en vergelijk hun scores.</p>

    <!-- SELECT BAR -->
    <form method="GET" class="cmp-select-bar">
        <div class="form-group" style="flex:1;margin-bottom:0;">
            <label class="form-label">Game A</label>
            <select name="a" class="form-input" onchange="this.form.submit()">
                <option value="">— Kies een game —</option>
                <?php foreach ($all as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $id1 === (int)$g['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($g['title']) ?> (<?= number_format($g['rating'],1) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="cmp-vs-badge">VS</div>
        <div class="form-group" style="flex:1;margin-bottom:0;">
            <label class="form-label">Game B</label>
            <select name="b" class="form-input" onchange="this.form.submit()">
                <option value="">— Kies een game —</option>
                <?php foreach ($all as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $id2 === (int)$g['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($g['title']) ?> (<?= number_format($g['rating'],1) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if (!$gameA || !$gameB): ?>
    <div class="empty-state">
        <span class="empty-state-icon">⚔️</span>
        <p>Kies twee games hierboven om ze te vergelijken.</p>
    </div>
    <?php else:
        $totalA = ($gameA['rating'] + $gameA['enjoyability'] + $gameA['replayability']) / 3;
        $totalB = ($gameB['rating'] + $gameB['enjoyability'] + $gameB['replayability']) / 3;
        $winnerTitle = $totalA > $totalB ? $gameA['title'] : ($totalB > $totalA ? $gameB['title'] : null);
        $aWins = $totalA > $totalB;
        $imgA  = coverUrl($gameA);
        $imgB  = coverUrl($gameB);
    ?>

    <!-- HERO -->
    <div class="cmp-hero">
        <!-- SIDE A -->
        <div class="cmp-side side-a <?= $aWins ? 'cmp-winner-side' : '' ?>">
            <?php if ($imgA): ?>
            <div class="cmp-side-bg" style="background-image:url('<?= htmlspecialchars($imgA) ?>')"></div>
            <?php endif; ?>
            <div class="cmp-side-content">
                <?php if ($imgA): ?>
                <img src="<?= htmlspecialchars($imgA) ?>" class="cmp-poster" loading="lazy">
                <?php else: ?>
                <div class="cmp-poster-placeholder">🎮</div>
                <?php endif; ?>
                <div class="cmp-side-title"><?= htmlspecialchars($gameA['title']) ?></div>
                <div class="cmp-side-meta"><?= htmlspecialchars($gameA['platform'] ?? '') ?> · <?= htmlspecialchars($gameA['genre'] ?? '') ?></div>
                <div class="cmp-side-score"><?= number_format($gameA['rating'],1) ?></div>
                <div class="cmp-side-score-lbl">Rating</div>
            </div>
        </div>

        <!-- MIDDLE -->
        <div class="cmp-middle">
            <div class="cmp-middle-vs">VS</div>
        </div>

        <!-- SIDE B -->
        <div class="cmp-side side-b <?= !$aWins && $winnerTitle ? 'cmp-winner-side' : '' ?>">
            <?php if ($imgB): ?>
            <div class="cmp-side-bg" style="background-image:url('<?= htmlspecialchars($imgB) ?>')"></div>
            <?php endif; ?>
            <div class="cmp-side-content">
                <?php if ($imgB): ?>
                <img src="<?= htmlspecialchars($imgB) ?>" class="cmp-poster" loading="lazy">
                <?php else: ?>
                <div class="cmp-poster-placeholder">🎮</div>
                <?php endif; ?>
                <div class="cmp-side-title"><?= htmlspecialchars($gameB['title']) ?></div>
                <div class="cmp-side-meta"><?= htmlspecialchars($gameB['platform'] ?? '') ?> · <?= htmlspecialchars($gameB['genre'] ?? '') ?></div>
                <div class="cmp-side-score"><?= number_format($gameB['rating'],1) ?></div>
                <div class="cmp-side-score-lbl">Rating</div>
            </div>
        </div>
    </div>

    <!-- WINNER BANNER -->
    <?php if ($winnerTitle): ?>
    <div class="cmp-winner-banner">🏆 <?= htmlspecialchars($winnerTitle) ?> wint!</div>
    <?php else: ?>
    <div class="cmp-winner-banner" style="background:linear-gradient(90deg,#64748b,#94a3b8);">🤝 Gelijkspel!</div>
    <?php endif; ?>

    <!-- STAT ROWS -->
    <div class="cmp-stats">
        <?php
        $rows = [
            ['label'=>'⭐ Rating',      'a'=>(float)$gameA['rating'],       'b'=>(float)$gameB['rating'],       'max'=>10,  'fmt'=>'%.1f',  'class'=>''],
            ['label'=>'😄 Fun',          'a'=>(float)$gameA['enjoyability'], 'b'=>(float)$gameB['enjoyability'], 'max'=>10,  'fmt'=>'%.1f',  'class'=>'cmp-stat-row-fun'],
            ['label'=>'🔁 Replayability','a'=>(float)$gameA['replayability'],'b'=>(float)$gameB['replayability'],'max'=>10, 'fmt'=>'%.1f',  'class'=>''],
            ['label'=>'⏱️ Speeltijd',    'a'=>(float)$gameA['playtime'],     'b'=>(float)$gameB['playtime'],     'max'=>max((float)$gameA['playtime'],(float)$gameB['playtime'],1), 'fmt'=>'%du', 'class'=>'cmp-stat-row-time'],
        ];
        foreach ($rows as $row):
            [$clA,$clB] = cmpClass($row['a'], $row['b']);
            $pctA = $row['max'] > 0 ? min(100, $row['a'] / $row['max'] * 100) : 0;
            $pctB = $row['max'] > 0 ? min(100, $row['b'] / $row['max'] * 100) : 0;
            $valA = ($row['fmt'] === '%du') ? (int)$row['a'].'u' : number_format($row['a'],1);
            $valB = ($row['fmt'] === '%du') ? (int)$row['b'].'u' : number_format($row['b'],1);
        ?>
        <div class="cmp-stat-row">
            <!-- BAR A (right-aligned) -->
            <div class="cmp-stat-bar-a stat-<?= $clA ?> <?= $row['class'] ?>">
                <span class="cmp-stat-num"><?= $valA ?></span>
                <div class="cmp-stat-track" style="transform:scaleX(-1);">
                    <div class="cmp-stat-fill" style="width:<?= $pctA ?>%"></div>
                </div>
            </div>

            <div class="cmp-stat-label"><?= $row['label'] ?></div>

            <!-- BAR B (left-aligned) -->
            <div class="cmp-stat-bar-b stat-<?= $clB ?> <?= $row['class'] ?>">
                <div class="cmp-stat-track">
                    <div class="cmp-stat-fill" style="width:<?= $pctB ?>%"></div>
                </div>
                <span class="cmp-stat-num"><?= $valB ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>

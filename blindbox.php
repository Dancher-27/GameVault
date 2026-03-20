<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once 'connection.php';
require_once 'classes/Game.php';

$gameObj = new Game($conn, (int)$_SESSION["user_id"]);
$userId  = $_SESSION['user_id'];

// Pool stored per user in a JSON file
$poolFile  = __DIR__ . '/blindbox_pool_' . $userId . '.json';
$activeFile = __DIR__ . '/blindbox_active_' . $userId . '.json';

function loadPool(string $file): array {
    return file_exists($file) ? (json_decode(file_get_contents($file), true) ?? []) : [];
}
function savePool(string $file, array $data): void {
    file_put_contents($file, json_encode($data));
}

$action = $_POST['action'] ?? '';

// Toggle game in/out of pool
if ($action === 'toggle') {
    $gid  = (int)$_POST['game_id'];
    $pool = loadPool($poolFile);
    if (in_array($gid, $pool)) {
        $pool = array_values(array_filter($pool, fn($id) => $id !== $gid));
    } else {
        $pool[] = $gid;
    }
    savePool($poolFile, $pool);
    header('Location: blindbox.php');
    exit();
}

// Draw a random game from pool
if ($action === 'draw') {
    $pool = loadPool($poolFile);
    if (!empty($pool)) {
        $drawn = $pool[array_rand($pool)];
        $deadline = date('Y-m-d H:i:s', strtotime('+7 days'));
        savePool($activeFile, ['game_id' => $drawn, 'drawn_at' => date('Y-m-d H:i:s'), 'deadline' => $deadline]);
    }
    header('Location: blindbox.php');
    exit();
}

// Clear active game
if ($action === 'clear_active') {
    if (file_exists($activeFile)) unlink($activeFile);
    header('Location: blindbox.php');
    exit();
}

// Load data
$pool   = loadPool($poolFile);
$active = loadPool($activeFile);
$activeGame = null;

if (!empty($active['game_id'])) {
    $activeGame = $gameObj->getGameById((int)$active['game_id']);
}

// All games for the pool picker (non-wishlist)
$allGames = array_merge(
    $gameObj->getGamesByStatus('played'),
    $gameObj->getGamesByStatus('playing'),
    $gameObj->getGamesByStatus('backlog')
);

// Days left for active game
$daysLeft = null;
if ($active && isset($active['deadline'])) {
    $diff = (new DateTime($active['deadline']))->diff(new DateTime());
    $daysLeft = max(0, 7 - $diff->days);
}

$current = 'blindbox.php';
?>
<!DOCTYPE html>
<html lang="nl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Blind Box</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .bb-hero {
            background: linear-gradient(135deg, #0f172a 0%, #4c1d95 100%);
            border-radius: var(--radius); padding: 36px 40px;
            color: #fff; margin-bottom: 32px;
        }
        .bb-hero h1 { font-size: 2rem; margin: 0 0 6px; }
        .bb-hero p  { opacity: .7; margin: 0; }

        /* Active game card */
        .bb-active {
            border-radius: var(--radius); overflow: hidden;
            background: var(--white); box-shadow: 0 8px 40px rgba(124,58,237,0.2);
            margin-bottom: 32px; display: grid;
            grid-template-columns: 180px 1fr; min-height: 270px;
        }
        .bb-active-img {
            width: 180px; object-fit: cover; object-position: center top; display: block;
        }
        .bb-active-placeholder {
            width: 180px; background: var(--purple-light);
            display: flex; align-items: center; justify-content: center; font-size: 4rem;
        }
        .bb-active-body { padding: 28px 32px; display: flex; flex-direction: column; justify-content: center; }
        .bb-active-label {
            font-size: 0.75rem; font-weight: 700; letter-spacing: 1px;
            text-transform: uppercase; color: var(--purple); margin-bottom: 8px;
        }
        .bb-active-title {
            font-size: 1.6rem; font-weight: 800; color: var(--text);
            margin-bottom: 6px; line-height: 1.2;
        }
        .bb-active-meta { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 16px; }
        .bb-deadline {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--purple-light); color: var(--purple);
            border-radius: 20px; padding: 6px 16px; font-size: 0.85rem; font-weight: 700;
            margin-bottom: 20px; width: fit-content;
        }
        .bb-deadline.urgent { background: #fef2f2; color: var(--danger); }

        /* Draw button */
        .bb-draw-section {
            text-align: center; padding: 40px 20px;
            background: var(--white); border-radius: var(--radius);
            box-shadow: var(--card-shadow); margin-bottom: 32px;
        }
        .bb-draw-icon { font-size: 4rem; margin-bottom: 12px; }
        .bb-draw-section h2 { margin: 0 0 8px; color: var(--text); }
        .bb-draw-section p { color: var(--text-muted); margin: 0 0 24px; }
        .bb-pool-count {
            display: inline-block; background: var(--purple-light); color: var(--purple);
            border-radius: 20px; padding: 4px 14px; font-size: 0.8rem; font-weight: 700;
            margin-bottom: 20px;
        }

        .btn-primary {
            background: var(--purple); color: #fff; border: none;
            border-radius: var(--radius-sm); padding: 14px 32px;
            font-size: 1rem; font-weight: 700; cursor: pointer;
            transition: background 0.15s;
        }
        .btn-primary:hover { background: var(--purple-hover); }
        .btn-ghost {
            background: transparent; color: var(--text-muted);
            border: 2px solid var(--border); border-radius: var(--radius-sm);
            padding: 10px 22px; font-size: 0.88rem; font-weight: 600;
            cursor: pointer; transition: border-color 0.15s, color 0.15s;
        }
        .btn-ghost:hover { border-color: var(--purple); color: var(--purple); }

        /* Pool picker */
        .bb-pool-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 16px;
        }
        .bb-pool-header h3 { margin: 0; font-size: 1.1rem; color: var(--text); }
        .bb-search {
            padding: 8px 14px; border: 2px solid var(--border); border-radius: var(--radius-sm);
            background: var(--white); color: var(--text); font-size: 0.88rem;
            outline: none; width: 220px;
        }
        .bb-search:focus { border-color: var(--purple); }

        .bb-pool-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 12px;
        }
        .bb-pool-card {
            position: relative; border-radius: var(--radius-sm); overflow: hidden;
            cursor: pointer; transition: transform 0.15s, box-shadow 0.15s;
            background: var(--white); box-shadow: var(--card-shadow);
        }
        .bb-pool-card:hover { transform: translateY(-3px); box-shadow: var(--card-hover); }
        .bb-pool-card.in-pool { outline: 3px solid var(--purple); }
        .bb-pool-img {
            width: 100%; aspect-ratio: 2/3;
            object-fit: cover; object-position: center top; display: block;
        }
        .bb-pool-placeholder {
            width: 100%; aspect-ratio: 2/3;
            background: var(--purple-light);
            display: flex; align-items: center; justify-content: center; font-size: 2rem;
        }
        .bb-pool-check {
            position: absolute; top: 6px; right: 6px;
            background: var(--purple); color: #fff;
            border-radius: 50%; width: 22px; height: 22px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem; font-weight: 700;
        }
        .bb-pool-name {
            padding: 6px 8px; font-size: 0.68rem; font-weight: 700;
            color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        @media (max-width: 600px) {
            .bb-active { grid-template-columns: 1fr; }
            .bb-active-img, .bb-active-placeholder { width: 100%; height: 200px; }
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container" style="max-width:1000px;margin:0 auto;padding:32px 20px;">

    <div class="bb-hero">
        <h1>📦 Blind Box</h1>
        <p>Voeg games toe aan de pot. De site kiest er willekeurig één — jij speelt het die week.</p>
    </div>

    <?php if ($activeGame): ?>
    <!-- ACTIVE GAME -->
    <?php
        $img = !empty($activeGame['cover_image']) ? 'uploads/' . $activeGame['cover_image'] : '';
        $hasImg = $img && file_exists(__DIR__ . '/' . $img);
    ?>
    <div style="margin-bottom:10px;">
        <span style="font-size:1rem;font-weight:700;color:var(--text);">🎮 Jouw game deze week</span>
    </div>
    <div class="bb-active">
        <?php if ($hasImg): ?>
        <img src="<?= htmlspecialchars($img) ?>" alt="" class="bb-active-img">
        <?php else: ?>
        <div class="bb-active-placeholder">🎮</div>
        <?php endif; ?>
        <div class="bb-active-body">
            <div class="bb-active-label">✨ Nu aan jou</div>
            <div class="bb-active-title"><?= htmlspecialchars($activeGame['title']) ?></div>
            <div class="bb-active-meta">
                <?= htmlspecialchars($activeGame['platform']) ?>
                <?php if ($activeGame['genre']): ?> · <?= htmlspecialchars($activeGame['genre']) ?><?php endif; ?>
            </div>
            <div class="bb-deadline <?= $daysLeft <= 2 ? 'urgent' : '' ?>">
                ⏳ <?= $daysLeft ?> dag<?= $daysLeft !== 1 ? 'en' : '' ?> over · deadline <?= date('d M', strtotime($active['deadline'])) ?>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="game_detail.php?id=<?= $activeGame['id'] ?>" class="btn-primary" style="font-size:0.88rem;padding:10px 20px;text-decoration:none;">
                    📖 Bekijk game
                </a>
                <form method="POST">
                    <input type="hidden" name="action" value="clear_active">
                    <button type="submit" class="btn-ghost">✕ Klaar / Overslaan</button>
                </form>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- DRAW SECTION -->
    <div class="bb-draw-section">
        <div class="bb-draw-icon">🎲</div>
        <h2>Trek een game!</h2>
        <p>Zet games in de pot hieronder en laat het lot beslissen wat je speelt.</p>
        <div class="bb-pool-count">🎮 <?= count($pool) ?> game<?= count($pool) !== 1 ? 's' : '' ?> in de pot</div>
        <br>
        <?php if (count($pool) >= 1): ?>
        <form method="POST">
            <input type="hidden" name="action" value="draw">
            <button type="submit" class="btn-primary" style="font-size:1.1rem;padding:16px 40px;">
                🎲 Trek een game!
            </button>
        </form>
        <?php else: ?>
        <p style="color:var(--text-muted);font-size:0.9rem;">Voeg eerst games toe aan de pot hieronder.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- POOL PICKER -->
    <div style="background:var(--white);border-radius:var(--radius);padding:24px;box-shadow:var(--card-shadow);">
        <div class="bb-pool-header">
            <h3>🪣 Jouw pot <span style="color:var(--purple);font-size:0.85rem;">(<?= count($pool) ?> games)</span></h3>
            <input type="text" class="bb-search" placeholder="🔍 Zoeken..." oninput="filterBB(this.value)" id="bbSearch">
        </div>

        <?php if (empty($allGames)): ?>
        <p style="color:var(--text-muted);text-align:center;padding:20px;">Geen games gevonden.</p>
        <?php else: ?>
        <div class="bb-pool-grid" id="bbGrid">
            <?php foreach ($allGames as $g): ?>
            <?php
                $inPool = in_array((int)$g['id'], $pool);
                $img = !empty($g['cover_image']) ? 'uploads/' . $g['cover_image'] : '';
                $hasImg = $img && file_exists(__DIR__ . '/' . $img);
            ?>
            <form method="POST" data-title="<?= strtolower(htmlspecialchars($g['title'])) ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="game_id" value="<?= $g['id'] ?>">
                <button type="submit" class="bb-pool-card <?= $inPool ? 'in-pool' : '' ?>"
                        style="width:100%;text-align:left;font-family:inherit;"
                        title="<?= htmlspecialchars($g['title']) ?>">
                    <?php if ($hasImg): ?>
                    <img src="<?= htmlspecialchars($img) ?>" alt="" class="bb-pool-img" loading="lazy">
                    <?php else: ?>
                    <div class="bb-pool-placeholder">🎮</div>
                    <?php endif; ?>
                    <?php if ($inPool): ?>
                    <span class="bb-pool-check">✓</span>
                    <?php endif; ?>
                    <div class="bb-pool-name"><?= htmlspecialchars($g['title']) ?></div>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
function filterBB(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#bbGrid form').forEach(function(el) {
        var t = el.dataset.title || '';
        el.style.display = t.includes(q) ? '' : 'none';
    });
}

(function(){
    var t = localStorage.getItem('gv_theme') || 'light';
    document.documentElement.setAttribute('data-theme', t);
    var b = document.getElementById('darkToggle');
    if (b) b.textContent = t === 'dark' ? '☀️ Light' : '🌙 Dark';
})();
</script>
</body>
</html>

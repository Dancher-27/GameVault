<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once 'connection.php';
require_once 'classes/Game.php';
require_once 'rawg_config.php';

$gameObj = new Game($conn, (int)$_SESSION["user_id"]);

// ── Tab 1: Jouw tijdlijn ──────────────────────────────────────────────────────
$result = $conn->query(
    "SELECT g.*,
        COALESCE(
            YEAR(MIN(CASE WHEN ps.note LIKE '%First Playthrough%' THEN ps.played_date END)),
            g.release_year
        ) AS timeline_year,
        MIN(CASE WHEN ps.note LIKE '%First Playthrough%' THEN ps.played_date END) AS first_play_date
     FROM games g
     LEFT JOIN play_sessions ps ON ps.game_id = g.id
     WHERE g.user_id = {$_SESSION['user_id']} AND g.status != 'wishlist'
     GROUP BY g.id
     HAVING timeline_year IS NOT NULL AND timeline_year > 0
     ORDER BY timeline_year DESC, first_play_date ASC, g.rating DESC"
);
$allGames = $result->fetch_all(MYSQLI_ASSOC);

$byYear = [];
foreach ($allGames as $g) {
    $byYear[(int)$g['timeline_year']][] = $g;
}
krsort($byYear);

$myYears = array_keys($byYear);

// ── Tab 2: Ontdek — add to wishlist ──────────────────────────────────────────
$addMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rawg_add'])) {
    $title    = trim($_POST['title'] ?? '');
    $platform = 'Onbekend';
    $cover    = trim($_POST['cover'] ?? '');
    $year     = (int)($_POST['release_year'] ?? 0);

    // Download cover image
    $savedCover = null;
    if ($cover) {
        $ext      = 'jpg';
        $filename = preg_replace('/[^a-z0-9_-]/i', '_', strtolower($title)) . '_rawg.' . $ext;
        $dest     = __DIR__ . '/uploads/' . $filename;
        if (!file_exists($dest)) {
            $data = @file_get_contents($cover);
            if ($data) { file_put_contents($dest, $data); $savedCover = $filename; }
        } else {
            $savedCover = $filename;
        }
    }

    $nextWishRank = $gameObj->getNextWishlistRank();
    $ok = $gameObj->addGame($title, $platform, '', $year ?: null, $savedCover, 0, 0, 0, 'wishlist', '', 0, 'not_replayed', $nextWishRank);
    $addMsg = $ok ? "✅ <strong>" . htmlspecialchars($title) . "</strong> toegevoegd aan verlanglijst!" : "❌ Er ging iets mis.";
    // Stay on tab 2
    header('Location: timeline.php?tab=discover&year=' . ($_POST['discover_year'] ?? date('Y')) . '&msg=' . urlencode($addMsg));
    exit();
}

// ── RAWG fetch ────────────────────────────────────────────────────────────────
$activeTab    = $_GET['tab'] ?? 'timeline';
$discoverYear = (int)($_GET['year'] ?? date('Y'));
$rawgGames    = [];
$rawgError    = '';
$addMsg       = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';

if ($activeTab === 'discover') {
    $keyOk = defined('RAWG_KEY') && RAWG_KEY !== 'JOU_API_KEY_HIER' && RAWG_KEY !== '';
    if (!$keyOk) {
        $rawgError = 'Vul eerst je RAWG API key in via <code>rawg_config.php</code>.<br>Haal hem gratis op via <a href="https://rawg.io/apidocs" target="_blank">rawg.io/apidocs</a>.';
    } else {
        $url = sprintf(
            'https://api.rawg.io/api/games?key=%s&dates=%d-01-01,%d-12-31&ordering=-metacritic&page_size=40&metacritic=1,100',
            urlencode(RAWG_KEY), $discoverYear, $discoverYear
        );
        $ctx  = stream_context_create(['http' => ['timeout' => 8, 'header' => 'User-Agent: GameVault/1.0']]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp) {
            $data = json_decode($resp, true);
            $rawgGames = $data['results'] ?? [];
        } else {
            $rawgError = 'Kon de RAWG API niet bereiken. Controleer je API key en internetverbinding.';
        }
    }
}

// IDs/titles already in user's collection to mark them
$myTitles = array_map('strtolower', array_column($allGames, 'title'));
$allMyGames = $gameObj->getAllGames();
$myTitlesAll = array_map('strtolower', array_column($allMyGames, 'title'));
$wishlist = $gameObj->getWishlist();
$myTitlesAll = array_merge($myTitlesAll, array_map('strtolower', array_column($wishlist, 'title')));

$current = 'timeline.php';
?>
<!DOCTYPE html>
<html lang="nl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Tijdlijn</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* ── Hero ── */
        .tl-hero {
            background: linear-gradient(135deg, var(--navbar-bg) 0%, #1a0533 100%);
            border-radius: var(--radius); padding: 36px 40px; color: #fff; margin-bottom: 28px;
        }
        .tl-hero h1 { font-size: 2rem; margin: 0 0 6px; }
        .tl-hero p  { opacity: .7; margin: 0; font-size: 1rem; }
        .tl-hero-stats { display: flex; gap: 32px; margin-top: 18px; flex-wrap: wrap; }
        .tl-hero-stat span:first-child { font-size: 1.8rem; font-weight: 800; color: #c4b5fd; }
        .tl-hero-stat span:last-child  { display: block; font-size: 0.8rem; opacity: .6; }

        /* ── Tabs ── */
        .tl-tabs {
            display: flex; gap: 4px; margin-bottom: 28px;
            background: var(--white); padding: 6px; border-radius: var(--radius);
            box-shadow: var(--card-shadow); width: fit-content;
        }
        .tl-tab {
            padding: 10px 24px; border-radius: 10px; font-size: 0.92rem; font-weight: 700;
            cursor: pointer; text-decoration: none; color: var(--text-muted);
            transition: background 0.15s, color 0.15s; border: none; background: none;
        }
        .tl-tab.active { background: var(--purple); color: #fff; }
        .tl-tab:hover:not(.active) { background: var(--purple-light); color: var(--purple); }

        /* ── Timeline (tab 1) ── */
        .tl-year-section { margin-bottom: 40px; }
        .tl-year-header  { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
        .tl-year-label   { font-size: 1.5rem; font-weight: 800; color: var(--purple); }
        .tl-year-count   {
            background: var(--purple-light); color: var(--purple);
            border-radius: 20px; padding: 3px 12px; font-size: 0.78rem; font-weight: 700;
        }
        .tl-year-line    { flex: 1; height: 2px; background: var(--border); }

        .tl-scroll {
            display: flex; gap: 14px; overflow-x: auto;
            padding-bottom: 12px; scroll-snap-type: x mandatory;
        }
        .tl-scroll::-webkit-scrollbar        { height: 5px; }
        .tl-scroll::-webkit-scrollbar-track  { background: var(--border); border-radius: 4px; }
        .tl-scroll::-webkit-scrollbar-thumb  { background: var(--purple-border); border-radius: 4px; }

        .tl-card {
            flex: 0 0 140px; scroll-snap-align: start;
            border-radius: var(--radius-sm); overflow: hidden; position: relative;
            text-decoration: none; background: var(--white); box-shadow: var(--card-shadow);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .tl-card:hover { transform: translateY(-4px); box-shadow: var(--card-hover); }
        .tl-card-img   {
            width: 100%; aspect-ratio: 2/3;
            object-fit: cover; object-position: center top; display: block;
        }
        .tl-card-placeholder {
            width: 100%; aspect-ratio: 2/3; background: var(--purple-light);
            display: flex; align-items: center; justify-content: center; font-size: 2.5rem;
        }
        .tl-card-info  { padding: 8px 10px 10px; }
        .tl-card-title {
            font-size: 0.78rem; font-weight: 700; color: var(--text);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px;
        }
        .tl-card-date   { font-size: 0.68rem; color: var(--text-muted); margin-bottom: 3px; }
        .tl-card-rating { font-size: 0.72rem; font-weight: 700; color: var(--purple); }
        .tl-card-status {
            position: absolute; top: 7px; right: 7px;
            background: rgba(0,0,0,0.65); color: #fff;
            border-radius: 6px; font-size: 0.62rem; padding: 2px 6px;
        }

        /* ── Discover (tab 2) ── */
        .disc-controls {
            display: flex; align-items: center; gap: 14px;
            margin-bottom: 24px; flex-wrap: wrap;
        }
        .disc-year-label { font-size: 1rem; font-weight: 700; color: var(--text); }
        .disc-year-nav {
            display: flex; align-items: center; gap: 6px;
            background: var(--white); border-radius: var(--radius-sm);
            padding: 6px 10px; box-shadow: var(--card-shadow);
        }
        .disc-year-btn {
            background: var(--purple-light); color: var(--purple); border: none;
            border-radius: 6px; width: 32px; height: 32px; font-size: 1rem;
            cursor: pointer; font-weight: 700; transition: background 0.15s;
            text-decoration: none; display: flex; align-items: center; justify-content: center;
        }
        .disc-year-btn:hover { background: var(--purple); color: #fff; }
        .disc-year-current { font-size: 1.4rem; font-weight: 800; color: var(--purple); min-width: 60px; text-align: center; }
        .disc-gap-badge {
            background: #fef3c7; color: #92400e;
            border-radius: 20px; padding: 4px 14px; font-size: 0.8rem; font-weight: 700;
        }
        .disc-have-badge {
            background: var(--purple-light); color: var(--purple);
            border-radius: 20px; padding: 4px 14px; font-size: 0.8rem; font-weight: 700;
        }

        .disc-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 16px;
        }
        .disc-card {
            border-radius: var(--radius-sm); overflow: hidden; background: var(--white);
            box-shadow: var(--card-shadow); position: relative;
        }
        .disc-card-img {
            width: 100%; aspect-ratio: 2/3;
            object-fit: cover; object-position: center top; display: block;
        }
        .disc-card-placeholder {
            width: 100%; aspect-ratio: 2/3; background: var(--purple-light);
            display: flex; align-items: center; justify-content: center; font-size: 3rem;
        }
        .disc-card-body { padding: 10px 12px 12px; }
        .disc-card-title {
            font-size: 0.82rem; font-weight: 700; color: var(--text);
            margin-bottom: 4px; line-height: 1.3;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .disc-card-meta  { font-size: 0.72rem; color: var(--text-muted); margin-bottom: 8px; }
        .disc-card-score { font-size: 0.78rem; font-weight: 700; color: var(--purple); margin-bottom: 8px; }
        .disc-add-btn {
            display: block; width: 100%; background: var(--purple); color: #fff;
            border: none; border-radius: 6px; padding: 7px 0; font-size: 0.78rem;
            font-weight: 700; cursor: pointer; transition: background 0.15s;
        }
        .disc-add-btn:hover   { background: var(--purple-hover); }
        .disc-add-btn.have-it {
            background: var(--purple-light); color: var(--purple); cursor: default;
        }
        .disc-owned-badge {
            position: absolute; top: 7px; left: 7px;
            background: var(--purple); color: #fff;
            border-radius: 6px; font-size: 0.62rem; font-weight: 700; padding: 2px 7px;
        }

        .disc-loading { text-align: center; padding: 60px 20px; color: var(--text-muted); font-size: 1.1rem; }
        .disc-error   {
            background: var(--error-bg); border-radius: var(--radius-sm);
            padding: 18px 22px; color: var(--danger); margin-bottom: 20px;
        }

        .tl-empty { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .tl-empty .tl-empty-icon { font-size: 3rem; margin-bottom: 12px; }

        .msg-success {
            background: #f0fdf4; border-radius: var(--radius-sm);
            padding: 14px 18px; color: #166534; margin-bottom: 20px; font-size: 0.9rem;
        }
        .msg-error {
            background: var(--error-bg); border-radius: var(--radius-sm);
            padding: 14px 18px; color: var(--danger); margin-bottom: 20px; font-size: 0.9rem;
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container" style="max-width:1200px;margin:0 auto;padding:32px 20px;">

    <!-- Hero -->
    <div class="tl-hero">
        <h1>📅 Jouw Spelgeschiedenis</h1>
        <p>Jouw tijdlijn &amp; ontdek wat je miste per jaar.</p>
        <div class="tl-hero-stats">
            <div class="tl-hero-stat">
                <span><?= count($allGames) ?></span>
                <span>Games in tijdlijn</span>
            </div>
            <div class="tl-hero-stat">
                <span><?= count($byYear) ?></span>
                <span>Jaren gedekt</span>
            </div>
            <?php if (!empty($byYear)): ?>
            <div class="tl-hero-stat">
                <span><?= array_key_first($byYear) ?></span>
                <span>Laatste jaar</span>
            </div>
            <div class="tl-hero-stat">
                <span><?= array_key_last($byYear) ?></span>
                <span>Oudste jaar</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tl-tabs">
        <a href="timeline.php?tab=timeline" class="tl-tab <?= $activeTab === 'timeline' ? 'active' : '' ?>">
            📅 Jouw Tijdlijn
        </a>
        <a href="timeline.php?tab=discover&year=<?= $discoverYear ?>" class="tl-tab <?= $activeTab === 'discover' ? 'active' : '' ?>">
            🔭 Ontdek Per Jaar
        </a>
    </div>

    <?php if ($addMsg): ?>
    <div class="<?= str_contains($addMsg, '✅') ? 'msg-success' : 'msg-error' ?>"><?= $addMsg ?></div>
    <?php endif; ?>

    <!-- ══ TAB 1: TIJDLIJN ══ -->
    <?php if ($activeTab === 'timeline'): ?>

    <?php if (empty($byYear)): ?>
    <div class="tl-empty">
        <div class="tl-empty-icon">📅</div>
        <p>Geen games gevonden.<br>Voeg speelsessies toe met een "First Playthrough" notitie, of games met een releasejaar.</p>
    </div>
    <?php else: ?>
    <?php foreach ($byYear as $year => $games): ?>
    <div class="tl-year-section">
        <div class="tl-year-header">
            <span class="tl-year-label"><?= $year ?></span>
            <span class="tl-year-count"><?= count($games) ?> game<?= count($games) !== 1 ? 's' : '' ?></span>
            <div class="tl-year-line"></div>
            <a href="timeline.php?tab=discover&year=<?= $year ?>" style="font-size:0.78rem;color:var(--purple);text-decoration:none;white-space:nowrap;">🔭 Ontdek meer uit <?= $year ?> →</a>
        </div>
        <div class="tl-scroll">
            <?php foreach ($games as $g): ?>
            <?php
                $img    = !empty($g['cover_image']) ? 'uploads/' . $g['cover_image'] : '';
                $hasImg = $img && file_exists(__DIR__ . '/' . $img);
                $statusLabel = match($g['status']) {
                    'played'  => '✅', 'playing' => '🎮', 'backlog' => '📋', default => ''
                };
            ?>
            <a href="game_detail.php?id=<?= $g['id'] ?>" class="tl-card" title="<?= htmlspecialchars($g['title']) ?>">
                <?php if ($hasImg): ?>
                <img src="<?= htmlspecialchars($img) ?>" alt="" class="tl-card-img" loading="lazy">
                <?php else: ?>
                <div class="tl-card-placeholder">🎮</div>
                <?php endif; ?>
                <?php if ($statusLabel): ?>
                <span class="tl-card-status"><?= $statusLabel ?></span>
                <?php endif; ?>
                <div class="tl-card-info">
                    <div class="tl-card-title"><?= htmlspecialchars($g['title']) ?></div>
                    <?php if (!empty($g['first_play_date'])): ?>
                    <div class="tl-card-date">📅 <?= date('d M Y', strtotime($g['first_play_date'])) ?></div>
                    <?php endif; ?>
                    <div class="tl-card-rating">⭐ <?= number_format((float)$g['rating'], 1) ?>/10</div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- ══ TAB 2: ONTDEK ══ -->
    <?php else: ?>

    <!-- Year nav -->
    <div class="disc-controls">
        <div class="disc-year-nav">
            <a href="timeline.php?tab=discover&year=<?= $discoverYear - 1 ?>" class="disc-year-btn">‹</a>
            <span class="disc-year-current"><?= $discoverYear ?></span>
            <a href="timeline.php?tab=discover&year=<?= $discoverYear + 1 ?>" class="disc-year-btn">›</a>
        </div>
        <?php if (in_array($discoverYear, $myYears)): ?>
        <span class="disc-have-badge">✅ Jij speelde games uit <?= $discoverYear ?></span>
        <?php else: ?>
        <span class="disc-gap-badge">⚠️ Geen games van jou uit <?= $discoverYear ?></span>
        <?php endif; ?>
        <span style="color:var(--text-muted);font-size:0.82rem;">Top 40 hoogst gewaardeerde releases</span>
    </div>

    <?php if ($rawgError): ?>
    <div class="disc-error">⚠️ <?= $rawgError ?></div>
    <?php elseif (empty($rawgGames)): ?>
    <div class="tl-empty">
        <div class="tl-empty-icon">🎮</div>
        <p>Geen games gevonden voor <?= $discoverYear ?>.</p>
    </div>
    <?php else: ?>
    <div class="disc-grid">
        <?php foreach ($rawgGames as $rg): ?>
        <?php
            $rTitle    = $rg['name'] ?? '';
            $rImg      = $rg['background_image'] ?? '';
            $rScore    = $rg['metacritic'] ?? ($rg['rating'] ? round($rg['rating'] * 10) : null);
            $rReleased = $rg['released'] ?? '';
            $rYear     = $rReleased ? (int)substr($rReleased, 0, 4) : $discoverYear;
            $alreadyHave = in_array(strtolower($rTitle), $myTitlesAll);
        ?>
        <div class="disc-card">
            <?php if ($rImg): ?>
            <img src="<?= htmlspecialchars($rImg) ?>" alt="" class="disc-card-img" loading="lazy">
            <?php else: ?>
            <div class="disc-card-placeholder">🎮</div>
            <?php endif; ?>
            <?php if ($alreadyHave): ?>
            <span class="disc-owned-badge">✓ In collectie</span>
            <?php endif; ?>
            <div class="disc-card-body">
                <div class="disc-card-title"><?= htmlspecialchars($rTitle) ?></div>
                <?php if ($rReleased): ?>
                <div class="disc-card-meta">📅 <?= date('d M Y', strtotime($rReleased)) ?></div>
                <?php endif; ?>
                <?php if ($rScore): ?>
                <div class="disc-card-score">🏅 Metacritic: <?= $rScore ?></div>
                <?php endif; ?>
                <?php if ($alreadyHave): ?>
                <button class="disc-add-btn have-it" disabled>✓ Al in collectie</button>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="rawg_add"       value="1">
                    <input type="hidden" name="title"          value="<?= htmlspecialchars($rTitle) ?>">
                    <input type="hidden" name="cover"          value="<?= htmlspecialchars($rImg) ?>">
                    <input type="hidden" name="release_year"   value="<?= $rYear ?>">
                    <input type="hidden" name="discover_year"  value="<?= $discoverYear ?>">
                    <button type="submit" class="disc-add-btn">+ Verlanglijst</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script>
(function(){
    var t = localStorage.getItem('gv_theme') || 'light';
    document.documentElement.setAttribute('data-theme', t);
    var b = document.getElementById('darkToggle');
    if (b) b.textContent = t === 'dark' ? '☀️ Light' : '🌙 Dark';
})();
</script>
</body>
</html>

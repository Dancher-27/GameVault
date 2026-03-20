<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'connection.php';
require_once 'classes/Game.php';
require_once 'classes/Tag.php';

$gameObj = new Game($conn, (int)$_SESSION["user_id"]);
$tagObj  = new Tag($conn, (int)$_SESSION["user_id"]);
$played  = $gameObj->getGamesByStatus('played');
$playing = $gameObj->getGamesByStatus('playing');
$backlog = $gameObj->getGamesByStatus('backlog');
$genres  = $gameObj->getPlayedGenres();
$stats   = $gameObj->getStats();

// DLCs grouped by parent game ID for display on cards
$dlcsByParent = [];
$uid = (int)$_SESSION['user_id'];
$dlcRows = $conn->query("SELECT id, title, cover_image, parent_game_id, status, rating FROM games WHERE user_id = $uid AND game_type = 'dlc' AND parent_game_id IS NOT NULL ORDER BY title ASC");
if ($dlcRows) { foreach ($dlcRows->fetch_all(MYSQLI_ASSOC) as $d) { $dlcsByParent[$d['parent_game_id']][] = $d; } }

// Sterren genereren (rating 0-10 => 5 sterren)
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

// Rank badge op basis van positie
function rankBadge(int $rank): string {
    if ($rank === 1) return '<span class="rank-badge rank-gold">🥇 #1</span>';
    if ($rank === 2) return '<span class="rank-badge rank-silver">🥈 #2</span>';
    if ($rank === 3) return '<span class="rank-badge rank-bronze">🥉 #3</span>';
    return '<span class="rank-badge rank-default">#' . $rank . '</span>';
}

// Replay status label
function replayLabel(string $status): string {
    return match($status) {
        'replayed'    => '<span class="replay-tag replay-replayed">🔁 Hergespeeld</span>',
        'will_replay' => '<span class="replay-tag replay-will_replay">🔜 Wil herspelen</span>',
        'wont_replay' => '<span class="replay-tag replay-wont_replay">🚫 Niet herspelen</span>',
        'not_replayed'=> '<span class="replay-tag replay-not_replayed">➖ Niet hergespeeld</span>',
        default       => '',
    };
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Ranking</title>
    <link rel="stylesheet" href="styles.css">
    <style>
    /* ===== RANKING PAGINATION ===== */
    .ranking-page {
        display: none;
        animation: fadeSlideIn 0.35s ease;
    }
    .ranking-page.active { display: grid; }
    @keyframes fadeSlideIn {
        from { opacity: 0; transform: translateX(30px); }
        to   { opacity: 1; transform: translateX(0); }
    }
    .ranking-page.slide-left { animation-name: fadeSlideInLeft; }
    @keyframes fadeSlideInLeft {
        from { opacity: 0; transform: translateX(-30px); }
        to   { opacity: 1; transform: translateX(0); }
    }

    .page-controls {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 14px;
        margin: 14px 0;
    }
    .page-nav-btn {
        width: 38px; height: 38px;
        border-radius: 50%;
        border: 2px solid var(--border);
        background: var(--surface);
        color: var(--text);
        cursor: pointer;
        font-size: 1.1rem;
        display: flex; align-items: center; justify-content: center;
        transition: background .2s, border-color .2s, opacity .2s;
        line-height: 1;
    }
    .page-nav-btn:hover:not(:disabled) {
        background: var(--purple, #7c3aed);
        border-color: var(--purple, #7c3aed);
        color: #fff;
    }
    .page-nav-btn:disabled { opacity: .25; cursor: not-allowed; }

    .page-dots { display: flex; gap: 8px; align-items: center; }
    .page-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        border: none;
        background: var(--border);
        cursor: pointer;
        padding: 0;
        transition: all .25s;
    }
    .page-dot.active {
        background: var(--purple, #7c3aed);
        width: 22px;
        border-radius: 4px;
    }
    .page-label {
        font-size: .78rem;
        color: var(--text-muted);
        min-width: 36px;
        text-align: center;
    }

    /* ===== SORT BAR ===== */
    .sort-bar {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 10px 0 16px;
        flex-wrap: wrap;
    }
    .sort-label {
        font-size: .78rem;
        color: var(--text-muted);
        font-weight: 600;
        margin-right: 2px;
    }
    .sort-btn {
        padding: 5px 12px;
        border-radius: 20px;
        border: 1.5px solid var(--border);
        background: var(--surface);
        color: var(--text-muted);
        font-size: .78rem;
        font-weight: 600;
        cursor: pointer;
        transition: all .2s;
    }
    .sort-btn:hover { border-color: var(--purple, #7c3aed); color: var(--purple, #7c3aed); }
    .sort-btn.active {
        background: var(--purple, #7c3aed);
        border-color: var(--purple, #7c3aed);
        color: #fff;
    }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-wrapper">
    <h1 class="page-title">🏆 Jouw Ranking, <?= htmlspecialchars($_SESSION['username']) ?></h1>
    <p class="page-subtitle">Jouw persoonlijke game ratings, scores en ranking overzicht.</p>

    <!-- ===== STATS ===== -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon">🎮</span>
            <span class="stat-value"><?= (int)($stats['total'] ?? 0) ?></span>
            <span class="stat-label">Totaal games</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">✅</span>
            <span class="stat-value"><?= (int)($stats['played'] ?? 0) ?></span>
            <span class="stat-label">Gespeeld</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">⭐</span>
            <span class="stat-value"><?= $stats['avg_rating'] ?? '—' ?></span>
            <span class="stat-label">Gem. rating</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">⏱️</span>
            <span class="stat-value"><?= (int)($stats['total_hours'] ?? 0) ?>u</span>
            <span class="stat-label">Totale speeltijd</span>
        </div>
    </div>

    <!-- ===== HALL OF FAME ===== -->
    <?php $hof = array_slice($played, 0, 3); if (!empty($hof)): ?>
    <div class="section-block">
        <div class="section-header">
            <h2>👑 Hall of Fame</h2>
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="section-count">Top 3</span>
                <a href="hof_upload.php" class="btn btn-secondary" style="padding:4px 12px;font-size:0.78rem;">🖼️ Foto's aanpassen</a>
            </div>
        </div>
        <div class="hof-grid">
            <?php foreach ($hof as $i => $g):
                // Use dedicated HoF landscape image if uploaded, otherwise fall back to cover
                $hofMatches = glob(__DIR__ . '/uploads/hof_' . $g['id'] . '.*');
                $coverFile = 'uploads/' . $g['cover_image'];
                if (!empty($hofMatches)) {
                    $img = 'uploads/' . basename($hofMatches[0]); $hasImg = true;
                } elseif (!empty($g['cover_image']) && file_exists(__DIR__ . '/' . $coverFile)) {
                    $img = $coverFile; $hasImg = true;
                } else {
                    $img = ''; $hasImg = false;
                }
                $hofClass = ['hof-gold','hof-silver','hof-bronze'][$i];
                $hofLabel = ['🥇 #1 — Beste game','🥈 #2','🥉 #3'][$i];
            ?>
            <a href="game_detail.php?id=<?= $g['id'] ?>" class="hof-card <?= $hofClass ?>">
                <?php if ($hasImg): ?>
                <div class="hof-bg" style="background-image:url('<?= htmlspecialchars($img) ?>')"></div>
                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($g['title']) ?>" class="hof-img" loading="lazy">
                <?php else: ?>
                <div class="hof-img-placeholder">🎮</div>
                <?php endif; ?>
                <div class="hof-overlay">
                    <span class="hof-rank"><?= $hofLabel ?></span>
                    <span class="hof-title"><?= htmlspecialchars($g['title']) ?></span>
                    <span class="hof-rating">⭐ <?= number_format($g['rating'], 1) ?>/10</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== RANKING: GESPEELD ===== -->
    <div class="section-block">
        <div class="section-header">
            <h2>🏆 Mijn Ranking</h2>
            <span class="section-count"><?= count($played) ?></span>
        </div>

        <?php if (!empty($genres)): ?>
        <!-- Genre tabs -->
        <div class="genre-tabs">
            <button class="genre-tab active" data-genre="all">Alle</button>
            <?php foreach ($genres as $genre): ?>
            <button class="genre-tab" data-genre="<?= htmlspecialchars($genre) ?>"><?= htmlspecialchars($genre) ?></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Sort knoppen -->
        <div class="sort-bar">
            <span class="sort-label">Sorteren:</span>
            <button class="sort-btn active" data-sort="rank">🏆 Ranking</button>
            <button class="sort-btn" data-sort="enjoyability">😄 Fun</button>
            <button class="sort-btn" data-sort="replayability">🔁 Replay</button>
            <button class="sort-btn" data-sort="playtime">⏱️ Speeltijd</button>
            <button class="sort-btn" data-sort="title">🔤 Titel</button>
            <button class="sort-btn" data-sort="year">📅 Jaar</button>
        </div>

        <?php if (empty($played)): ?>
        <div class="empty-state">
            <span class="empty-state-icon">🎯</span>
            <p>Nog geen games gerangschikt. Voeg je eerste game toe!</p>
            <a href="add_game.php" class="btn btn-primary" style="width:auto;">➕ Game toevoegen</a>
        </div>
        <?php else: ?>
        <div class="game-grid" id="ranking-grid">
            <?php foreach ($played as $rank => $game): ?>
            <a href="game_detail.php?id=<?= $game['id'] ?>"
               class="game-card-link"
               data-id="<?= $game['id'] ?>"
               data-genre="<?= htmlspecialchars($game['genre'] ?? '') ?>"
               data-playtime="<?= (int)($game['playtime'] ?? 0) ?>"
               data-title="<?= htmlspecialchars(mb_strtolower($game['title'])) ?>"
               data-year="<?= (int)($game['release_year'] ?? 0) ?>"
               data-enjoyability="<?= (float)($game['enjoyability'] ?? 0) ?>"
               data-replayability="<?= (float)($game['replayability'] ?? 0) ?>">
                <div class="game-card <?= $rank < 3 ? 'game-card-top' : '' ?>">
                    <?= rankBadge($rank + 1) ?>

                    <?php
                    $img = 'uploads/' . $game['cover_image'];
                    if (!empty($game['cover_image']) && file_exists(__DIR__ . '/' . $img)): ?>
                        <img src="<?= htmlspecialchars($img) ?>"
                             alt="<?= htmlspecialchars($game['title']) ?>"
                             class="game-card-img"
                             loading="lazy">
                    <?php else: ?>
                        <div class="game-card-img-placeholder">🎮</div>
                    <?php endif; ?>

                    <div class="game-card-body">
                        <div class="game-card-title"><?= htmlspecialchars($game['title']) ?></div>

                        <div class="game-card-meta">
                            <?php if (!empty($game['platform'])): ?>
                            <span class="meta-tag">🖥️ <?= htmlspecialchars($game['platform']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($game['genre'])): ?>
                            <span class="meta-tag">🎯 <?= htmlspecialchars($game['genre']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($game['playtime'])): ?>
                            <span class="meta-tag">⏱️ <?= (int)$game['playtime'] ?>u</span>
                            <?php endif; ?>
                        </div>

                        <div class="rating-row">
                            <div class="stars-display"><?= starsHtml((float)$game['rating']) ?></div>
                            <span class="rating-value"><?= number_format((float)$game['rating'], 1) ?>/10</span>
                        </div>

                        <!-- Score bars -->
                        <?php if (!empty($game['enjoyability']) || !empty($game['replayability'])): ?>
                        <div class="score-bars">
                            <div class="score-bar-row">
                                <span class="score-bar-label">Fun</span>
                                <div class="score-bar-track">
                                    <div class="score-bar-fill blue"
                                         style="width:<?= min(100, (float)$game['enjoyability'] * 10) ?>%"></div>
                                </div>
                                <span class="score-bar-val"><?= number_format((float)$game['enjoyability'], 1) ?></span>
                            </div>
                            <div class="score-bar-row">
                                <span class="score-bar-label">Rep</span>
                                <div class="score-bar-track">
                                    <div class="score-bar-fill purple"
                                         style="width:<?= min(100, (float)$game['replayability'] * 10) ?>%"></div>
                                </div>
                                <span class="score-bar-val"><?= number_format((float)$game['replayability'], 1) ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($game['replay_status'])): ?>
                        <?= replayLabel($game['replay_status']) ?>
                        <?php endif; ?>

                        <?php $gameTags = $tagObj->getTagsByGame((int)$game['id']);
                        if (!empty($gameTags)): ?>
                        <div class="game-tags" style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px;">
                            <?php foreach ($gameTags as $t): ?>
                            <span class="tag-badge" style="--tag-color:<?= htmlspecialchars($t['color']) ?>"><?= htmlspecialchars($t['name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($dlcsByParent[$game['id']])): ?>
                        <div style="margin-top:8px;border-top:1px solid var(--border);padding-top:8px;">
                            <div style="font-size:0.68rem;color:var(--text-muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:.05em;">📦 DLC's</div>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                            <?php foreach ($dlcsByParent[$game['id']] as $dlc):
                                $dlcImg    = 'uploads/' . ($dlc['cover_image'] ?? '');
                                $dlcHasImg = !empty($dlc['cover_image']) && file_exists(__DIR__ . '/' . $dlcImg);
                                $dlcDone   = $dlc['status'] === 'played';
                            ?>
                            <div title="<?= htmlspecialchars($dlc['title']) ?> — <?= $dlcDone ? 'Gespeeld' : 'Niet gespeeld' ?>"
                                 style="opacity:<?= $dlcDone ? '1' : '0.45' ?>;position:relative;flex-shrink:0;">
                                <?php if ($dlcHasImg): ?>
                                <img src="<?= htmlspecialchars($dlcImg) ?>"
                                     style="width:48px;height:64px;object-fit:cover;object-position:center top;border-radius:5px;display:block;">
                                <?php else: ?>
                                <div style="width:48px;height:64px;background:var(--purple-light);border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:1rem;">📦</div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== NU BEZIG ===== -->
    <?php if (!empty($playing)): ?>
    <div class="section-block">
        <div class="section-header">
            <h2>🎮 Nu Bezig</h2>
            <span class="section-count"><?= count($playing) ?></span>
        </div>
        <div class="game-grid">
            <?php foreach ($playing as $game): ?>
            <a href="game_detail.php?id=<?= $game['id'] ?>" class="game-card-link" data-id="<?= $game['id'] ?>">
                <div class="game-card">
                    <span class="status-badge status-playing">🎮 Bezig</span>
                    <?php
                    $img = 'uploads/' . $game['cover_image'];
                    if (!empty($game['cover_image']) && file_exists(__DIR__ . '/' . $img)): ?>
                        <img src="<?= htmlspecialchars($img) ?>"
                             alt="<?= htmlspecialchars($game['title']) ?>"
                             class="game-card-img"
                             loading="lazy">
                    <?php else: ?>
                        <div class="game-card-img-placeholder">🎮</div>
                    <?php endif; ?>
                    <div class="game-card-body">
                        <div class="game-card-title"><?= htmlspecialchars($game['title']) ?></div>
                        <div class="game-card-meta">
                            <?php if (!empty($game['platform'])): ?>
                            <span class="meta-tag">🖥️ <?= htmlspecialchars($game['platform']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($game['genre'])): ?>
                            <span class="meta-tag">🎯 <?= htmlspecialchars($game['genre']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== BACKLOG ===== -->
    <?php if (!empty($backlog)): ?>
    <div class="section-block">
        <div class="section-header">
            <h2>📋 Backlog</h2>
            <span class="section-count"><?= count($backlog) ?></span>
        </div>
        <div class="game-grid">
            <?php foreach ($backlog as $game): ?>
            <a href="game_detail.php?id=<?= $game['id'] ?>" class="game-card-link" data-id="<?= $game['id'] ?>">
                <div class="game-card">
                    <span class="status-badge status-backlog">📋 Backlog</span>
                    <?php
                    $img = 'uploads/' . $game['cover_image'];
                    if (!empty($game['cover_image']) && file_exists(__DIR__ . '/' . $img)): ?>
                        <img src="<?= htmlspecialchars($img) ?>"
                             alt="<?= htmlspecialchars($game['title']) ?>"
                             class="game-card-img"
                             loading="lazy">
                    <?php else: ?>
                        <div class="game-card-img-placeholder">🎮</div>
                    <?php endif; ?>
                    <div class="game-card-body">
                        <div class="game-card-title"><?= htmlspecialchars($game['title']) ?></div>
                        <div class="game-card-meta">
                            <?php if (!empty($game['platform'])): ?>
                            <span class="meta-tag">🖥️ <?= htmlspecialchars($game['platform']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($game['genre'])): ?>
                            <span class="meta-tag">🎯 <?= htmlspecialchars($game['genre']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /page-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    const CARDS_PER_PAGE = 10;
    const grid = document.getElementById('ranking-grid');
    if (!grid) return;

    const allCards  = Array.from(grid.querySelectorAll('.game-card-link'));
    const genreTabs = document.querySelectorAll('.genre-tab');
    const usePagination = allCards.length > CARDS_PER_PAGE;

    /* ── NO PAGINATION (few games) ── */
    if (!usePagination) {
        genreTabs.forEach(tab => {
            tab.addEventListener('click', function () {
                genreTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                const genre = this.dataset.genre;
                allCards.forEach(c => {
                    c.style.display = (genre === 'all' || c.dataset.genre === genre) ? '' : 'none';
                });
            });
        });
        initSortable([grid]);
        return;
    }

    /* ── PAGINATION SETUP ── */
    let currentPage = 0;
    allCards.forEach((c, i) => { c.dataset.pageIdx = Math.floor(i / CARDS_PER_PAGE); });

    // Pages container
    const container = document.createElement('div');
    container.id = 'ranking-container';

    // Pages
    const numPages = Math.ceil(allCards.length / CARDS_PER_PAGE);
    const pages = [];

    for (let p = 0; p < numPages; p++) {
        const pageEl = document.createElement('div');
        pageEl.className = 'ranking-page game-grid';
        allCards.slice(p * CARDS_PER_PAGE, (p + 1) * CARDS_PER_PAGE).forEach(c => pageEl.appendChild(c));
        container.appendChild(pageEl);
        pages.push(pageEl);
    }

    // Flat grid for genre filter view
    const flatGrid = document.createElement('div');
    flatGrid.className = 'game-grid';
    flatGrid.style.display = 'none';

    // Replace original grid
    grid.replaceWith(container);
    container.after(flatGrid);

    /* ── CONTROLS ── */
    function buildControls() {
        const ctrl = document.createElement('div');
        ctrl.className = 'page-controls';

        const prev = document.createElement('button');
        prev.className = 'page-nav-btn prev-btn';
        prev.innerHTML = '&#8592;';
        prev.title = 'Vorige pagina';

        const dotsEl = document.createElement('div');
        dotsEl.className = 'page-dots';
        for (let p = 0; p < numPages; p++) {
            const dot = document.createElement('button');
            dot.className = 'page-dot';
            dot.setAttribute('data-p', p);
            dot.title = `Pagina ${p + 1}`;
            dotsEl.appendChild(dot);
        }

        const label = document.createElement('span');
        label.className = 'page-label';

        const next = document.createElement('button');
        next.className = 'page-nav-btn next-btn';
        next.innerHTML = '&#8594;';
        next.title = 'Volgende pagina';

        ctrl.append(prev, dotsEl, label, next);
        return ctrl;
    }

    const ctrlTop = buildControls();
    const ctrlBot = buildControls();
    container.before(ctrlTop);
    container.after(ctrlBot);

    function updateControls(page) {
        [ctrlTop, ctrlBot].forEach(ctrl => {
            ctrl.querySelector('.prev-btn').disabled = page === 0;
            ctrl.querySelector('.next-btn').disabled = page === numPages - 1;
            ctrl.querySelectorAll('.page-dot').forEach((d, i) => d.classList.toggle('active', i === page));
            ctrl.querySelector('.page-label').textContent = `${page + 1} / ${numPages}`;
        });
    }

    function goToPage(idx, direction = 1) {
        if (idx < 0 || idx >= numPages) return;
        pages[currentPage].classList.remove('active', 'slide-left');
        currentPage = idx;
        pages[currentPage].classList.remove('slide-left');
        if (direction < 0) pages[currentPage].classList.add('slide-left');
        pages[currentPage].classList.add('active');
        updateControls(idx);
    }

    [ctrlTop, ctrlBot].forEach(ctrl => {
        ctrl.querySelector('.prev-btn').addEventListener('click', () => goToPage(currentPage - 1, -1));
        ctrl.querySelector('.next-btn').addEventListener('click', () => goToPage(currentPage + 1, 1));
        ctrl.querySelectorAll('.page-dot').forEach(dot => {
            dot.addEventListener('click', () => {
                const t = +dot.dataset.p;
                goToPage(t, t > currentPage ? 1 : -1);
            });
        });
    });

    /* ── GENRE FILTER ── */
    function showPaginated() {
        // Move all cards back to their original pages
        allCards.forEach(c => pages[+c.dataset.pageIdx].appendChild(c));
        flatGrid.style.display = 'none';
        container.style.display = '';
        pages.forEach((p, i) => p.classList.toggle('active', i === currentPage));
        ctrlTop.style.display = '';
        ctrlBot.style.display = '';
    }

    function showFiltered(genre) {
        // Move matching cards into flat grid
        pages.forEach(p => p.classList.remove('active'));
        container.style.display = 'none';
        flatGrid.style.display = '';
        flatGrid.innerHTML = '';
        allCards.filter(c => c.dataset.genre === genre).forEach(c => flatGrid.appendChild(c));
        ctrlTop.style.display = 'none';
        ctrlBot.style.display = 'none';
    }

    genreTabs.forEach(tab => {
        tab.addEventListener('click', function () {
            genreTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const genre = this.dataset.genre;
            if (genre === 'all') showPaginated();
            else showFiltered(genre);
        });
    });

    /* ── SORT ── */
    let originalOrder = [...allCards]; // bewaar originele volgorde
    let currentSort = 'rank';

    function sortCards(criterion) {
        const sorted = [...allCards];
        if (criterion === 'rank') {
            sorted.sort((a, b) => originalOrder.indexOf(a) - originalOrder.indexOf(b));
        } else if (criterion === 'enjoyability') {
            sorted.sort((a, b) => +b.dataset.enjoyability - +a.dataset.enjoyability);
        } else if (criterion === 'replayability') {
            sorted.sort((a, b) => +b.dataset.replayability - +a.dataset.replayability);
        } else if (criterion === 'playtime') {
            sorted.sort((a, b) => +b.dataset.playtime - +a.dataset.playtime);
        } else if (criterion === 'title') {
            sorted.sort((a, b) => a.dataset.title.localeCompare(b.dataset.title));
        } else if (criterion === 'year') {
            sorted.sort((a, b) => +b.dataset.year - +a.dataset.year);
        }

        // Herverdeeld gesorteerde cards over pages
        pages.forEach(p => { while (p.firstChild) p.firstChild.remove(); });
        sorted.forEach((c, i) => {
            c.dataset.pageIdx = Math.floor(i / CARDS_PER_PAGE);
            pages[Math.floor(i / CARDS_PER_PAGE)].appendChild(c);
        });

        // Update allCards volgorde (zodat genre filter ook gesorteerd is)
        allCards.length = 0;
        sorted.forEach(c => allCards.push(c));

        currentPage = 0;
        pages[0].classList.add('active');
        pages.forEach((p, i) => p.classList.toggle('active', i === 0));
        updateControls(0);
    }

    document.querySelectorAll('.sort-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentSort = this.dataset.sort;
            sortCards(currentSort);
        });
    });

    /* ── SORTABLE ── */
    initSortable([...pages, flatGrid]);

    /* ── INIT ── */
    pages[0].classList.add('active');
    updateControls(0);
})();

function initSortable(containers) {
    if (typeof Sortable === 'undefined') return;
    containers.forEach(container => {
        new Sortable(container, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            handle: '.game-card',
            onEnd: function () {
                // Collect IDs across ALL pages in order
                const ids = Array.from(document.querySelectorAll('.ranking-page .game-card-link, #ranking-grid .game-card-link'))
                    .map(el => el.dataset.id);
                fetch('save_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: 'rank', ids: ids })
                });
            }
        });
    });
}
</script>

</body>
</html>

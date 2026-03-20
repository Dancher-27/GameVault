<?php
$current = basename($_SERVER['PHP_SELF']);

// Fetch hover-card data when logged in
$_nav_hoverData = null;
if (isset($_SESSION['user_id']) && isset($conn)) {
    $nav_uid = (int)$_SESSION['user_id'];

    $nav_acct = $conn->query("SELECT avatar FROM account WHERE id = $nav_uid")->fetch_assoc();
    $nav_avatar = '';
    if (!empty($nav_acct['avatar'])) {
        $ap = __DIR__ . '/uploads/' . $nav_acct['avatar'];
        if (file_exists($ap)) $nav_avatar = 'uploads/' . $nav_acct['avatar'];
    }

    $nav_top3 = $conn->query("
        SELECT title, rating, cover_image FROM games
        WHERE user_id=$nav_uid AND status='played' AND (game_type='game' OR game_type IS NULL)
        ORDER BY IF(rank_order=0,999999,rank_order) ASC, rating DESC
        LIMIT 3
    ")->fetch_all(MYSQLI_ASSOC);

    $nav_stats = $conn->query("
        SELECT
            SUM(status='played')  AS played,
            SUM(status='playing') AS playing,
            SUM(status='backlog') AS backlog,
            ROUND(AVG(NULLIF(rating,0)),1) AS avg_r,
            SUM(playtime) AS hours
        FROM games WHERE user_id=$nav_uid AND (game_type='game' OR game_type IS NULL)
    ")->fetch_assoc();

    $_nav_hoverData = ['avatar' => $nav_avatar, 'top3' => $nav_top3, 'stats' => $nav_stats];
}
?>
<style>
.nav-profile-wrap {
    position: relative;
    display: inline-block;
}
.nav-profile-card {
    position: fixed;
    top: 68px;
    right: 16px;
    width: 280px;
    background: var(--white, #fff);
    border-radius: 16px;
    box-shadow: 0 8px 40px rgba(0,0,0,.22), 0 2px 8px rgba(0,0,0,.1);
    overflow: hidden;
    opacity: 0;
    pointer-events: none;
    transform: translateY(-8px) scale(.97);
    transition: opacity .22s ease, transform .22s ease;
    z-index: 9999;
}
.nav-profile-card.show {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0) scale(1);
}
/* little arrow pointing up */
.nav-profile-card::before {
    content: '';
    position: absolute;
    top: -7px;
    right: 22px;
    width: 14px; height: 14px;
    background: #7c3aed;
    transform: rotate(45deg);
    z-index: 1;
}
.npc-hero {
    background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 60%, #2563eb 100%);
    padding: 18px 18px 14px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.npc-avatar-img {
    width: 52px; height: 52px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255,255,255,.5);
    flex-shrink: 0;
}
.npc-avatar-ph {
    width: 52px; height: 52px;
    border-radius: 50%;
    background: rgba(255,255,255,.15);
    border: 3px solid rgba(255,255,255,.35);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.npc-name { color: #fff; font-weight: 800; font-size: .95rem; line-height: 1.2; }
.npc-sub  { color: rgba(255,255,255,.7); font-size: .72rem; margin-top: 2px; }
.npc-stats {
    display: flex;
    border-bottom: 1px solid var(--border, #e5e7eb);
    padding: 0;
}
.npc-stat {
    flex: 1;
    text-align: center;
    padding: 10px 4px;
    border-right: 1px solid var(--border, #e5e7eb);
}
.npc-stat:last-child { border-right: none; }
.npc-stat-val { font-size: 1rem; font-weight: 800; color: var(--purple, #7c3aed); display: block; }
.npc-stat-lbl { font-size: .6rem; color: var(--text-muted, #6b7280); text-transform: uppercase; letter-spacing: .05em; font-weight: 600; }
.npc-top3 { padding: 12px 14px; }
.npc-top3-title { font-size: .67rem; font-weight: 700; color: var(--text-muted, #9ca3af); text-transform: uppercase; letter-spacing: .07em; margin-bottom: 8px; }
.npc-game-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}
.npc-game-row:last-child { margin-bottom: 0; }
.npc-game-cover {
    width: 32px; height: 42px;
    border-radius: 4px;
    object-fit: cover;
    flex-shrink: 0;
}
.npc-game-cover-ph {
    width: 32px; height: 42px;
    border-radius: 4px;
    background: var(--purple-light, #ede9fe);
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem;
    flex-shrink: 0;
}
.npc-game-info { flex: 1; min-width: 0; }
.npc-game-name { font-size: .78rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.npc-game-rating { font-size: .68rem; color: var(--text-muted, #9ca3af); }
.npc-medal { font-size: .75rem; flex-shrink: 0; }
.npc-footer {
    border-top: 1px solid var(--border, #e5e7eb);
    padding: 10px 14px;
    text-align: center;
}
.npc-footer a {
    font-size: .75rem;
    font-weight: 700;
    color: var(--purple, #7c3aed);
    text-decoration: none;
}
.npc-footer a:hover { text-decoration: underline; }

/* ── MEER DROPDOWN ── */
.nav-meer-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
}
.nav-meer-btn {
    background: none;
    border: none;
    color: rgba(255,255,255,.70);
    font-size: .82rem;
    font-weight: 500;
    padding: 7px 13px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
    transition: background .15s, color .15s;
    font-family: inherit;
}
.nav-meer-btn:hover,
.nav-meer-btn.active,
.nav-meer-btn.open {
    background: rgba(255,255,255,.15);
    color: #fff;
}
.nav-meer-chevron {
    display: inline-block;
    transition: transform .2s ease;
    font-size: .7rem;
}
.nav-meer-btn.open .nav-meer-chevron { transform: rotate(180deg); }

.nav-dropdown {
    position: fixed;
    top: 64px;
    left: auto;
    min-width: 200px;
    background: var(--white, #fff);
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,.18), 0 2px 8px rgba(0,0,0,.08);
    padding: 6px;
    opacity: 0;
    pointer-events: none;
    transform: translateY(-6px) scale(.97);
    transform-origin: top left;
    transition: opacity .18s ease, transform .18s ease;
    z-index: 9998;
}
.nav-dropdown.open {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0) scale(1);
}
.nav-dropdown a {
    display: block;
    padding: 8px 14px;
    border-radius: 8px;
    color: var(--text, #1f2937);
    text-decoration: none;
    font-size: .83rem;
    font-weight: 500;
    transition: background .13s, color .13s;
    white-space: nowrap;
}
.nav-dropdown a:hover { background: var(--purple-light, #ede9fe); color: var(--purple, #7c3aed); }
.nav-dropdown a.dd-active { background: var(--purple, #7c3aed); color: #fff; font-weight: 700; }
.nav-dropdown a.dd-active:hover { background: #6d28d9; }
</style>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">
        <span class="brand-icon">🎮</span>
        GameVault
    </a>

    <?php
    $meerPages = ['edit_game.php','compare.php','tags.php','series.php','series_detail.php','timeline.php','bracket.php','blindbox.php','manage.php','delete.php'];
    $meerActive = in_array($current, $meerPages);
    ?>
    <div class="navbar-links">
        <a href="index.php"    <?= $current === 'index.php'    ? 'class="active"' : '' ?>>🏆 Ranking</a>
        <a href="wishlist.php" <?= $current === 'wishlist.php' ? 'class="active"' : '' ?>>🎯 Verlanglijst</a>
        <a href="search.php"   <?= $current === 'search.php'   ? 'class="active"' : '' ?>>🔍 Zoeken</a>
        <a href="add_game.php" <?= $current === 'add_game.php' ? 'class="active"' : '' ?>>➕ Toevoegen</a>
        <a href="stats.php"    <?= $current === 'stats.php'    ? 'class="active"' : '' ?>>📊 Stats</a>
        <a href="feed.php"    <?= $current === 'feed.php'     ? 'class="active"' : '' ?>>🌐 Feed</a>

        <!-- Meer dropdown -->
        <div class="nav-meer-wrap" id="nav-meer-wrap">
            <button class="nav-meer-btn <?= $meerActive ? 'active' : '' ?>" id="nav-meer-btn">
                ••• Meer <span class="nav-meer-chevron" id="nav-meer-chevron">▾</span>
            </button>
            <div class="nav-dropdown" id="nav-dropdown">
                <a href="edit_game.php"  <?= $current === 'edit_game.php'  ? 'class="dd-active"' : '' ?>>✏️ Bewerken</a>
                <a href="compare.php"    <?= $current === 'compare.php'    ? 'class="dd-active"' : '' ?>>⚔️ Vergelijken</a>
                <a href="tags.php"       <?= $current === 'tags.php'       ? 'class="dd-active"' : '' ?>>🏷️ Tags</a>
                <a href="series.php"     <?= in_array($current,['series.php','series_detail.php']) ? 'class="dd-active"' : '' ?>>🎮 Reeksen</a>
                <a href="timeline.php"   <?= $current === 'timeline.php'   ? 'class="dd-active"' : '' ?>>📅 Tijdlijn</a>
                <a href="bracket.php"    <?= $current === 'bracket.php'    ? 'class="dd-active"' : '' ?>>⚔️ Toernooi</a>
                <a href="blindbox.php"   <?= $current === 'blindbox.php'   ? 'class="dd-active"' : '' ?>>📦 Blind Box</a>
                <a href="manage.php"     <?= $current === 'manage.php'     ? 'class="dd-active"' : '' ?>>⚙️ Beheer</a>
                <a href="delete.php"     <?= $current === 'delete.php'     ? 'class="dd-active"' : '' ?>>🗑️ Verwijderen</a>
            </div>
        </div>

        <?php if (isset($_SESSION['username']) && $_nav_hoverData): ?>
        <div class="nav-profile-wrap" id="nav-profile-wrap">
            <a href="profile.php?user=<?= urlencode($_SESSION['username']) ?>" target="_blank">🌐 Profiel</a>

            <div class="nav-profile-card" id="nav-profile-card">
                <!-- Hero -->
                <div class="npc-hero">
                    <?php if ($_nav_hoverData['avatar']): ?>
                        <img src="<?= htmlspecialchars($_nav_hoverData['avatar']) ?>" class="npc-avatar-img">
                    <?php else: ?>
                        <div class="npc-avatar-ph">🎮</div>
                    <?php endif; ?>
                    <div>
                        <div class="npc-name"><?= htmlspecialchars($_SESSION['username']) ?></div>
                        <div class="npc-sub">GameVault profiel</div>
                    </div>
                </div>

                <!-- Stats strip -->
                <div class="npc-stats">
                    <div class="npc-stat">
                        <span class="npc-stat-val"><?= (int)($_nav_hoverData['stats']['played'] ?? 0) ?></span>
                        <span class="npc-stat-lbl">Gespeeld</span>
                    </div>
                    <div class="npc-stat">
                        <span class="npc-stat-val"><?= $_nav_hoverData['stats']['avg_r'] ?? '—' ?></span>
                        <span class="npc-stat-lbl">Gem. rating</span>
                    </div>
                    <div class="npc-stat">
                        <span class="npc-stat-val"><?= (int)($_nav_hoverData['stats']['hours'] ?? 0) ?>u</span>
                        <span class="npc-stat-lbl">Speeltijd</span>
                    </div>
                    <div class="npc-stat">
                        <span class="npc-stat-val"><?= (int)($_nav_hoverData['stats']['backlog'] ?? 0) ?></span>
                        <span class="npc-stat-lbl">Backlog</span>
                    </div>
                </div>

                <!-- Top 3 -->
                <?php if (!empty($_nav_hoverData['top3'])): ?>
                <div class="npc-top3">
                    <div class="npc-top3-title">Top games</div>
                    <?php
                    $medals = ['🥇','🥈','🥉'];
                    foreach ($_nav_hoverData['top3'] as $i => $g):
                        $img = 'uploads/' . $g['cover_image'];
                        $hasImg = !empty($g['cover_image']) && file_exists(__DIR__ . '/' . $img);
                    ?>
                    <div class="npc-game-row">
                        <?php if ($hasImg): ?>
                            <img src="<?= htmlspecialchars($img) ?>" class="npc-game-cover">
                        <?php else: ?>
                            <div class="npc-game-cover-ph">🎮</div>
                        <?php endif; ?>
                        <div class="npc-game-info">
                            <div class="npc-game-name" title="<?= htmlspecialchars($g['title']) ?>"><?= htmlspecialchars($g['title']) ?></div>
                            <div class="npc-game-rating">⭐ <?= number_format((float)$g['rating'], 1) ?>/10</div>
                        </div>
                        <div class="npc-medal"><?= $medals[$i] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Footer link -->
                <div class="npc-footer">
                    <a href="profile.php?user=<?= urlencode($_SESSION['username']) ?>" target="_blank">Bekijk volledig profiel →</a>
                </div>
            </div>
        </div>
        <?php elseif (isset($_SESSION['username'])): ?>
        <a href="profile.php?user=<?= urlencode($_SESSION['username']) ?>" target="_blank">🌐 Profiel</a>
        <?php endif; ?>

        <a href="Logout.php" class="nav-logout">🚪 Uitloggen</a>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-left:auto;">
        <button class="dark-toggle" id="darkToggle" onclick="toggleDark()" title="Dark/Light mode">🌙 Dark</button>
        <?php if (isset($_SESSION['username'])): ?>
        <div class="navbar-user" id="navbar-user-trigger">
            <div class="navbar-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
            <?= htmlspecialchars($_SESSION['username']) ?>
        </div>
        <?php endif; ?>
    </div>
</nav>
<script>
(function(){
    var theme = localStorage.getItem('gv_theme') || 'light';
    document.documentElement.setAttribute('data-theme', theme);
    var btn = document.getElementById('darkToggle');
    if (btn) btn.textContent = theme === 'dark' ? '☀️ Light' : '🌙 Dark';
})();
function toggleDark(){
    var cur = document.documentElement.getAttribute('data-theme');
    var next = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('gv_theme', next);
    document.getElementById('darkToggle').textContent = next === 'dark' ? '☀️ Light' : '🌙 Dark';
}

// Meer dropdown
(function(){
    var btn  = document.getElementById('nav-meer-btn');
    var drop = document.getElementById('nav-dropdown');
    var wrap = document.getElementById('nav-meer-wrap');
    if (!btn || !drop) return;

    function openDrop() {
        var rect = wrap.getBoundingClientRect();
        drop.style.left = rect.left + 'px';
        drop.classList.add('open');
        btn.classList.add('open');
    }
    function closeDrop() {
        drop.classList.remove('open');
        btn.classList.remove('open');
    }

    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        drop.classList.contains('open') ? closeDrop() : openDrop();
    });
    document.addEventListener('click', function(e) {
        if (!wrap.contains(e.target) && !drop.contains(e.target)) closeDrop();
    });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeDrop(); });
})();

// Profile hover card
(function(){
    var card = document.getElementById('nav-profile-card');
    if (!card) return;
    var showTimer, hideTimer;

    function showCard() {
        clearTimeout(hideTimer);
        showTimer = setTimeout(function(){ card.classList.add('show'); }, 120);
    }
    function hideCard() {
        clearTimeout(showTimer);
        hideTimer = setTimeout(function(){ card.classList.remove('show'); }, 200);
    }

    // Trigger from profile link in navbar-links
    var wrap = document.getElementById('nav-profile-wrap');
    if (wrap) {
        wrap.addEventListener('mouseenter', showCard);
        wrap.addEventListener('mouseleave', hideCard);
    }

    // Trigger from user avatar in top-right
    var userTrigger = document.getElementById('navbar-user-trigger');
    if (userTrigger) {
        userTrigger.style.cursor = 'pointer';
        userTrigger.addEventListener('mouseenter', showCard);
        userTrigger.addEventListener('mouseleave', hideCard);
    }

    card.addEventListener('mouseenter', function(){ clearTimeout(hideTimer); });
    card.addEventListener('mouseleave', hideCard);
})();
</script>

<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once 'connection.php';
require_once 'classes/Game.php';

$uid     = (int)$_SESSION['user_id'];
$gameObj = new Game($conn, $uid);
$feed    = $gameObj->getFeedActivity(80);
$following = $gameObj->getFollowing($uid);

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'zojuist';
    if ($diff < 3600)   return (int)($diff/60) . ' min geleden';
    if ($diff < 86400)  return (int)($diff/3600) . ' uur geleden';
    if ($diff < 604800) return (int)($diff/86400) . ' dag' . ((int)($diff/86400)>1?'en':'') . ' geleden';
    return date('d M Y', strtotime($datetime));
}

$statusLabels = ['played'=>'Gespeeld','playing'=>'Bezig met','backlog'=>'Backlog','wishlist'=>'Verlanglijst'];

function activityText(array $item): string {
    global $statusLabels;
    $title = '<strong>' . htmlspecialchars($item['game_title']) . '</strong>';
    switch ($item['action']) {
        case 'game_added':
            $lbl = $statusLabels[$item['meta']] ?? $item['meta'];
            return "heeft $title toegevoegd" . ($item['meta'] ? " aan <em>$lbl</em>" : '');
        case 'status_changed':
            $parts = explode('→', $item['meta']);
            $from  = $statusLabels[$parts[0] ?? ''] ?? ($parts[0] ?? '');
            $to    = $statusLabels[$parts[1] ?? ''] ?? ($parts[1] ?? '');
            return "heeft $title verplaatst van <em>$from</em> naar <em>$to</em>";
        case 'rating_changed':
            $parts = explode('→', $item['meta']);
            return "heeft $title een rating van <em>{$parts[1]}/10</em> gegeven";
        case 'game_removed':
            return "heeft $title verwijderd";
        default:
            return "heeft iets gedaan met $title";
    }
}

function activityIcon(string $action): string {
    return match($action) {
        'game_added'     => '➕',
        'status_changed' => '🔄',
        'rating_changed' => '⭐',
        'game_removed'   => '🗑️',
        default          => '🎮',
    };
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Feed</title>
    <link rel="stylesheet" href="styles.css">
    <style>
    .feed-layout {
        display: grid;
        grid-template-columns: 1fr 280px;
        gap: 28px;
        align-items: start;
    }
    @media(max-width:860px){ .feed-layout { grid-template-columns: 1fr; } }

    /* Feed items */
    .feed-item {
        display: flex;
        gap: 14px;
        padding: 18px 0;
        border-bottom: 1px solid var(--border);
        animation: fadeIn .3s ease;
    }
    @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }
    .feed-avatar {
        width: 44px; height: 44px;
        border-radius: 50%;
        flex-shrink: 0;
        object-fit: cover;
    }
    .feed-avatar-ph {
        width: 44px; height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--purple,#7c3aed), #4f46e5);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 800; font-size: 1rem;
        flex-shrink: 0;
    }
    .feed-body { flex: 1; min-width: 0; }
    .feed-user {
        font-weight: 700; font-size: .88rem;
        color: var(--purple,#7c3aed);
        text-decoration: none;
    }
    .feed-user:hover { text-decoration: underline; }
    .feed-text { font-size: .88rem; line-height: 1.5; margin: 2px 0 4px; }
    .feed-time { font-size: .72rem; color: var(--text-muted,#9ca3af); }
    .feed-icon {
        width: 36px; height: 36px;
        border-radius: 10px;
        background: var(--surface,#f9fafb);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
        align-self: center;
    }

    /* Empty state */
    .feed-empty {
        text-align: center;
        padding: 60px 24px;
        color: var(--text-muted);
    }
    .feed-empty-icon { font-size: 3.5rem; margin-bottom: 16px; }
    .feed-empty h3 { margin: 0 0 8px; font-size: 1.1rem; color: var(--text); }
    .feed-empty p { font-size: .88rem; margin: 0; }

    /* Sidebar */
    .sidebar-card {
        background: var(--white,#fff);
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,.07);
        overflow: hidden;
        margin-bottom: 20px;
    }
    .sidebar-card-header {
        display: flex; align-items: center; gap: 10px;
        padding: 16px 20px 14px;
        border-bottom: 1px solid var(--border);
    }
    .sidebar-card-header-icon {
        width: 32px; height: 32px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--purple,#7c3aed), #4f46e5);
        display: flex; align-items: center; justify-content: center;
        font-size: .9rem;
        flex-shrink: 0;
    }
    .sidebar-card-header h3 {
        margin: 0;
        font-size: .82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--text-muted);
    }
    .sidebar-card-body { padding: 14px 20px 16px; }

    .following-row {
        display: flex; align-items: center; gap: 12px;
        padding: 9px 20px;
        text-decoration: none;
        color: inherit;
        transition: background .13s;
        border-bottom: 1px solid var(--border);
    }
    .following-row:last-child { border-bottom: none; }
    .following-row:hover { background: var(--surface,#f9fafb); }
    .following-row:hover .following-name { color: var(--purple,#7c3aed); }

    .following-av {
        width: 38px; height: 38px; border-radius: 50%;
        background: linear-gradient(135deg, var(--purple,#7c3aed), #4f46e5);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 800; font-size: .88rem; flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(124,58,237,.25);
    }
    .following-av img { width:38px;height:38px;border-radius:50%;object-fit:cover; }
    .following-name { font-size: .88rem; font-weight: 600; transition: color .13s; }
    .following-arrow { margin-left: auto; font-size: .7rem; color: var(--text-muted); opacity: 0; transition: opacity .13s; }
    .following-row:hover .following-arrow { opacity: 1; }

    /* Search users */
    .user-search-wrap { position: relative; }
    .user-search-input {
        width: 100%; box-sizing: border-box;
        border: 1.5px solid var(--border,#e5e7eb);
        border-radius: 10px;
        padding: 9px 14px 9px 38px;
        font-size: .85rem;
        background: var(--surface,#f9fafb);
        color: var(--text);
        outline: none;
        transition: border-color .2s, box-shadow .2s;
    }
    .user-search-input:focus {
        border-color: var(--purple,#7c3aed);
        box-shadow: 0 0 0 3px rgba(124,58,237,.12);
        background: var(--white,#fff);
    }
    .user-search-icon {
        position: absolute; left: 12px; top: 50%;
        transform: translateY(-50%);
        font-size: .85rem; pointer-events: none;
        color: var(--text-muted);
    }
    .user-results {
        position: absolute; top: calc(100% + 6px); left: 0; right: 0;
        background: var(--white,#fff);
        border-radius: 12px;
        box-shadow: 0 8px 28px rgba(0,0,0,.13);
        z-index: 100;
        overflow: hidden;
        display: none;
        border: 1px solid var(--border);
    }
    .user-results.show { display: block; }
    .user-result-row {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 14px;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        transition: background .13s;
        border-bottom: 1px solid var(--border);
    }
    .user-result-row:last-child { border-bottom: none; }
    .user-result-row:hover { background: var(--surface); }
    .user-result-av {
        width: 32px; height: 32px; border-radius: 50%;
        background: linear-gradient(135deg, var(--purple,#7c3aed), #4f46e5);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 800; font-size: .78rem; flex-shrink: 0;
    }

    /* Empty following */
    .following-empty {
        padding: 20px;
        text-align: center;
        color: var(--text-muted);
        font-size: .82rem;
        line-height: 1.6;
    }
    .following-empty-icon { font-size: 1.8rem; margin-bottom: 8px; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="page-wrapper">
    <div class="section-header" style="margin-bottom:24px;">
        <h1 class="page-title" style="margin:0;">🌐 Feed</h1>
        <span style="font-size:.82rem;color:var(--text-muted);">Activiteit van mensen die je volgt</span>
    </div>

    <div class="feed-layout">
        <!-- MAIN FEED -->
        <div>
            <?php if (empty($feed)): ?>
            <div class="feed-empty">
                <div class="feed-empty-icon">🎮</div>
                <h3>Je feed is leeg</h3>
                <p>Volg andere spelers via hun profiel om hun activiteit hier te zien.</p>
            </div>
            <?php else: ?>
            <?php foreach ($feed as $item):
                $avatarSrc = '';
                if (!empty($item['avatar'])) {
                    $ap = 'uploads/' . $item['avatar'];
                    if (file_exists(__DIR__ . '/' . $ap)) $avatarSrc = $ap;
                }
            ?>
            <div class="feed-item">
                <div class="feed-icon"><?= activityIcon($item['action']) ?></div>
                <div class="feed-body">
                    <div class="feed-text">
                        <a href="profile.php?user=<?= urlencode($item['username']) ?>" class="feed-user"><?= htmlspecialchars($item['username']) ?></a>
                        <?= activityText($item) ?>
                    </div>
                    <div class="feed-time"><?= timeAgo($item['created_at']) ?></div>
                </div>
                <?php if ($avatarSrc): ?>
                    <img src="<?= htmlspecialchars($avatarSrc) ?>" class="feed-avatar">
                <?php else: ?>
                    <div class="feed-avatar-ph"><?= strtoupper(substr($item['username'],0,1)) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- SIDEBAR -->
        <div>
            <!-- Gebruiker zoeken -->
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <div class="sidebar-card-header-icon">🔍</div>
                    <h3>Spelers zoeken</h3>
                </div>
                <div class="sidebar-card-body">
                    <div class="user-search-wrap">
                        <span class="user-search-icon">🔍</span>
                        <input type="text" id="user-search" class="user-search-input" placeholder="Zoek op gebruikersnaam…" autocomplete="off">
                        <div class="user-results" id="user-results"></div>
                    </div>
                </div>
            </div>

            <!-- Volgend -->
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <div class="sidebar-card-header-icon">👥</div>
                    <h3>Je volgt<?= !empty($following) ? ' (' . count($following) . ')' : '' ?></h3>
                </div>
                <?php if (!empty($following)): ?>
                <?php foreach ($following as $f):
                    $fAv = '';
                    if (!empty($f['avatar'])) {
                        $ap = 'uploads/' . $f['avatar'];
                        if (file_exists(__DIR__ . '/' . $ap)) $fAv = $ap;
                    }
                ?>
                <a href="profile.php?user=<?= urlencode($f['username']) ?>" class="following-row">
                    <div class="following-av">
                        <?php if ($fAv): ?><img src="<?= htmlspecialchars($fAv) ?>"><?php else: ?><?= strtoupper(substr($f['username'],0,1)) ?><?php endif; ?>
                    </div>
                    <div class="following-name"><?= htmlspecialchars($f['username']) ?></div>
                    <span class="following-arrow">›</span>
                </a>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="following-empty">
                    <div class="following-empty-icon">👤</div>
                    Je volgt nog niemand.<br>Zoek een speler hierboven.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// User search
const searchInput  = document.getElementById('user-search');
const resultsBox   = document.getElementById('user-results');
let searchTimer;

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 2) { resultsBox.classList.remove('show'); return; }
    searchTimer = setTimeout(() => {
        fetch('search_users.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (!data.length) { resultsBox.innerHTML = '<div style="padding:12px 14px;font-size:.82rem;color:var(--text-muted)">Geen resultaten</div>'; }
                else {
                    resultsBox.innerHTML = data.map(u =>
                        `<a href="profile.php?user=${encodeURIComponent(u.username)}" class="user-result-row">
                            <div class="user-result-av">${u.username[0].toUpperCase()}</div>
                            <span style="font-size:.85rem;font-weight:600">${u.username}</span>
                            <span style="margin-left:auto;font-size:.72rem;color:var(--purple)">Bekijk profiel →</span>
                        </a>`
                    ).join('');
                }
                resultsBox.classList.add('show');
            });
    }, 300);
});

document.addEventListener('click', e => {
    if (!searchInput.contains(e.target)) resultsBox.classList.remove('show');
});
</script>
</body>
</html>

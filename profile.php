<?php
session_start();
require_once 'connection.php';

$requestedUser = trim($_GET['user'] ?? '');
$isOwn = false;

if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id, username, avatar, bio FROM account WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $selfUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($requestedUser === '' || ($selfUser && strtolower(preg_replace('/[^\w]/u', '', $requestedUser)) === strtolower(preg_replace('/[^\w]/u', '', $selfUser['username'])))) {
        $profileUser = $selfUser;
        $isOwn = true;
    } else {
        $stmt = $conn->prepare("SELECT id, username, avatar, bio FROM account WHERE username = ?");
        $stmt->bind_param("s", $requestedUser);
        $stmt->execute();
        $profileUser = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$profileUser) { http_response_code(404); die('<p style="font-family:sans-serif;padding:40px">Gebruiker niet gevonden.</p>'); }
    }
} elseif ($requestedUser !== '') {
    $stmt = $conn->prepare("SELECT id, username, avatar, bio FROM account WHERE username = ?");
    $stmt->bind_param("s", $requestedUser);
    $stmt->execute();
    $profileUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$profileUser) { http_response_code(404); die('<p style="font-family:sans-serif;padding:40px">Gebruiker niet gevonden.</p>'); }
} else {
    header('Location: login.php'); exit();
}

$uid = (int)$profileUser['id'];
$msg = '';
$msgType = 'success';

// ── Handle profile edit (own only) ───────────────────────────────────────────
if ($isOwn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $newUsername = trim($_POST['username'] ?? '');
        $newBio      = trim($_POST['bio'] ?? '');
        $newBio      = mb_substr($newBio, 0, 500);

        // Avatar upload
        $avatarFile = null;
        if (!empty($_FILES['avatar']['tmp_name'])) {
            $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            if (in_array($_FILES['avatar']['type'], $allowed) && $_FILES['avatar']['size'] < 3 * 1024 * 1024) {
                $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $dir = __DIR__ . '/uploads/avatars/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $avatarFile = 'avatars/' . $uid . '.' . $ext;
                move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . $uid . '.' . $ext);
            }
        }

        if ($newUsername !== '' && $newUsername !== $profileUser['username']) {
            // Check uniqueness
            $chk = $conn->prepare("SELECT id FROM account WHERE username = ? AND id != ?");
            $chk->bind_param("si", $newUsername, $uid);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $msg = 'Deze gebruikersnaam is al in gebruik.';
                $msgType = 'error';
                $newUsername = $profileUser['username'];
            }
            $chk->close();
        } else {
            $newUsername = $profileUser['username'];
        }

        if ($msg === '') {
            if ($avatarFile) {
                $stmt = $conn->prepare("UPDATE account SET username=?, bio=?, avatar=? WHERE id=?");
                $stmt->bind_param("sssi", $newUsername, $newBio, $avatarFile, $uid);
            } else {
                $stmt = $conn->prepare("UPDATE account SET username=?, bio=? WHERE id=?");
                $stmt->bind_param("ssi", $newUsername, $newBio, $uid);
            }
            $stmt->execute();
            $stmt->close();
            $_SESSION['username'] = $newUsername;
            $profileUser['username'] = $newUsername;
            $profileUser['bio']      = $newBio;
            if ($avatarFile) $profileUser['avatar'] = $avatarFile;
            $msg = 'Profiel opgeslagen!';
        }
    }

    if ($action === 'remove_avatar') {
        $stmt = $conn->prepare("UPDATE account SET avatar=NULL WHERE id=?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();
        $profileUser['avatar'] = null;
        $msg = 'Profielfoto verwijderd.';
    }
}

// ── Social data ───────────────────────────────────────────────────────────────
require_once 'classes/Game.php';
$profileGameObj  = new Game($conn, $uid);
$followerCount   = $profileGameObj->getFollowerCount($uid);
$followingCount  = $profileGameObj->getFollowingCount($uid);
$profileActivity = $profileGameObj->getActivity(15);

$isFollowing = false;
if (!$isOwn && isset($_SESSION['user_id'])) {
    $selfGameObj = new Game($conn, (int)$_SESSION['user_id']);
    $isFollowing = $selfGameObj->isFollowing($uid);
}

// ── Data ─────────────────────────────────────────────────────────────────────
$statsRow = $conn->query("
    SELECT SUM(status='played') AS played, SUM(status='playing') AS playing,
           SUM(status='backlog') AS backlog, SUM(status='wishlist') AS wishlist,
           ROUND(AVG(NULLIF(rating,0)),1) AS avg_rating, SUM(playtime) AS total_hours,
           COUNT(*) AS total
    FROM games WHERE user_id=$uid AND (game_type='game' OR game_type IS NULL)
")->fetch_assoc();

$played = $conn->query("
    SELECT * FROM games WHERE user_id=$uid AND status='played' AND (game_type='game' OR game_type IS NULL)
    ORDER BY IF(rank_order=0,999999,rank_order) ASC, rating DESC
")->fetch_all(MYSQLI_ASSOC);

$topGenres = $conn->query("
    SELECT genre, COUNT(*) AS cnt FROM games
    WHERE user_id=$uid AND status='played' AND genre!='' AND (game_type='game' OR game_type IS NULL)
    GROUP BY genre ORDER BY cnt DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Avatar path
$avatarPath = '';
if (!empty($profileUser['avatar'])) {
    $ap = 'uploads/' . $profileUser['avatar'];
    if (file_exists(__DIR__ . '/' . $ap)) $avatarPath = $ap;
}

$profileUrl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?user=' . urlencode($profileUser['username']);

function starsHtml(float $r): string {
    $f = (int)round($r/2); $h = '';
    for ($i=1;$i<=5;$i++) $h .= $i<=$f ? '<span style="color:#f59e0b">★</span>' : '<span style="color:#d1d5db">★</span>';
    return $h;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profileUser['username']) ?>'s GameVault</title>
    <link rel="stylesheet" href="styles.css">
    <style>
    body { background: var(--bg, #f3f4f6); }

    /* ── HERO ── */
    .profile-hero {
        background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 50%, #2563eb 100%);
        color: #fff;
        padding: 56px 24px 0;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .profile-hero::before {
        content: '';
        position: absolute; inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='4'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .hero-inner { position: relative; max-width: 1200px; margin: 0 auto; padding-bottom: 80px; }

    .avatar-wrap { position: relative; display: inline-block; margin-bottom: 16px; }
    .profile-avatar-img {
        width: 110px; height: 110px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid rgba(255,255,255,.6);
        box-shadow: 0 8px 32px rgba(0,0,0,.25);
        display: block;
    }
    .profile-avatar-placeholder {
        width: 110px; height: 110px;
        border-radius: 50%;
        background: rgba(255,255,255,.15);
        border: 4px solid rgba(255,255,255,.4);
        display: flex; align-items: center; justify-content: center;
        font-size: 3rem;
        box-shadow: 0 8px 32px rgba(0,0,0,.2);
    }
    .avatar-edit-btn {
        position: absolute; bottom: 4px; right: 4px;
        width: 30px; height: 30px;
        border-radius: 50%;
        background: #fff;
        color: #7c3aed;
        border: none;
        cursor: pointer;
        font-size: .85rem;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 2px 8px rgba(0,0,0,.2);
    }
    .profile-name { font-size: 2rem; font-weight: 800; margin-bottom: 4px; letter-spacing: -.02em; }
    .profile-bio { opacity: .8; font-size: .95rem; margin-bottom: 8px; max-width: 480px; margin-left: auto; margin-right: auto; line-height: 1.5; }
    .profile-share-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 16px;
        border-radius: 20px;
        border: 1.5px solid rgba(255,255,255,.4);
        background: rgba(255,255,255,.1);
        color: #fff;
        font-size: .78rem;
        font-weight: 600;
        cursor: pointer;
        backdrop-filter: blur(4px);
        transition: background .2s;
        margin-top: 8px;
    }
    .profile-share-btn:hover { background: rgba(255,255,255,.2); }

    /* ── STATS CARDS (floating) ── */
    .profile-stats-row {
        display: flex;
        gap: 16px;
        justify-content: center;
        flex-wrap: wrap;
        position: relative;
        margin: -40px auto 32px;
        max-width: 900px;
        padding: 0 24px;
        z-index: 10;
    }
    .profile-stat-card {
        background: var(--white, #fff);
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(0,0,0,.1);
        padding: 20px 28px;
        text-align: center;
        flex: 1;
        min-width: 120px;
    }
    .ps-val { font-size: 2rem; font-weight: 800; color: var(--purple, #7c3aed); display: block; }
    .ps-lbl { font-size: .72rem; color: var(--text-muted, #6b7280); text-transform: uppercase; letter-spacing: .06em; font-weight: 600; margin-top: 2px; display: block; }

    /* ── EDIT PANEL ── */
    .edit-panel {
        background: var(--white, #fff);
        border-radius: 16px;
        box-shadow: 0 2px 16px rgba(0,0,0,.07);
        padding: 28px 32px;
        margin-bottom: 32px;
        display: none;
    }
    .edit-panel.open { display: block; }
    .edit-panel h3 { margin: 0 0 20px; font-size: 1.05rem; }
    .edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media(max-width:600px){ .edit-grid { grid-template-columns: 1fr; } }
    .edit-avatar-preview {
        width: 80px; height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--border);
        margin-bottom: 8px;
    }
    .edit-avatar-ph {
        width: 80px; height: 80px;
        border-radius: 50%;
        background: var(--purple-light, #ede9fe);
        display: flex; align-items: center; justify-content: center;
        font-size: 2rem;
        border: 3px solid var(--border);
        margin-bottom: 8px;
    }

    /* ── GAME GRID ── */
    .pub-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 16px; }
    .pub-card {
        background: var(--white, #fff);
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,.07);
        overflow: hidden;
        transition: transform .2s, box-shadow .2s;
        position: relative;
    }
    .pub-card:hover { transform: translateY(-5px); box-shadow: 0 8px 24px rgba(0,0,0,.13); }
    .pub-card img { width:100%; aspect-ratio:2/3; object-fit:cover; display:block; }
    .pub-card-placeholder { width:100%; aspect-ratio:2/3; background:linear-gradient(135deg,#ede9fe,#dbeafe); display:flex;align-items:center;justify-content:center;font-size:2.5rem; }
    .pub-card-body { padding: 10px 12px 12px; }
    .pub-card-rank { font-size:.68rem; font-weight:800; color:var(--purple,#7c3aed); margin-bottom:2px; text-transform:uppercase; letter-spacing:.04em; }
    .pub-card-title { font-size:.82rem; font-weight:700; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .pub-card-rating { font-size:.75rem; color:var(--text-muted,#6b7280); }
    .rank-badge-overlay {
        position: absolute; top: 8px; left: 8px;
        padding: 2px 8px; border-radius: 6px;
        font-size: .7rem; font-weight: 800; color: #fff;
    }
    .rank-1 { background: #f59e0b; }
    .rank-2 { background: #9ca3af; }
    .rank-3 { background: #b45309; }

    /* ── GENRE BARS ── */
    .genre-bar-row { display:flex; align-items:center; gap:12px; margin-bottom:10px; }
    .genre-bar-name { width: 100px; font-size:.82rem; font-weight:600; flex-shrink:0; }
    .genre-bar-track { flex:1; height:8px; background:var(--border,#e5e7eb); border-radius:4px; overflow:hidden; }
    .genre-bar-fill { height:100%; background:linear-gradient(90deg,#7c3aed,#4f46e5); border-radius:4px; transition:width .6s ease; }
    .genre-bar-count { width:30px; text-align:right; font-size:.78rem; color:var(--text-muted); flex-shrink:0; }

    /* ── OWN BADGE ── */
    .own-badge {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 10px; border-radius: 20px;
        background: rgba(255,255,255,.15);
        font-size: .72rem; font-weight: 600;
        margin-bottom: 10px;
    }

    .alert-success { background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:.88rem; }
    .alert-error   { background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:.88rem; }
    </style>
</head>
<body>
<?php if ($isOwn): include 'navbar.php'; endif; ?>

<!-- HERO -->
<div class="profile-hero">
    <div class="hero-inner">
        <?php if ($isOwn): ?>
        <div class="own-badge">✏️ Jouw profiel</div><br>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="avatar-form" style="display:inline;">
            <input type="hidden" name="action" value="update_profile">
            <div class="avatar-wrap">
                <?php if ($avatarPath): ?>
                    <img src="<?= htmlspecialchars($avatarPath) ?>" class="profile-avatar-img" id="avatar-preview">
                <?php else: ?>
                    <div class="profile-avatar-placeholder" id="avatar-preview-ph">🎮</div>
                <?php endif; ?>
                <?php if ($isOwn): ?>
                <label class="avatar-edit-btn" title="Foto wijzigen" for="avatar-input">📷</label>
                <input type="file" id="avatar-input" name="avatar" accept="image/*" style="display:none">
                <?php endif; ?>
            </div>
        </form>

        <div class="profile-name"><?= htmlspecialchars($profileUser['username']) ?></div>

        <?php if (!empty($profileUser['bio'])): ?>
        <div class="profile-bio"><?= htmlspecialchars($profileUser['bio']) ?></div>
        <?php elseif ($isOwn): ?>
        <div class="profile-bio" style="opacity:.5;font-style:italic;">Voeg een bio toe via Profiel bewerken</div>
        <?php endif; ?>

        <!-- Follower counts -->
        <div style="display:flex;gap:24px;justify-content:center;margin:12px 0 4px;">
            <div style="text-align:center;">
                <div style="font-size:1.2rem;font-weight:800;" id="follower-count"><?= $followerCount ?></div>
                <div style="font-size:.7rem;opacity:.7;text-transform:uppercase;letter-spacing:.05em;">Volgers</div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:1.2rem;font-weight:800;"><?= $followingCount ?></div>
                <div style="font-size:.7rem;opacity:.7;text-transform:uppercase;letter-spacing:.05em;">Volgend</div>
            </div>
        </div>

        <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-top:12px;">
            <button class="profile-share-btn" onclick="copyProfileLink()">🔗 Kopieer profiellink</button>
            <?php if ($isOwn): ?>
            <button class="profile-share-btn" onclick="toggleEdit()">✏️ Profiel bewerken</button>
            <?php elseif (isset($_SESSION['user_id'])): ?>
            <button class="profile-share-btn" id="follow-btn"
                    data-target="<?= $uid ?>"
                    data-following="<?= $isFollowing ? '1' : '0' ?>"
                    onclick="toggleFollow(this)">
                <?= $isFollowing ? '✅ Volgend' : '➕ Volgen' ?>
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- FLOATING STATS -->
<div class="profile-stats-row">
    <div class="profile-stat-card">
        <span class="ps-val"><?= (int)($statsRow['played'] ?? 0) ?></span>
        <span class="ps-lbl">Gespeeld</span>
    </div>
    <div class="profile-stat-card">
        <span class="ps-val"><?= $statsRow['avg_rating'] ?? '—' ?></span>
        <span class="ps-lbl">Gem. rating</span>
    </div>
    <div class="profile-stat-card">
        <span class="ps-val"><?= (int)($statsRow['total_hours'] ?? 0) ?>u</span>
        <span class="ps-lbl">Speeltijd</span>
    </div>
    <div class="profile-stat-card">
        <span class="ps-val"><?= (int)($statsRow['backlog'] ?? 0) ?></span>
        <span class="ps-lbl">Backlog</span>
    </div>
    <div class="profile-stat-card">
        <span class="ps-val"><?= (int)($statsRow['wishlist'] ?? 0) ?></span>
        <span class="ps-lbl">Verlanglijst</span>
    </div>
</div>

<div class="page-wrapper" style="padding-top:0;">

    <?php if ($msg): ?>
    <div class="alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- EDIT PANEL -->
    <?php if ($isOwn): ?>
    <div class="edit-panel" id="edit-panel">
        <h3>✏️ Profiel bewerken</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">
            <div class="edit-grid">
                <div>
                    <label class="form-label">Gebruikersnaam</label>
                    <input type="text" name="username" class="form-control"
                           value="<?= htmlspecialchars($profileUser['username']) ?>"
                           maxlength="100" style="margin-bottom:12px;">
                    <label class="form-label">Bio <small style="color:var(--text-muted)">(max 500 tekens)</small></label>
                    <textarea name="bio" class="form-control" rows="3" maxlength="500"
                              style="resize:vertical;"><?= htmlspecialchars($profileUser['bio'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="form-label">Profielfoto</label>
                    <?php if ($avatarPath): ?>
                        <img src="<?= htmlspecialchars($avatarPath) ?>" class="edit-avatar-preview"><br>
                    <?php else: ?>
                        <div class="edit-avatar-ph">🎮</div>
                    <?php endif; ?>
                    <input type="file" name="avatar" accept="image/*" class="form-control" style="margin-top:6px;">
                    <small style="color:var(--text-muted);">Max 3MB — JPG, PNG, WebP</small>
                    <?php if ($avatarPath): ?>
                    <br><a href="?action=remove_avatar" onclick="return confirm('Profielfoto verwijderen?')"
                           style="font-size:.78rem;color:#ef4444;">Foto verwijderen</a>
                    <?php endif; ?>
                </div>
            </div>
            <div style="margin-top:20px;display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" style="padding:8px 24px;">Opslaan</button>
                <button type="button" class="btn btn-secondary" onclick="toggleEdit()" style="padding:8px 16px;">Annuleren</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- RANKING -->
    <?php if (!empty($played)): ?>
    <div class="section-block">
        <div class="section-header">
            <h2>🏆 Ranking</h2>
            <span class="section-count"><?= count($played) ?> games</span>
        </div>
        <div class="pub-grid">
            <?php foreach ($played as $rank => $game):
                $img = 'uploads/' . $game['cover_image'];
                $hasImg = !empty($game['cover_image']) && file_exists(__DIR__ . '/' . $img);
                $badgeClass = $rank === 0 ? 'rank-1' : ($rank === 1 ? 'rank-2' : ($rank === 2 ? 'rank-3' : ''));
                $badgeLabel = $rank === 0 ? '🥇' : ($rank === 1 ? '🥈' : ($rank === 2 ? '🥉' : '#' . ($rank+1)));
            ?>
            <div class="pub-card">
                <?php if ($hasImg): ?>
                    <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($game['title']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="pub-card-placeholder">🎮</div>
                <?php endif; ?>
                <?php if ($rank < 3): ?>
                <div class="rank-badge-overlay <?= $badgeClass ?>"><?= $badgeLabel ?></div>
                <?php endif; ?>
                <div class="pub-card-body">
                    <div class="pub-card-rank"><?= $rank >= 3 ? '#'.($rank+1) : '' ?></div>
                    <div class="pub-card-title" title="<?= htmlspecialchars($game['title']) ?>"><?= htmlspecialchars($game['title']) ?></div>
                    <div class="pub-card-rating"><?= starsHtml((float)$game['rating']) ?> <?= number_format((float)$game['rating'],1) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- GENRE VERDELING -->
    <?php if (!empty($topGenres)): ?>
    <div class="section-block">
        <div class="section-header"><h2>🎯 Favoriete genres</h2></div>
        <?php $maxCnt = max(array_column($topGenres, 'cnt')); ?>
        <?php foreach ($topGenres as $g): ?>
        <div class="genre-bar-row">
            <div class="genre-bar-name"><?= htmlspecialchars($g['genre']) ?></div>
            <div class="genre-bar-track">
                <div class="genre-bar-fill" style="width:<?= round($g['cnt'] / $maxCnt * 100) ?>%"></div>
            </div>
            <div class="genre-bar-count"><?= $g['cnt'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ACTIVITEIT -->
    <?php if (!empty($profileActivity)): ?>
    <div class="section-block">
        <div class="section-header"><h2>📋 Recente activiteit</h2></div>
        <?php
        $actLabels = ['played'=>'Gespeeld','playing'=>'Bezig met','backlog'=>'Backlog','wishlist'=>'Verlanglijst'];
        $actIcons  = ['game_added'=>'➕','status_changed'=>'🔄','rating_changed'=>'⭐','game_removed'=>'🗑️'];
        foreach ($profileActivity as $item):
            $icon = $actIcons[$item['action']] ?? '🎮';
            $diff = time() - strtotime($item['created_at']);
            if ($diff < 60)          $ago = 'zojuist';
            elseif ($diff < 3600)    $ago = (int)($diff/60) . ' min geleden';
            elseif ($diff < 86400)   $ago = (int)($diff/3600) . ' uur geleden';
            elseif ($diff < 604800)  $ago = (int)($diff/86400) . ' dag' . ((int)($diff/86400)>1?'en':'') . ' geleden';
            else                     $ago = date('d M Y', strtotime($item['created_at']));
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
            <div style="width:34px;height:34px;border-radius:10px;background:var(--surface);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;"><?= $icon ?></div>
            <div style="flex:1;min-width:0;">
                <?php
                $t = '<strong>' . htmlspecialchars($item['game_title']) . '</strong>';
                switch ($item['action']) {
                    case 'game_added':
                        $lbl = $actLabels[$item['meta']] ?? $item['meta'];
                        echo "heeft $t toegevoegd aan <em>$lbl</em>";
                        break;
                    case 'status_changed':
                        $parts = explode('→', $item['meta']);
                        $from = $actLabels[$parts[0]??''] ?? ($parts[0]??'');
                        $to   = $actLabels[$parts[1]??''] ?? ($parts[1]??'');
                        echo "heeft $t verplaatst van <em>$from</em> naar <em>$to</em>";
                        break;
                    case 'rating_changed':
                        $parts = explode('→', $item['meta']);
                        echo "heeft $t een rating van <em>{$parts[1]}/10</em> gegeven";
                        break;
                    case 'game_removed':
                        echo "heeft $t verwijderd";
                        break;
                }
                ?>
            </div>
            <div style="font-size:.72rem;color:var(--text-muted);flex-shrink:0;"><?= $ago ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <p style="text-align:center;color:var(--text-muted,#9ca3af);font-size:.8rem;margin-top:40px;">
        Gebouwd met <strong>GameVault</strong>
        <?php if (!$isOwn): ?> · <a href="login.php">Maak je eigen profiel</a><?php endif; ?>
    </p>
</div>

<input type="hidden" id="profile-url" value="<?= htmlspecialchars($profileUrl) ?>">

<script>
function toggleEdit() {
    const panel = document.getElementById('edit-panel');
    panel.classList.toggle('open');
    if (panel.classList.contains('open')) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function copyProfileLink() {
    const url = document.getElementById('profile-url').value;
    navigator.clipboard.writeText(url).then(() => {
        const btn = event.target;
        const orig = btn.textContent;
        btn.textContent = '✅ Gekopieerd!';
        setTimeout(() => btn.textContent = orig, 2000);
    });
}

// Live avatar preview via camera button
const avatarInput = document.getElementById('avatar-input');
if (avatarInput) {
    avatarInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                // Submit form automatically when avatar is chosen
                this.closest('form').submit();
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
}

// Follow / unfollow
function toggleFollow(btn) {
    const targetId  = btn.dataset.target;
    const following = btn.dataset.following === '1';
    const action    = following ? 'unfollow' : 'follow';

    btn.disabled = true;
    fetch('follow.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'target_id=' + targetId + '&action=' + action
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            const nowFollowing = data.action === 'follow';
            btn.dataset.following = nowFollowing ? '1' : '0';
            btn.textContent = nowFollowing ? '✅ Volgend' : '➕ Volgen';
            const cnt = document.getElementById('follower-count');
            if (cnt) cnt.textContent = data.followers;
        }
        btn.disabled = false;
    });
}

// Remove avatar via GET workaround
document.querySelectorAll('a[href*="remove_avatar"]').forEach(a => {
    a.addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm('Profielfoto verwijderen?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input name="action" value="remove_avatar">';
            document.body.appendChild(form);
            form.submit();
        }
    });
});

<?php if ($msg): ?>
// Open edit panel if there was a message (e.g. error)
<?php if ($msgType === 'error'): ?>
document.getElementById('edit-panel')?.classList.add('open');
<?php endif; ?>
<?php endif; ?>
</script>

</body>
</html>

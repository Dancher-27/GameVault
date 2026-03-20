<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once 'connection.php';
require_once 'classes/Game.php';

$uid     = (int)$_SESSION['user_id'];
$gameObj = new Game($conn, $uid);
$msg     = '';

// Handle bulk action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['game_ids'])) {
    $ids    = array_map('intval', $_POST['game_ids']);
    $action = $_POST['bulk_action'] ?? '';

    $allowed = ['played','playing','backlog','wishlist','delete'];
    if (in_array($action, $allowed) && !empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM games WHERE id IN ($placeholders) AND user_id = ?");
            $stmt->bind_param($types . 'i', ...[...$ids, $uid]);
            $stmt->execute();
            $count = $stmt->affected_rows;
            $stmt->close();
            $msg = "✅ $count game(s) verwijderd.";
        } else {
            $stmt = $conn->prepare("UPDATE games SET status=? WHERE id IN ($placeholders) AND user_id = ?");
            $stmt->bind_param('s' . $types . 'i', $action, ...[...$ids, $uid]);
            $stmt->execute();
            $count = $stmt->affected_rows;
            $stmt->close();
            $labels = ['played'=>'Gespeeld','playing'=>'Bezig','backlog'=>'Backlog','wishlist'=>'Verlanglijst'];
            $msg = "✅ $count game(s) verplaatst naar {$labels[$action]}.";
        }
    }
}

// Haal alle games op
$allGames = $conn->query("
    SELECT * FROM games WHERE user_id = $uid
    ORDER BY FIELD(status,'played','playing','backlog','wishlist'), IF(rank_order=0,999999,rank_order) ASC, title ASC
")->fetch_all(MYSQLI_ASSOC);

$statusLabels = ['played'=>'Gespeeld','playing'=>'Bezig','backlog'=>'Backlog','wishlist'=>'Verlanglijst','dlc'=>'DLC'];
$statusColors = ['played'=>'#10b981','playing'=>'#3b82f6','backlog'=>'#f59e0b','wishlist'=>'#8b5cf6'];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Beheer</title>
    <link rel="stylesheet" href="styles.css">
    <style>
    .manage-table { width:100%; border-collapse:collapse; }
    .manage-table th { text-align:left; padding:10px 12px; font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted); border-bottom:2px solid var(--border); }
    .manage-table td { padding:10px 12px; border-bottom:1px solid var(--border); vertical-align:middle; font-size:.88rem; }
    .manage-table tr:hover td { background:var(--surface); }
    .manage-table tr.selected td { background:#ede9fe; }
    .manage-cover { width:36px; height:48px; object-fit:cover; border-radius:4px; display:block; }
    .manage-cover-ph { width:36px; height:48px; background:var(--purple-light); border-radius:4px; display:flex; align-items:center; justify-content:center; font-size:1rem; }
    .status-pill { display:inline-block; padding:2px 10px; border-radius:20px; font-size:.72rem; font-weight:700; color:#fff; }
    .bulk-bar {
        position: sticky; bottom: 0;
        background: var(--white);
        border-top: 2px solid var(--purple,#7c3aed);
        padding: 14px 24px;
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        box-shadow: 0 -4px 20px rgba(0,0,0,.1);
        z-index: 100;
        transition: opacity .2s;
    }
    .bulk-bar.hidden { opacity: 0; pointer-events: none; }
    .bulk-count { font-weight:700; color:var(--purple,#7c3aed); min-width:120px; }
    .filter-tabs { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
    .filter-tab { padding:5px 14px; border-radius:20px; border:1.5px solid var(--border); background:var(--surface); color:var(--text-muted); font-size:.78rem; font-weight:600; cursor:pointer; transition:all .2s; }
    .filter-tab.active { background:var(--purple,#7c3aed); border-color:var(--purple,#7c3aed); color:#fff; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="page-wrapper">
    <div class="section-header" style="margin-bottom:20px;">
        <h1 class="page-title" style="margin:0;">⚙️ Beheer</h1>
        <a href="index.php" class="btn btn-secondary" style="padding:6px 14px;font-size:.82rem;">← Terug</a>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success" style="margin-bottom:16px;"><?= $msg ?></div>
    <?php endif; ?>

    <!-- Filter tabs -->
    <div class="filter-tabs">
        <button class="filter-tab active" data-filter="all">Alle (<?= count($allGames) ?>)</button>
        <?php
        $counts = array_count_values(array_column($allGames, 'status'));
        foreach ($statusLabels as $key => $lbl):
            if (!isset($counts[$key])) continue;
        ?>
        <button class="filter-tab" data-filter="<?= $key ?>"><?= $lbl ?> (<?= $counts[$key] ?>)</button>
        <?php endforeach; ?>
    </div>

    <form method="POST" id="manage-form">
        <div style="margin-bottom:10px;display:flex;gap:10px;align-items:center;">
            <label style="font-size:.82rem;font-weight:600;cursor:pointer;">
                <input type="checkbox" id="select-all"> Alles selecteren
            </label>
        </div>

        <table class="manage-table">
            <thead>
                <tr>
                    <th style="width:36px;"></th>
                    <th style="width:52px;"></th>
                    <th>Titel</th>
                    <th>Platform</th>
                    <th>Genre</th>
                    <th>Status</th>
                    <th>Rating</th>
                    <th>Speeltijd</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="games-tbody">
            <?php foreach ($allGames as $game):
                $img = 'uploads/' . $game['cover_image'];
                $hasImg = !empty($game['cover_image']) && file_exists(__DIR__ . '/' . $img);
                $color = $statusColors[$game['status']] ?? '#6b7280';
            ?>
            <tr data-status="<?= $game['status'] ?>">
                <td><input type="checkbox" name="game_ids[]" value="<?= $game['id'] ?>" class="game-checkbox"></td>
                <td>
                    <?php if ($hasImg): ?>
                        <img src="<?= htmlspecialchars($img) ?>" class="manage-cover" loading="lazy">
                    <?php else: ?>
                        <div class="manage-cover-ph">🎮</div>
                    <?php endif; ?>
                </td>
                <td><strong><?= htmlspecialchars($game['title']) ?></strong></td>
                <td><?= htmlspecialchars($game['platform'] ?? '—') ?></td>
                <td><?= htmlspecialchars($game['genre'] ?? '—') ?></td>
                <td><span class="status-pill" style="background:<?= $color ?>"><?= $statusLabels[$game['status']] ?? $game['status'] ?></span></td>
                <td><?= $game['rating'] > 0 ? number_format((float)$game['rating'],1).'/10' : '—' ?></td>
                <td><?= $game['playtime'] > 0 ? (int)$game['playtime'].'u' : '—' ?></td>
                <td><a href="edit_game.php?id=<?= $game['id'] ?>" style="font-size:.78rem;color:var(--purple);">Bewerk</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Sticky bulk action bar -->
        <div class="bulk-bar hidden" id="bulk-bar">
            <span class="bulk-count" id="bulk-count">0 geselecteerd</span>
            <select name="bulk_action" id="bulk-action" class="form-control" style="width:auto;padding:7px 12px;">
                <option value="">-- Kies actie --</option>
                <option value="played">→ Gespeeld</option>
                <option value="playing">→ Bezig</option>
                <option value="backlog">→ Backlog</option>
                <option value="wishlist">→ Verlanglijst</option>
                <option value="delete">🗑️ Verwijderen</option>
            </select>
            <button type="submit" class="btn btn-primary" id="bulk-apply" style="padding:7px 20px;" disabled>Toepassen</button>
            <button type="button" class="btn btn-secondary" style="padding:7px 16px;" onclick="deselectAll()">Deselecteer</button>
        </div>
    </form>
</div>

<script>
const checkboxes  = document.querySelectorAll('.game-checkbox');
const selectAll   = document.getElementById('select-all');
const bulkBar     = document.getElementById('bulk-bar');
const bulkCount   = document.getElementById('bulk-count');
const bulkAction  = document.getElementById('bulk-action');
const bulkApply   = document.getElementById('bulk-apply');

function updateBar() {
    const checked = document.querySelectorAll('.game-checkbox:checked').length;
    bulkCount.textContent = checked + ' geselecteerd';
    bulkBar.classList.toggle('hidden', checked === 0);
    bulkApply.disabled = checked === 0 || bulkAction.value === '';
}

checkboxes.forEach(cb => cb.addEventListener('change', updateBar));
bulkAction.addEventListener('change', updateBar);

selectAll.addEventListener('change', function() {
    const visible = document.querySelectorAll('#games-tbody tr:not([style*="display: none"]) .game-checkbox');
    visible.forEach(cb => cb.checked = this.checked);
    updateBar();
});

function deselectAll() {
    checkboxes.forEach(cb => cb.checked = false);
    selectAll.checked = false;
    updateBar();
}

// Confirm delete
document.getElementById('manage-form').addEventListener('submit', function(e) {
    if (bulkAction.value === 'delete') {
        const n = document.querySelectorAll('.game-checkbox:checked').length;
        if (!confirm(`Weet je zeker dat je ${n} game(s) wil verwijderen? Dit kan niet ongedaan worden.`)) {
            e.preventDefault();
        }
    }
});

// Filter tabs
document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        const filter = this.dataset.filter;
        document.querySelectorAll('#games-tbody tr').forEach(row => {
            row.style.display = (filter === 'all' || row.dataset.status === filter) ? '' : 'none';
        });
        deselectAll();
    });
});
</script>

</body>
</html>

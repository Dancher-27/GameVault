<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'connection.php';
require_once 'classes/Game.php';

$gameObj = new Game($conn, (int)$_SESSION["user_id"]);
$played  = array_filter($gameObj->getAllGames(), fn($g) => $g['status'] === 'played');
usort($played, fn($a,$b) => $b['rating'] <=> $a['rating']);
$hof = array_slice(array_values($played), 0, 3);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['game_id'] ?? 0);
    if ($id && isset($_FILES['hof_img']) && $_FILES['hof_img']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        $ext = strtolower(pathinfo($_FILES['hof_img']['name'], PATHINFO_EXTENSION));
        if (in_array($_FILES['hof_img']['type'], $allowed)) {
            $dest = __DIR__ . '/uploads/hof_' . $id . '.' . $ext;
            // Remove any old hof image for this game
            foreach (glob(__DIR__ . '/uploads/hof_' . $id . '.*') as $old) unlink($old);
            if (move_uploaded_file($_FILES['hof_img']['tmp_name'], $dest)) {
                $msg = 'success';
            } else {
                $msg = 'error';
            }
        } else {
            $msg = 'type';
        }
    }
    // Delete
    if (isset($_POST['delete_id'])) {
        $did = (int)$_POST['delete_id'];
        foreach (glob(__DIR__ . '/uploads/hof_' . $did . '.*') as $f) unlink($f);
        $msg = 'deleted';
    }
}

$labels = ['🥇 #1 — Beste game', '🥈 #2', '🥉 #3'];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Hall of Fame foto's</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="page-wrapper">
    <h1 class="page-title">🖼️ Hall of Fame foto's</h1>
    <p class="page-subtitle">Upload een landschapsfoto per game. Ideaal formaat: breed (16:9). Dit vervangt de cover alleen in de Hall of Fame.</p>

    <?php if ($msg === 'success'): ?>
    <div class="alert alert-success" style="margin-bottom:20px;">✅ Foto opgeslagen!</div>
    <?php elseif ($msg === 'deleted'): ?>
    <div class="alert alert-success" style="margin-bottom:20px;">🗑️ Foto verwijderd, valt terug op cover.</div>
    <?php elseif ($msg === 'error' || $msg === 'type'): ?>
    <div class="alert alert-error" style="margin-bottom:20px;">⚠️ Upload mislukt. Gebruik JPG, PNG, WEBP of GIF.</div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:24px;">
    <?php foreach ($hof as $i => $g):
        $hofMatches = glob(__DIR__ . '/uploads/hof_' . $g['id'] . '.*');
        $hasHof  = !empty($hofMatches);
        $hofFile = $hasHof ? 'uploads/' . basename($hofMatches[0]) : '';
        $cover   = 'uploads/' . $g['cover_image'];
        $hasCover = !empty($g['cover_image']) && file_exists(__DIR__ . '/' . $cover);
        $preview = $hasHof ? $hofFile : ($hasCover ? $cover : '');
    ?>
    <div class="section-block" style="padding:20px;">
        <div style="font-size:0.75rem;font-weight:700;color:var(--purple);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">
            <?= $labels[$i] ?> — <?= htmlspecialchars($g['title']) ?>
        </div>

        <?php if ($preview): ?>
        <img src="<?= htmlspecialchars($preview) ?>"
             style="width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:8px;margin-bottom:12px;display:block;">
        <?php if ($hasHof): ?>
        <div style="font-size:0.75rem;color:var(--green,#16a34a);margin-bottom:8px;">✅ Eigen HoF-foto actief</div>
        <form method="POST" style="margin-bottom:12px;">
            <input type="hidden" name="delete_id" value="<?= $g['id'] ?>">
            <button type="submit" class="btn btn-secondary" style="font-size:0.8rem;padding:6px 14px;width:100%;"
                    onclick="return confirm('Foto verwijderen?')">🗑️ Verwijderen (terug naar cover)</button>
        </form>
        <?php else: ?>
        <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:8px;">📌 Momenteel: cover afbeelding</div>
        <?php endif; ?>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="game_id" value="<?= $g['id'] ?>">
            <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;color:var(--text);">
                <?= $hasHof ? '🔄 Foto vervangen' : '📤 HoF-foto uploaden' ?>
            </label>
            <label class="hof-file-label" id="lbl-<?= $g['id'] ?>">
                📁 Bestand kiezen
                <input type="file" name="hof_img" accept=".jpg,.jpeg,.png,.webp,.gif"
                       onchange="document.getElementById('name-<?= $g['id'] ?>').textContent = this.files[0]?.name ?? 'Geen bestand gekozen'">
            </label>
            <span class="hof-file-name" id="name-<?= $g['id'] ?>">Geen bestand gekozen</span>
            <button type="submit" class="btn btn-primary" style="width:100%;font-size:0.85rem;margin-top:10px;">
                💾 Opslaan
            </button>
        </form>
    </div>
    <?php endforeach; ?>
    </div>

    <div style="margin-top:24px;">
        <a href="index.php" class="btn btn-secondary">← Terug naar ranking</a>
    </div>
</div>

<style>
.hof-file-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 10px;
    border: 2px dashed var(--purple);
    border-radius: var(--radius);
    color: var(--purple);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    box-sizing: border-box;
    transition: background 0.2s ease;
}
.hof-file-label:hover { background: var(--purple-light); }
.hof-file-label input[type="file"] { display: none; }
.hof-file-name {
    display: block;
    font-size: 0.78rem;
    color: var(--text-muted);
    margin-top: 6px;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
</body>
</html>

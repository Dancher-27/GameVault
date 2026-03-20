<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once 'connection.php';
require_once 'classes/Tag.php';

$tagObj = new Tag($conn, (int)$_SESSION["user_id"]);
$alert  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name  = trim($_POST['name']  ?? '');
        $color = trim($_POST['color'] ?? '#7c3aed');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#7c3aed';
        if ($name !== '') {
            $tagObj->createTag($name, $color);
            $alert = 'success|Tag "' . htmlspecialchars($name) . '" aangemaakt!';
        }
    } elseif ($action === 'delete') {
        $tagObj->deleteTag((int)($_POST['tag_id'] ?? 0));
        $alert = 'success|Tag verwijderd.';
    }
}

$tags = $tagObj->getAllTags();
[$alertType, $alertMsg] = $alert ? explode('|', $alert, 2) : ['', ''];
?>
<!DOCTYPE html>
<html lang="nl" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Tags beheren</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-wrapper">
    <h1 class="page-title">🏷️ Tags beheren</h1>
    <p class="page-subtitle">Maak eigen tags aan en koppel ze aan games.</p>

    <?php if ($alertMsg): ?>
    <div class="alert alert-<?= $alertType ?>" style="margin-bottom:20px;">
        <?= $alertType === 'success' ? '✅' : '⚠️' ?> <?= $alertMsg ?>
    </div>
    <?php endif; ?>

    <div class="form-container" style="max-width:600px;">
        <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:18px;">➕ Nieuwe tag</h2>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                <div class="form-group" style="flex:1;min-width:160px;margin-bottom:0;">
                    <label class="form-label">Naam *</label>
                    <input type="text" name="name" class="form-input" placeholder="Bijv. Favoriete, Must-play..." required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Kleur</label>
                    <input type="color" name="color" value="#7c3aed" style="height:40px;width:60px;border-radius:8px;border:1px solid var(--border);cursor:pointer;padding:2px;">
                </div>
                <button type="submit" class="btn btn-primary" style="margin-bottom:0;">Aanmaken</button>
            </div>
        </form>
    </div>

    <div class="section-block" style="margin-top:28px;">
        <div class="section-header">
            <h2>Alle tags</h2>
            <span class="section-count"><?= count($tags) ?></span>
        </div>
        <?php if (empty($tags)): ?>
        <div class="empty-state"><span class="empty-state-icon">🏷️</span><p>Nog geen tags aangemaakt.</p></div>
        <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:8px;">
            <?php foreach ($tags as $t): ?>
            <div style="display:flex;align-items:center;gap:8px;background:var(--white);border-radius:20px;padding:6px 14px;box-shadow:var(--card-shadow);">
                <span style="width:12px;height:12px;border-radius:50%;background:<?= htmlspecialchars($t['color']) ?>;display:inline-block;"></span>
                <span style="font-weight:600;color:var(--text);"><?= htmlspecialchars($t['name']) ?></span>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Tag verwijderen?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="tag_id" value="<?= $t['id'] ?>">
                    <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--danger);font-size:0.9rem;" title="Verwijderen">✕</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

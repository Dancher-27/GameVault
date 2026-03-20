<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once 'connection.php';
require_once 'classes/Game.php';

$gameObj = new Game($conn, (int)$_SESSION["user_id"]);
$userId  = $_SESSION['user_id'];

// ── Actions ──────────────────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';

if ($action === 'start') {
    // Load all played games, shuffle them
    $games = $gameObj->getGamesByStatus('played');
    if (count($games) < 2) {
        $error = 'Je hebt minimaal 2 gespeelde games nodig voor een toernooi.';
    } else {
        shuffle($games);
        // If odd number, last game gets a "bye" (auto-wins)
        $_SESSION['bracket'] = [
            'games'      => $games,
            'round'      => 1,
            'matchIndex' => 0,
            'rounds'     => [array_column($games, 'id')],
            'currentRoundGames' => $games,
            'winners'    => [],
        ];
        header('Location: bracket.php');
        exit();
    }
}

if ($action === 'pick' && isset($_SESSION['bracket'])) {
    $winnerId = (int)$_POST['winner_id'];
    $b = &$_SESSION['bracket'];
    $b['winners'][] = $winnerId;
    $b['matchIndex']++;

    $total = count($b['currentRoundGames']);
    $pairs = (int)floor($total / 2);

    if ($b['matchIndex'] >= $pairs) {
        // Round done — collect winners + bye if odd
        $winnerGames = [];
        foreach ($b['winners'] as $wid) {
            foreach ($b['currentRoundGames'] as $g) {
                if ((int)$g['id'] === $wid) { $winnerGames[] = $g; break; }
            }
        }
        // If odd, last game gets bye
        if ($total % 2 === 1) {
            $winnerGames[] = $b['currentRoundGames'][$total - 1];
        }

        if (count($winnerGames) === 1) {
            // WINNER!
            $_SESSION['bracket']['champion'] = $winnerGames[0];
        } else {
            shuffle($winnerGames);
            $b['round']++;
            $b['matchIndex']   = 0;
            $b['winners']      = [];
            $b['currentRoundGames'] = $winnerGames;
        }
    }
    header('Location: bracket.php');
    exit();
}

if ($action === 'reset') {
    unset($_SESSION['bracket']);
    header('Location: bracket.php');
    exit();
}

// ── State ─────────────────────────────────────────────────────────────────────
$bracket   = $_SESSION['bracket'] ?? null;
$champion  = $bracket['champion'] ?? null;
$game1 = $game2 = null;

if ($bracket && !$champion) {
    $games = $bracket['currentRoundGames'];
    $idx   = $bracket['matchIndex'] * 2;
    $game1 = $games[$idx]       ?? null;
    $game2 = $games[$idx + 1]   ?? null;
    $totalInRound = count($games);
    $pairs = (int)floor($totalInRound / 2);
}

$current = 'bracket.php';
?>
<!DOCTYPE html>
<html lang="nl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Toernooi</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .brk-hero {
            background: linear-gradient(135deg, #1e1b4b 0%, #7c3aed 100%);
            border-radius: var(--radius); padding: 32px 40px;
            color: #fff; margin-bottom: 32px; text-align: center;
        }
        .brk-hero h1 { font-size: 2rem; margin: 0 0 6px; }
        .brk-hero p  { opacity: .7; margin: 0; }

        .brk-progress {
            display: flex; align-items: center; justify-content: center;
            gap: 8px; margin-bottom: 28px; flex-wrap: wrap;
        }
        .brk-round-pill {
            background: var(--purple-light); color: var(--purple);
            border-radius: 20px; padding: 6px 18px; font-size: 0.85rem; font-weight: 700;
        }
        .brk-match-pill {
            background: var(--white); color: var(--text-muted);
            border-radius: 20px; padding: 6px 18px; font-size: 0.85rem;
            border: 2px solid var(--border);
        }

        .brk-arena {
            display: grid; grid-template-columns: 1fr auto 1fr;
            gap: 20px; align-items: center; margin-bottom: 28px;
        }
        .brk-card {
            border-radius: var(--radius); overflow: hidden;
            background: var(--white); box-shadow: var(--card-shadow);
            cursor: pointer; transition: transform 0.15s, box-shadow 0.15s;
            text-decoration: none;
        }
        .brk-card:hover { transform: translateY(-6px) scale(1.02); box-shadow: var(--card-hover); }
        .brk-card-img {
            width: 100%; aspect-ratio: 2/3;
            object-fit: cover; object-position: center top; display: block;
        }
        .brk-card-placeholder {
            width: 100%; aspect-ratio: 2/3;
            background: var(--purple-light);
            display: flex; align-items: center; justify-content: center;
            font-size: 4rem;
        }
        .brk-card-info {
            padding: 14px 16px 16px;
        }
        .brk-card-title {
            font-size: 1rem; font-weight: 800; color: var(--text);
            margin-bottom: 6px; line-height: 1.3;
        }
        .brk-card-meta { font-size: 0.82rem; color: var(--text-muted); margin-bottom: 8px; }
        .brk-card-rating { font-size: 0.9rem; font-weight: 700; color: var(--purple); }
        .brk-pick-btn {
            display: block; width: 100%; margin-top: 12px;
            background: var(--purple); color: #fff;
            border: none; border-radius: var(--radius-sm);
            padding: 10px; font-size: 0.9rem; font-weight: 700;
            cursor: pointer; transition: background 0.15s;
        }
        .brk-pick-btn:hover { background: var(--purple-hover); }

        .brk-vs {
            text-align: center; font-size: 2.5rem; font-weight: 900;
            color: var(--purple); text-shadow: 0 2px 12px rgba(124,58,237,0.4);
            line-height: 1;
        }
        .brk-vs small {
            display: block; font-size: 0.65rem; font-weight: 600;
            color: var(--text-muted); margin-top: 4px; letter-spacing: 1px;
        }

        /* Champion */
        .brk-champion {
            text-align: center; padding: 40px 20px;
        }
        .brk-champion-trophy { font-size: 5rem; margin-bottom: 16px; }
        .brk-champion h2 { font-size: 1.8rem; color: var(--purple); margin: 0 0 24px; }
        .brk-champion-card {
            display: inline-block; max-width: 260px;
            border-radius: var(--radius); overflow: hidden;
            box-shadow: 0 8px 40px rgba(124,58,237,0.35);
            text-decoration: none;
        }
        .brk-champion-card img {
            width: 100%; aspect-ratio: 2/3;
            object-fit: cover; object-position: center top; display: block;
        }
        .brk-champion-info {
            background: var(--white); padding: 16px;
        }
        .brk-champion-title {
            font-size: 1.1rem; font-weight: 800; color: var(--text); margin-bottom: 6px;
        }
        .brk-champion-rating {
            font-size: 1rem; font-weight: 700; color: var(--purple);
        }

        /* Start screen */
        .brk-start {
            text-align: center; padding: 60px 20px;
        }
        .brk-start-icon { font-size: 4rem; margin-bottom: 16px; }
        .brk-start h2 { font-size: 1.6rem; color: var(--text); margin: 0 0 12px; }
        .brk-start p { color: var(--text-muted); margin-bottom: 28px; max-width: 480px; margin-left: auto; margin-right: auto; }

        .btn-primary {
            background: var(--purple); color: #fff;
            border: none; border-radius: var(--radius-sm);
            padding: 14px 32px; font-size: 1rem; font-weight: 700;
            cursor: pointer; transition: background 0.15s; text-decoration: none; display: inline-block;
        }
        .btn-primary:hover { background: var(--purple-hover); }
        .btn-ghost {
            background: transparent; color: var(--text-muted);
            border: 2px solid var(--border); border-radius: var(--radius-sm);
            padding: 12px 28px; font-size: 0.9rem; font-weight: 600;
            cursor: pointer; transition: border-color 0.15s, color 0.15s; text-decoration: none;
            display: inline-block; margin-left: 12px;
        }
        .btn-ghost:hover { border-color: var(--purple); color: var(--purple); }

        @media (max-width: 600px) {
            .brk-arena { grid-template-columns: 1fr; }
            .brk-vs { font-size: 1.8rem; }
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container" style="max-width:900px;margin:0 auto;padding:32px 20px;">

    <div class="brk-hero">
        <h1>⚔️ 1v1 Toernooi</h1>
        <p>Kies telkens de beste game. Aan het einde weet je jouw echte winnaar.</p>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert-error" style="margin-bottom:20px;padding:14px 18px;background:var(--error-bg);border-radius:var(--radius-sm);color:var(--danger);">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($champion): ?>
    <!-- CHAMPION SCREEN -->
    <div class="brk-champion">
        <div class="brk-champion-trophy">🏆</div>
        <h2>Jouw ultieme kampioen!</h2>
        <?php
            $img = !empty($champion['cover_image']) ? 'uploads/' . $champion['cover_image'] : '';
            $hasImg = $img && file_exists(__DIR__ . '/' . $img);
        ?>
        <a href="game_detail.php?id=<?= $champion['id'] ?>" class="brk-champion-card">
            <?php if ($hasImg): ?>
            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($champion['title']) ?>">
            <?php else: ?>
            <div style="width:100%;aspect-ratio:2/3;background:var(--purple-light);display:flex;align-items:center;justify-content:center;font-size:5rem;">🎮</div>
            <?php endif; ?>
            <div class="brk-champion-info">
                <div class="brk-champion-title"><?= htmlspecialchars($champion['title']) ?></div>
                <div class="brk-champion-rating">⭐ <?= number_format((float)$champion['rating'], 1) ?>/10</div>
            </div>
        </a>
        <div style="margin-top:32px;">
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="reset">
                <button class="btn-primary" type="submit">🔄 Nieuw toernooi starten</button>
            </form>
        </div>
    </div>

    <?php elseif ($bracket && $game1 && $game2): ?>
    <!-- MATCH SCREEN -->
    <div class="brk-progress">
        <span class="brk-round-pill">🏟️ Ronde <?= $bracket['round'] ?></span>
        <span class="brk-match-pill">
            Match <?= $bracket['matchIndex'] + 1 ?> van <?= $pairs ?>
            &nbsp;·&nbsp; <?= count($bracket['currentRoundGames']) ?> games over
        </span>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="reset">
            <button type="submit" style="background:none;border:none;color:var(--text-muted);font-size:0.8rem;cursor:pointer;padding:6px 12px;">✕ Stoppen</button>
        </form>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="pick">
        <div class="brk-arena">
            <!-- Game 1 -->
            <?php
                $img1 = !empty($game1['cover_image']) ? 'uploads/' . $game1['cover_image'] : '';
                $has1 = $img1 && file_exists(__DIR__ . '/' . $img1);
            ?>
            <div>
                <div class="brk-card">
                    <?php if ($has1): ?>
                    <img src="<?= htmlspecialchars($img1) ?>" alt="" class="brk-card-img" loading="lazy">
                    <?php else: ?>
                    <div class="brk-card-placeholder">🎮</div>
                    <?php endif; ?>
                    <div class="brk-card-info">
                        <div class="brk-card-title"><?= htmlspecialchars($game1['title']) ?></div>
                        <div class="brk-card-meta"><?= htmlspecialchars($game1['platform']) ?> · <?= htmlspecialchars($game1['genre'] ?? '—') ?></div>
                        <div class="brk-card-rating">⭐ <?= number_format((float)$game1['rating'], 1) ?>/10</div>
                        <button type="submit" name="winner_id" value="<?= $game1['id'] ?>" class="brk-pick-btn">
                            👆 Ik kies deze
                        </button>
                    </div>
                </div>
            </div>

            <!-- VS -->
            <div class="brk-vs">
                ⚔️
                <small>VS</small>
            </div>

            <!-- Game 2 -->
            <?php
                $img2 = !empty($game2['cover_image']) ? 'uploads/' . $game2['cover_image'] : '';
                $has2 = $img2 && file_exists(__DIR__ . '/' . $img2);
            ?>
            <div>
                <div class="brk-card">
                    <?php if ($has2): ?>
                    <img src="<?= htmlspecialchars($img2) ?>" alt="" class="brk-card-img" loading="lazy">
                    <?php else: ?>
                    <div class="brk-card-placeholder">🎮</div>
                    <?php endif; ?>
                    <div class="brk-card-info">
                        <div class="brk-card-title"><?= htmlspecialchars($game2['title']) ?></div>
                        <div class="brk-card-meta"><?= htmlspecialchars($game2['platform']) ?> · <?= htmlspecialchars($game2['genre'] ?? '—') ?></div>
                        <div class="brk-card-rating">⭐ <?= number_format((float)$game2['rating'], 1) ?>/10</div>
                        <button type="submit" name="winner_id" value="<?= $game2['id'] ?>" class="brk-pick-btn">
                            👆 Ik kies deze
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <?php else: ?>
    <!-- START SCREEN -->
    <?php
        $playedCount = count($gameObj->getGamesByStatus('played'));
    ?>
    <div class="brk-start">
        <div class="brk-start-icon">⚔️</div>
        <h2>Wie is jouw beste game ooit?</h2>
        <p>
            Al jouw <?= $playedCount ?> gespeelde games gaan 1 tegen 1 het veld in.
            Jij kiest telkens de winnaar. Aan het einde staat de echte kampioen vast —
            niet op basis van rating, maar op jouw gevoel.
        </p>
        <?php if ($playedCount >= 2): ?>
        <form method="POST">
            <input type="hidden" name="action" value="start">
            <button class="btn-primary" type="submit">🎮 Toernooi starten!</button>
        </form>
        <?php else: ?>
        <p style="color:var(--danger);">Je hebt minimaal 2 gespeelde games nodig.</p>
        <?php endif; ?>
    </div>
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

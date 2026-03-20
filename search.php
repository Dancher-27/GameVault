<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// =============================================
//  RAWG API — Gratis game database
//  Registreer op: https://rawg.io/apidocs
//  Zet je API key hieronder neer
// =============================================
$apiKey = 'd76ba15d2d144734b4d783b78ea4eaf1';

$results      = [];
$errorMessage = '';
$query        = trim($_GET['q'] ?? '');

if ($query !== '') {
    $url      = "https://api.rawg.io/api/games?key={$apiKey}&search=" . urlencode($query) . "&page_size=8&search_exact=false";
    $response = @file_get_contents($url);

    if ($response !== false) {
        $data = json_decode($response, true);
        if (isset($data['results']) && count($data['results']) > 0) {
            $results = $data['results'];
        } else {
            $errorMessage = 'Geen games gevonden voor "' . htmlspecialchars($query) . '".';
        }
    } else {
        $errorMessage = 'Kan geen verbinding maken met de RAWG database. Controleer je API key.';
    }
}

// Helper: haal platforms op als string
function platformsStr(array $game): string {
    if (empty($game['platforms'])) return '';
    $names = array_map(fn($p) => $p['platform']['name'], $game['platforms']);
    return implode(', ', array_slice($names, 0, 3));
}

// Helper: haal genres op als string
function genresStr(array $game): string {
    if (empty($game['genres'])) return '';
    $names = array_map(fn($g) => $g['name'], $game['genres']);
    return implode(', ', array_slice($names, 0, 2));
}

// Eerste platform → mapping naar onze dropdownopties
function firstPlatform(array $game): string {
    if (empty($game['platforms'])) return '';
    $name = $game['platforms'][0]['platform']['name'] ?? '';
    $map  = [
        'PC'                => 'PC',
        'PlayStation 5'     => 'PlayStation 5',
        'PlayStation 4'     => 'PlayStation 4',
        'Xbox Series S/X'   => 'Xbox Series X/S',
        'Xbox One'          => 'Xbox One',
        'Nintendo Switch'   => 'Nintendo Switch',
        'iOS'               => 'Mobile',
        'Android'           => 'Mobile',
    ];
    return $map[$name] ?? 'Other';
}

// Eerste genre → mapping naar onze dropdownopties
function firstGenre(array $game): string {
    if (empty($game['genres'])) return '';
    $name = $game['genres'][0]['name'] ?? '';
    $map  = [
        'Action'            => 'Action',
        'Adventure'         => 'Adventure',
        'Role-playing (RPG)'=> 'RPG',
        'Shooter'           => 'FPS',
        'Strategy'          => 'Strategy',
        'Sports'            => 'Sports',
        'Horror'            => 'Horror',
        'Puzzle'            => 'Puzzle',
        'Simulation'        => 'Simulation',
        'Racing'            => 'Racing',
        'Fighting'          => 'Fighting',
        'Platformer'        => 'Platformer',
        'Stealth'           => 'Stealth',
        'Open World'        => 'Open World',
    ];
    return $map[$name] ?? 'Other';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Zoeken</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="page-wrapper">
    <h1 class="page-title">🔍 Game zoeken</h1>
    <p class="page-subtitle">Zoek games op via de RAWG database en voeg ze direct toe aan je vault.</p>

    <!-- ===== ZOEKBALK ===== -->
    <div class="search-bar-wrapper">
        <form action="search.php" method="GET" class="search-bar-form">
            <div class="form-group" style="flex:1; margin-bottom:0;">
                <input type="text" name="q" id="q"
                       placeholder="Bijv. The Witcher 3, Elden Ring, Red Dead Redemption..."
                       value="<?= htmlspecialchars($query) ?>"
                       autocomplete="off" required>
            </div>
            <button type="submit" class="btn btn-primary">🔍 Zoeken</button>
        </form>
    </div>

    <?php if ($apiKey === 'YOUR_RAWG_API_KEY'): ?>
    <div class="alert alert-error" style="margin-bottom:28px;">
        ⚠️ Vul je RAWG API key in via <strong>search.php</strong> regel 12.
        Gratis key ophalen op <strong>rawg.io/apidocs</strong>.
    </div>
    <?php endif; ?>

    <!-- ===== FOUTMELDING ===== -->
    <?php if ($errorMessage): ?>
    <div class="search-not-found">
        <span class="empty-state-icon">🎮</span>
        <p><?= htmlspecialchars($errorMessage) ?></p>
    </div>
    <?php endif; ?>

    <!-- ===== ZOEKRESULTATEN ===== -->
    <?php if (!empty($results)): ?>
    <div class="section-header">
        <h2>📋 Resultaten voor "<?= htmlspecialchars($query) ?>"</h2>
        <span class="section-count"><?= count($results) ?></span>
    </div>

    <div class="search-results-grid">
        <?php foreach ($results as $game):
            $title    = $game['name']             ?? '';
            $year     = substr($game['released'] ?? '', 0, 4);
            $cover    = $game['background_image'] ?? '';
            $meta     = $game['metacritic']       ?? null;
            $platform = firstPlatform($game);
            $genre    = firstGenre($game);

            // URL params voor add_game.php
            $baseParams = http_build_query([
                'title'    => $title,
                'year'     => $year,
                'platform' => $platform,
                'genre'    => $genre,
                'cover_url'=> $cover,
            ]);
            $addParams      = $baseParams . '&status=played';
            $wishlistParams = $baseParams . '&status=wishlist';
        ?>
        <div class="search-result-card">
            <?php if ($cover): ?>
            <img src="<?= htmlspecialchars($cover) ?>"
                 alt="<?= htmlspecialchars($title) ?>"
                 class="search-result-img"
                 onerror="this.parentElement.querySelector('.search-result-placeholder').style.display='flex'; this.style.display='none';">
            <?php endif; ?>
            <div class="search-result-placeholder" style="<?= $cover ? 'display:none' : '' ?>">🎮</div>

            <div class="search-result-body">
                <div class="search-result-title"><?= htmlspecialchars($title) ?></div>

                <div class="search-result-meta">
                    <?php if ($year): ?>
                    <span class="meta-tag">📅 <?= htmlspecialchars($year) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($game['genres'])): ?>
                    <span class="meta-tag">🎯 <?= htmlspecialchars(genresStr($game)) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($game['platforms'])): ?>
                    <span class="meta-tag">🖥️ <?= htmlspecialchars(platformsStr($game)) ?></span>
                    <?php endif; ?>
                    <?php if ($meta): ?>
                    <span class="meta-tag metacritic-score">🏆 Metacritic: <?= (int)$meta ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($game['short_screenshots'])): ?>
                <div class="search-screenshots">
                    <?php foreach (array_slice($game['short_screenshots'], 0, 3) as $ss): ?>
                    <img src="<?= htmlspecialchars($ss['image']) ?>" alt="screenshot" class="search-ss-thumb">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div style="display:flex; gap:8px; margin-top:auto;">
                    <a href="add_game.php?<?= $addParams ?>" class="btn btn-primary" style="flex:1; justify-content:center; font-size:0.8rem; padding:8px 6px;">
                        ➕ Ranking
                    </a>
                    <a href="add_game.php?<?= $wishlistParams ?>" class="btn btn-secondary" style="flex:1; justify-content:center; font-size:0.8rem; padding:8px 6px;">
                        🎯 Verlanglijst
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

</body>
</html>

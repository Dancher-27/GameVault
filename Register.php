<?php
session_start();
require_once 'connection.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    if (strlen($username) < 3) {
        $error = 'Gebruikersnaam moet minimaal 3 tekens bevatten.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ongeldig e-mailadres.';
    } elseif (strlen($password) < 6) {
        $error = 'Wachtwoord moet minimaal 6 tekens bevatten.';
    } elseif ($password !== $confirm) {
        $error = 'Wachtwoorden komen niet overeen.';
    } else {
        // Check of email al bestaat
        $check = $conn->prepare("SELECT id FROM account WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'Dit e-mailadres is al in gebruik.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO account (username, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $hash);
            if ($stmt->execute()) {
                $success = 'Account aangemaakt! Je kunt nu inloggen.';
            } else {
                $error = 'Fout bij aanmaken: ' . $conn->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Registreren</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="auth-page">

<nav class="auth-navbar">
    <a href="login.php">🔑 Inloggen</a>
    <a href="Register.php" class="active">📝 Registreren</a>
</nav>

<div class="auth-card">
    <div class="auth-logo">
        <span class="logo-icon">🎮</span>
        <h1>GameVault</h1>
        <p>Maak een account aan om te beginnen</p>
    </div>

    <?php if ($error): ?>
    <div class="auth-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="auth-success">✅ <?= htmlspecialchars($success) ?> <a href="login.php">Inloggen →</a></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="username">Gebruikersnaam</label>
            <input type="text" id="username" name="username"
                   placeholder="Jouw naam"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   required minlength="3">
        </div>
        <div class="form-group">
            <label for="email">E-mailadres</label>
            <input type="email" id="email" name="email"
                   placeholder="jouw@email.nl"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   required>
        </div>
        <div class="form-group">
            <label for="password">Wachtwoord</label>
            <input type="password" id="password" name="password"
                   placeholder="Minimaal 6 tekens" required minlength="6">
        </div>
        <div class="form-group">
            <label for="confirm">Wachtwoord bevestigen</label>
            <input type="password" id="confirm" name="confirm"
                   placeholder="Herhaal wachtwoord" required>
        </div>
        <button type="submit" class="btn btn-primary">📝 Account aanmaken</button>
    </form>

    <div class="auth-switch">
        Al een account? <a href="login.php">Log hier in</a>
    </div>
</div>

</body>
</html>

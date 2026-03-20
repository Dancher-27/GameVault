<?php
session_start();
require_once 'connection.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    $stmt = $conn->prepare("SELECT * FROM account WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Vernieuw sessie ID om session fixation te voorkomen
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $stmt->close();
            header('Location: index.php');
            exit();
        } else {
            $error = 'Onjuist wachtwoord.';
        }
    } else {
        $error = 'Geen account gevonden met dit e-mailadres.';
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault — Inloggen</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="auth-page">

<nav class="auth-navbar">
    <a href="login.php" class="active">🔑 Inloggen</a>
    <a href="Register.php">📝 Registreren</a>
</nav>

<div class="auth-card">
    <div class="auth-logo">
        <span class="logo-icon">🎮</span>
        <h1>GameVault</h1>
        <p>Jouw persoonlijke game ratings tracker</p>
    </div>

    <?php if ($error): ?>
    <div class="auth-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="email">E-mailadres</label>
            <input type="email" id="email" name="email"
                   placeholder="jouw@email.nl"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   required autocomplete="email">
        </div>
        <div class="form-group">
            <label for="password">Wachtwoord</label>
            <input type="password" id="password" name="password"
                   placeholder="••••••••"
                   required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary">🔑 Inloggen</button>
    </form>

    <div class="auth-switch">
        Nog geen account? <a href="Register.php">Registreer je hier</a>
    </div>
</div>

</body>
</html>

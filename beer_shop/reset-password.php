<?php
session_start();
$pdo = new PDO("mysql:host=mysql2.small.pl;dbname=m2378_beer;charset=utf8mb4", "m2378_admin", "Minecraft1", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$step = $_GET['step'] ?? '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'request') {
    $email = trim($_POST['email'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            $pdo->prepare("INSERT INTO password_resets (email, token, expires) VALUES (?, ?, ?)")->execute([$email, $token, $expires]);

            $resetLink = "https://twojadomena.pl/reset-password.php?step=reset&token=" . urlencode($token);

            $mailHtml = "<!DOCTYPE html><html><head><meta charset='utf-8'></head><body style='font-family:Arial;background:#0a0a0a;color:#f0f0f0;padding:40px;text-align:center;'>
                <h1 style='color:#ffc800'>BeerShop – Reset hasła</h1>
                <p>Ktoś (miejmy nadzieję, że Ty) poprosił o zmianę hasła.</p>
                <p><a href='$resetLink' style='background:#ffc800;color:black;padding:18px 50px;text-decoration:none;border-radius:50px;font-weight:900;font-size:1.4em;display:inline-block;margin:30px;'>Zresetuj hasło</a></p>
                <p>Link ważny 1 godzinę. Jeśli to nie Ty – zignoruj.</p>
            </body></html>";

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'twojmail@gmail.com';
                $mail->Password = 'twoje_haslo_aplikacji';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                $mail->setFrom('no-reply@twojadomena.pl', 'BeerShop');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Reset hasła – BeerShop';
                $mail->Body = $mailHtml;
                $mail->send();
                $success = "Link do resetu hasła został wysłany!";
            } catch (Exception $e) { $error = "Błąd serwera. Spróbuj później."; }
        } else $error = "Nie znaleziono konta z tym e-mailem.";
    } else $error = "Podaj poprawny e-mail.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset') {
    $pass = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';
    if (strlen($pass) < 6) $error = "Hasło min. 6 znaków";
    elseif ($pass !== $pass2) $error = "Hasła się różnią";
    else {
        $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires > NOW()");
        $stmt->execute([$token]);
        if ($row = $stmt->fetch()) {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([$hash, $row['email']]);
            $pdo->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);
            $success = "Hasło zmienione! Możesz się zalogować.";
        } else $error = "Link wygasł lub jest nieprawidłowy.";
    }
}
  $is_admin = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $is_admin = $stmt->fetchColumn() === 'admin';
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset hasła – BeerShop</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="assets/BeerShop.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
  <style>
    body {
        margin: 0;
        padding: 0;
        min-height: 100vh;
        background: var(--bg);
    }
    .reset-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
    }
    .account-container {
        background: var(--surface);
        border-radius: 25px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.9);
        border: 4px solid var(--primary);
        padding: 50px;
        max-width: 500px;
        width: 100%;
        text-align: center;
    }
    .account-header h2 {
        font-size: 2.4rem;
        color: var(--primary);
        margin-bottom: 30px;
    }
</style>
</head>
<body>
<nav>
    <div class="nav-container">
        <!-- LOGO Z PODSKAKUJĄCYMI LITERKAMI -->
        <a href="index.php" class="logo-wrapper">
            <img src="assets/BeerShop.png" alt="BeerShop" class="logo-img">
            <span class="logo-text">
                <span>B</span><span>e</span><span>e</span><span>r</span>
                <span> </span>
                <span>S</span><span>h</span><span>o</span><span>p</span>
            </span>
        </a>

        <div class="nav-links">
            <!-- Strona główna -->
            <a href="index.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>Strona główna
            </a>

            <!-- Konto -->
            <a href="konto.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'konto.php') ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>Konto
            </a>

            <!-- Panel Admina -->
            <?php if ($is_admin): ?>
                <a href="admin/" class="nav-link admin-panel-btn <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') === 0) ? 'active' : ''; ?>">
                    <i class="fas fa-crown"></i>Panel Admina
                </a>
            <?php endif; ?>

            <!-- Koszyk -->
            <a href="koszyk.php" class="nav-link cart-link <?php echo (basename($_SERVER['PHP_SELF']) == 'koszyk.php') ? 'active' : ''; ?>">
                <i class="fas fa-beer-mug-empty"></i>Koszyk
                <span class="cart-count" id="cart-count"></span>
            </a>
        </div>
    </div>
</nav>
  
<div class="reset-wrapper">
    <div class="account-container">
        <div class="account-header"><h2>Reset hasła</h2></div>
        <?php if ($error): ?><div class="message error"><?=htmlspecialchars($error)?></div><?php endif; ?>
        <?php if ($success): ?><div class="message success"><?=htmlspecialchars($success)?></div><?php endif; ?>

        <?php if (!$step || $step === 'request'): ?>
            <form method="POST" style="padding:50px;">
                <input type="hidden" name="step" value="request">
                <div class="form-group"><label>E-mail</label><input type="email" name="email" required></div>
                <button type="submit" class="btn-primary">Wyślij link</button>
            </form>
        <?php elseif ($step === 'reset' && !$success): ?>
            <form method="POST" style="padding:50px;">
                <input type="hidden" name="step" value="reset">
                <input type="hidden" name="token" value="<?=htmlspecialchars($token)?>">
                <div class="form-group password-wrapper"><label>Nowe hasło</label><input type="password" name="password" required><i class="fas fa-eye toggle-password"></i></div>
                <div class="form-group password-wrapper"><label>Powtórz hasło</label><input type="password" name="password2" required><i class="fas fa-eye toggle-password"></i></div>
                <button type="submit" class="btn-primary">Zmień hasło</button>
            </form>
        <?php endif; ?>
        <p style="text-align:center;padding:20px;"><a href="konto.php" style="color:#ffc800;">Wróć do logowania</a></p>
    </div>
</div>
<script>
document.querySelectorAll('.toggle-password').forEach(el => 
    el.onclick = () => el.previousElementSibling.type = el.previousElementSibling.type === 'password' ? 'text' : 'password'
);
</script>
</body>
</html>
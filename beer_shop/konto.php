<?php
session_start();

$pdo = new PDO(
    "mysql:host=mysql2.small.pl;dbname=m2378_beer;charset=utf8mb4",
    "m2378_admin",
    "Minecraft1",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$errors  = [];
$success = '';
$is_register_attempt = false;

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    birth_date DATE NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    role ENUM('client','admin') DEFAULT 'client',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('new','processing','shipped','cancelled') DEFAULT 'new',
    address TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // REJESTRACJA
    if ($action === 'register') {
        $is_register_attempt = true;
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $birth_raw  = preg_replace('/\D/', '', $_POST['birth_date'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $pass       = $_POST['password'] ?? '';
        $pass2      = $_POST['password2'] ?? '';

        if (!$first_name) $errors[] = "Podaj imię";
        if (!$last_name)  $errors[] = "Podaj nazwisko";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Nieprawidłowy e-mail";

        $birth_date = null;
        if (strlen($birth_raw) === 8 && ctype_digit($birth_raw)) {
            $year  = substr($birth_raw, 0, 4);
            $month = substr($birth_raw, 4, 2);
            $day   = substr($birth_raw, 6, 2);
            if (checkdate($month, $day, $year)) {
                $birth_date = "$year-$month-$day";
                $age = date_diff(date_create($birth_date), date_create('today'))->y;
                if ($age < 18) $errors[] = "Musisz mieć ukończone 18 lat!";
            } else $errors[] = "Nieprawidłowa data urodzenia";
        } else $errors[] = "Wpisz datę jako 8 cyfr (np. 19950515)";

        if (!$address) $errors[] = "Podaj adres";
        if (!preg_match('/^[\d\s+()-]{9,20}$/', $phone)) $errors[] = "Nieprawidłowy numer telefonu";
        if (strlen($pass) < 6) $errors[] = "Hasło musi mieć min. 6 znaków";
        if ($pass !== $pass2) $errors[] = "Hasła się różnią";

        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Konto o tym e-mailu już istnieje";
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password, first_name, last_name, birth_date, address, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$email, $hash, $first_name, $last_name, $birth_date, $address, $phone]);

                $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_email'] = $email;
                header("Location: konto.php");
                exit;
            }
        }
    }

    // LOGOWANIE
    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        if (filter_var($email, FILTER_VALIDATE_EMAIL) && $pass) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && password_verify($pass, $user['password'])) {
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_email'] = $user['email'];
                header("Location: konto.php");
                exit;
            } else $errors[] = "Błędny e-mail lub hasło";
        } else $errors[] = "Wypełnij wszystkie pola";
    }

    // AKTUALIZACJA
    if ($action === 'update' && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $birth_raw  = preg_replace('/\D/', '', $_POST['birth_date'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $pass       = $_POST['password'] ?? '';
        $pass2      = $_POST['password2'] ?? '';

        if (!$first_name) $errors[] = "Podaj imię";
        if (!$last_name)  $errors[] = "Podaj nazwisko";
        if (strlen($birth_raw) !== 8 || !ctype_digit($birth_raw) || !checkdate(substr($birth_raw,4,2), substr($birth_raw,6,2), substr($birth_raw,0,4)))
            $errors[] = "Nieprawidłowa data urodzenia";
        if (!$address) $errors[] = "Podaj adres";
        if (!preg_match('/^[\d\s+()-]{9,20}$/', $phone)) $errors[] = "Nieprawidłowy numer telefonu";
        if ($pass && strlen($pass) < 6) $errors[] = "Hasło min. 6 znaków";
        if ($pass && $pass !== $pass2) $errors[] = "Hasła się różnią";

        if (empty($errors)) {
            $birth_date = substr($birth_raw,0,4).'-'.substr($birth_raw,4,2).'-'.substr($birth_raw,6,2);
            $sql = "UPDATE users SET first_name=?, last_name=?, birth_date=?, address=?, phone=?";
            $params = [$first_name, $last_name, $birth_date, $address, $phone];
            if ($pass) { $sql .= ", password=?"; $params[] = password_hash($pass, PASSWORD_DEFAULT); }
            $sql .= " WHERE id=?"; $params[] = $user_id;
            $pdo->prepare($sql)->execute($params);

            $success = "Dane zaktualizowane!";
            $_SESSION['user_name'] = "$first_name $last_name";
        }
    }

    // WYLOGOWANIE
    if ($action === 'logout') {
        session_destroy();
        header("Location: konto.php"); exit;
    }
}

// Dane użytkownika
$user = null;
$is_admin = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $is_admin = ($user['role'] ?? '') === 'admin';
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moje konto – BeerShop</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="icon" href="assets/BeerShop.ico">
    <style>
        .section-toggle{display:flex;background:#2a2a2a;margin:40px 50px;border-radius:50px;padding:6px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.5);position:relative}
        .section-slider{position:absolute;top:6px;left:6px;width:calc(50% - 6px);height:calc(100% - 12px);background:var(--primary);border-radius:50px;transition:transform .5s cubic-bezier(.68,-.55,.265,1.55);box-shadow:0 8px 30px rgba(255,200,0,.5);z-index:1}
        .section-btn{flex:1;padding:18px 0;text-align:center;font-size:1.35rem;font-weight:900;color:#888;z-index:2;cursor:pointer;transition:color .4s}
        .section-btn.active{color:#000!important}
        .account-section{display:none;animation:fadeIn .6s forwards}
        .account-section.active{display:block}
        @keyframes fadeIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
        .order-item{background:var(--card);padding:24px;border-radius:18px;margin-bottom:20px;box-shadow:0 8px 30px rgba(0,0,0,.5);transition:transform .3s}
        .order-item:hover{transform:translateY(-6px)}
        .order-status{display:inline-block;padding:6px 16px;border-radius:12px;font-weight:bold;font-size:.9rem}
        .status-new{background:#3498db;color:#fff}
        .status-processing{background:#f39c12;color:#fff}
        .status-shipped{background:#27ae60;color:#fff}
        .status-cancelled{background:#e74c3c;color:#fff}
    </style>
</head>
<body>

<nav>
    <div class="nav-container">
        <a href="index.php" class="logo-wrapper">
            <img src="assets/BeerShop.png" alt="BeerShop" class="logo-img">
            <span class="logo-text">
                <span>B</span><span>e</span><span>e</span><span>r</span>
                <span> </span>
                <span>S</span><span>h</span><span>o</span><span>p</span>
            </span>
        </a>

        <div class="nav-links">
            <a href="index.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>Strona główna
            </a>

            <a href="konto.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'konto.php') ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>Konto
            </a>
            <?php if ($is_admin): ?>
                <a href="admin/" class="nav-link admin-panel-btn <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') === 0) ? 'active' : ''; ?>">
                    <i class="fas fa-crown"></i>Panel Admina
                </a>
            <?php endif; ?>
          
            <a href="koszyk.php" class="nav-link cart-link <?php echo (basename($_SERVER['PHP_SELF']) == 'koszyk.php') ? 'active' : ''; ?>">
                <i class="fas fa-beer-mug-empty"></i>Koszyk
                <span class="cart-count" id="cart-count"></span>
            </a>
        </div>
    </div>
</nav>

<?php if (isset($_SESSION['user_id'])): ?>
    <!-- ZALOGOWANY -->
    <div class="account-wrapper">
        <div class="account-container">
            <div class="account-header">
                <h2>Witaj, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h2>
                <p>Zarządzaj swoim kontem</p>
            </div>

            <?php if ($success): ?>
                <div class="message success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
            <?php endif; ?>

            <div class="section-toggle">
                <div class="section-slider" id="slider"></div>
                <div class="section-btn active" onclick="showSection('edit')">Edytuj dane</div>
                <div class="section-btn" onclick="showSection('orders')">Moje zamówienia</div>
            </div>

            <div id="edit" class="account-section active">
                <?php foreach ($errors as $e): ?>
                    <div class="message error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>

                <form method="POST" style="margin:40px 50px;">
                    <input type="hidden" name="action" value="update">
                    <div class="form-group"><label>Imię</label><input type="text" name="first_name" required value="<?= htmlspecialchars($user['first_name']) ?>"></div>
                    <div class="form-group"><label>Nazwisko</label><input type="text" name="last_name" required value="<?= htmlspecialchars($user['last_name']) ?>"></div>
                    <div class="form-group"><label>Data urodzenia (RRRR-MM-DD)</label> <input type="text" name="birth_date" required maxlength="10" value="<?= htmlspecialchars($user['birth_date'] ?? '') ?>" oninput="formatDateInput(this)"></div>
                    <div class="form-group"><label>Adres</label><input type="text" name="address" required value="<?= htmlspecialchars($user['address'] ?? '') ?>"></div>
                    <div class="form-group"><label>Telefon</label><input type="text" name="phone" required value="<?= htmlspecialchars($user['phone'] ?? '') ?>"></div>
                    <div class="form-group"><label>E-mail</label><input type="email" disabled value="<?= htmlspecialchars($user['email']) ?>"></div>
                    <div class="form-group password-wrapper">
                        <label>Nowe hasło (zostaw puste jeśli nie zmieniasz)</label>
                        <input type="password" name="password" id="pass1">
                        <i class="fas fa-eye toggle-password" onclick="togglePass('pass1')"></i>
                    </div>
                    <div class="form-group password-wrapper">
                        <label>Powtórz nowe hasło</label>
                        <input type="password" name="password2" id="pass2">
                        <i class="fas fa-eye toggle-password" onclick="togglePass('pass2')"></i>
                    </div>
                    <button type="submit" class="btn-primary">Zapisz zmiany</button>
                </form>
            </div>

            <div id="orders" class="account-section">
                <div style="margin:40px 50px;">
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $orders = $stmt->fetchAll();
                    ?>
                    <?php if ($orders): ?>
                        <?php foreach ($orders as $order): ?>
                            <div class="order-item">
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <div>
                                        <strong>Zamówienie #<?= $order['id'] ?></strong><br>
                                        <small style="color:var(--text-muted);"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></small>
                                    </div>
                                    <div style="text-align:right;">
                                        <span class="order-status status-<?= $order['status'] ?>">
                                            <?= ['new'=>'Nowe','processing'=>'W realizacji','shipped'=>'Wysłane','cancelled'=>'Anulowane'][$order['status']] ?? $order['status'] ?>
                                        </span>
                                        <div style="margin-top:8px;font-size:1.4rem;color:var(--primary);font-weight:bold;">
                                            <?= number_format($order['total'], 2) ?> zł
                                        </div>
                                    </div>
                                </div>
                                <div style="margin-top:16px;color:var(--text-muted);">
                                    Adres dostawy: <?= htmlspecialchars($order['address']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center;padding:100px 20px;color:var(--text-muted);">
                            <i class="fas fa-beer" style="font-size:5rem;opacity:0.3;"></i>
                            <h3>Nie masz jeszcze żadnych zamówień</h3>
                            <a href="index.php" class="btn-primary" style="margin-top:20px;display:inline-block;">Wróć do sklepu</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="text-align:center;padding:40px;">
                <form method="POST" style="display:inline-block;">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn-primary" style="background:#e74c3c;">Wyloguj się</button>
                </form>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- NIEZALOGOWANY -->
    <div class="account-wrapper">
        <div class="account-container">
            <div class="account-header">
                <h2>BeerShop</h2>
                <p>Zaloguj się lub utwórz konto</p>
            </div>

           <div class="toggle-container">
                <input type="checkbox" id="toggle-register" hidden <?= $is_register_attempt ? 'checked' : '' ?>>
                <label for="toggle-register" class="toggle-label <?= !$is_register_attempt ? 'active' : '' ?>">Logowanie</label>
                <label for="toggle-register" class="toggle-label <?= $is_register_attempt ? 'active' : '' ?>">Rejestracja</label>
                <div class="toggle-slider"></div>
            </div>

          
            <div class="form-wrapper">
                <div class="form-slide <?= !$is_register_attempt ? 'active' : '' ?>" id="login-form">
                    <form method="POST" style="padding:0 50px;">
                        <input type="hidden" name="action" value="login">
                        <?php foreach($errors as $e): if(!$is_register_attempt): ?>
                            <div class="message error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($e) ?></div>
                        <?php endif; endforeach; ?>
                        <div class="form-group"><label>E-mail</label><input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
                        <div class="form-group password-wrapper"><label>Hasło</label><input type="password" name="password" required><i class="fas fa-eye toggle-password" onclick="togglePass(this.previousElementSibling)"></i></div>
                        <button type="submit" class="btn-primary">Zaloguj się</button>
                    </form>
                </div>

                <div class="form-slide <?= $is_register_attempt ? 'active' : '' ?>" id="register-form">
                    <form method="POST" style="padding:0 50px;">
                        <input type="hidden" name="action" value="register">
                        <?php foreach($errors as $e): if($is_register_attempt): ?>
                            <div class="message error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($e) ?></div>
                        <?php endif; endforeach; ?>
                        <div class="form-group"><label>Imię</label><input type="text" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"></div>
                        <div class="form-group"><label>Nazwisko</label><input type="text" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"></div>
                        <div class="form-group"><label>E-mail</label><input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
                        <div class="form-group">
    <label>Data urodzenia (RRRR-MM-DD)</label>
    <input type="text" 
           name="birth_date"
           maxlength="10" 
           required
           value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>"
           oninput="formatDateInput(this)">
</div>
                        <div class="form-group"><label>Adres dostawy</label><input type="text" name="address" required value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"></div>
                        <div class="form-group"><label>Telefon</label><input type="text" name="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"></div>
                        <div class="form-group password-wrapper"><label>Hasło</label><input type="password" name="password" required><i class="fas fa-eye toggle-password" onclick="togglePass(this.previousElementSibling)"></i></div>
                        <div class="form-group password-wrapper"><label>Powtórz hasło</label><input type="password" name="password2" required><i class="fas fa-eye toggle-password" onclick="togglePass(this.previousElementSibling)"></i></div>
                        <button type="submit" class="btn-primary">Zarejestruj się</button>
                    </form>
                </div>
            </div>
          <p style="text-align:center;margin-top:30px;">
    <a href="reset-password.php" style="color:#ffc800;font-weight:600;text-decoration:underline;">
        Zapomniałem hasła
    </a>
</p>
        </div>
    </div>
<?php endif; ?>

<script>
function togglePass(input) {
    if (input.type === "password") {
        input.type = "text";
        input.nextElementSibling.classList.replace("fa-eye", "fa-eye-slash");
    } else {
        input.type = "password";
        input.nextElementSibling.classList.replace("fa-eye-slash", "fa-eye");
    }
}

function showSection(section) {
    document.querySelectorAll('.account-section').forEach(s => s.classList.remove('active'));
    document.getElementById(section).classList.add('active');
    document.querySelectorAll('.section-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`.section-btn[onclick="showSection('${section}')"]`).classList.add('active');
    document.getElementById('slider').style.transform = section === 'edit' ? 'translateX(0)' : 'translateX(100%)';
}

// Przełączanie Logowanie / Rejestracja
const toggle = document.getElementById('toggle-register');
if (toggle) {

toggle.addEventListener('change', function() {
    const isRegister = this.checked;
    document.getElementById('login-form').classList.toggle('active', !isRegister);
    document.getElementById('register-form').classList.toggle('active', isRegister);

    const labels = document.querySelectorAll('.toggle-label');
    if (labels.length === 2) {
        labels[0].classList.toggle('active', !isRegister); // Logowanie
        labels[1].classList.toggle('active', isRegister);  // Rejestracja
    }
});
}

<?php if (isset($_SESSION['user_id'])): ?>
    showSection('orders');
<?php endif; ?>

function updateCartCount() {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const total = cart.reduce((a, i) => a + i.qty, 0);
    document.querySelectorAll('#cart-count').forEach(el => {
        el.textContent = total || '';
        el.style.display = total ? 'flex' : 'none';
    });
}
updateCartCount();
  
  function formatDateInput(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length > 8) value = value.substr(0, 8);
    
    if (value.length > 6) {
        value = value.substr(0, 4) + '-' + value.substr(4, 2) + '-' + value.substr(6);
    } else if (value.length > 4) {
        value = value.substr(0, 4) + '-' + value.substr(4);
    }
    
    input.value = value;
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[name="birth_date"]').forEach(input => {
        if (input.value && input.value.length === 8 && /^\d{8}$/.test(input.value)) {
            const v = input.value;
            input.value = v.substr(0,4) + '-' + v.substr(4,2) + '-' + v.substr(6);
        }
    });
});
  
  
</script>
</body>
</html>
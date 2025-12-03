<?php
session_start();

$pdo = new PDO(
    "mysql:host=mysql2.small.pl;dbname=m2378_beer;charset=utf8mb4",
    "m2378_admin",
    "Minecraft1",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$errors = [];
$success = '';

$user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Składanie zamówienia
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart = json_decode($_POST['cart'] ?? '[]', true);

    if (empty($cart)) {
        $errors[] = "Koszyk jest pusty";
    } else {
        $email      = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Nieprawidłowy e-mail";
        if (!$first_name || !$last_name) $errors[] = "Podaj imię i nazwisko";
        if (!$address) $errors[] = "Podaj adres dostawy";
        if (!preg_match('/^[\d\s+()-]{9,20}$/', $phone)) $errors[] = "Nieprawidłowy numer telefonu";

        if (empty($errors)) {
            $total = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));

            $pdo->beginTransaction();
            try {
                // Zapis zamówienia
                $stmt = $pdo->prepare("INSERT INTO orders 
                    (user_id, total, first_name, last_name, email, address, phone, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'new', NOW())");

                $stmt->execute([
                    $user ? $user['id'] : null,
                    $total,
                    $first_name,
                    $last_name,
                    $email,
                    $address,
                    $phone
                ]);

                $order_id = $pdo->lastInsertId();

                // Zapis produktów
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stockStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");

                foreach ($cart as $item) {
                    $stmt->execute([$order_id, $item['id'], $item['qty'], $item['price']]);
                    $stockStmt->execute([$item['qty'], $item['id'], $item['qty']]);
                    if ($stockStmt->rowCount() === 0) {
                        throw new Exception("Brak wystarczającej ilości produktu: {$item['name']}");
                    }
                }

                $pdo->commit();


                // Czyszczenie koszyka
                $success = "Zamówienie #$order_id złożone pomyślnie! Potwierdzenie wysłano na <strong>$email</strong>";
                echo "<script>localStorage.removeItem('cart'); setTimeout(()=>location.href='index.php', 6000);</script>";

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Błąd podczas składania zamówienia. Spróbuj ponownie.";
                error_log("Checkout error: " . $e->getMessage());
            }
        }
    }
}

// Sprawdzenie roli admina
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
    <title>Realizacja zamówienia – BeerShop</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="icon" href="assets/BeerShop.ico">
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
                Strona główna
            </a>
            <a href="konto.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'konto.php') ? 'active' : ''; ?>">
                Konto
            </a>
            <?php if ($is_admin): ?>
                <a href="admin/" class="nav-link admin-panel-btn <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') === 0) ? 'active' : ''; ?>">
                    Panel Admina
                </a>
            <?php endif; ?>
            <a href="koszyk.php" class="nav-link cart-link <?php echo (basename($_SERVER['PHP_SELF']) == 'koszyk.php') ? 'active' : ''; ?>">
                Koszyk
                <span class="cart-count" id="cart-count"></span>
            </a>
        </div>
    </div>
</nav>

<div class="cart-wrapper">
    <div class="cart-left">
        <h1 class="cart-header">Podsumowanie zamówienia</h1>
        <div class="cart-items" id="checkout-items"></div>
    </div>

    <div class="cart-right">
        <div class="cart-summary">
            <h2>Razem do zapłaty</h2>
            <div class="summary-total"><span id="total-price">0.00</span> zł</div>

            <?php if ($success): ?>
                <div class="success" style="font-size:1.9rem;padding:50px;text-align:center;background:rgba(76,175,80,0.15);border:3px solid #4caf50;border-radius:20px;">
                    <i class="fas fa-check-circle" style="font-size:4rem;color:#4caf50;margin-bottom:20px;"></i><br>
                    <?= $success ?><br><br>
                    Za chwilę zostaniesz przeniesiony do sklepu...<br><br>
                    <a href="index.php" style="background:#4caf50;padding:18px 50px;border-radius:50px;color:white;font-weight:bold;text-decoration:none;">Wróć teraz</a>
                </div>
            <?php else: ?>
                <?php foreach ($errors as $e): ?>
                    <div class="error"><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>

                <form method="POST" id="checkout-form">
                    <input type="hidden" name="cart" id="cart-input">

                    <?php if (!$user): ?>
                        <h3 style="color:var(--primary);margin:35px 0 20px;font-size:1.6rem;">Dane do wysyłki</h3>
                        <div class="form-group"><label>E-mail *</label><input type="email" name="email" required></div>
                        <div class="form-group"><label>Imię *</label><input type="text" name="first_name" required></div>
                        <div class="form-group"><label>Nazwisko *</label><input type="text" name="last_name" required></div>
                    <?php else: ?>
                        <p style="margin:35px 0;color:#ffc800;font-size:1.3rem;background:rgba(255,200,0,0.1);padding:20px;border-radius:15px;border-left:6px solid #ffc800;">
                            Zamawiasz jako: <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong><br>
                            <?= htmlspecialchars($user['email']) ?>
                        </p>
                        <input type="hidden" name="email" value="<?= $user['email'] ?>">
                        <input type="hidden" name="first_name" value="<?= $user['first_name'] ?>">
                        <input type="hidden" name="last_name" value="<?= $user['last_name'] ?>">
                    <?php endif; ?>

                    <div class="form-group"><label>Adres dostawy *</label><input type="text" name="address" required value="<?= htmlspecialchars($user['address'] ?? '') ?>"></div>
                    <div class="form-group"><label>Telefon *</label><input type="text" name="phone" required value="<?= htmlspecialchars($user['phone'] ?? '') ?>"></div>

                    <button type="submit" class="checkout-btn" style="margin-top:40px;padding:22px;font-size:1.7rem;">
                        Złóż zamówienie i zapłać
                    </button>
                </form>

                <p style="margin-top:40px;text-align:center;">
                    <a href="koszyk.php" style="color:var(--primary);font-size:1.2rem;text-decoration:underline;">← Wróć do koszyka</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const cart = JSON.parse(localStorage.getItem('cart') || '[]');

function renderCheckout() {
    const itemsEl = document.getElementById('checkout-items');
    const totalEl = document.getElementById('total-price');
    let total = 0;
    
    itemsEl.innerHTML = '';
    if (cart.length === 0) {
        itemsEl.innerHTML = '<p style="text-align:center;padding:120px;font-size:2rem;color:#555;">Koszyk jest pusty</p>';
        return;
    }

    cart.forEach(item => {
        total += item.price * item.qty;
        itemsEl.innerHTML += `
            <div class="cart-item">
                <img src="assets/${item.image}" onerror="this.src='assets/no-image.jpg'" class="cart-item-img">
                <div class="cart-item-name">${item.name}</div>
                <div class="cart-item-price">${item.price.toFixed(2)} zł/szt.</div>
                <div class="cart-item-qty"><span class="qty-number">${item.qty}</span></div>
                <div class="cart-item-total">${(item.price * item.qty).toFixed(2)} zł</div>
            </div>`;
    });

    totalEl.textContent = total.toFixed(2);
    document.getElementById('cart-input').value = JSON.stringify(cart);
}

function updateBadge() {
    const count = cart.reduce((sum, i) => sum + i.qty, 0);
    document.querySelectorAll('#cart-count').forEach(el => {
        el.textContent = count || '';
        el.style.display = count ? 'flex' : 'none';
    });
}

renderCheckout();
updateBadge();
</script>
</body>
</html>
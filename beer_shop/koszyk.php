<?php
session_start();
$pdo = new PDO(
    "mysql:host=mysql2.small.pl;dbname=m2378_beer;charset=utf8mb4",
    "m2378_admin",
    "Minecraft1",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$category = $_GET['category'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? 'name_asc';
$sql = "SELECT * FROM products WHERE 1=1";
$params = [];
if ($category !== '') { $sql .= " AND category = :category"; $params[':category'] = $category; }
if ($min_price !== '' && $min_price >= 0) { $sql .= " AND price >= :min_price"; $params[':min_price'] = (float)$min_price; }
if ($max_price !== '' && $max_price >= 0) { $sql .= " AND price <= :max_price"; $params[':max_price'] = (float)$max_price; }
switch ($sort) {
    case 'price_asc': $sql .= " ORDER BY price ASC"; break;
    case 'price_desc': $sql .= " ORDER BY price DESC"; break;
    case 'name_asc': $sql .= " ORDER BY name ASC"; break;
    case 'name_desc': $sql .= " ORDER BY name DESC"; break;
    default: $sql .= " ORDER BY name ASC";
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$is_admin = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $is_admin = $stmt->fetchColumn() === 'admin';
}
?>

<DOCUMENT filename="koszyk.php">
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koszyk - BeerShop</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="icon" href="assets/BeerShop.ico">
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
<div class="cart-wrapper">
    <div class="cart-left">
        <h1 class="cart-header">Koszyk</h1>
        <div class="cart-items" id="cart-items"></div>
    </div>

    <div class="cart-right">
        <div class="cart-summary" id="cart-summary">
            <h2>Razem do zapłaty</h2>
            <div class="summary-total"><span id="total-price">0.00</span> zł</div>
            <a href="checkout.php" class="checkout-btn" style="text-decoration:none;">Przejdź do płatności</a>
        </div>
        <div class="empty-cart" id="empty-cart">
            <div class="empty-cart-content">
                <i class="fas fa-shopping-cart empty-icon"></i>
                <p>Twój koszyk jest pusty</p>
                <a href="index.php" class="back-to-shop">Wróć do sklepu</a>
            </div>
        </div>
    </div>
</div>

<div id="custom-alert" class="custom-alert">
    <div class="alert-content">
        <i class="fas fa-check-circle alert-icon"></i>
        <div class="alert-text">Produkt dodany do koszyka!</div>
    </div>
</div>

<script>
let cart = JSON.parse(localStorage.getItem('cart') || '[]');

function showAlert(msg, success = true) {
    const alertEl = document.getElementById("custom-alert");
    const icon = alertEl.querySelector(".alert-icon");
    const text = alertEl.querySelector(".alert-text");

    // Reset dla bezpieczeństwa
    alertEl.classList.remove("show");
    alertEl.onclick = null;
    document.removeEventListener("click", closeAlert);

    icon.className = success ? "fas fa-check-circle alert-icon" : "fas fa-times-circle alert-icon";
    icon.style.color = success ? "#ffc800" : "#ff4444";
    text.textContent = msg;

    // Pokazanie alertu
    setTimeout(() => alertEl.classList.add("show"), 10);

    function closeAlert() {
        alertEl.classList.remove("show");
        alertEl.onclick = null;
        document.removeEventListener("click", closeAlert);
    }

    // kliknięcie w overlay zamyka
    alertEl.onclick = closeAlert;

    // kliknięcie gdziekolwiek zamyka
    setTimeout(() => document.addEventListener("click", closeAlert), 100);

    // auto-zamknięcie
    setTimeout(closeAlert, 1300);
}


function saveCart() {
    localStorage.setItem('cart', JSON.stringify(cart));
    renderCart();
}

function changeQty(i, delta) {
    const item = cart[i];
    const newQty = item.qty + delta;
    if (newQty > item.stock) {
        showAlert(`Na stanie tylko ${item.stock} szt.!`, false);
        return;
    }
    if (newQty < 1) {
        removeItem(i);
        return;
    }
    item.qty = newQty;
    saveCart();
}

function removeItem(i) {
    cart.splice(i, 1);
    showAlert("Produkt usunięty z koszyka", true);
    saveCart();
}

function renderCart() {
    const itemsEl = document.getElementById('cart-items');
    const sumEl = document.getElementById('cart-summary');
    const emptyEl = document.getElementById('empty-cart');
    const totalEl = document.getElementById('total-price');

    if (cart.length === 0) {
        sumEl.style.display = 'none';
        emptyEl.style.display = 'block';
        totalEl.textContent = '0.00';
    } else {
        sumEl.style.display = 'block';
        emptyEl.style.display = 'none';
    }

    let total = 0;
    itemsEl.innerHTML = '';
    cart.forEach((item, i) => {
        total += item.price * item.qty;
        const div = document.createElement('div');
        div.className = 'cart-item';
        div.innerHTML = `
            <img src="assets/${item.image}" alt="${item.name}" class="cart-item-img" onerror="this.src='assets/no-image.jpg'">
            <div class="cart-item-name">${item.name}</div>
            <div class="cart-item-price">${item.price.toFixed(2)} zł/szt.</div>
            <div class="cart-item-qty">
                <button onclick="changeQty(${i}, -1)">–</button>
                <span class="qty-number">${item.qty}</span>
                <button onclick="changeQty(${i}, 1)">+</button>
            </div>
            <div class="cart-item-total">${(item.price * item.qty).toFixed(2)} zł</div>
            <button class="cart-item-remove" onclick="removeItem(${i})">×</button>
        `;
        itemsEl.appendChild(div);
    });

    totalEl.textContent = total.toFixed(2);
    updateBadge();
}

function updateBadge() {
    const count = cart.reduce((s, i) => s + i.qty, 0);
    document.querySelectorAll('.cart-count').forEach(b => {
        b.textContent = count || '';
        b.style.display = count ? 'flex' : 'none';
    });
}

renderCart();
</script>
</body>
</html>
</DOCUMENT>
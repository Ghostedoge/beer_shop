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
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Strona Główna - BeerShop</title>
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
<div class="container">
    <aside class="filters">
        <h2>Filtry</h2>
        <form method="GET">
            <label>Typ piwa</label>
            <select name="category">
                <option value="">Wszystkie kategorie</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <label>Cena</label>
            <div class="price-range">
                <input type="number" name="min_price" min="0" step="0.10" placeholder="od" value="<?= htmlspecialchars($min_price ?? '') ?>">
                <input type="number" name="max_price" min="0" step="0.10" placeholder="do" value="<?= htmlspecialchars($max_price ?? '') ?>">
            </div>
            <label>Sortuj według</label>
            <select name="sort">
                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Nazwa: A → Z</option>
                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Nazwa: Z → A</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Cena: rosnąco</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Cena: malejąco</option>
            </select>
            <div class="filter-buttons">
                <button type="submit">Zastosuj filtry</button>
                <a href="index.php" class="reset-btn">Wyczyść wszystko</a>
            </div>
        </form>
    </aside>
    <main class="products">
        <?php if (empty($products)): ?>
            <p class="no-results">Nie znaleziono piw spełniających kryteria.</p>
        <?php else: ?>
            <?php foreach ($products as $p): ?>
                <div class="product-card" onclick="openModal(<?= $p['id'] ?>)">
                    <img src="assets/<?= htmlspecialchars($p['image'] ?: 'no-image.jpg') ?>" alt="<?= htmlspecialchars($p['name']) ?>" onerror="this.src='assets/no-image.jpg'">
                    <h3><?= htmlspecialchars($p['name']) ?></h3>
                    <p class="category"><?= htmlspecialchars($p['category']) ?></p>
                    <span class="price"><?= number_format($p['price'], 2) ?> zł</span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</div>

<div id="product-modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">×</span>
        <div id="modal-body"></div>
    </div>
</div>

<div id="custom-alert" class="custom-alert">
    <div class="alert-content">
        <div class="alert-text">Produkt dodany do koszyka!</div>
    </div>
</div>

<script>
let blockUntil = 0;
function getCart() { return JSON.parse(localStorage.getItem('cart') || '[]'); }
function saveCart(c) { localStorage.setItem('cart', JSON.stringify(c)); updateCartCount(); }
function updateCartCount() {
    const count = getCart().reduce((s, i) => s + i.qty, 0);
    document.querySelectorAll('#cart-count').forEach(el => {
        el.textContent = count || '';
        el.style.display = count ? 'flex' : 'none';
    });
}
function showAlert(msg, success = true) {
    const alertEl = document.getElementById("custom-alert");
    const text = alertEl.querySelector(".alert-text");
    alertEl.classList.remove("show");
    text.textContent = msg;
    setTimeout(() => alertEl.classList.add("show"), 10);
    setTimeout(() => alertEl.classList.remove("show"), 2000);
  setTimeout(() => document.addEventListener("click", closeAlert), 100);
  
  function closeAlert() {
        alertEl.classList.remove("show");
        alertEl.onclick = null;
        document.removeEventListener("click", closeAlert);
    }

    alertEl.onclick = closeAlert;

    setTimeout(() => document.addEventListener("click", closeAlert), 100);

    setTimeout(closeAlert, 1300);
}
function addToCart(id, name, price, stock, image) {
    if (Date.now() < blockUntil) return;
    const qtyInput = document.getElementById('qty-input');
    let qty = Math.max(1, parseInt(qtyInput?.value) || 1);
    let cart = getCart();
    const existing = cart.find(i => i.id == id);
    const newTotal = existing ? existing.qty + qty : qty;
    if (newTotal > stock) {
        showAlert(`Na stanie tylko ${stock} szt.!`, false);
        return;
    }
    if (existing) existing.qty = newTotal;
    else cart.push({ id, name, price, qty, stock, image });
    saveCart(cart);
    showAlert(`Dodano ${qty} × ${name}`, true);
    closeModal();
}
function openModal(id) {
    document.getElementById("product-modal").classList.add("active");
    document.getElementById("modal-body").innerHTML = '<div style="text-align:center;padding:100px;color:#888;">Ładowanie...</div>';
    fetch(`product.php?id=${id}`)
        .then(r => r.text())
        .then(html => {
            document.getElementById("modal-body").innerHTML = html;
        });
}
function closeModal() {
    const modal = document.getElementById("product-modal");
    modal.classList.remove("active");
    setTimeout(() => document.getElementById("modal-body").innerHTML = '', 350);
                     
}
window.addEventListener("click", e => {
    if (e.target.id === "product-modal") closeModal();
});
updateCartCount();
</script>
</body>
</html>
<?php
$pdo = new PDO(
    "mysql:host=mysql2.small.pl;dbname=m2378_beer;charset=utf8mb4",
    "m2378_admin",
    "Minecraft1",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    echo "<p style='text-align:center;padding:100px;color:#aaa;'>Produkt nie istnieje.</p>";
    exit;
}
?>

<h2><?= htmlspecialchars($p['name']) ?></h2>

<div class="modal-product-wrapper">
    <div class="modal-product-image">
        <img src="assets/<?= htmlspecialchars($p['image'] ?: 'no-image.jpg') ?>"
             alt="<?= htmlspecialchars($p['name']) ?>"
             onerror="this.src='assets/no-image.jpg'">
    </div>

    <div class="modal-product-details">
        <div class="modal-info">
            <p><strong>Kategoria:</strong> <?= htmlspecialchars($p['category']) ?></p>
            <p><strong>Cena:</strong> <span class="modal-price"><?= number_format($p['price'], 2) ?> zł/szt.</span></p>
            <p><strong>Na stanie:</strong> <?= $p['stock'] ?> szt.</p>
            <?php if (!empty($p['description'])): ?>
                <div class="modal-description">
                    <strong>Opis:</strong><br>
                    <?= nl2br(htmlspecialchars($p['description'])) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="modal-actions">
            <button class="add-to-cart" onclick="addToCart(
                <?= $p['id'] ?>,
                '<?= addslashes(htmlspecialchars($p['name'])) ?>',
                <?= $p['price'] ?>,
                <?= $p['stock'] ?>,
                '<?= htmlspecialchars($p['image'] ?: 'no-image.jpg') ?>'
            )">Do koszyka</button>

            <div class="quantity-selector">
                <label>Ilość:</label>
                <input type="number" id="qty-input" min="1" max="<?= min(100, $p['stock']) ?>" value="1">
                <span>(max <?= min(100, $p['stock']) ?> szt.)</span>
            </div>
        </div>
    </div>
</div>
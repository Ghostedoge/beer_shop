<?php
session_start();
$pdo = new PDO("mysql:host=mysql2.small.pl;dbname=m2378_beer;charset=utf8mb4", "m2378_admin", "Minecraft1", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Ochrona dostępu
if (!isset($_SESSION['user_id'])) { header("Location: ../konto.php"); exit; }
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() !== 'admin') {
    die("<h1 style='text-align:center;padding:120px;color:#ff4444;background:#000;font-family:sans-serif;'>Brak dostępu – tylko administrator</h1>");
}

$section = $_GET['s'] ?? 'dashboard';
$msg = '';

function recalculateOrderTotal($pdo, $order_id) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(price * quantity), 0) FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $total = $stmt->fetchColumn();
    $stmt = $pdo->prepare("UPDATE orders SET total = ? WHERE id = ?");
    $stmt->execute([$total, $order_id]);
}

// === WSZYSTKIE AKCJE ===
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_product':
            $name = trim($_POST['name']); $price = (float)$_POST['price']; $category = $_POST['category'];
            $stock = (int)$_POST['stock']; $desc = $_POST['description'] ?? ''; $image = '';
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0) {
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $image = uniqid() . '.' . strtolower($ext);
                move_uploaded_file($_FILES['image']['tmp_name'], '../assets/' . $image);
            }
            $stmt = $pdo->prepare("INSERT INTO products (name, price, category, stock, image, description) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$name, $price, $category, $stock, $image, $desc]);
            $msg = "Produkt dodany!";
            break;

        case 'edit_product':
            $id = (int)$_POST['id']; $name = trim($_POST['name']); $price = (float)$_POST['price'];
            $category = $_POST['category']; $stock = (int)$_POST['stock']; $desc = $_POST['description'] ?? '';
            $sql = "UPDATE products SET name=?, price=?, category=?, stock=?, description=?"; $params = [$name, $price, $category, $stock, $desc];
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0) {
                $stmt = $pdo->prepare("SELECT image FROM products WHERE id=?"); $stmt->execute([$id]); $old = $stmt->fetchColumn();
                if ($old && file_exists("../assets/$old")) @unlink("../assets/$old");
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $newimg = uniqid() . '.' . strtolower($ext);
                move_uploaded_file($_FILES['image']['tmp_name'], '../assets/' . $newimg);
                $sql .= ", image=?"; $params[] = $newimg;
            }
            $sql .= " WHERE id=?"; $params[] = $id;
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $msg = "Produkt zaktualizowany!";
            break;

        case 'edit_order':
            $stmt = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
            $stmt->execute([$_POST['status'], (int)$_POST['order_id']]);
            $msg = "Status zamówienia zmieniony!";
            break;

        case 'edit_user':
            $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, role=?, phone=?, address=? WHERE id=?");
            $stmt->execute([$_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['role'], $_POST['phone']??null, $_POST['address']??null, (int)$_POST['id']]);
            $msg = "Użytkownik zaktualizowany!";
            break;

        case 'edit_item':
            $stmt = $pdo->prepare("UPDATE order_items SET quantity=?, price=? WHERE id=?");
            $stmt->execute([max(1,(int)$_POST['quantity']), (float)$_POST['price'], (int)$_POST['item_id']]);
            recalculateOrderTotal($pdo, (int)$_POST['order_id']);
            $msg = "Pozycja zaktualizowana!";
            break;

        case 'add_item':
            $stmt = $pdo->prepare("SELECT price FROM products WHERE id=?"); $stmt->execute([(int)$_POST['product_id']]); $price = $stmt->fetchColumn() ?: 0;
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?,?,?,?)");
            $stmt->execute([(int)$_POST['order_id'], (int)$_POST['product_id'], max(1,(int)$_POST['quantity']), $price]);
            recalculateOrderTotal($pdo, (int)$_POST['order_id']);
            $msg = "Pozycja dodana!";
            break;
    }
}

if (!empty($_GET['del_product'])) {
    $id = (int)$_GET['del_product'];
    $stmt = $pdo->prepare("SELECT image FROM products WHERE id=?"); $stmt->execute([$id]); $img = $stmt->fetchColumn();
    if ($img && file_exists("../assets/$img")) @unlink("../assets/$img");
    $stmt = $pdo->prepare("DELETE FROM products WHERE id=?"); $stmt->execute([$id]);
    $msg = "Produkt usunięty!";
}

if (!empty($_GET['del_item'])) {
    $item_id = (int)$_GET['del_item'];
    $stmt = $pdo->prepare("SELECT order_id FROM order_items WHERE id=?"); $stmt->execute([$item_id]); $order_id = $stmt->fetchColumn();
    $stmt = $pdo->prepare("DELETE FROM order_items WHERE id=?"); $stmt->execute([$item_id]);
    recalculateOrderTotal($pdo, $order_id);
    $msg = "Pozycja usunięta!";
    header("Location: ?s=items&order_id=$order_id"); exit;
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admina – BeerShop</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="icon" href="../assets/BeerShop.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        :root{--p:#ffc800;--bg:#0a0a0a;--c:#1a1a1a;--t:#f0f0f0;--m:#bbb;--s:#00d26a;--d:#ff4444;--w:#ff9500}
        body{background:var(--bg);color:var(--t);font-family:'Segoe UI',sans-serif;margin:0;padding:0;min-height:100vh}
        .wrapper{max-width:1500px;margin:0 auto;padding:15px}
        header{background:var(--c);padding:18px 30px;border-radius:14px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 8px 30px rgba(0,0,0,0.6);border:1px solid #333}
        header h1{color:var(--p);font-size:2rem;margin:0;font-weight:800}
        .nav{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:25px}
        .nav a{padding:12px 24px;background:var(--c);color:var(--m);border-radius:10px;font-weight:600;transition:.3s;text-decoration:none}
        .nav a:hover,.nav a.active{background:var(--p);color:#000;transform:translateY(-3px);box-shadow:0 10px 25px rgba(255,200,0,0.3)}
        .card{background:var(--c);border-radius:14px;padding:25px;margin-bottom:25px;box-shadow:0 8px 30px rgba(0,0,0,0.6);border:1px solid #333}
        table{width:100%;border-collapse:collapse;font-size:0.95rem}
        th{background:var(--p);color:#000;padding:14px 12px;text-align:left;font-weight:700;border-bottom:1px solid #333}
        td{padding:14px 12px;border-bottom:1px solid #333;vertical-align:middle}
        tr:hover{background:rgba(255,200,0,0.06)}
        .btn{padding:9px 18px;border:none;border-radius:9px;font-weight:600;cursor:pointer;margin:3px 5px;font-size:0.92rem;transition:.3s;display:inline-block;text-decoration:none}
        .btn-p{background:var(--p);color:#000}
        .btn-s{background:var(--s);color:#fff}
        .btn-d{background:var(--d);color:#fff}
        .btn-w{background:var(--w);color:#fff}
        .btn-sm{padding:7px 14px;font-size:0.88rem}
        .btn-p:hover,.btn-s:hover,.btn-d:hover,.btn-w:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,0.4)}
        .status{padding:7px 16px;border-radius:20px;font-size:0.88rem;font-weight:600}
        .status.new{background:#333;color:#fff}
        .status.paid{background:#ffd700;color:#000}
        .status.sent{background:#3498db;color:#fff}
        .status.completed{background:var(--s);color:#fff}
        .status.canceled{background:#880808;color:#fff}
        .msg{padding:16px;background:rgba(255,200,0,0.15);border-left:6px solid var(--p);border-radius:10px;margin:15px 0;font-weight:600}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.95);z-index:9999;align-items:center;justify-content:center;padding:20px;overflow-y:auto}
        .modal.active{display:flex}
        .modal-content{background:var(--c);padding:30px 35px;border-radius:16px;width:100%;max-width:560px;border:2px solid var(--p);box-shadow:0 0 50px rgba(255,200,0,0.4);position:relative}
        .close{position:absolute;top:12px;right:18px;font-size:2.2rem;cursor:pointer;color:#aaa;transition:.3s}
        .close:hover{color:var(--p);transform:scale(1.2)}
        .form-group{margin:18px 0}
        .form-group label{display:block;margin-bottom:8px;color:var(--m);font-weight:600;font-size:0.98rem}
        .form-group input,.form-group select,.form-group textarea{width:100%;padding:13px;background:#2a2a2a;border:none;border-radius:10px;color:#fff;font-size:1rem;transition:.3s}
        .form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;box-shadow:0 0 0 3px rgba(255,200,0,0.3)}
        @media (max-width:768px){
            header{flex-direction:column;text-align:center;gap:15px}
            .nav{justify-content:center}
            table{font-size:0.88rem}
            th,td{padding:10px 8px}
            .modal-content{padding:25px}
        }
    </style>
</head>
<body>

<div class="wrapper">
    <header>
        <h1>Panel Administratora – BeerShop</h1>
        <a href="../index.php" class="btn btn-d">Wróć do sklepu</a>
    </header>

    <?php if($msg): ?><div class="msg"><?=htmlspecialchars($msg)?></div><?php endif; ?>

    <div class="nav">
        <a href="?s=dashboard" class="<?=$section==='dashboard'?'active':''?>">Dashboard</a>
        <a href="?s=products" class="<?=$section==='products'?'active':''?>">Produkty</a>
        <a href="?s=orders" class="<?=$section==='orders'?'active':''?>">Zamówienia</a>
        <a href="?s=users" class="<?=$section==='users'?'active':''?>">Użytkownicy</a>
    </div>

    <?php if($section === 'dashboard'): ?>
        <div class="card">
            <h2 style="color:var(--p);margin-bottom:25px;font-size:2rem;text-align:center">Statystyki sklepu</h2>
            <table>
                <tr><th>Liczba produktów</th><td style="font-size:2.2rem;color:var(--p);text-align:center"><?= $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn() ?></td></tr>
                <tr><th>Liczba użytkowników</th><td style="font-size:2.2rem;color:var(--p);text-align:center"><?= $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?></td></tr>
                <tr><th>Liczba zamówień</th><td style="font-size:2.2rem;color:var(--p);text-align:center"><?= $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() ?></td></tr>
                <tr><th>Przychód całkowity</th><td style="font-size:2.8rem;color:var(--s);font-weight:900;text-align:center"><?= number_format($pdo->query("SELECT COALESCE(SUM(total),0) FROM orders")->fetchColumn(), 2) ?> zł</td></tr>
            </table>
        </div>

    <?php elseif($section === 'products'): ?>
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <h2 style="color:var(--p);margin:0;font-size:1.9rem">Produkty</h2>
                <button onclick="document.getElementById('add-modal').classList.add('active')" class="btn btn-p">+ Dodaj produkt</button>
            </div>
            <table>
                <tr><th>ID</th><th>Zdjęcie</th><th>Nazwa</th><th>Cena</th><th>Kategoria</th><th>Stan</th><th>Akcje</th></tr>
                <?php foreach($pdo->query("SELECT * FROM products ORDER BY id DESC") as $p): ?>
                <tr>
                    <td>#<?=$p['id']?></td>
                    <td><img src="../assets/<?=htmlspecialchars($p['image']?:'no-image.jpg')?>" width="55" style="border-radius:8px" onerror="this.src='../assets/no-image.jpg'"></td>
                    <td style="font-weight:700"><?=htmlspecialchars($p['name'])?></td>
                    <td style="color:var(--p);font-weight:800"><?=number_format($p['price'],2)?> zł</td>
                    <td><?=htmlspecialchars($p['category'])?></td>
                    <td><?=$p['stock']>0?"<span style='color:var(--s);font-weight:700'>{$p['stock']} szt.</span>":"<span style='color:var(--d);font-weight:700'>Brak</span>"?></td>
                    <td>
                        <button class="btn btn-w btn-sm" onclick='editProduct(<?=json_encode($p,JSON_HEX_APOS|JSON_HEX_QUOT)?>)'>Edytuj</button>
                        <a href="?del_product=<?=$p['id']?>" class="btn btn-d btn-sm" onclick="return confirm('Na pewno usunąć?')">Usuń</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

    <?php elseif($section === 'orders'): ?>
        <div class="card">
            <h2 style="color:var(--p);margin-bottom:20px;font-size:1.9rem">Zamówienia</h2>
            <table>
                <tr><th>ID</th><th>Data</th><th>Klient</th><th>E-mail</th><th>Telefon</th><th>Adres</th><th>Wartość</th><th>Status</th><th>Akcje</th></tr>
                <?php foreach($pdo->query("SELECT o.*, o.email as order_email, o.first_name, o.last_name, o.phone, o.address FROM orders o ORDER BY created_at DESC") as $o): ?>
                <tr>
                    <td><strong>#<?=$o['id']?></strong></td>
                    <td><?=date('d.m H:i',strtotime($o['created_at']))?></td>
                    <td style="font-weight:700"><?=htmlspecialchars($o['first_name'].' '.$o['last_name'])?></td>
                    <td><?=htmlspecialchars($o['order_email'])?></td>
                    <td><?=htmlspecialchars($o['phone']??'-')?></td>
                    <td style="max-width:190px;font-size:0.9rem"><?=nl2br(htmlspecialchars($o['address']))?></td>
                    <td style="color:var(--p);font-weight:900;font-size:1.1rem"><?=number_format($o['total'],2)?> zł</td>
                    <td><span class="status <?=$o['status']?>"><?=['new'=>'Nowe','paid'=>'Opłacone','sent'=>'Wysłane','completed'=>'Zrealizowane', 'canceled'=>'Anulowane'][$o['status']]??$o['status']?></span></td>
                    <td>
                        <button class="btn btn-s btn-sm" onclick='editOrder(<?=json_encode($o,JSON_HEX_APOS|JSON_HEX_QUOT)?>)'>Zmień status</button>
                        <a href="?s=items&order_id=<?=$o['id']?>" class="btn btn-p btn-sm">Szczegóły</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

    <?php elseif($section === 'items' && !empty($_GET['order_id'])): ?>
        <?php $order_id = (int)$_GET['order_id']; ?>
        <div class="card">
            <h2 style="color:var(--p);margin-bottom:20px">Zamówienie #<?=$order_id?> <a href="?s=orders" class="btn btn-d" style="float:right;font-size:0.9rem">Wróć</a></h2>
            <button onclick="openAddItemModal(<?=$order_id?>)" class="btn btn-s" style="margin-bottom:15px">+ Dodaj pozycję</button>
            <table>
                <tr><th>Produkt</th><th>Ilość</th><th>Cena jedn.</th><th>Razem</th><th>Akcje</th></tr>
                <?php
                $stmt = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE order_id = ?");
                $stmt->execute([$order_id]);
                foreach($stmt as $i):
                ?>
                <tr>
                    <td style="font-weight:700"><?=htmlspecialchars($i['name']??'Usunięty produkt')?></td>
                    <td><?=$i['quantity']?></td>
                    <td><?=number_format($i['price'],2)?> zł</td>
                    <td style="color:var(--p);font-weight:800"><?=number_format($i['price']*$i['quantity'],2)?> zł</td>
                    <td>
                        <button class="btn btn-w btn-sm" onclick='editItem(<?=json_encode($i,JSON_HEX_APOS|JSON_HEX_QUOT)?>,<?=$order_id?>)'>Edytuj</button>
                        <a href="?del_item=<?=$i['id']?>" class="btn btn-d btn-sm" onclick="return confirm('Usunąć pozycję?')">Usuń</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

    <?php elseif($section === 'users'): ?>
        <div class="card">
            <h2 style="color:var(--p);margin-bottom:20px">Użytkownicy</h2>
            <table>
                <tr><th>ID</th><th>Imię i nazwisko</th><th>E-mail</th><th>Rola</th><th>Rejestracja</th><th>Akcje</th></tr>
                <?php foreach($pdo->query("SELECT * FROM users ORDER BY created_at DESC") as $u): ?>
                <tr>
                    <td>#<?=$u['id']?></td>
                    <td style="font-weight:700"><?=htmlspecialchars($u['first_name'].' '.$u['last_name'])?></td>
                    <td><?=htmlspecialchars($u['email'])?></td>
                    <td><?=$u['role']==='admin'?'<strong style="color:var(--p)">ADMIN</strong>':'Klient'?></td>
                    <td><?=date('d.m.Y',strtotime($u['created_at']))?></td>
                    <td><button class="btn btn-w btn-sm" onclick='editUser(<?=json_encode($u,JSON_HEX_APOS|JSON_HEX_QUOT)?>)'>Edytuj</button></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>

<div id="add-modal" class="modal"><div class="modal-content">
    <span class="close" onclick="this.parentNode.parentNode.classList.remove('active')">x</span>
    <h2>Dodaj produkt</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_product">
        <div class="form-group"><label>Nazwa</label><input name="name" required></div>
        <div class="form-group"><label>Cena</label><input name="price" type="number" step="0.01" required></div>
        <div class="form-group"><label>Kategoria</label><input name="category" required></div>
        <div class="form-group"><label>Na stanie</label><input name="stock" type="number" value="10" required></div>
        <div class="form-group"><label>Opis</label><textarea name="description" rows="4"></textarea></div>
        <div class="form-group"><label>Zdjęcie</label><input type="file" name="image" accept="image/*"></div>
        <button type="submit" class="btn btn-p" style="width:100%;padding:15px;font-size:1.1rem">Dodaj produkt</button>
    </form>
</div></div>

<div id="edit-modal" class="modal"><div class="modal-content">
    <span class="close" onclick="this.parentNode.parentNode.classList.remove('active')">x</span>
    <h2>Edytuj produkt</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="edit_product">
        <input type="hidden" name="id" id="pid">
        <div class="form-group"><label>Nazwa</label><input name="name" id="pname" required></div>
        <div class="form-group"><label>Cena</label><input name="price" id="pprice" type="number" step="0.01" required></div>
        <div class="form-group"><label>Kategoria</label><input name="category" id="pcat" required></div>
        <div class="form-group"><label>Na stanie</label><input name="stock" id="pstock" type="number" required></div>
        <div class="form-group"><label>Opis</label><textarea name="description" id="pdesc" rows="4"></textarea></div>
        <div class="form-group"><label>Nowe zdjęcie</label><input type="file" name="image" accept="image/*"></div>
        <button type="submit" class="btn btn-p" style="width:100%;padding:15px;font-size:1.1rem">Zapisz zmiany</button>
    </form>
</div></div>

<div id="user-modal" class="modal"><div class="modal-content">
    <span class="close" onclick="this.parentNode.parentNode.classList.remove('active')">x</span>
    <h2>Edytuj użytkownika</h2>
    <form method="POST">
        <input type="hidden" name="action" value="edit_user">
        <input type="hidden" name="id" id="uid">
        <div class="form-group"><label>Imię</label><input name="first_name" id="ufirst" required></div>
        <div class="form-group"><label>Nazwisko</label><input name="last_name" id="ulast" required></div>
        <div class="form-group"><label>E-mail</label><input name="email" id="uemail" type="email" required></div>
        <div class="form-group"><label>Rola</label>
            <select name="role"><option value="client">client</option><option value="admin">admin</option></select>
        </div>
        <div class="form-group"><label>Telefon</label><input name="phone" id="uphone"></div>
        <div class="form-group"><label>Adres</label><input name="address" id="uaddress"></div>
        <button type="submit" class="btn btn-p" style="width:100%;padding:15px;font-size:1.1rem">Zapisz</button>
    </form>
</div></div>

<div id="order-modal" class="modal"><div class="modal-content">
    <span class="close" onclick="this.parentNode.parentNode.classList.remove('active')">x</span>
    <h2>Zmień status zamówienia</h2>
    <form method="POST">
        <input type="hidden" name="action" value="edit_order">
        <input type="hidden" name="order_id" id="order_id_edit">
        <div class="form-group">
            <label>Status</label>
            <select name="status" id="order_status">
                <option value="new">Nowe</option>
                <option value="paid">Opłacone</option>
                <option value="sent">Wysłane</option>
                <option value="completed">Zrealizowane</option>
                <option value="canceled">Anulowane</option>
            </select>
        </div>
        <button type="submit" class="btn btn-s" style="width:100%;padding:15px;font-size:1.1rem">Zapisz</button>
    </form>
</div></div>

<div id="edit-item-modal" class="modal"><div class="modal-content">
    <span class="close" onclick="this.parentNode.parentNode.classList.remove('active')">x</span>
    <h2>Edytuj pozycję</h2>
    <form method="POST">
        <input type="hidden" name="action" value="edit_item">
        <input type="hidden" name="item_id" id="item_id">
        <input type="hidden" name="order_id" id="item_order_id">
        <div class="form-group"><label>Ilość</label><input name="quantity" id="item_qty" type="number" min="1" required></div>
        <div class="form-group"><label>Cena jednostkowa (zł)</label><input name="price" id="item_price" type="number" step="0.01" required></div>
        <button type="submit" class="btn btn-p" style="width:100%;padding:15px;font-size:1.1rem">Zapisz</button>
    </form>
</div></div>

<div id="add-item-modal" class="modal"><div class="modal-content">
    <span class="close" onclick="this.parentNode.parentNode.classList.remove('active')">x</span>
    <h2>Dodaj pozycję do zamówienia</h2>
    <form method="POST">
        <input type="hidden" name="action" value="add_item">
        <input type="hidden" name="order_id" id="add_order_id">
        <div class="form-group">
            <label>Produkt</label>
            <select name="product_id" required>
                <option value="">-- wybierz produkt --</option>
                <?php foreach($pdo->query("SELECT id, name, price FROM products ORDER BY name") as $p): ?>
                    <option value="<?=$p['id']?>"><?=htmlspecialchars($p['name'])?> (<?=number_format($p['price'],2)?> zł)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Ilość</label><input name="quantity" type="number" min="1" value="1" required></div>
        <button type="submit" class="btn btn-s" style="width:100%;padding:15px;font-size:1.1rem">Dodaj</button>
    </form>
</div></div>

<script>
function editProduct(p){document.getElementById('pid').value=p.id;document.getElementById('pname').value=p.name;document.getElementById('pprice').value=p.price;document.getElementById('pcat').value=p.category;document.getElementById('pstock').value=p.stock;document.getElementById('pdesc').value=p.description||'';document.getElementById('edit-modal').classList.add('active');}
function editUser(u){document.getElementById('uid').value=u.id;document.getElementById('ufirst').value=u.first_name;document.getElementById('ulast').value=u.last_name;document.getElementById('uemail').value=u.email;document.querySelector('#user-modal select').value=u.role;document.getElementById('uphone').value=u.phone||'';document.getElementById('uaddress').value=u.address||'';document.getElementById('user-modal').classList.add('active');}
function editOrder(o){document.getElementById('order_id_edit').value=o.id;document.getElementById('order_status').value=o.status||'new';document.getElementById('order-modal').classList.add('active');}
function editItem(i,oid){document.getElementById('item_id').value=i.id;document.getElementById('item_order_id').value=oid;document.getElementById('item_qty').value=i.quantity;document.getElementById('item_price').value=i.price;document.getElementById('edit-item-modal').classList.add('active');}
function openAddItemModal(oid){document.getElementById('add_order_id').value=oid;document.getElementById('add-item-modal').classList.add('active');}
</script>
</body>
</html>
<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'db.php';

try {
    $pdo = getDbConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Database connection failed: ' . htmlspecialchars($e->getMessage());
    exit;
}

$stallId = (int)($_GET['stall'] ?? ($_GET['id'] ?? 0));
$stall = $stallId > 0 ? fetchStallById($pdo, $stallId) : null;

if (!$stall) {
    http_response_code(404);
}

$signatureItems = $stall['menu']['signature'] ?? [];
$sideItems = $stall['menu']['sides'] ?? [];
$publicOrderUrl = $stall ? 'stall.php?stall=' . (int)$stall['id'] : 'index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $stall ? htmlspecialchars((string)$stall['name']) . ' Order Page' : 'NakBurger Stall' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at top left, rgba(255, 184, 77, 0.2), transparent 35%),
                        radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 28%),
                        linear-gradient(135deg, #fff8ef, #f9fff7 52%, #eef6ff);
            min-height: 100vh;
        }

        .brand {
            font-weight: 800;
            letter-spacing: -0.7px;
        }

        .glass {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 22px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
        }

        .menu-card {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 18px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .menu-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
        }

        .section-label {
            letter-spacing: 0.14em;
            font-size: 0.72rem;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
        }

        .cart-sticky {
            position: sticky;
            top: 92px;
        }

        .cart-item {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #fff;
        }

        .hero-badge {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 999px;
            padding: 0.35rem 0.75rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top py-3">
        <div class="container-fluid px-lg-5">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <span class="bg-warning text-dark px-3 py-2 rounded-3 me-2 d-inline-block shadow-sm">
                    <i class="bi bi-fire"></i>
                </span>
                <span class="brand text-white fs-4">Nak<span class="text-warning">Burger</span></span>
            </a>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-light px-3" href="index.php"><i class="bi bi-arrow-left me-1"></i> Back</a>
                <a class="btn btn-outline-warning px-3" href="owner.php"><i class="bi bi-shop me-1"></i> Owner</a>
                <a class="btn btn-warning px-3 fw-bold" href="<?= htmlspecialchars($publicOrderUrl) ?>"><i class="bi bi-link-45deg me-1"></i> Share Page</a>
            </div>
        </div>
    </nav>

    <main class="container py-4 py-lg-5">
        <?php if (!$stall): ?>
            <div class="glass p-5 text-center">
                <i class="bi bi-shop-window fs-1 text-warning"></i>
                <h1 class="fw-bold mt-3 mb-2">Stall not found</h1>
                <p class="text-secondary mb-4">The customer ordering page needs a valid stall link.</p>
                <a class="btn btn-dark" href="index.php">Browse stalls</a>
            </div>
        <?php else: ?>
            <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <div class="glass p-4 p-lg-5 mb-4">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge text-bg-warning text-dark hero-badge"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars((string)$stall['type']) ?></span>
                            <span class="badge text-bg-light text-dark hero-badge"><i class="bi bi-star-fill text-warning me-1"></i><?= number_format((float)$stall['rating'], 1) ?> rating</span>
                            <span class="badge text-bg-light text-dark hero-badge"><i class="bi bi-receipt me-1"></i><?= (int)$stall['reviews'] ?> reviews</span>
                        </div>

                        <div class="row g-4 align-items-center">
                            <div class="col-md-8">
                                <p class="section-label mb-2">Customer Order Page</p>
                                <h1 class="fw-bold display-6 mb-2"><?= htmlspecialchars((string)$stall['name']) ?></h1>
                                <p class="text-secondary mb-3"><?= htmlspecialchars((string)$stall['specialty']) ?></p>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="hero-badge"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars((string)$stall['address']) ?></span>
                                    <?php if (!empty($stall['owner']['name'])): ?>
                                        <span class="hero-badge"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars((string)$stall['owner']['name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <div class="display-6 fw-bold text-warning mb-0">Order</div>
                                <div class="text-secondary">direct from this stall</div>
                            </div>
                        </div>
                    </div>

                    <div class="glass p-4 p-lg-5 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <p class="section-label mb-1">Signature Picks</p>
                                <h4 class="fw-bold mb-0">Burgers</h4>
                            </div>
                            <span class="text-secondary small"><?= count($signatureItems) ?> items</span>
                        </div>
                        <div class="row g-3">
                            <?php if (!$signatureItems): ?>
                                <div class="col-12">
                                    <div class="alert alert-light border mb-0">This stall has not added signature items yet.</div>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($signatureItems as $item): ?>
                                <div class="col-md-6">
                                    <div class="menu-card p-3 h-100 bg-white">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <h5 class="fw-bold mb-1"><?= htmlspecialchars((string)($item['name'] ?? 'Menu Item')) ?></h5>
                                                <p class="text-secondary small mb-2"><?= htmlspecialchars((string)($item['desc'] ?? '')) ?></p>
                                                <div class="fw-bold text-success">RM <?= number_format((float)($item['price'] ?? 0), 2) ?></div>
                                            </div>
                                            <div class="fs-2 flex-shrink-0"><?= htmlspecialchars((string)($item['emoji'] ?? '🍔')) ?></div>
                                        </div>
                                        <button class="btn btn-warning w-100 mt-3 fw-bold" type="button" onclick='addToCart(<?= json_encode((string)($item['name'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode((float)($item['price'] ?? 0), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode((string)($item['emoji'] ?? '🍔'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode((string)($item['desc'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'>Add to order</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="glass p-4 p-lg-5">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <p class="section-label mb-1">Sides and Drinks</p>
                                <h4 class="fw-bold mb-0">Extras</h4>
                            </div>
                            <span class="text-secondary small"><?= count($sideItems) ?> items</span>
                        </div>
                        <div class="row g-3">
                            <?php if (!$sideItems): ?>
                                <div class="col-12">
                                    <div class="alert alert-light border mb-0">This stall has not added sides or drinks yet.</div>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($sideItems as $item): ?>
                                <div class="col-md-6">
                                    <div class="menu-card p-3 h-100 bg-white">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <h5 class="fw-bold mb-1"><?= htmlspecialchars((string)($item['name'] ?? 'Menu Item')) ?></h5>
                                                <p class="text-secondary small mb-2"><?= htmlspecialchars((string)($item['desc'] ?? '')) ?></p>
                                                <div class="fw-bold text-success">RM <?= number_format((float)($item['price'] ?? 0), 2) ?></div>
                                            </div>
                                            <div class="fs-2 flex-shrink-0"><?= htmlspecialchars((string)($item['emoji'] ?? '🥤')) ?></div>
                                        </div>
                                        <button class="btn btn-outline-dark w-100 mt-3 fw-bold" type="button" onclick='addToCart(<?= json_encode((string)($item['name'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode((float)($item['price'] ?? 0), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode((string)($item['emoji'] ?? '🥤'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode((string)($item['desc'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'>Add to order</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="cart-sticky">
                        <div class="glass p-4 p-lg-4">
                            <p class="section-label mb-1">Checkout</p>
                            <h4 class="fw-bold mb-3">Your basket</h4>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Customer name</label>
                                <input class="form-control form-control-lg" id="customerName" placeholder="Guest">
                            </div>

                            <div id="cartItems" class="d-flex flex-column gap-2 mb-3"></div>

                            <div class="border-top pt-3 d-flex justify-content-between align-items-center mb-3">
                                <span class="fw-semibold">Total</span>
                                <span class="fs-4 fw-bold text-success" id="cartTotal">RM 0.00</span>
                            </div>

                            <button class="btn btn-dark w-100 fw-bold py-2" type="button" onclick="submitOrder()">Place Order</button>
                            <p class="text-secondary small mt-3 mb-0">Orders go straight to the kitchen board for this stall.</p>
                        </div>

                        <div class="alert alert-warning border-0 shadow-sm mt-3 mb-0" id="statusBox">
                            Add menu items to start an order.
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        const stall = <?= json_encode($stall, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const cart = [];

        function money(value) {
            return 'RM ' + Number(value || 0).toFixed(2);
        }

        function setStatus(message, type = 'warning') {
            const box = document.getElementById('statusBox');
            if (!box) return;

            box.className = 'alert border-0 shadow-sm mt-3 mb-0 alert-' + type;
            box.textContent = message;
        }

        function addToCart(name, price, emoji, desc) {
            if (!stall) return;

            const existing = cart.find(item => item.name === name);
            if (existing) {
                existing.qty += 1;
            } else {
                cart.push({
                    name,
                    price: Number(price) || 0,
                    qty: 1,
                    emoji,
                    desc,
                    remarks: '',
                    addons: [],
                    prepStyle: 'Customer choice',
                });
            }

            renderCart();
            setStatus(name + ' added to basket.', 'success');
        }

        function changeQty(index, delta) {
            const item = cart[index];
            if (!item) return;

            item.qty += delta;
            if (item.qty <= 0) {
                cart.splice(index, 1);
            }

            renderCart();
        }

        function removeItem(index) {
            cart.splice(index, 1);
            renderCart();
        }

        function renderCart() {
            const container = document.getElementById('cartItems');
            const totalElement = document.getElementById('cartTotal');
            if (!container || !totalElement) return;

            if (cart.length === 0) {
                container.innerHTML = '<div class="text-center text-secondary py-4">Your basket is empty.</div>';
                totalElement.textContent = money(0);
                return;
            }

            let total = 0;
            container.innerHTML = '';

            cart.forEach((item, index) => {
                total += item.price * item.qty;
                container.insertAdjacentHTML('beforeend', `
                    <div class="cart-item p-3">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                            <div>
                                <div class="fw-semibold">${item.emoji || '🍔'} ${escapeHtml(item.name)}</div>
                                <div class="text-secondary small">${money(item.price)} each</div>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" type="button" onclick="removeItem(${index})">Remove</button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <div class="btn-group btn-group-sm" role="group" aria-label="Quantity controls">
                                <button class="btn btn-outline-dark" type="button" onclick="changeQty(${index}, -1)">-</button>
                                <button class="btn btn-dark" type="button" disabled>${item.qty}</button>
                                <button class="btn btn-outline-dark" type="button" onclick="changeQty(${index}, 1)">+</button>
                            </div>
                            <strong class="text-success">${money(item.price * item.qty)}</strong>
                        </div>
                    </div>
                `);
            });

            totalElement.textContent = money(total);
        }

        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#39;');
        }

        async function submitOrder() {
            if (!stall) return;

            if (cart.length === 0) {
                setStatus('Add at least one menu item before checkout.', 'warning');
                return;
            }

            const customerNameInput = document.getElementById('customerName');
            const customerName = customerNameInput ? customerNameInput.value.trim() : '';

            try {
                setStatus('Sending order to the kitchen...', 'info');

                const response = await fetch('orders_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        stallId: stall.id,
                        stallName: stall.name,
                        customerName: customerName || 'Guest',
                        items: cart.map(item => ({
                            name: item.name,
                            price: item.price,
                            qty: item.qty,
                            addons: [],
                            prepStyle: item.prepStyle,
                            remarks: item.remarks,
                        })),
                    }),
                });

                const result = await response.json();
                if (!response.ok || !result.ok) {
                    throw new Error(result.message || 'Order submission failed.');
                }

                cart.length = 0;
                renderCart();
                setStatus(`Order ${result.orderCode} placed successfully. Total ${money(result.totalAmount)}.`, 'success');
            } catch (error) {
                setStatus(error.message || 'Failed to place order.', 'danger');
            }
        }

        renderCart();
    </script>
</body>
</html>
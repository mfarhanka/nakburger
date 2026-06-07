<?php
session_start();

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'db.php';

function parseMenuItemsFromPost(string $prefix, string $defaultEmoji): array
{
    $names = $_POST[$prefix . '_name'] ?? [];
    $prices = $_POST[$prefix . '_price'] ?? [];
    $descs = $_POST[$prefix . '_desc'] ?? [];
    $emojis = $_POST[$prefix . '_emoji'] ?? [];

    if (!is_array($names) || !is_array($prices) || !is_array($descs) || !is_array($emojis)) {
        throw new RuntimeException('Invalid menu input format.');
    }

    $maxRows = max(count($names), count($prices), count($descs), count($emojis));
    $items = [];

    for ($i = 0; $i < $maxRows; $i++) {
        $name = trim((string)($names[$i] ?? ''));
        $desc = trim((string)($descs[$i] ?? ''));
        $emoji = trim((string)($emojis[$i] ?? $defaultEmoji));
        $priceRaw = trim((string)($prices[$i] ?? '0'));

        if ($name === '' && $desc === '' && $priceRaw === '' && $emoji === '') {
            continue;
        }

        if ($name === '') {
            throw new RuntimeException('Menu item name is required when a row is filled.');
        }

        if ($priceRaw !== '' && !is_numeric($priceRaw)) {
            throw new RuntimeException('Menu item price must be numeric.');
        }

        $items[] = [
            'name' => $name,
            'price' => (float)($priceRaw === '' ? 0 : $priceRaw),
            'desc' => $desc,
            'emoji' => $emoji === '' ? $defaultEmoji : $emoji,
        ];
    }

    return $items;
}

try {
    $pdo = getDbConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Database connection failed: ' . htmlspecialchars($e->getMessage());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'logout') {
        unset($_SESSION['owner_stall_id']);
        $_SESSION['owner_flash'] = ['type' => 'success', 'message' => 'Logged out from owner portal.'];
        header('Location: owner.php');
        exit;
    }

    if ($action === 'login') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        try {
            if ($username === '' || $password === '') {
                throw new RuntimeException('Username and password are required.');
            }

            $stall = authenticateStallOwner($pdo, $username, $password);
            if (!$stall) {
                throw new RuntimeException('Invalid owner login.');
            }

            $_SESSION['owner_stall_id'] = (int)$stall['id'];
            $_SESSION['owner_flash'] = ['type' => 'success', 'message' => 'Welcome back, ' . $stall['owner']['name'] . '.'];
        } catch (Throwable $e) {
            $_SESSION['owner_flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        }

        header('Location: owner.php');
        exit;
    }

    if ($action === 'save') {
        $stallId = (int)($_SESSION['owner_stall_id'] ?? 0);

        try {
            if ($stallId <= 0) {
                throw new RuntimeException('Owner session expired. Please log in again.');
            }

            $stall = fetchStallById($pdo, $stallId);
            if (!$stall) {
                throw new RuntimeException('Assigned stall could not be found.');
            }

            $name = trim((string)($_POST['name'] ?? ''));
            $type = trim((string)($_POST['type'] ?? ''));
            $specialty = trim((string)($_POST['specialty'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $signature = parseMenuItemsFromPost('signature', '🍔');
            $sides = parseMenuItemsFromPost('sides', '🥤');

            if ($name === '' || $type === '' || $address === '') {
                throw new RuntimeException('Name, type, and address are required.');
            }

            $menuSignatureJson = json_encode($signature, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $menuSidesJson = json_encode($sides, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($menuSignatureJson === false || $menuSidesJson === false) {
                throw new RuntimeException('Failed to encode menu payload.');
            }

            $updateStmt = $pdo->prepare(
                'UPDATE stalls '
                . 'SET name = :name, type = :type, specialty = :specialty, address = :address, '
                . 'menu_signature = :menu_signature, menu_sides = :menu_sides '
                . 'WHERE id = :id'
            );

            $updateStmt->execute([
                ':id' => $stallId,
                ':name' => $name,
                ':type' => $type,
                ':specialty' => $specialty,
                ':address' => $address,
                ':menu_signature' => $menuSignatureJson,
                ':menu_sides' => $menuSidesJson,
            ]);

            $_SESSION['owner_flash'] = ['type' => 'success', 'message' => 'Stall menu updated.'];
        } catch (Throwable $e) {
            $_SESSION['owner_flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        }

        header('Location: owner.php');
        exit;
    }
}

$flash = $_SESSION['owner_flash'] ?? null;
unset($_SESSION['owner_flash']);

$stallId = (int)($_SESSION['owner_stall_id'] ?? 0);
$ownerStall = $stallId > 0 ? fetchStallById($pdo, $stallId) : null;

if ($stallId > 0 && !$ownerStall) {
    unset($_SESSION['owner_stall_id']);
    $flash = ['type' => 'danger', 'message' => 'Assigned stall no longer exists.'];
}

$signatureRows = $ownerStall['menu']['signature'] ?? [];
$sidesRows = $ownerStall['menu']['sides'] ?? [];

if (!$signatureRows) {
    $signatureRows = [['name' => '', 'price' => '', 'desc' => '', 'emoji' => '🍔']];
}

if (!$sidesRows) {
    $sidesRows = [['name' => '', 'price' => '', 'desc' => '', 'emoji' => '🥤']];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NakBurger Owner Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f8fff7, #fff7ed 55%, #eff6ff);
            min-height: 100vh;
        }

        .brand {
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .glass {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(6px);
            border-radius: 20px;
            border: 1px solid rgba(15, 23, 42, 0.08);
        }

        .menu-row {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <div class="container py-4 py-lg-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <h1 class="brand mb-1">NakBurger Owner Portal</h1>
                <p class="text-secondary mb-0">Owners can update their stall profile and manage menu items.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-dark" href="index.php"><i class="bi bi-arrow-left"></i> Back to App</a>
                <a class="btn btn-outline-info" href="kitchen.php"><i class="bi bi-egg-fried"></i> Kitchen</a>
                <a class="btn btn-outline-secondary" href="admin.php"><i class="bi bi-shield-lock"></i> Admin</a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars((string)$flash['type']) ?> shadow-sm" role="alert">
                <?= htmlspecialchars((string)$flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if (!$ownerStall): ?>
            <div class="row justify-content-center">
                <div class="col-lg-5">
                    <div class="glass p-4 shadow-sm">
                        <h5 class="fw-bold mb-3">Owner Login</h5>
                        <form method="post">
                            <input type="hidden" name="action" value="login">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input class="form-control" name="username" required placeholder="owner.login">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input class="form-control" type="password" name="password" required placeholder="Enter password">
                            </div>
                            <button class="btn btn-dark w-100" type="submit">Login to Manage Stall</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="glass p-4 shadow-sm h-100">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <div class="text-secondary small text-uppercase">Signed in as</div>
                                <h4 class="fw-bold mb-1"><?= htmlspecialchars((string)$ownerStall['owner']['name']) ?></h4>
                                <div class="text-secondary">@<?= htmlspecialchars((string)$ownerStall['owner']['username']) ?></div>
                            </div>
                            <form method="post">
                                <input type="hidden" name="action" value="logout">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Logout</button>
                            </form>
                        </div>
                        <div class="border rounded-3 p-3 bg-light-subtle">
                            <div class="small text-secondary text-uppercase mb-1">Your stall</div>
                            <div class="fw-semibold fs-5"><?= htmlspecialchars((string)$ownerStall['name']) ?></div>
                            <div class="text-secondary mb-2"><?= htmlspecialchars((string)$ownerStall['address']) ?></div>
                            <span class="badge text-bg-warning text-dark"><?= htmlspecialchars((string)$ownerStall['type']) ?></span>
                        </div>
                        <div class="mt-3 small text-secondary">
                            You can update the menu, specialty, stall name, type, and address here. Ratings, review counts, and stall deletion stay in admin control.
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="glass p-4 shadow-sm">
                        <h5 class="fw-bold mb-3">Manage Stall Menu</h5>
                        <form method="post">
                            <input type="hidden" name="action" value="save">

                            <div class="row g-2">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Stall Name</label>
                                    <input class="form-control" name="name" required value="<?= htmlspecialchars((string)$ownerStall['name']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Type</label>
                                    <select class="form-select" name="type" required>
                                        <?php $types = ['Ramly Style', 'Smashed Beef', 'Charcoal Grill']; ?>
                                        <?php foreach ($types as $type): ?>
                                            <option value="<?= htmlspecialchars($type) ?>" <?= $ownerStall['type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Specialty</label>
                                <input class="form-control" name="specialty" value="<?= htmlspecialchars((string)$ownerStall['specialty']) ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <input class="form-control" name="address" required value="<?= htmlspecialchars((string)$ownerStall['address']) ?>">
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0">Signature Menu Items</label>
                                    <button type="button" class="btn btn-sm btn-outline-dark add-menu-row" data-prefix="signature" data-emoji="🍔">+ Add Item</button>
                                </div>
                                <div id="signatureRows" class="d-flex flex-column gap-2">
                                    <?php foreach ($signatureRows as $item): ?>
                                        <div class="menu-row p-2">
                                            <div class="row g-2 align-items-end">
                                                <div class="col-4">
                                                    <label class="form-label small">Name</label>
                                                    <input class="form-control form-control-sm" name="signature_name[]" value="<?= htmlspecialchars((string)($item['name'] ?? '')) ?>" placeholder="Menu name">
                                                </div>
                                                <div class="col-2">
                                                    <label class="form-label small">Price</label>
                                                    <input class="form-control form-control-sm" type="number" min="0" step="0.1" name="signature_price[]" value="<?= htmlspecialchars((string)($item['price'] ?? '')) ?>" placeholder="0.0">
                                                </div>
                                                <div class="col-2">
                                                    <label class="form-label small">Emoji</label>
                                                    <input class="form-control form-control-sm" name="signature_emoji[]" value="<?= htmlspecialchars((string)($item['emoji'] ?? '🍔')) ?>" placeholder="🍔">
                                                </div>
                                                <div class="col-3">
                                                    <label class="form-label small">Description</label>
                                                    <input class="form-control form-control-sm" name="signature_desc[]" value="<?= htmlspecialchars((string)($item['desc'] ?? '')) ?>" placeholder="Short description">
                                                </div>
                                                <div class="col-1 d-grid">
                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-menu-row">x</button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0">Sides/Drinks Items</label>
                                    <button type="button" class="btn btn-sm btn-outline-dark add-menu-row" data-prefix="sides" data-emoji="🥤">+ Add Item</button>
                                </div>
                                <div id="sidesRows" class="d-flex flex-column gap-2">
                                    <?php foreach ($sidesRows as $item): ?>
                                        <div class="menu-row p-2">
                                            <div class="row g-2 align-items-end">
                                                <div class="col-4">
                                                    <label class="form-label small">Name</label>
                                                    <input class="form-control form-control-sm" name="sides_name[]" value="<?= htmlspecialchars((string)($item['name'] ?? '')) ?>" placeholder="Menu name">
                                                </div>
                                                <div class="col-2">
                                                    <label class="form-label small">Price</label>
                                                    <input class="form-control form-control-sm" type="number" min="0" step="0.1" name="sides_price[]" value="<?= htmlspecialchars((string)($item['price'] ?? '')) ?>" placeholder="0.0">
                                                </div>
                                                <div class="col-2">
                                                    <label class="form-label small">Emoji</label>
                                                    <input class="form-control form-control-sm" name="sides_emoji[]" value="<?= htmlspecialchars((string)($item['emoji'] ?? '🥤')) ?>" placeholder="🥤">
                                                </div>
                                                <div class="col-3">
                                                    <label class="form-label small">Description</label>
                                                    <input class="form-control form-control-sm" name="sides_desc[]" value="<?= htmlspecialchars((string)($item['desc'] ?? '')) ?>" placeholder="Short description">
                                                </div>
                                                <div class="col-1 d-grid">
                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-menu-row">x</button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <button class="btn btn-dark w-100" type="submit">Save Menu Changes</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function createMenuRow(prefix, defaultEmoji) {
            return `
                <div class="menu-row p-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-4">
                            <label class="form-label small">Name</label>
                            <input class="form-control form-control-sm" name="${prefix}_name[]" placeholder="Menu name">
                        </div>
                        <div class="col-2">
                            <label class="form-label small">Price</label>
                            <input class="form-control form-control-sm" type="number" min="0" step="0.1" name="${prefix}_price[]" placeholder="0.0">
                        </div>
                        <div class="col-2">
                            <label class="form-label small">Emoji</label>
                            <input class="form-control form-control-sm" name="${prefix}_emoji[]" value="${defaultEmoji}" placeholder="${defaultEmoji}">
                        </div>
                        <div class="col-3">
                            <label class="form-label small">Description</label>
                            <input class="form-control form-control-sm" name="${prefix}_desc[]" placeholder="Short description">
                        </div>
                        <div class="col-1 d-grid">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-menu-row">x</button>
                        </div>
                    </div>
                </div>
            `;
        }

        document.addEventListener('click', function(event) {
            const addBtn = event.target.closest('.add-menu-row');
            if (addBtn) {
                const prefix = addBtn.dataset.prefix;
                const emoji = addBtn.dataset.emoji || '';
                const target = document.getElementById(prefix + 'Rows');
                if (target) {
                    target.insertAdjacentHTML('beforeend', createMenuRow(prefix, emoji));
                }
                return;
            }

            const removeBtn = event.target.closest('.remove-menu-row');
            if (!removeBtn) {
                return;
            }

            const row = removeBtn.closest('.menu-row');
            const container = row ? row.parentElement : null;
            if (!row || !container) {
                return;
            }

            if (container.children.length > 1) {
                row.remove();
                return;
            }

            row.querySelectorAll('input').forEach(function(input) {
                input.value = '';
            });
        });
    </script>
</body>
</html>
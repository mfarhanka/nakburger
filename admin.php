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

$stalls = fetchAllStalls($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $deleteId = (int)($_POST['id'] ?? 0);

        try {
            $deleteStmt = $pdo->prepare('DELETE FROM stalls WHERE id = :id');
            $deleteStmt->execute([':id' => $deleteId]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Stall deleted.'];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to delete stall.'];
        }

        header('Location: admin.php');
        exit;
    }

    if ($action === 'save') {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $type = trim((string)($_POST['type'] ?? ''));
            $rating = (float)($_POST['rating'] ?? 0);
            $reviews = (int)($_POST['reviews'] ?? 0);
            $specialty = trim((string)($_POST['specialty'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $offsetLat = (float)($_POST['offsetLat'] ?? 0);
            $offsetLng = (float)($_POST['offsetLng'] ?? 0);
            $ownerName = trim((string)($_POST['owner_name'] ?? ''));
            $ownerUsername = trim((string)($_POST['owner_username'] ?? ''));
            $ownerPassword = (string)($_POST['owner_password'] ?? '');
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

            $pdo->beginTransaction();

            if ($id > 0) {
                $updateStmt = $pdo->prepare(
                    'UPDATE stalls '
                    . 'SET name = :name, type = :type, rating = :rating, reviews = :reviews, specialty = :specialty, '
                    . 'address = :address, offset_lat = :offset_lat, offset_lng = :offset_lng, menu_signature = :menu_signature, menu_sides = :menu_sides '
                    . 'WHERE id = :id'
                );

                $updateStmt->execute([
                    ':id' => $id,
                    ':name' => $name,
                    ':type' => $type,
                    ':rating' => $rating,
                    ':reviews' => $reviews,
                    ':specialty' => $specialty,
                    ':address' => $address,
                    ':offset_lat' => $offsetLat,
                    ':offset_lng' => $offsetLng,
                    ':menu_signature' => $menuSignatureJson,
                    ':menu_sides' => $menuSidesJson,
                ]);
                saveStallOwner($pdo, $id, $ownerName, $ownerUsername, $ownerPassword);
                $updated = true;
            } else {
                $insertStmt = $pdo->prepare(
                    'INSERT INTO stalls (name, type, rating, reviews, specialty, address, offset_lat, offset_lng, menu_signature, menu_sides) '
                    . 'VALUES (:name, :type, :rating, :reviews, :specialty, :address, :offset_lat, :offset_lng, :menu_signature, :menu_sides)'
                );

                $insertStmt->execute([
                    ':name' => $name,
                    ':type' => $type,
                    ':rating' => $rating,
                    ':reviews' => $reviews,
                    ':specialty' => $specialty,
                    ':address' => $address,
                    ':offset_lat' => $offsetLat,
                    ':offset_lng' => $offsetLng,
                    ':menu_signature' => $menuSignatureJson,
                    ':menu_sides' => $menuSidesJson,
                ]);
                $id = (int)$pdo->lastInsertId();
                saveStallOwner($pdo, $id, $ownerName, $ownerUsername, $ownerPassword);
                $updated = false;
            }

            $pdo->commit();

            $_SESSION['flash'] = ['type' => 'success', 'message' => $updated ? 'Stall updated.' : 'Stall created.'];
            header('Location: admin.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
            header('Location: admin.php');
            exit;
        }
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$editId = (int)($_GET['edit'] ?? 0);
$editingStall = null;
foreach ($stalls as $stall) {
    if ((int)$stall['id'] === $editId) {
        $editingStall = $stall;
        break;
    }
}

$defaultStall = [
    'id' => 0,
    'name' => '',
    'type' => 'Ramly Style',
    'rating' => 4.5,
    'reviews' => 0,
    'specialty' => '',
    'address' => '',
    'offsetLat' => 0,
    'offsetLng' => 0,
    'menu' => ['signature' => [], 'sides' => []],
    'owner' => ['id' => 0, 'name' => '', 'username' => ''],
];

$formStall = $editingStall ?: $defaultStall;
$signatureRows = $formStall['menu']['signature'] ?? [];
$sidesRows = $formStall['menu']['sides'] ?? [];

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
    <title>NakBurger Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(120deg, #fff4df, #f6fff3 55%, #eff9ff);
            min-height: 100vh;
        }

        .brand {
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .glass {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(5px);
            border-radius: 16px;
            border: 1px solid rgba(0, 0, 0, 0.06);
        }

        .menu-row {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container py-4 py-lg-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <h1 class="brand mb-1">NakBurger Admin</h1>
                <p class="text-secondary mb-0">Manage stall profile, map position offsets, and menus.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-dark" href="index.php"><i class="bi bi-arrow-left"></i> Back to App</a>
                <a class="btn btn-dark" href="admin.php">New / Reset Form</a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars((string)$flash['type']) ?> shadow-sm" role="alert">
                <?= htmlspecialchars((string)$flash['message']) ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="glass p-3 p-lg-4 shadow-sm">
                    <h5 class="fw-bold mb-3">Stall List (<?= count($stalls) ?>)</h5>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Owner</th>
                                    <th>Type</th>
                                    <th>Rating</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$stalls): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">No stalls available yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($stalls as $stall): ?>
                                    <tr>
                                        <td><?= (int)$stall['id'] ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string)$stall['name']) ?></div>
                                            <div class="small text-secondary"><?= htmlspecialchars((string)$stall['address']) ?></div>
                                        </td>
                                        <td>
                                            <?php if (!empty($stall['owner']['name'])): ?>
                                                <div class="fw-semibold"><?= htmlspecialchars((string)$stall['owner']['name']) ?></div>
                                                <div class="small text-secondary">@<?= htmlspecialchars((string)$stall['owner']['username']) ?></div>
                                            <?php else: ?>
                                                <span class="text-muted small">No owner</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars((string)$stall['type']) ?></td>
                                        <td><?= number_format((float)$stall['rating'], 1) ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a class="btn btn-sm btn-warning" href="admin.php?edit=<?= (int)$stall['id'] ?>">Edit</a>
                                                <?php if (!empty($stall['owner']['username'])): ?>
                                                    <a class="btn btn-sm btn-outline-primary" href="owner.php">Owner Login</a>
                                                <?php endif; ?>
                                                <form method="post" onsubmit="return confirm('Delete this stall?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$stall['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="glass p-3 p-lg-4 shadow-sm">
                    <h5 class="fw-bold mb-3"><?= $editingStall ? 'Edit Stall #' . (int)$formStall['id'] : 'Create New Stall' ?></h5>
                    <form method="post">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?= (int)$formStall['id'] ?>">

                        <div class="mb-3">
                            <label class="form-label">Stall Name</label>
                            <input class="form-control" name="name" required value="<?= htmlspecialchars((string)$formStall['name']) ?>">
                        </div>

                        <div class="row g-2">
                            <div class="col-6 mb-3">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="type" required>
                                    <?php $types = ['Ramly Style', 'Smashed Beef', 'Charcoal Grill']; ?>
                                    <?php foreach ($types as $type): ?>
                                        <option value="<?= htmlspecialchars($type) ?>" <?= $formStall['type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-3 mb-3">
                                <label class="form-label">Rating</label>
                                <input class="form-control" type="number" min="1" max="5" step="0.1" name="rating" value="<?= htmlspecialchars((string)$formStall['rating']) ?>">
                            </div>
                            <div class="col-3 mb-3">
                                <label class="form-label">Reviews</label>
                                <input class="form-control" type="number" min="0" step="1" name="reviews" value="<?= htmlspecialchars((string)$formStall['reviews']) ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Specialty</label>
                            <input class="form-control" name="specialty" value="<?= htmlspecialchars((string)$formStall['specialty']) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input class="form-control" name="address" required value="<?= htmlspecialchars((string)$formStall['address']) ?>">
                        </div>

                        <div class="border rounded-3 p-3 mb-3 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <label class="form-label mb-0 fw-semibold">Stall Owner Access</label>
                                <a class="btn btn-sm btn-outline-primary" href="owner.php">Open Owner Portal</a>
                            </div>
                            <p class="small text-secondary mb-3">Assign one owner account to this stall. Leave all fields blank to remove owner access. Leave password blank while editing to keep the existing password.</p>
                            <div class="mb-3">
                                <label class="form-label">Owner Name</label>
                                <input class="form-control" name="owner_name" value="<?= htmlspecialchars((string)($formStall['owner']['name'] ?? '')) ?>" placeholder="Owner full name">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Owner Username</label>
                                <input class="form-control" name="owner_username" value="<?= htmlspecialchars((string)($formStall['owner']['username'] ?? '')) ?>" placeholder="owner.login">
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Owner Password</label>
                                <input class="form-control" type="password" name="owner_password" placeholder="Set or change password">
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-6 mb-3">
                                <label class="form-label">Offset Latitude</label>
                                <input class="form-control" type="number" step="0.0001" name="offsetLat" value="<?= htmlspecialchars((string)$formStall['offsetLat']) ?>">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Offset Longitude</label>
                                <input class="form-control" type="number" step="0.0001" name="offsetLng" value="<?= htmlspecialchars((string)$formStall['offsetLng']) ?>">
                            </div>
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

                        <button class="btn btn-dark w-100" type="submit">Save Stall</button>
                    </form>
                </div>
            </div>
        </div>
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
                if (input.name.endsWith('_emoji[]')) {
                    input.value = '';
                } else {
                    input.value = '';
                }
            });
        });
    </script>
</body>
</html>

<?php
session_start();

$dataFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'stalls.json';

function loadStalls(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $content = file_get_contents($path);
    if ($content === false || trim($content) === '') {
        return [];
    }

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

function saveStalls(string $path, array $stalls): bool
{
    $json = json_encode($stalls, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents($path, $json . PHP_EOL, LOCK_EX) !== false;
}

function nextId(array $stalls): int
{
    if (!$stalls) {
        return 1;
    }

    $ids = array_map(static fn($stall) => (int)($stall['id'] ?? 0), $stalls);
    return max($ids) + 1;
}

function parseMenuItems(string $jsonText): array
{
    $decoded = json_decode($jsonText, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Menu JSON must be a valid JSON array.');
    }

    $items = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            throw new RuntimeException('Every menu item must be an object.');
        }

        $items[] = [
            'name' => trim((string)($item['name'] ?? '')),
            'price' => (float)($item['price'] ?? 0),
            'desc' => trim((string)($item['desc'] ?? '')),
            'emoji' => trim((string)($item['emoji'] ?? '🍔')),
        ];
    }

    return $items;
}

$stalls = loadStalls($dataFile);
usort($stalls, static fn($a, $b) => ((int)$a['id']) <=> ((int)$b['id']));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $deleteId = (int)($_POST['id'] ?? 0);
        $stalls = array_values(array_filter($stalls, static fn($stall) => (int)$stall['id'] !== $deleteId));

        if (saveStalls($dataFile, $stalls)) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Stall deleted.'];
        } else {
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
            $signature = parseMenuItems((string)($_POST['signatureMenu'] ?? '[]'));
            $sides = parseMenuItems((string)($_POST['sidesMenu'] ?? '[]'));

            if ($name === '' || $type === '' || $address === '') {
                throw new RuntimeException('Name, type, and address are required.');
            }

            $payload = [
                'id' => $id > 0 ? $id : nextId($stalls),
                'name' => $name,
                'type' => $type,
                'rating' => $rating,
                'reviews' => $reviews,
                'specialty' => $specialty,
                'address' => $address,
                'offsetLat' => $offsetLat,
                'offsetLng' => $offsetLng,
                'menu' => [
                    'signature' => $signature,
                    'sides' => $sides,
                ],
            ];

            $updated = false;
            foreach ($stalls as $idx => $stall) {
                if ((int)$stall['id'] === (int)$payload['id']) {
                    $stalls[$idx] = $payload;
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                $stalls[] = $payload;
            }

            usort($stalls, static fn($a, $b) => ((int)$a['id']) <=> ((int)$b['id']));

            if (!saveStalls($dataFile, $stalls)) {
                throw new RuntimeException('Failed to save data to JSON file.');
            }

            $_SESSION['flash'] = ['type' => 'success', 'message' => $updated ? 'Stall updated.' : 'Stall created.'];
            header('Location: admin.php');
            exit;
        } catch (Throwable $e) {
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
];

$formStall = $editingStall ?: $defaultStall;
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

        textarea.code {
            font-family: Consolas, Monaco, monospace;
            min-height: 170px;
            font-size: 13px;
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
                                    <th>Type</th>
                                    <th>Rating</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$stalls): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">No stalls available yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($stalls as $stall): ?>
                                    <tr>
                                        <td><?= (int)$stall['id'] ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string)$stall['name']) ?></div>
                                            <div class="small text-secondary"><?= htmlspecialchars((string)$stall['address']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars((string)$stall['type']) ?></td>
                                        <td><?= number_format((float)$stall['rating'], 1) ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a class="btn btn-sm btn-warning" href="admin.php?edit=<?= (int)$stall['id'] ?>">Edit</a>
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
                            <label class="form-label">Signature Menu JSON</label>
                            <textarea class="form-control code" name="signatureMenu"><?= htmlspecialchars(json_encode($formStall['menu']['signature'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Sides/Drinks Menu JSON</label>
                            <textarea class="form-control code" name="sidesMenu"><?= htmlspecialchars(json_encode($formStall['menu']['sides'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></textarea>
                        </div>

                        <button class="btn btn-dark w-100" type="submit">Save Stall</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

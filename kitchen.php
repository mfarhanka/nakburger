<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'db.php';

try {
    $pdo = getDbConnection();
    $orders = fetchKitchenOrders($pdo, 150);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Failed to load kitchen orders: ' . htmlspecialchars($e->getMessage());
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NakBurger Kitchen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <meta http-equiv="refresh" content="15">
    <style>
        body {
            background: linear-gradient(135deg, #fff7ed, #fffbeb 50%, #ecfeff);
            min-height: 100vh;
        }

        .brand {
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .glass {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(6px);
            border-radius: 16px;
            border: 1px solid rgba(2, 6, 23, 0.08);
        }
    </style>
</head>
<body>
    <div class="container py-4 py-lg-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <h1 class="brand mb-1"><i class="bi bi-egg-fried text-warning"></i> NakBurger Kitchen</h1>
                <p class="text-secondary mb-0">Live order board for kitchen team. Auto-refresh every 15 seconds.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-dark" href="index.php"><i class="bi bi-arrow-left"></i> Back to App</a>
                <a class="btn btn-outline-secondary" href="admin.php"><i class="bi bi-sliders"></i> Admin</a>
            </div>
        </div>

        <div class="glass p-3 p-lg-4 shadow-sm mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Incoming Orders (<?= count($orders) ?>)</h5>
                <span class="badge text-bg-dark px-3 py-2">Kitchen View</span>
            </div>
        </div>

        <?php if (!$orders): ?>
            <div class="glass p-5 text-center shadow-sm">
                <i class="bi bi-inboxes fs-1 text-secondary"></i>
                <h5 class="fw-bold mt-3">No orders yet</h5>
                <p class="text-secondary mb-0">New customer checkouts will appear here automatically.</p>
            </div>
        <?php else: ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($orders as $order): ?>
                    <div class="glass p-3 p-lg-4 shadow-sm">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
                            <div>
                                <div class="small text-secondary">Order Code</div>
                                <div class="fw-bold fs-5"><?= htmlspecialchars((string)$order['orderCode']) ?></div>
                            </div>
                            <div class="text-md-end">
                                <div class="badge text-bg-warning text-dark mb-1"><?= htmlspecialchars((string)$order['status']) ?></div>
                                <div class="small text-secondary"><?= htmlspecialchars((string)$order['createdAt']) ?></div>
                            </div>
                        </div>

                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <div class="border rounded-3 p-2 bg-light-subtle h-100">
                                    <div class="small text-secondary text-uppercase">Stall</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string)$order['stallName']) ?> (#<?= (int)$order['stallId'] ?>)</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded-3 p-2 bg-light-subtle h-100">
                                    <div class="small text-secondary text-uppercase">Customer</div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string)$order['customerName']) ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded-3 p-2 bg-light-subtle h-100">
                                    <div class="small text-secondary text-uppercase">Total</div>
                                    <div class="fw-bold text-success">RM <?= number_format((float)$order['totalAmount'], 2) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Prep</th>
                                        <th>Add-ons</th>
                                        <th>Remarks</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order['items'] as $item): ?>
                                        <?php
                                            $addons = $item['addons'] ?? [];
                                            $addonsLabel = is_array($addons) && $addons ? implode(', ', $addons) : 'None';
                                        ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars((string)($item['name'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string)($item['prepStyle'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string)$addonsLabel) ?></td>
                                            <td><?= htmlspecialchars((string)($item['remarks'] ?? '-')) ?></td>
                                            <td class="text-end"><?= (int)($item['qty'] ?? 1) ?></td>
                                            <td class="text-end text-success fw-semibold">RM <?= number_format((float)($item['lineTotal'] ?? 0), 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true);

    if (!is_array($payload)) {
        throw new RuntimeException('Invalid request payload.');
    }

    $stallId = (int)($payload['stallId'] ?? 0);
    $stallName = (string)($payload['stallName'] ?? '');
    $items = $payload['items'] ?? [];
    $customerName = (string)($payload['customerName'] ?? 'Guest');

    if (!is_array($items)) {
        throw new RuntimeException('Invalid items payload.');
    }

    $pdo = getDbConnection();
    $created = createOrder($pdo, $stallId, $stallName, $items, $customerName);

    echo json_encode([
        'ok' => true,
        'orderId' => $created['id'],
        'orderCode' => $created['orderCode'],
        'totalAmount' => $created['totalAmount'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

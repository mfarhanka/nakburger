<?php

declare(strict_types=1);

function dbConfig(): array
{
    return [
        'host' => getenv('NAKBURGER_DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('NAKBURGER_DB_PORT') ?: 3306),
        'database' => getenv('NAKBURGER_DB_NAME') ?: 'dzvisual_nakburger',
        'username' => getenv('NAKBURGER_DB_USER') ?: 'root',
        'password' => getenv('NAKBURGER_DB_PASS') ?: '',
    ];
}

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = dbConfig();

    $bootstrapDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $cfg['host'], $cfg['port']);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $bootstrapPdo = new PDO($bootstrapDsn, $cfg['username'], $cfg['password'], $options);
    $bootstrapPdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $cfg['database']));

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['database']);
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $options);

    ensureStallSchema($pdo);
    ensureOwnerSchema($pdo);
    ensureOrderSchema($pdo);

    return $pdo;
}

function ensureStallSchema(PDO $pdo): void
{
    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS stalls (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(191) NOT NULL,
    type VARCHAR(100) NOT NULL,
    rating DECIMAL(3,1) NOT NULL DEFAULT 4.5,
    reviews INT UNSIGNED NOT NULL DEFAULT 0,
    specialty VARCHAR(255) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL,
    offset_lat DECIMAL(10,7) NOT NULL DEFAULT 0,
    offset_lng DECIMAL(10,7) NOT NULL DEFAULT 0,
    menu_signature JSON NOT NULL,
    menu_sides JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    $pdo->exec($sql);
}

function ensureOwnerSchema(PDO $pdo): void
{
    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS stall_owners (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    stall_id INT UNSIGNED NOT NULL,
    owner_name VARCHAR(191) NOT NULL,
    username VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_stall_owner_stall (stall_id),
    UNIQUE KEY uniq_stall_owner_username (username),
    CONSTRAINT fk_stall_owners_stall FOREIGN KEY (stall_id) REFERENCES stalls (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    $pdo->exec($sql);
}

function ensureOrderSchema(PDO $pdo): void
{
    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_code VARCHAR(40) NOT NULL,
    stall_id INT UNSIGNED NOT NULL,
    stall_name VARCHAR(191) NOT NULL,
    customer_name VARCHAR(191) NOT NULL DEFAULT 'Guest',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'received',
    order_items JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_orders_order_code (order_code),
    KEY idx_orders_stall_created (stall_id, created_at),
    CONSTRAINT fk_orders_stall FOREIGN KEY (stall_id) REFERENCES stalls (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    $pdo->exec($sql);
}

function rowToStallPayload(array $row): array
{
    $signature = json_decode((string)($row['menu_signature'] ?? '[]'), true);
    $sides = json_decode((string)($row['menu_sides'] ?? '[]'), true);
    $ownerId = isset($row['owner_id']) ? (int)$row['owner_id'] : 0;
    $ownerName = trim((string)($row['owner_name'] ?? ''));
    $ownerUsername = trim((string)($row['owner_username'] ?? ''));

    return [
        'id' => (int)$row['id'],
        'name' => (string)$row['name'],
        'type' => (string)$row['type'],
        'rating' => (float)$row['rating'],
        'reviews' => (int)$row['reviews'],
        'specialty' => (string)$row['specialty'],
        'address' => (string)$row['address'],
        'offsetLat' => (float)$row['offset_lat'],
        'offsetLng' => (float)$row['offset_lng'],
        'menu' => [
            'signature' => is_array($signature) ? $signature : [],
            'sides' => is_array($sides) ? $sides : [],
        ],
        'owner' => [
            'id' => $ownerId,
            'name' => $ownerName,
            'username' => $ownerUsername,
        ],
    ];
}

function fetchAllStalls(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT s.*, o.id AS owner_id, o.owner_name, o.username AS owner_username '
        . 'FROM stalls s '
        . 'LEFT JOIN stall_owners o ON o.stall_id = s.id '
        . 'ORDER BY s.id ASC'
    );
    $rows = $stmt->fetchAll();

    return array_map(static fn(array $row): array => rowToStallPayload($row), $rows);
}

function fetchStallById(PDO $pdo, int $stallId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT s.*, o.id AS owner_id, o.owner_name, o.username AS owner_username '
        . 'FROM stalls s '
        . 'LEFT JOIN stall_owners o ON o.stall_id = s.id '
        . 'WHERE s.id = :stall_id '
        . 'LIMIT 1'
    );
    $stmt->execute([':stall_id' => $stallId]);
    $row = $stmt->fetch();

    return is_array($row) ? rowToStallPayload($row) : null;
}

function saveStallOwner(PDO $pdo, int $stallId, string $ownerName, string $username, string $password): void
{
    $ownerName = trim($ownerName);
    $username = trim($username);
    $password = trim($password);

    $existingStmt = $pdo->prepare('SELECT id, password_hash FROM stall_owners WHERE stall_id = :stall_id LIMIT 1');
    $existingStmt->execute([':stall_id' => $stallId]);
    $existingOwner = $existingStmt->fetch();

    if ($ownerName === '' && $username === '' && $password === '') {
        $deleteStmt = $pdo->prepare('DELETE FROM stall_owners WHERE stall_id = :stall_id');
        $deleteStmt->execute([':stall_id' => $stallId]);
        return;
    }

    if ($ownerName === '' || $username === '') {
        throw new RuntimeException('Owner name and username are required when owner access is enabled.');
    }

    if (!$existingOwner && $password === '') {
        throw new RuntimeException('Owner password is required for a new owner account.');
    }

    $duplicateStmt = $pdo->prepare(
        'SELECT id FROM stall_owners WHERE username = :username AND stall_id <> :stall_id LIMIT 1'
    );
    $duplicateStmt->execute([':username' => $username, ':stall_id' => $stallId]);

    if ($duplicateStmt->fetch()) {
        throw new RuntimeException('Owner username is already used by another stall.');
    }

    $passwordHash = $existingOwner['password_hash'] ?? null;
    if ($password !== '') {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Failed to secure the owner password.');
        }
    }

    if ($existingOwner) {
        $updateStmt = $pdo->prepare(
            'UPDATE stall_owners '
            . 'SET owner_name = :owner_name, username = :username, password_hash = :password_hash '
            . 'WHERE stall_id = :stall_id'
        );

        $updateStmt->execute([
            ':stall_id' => $stallId,
            ':owner_name' => $ownerName,
            ':username' => $username,
            ':password_hash' => $passwordHash,
        ]);
        return;
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO stall_owners (stall_id, owner_name, username, password_hash) '
        . 'VALUES (:stall_id, :owner_name, :username, :password_hash)'
    );

    $insertStmt->execute([
        ':stall_id' => $stallId,
        ':owner_name' => $ownerName,
        ':username' => $username,
        ':password_hash' => $passwordHash,
    ]);
}

function authenticateStallOwner(PDO $pdo, string $username, string $password): ?array
{
    $stmt = $pdo->prepare(
        'SELECT s.*, o.id AS owner_id, o.owner_name, o.username AS owner_username, o.password_hash '
        . 'FROM stall_owners o '
        . 'INNER JOIN stalls s ON s.id = o.stall_id '
        . 'WHERE o.username = :username '
        . 'LIMIT 1'
    );
    $stmt->execute([':username' => trim($username)]);
    $row = $stmt->fetch();

    if (!is_array($row)) {
        return null;
    }

    if (!password_verify($password, (string)$row['password_hash'])) {
        return null;
    }

    return rowToStallPayload($row);
}

function createOrder(PDO $pdo, int $stallId, string $stallName, array $items, string $customerName = 'Guest'): array
{
    if ($stallId <= 0) {
        throw new RuntimeException('Invalid stall for order.');
    }

    if (!$items) {
        throw new RuntimeException('Order requires at least one item.');
    }

    $normalizedItems = [];
    $totalAmount = 0.0;

    foreach ($items as $item) {
        $name = trim((string)($item['name'] ?? ''));
        $price = (float)($item['price'] ?? 0);
        $qty = max(1, (int)($item['qty'] ?? 1));
        $remarks = trim((string)($item['remarks'] ?? ''));
        $addons = $item['addons'] ?? [];

        if ($name === '') {
            throw new RuntimeException('Order item name is required.');
        }

        $lineTotal = $price * $qty;
        $totalAmount += $lineTotal;

        $normalizedItems[] = [
            'name' => $name,
            'price' => $price,
            'qty' => $qty,
            'addons' => is_array($addons) ? $addons : [],
            'prepStyle' => trim((string)($item['prepStyle'] ?? '')),
            'remarks' => $remarks,
            'lineTotal' => $lineTotal,
        ];
    }

    $itemsJson = json_encode($normalizedItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($itemsJson === false) {
        throw new RuntimeException('Failed to encode order items.');
    }

    $orderCode = 'NB-' . strtoupper(bin2hex(random_bytes(3)));

    $insertStmt = $pdo->prepare(
        'INSERT INTO orders (order_code, stall_id, stall_name, customer_name, total_amount, status, order_items) '
        . 'VALUES (:order_code, :stall_id, :stall_name, :customer_name, :total_amount, :status, :order_items)'
    );

    $insertStmt->execute([
        ':order_code' => $orderCode,
        ':stall_id' => $stallId,
        ':stall_name' => trim($stallName) === '' ? 'Unknown Stall' : trim($stallName),
        ':customer_name' => trim($customerName) === '' ? 'Guest' : trim($customerName),
        ':total_amount' => round($totalAmount, 2),
        ':status' => 'received',
        ':order_items' => $itemsJson,
    ]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'orderCode' => $orderCode,
        'totalAmount' => round($totalAmount, 2),
    ];
}

function fetchKitchenOrders(PDO $pdo, int $limit = 100): array
{
    $safeLimit = max(1, min(500, $limit));

    $stmt = $pdo->query(
        'SELECT id, order_code, stall_id, stall_name, customer_name, total_amount, status, order_items, created_at '
        . 'FROM orders ORDER BY id DESC LIMIT ' . $safeLimit
    );
    $rows = $stmt->fetchAll();

    $orders = [];
    foreach ($rows as $row) {
        $items = json_decode((string)($row['order_items'] ?? '[]'), true);
        $orders[] = [
            'id' => (int)$row['id'],
            'orderCode' => (string)$row['order_code'],
            'stallId' => (int)$row['stall_id'],
            'stallName' => (string)$row['stall_name'],
            'customerName' => (string)$row['customer_name'],
            'totalAmount' => (float)$row['total_amount'],
            'status' => (string)$row['status'],
            'items' => is_array($items) ? $items : [],
            'createdAt' => (string)$row['created_at'],
        ];
    }

    return $orders;
}

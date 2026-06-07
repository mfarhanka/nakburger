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

function rowToStallPayload(array $row): array
{
    $signature = json_decode((string)($row['menu_signature'] ?? '[]'), true);
    $sides = json_decode((string)($row['menu_sides'] ?? '[]'), true);

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
    ];
}

function fetchAllStalls(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM stalls ORDER BY id ASC');
    $rows = $stmt->fetchAll();

    return array_map(static fn(array $row): array => rowToStallPayload($row), $rows);
}

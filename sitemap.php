<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'db.php';

header('Content-Type: application/xml; charset=utf-8');

try {
    $pdo = getDbConnection();

    $stmt = $pdo->query('SELECT id, name, updated_at FROM stalls ORDER BY id ASC');
    $stalls = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"></urlset>";
    exit;
}

$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
$scheme = $isSecure ? 'https' : 'http';
$host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
$basePath = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');

if ($basePath === '/' || $basePath === '\\') {
    $basePath = '';
}

$baseUrl = $scheme . '://' . $host;
$siteRootUrl = $baseUrl . ($basePath !== '' ? $basePath . '/' : '/');

$entries = [
    [
        'loc' => $siteRootUrl,
        'lastmod' => gmdate('Y-m-d'),
        'changefreq' => 'daily',
        'priority' => '1.0',
    ],
];

foreach ($stalls as $stall) {
    $stallId = (int)($stall['id'] ?? 0);
    $stallName = (string)($stall['name'] ?? 'stall');
    $updatedAt = (string)($stall['updated_at'] ?? '');

    if ($stallId <= 0) {
        continue;
    }

    $loc = $baseUrl . '/' . ltrim($basePath . '/' . stallPublicPath($stallId, $stallName), '/');

    $lastmodTimestamp = strtotime($updatedAt);
    $lastmod = $lastmodTimestamp ? gmdate('Y-m-d', $lastmodTimestamp) : gmdate('Y-m-d');

    $entries[] = [
        'loc' => $loc,
        'lastmod' => $lastmod,
        'changefreq' => 'weekly',
        'priority' => '0.8',
    ];
}

$xml = new XMLWriter();
$xml->openMemory();
$xml->startDocument('1.0', 'UTF-8');
$xml->startElement('urlset');
$xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

foreach ($entries as $entry) {
    $xml->startElement('url');
    $xml->writeElement('loc', $entry['loc']);
    $xml->writeElement('lastmod', $entry['lastmod']);
    $xml->writeElement('changefreq', $entry['changefreq']);
    $xml->writeElement('priority', $entry['priority']);
    $xml->endElement();
}

$xml->endElement();
$xml->endDocument();

echo $xml->outputMemory();

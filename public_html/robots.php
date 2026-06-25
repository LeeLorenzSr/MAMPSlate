<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$baseUrl = rtrim($config['app']['base_url'] ?? '', '/');

security_headers();
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
echo "Disallow: /admin\n";
echo "Disallow: /auth\n";
echo "Disallow: /api\n";
echo "\n";
if ($baseUrl !== '') {
    echo "Sitemap: " . $baseUrl . "/sitemap.xml\n";
}

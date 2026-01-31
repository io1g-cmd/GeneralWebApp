<?php
declare(strict_types=1);

$uri = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$uri = $uri ?: '/';

// PHP 內建伺服器：優先回傳靜態檔（css/js/img/content/...）
$file = __DIR__ . $uri;
if (php_sapi_name() === 'cli-server' && is_file($file)) {
    return false;
}

// API
if ($uri === '/api.php' || substr($uri, -strlen('/api.php')) === '/api.php') {
    require __DIR__ . '/api.php';
    return true;
}

// 後台（支援 /admin 與 /admin.php）
if ($uri === '/admin' || $uri === '/admin.php' || substr($uri, -strlen('/admin.php')) === '/admin.php') {
    require __DIR__ . '/admin.php';
    return true;
}

// 其他全部交給前台（包含 /sitemap.xml 與動態頁面）
require __DIR__ . '/index.php';
return true;



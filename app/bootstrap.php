<?php
declare(strict_types=1);

function gwa_starts_with(string $haystack, string $needle): bool {
    if ($needle === '') return true;
    return substr($haystack, 0, strlen($needle)) === $needle;
}

function gwa_ends_with(string $haystack, string $needle): bool {
    if ($needle === '') return true;
    $len = strlen($needle);
    if ($len === 0) return true;
    return substr($haystack, -$len) === $needle;
}

function gwa_base_path(): string {
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptName = str_replace('\\', '/', $scriptName);
    $dir = str_replace('\\', '/', (string)dirname($scriptName));
    if ($dir === '.' || $dir === '\\') {
        $dir = '/';
    }
    if ($dir === '/') {
        return '/';
    }
    if ($dir === '') {
        return '/';
    }
    if ($dir[0] !== '/') {
        $dir = '/' . $dir;
    }
    return rtrim($dir, '/') . '/';
}

function gwa_sanitize_path(string $path): string {
    $path = trim($path);
    $path = preg_replace('/[^a-zA-Z0-9\-_\/]/', '', $path) ?? '';
    $path = preg_replace('#/{2,}#', '/', $path) ?? '';
    $path = trim($path, '/');
    return $path === '' ? 'home' : $path;
}

function gwa_request_path(string $basePath): string {
    // 取得 REQUEST_URI 並解析路徑部分（移除查詢字串和錨點）
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $uriPath = parse_url($requestUri, PHP_URL_PATH);
    $uriPath = $uriPath === null ? '/' : (string)$uriPath;
    $uriPath = str_replace('\\', '/', $uriPath);

    // 移除開頭和結尾的斜線以便處理
    $uriPath = trim($uriPath, '/');

    // 處理 basePath
    $basePath = $basePath === '' ? '/' : $basePath;
    $basePath = rtrim($basePath, '/');
    
    if ($basePath !== '' && $basePath !== '/') {
        // 如果 basePath 不是根目錄，移除 basePath 前綴
        $basePathTrimmed = ltrim($basePath, '/');
        if (gwa_starts_with($uriPath, $basePathTrimmed)) {
            $uriPath = substr($uriPath, strlen($basePathTrimmed));
        $uriPath = ltrim($uriPath, '/');
        }
    }
    
    // 移除已知的檔案前綴（如果被錯誤包含）
    $knownFiles = ['index.php', 'index.html', 'index', 'router.php'];
    foreach ($knownFiles as $file) {
        if ($uriPath === $file || gwa_starts_with($uriPath, $file . '/')) {
            $uriPath = substr($uriPath, strlen($file));
            $uriPath = ltrim($uriPath, '/');
            break;
        }
    }

    return gwa_sanitize_path($uriPath);
}

function gwa_json(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function gwa_read_json_file(string $file, array $default = []): array {
    if (!is_file($file)) {
        return $default;
    }
    $raw = file_get_contents($file);
    if ($raw === false) {
        return $default;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $default;
}

function gwa_mkdirp(string $dir): void {
    if ($dir === '') return;
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('無法建立目錄：' . $dir);
        }
    }
}

function gwa_write_json_file_atomic(string $file, array $data): void {
    $dir = dirname($file);
    gwa_mkdirp($dir);
    $tmp = $file . '.tmp.' . bin2hex(random_bytes(8));
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('JSON 編碼失敗');
    }
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        throw new RuntimeException('寫入檔案失敗');
    }
    if (!rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException('原子寫入失敗');
    }
}



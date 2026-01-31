<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Cms.php';
require_once __DIR__ . '/app/Auth.php';
require_once __DIR__ . '/app/Settings.php';

$basePath = gwa_base_path();
$cms = new Cms(__DIR__);
$auth = new Auth(__DIR__);
$settings = new Settings(__DIR__);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = (string)($_GET['action'] ?? '');
if ($action === '' && $method === 'POST') {
    $action = (string)($_POST['action'] ?? '');
}

function read_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') return [];
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }
    return is_array($decoded) ? $decoded : [];
}

function scheme_host(): array {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return [$scheme, $host];
}

if ($action === 'status') {
    gwa_json([
        'ok' => true,
        'basePath' => $basePath,
        'configured' => $auth->isConfigured(),
        'loggedIn' => $auth->isLoggedIn(),
        'csrfToken' => $auth->isLoggedIn() ? $auth->csrfToken() : null,
        'theme' => $settings->getTheme(),
    ]);
}

if ($action === 'theme_get') {
    gwa_json(['ok' => true, 'theme' => $settings->getTheme(), 'themes' => $settings->themes()]);
}

if ($action === 'theme_set' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $body = read_json_body();
    $theme = (string)($body['theme'] ?? '');
    $settings->setTheme($theme);
    gwa_json(['ok' => true, 'theme' => $settings->getTheme()]);
}

if ($action === 'brand_get') {
    $auth->requireLoggedInJson();
    $brand = $settings->getBrand();
    gwa_json(['ok' => true, 'brand' => $brand]);
}

if ($action === 'brand_set' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $body = read_json_body();
    $title = (string)($body['title'] ?? '');
    $subtitle = (string)($body['subtitle'] ?? '');
    $logo = (string)($body['logo'] ?? '');
    $icon = (string)($body['icon'] ?? '');
    $settings->setBrand($title, $subtitle, $logo, $icon);
    gwa_json(['ok' => true, 'brand' => $settings->getBrand()]);
}

if ($action === 'typography_get') {
    $auth->requireLoggedInJson();
    gwa_json(['ok' => true, 'typography' => $settings->getTypography()]);
}

if ($action === 'typography_set' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $body = read_json_body();
    $typography = (array)($body['typography'] ?? []);
    $settings->setTypography($typography);
    gwa_json(['ok' => true, 'typography' => $settings->getTypography()]);
}

if ($action === 'upload_brand_image' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        gwa_json(['ok' => false, 'error' => '上傳失敗']);
    }
    
    $file = $_FILES['image'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes, true)) {
        gwa_json(['ok' => false, 'error' => '不支援的圖片格式']);
    }
    
    $rootDir = __DIR__;
    $uploadDir = $rootDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'brand' . DIRECTORY_SEPARATOR;
    gwa_mkdirp($uploadDir);
    
    // 設置目錄權限（確保 web 服務器可以讀取）
    @chmod($uploadDir, 0755);
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
    $filename = 'brand_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filepath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        gwa_json(['ok' => false, 'error' => '無法保存文件']);
    }
    
    // 設置文件權限（確保 web 服務器可以讀取）
    @chmod($filepath, 0644);
    
    $url = 'data/brand/' . $filename;
    
    // 生成 logo (200x80) 和 icon (64x64)
    $logoUrl = '';
    $iconUrl = '';
    
    // SVG 文件直接使用，不進行處理
    if (strtolower($ext) === 'svg' || $file['type'] === 'image/svg+xml') {
        $logoUrl = $url;
        $iconUrl = $url;
    } else {
        try {
            $imageInfo = @getimagesize($filepath);
            if ($imageInfo !== false && isset($imageInfo[2])) {
                $srcImage = null;
                switch ($imageInfo[2]) {
                    case IMAGETYPE_JPEG:
                        $srcImage = @imagecreatefromjpeg($filepath);
                        break;
                    case IMAGETYPE_PNG:
                        $srcImage = @imagecreatefrompng($filepath);
                        break;
                    case IMAGETYPE_WEBP:
                        $srcImage = @imagecreatefromwebp($filepath);
                        break;
                    case IMAGETYPE_GIF:
                        $srcImage = @imagecreatefromgif($filepath);
                        break;
                }
                
                if ($srcImage) {
                    $srcW = imagesx($srcImage);
                    $srcH = imagesy($srcImage);
                    
                    // 生成 Logo (200x80)
                    $logoW = 200;
                    $logoH = 80;
                    $logo = imagecreatetruecolor($logoW, $logoH);
                    imagealphablending($logo, false);
                    imagesavealpha($logo, true);
                    $transparent = imagecolorallocatealpha($logo, 0, 0, 0, 127);
                    imagefill($logo, 0, 0, $transparent);
                    
                    $scale = min($logoW / $srcW, $logoH / $srcH);
                    $newW = (int)($srcW * $scale);
                    $newH = (int)($srcH * $scale);
                    $x = (int)(($logoW - $newW) / 2);
                    $y = (int)(($logoH - $newH) / 2);
                    imagecopyresampled($logo, $srcImage, $x, $y, 0, 0, $newW, $newH, $srcW, $srcH);
                    
                    $logoFilename = 'logo_' . time() . '.png';
                    $logoPath = $uploadDir . $logoFilename;
                    if (@imagepng($logo, $logoPath)) {
                        @chmod($logoPath, 0644);
                        $logoUrl = 'data/brand/' . $logoFilename;
                    }
                    imagedestroy($logo);
                    
                    // 生成 Icon (64x64)
                    $iconW = 64;
                    $iconH = 64;
                    $icon = imagecreatetruecolor($iconW, $iconH);
                    imagealphablending($icon, false);
                    imagesavealpha($icon, true);
                    $transparent = imagecolorallocatealpha($icon, 0, 0, 0, 127);
                    imagefill($icon, 0, 0, $transparent);
                    
                    $scale = min($iconW / $srcW, $iconH / $srcH);
                    $newW = (int)($srcW * $scale);
                    $newH = (int)($srcH * $scale);
                    $x = (int)(($iconW - $newW) / 2);
                    $y = (int)(($iconH - $newH) / 2);
                    imagecopyresampled($icon, $srcImage, $x, $y, 0, 0, $newW, $newH, $srcW, $srcH);
                    
                    $iconFilename = 'icon_' . time() . '.png';
                    $iconPath = $uploadDir . $iconFilename;
                    if (@imagepng($icon, $iconPath)) {
                        @chmod($iconPath, 0644);
                        $iconUrl = 'data/brand/' . $iconFilename;
                    }
                    imagedestroy($icon);
                    
                    imagedestroy($srcImage);
                }
            }
            
            // 如果處理失敗，使用原始圖片
            if (!$logoUrl) $logoUrl = $url;
            if (!$iconUrl) $iconUrl = $url;
        } catch (Exception $e) {
            // 如果圖片處理失敗，使用原始圖片
            $logoUrl = $url;
            $iconUrl = $url;
        }
    }
    
    gwa_json([
        'ok' => true,
        'url' => $url,
        'logo' => $logoUrl ?: $url,
        'icon' => $iconUrl ?: $url
    ]);
}

if ($action === 'footer_get') {
    $auth->requireLoggedInJson();
    $footer = $settings->getFooter();
    gwa_json(['ok' => true, 'footer' => $footer]);
}

if ($action === 'footer_set' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $body = read_json_body();
    $html = (string)($body['html'] ?? '');
    $settings->setFooter($html);
    gwa_json(['ok' => true, 'footer' => $settings->getFooter()]);
}

if ($action === 'whatsapp_get') {
    $whatsapp = $settings->getWhatsApp();
    gwa_json(['ok' => true, 'whatsapp' => $whatsapp]);
}

if ($action === 'whatsapp_set' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $body = read_json_body();
    $number = (string)($body['number'] ?? '');
    $settings->setWhatsApp($number);
    gwa_json(['ok' => true, 'whatsapp' => $settings->getWhatsApp()]);
}

if ($action === 'checkout_page_get') {
    $auth->requireLoggedInJson();
    $checkoutPage = $settings->getCheckoutPage();
    gwa_json(['ok' => true, 'checkout_page' => $checkoutPage]);
}

if ($action === 'checkout_page_set' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $body = read_json_body();
    $html = (string)($body['html'] ?? '');
    $settings->setCheckoutPage($html);
    gwa_json(['ok' => true, 'checkout_page' => $settings->getCheckoutPage()]);
}

if ($action === 'currency_get') {
    $currency = $settings->getCurrency();
    $symbol = $settings->getCurrencySymbol();
    gwa_json(['ok' => true, 'currency' => $currency, 'symbol' => $symbol]);
}

if ($action === 'currency_set' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $body = read_json_body();
    $currency = (string)($body['currency'] ?? 'HKD');
    $settings->setCurrency($currency);
    gwa_json(['ok' => true, 'currency' => $settings->getCurrency(), 'symbol' => $settings->getCurrencySymbol()]);
}

if ($action === 'maintenance_get') {
    $auth->requireLoggedInJson();
    $enabled = $settings->getMaintenanceMode();
    gwa_json(['ok' => true, 'maintenance_mode' => $enabled]);
}

if ($action === 'maintenance_set' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $body = read_json_body();
    $enabled = (bool)($body['enabled'] ?? false);
    $settings->setMaintenanceMode($enabled);
    gwa_json(['ok' => true, 'maintenance_mode' => $settings->getMaintenanceMode()]);
}

if ($action === 'languages_get') {
    // 公開 API，前端頁面需要顯示語言選擇器
    $languages = $settings->getLanguages();
    $defaultLang = $settings->getDefaultLanguage();
    gwa_json(['ok' => true, 'languages' => $languages, 'default_language' => $defaultLang]);
}

if ($action === 'languages_set' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $body = read_json_body();
    $languages = (array)($body['languages'] ?? []);
    $defaultLang = (string)($body['default_language'] ?? 'zh-TW');
    $settings->setLanguages($languages);
    $settings->setDefaultLanguage($defaultLang);
    gwa_json(['ok' => true, 'languages' => $settings->getLanguages(), 'default_language' => $settings->getDefaultLanguage()]);
}

if ($action === 'translations_get') {
    $lang = (string)($_GET['lang'] ?? '');
    if ($lang === '') {
        $auth->requireLoggedInJson();
        $translations = $settings->getTranslations();
        gwa_json(['ok' => true, 'translations' => $translations]);
    } else {
        // 允許前端不登錄獲取特定語言的翻譯
        $translations = $settings->getTranslations();
        $langTranslations = (array)($translations[$lang] ?? []);
        gwa_json(['ok' => true, 'translations' => $langTranslations, 'lang' => $lang]);
    }
}

if ($action === 'translations_set' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $body = read_json_body();
    $translations = (array)($body['translations'] ?? []);
    
    // 驗證並清理 translations 數據結構
    $cleanedTranslations = [];
    foreach ($translations as $lang => $langTranslations) {
        if (is_string($lang) && is_array($langTranslations)) {
            $cleanedLangTranslations = [];
            foreach ($langTranslations as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $key = trim($key);
                    $value = trim($value);
                    if ($key !== '' && $value !== '') {
                        $cleanedLangTranslations[$key] = $value;
                    }
                }
            }
            if (!empty($cleanedLangTranslations)) {
                $cleanedTranslations[trim($lang)] = $cleanedLangTranslations;
            }
        }
    }
    
    $settings->setTranslations($cleanedTranslations);
    $saved = $settings->getTranslations();
    gwa_json(['ok' => true, 'translations' => $saved]);
}

if ($action === 'translate' && $method === 'POST') {
    $body = read_json_body();
    $text = (string)($body['text'] ?? '');
    $targetLang = (string)($body['target_lang'] ?? $body['to'] ?? 'en');
    $sourceLang = (string)($body['source_lang'] ?? $body['from'] ?? '');
    
    // 如果沒有提供源語言，從設置中獲取第一個原生語言或預設語言
    if ($sourceLang === '') {
        $languages = $settings->getLanguages();
        $defaultLang = $settings->getDefaultLanguage();
        
        // 優先使用第一個原生語言
        $nativeLangs = [];
        foreach ($languages as $lang => $info) {
            if (is_array($info) && ($info['native'] ?? false) === true) {
                $nativeLangs[] = $lang;
            }
        }
        
        if (count($nativeLangs) > 0) {
            $sourceLang = $nativeLangs[0]; // 使用第一個原生語言
        } elseif ($defaultLang && isset($languages[$defaultLang])) {
            $sourceLang = $defaultLang; // 使用預設語言
        } else {
            $sourceLang = 'zh-TW'; // 最後的後備選項（向後兼容）
        }
    }
    
    if ($text === '') {
        gwa_json(['ok' => false, 'error' => '缺少文字內容'], 400);
        return;
    }
    
    // 如果源語言和目標語言相同，直接返回原文
    if ($sourceLang === $targetLang) {
        gwa_json(['ok' => true, 'translated' => $text, 'source' => 'none', 'cached' => false]);
        return;
    }
    
    // ========== 新架構：智能翻譯系統 ==========
    // 步驟 1：嚴格執行措詞功能（優先於自動翻譯）
    $originalText = $text; // 保存原始文本
    
    // 從 Settings 獲取措詞數據（統一管理）
    $allTranslations = $settings->getTranslations();
    $wordingData = (array)($allTranslations[$targetLang] ?? []);
    
    if (!empty($wordingData)) {
        // 檢查完全匹配
        if (isset($wordingData[$originalText])) {
            $wordingValue = trim((string)$wordingData[$originalText]);
            if ($wordingValue !== '') {
                // 措詞完全匹配成功，直接返回（不進行自動翻譯）
                gwa_json([
                    'ok' => true,
                    'translated' => $wordingValue,
                    'source' => 'wording',
                    'cached' => false
                ]);
                return;
            }
        }
        
        // 檢查部分匹配（措詞中的 key 是否包含在文本中）
        $sortedKeys = array_keys($wordingData);
        usort($sortedKeys, function($a, $b) {
            return strlen($b) - strlen($a); // 按長度降序排列
        });
        
        foreach ($sortedKeys as $key) {
            $value = trim((string)($wordingData[$key] ?? ''));
            if ($value !== '' && strpos($text, $key) !== false) {
                // 部分匹配：替換文本中的措詞 key
                $isCJK = preg_match('/[\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}\x{3040}-\x{309f}\x{30a0}-\x{30ff}\x{ac00}-\x{d7af}]/u', $key);
                if ($isCJK) {
                    $text = str_replace($key, $value, $text);
                } else {
                    $text = preg_replace('/\b' . preg_quote($key, '/') . '\b/iu', $value, $text);
                }
            }
        }
    }
    
    // 步驟 2：檢查伺服器端緩存（比較時間戳）
    $pagePath = (string)($body['page_path'] ?? ''); // 可選：頁面路徑，用於獲取原文修改時間
    $translationsDir = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'translations' . DIRECTORY_SEPARATOR;
    $cacheDir = $translationsDir . 'cache' . DIRECTORY_SEPARATOR;
    gwa_mkdirp($cacheDir);
    
    $textHash = md5($text . $sourceLang . $targetLang);
    $cacheFile = $cacheDir . $textHash . '.json';
    
    $useCache = false;
    $cachedTranslation = null;
    $cacheTimestamp = 0;
    
    if (is_file($cacheFile)) {
        $cacheData = @json_decode(file_get_contents($cacheFile), true);
        if (is_array($cacheData) && isset($cacheData['translated']) && isset($cacheData['timestamp'])) {
            $cacheTimestamp = (int)($cacheData['timestamp'] ?? 0);
            $sourceTimestamp = 0;
            
            // 獲取原文修改時間（如果有提供頁面路徑）
            if ($pagePath !== '') {
                $contentFile = $cms->contentPath($pagePath);
                if (is_file($contentFile)) {
                    $sourceTimestamp = filemtime($contentFile);
                }
            }
            
            // 如果緩存時間晚於或等於原文修改時間，使用緩存
            if ($cacheTimestamp >= $sourceTimestamp) {
                $useCache = true;
                $cachedTranslation = $cacheData['translated'];
                error_log("[translate] 使用緩存: \"{$text}\" -> \"{$cachedTranslation}\" (緩存時間: {$cacheTimestamp}, 原文時間: {$sourceTimestamp})");
            } else {
                error_log("[translate] 緩存過時，需要重新翻譯 (緩存時間: {$cacheTimestamp}, 原文時間: {$sourceTimestamp})");
            }
        }
    }
    
    if ($useCache && $cachedTranslation !== null) {
        gwa_json([
            'ok' => true,
            'translated' => $cachedTranslation,
            'source' => 'cache',
            'cached' => true,
            'timestamp' => $cacheTimestamp
        ]);
        return;
    }
    
    // 步驟 3：調用外部翻譯 API（緩存過時或不存在時）
    // LibreTranslate API 語言代碼映射（LibreTranslate 使用的標準語言代碼）
    $libreLangMap = [
        'zh-TW' => 'zh',
        'zh-CN' => 'zh',
        'en' => 'en',
        'ja' => 'ja',
        'ko' => 'ko',
        'es' => 'es',
        'fr' => 'fr',
        'de' => 'de',
        'it' => 'it',
        'pt' => 'pt',
        'ru' => 'ru',
        'ar' => 'ar',
    ];
    
    $libreSourceCode = $libreLangMap[$sourceLang] ?? 'zh';
    $libreTargetCode = $libreLangMap[$targetLang] ?? 'en';
    
    // 多個備用翻譯服務（避免 429 速率限制）
    // 並發請求多個服務，第一個成功即使用，取消其他請求
    $services = [
        // LibreTranslate 實例 1（優先）
        [
            'name' => 'LibreTranslate (argosopentech)',
            'url' => 'https://translate.argosopentech.com/translate',
            'type' => 'libretranslate',
            'data' => [
                'q' => $text,
                'source' => $libreSourceCode,
                'target' => $libreTargetCode,
                'format' => 'text'
            ]
        ],
        // LibreTranslate 實例 2（備用）
        [
            'name' => 'LibreTranslate (libretranslate.de)',
            'url' => 'https://libretranslate.de/translate',
            'type' => 'libretranslate',
            'data' => [
                'q' => $text,
                'source' => $libreSourceCode,
                'target' => $libreTargetCode,
                'format' => 'text'
            ]
        ],
        // LibreTranslate 實例 3（備用）
        [
            'name' => 'LibreTranslate (libretranslate.com)',
            'url' => 'https://libretranslate.com/translate',
            'type' => 'libretranslate',
            'data' => [
                'q' => $text,
                'source' => $libreSourceCode,
                'target' => $libreTargetCode,
                'format' => 'text'
            ]
        ],
        // MyMemory（最後備用，容易 429）
        [
            'name' => 'MyMemory',
            'url' => 'https://api.mymemory.translated.net/get',
            'type' => 'mymemory',
            'data' => [
                'q' => $text,
                'langpair' => $sourceLang . '|' . $targetLang
            ]
        ]
    ];
    
    // 並發執行多個服務的請求
    $multiHandle = curl_multi_init();
    if ($multiHandle === false) {
        error_log("[translate] 無法初始化 curl_multi_init");
        gwa_json(['ok' => false, 'error' => '無法初始化並發請求'], 500);
        return;
    }
    
    $handles = [];
    $serviceMap = [];
    
    // 為每個服務創建請求（最多同時發起 3 個，避免過載）
    $maxConcurrent = 3;
    $servicesToUse = array_slice($services, 0, $maxConcurrent);
    
    foreach ($servicesToUse as $service) {
        try {
            if ($service['type'] === 'mymemory') {
            // MyMemory 使用 GET 請求
            $url = $service['url'] . '?' . http_build_query($service['data']);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        } else {
            // LibreTranslate 使用 POST 請求
            $ch = curl_init($service['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($service['data']));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        }
        
            curl_setopt($ch, CURLOPT_USERAGENT, 'GeneralWebApp/1.0');
            $addResult = curl_multi_add_handle($multiHandle, $ch);
            if ($addResult !== 0) {
                error_log("[translate] 無法添加 {$service['name']} 到並發請求: cURL 錯誤 {$addResult}");
                curl_close($ch);
                continue;
            }
            $handles[] = $ch;
            $serviceMap[(int)$ch] = $service;
        } catch (Throwable $e) {
            error_log("[translate] 創建 {$service['name']} 請求時發生異常: " . $e->getMessage());
            continue;
        }
    }
    
    // 如果沒有任何有效的請求，返回錯誤
    if (empty($handles)) {
        curl_multi_close($multiHandle);
        error_log("[translate] 無法創建任何有效的翻譯請求");
        gwa_json(['ok' => false, 'error' => '無法創建翻譯請求'], 500);
        return;
    }
    
    $active = null;
    $successResult = null;
    $lastError = null;
    $completedHandles = [];
    
    // 執行並發請求，等待第一個成功響應
    do {
        $mrc = curl_multi_exec($multiHandle, $active);
        
        // 檢查是否有完成的請求
        while ($info = curl_multi_info_read($multiHandle)) {
            if ($info['msg'] === CURLMSG_DONE) {
                $ch = $info['handle'];
                $handleId = (int)$ch;
                
                if (in_array($handleId, $completedHandles)) {
                    continue; // 已經處理過
                }
                $completedHandles[] = $handleId;
                
                // 檢查 serviceMap 中是否存在該 handle
                if (!isset($serviceMap[$handleId])) {
                    error_log("[translate] 警告: handleId {$handleId} 不在 serviceMap 中");
                    continue;
                }
                
                $service = $serviceMap[$handleId];
                $response = curl_multi_getcontent($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                $curlErrno = curl_errno($ch);
                
                // 檢查 cURL 錯誤
                if ($curlErrno !== 0 || $curlError !== '') {
                    $errorMsg = $curlError ?: "cURL 錯誤代碼: {$curlErrno}";
                    error_log("[translate] {$service['name']} cURL 錯誤: {$errorMsg}");
                    $lastError = "翻譯服務連接失敗: {$errorMsg}";
                    continue;
                }
                
                // 檢查響應是否為 false
                if ($response === false) {
                    error_log("[translate] {$service['name']} 響應為空");
                    $lastError = '翻譯服務無響應';
                    continue;
                }
                
                // 檢查響應是否為 HTML
                if (stripos($response, '<!DOCTYPE') !== false || stripos($response, '<html') !== false) {
                    error_log("[translate] {$service['name']} 返回 HTML 而非 JSON");
                    $lastError = '翻譯服務端點返回 HTML';
                    continue;
                }
                
                // 處理 HTTP 錯誤
                if ($httpCode === 400) {
                    // 400 Bad Request：檢查請求格式
                    $errorData = @json_decode($response, true);
                    $errorMsg = is_array($errorData) && isset($errorData['error']) 
                        ? $errorData['error'] 
                        : "HTTP 400 Bad Request - 請求格式錯誤";
                    
                    // 檢查是否是需要 API key 的錯誤（跳過此服務，嘗試其他）
                    if (stripos($errorMsg, 'API key') !== false || stripos($errorMsg, 'portal.libretranslate') !== false) {
                        error_log("[translate] {$service['name']} 需要 API key，跳過此服務: {$errorMsg}");
                        continue; // 跳過，嘗試其他服務
                    }
                    
                    error_log("[translate] {$service['name']} 400 Bad Request: {$errorMsg}, 請求數據: " . json_encode($service['data']));
                    $lastError = "請求格式錯誤: {$errorMsg}";
                    continue;
                }
                
                if ($httpCode === 429) {
                    // HTTP 429：速率限制（自動跳過，嘗試其他服務）
                    $errorData = @json_decode($response, true);
                    $errorMsg = is_array($errorData) && isset($errorData['error']) 
                        ? $errorData['error'] 
                        : "HTTP 429 Too Many Requests - 速率限制";
                    error_log("[translate] {$service['name']} 429 速率限制，跳過此服務: {$errorMsg}");
                    // 不設置 lastError，繼續嘗試其他服務
                    continue;
                }
                
                if ($httpCode !== 200) {
                    $errorData = @json_decode($response, true);
                    $errorMsg = is_array($errorData) && isset($errorData['error']) 
                        ? $errorData['error'] 
                        : "HTTP {$httpCode}";
                    
                    // 檢查是否是需要 API key 的錯誤（跳過此服務，嘗試其他）
                    if (stripos($errorMsg, 'API key') !== false || stripos($errorMsg, 'portal.libretranslate') !== false) {
                        error_log("[translate] {$service['name']} 需要 API key，跳過此服務: {$errorMsg}");
                        continue; // 跳過，嘗試其他服務
                    }
                    
                    error_log("[translate] {$service['name']} HTTP 錯誤: {$errorMsg}, HTTP 狀態: {$httpCode}");
                    $lastError = "翻譯服務錯誤: {$errorMsg}";
                    continue;
                }
                
                // 解析 JSON 響應
                $result = json_decode($response, true);
                $jsonError = json_last_error();
                
                if ($jsonError !== JSON_ERROR_NONE) {
                    $errorMsg = json_last_error_msg();
                    if (stripos($response, '<!DOCTYPE') !== false || stripos($response, '<html') !== false) {
                        $errorMsg = "服務返回 HTML 而非 JSON";
                    }
                    error_log("[translate] {$service['name']} JSON 解析錯誤: {$errorMsg}");
                    $lastError = "翻譯服務響應格式錯誤: {$errorMsg}";
                    continue;
                }
                
                // 根據服務類型解析響應
                $translated = null;
                
                if ($service['type'] === 'mymemory') {
                    // MyMemory 響應格式: {"responseData":{"translatedText":"..."},"responseStatus":200}
                    if (isset($result['responseData']['translatedText']) && $result['responseStatus'] === 200) {
                        $translated = trim($result['responseData']['translatedText']);
                    } else {
                        error_log("[translate] {$service['name']} 響應格式異常");
                        $lastError = "MyMemory 響應格式異常";
                        continue;
                    }
                } else {
                    // LibreTranslate 響應格式: {"translatedText":"..."}
                    if (isset($result['translatedText']) && is_string($result['translatedText'])) {
                        $translated = trim($result['translatedText']);
                    } else {
                        error_log("[translate] {$service['name']} 響應格式異常，缺少 translatedText 字段");
                        $lastError = "響應格式異常，缺少 translatedText 字段";
                        continue;
                    }
                }
                
                if ($translated === '' || $translated === null) {
                    error_log("[translate] {$service['name']} 返回空翻譯結果");
                    $lastError = '翻譯服務返回空結果';
                    continue;
                }
                
                // 成功！取消其他請求並返回結果
                $successResult = [
                    'translated' => $translated,
                    'source' => $service['name']
                ];
                
                // 取消所有其他請求
                foreach ($handles as $otherCh) {
                    if ((int)$otherCh !== $handleId) {
                        curl_multi_remove_handle($multiHandle, $otherCh);
                        curl_close($otherCh);
                    }
                }
                
                curl_multi_remove_handle($multiHandle, $ch);
                curl_multi_close($multiHandle);
                
                // 成功返回翻譯結果，並保存到伺服器端緩存
                error_log("[translate] {$service['name']} 翻譯成功: \"{$text}\" -> \"{$translated}\"");
                
                // 保存翻譯結果到伺服器端緩存
                $cacheData = [
                    'translated' => $translated,
                    'source' => $service['name'],
                    'timestamp' => time(),
                    'text_hash' => $textHash,
                    'source_lang' => $sourceLang,
                    'target_lang' => $targetLang
                ];
                @file_put_contents($cacheFile, json_encode($cacheData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                
                gwa_json([
                    'ok' => true,
                    'translated' => $translated,
                    'source' => $service['name'],
                    'cached' => false,
                    'timestamp' => $cacheData['timestamp']
                ]);
                return;
            }
        }
        
        // 如果已經有成功結果，退出循環
        if ($successResult !== null) {
            break;
        }
        
        // 避免 CPU 佔用過高
        if ($active > 0) {
            curl_multi_select($multiHandle, 0.1);
        }
        
    } while ($active > 0 && $mrc === CURLM_OK);
    
    // 清理所有請求
    foreach ($handles as $ch) {
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }
    curl_multi_close($multiHandle);
    
    // 如果前 3 個服務都失敗，嘗試剩餘的服務（順序執行）
    if ($successResult === null && count($services) > $maxConcurrent) {
        $remainingServices = array_slice($services, $maxConcurrent);
        error_log("[translate] 前 {$maxConcurrent} 個服務都失敗，嘗試剩餘 " . count($remainingServices) . " 個服務");
        
        foreach ($remainingServices as $service) {
            try {
                if ($service['type'] === 'mymemory') {
                    $url = $service['url'] . '?' . http_build_query($service['data']);
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                } else {
                    $ch = curl_init($service['url']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($service['data']));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Accept: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                }
                
                curl_setopt($ch, CURLOPT_USERAGENT, 'GeneralWebApp/1.0');
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                $curlErrno = curl_errno($ch);
                curl_close($ch);
                
                // 跳過 429 錯誤，繼續嘗試下一個
                if ($httpCode === 429) {
                    error_log("[translate] {$service['name']} 429 速率限制，跳過");
                    continue;
                }
                
                // 檢查其他錯誤
                if ($curlErrno !== 0 || $curlError !== '' || $response === false || $httpCode !== 200) {
                    continue;
                }
                
                // 解析響應
                $result = json_decode($response, true);
                if ($result === null) continue;
                
                $translated = null;
                if ($service['type'] === 'mymemory') {
                    if (isset($result['responseData']['translatedText']) && $result['responseStatus'] === 200) {
                        $translated = trim($result['responseData']['translatedText']);
                    }
                } else {
                    if (isset($result['translatedText']) && is_string($result['translatedText'])) {
                        $translated = trim($result['translatedText']);
                    }
                }
                
                if ($translated && $translated !== '') {
                    error_log("[translate] {$service['name']} 翻譯成功（備用服務）: \"{$text}\" -> \"{$translated}\"");
                    
                    // 保存翻譯結果到伺服器端緩存
                    $cacheData = [
                        'translated' => $translated,
                        'source' => $service['name'],
                        'timestamp' => time(),
                        'text_hash' => $textHash,
                        'source_lang' => $sourceLang,
                        'target_lang' => $targetLang
                    ];
                    @file_put_contents($cacheFile, json_encode($cacheData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    
                    gwa_json([
                        'ok' => true,
                        'translated' => $translated,
                        'source' => $service['name'],
                        'cached' => false,
                        'timestamp' => $cacheData['timestamp']
                    ]);
                    return;
                }
            } catch (Throwable $e) {
                error_log("[translate] {$service['name']} 異常: " . $e->getMessage());
                continue;
            }
        }
    }
    
    // 如果所有服務都失敗，返回錯誤
    error_log("[translate] 所有翻譯服務都失敗，最後錯誤: {$lastError}");
    gwa_json(['ok' => false, 'error' => $lastError ?: '所有翻譯服務都不可用'], 500);
}

if ($action === 'checkout' && $method === 'POST') {
    $body = read_json_body();
    $items = (array)($body['items'] ?? []);
    $customerInfo = (array)($body['customer'] ?? []);
    
    if (empty($items)) {
        gwa_json(['ok' => false, 'error' => '購物車為空'], 400);
    }
    
    // 驗證商品
    $pages = $cms->getPages();
    $pagesByPath = [];
    foreach ($pages as $p) {
        $pagesByPath[(string)($p['path'] ?? '')] = $p;
    }
    
    $validItems = [];
    $total = 0;
    foreach ($items as $item) {
        $path = (string)($item['path'] ?? '');
        $quantity = (int)($item['quantity'] ?? 1);
        if ($quantity < 1) continue;
        
        if (!isset($pagesByPath[$path])) continue;
        $page = $pagesByPath[$path];
        if (($page['type'] ?? 'page') !== 'product') continue;
        
        $price = (float)($page['price'] ?? 0);
        $validItems[] = [
            'path' => $path,
            'title' => (string)($page['title'] ?? ''),
            'menu_title' => (string)($page['menu_title'] ?? $page['title'] ?? ''),
            'price' => $price,
            'quantity' => $quantity,
        ];
        $total += $price * $quantity;
    }
    
    if (empty($validItems)) {
        gwa_json(['ok' => false, 'error' => '無有效商品'], 400);
    }
    
    // 儲存訂單
    $ordersDir = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'orders' . DIRECTORY_SEPARATOR;
    gwa_mkdirp($ordersDir);
    
    // 生成唯一的訂單 ID（避免衝突）
    $maxAttempts = 10;
    $orderId = '';
    $orderFile = '';
    for ($i = 0; $i < $maxAttempts; $i++) {
        $orderId = date('Ymd-His-') . bin2hex(random_bytes(4));
        $orderFile = $ordersDir . $orderId . '.json';
        if (!is_file($orderFile)) {
            break;
        }
    }
    if ($orderId === '' || is_file($orderFile)) {
        gwa_json(['ok' => false, 'error' => '無法生成唯一訂單 ID'], 500);
    }
    
    $order = [
        'id' => $orderId,
        'created_at' => date('c'),
        'items' => $validItems,
        'total' => $total,
        'customer' => [
            'name' => trim((string)($customerInfo['name'] ?? '')),
            'phone' => trim((string)($customerInfo['phone'] ?? '')),
            'email' => trim((string)($customerInfo['email'] ?? '')),
            'address' => trim((string)($customerInfo['address'] ?? '')),
            'note' => trim((string)($customerInfo['note'] ?? '')),
        ],
        'status' => 'pending',
    ];
    
    gwa_write_json_file_atomic($orderFile, $order);
    
    gwa_json([
        'ok' => true,
        'order_id' => $orderId,
        'total' => $total,
        'checkout_page' => $settings->getCheckoutPage(),
    ]);
}

if ($action === 'orders' && $method === 'GET') {
    $auth->requireLoggedInJson();
    
    $ordersDir = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'orders' . DIRECTORY_SEPARATOR;
    $orders = [];
    
    try {
        // 確保目錄存在
        if (!is_dir($ordersDir)) {
            gwa_mkdirp($ordersDir);
        }
        
        if (is_dir($ordersDir)) {
            $files = @glob($ordersDir . '*.json');
            if ($files === false) {
                $files = [];
            }
            
            // 調試：記錄找到的文件數量
            error_log("訂單目錄: {$ordersDir}, 找到文件數: " . count($files));
            
            foreach ($files as $file) {
                if (!is_file($file)) {
                    error_log("跳過非文件: {$file}");
                    continue;
                }
                
                // 檢查檔案是否可讀
                if (!is_readable($file)) {
                    error_log("文件不可讀: {$file}");
                    continue;
                }
                
                try {
                    // 直接讀取文件內容
                    $raw = file_get_contents($file);
                    if ($raw === false) {
                        error_log("無法讀取文件內容: {$file}");
                        continue;
                    }
                    
                    $order = json_decode($raw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log("JSON 解析錯誤 ({$file}): " . json_last_error_msg());
                        continue;
                    }
                    
                    if (!is_array($order)) {
                        error_log("訂單數據不是數組: {$file}");
                        continue;
                    }
                    
                        // 確保訂單有必要的欄位
                        if (!isset($order['id'])) {
                            // 從檔名提取訂單 ID
                            $order['id'] = basename($file, '.json');
                        }
                        $orders[] = $order;
                    error_log("成功讀取訂單: {$order['id']}");
                } catch (Throwable $e) {
                    // 跳過無法讀取的訂單檔案，記錄錯誤但繼續處理
                    error_log("無法讀取訂單檔案 {$file}: " . $e->getMessage());
                    continue;
                }
            }
            
            // 按建立時間排序（最新的在前）
            usort($orders, function($a, $b) {
                $ta = strtotime($a['created_at'] ?? '1970-01-01');
                $tb = strtotime($b['created_at'] ?? '1970-01-01');
                return $tb <=> $ta;
            });
        }
        
        gwa_json(['ok' => true, 'orders' => $orders, 'count' => count($orders)]);
    } catch (Throwable $e) {
        error_log("訂單讀取錯誤: " . $e->getMessage());
        gwa_json(['ok' => false, 'error' => '讀取訂單失敗：' . $e->getMessage()], 500);
    }
}

if ($action === 'order_update' && $method === 'POST') {
    try {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
        
    $body = read_json_body();
        if (empty($body)) {
            error_log('[order_update] 請求體為空或 JSON 解析失敗');
            gwa_json(['ok' => false, 'error' => '請求資料格式錯誤'], 400);
        }
        
        $orderId = isset($body['order_id']) ? trim((string)$body['order_id']) : '';
        $status = isset($body['status']) ? trim((string)$body['status']) : '';
    $note = isset($body['admin_note']) ? trim((string)$body['admin_note']) : null;
    
    if ($orderId === '') {
            error_log('[order_update] 缺少訂單 ID');
        gwa_json(['ok' => false, 'error' => '缺少訂單 ID'], 400);
    }
    
    $allowedStatuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];
        if ($status === '' || !in_array($status, $allowedStatuses, true)) {
            error_log("[order_update] 無效的訂單狀態: {$status}");
        gwa_json(['ok' => false, 'error' => '無效的訂單狀態'], 400);
    }
    
    $ordersDir = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'orders' . DIRECTORY_SEPARATOR;
        gwa_mkdirp($ordersDir);
        
    $orderFile = $ordersDir . $orderId . '.json';
    
    if (!is_file($orderFile)) {
            error_log("[order_update] 訂單檔案不存在: {$orderFile}");
        gwa_json(['ok' => false, 'error' => '訂單不存在'], 404);
    }
    
        $order = gwa_read_json_file($orderFile, []);
        if (empty($order) || !is_array($order)) {
            error_log("[order_update] 訂單資料異常: {$orderFile}");
        gwa_json(['ok' => false, 'error' => '訂單資料異常'], 500);
    }
        
        // 驗證訂單 ID 是否匹配
        if (!isset($order['id']) || $order['id'] !== $orderId) {
            error_log("[order_update] 訂單 ID 不匹配: 期望 {$orderId}, 實際 " . ($order['id'] ?? 'null'));
            gwa_json(['ok' => false, 'error' => '訂單 ID 不匹配'], 400);
        }
    
    $oldStatus = (string)($order['status'] ?? 'pending');
        
        // 如果狀態沒有改變，只更新備註（如果提供）
        if ($oldStatus === $status && $note === null) {
            gwa_json(['ok' => true, 'order' => $order, 'message' => '無變更']);
        }
        
    $order['status'] = $status;
    $order['updated_at'] = date('c');
    
    // 記錄狀態變更歷史
        if (!isset($order['status_history']) || !is_array($order['status_history'])) {
        $order['status_history'] = [];
    }
    $order['status_history'][] = [
        'from' => $oldStatus,
        'to' => $status,
        'at' => date('c'),
        'by' => 'admin'
    ];
    
        // 更新管理員備註（如果提供）
        if ($note !== null && $note !== '') {
        $order['admin_note'] = $note;
        } elseif ($note === '') {
            // 如果傳入空字串，清除備註
            unset($order['admin_note']);
    }
    
        // 原子寫入訂單檔案
    gwa_write_json_file_atomic($orderFile, $order);
    
        error_log("[order_update] 成功更新訂單 {$orderId}: {$oldStatus} -> {$status}");
    gwa_json(['ok' => true, 'order' => $order]);
        
    } catch (Throwable $e) {
        error_log('[order_update] 異常: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        gwa_json(['ok' => false, 'error' => '更新訂單時發生錯誤：' . $e->getMessage()], 500);
    }
}

if ($action === 'page') {
    $path = (string)($_GET['path'] ?? 'home');
    $path = gwa_sanitize_path($path);
    $pages = $cms->getPages();
    $page = $cms->getPage($path);
    if ($page === null) {
        // 沒有 meta 也允許讀內容（但會顯示不存在）
        $page = ['path' => $path, 'title' => '頁面不存在', 'menu_title' => '', 'parent' => ''];
    }
    $html = $cms->getContentHtml($path);
    $breadcrumbs = $cms->buildBreadcrumbs($pages, $path);

    [$scheme, $host] = scheme_host();
    $canonicalPath = $cms->publicUrl($basePath, $path);
    $canonical = $scheme . '://' . $host . $canonicalPath;

    gwa_json([
        'ok' => true,
        'page' => $page,
        'html' => $html,
        'breadcrumbs' => $breadcrumbs,
        'canonical' => $canonical,
        'csrfToken' => $auth->isLoggedIn() ? $auth->csrfToken() : null,
    ]);
}

if ($action === 'pages') {
    $pages = $cms->getPages();
    gwa_json(['ok' => true, 'pages' => $pages]);
}

if ($action === 'nav_update' && $method === 'POST') {
    try {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $body = read_json_body();
    $updates = (array)($body['updates'] ?? []);
        $pathChanges = $cms->updateNav($updates);
        gwa_json(['ok' => true, 'pathChanges' => $pathChanges]);
    } catch (\Exception $e) {
        error_log('[GWA] nav_update error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        gwa_json(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($action === 'login' && $method === 'POST') {
    if (!$auth->isConfigured()) {
        gwa_json(['ok' => false, 'error' => '尚未設定管理員密碼'], 400);
    }
    $body = read_json_body();
    $password = (string)($body['password'] ?? ($_POST['password'] ?? ''));
    if ($auth->login($password)) {
        gwa_json(['ok' => true, 'csrfToken' => $auth->csrfToken()]);
    }
    gwa_json(['ok' => false, 'error' => '密碼錯誤'], 401);
}

if ($action === 'logout' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $auth->logout();
    gwa_json(['ok' => true]);
}

if ($action === 'change_password' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $body = read_json_body();
    $current = (string)($body['currentPassword'] ?? '');
    $next = (string)($body['newPassword'] ?? '');
    $auth->changePassword($current, $next);
    gwa_json(['ok' => true, 'csrfToken' => $auth->csrfToken()]);
}

if ($action === 'set_password' && $method === 'POST') {
    if ($auth->isConfigured()) {
        gwa_json(['ok' => false, 'error' => '已設定過密碼'], 400);
    }
    $body = read_json_body();
    $password = (string)($body['password'] ?? ($_POST['password'] ?? ''));
    $auth->setPassword($password);
    $auth->login($password);
    gwa_json(['ok' => true, 'csrfToken' => $auth->csrfToken()]);
}

if ($action === 'save_page' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $body = read_json_body();
    $page = (array)($body['page'] ?? []);
    
    // 驗證必要欄位
    if (empty($page['path']) && empty($page['title'])) {
        gwa_json(['ok' => false, 'error' => '缺少必要欄位：path 或 title'], 400);
    }
    
    // 處理 oldPath：空字串轉為 null
    $oldPath = null;
    if (isset($body['oldPath'])) {
        $oldPathValue = trim((string)$body['oldPath']);
        $oldPath = $oldPathValue === '' ? null : $oldPathValue;
    }
    
    $html = (string)($body['html'] ?? '');
    
    try {
        $cms->savePage($page, $html, $oldPath);
        gwa_json(['ok' => true]);
    } catch (InvalidArgumentException $e) {
        gwa_json(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (RuntimeException $e) {
        gwa_json(['ok' => false, 'error' => '儲存失敗：' . $e->getMessage()], 500);
    } catch (Throwable $e) {
        gwa_json(['ok' => false, 'error' => '發生錯誤：' . $e->getMessage()], 500);
    }
}

if ($action === 'delete_page' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $body = read_json_body();
    $path = (string)($body['path'] ?? '');
    
    // 禁止刪除主頁（home），因為它是系統必需的
    if ($path === 'home') {
        gwa_json(['ok' => false, 'error' => '主頁（home）不允許刪除，因為它是系統必需的頁面'], 400);
        return;
    }
    
    $cms->deletePage($path);
    gwa_json(['ok' => true]);
}

if ($action === 'trash_list' && $method === 'GET') {
    $auth->requireLoggedInJson();
    $trash = $cms->getTrash();
    gwa_json(['ok' => true, 'trash' => $trash]);
}

if ($action === 'restore_page' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $body = read_json_body();
    $trashPath = (string)($body['trash_path'] ?? '');
    
    if ($trashPath === '') {
        gwa_json(['ok' => false, 'error' => '缺少 trash_path'], 400);
        return;
    }
    
    $cms->restorePage($trashPath);
    gwa_json(['ok' => true]);
}

if ($action === 'upload_image' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    if (!isset($_FILES['image'])) {
        gwa_json(['ok' => false, 'error' => '缺少 image'], 400);
    }
    $file = $_FILES['image'];
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        gwa_json(['ok' => false, 'error' => '上傳失敗'], 400);
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        gwa_json(['ok' => false, 'error' => '上傳暫存檔不存在'], 400);
    }

    // 檢查檔案大小（限制為 10MB）
    $maxSize = 10 * 1024 * 1024; // 10MB
    $fileSize = filesize($tmp);
    if ($fileSize === false || $fileSize > $maxSize) {
        gwa_json(['ok' => false, 'error' => '圖片檔案過大（最大 10MB）'], 400);
    }

    // 某些 Windows PHP 發行版可能未啟用 fileinfo（finfo_*），改用 getimagesize 的 mime
    $imgInfo = @getimagesize($tmp);
    if ($imgInfo === false) {
        gwa_json(['ok' => false, 'error' => '圖片驗證失敗'], 400);
    }
    $mime = (string)($imgInfo['mime'] ?? '');
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        gwa_json(['ok' => false, 'error' => '不支援的圖片格式'], 400);
    }

    $pagePath = (string)($_POST['page_path'] ?? '');
    $pagePath = $pagePath === '' ? '' : gwa_sanitize_path($pagePath);
    $subDir = $pagePath === '' ? '' : (str_replace('/', DIRECTORY_SEPARATOR, $pagePath) . DIRECTORY_SEPARATOR);

    $imagesDir = $cms->getContentDirAbsolute() . 'images' . DIRECTORY_SEPARATOR . $subDir;
    gwa_mkdirp($imagesDir);
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $dest = $imagesDir . $filename;

    if (!move_uploaded_file($tmp, $dest)) {
        gwa_json(['ok' => false, 'error' => '儲存圖片失敗'], 500);
    }

    $url = $basePath . 'content/images/' . ($pagePath === '' ? '' : ($pagePath . '/')) . $filename;
    gwa_json(['ok' => true, 'url' => $url]);
}

if ($action === 'create_random_example_page' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();

    $parent = '';
    $now = date('Y-m-d H:i:s');
    $rand = bin2hex(random_bytes(4));
    $name = 'Random Dump ' . $rand;
    $title = 'Random Dump Example (' . $rand . ')';
    $slug = 'dump-' . date('Ymd-His') . '-' . $rand;

    // 盡量避免撞名（最多重試 5 次）
    $pages = $cms->getPages();
    $existing = [];
    foreach ($pages as $p) $existing[(string)($p['path'] ?? '')] = true;
    $path = $slug;
    for ($i = 0; $i < 5; $i++) {
        if (!isset($existing[$path])) break;
        $path = $slug . '-' . ($i + 2);
    }

    $dump = [
        'ts' => $now,
        'rand' => $rand,
        'numbers' => [random_int(1, 999), random_int(1, 999), random_int(1, 999)],
        'flags' => ['a' => (bool)random_int(0,1), 'b' => (bool)random_int(0,1)],
        'note' => 'This is random dump example data generated by admin tool.',
    ];
    $dumpJson = json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($dumpJson === false) $dumpJson = '{}';

    $html = '<h1>' . htmlspecialchars($title, ENT_QUOTES) . '</h1>'
        . '<p>Generated at: <code>' . htmlspecialchars($now, ENT_QUOTES) . '</code></p>'
        . '<p>Menu name: <code>' . htmlspecialchars($name, ENT_QUOTES) . '</code></p>'
        . '<h2>Dump</h2>'
        . '<pre><code>' . htmlspecialchars($dumpJson, ENT_QUOTES) . '</code></pre>';

    $cms->savePage([
        'path' => $path,
        'title' => $title,
        'menu_title' => $name,
        'parent' => $parent,
    ], $html);

    gwa_json(['ok' => true, 'path' => $path]);
}

if ($action === 'create_child_page' && $method === 'POST') {
    $auth->requireLoggedInJson();
    $auth->requireCsrfFromRequest();
    $body = read_json_body();

    $parent = trim((string)($body['parent'] ?? ''));
    $parent = $parent === '' ? '' : gwa_sanitize_path($parent);
    if ($parent === 'home') $parent = '';

    $name = trim((string)($body['menu_title'] ?? '新子頁'));
    if ($name === '') $name = '新子頁';
    $title = trim((string)($body['title'] ?? $name));
    if ($title === '') $title = $name;

    $slug = strtolower($name);
    $slug = preg_replace('/[\s_]+/', '-', $slug) ?? '';
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug) ?? '';
    $slug = preg_replace('/-+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '' || $slug === 'home') {
        $slug = 'p-' . bin2hex(random_bytes(3));
    }

    $pages = $cms->getPages();
    $existing = [];
    foreach ($pages as $p) $existing[(string)($p['path'] ?? '')] = true;

    $base = $parent ? ($parent . '/' . $slug) : $slug;
    $path = $base;
    for ($i = 0; $i < 50; $i++) {
        if (!isset($existing[$path])) break;
        $path = $base . '-' . ($i + 2);
    }

    $html = '<h1>' . htmlspecialchars($title, ENT_QUOTES) . '</h1><p>（這是一個新建立的子頁）</p>';
    $cms->savePage([
        'path' => $path,
        'title' => $title,
        'menu_title' => $name,
        'parent' => $parent,
    ], $html);

    gwa_json(['ok' => true, 'path' => $path]);
}

gwa_json(['ok' => false, 'error' => '未知 action'], 400);



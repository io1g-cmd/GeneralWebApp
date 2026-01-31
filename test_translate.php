<?php
/**
 * 自動翻譯系統驗證腳本
 * 測試翻譯 API、語言設定、翻譯數據等功能
 */

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Settings.php';

$settings = new Settings(__DIR__);
$basePath = gwa_base_path();
$apiUrl = $basePath . 'api.php';

echo "=== 自動翻譯系統驗證 ===\n\n";

// 測試 1: 檢查語言設定
echo "【測試 1】檢查語言設定\n";
$languages = $settings->getLanguages();
$defaultLang = $settings->getDefaultLanguage();
echo "預設語言: {$defaultLang}\n";
echo "已設定的語言:\n";
foreach ($languages as $lang => $info) {
    $native = $info['native'] ?? false;
    $name = $info['name'] ?? $lang;
    echo "  - {$lang}: {$name} " . ($native ? '(原生)' : '(需翻譯)') . "\n";
}
echo "\n";

// 測試 2: 檢查翻譯數據
echo "【測試 2】檢查翻譯數據\n";
$translations = $settings->getTranslations();
if (empty($translations)) {
    echo "  警告: 沒有設定任何翻譯數據\n";
} else {
    foreach ($translations as $lang => $langTranslations) {
        $count = count($langTranslations);
        echo "  {$lang}: {$count} 個翻譯項目\n";
    }
}
echo "\n";

// 測試 3: 測試翻譯邏輯（直接測試 LibreTranslate API）
echo "【測試 3】測試翻譯邏輯（LibreTranslate API）\n";

function testLibreTranslate(string $text, string $sourceLang, string $targetLang): array {
    // 語言代碼映射
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
    
    // 如果源語言和目標語言相同，直接返回原文
    if ($sourceLang === $targetLang) {
        return ['ok' => true, 'translated' => $text, 'source' => 'none'];
    }
    
    $apiUrl = 'https://libretranslate.de/translate';
    $requestData = [
        'q' => $text,
        'source' => $libreSourceCode,
        'target' => $libreTargetCode,
        'format' => 'text'
    ];
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // 跟隨重定向
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 在測試環境中允許自簽名證書
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    if ($curlErrno !== 0 || $curlError !== '') {
        return ['ok' => false, 'error' => "cURL 錯誤: {$curlError}", 'http_code' => $httpCode];
    }
    
    if ($response === false) {
        return ['ok' => false, 'error' => 'API 無響應', 'http_code' => $httpCode];
    }
    
    if ($httpCode !== 200) {
        return ['ok' => false, 'error' => "HTTP {$httpCode}", 'http_code' => $httpCode, 'raw' => substr($response, 0, 200)];
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // 顯示響應的前 500 個字符以便調試
        $preview = substr($response, 0, 500);
        return ['ok' => false, 'error' => 'JSON 解析失敗: ' . json_last_error_msg(), 'http_code' => $httpCode, 'raw' => $preview, 'response_length' => strlen($response)];
    }
    
    if (!isset($result['translatedText']) || !is_string($result['translatedText'])) {
        return ['ok' => false, 'error' => '響應格式異常，缺少 translatedText 字段', 'http_code' => $httpCode, 'raw' => substr($response, 0, 200)];
    }
    
    $translated = trim($result['translatedText']);
    if ($translated === '') {
        return ['ok' => false, 'error' => '翻譯結果為空', 'http_code' => $httpCode];
    }
    
    return ['ok' => true, 'translated' => $translated, 'source' => 'api', 'http_code' => $httpCode];
}

$testCases = [
    ['text' => '首頁', 'source' => 'zh-TW', 'target' => 'en'],
    ['text' => '歡迎', 'source' => 'zh-TW', 'target' => 'en'],
    ['text' => '購物車', 'source' => 'zh-TW', 'target' => 'en'],
];

$successCount = 0;
foreach ($testCases as $i => $test) {
    echo "  測試案例 " . ($i + 1) . ": \"{$test['text']}\" ({$test['source']} → {$test['target']})\n";
    $result = testLibreTranslate($test['text'], $test['source'], $test['target']);
    
    if ($result['ok'] ?? false) {
        $translated = $result['translated'] ?? '';
        $source = $result['source'] ?? 'unknown';
        echo "    ✓ 成功\n";
        echo "    原文: {$test['text']}\n";
        echo "    翻譯: {$translated}\n";
        echo "    來源: {$source}\n";
        $successCount++;
    } else {
        $error = $result['error'] ?? '未知錯誤';
        $httpCode = $result['http_code'] ?? 0;
        echo "    ✗ 失敗";
        if ($httpCode > 0) {
            echo " (HTTP {$httpCode})";
        }
        echo "\n";
        echo "    錯誤: {$error}\n";
        if (isset($result['raw']) && $result['raw']) {
            echo "    響應預覽: " . substr($result['raw'], 0, 150) . "...\n";
        }
        if (isset($result['response_length'])) {
            echo "    響應長度: {$result['response_length']} bytes\n";
        }
    }
    echo "\n";
}

if ($successCount === count($testCases)) {
    echo "  ✓ 所有翻譯測試通過\n";
} elseif ($successCount > 0) {
    echo "  ⚠ 部分翻譯測試通過 ({$successCount}/" . count($testCases) . ")\n";
} else {
    echo "  ✗ 所有翻譯測試失敗\n";
}
echo "\n";

// 測試 4: 檢查 API 代碼邏輯
echo "【測試 4】檢查 API 代碼邏輯\n";
$apiFile = __DIR__ . DIRECTORY_SEPARATOR . 'api.php';
if (!is_file($apiFile)) {
    echo "  ✗ API 文件不存在: {$apiFile}\n";
} else {
    echo "  ✓ API 文件存在\n";
    
    // 檢查關鍵函數和邏輯
    $apiContent = file_get_contents($apiFile);
    if ($apiContent === false) {
        echo "  ✗ 無法讀取 API 文件\n";
    } else {
        $checks = [
            'translate action' => strpos($apiContent, "action === 'translate'") !== false,
            'LibreTranslate API' => strpos($apiContent, 'libretranslate.de') !== false,
            '語言映射' => strpos($apiContent, 'libreLangMap') !== false,
            '錯誤處理' => strpos($apiContent, 'error_log') !== false,
            'JSON 響應' => strpos($apiContent, 'gwa_json') !== false,
        ];
        
        foreach ($checks as $check => $passed) {
            echo "    " . ($passed ? '✓' : '✗') . " {$check}\n";
        }
    }
}
echo "\n";

// 測試 5: 檢查前端翻譯代碼
echo "【測試 5】檢查前端翻譯代碼\n";
$indexFile = __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
if (!is_file($indexFile)) {
    echo "  ✗ index.php 文件不存在\n";
} else {
    echo "  ✓ index.php 文件存在\n";
    
    $indexContent = file_get_contents($indexFile);
    if ($indexContent === false) {
        echo "  ✗ 無法讀取 index.php 文件\n";
    } else {
        $checks = [
            'translateText 函數' => strpos($indexContent, 'function translateText') !== false,
            'applyTranslations 函數' => strpos($indexContent, 'function applyTranslations') !== false,
            '語言選擇器' => strpos($indexContent, 'languageSelector') !== false,
            '翻譯緩存' => strpos($indexContent, 'translationCache') !== false,
            'LibreTranslate API 調用' => strpos($indexContent, 'action=translate') !== false,
        ];
        
        foreach ($checks as $check => $passed) {
            echo "    " . ($passed ? '✓' : '✗') . " {$check}\n";
        }
    }
}
echo "\n";

// 測試 6: 檢查數據目錄和文件
echo "【測試 6】檢查數據目錄和文件\n";
$dataDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
$settingsFile = $dataDir . DIRECTORY_SEPARATOR . 'settings.json';

if (!is_dir($dataDir)) {
    echo "  ✗ 數據目錄不存在: {$dataDir}\n";
} else {
    echo "  ✓ 數據目錄存在\n";
}

if (!is_file($settingsFile)) {
    echo "  ⚠ 設定文件不存在: {$settingsFile}\n";
    echo "    將在首次使用時自動創建\n";
} else {
    echo "  ✓ 設定文件存在\n";
    $fileSize = filesize($settingsFile);
    echo "    文件大小: {$fileSize} bytes\n";
    
    // 檢查文件內容
    $content = file_get_contents($settingsFile);
    if ($content === false) {
        echo "  ✗ 無法讀取設定文件\n";
    } else {
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "  ✗ 設定文件 JSON 格式錯誤: " . json_last_error_msg() . "\n";
        } else {
            echo "  ✓ 設定文件格式正確\n";
        }
    }
}
echo "\n";

// 總結
echo "=== 驗證完成 ===\n";
echo "\n建議檢查項目:\n";
echo "1. 確保 LibreTranslate API (https://libretranslate.de) 可訪問\n";
echo "2. 檢查語言設定是否正確配置\n";
echo "3. 如有需要，在管理後台設定翻譯覆蓋數據\n";
echo "4. 測試前端語言切換功能\n";


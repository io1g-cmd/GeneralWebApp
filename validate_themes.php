<?php
declare(strict_types=1);

/**
 * 主題樣式驗證與自動修正工具
 * 確保所有主題的編輯器和前端樣式變量一致
 */

$themesDir = __DIR__ . '/assets/themes';
$requiredVars = [
    '--editor-bg',
    '--editor-text', 
    '--page-bg',
    '--page-shadow',
    '--editor-toolbar-bg'
];

$themes = glob($themesDir . '/*.css');
$results = [];

foreach ($themes as $themeFile) {
    $themeName = basename($themeFile, '.css');
    $content = file_get_contents($themeFile);
    $issues = [];
    $fixes = [];
    
    // 檢查必需的變量
    foreach ($requiredVars as $var) {
        if (!preg_match('/' . preg_quote($var, '/') . '\s*:/', $content)) {
            $issues[] = "缺少變量: {$var}";
            
            // 自動生成缺失的變量
            $defaultValue = match($var) {
                '--editor-bg' => 'var(--bg)',
                '--editor-text' => 'var(--text)',
                '--page-bg' => 'var(--editor-bg, var(--bg))',
                '--page-shadow' => 'var(--shadow)',
                '--editor-toolbar-bg' => 'var(--surface)',
                default => 'inherit'
            };
            
            // 如果主題有 --text，使用它；否則使用默認值
            if ($var === '--editor-text' && preg_match('/--text:\s*([^;]+);/', $content, $matches)) {
                $defaultValue = 'var(--text)';
            }
            if ($var === '--editor-bg' && preg_match('/--bg:\s*([^;]+);/', $content, $matches)) {
                $defaultValue = 'var(--bg)';
            }
            if ($var === '--page-bg' && preg_match('/--editor-bg:\s*([^;]+);/', $content, $matches)) {
                $defaultValue = 'var(--editor-bg, var(--bg))';
            }
            
            $fixes[] = "  {$var}:{$defaultValue};";
        }
    }
    
    // 檢查 --editor-text 是否正確引用
    if (preg_match('/--editor-text:\s*([^;]+);/', $content, $matches)) {
        $value = trim($matches[1]);
        if ($value !== 'var(--text)' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) && !preg_match('/^rgba?\(/', $value)) {
            $issues[] = "--editor-text 值不標準: {$value}";
            $fixes[] = "  --editor-text:var(--text);";
        }
    }
    
    // 檢查 --page-bg 是否與 --editor-bg 一致（應該相似但不完全相同）
    if (preg_match('/--editor-bg:\s*([^;]+);/', $content, $editorMatches) &&
        preg_match('/--page-bg:\s*([^;]+);/', $content, $pageMatches)) {
        $editorBg = trim($editorMatches[1]);
        $pageBg = trim($pageMatches[1]);
        
        // 如果兩者完全相同，自動修正為使用 var(--editor-bg)
        if ($editorBg === $pageBg && !str_contains($pageBg, 'var(--editor-bg)')) {
            $issues[] = "--page-bg 與 --editor-bg 相同，已自動修正為 var(--editor-bg)";
            // 自動修正
            $content = preg_replace(
                '/--page-bg:\s*' . preg_quote($pageBg, '/') . ';/',
                '--page-bg:var(--editor-bg);',
                $content
            );
            $fixesApplied = ($fixesApplied ?? 0) + 1;
        }
    }
    
    // 應用其他修正（缺失變量）
    if (!empty($fixes)) {
        // 找到 :root{ 的位置
        if (preg_match('/:root\s*\{/', $content, $rootMatch, PREG_OFFSET_CAPTURE)) {
            $insertPos = $rootMatch[0][1] + strlen($rootMatch[0][0]);
            
            // 找到第一個 } 的位置（:root 的結束）
            $rootEnd = strpos($content, '}', $insertPos);
            
            if ($rootEnd !== false) {
                // 在 :root 塊內插入缺失的變量
                $before = substr($content, 0, $rootEnd);
                $after = substr($content, $rootEnd);
                
                // 檢查是否已有換行
                $needsNewline = !preg_match('/\n\s*$/', $before);
                $newVars = ($needsNewline ? "\n" : '') . implode("\n", $fixes) . "\n";
                
                $content = $before . $newVars . $after;
                file_put_contents($themeFile, $content);
                $fixesApplied = count($fixes);
            }
        }
    }
    
    // 保存修正後的內容
    if (isset($fixesApplied) && $fixesApplied > 0) {
        file_put_contents($themeFile, $content);
    }
    
    $results[$themeName] = [
        'file' => $themeFile,
        'issues' => $issues,
        'fixes_applied' => $fixesApplied ?? 0,
        'status' => empty($issues) && empty($fixes) ? 'ok' : (empty($issues) ? 'fixed' : 'has_issues')
    ];
}

// 輸出結果
echo "主題驗證報告\n";
echo str_repeat('=', 60) . "\n\n";

foreach ($results as $themeName => $result) {
    $status = match($result['status']) {
        'ok' => '✓ 正常',
        'fixed' => '✓ 已修正',
        'has_issues' => '✗ 有問題',
        default => '? 未知'
    };
    
    echo "主題: {$themeName} - {$status}\n";
    
    if (!empty($result['issues'])) {
        echo "  問題:\n";
        foreach ($result['issues'] as $issue) {
            echo "    - {$issue}\n";
        }
    }
    
    if ($result['fixes_applied'] > 0) {
        echo "  已自動修正 {$result['fixes_applied']} 個問題\n";
    }
    
    echo "\n";
}

echo "\n驗證完成！\n";


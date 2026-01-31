<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Cms.php';
require_once __DIR__ . '/app/Settings.php';
require_once __DIR__ . '/app/GoogleFonts.php';
require_once __DIR__ . '/app/Auth.php';

$basePath = gwa_base_path();
$cms = new Cms(__DIR__);
$settings = new Settings(__DIR__);
$auth = new Auth(__DIR__);
$theme = $settings->getTheme();
$brand = $settings->getBrand();
$footer = $settings->getFooter();
$typography = $settings->getTypography();
$currencySymbol = $settings->getCurrencySymbol();

// 檢查維護模式
$maintenanceMode = $settings->getMaintenanceMode();
$isAdmin = $auth->isLoggedIn();
if ($maintenanceMode && !$isAdmin) {
    // 顯示維護頁面
    header('Content-Type: text/html; charset=UTF-8');
    http_response_code(503); // Service Unavailable
    $logoUrl = !empty($brand['logo']) ? $basePath . $brand['logo'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Upgrade in Progress</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #fff;
            transition: background 1s ease, color 1s ease;
        }
        .maintenance-container {
            text-align: center;
            max-width: 600px;
            animation: fadeIn 0.8s ease-in;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .logo-container {
            margin-bottom: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .logo-container img {
            max-width: 300px;
            max-height: 120px;
            object-fit: contain;
            filter: drop-shadow(0 4px 20px rgba(0,0,0,0.3));
            animation: logoFadeIn 1s ease-in;
        }
        @keyframes logoFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        .icon {
            font-size: 80px;
            margin-bottom: 30px;
            animation: pulse 2s ease-in-out infinite;
        }
        .logo-container.has-logo .icon {
            display: none;
        }
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }
        h1 {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 20px;
            letter-spacing: -1px;
        }
        .subtitle {
            font-size: 20px;
            margin-bottom: 40px;
            opacity: 0.9;
            line-height: 1.6;
        }
        .message {
            font-size: 16px;
            line-height: 1.8;
            opacity: 0.85;
            margin-bottom: 30px;
        }
        .spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-top: 20px;
        }
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        .footer {
            margin-top: 50px;
            font-size: 14px;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="logo-container">
            <?php if ($logoUrl): ?>
                <img id="logoImg" src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($brand['title']); ?>" style="display: none;">
            <?php endif; ?>
            <div class="icon">🔧</div>
        </div>
        <h1>System Upgrade in Progress</h1>
        <p class="subtitle">We're currently performing scheduled maintenance</p>
        <p class="message">
            Our website is temporarily unavailable while we upgrade our systems to serve you better.
            <br>We'll be back online shortly. Thank you for your patience.
        </p>
        <div class="spinner"></div>
        <div class="footer">
            <p>Please check back soon</p>
        </div>
    </div>
    <script>
        (function() {
            const logoImg = document.getElementById('logoImg');
            if (!logoImg) return;
            
            // 從圖片提取四角顏色
            function extractCornerColors(img) {
                return new Promise((resolve, reject) => {
                    try {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        
                        // 確保使用實際圖片尺寸
                        const imgWidth = img.naturalWidth || img.width;
                        const imgHeight = img.naturalHeight || img.height;
                        
                        if (!imgWidth || !imgHeight) {
                            console.warn('[維護模式] 無法獲取圖片尺寸');
                            reject(new Error('無法獲取圖片尺寸'));
                            return;
                        }
                        
                        canvas.width = imgWidth;
                        canvas.height = imgHeight;
                        
                        // 繪製圖片到 Canvas
                        try {
                            ctx.drawImage(img, 0, 0);
                        } catch (drawError) {
                            console.error('[維護模式] 繪製圖片失敗（可能是 CORS 問題）:', drawError);
                            reject(drawError);
                            return;
                        }
                        
                        const w = canvas.width;
                        const h = canvas.height;
                        const sampleSize = Math.max(1, Math.floor(Math.min(w, h) * 0.1)); // 取 10% 區域
                        
                        // 提取四角顏色
                        let topLeft, topRight, bottomLeft, bottomRight;
                        try {
                            topLeft = ctx.getImageData(0, 0, sampleSize, sampleSize);
                            topRight = ctx.getImageData(w - sampleSize, 0, sampleSize, sampleSize);
                            bottomLeft = ctx.getImageData(0, h - sampleSize, sampleSize, sampleSize);
                            bottomRight = ctx.getImageData(w - sampleSize, h - sampleSize, sampleSize, sampleSize);
                        } catch (getDataError) {
                            console.error('[維護模式] 讀取像素數據失敗（可能是 CORS 問題）:', getDataError);
                            reject(getDataError);
                            return;
                        }
                        
                        function getAverageColor(imageData) {
                            let r = 0, g = 0, b = 0, count = 0;
                            const data = imageData.data;
                            for (let i = 0; i < data.length; i += 4) {
                                const alpha = data[i + 3];
                                if (alpha > 10) { // 忽略幾乎透明的像素（閾值提高）
                                    r += data[i];
                                    g += data[i + 1];
                                    b += data[i + 2];
                                    count++;
                                }
                            }
                            if (count === 0) {
                                // 如果沒有有效像素，返回 null 讓調用者處理
                                return null;
                            }
                            return {
                                r: Math.round(r / count),
                                g: Math.round(g / count),
                                b: Math.round(b / count)
                            };
                        }
                        
                        const colors = {
                            topLeft: getAverageColor(topLeft),
                            topRight: getAverageColor(topRight),
                            bottomLeft: getAverageColor(bottomLeft),
                            bottomRight: getAverageColor(bottomRight)
                        };
                        
                        // 檢查是否有任何顏色提取失敗
                        const hasNull = Object.values(colors).some(c => c === null);
                        if (hasNull) {
                            console.warn('[維護模式] 部分顏色提取失敗，使用備用方案');
                            // 使用圖片中心區域作為備用
                            const centerSize = Math.max(1, Math.floor(Math.min(w, h) * 0.2));
                            const centerX = Math.floor((w - centerSize) / 2);
                            const centerY = Math.floor((h - centerSize) / 2);
                            try {
                                const center = ctx.getImageData(centerX, centerY, centerSize, centerSize);
                                const centerColor = getAverageColor(center);
                                if (centerColor) {
                                    // 用中心顏色填充缺失的角落
                                    if (!colors.topLeft) colors.topLeft = centerColor;
                                    if (!colors.topRight) colors.topRight = centerColor;
                                    if (!colors.bottomLeft) colors.bottomLeft = centerColor;
                                    if (!colors.bottomRight) colors.bottomRight = centerColor;
                                }
                            } catch (e) {
                                console.error('[維護模式] 備用顏色提取也失敗:', e);
                            }
                        }
                        
                        // 最終檢查：如果所有顏色都失敗，使用默認顏色
                        const finalColors = {
                            topLeft: colors.topLeft || { r: 102, g: 126, b: 234 },
                            topRight: colors.topRight || { r: 102, g: 126, b: 234 },
                            bottomLeft: colors.bottomLeft || { r: 102, g: 126, b: 234 },
                            bottomRight: colors.bottomRight || { r: 102, g: 126, b: 234 }
                        };
                        
                        // 調試輸出
                        console.log('[維護模式] 提取的顏色:', finalColors);
                        
                        resolve(finalColors);
                    } catch (error) {
                        console.error('[維護模式] 顏色提取過程出錯:', error);
                        reject(error);
                    }
                });
            }
            
            // 計算對比色（確保文字可讀性）
            function getContrastColor(rgb) {
                // 計算亮度
                const luminance = (0.299 * rgb.r + 0.587 * rgb.g + 0.114 * rgb.b) / 255;
                // 如果背景較亮，使用深色文字；否則使用淺色文字
                return luminance > 0.5 ? { r: 20, g: 20, b: 30 } : { r: 255, g: 255, b: 255 };
            }
            
            // 獲取主色調（四角的平均色）
            function getDominantColor(corners) {
                const avg = {
                    r: Math.round((corners.topLeft.r + corners.topRight.r + corners.bottomLeft.r + corners.bottomRight.r) / 4),
                    g: Math.round((corners.topLeft.g + corners.topRight.g + corners.bottomLeft.g + corners.bottomRight.g) / 4),
                    b: Math.round((corners.topLeft.b + corners.topRight.b + corners.bottomLeft.b + corners.bottomRight.b) / 4)
                };
                return avg;
            }
            
            // RGB 轉 HEX
            function rgbToHex(r, g, b) {
                return '#' + [r, g, b].map(x => {
                    const hex = x.toString(16);
                    return hex.length === 1 ? '0' + hex : hex;
                }).join('');
            }
            
            // 應用顏色（豪華升級：四角顏色獨立從 logo 位置輻射，支持 logo 融入背景）
            function applyColors(corners) {
                const dominant = getDominantColor(corners);
                const textColor = getContrastColor(dominant);
                
                // 獲取 logo 位置和尺寸（相對於視窗）
                const logoRect = logoImg.getBoundingClientRect();
                const logoCenterX = (logoRect.left + logoRect.width / 2) / window.innerWidth * 100;
                const logoCenterY = (logoRect.top + logoRect.height / 2) / window.innerHeight * 100;
                
                // 計算視窗對角線長度（用於確定漸層範圍）
                const viewportDiagonal = Math.sqrt(window.innerWidth * window.innerWidth + window.innerHeight * window.innerHeight);
                const maxRadius = viewportDiagonal * 1.2; // 120% 的對角線長度，確保覆蓋整個視窗
                
                // 轉換顏色為帶透明度的格式（用於漸層融合）
                function rgbToRgba(r, g, b, a = 1) {
                    return `rgba(${r}, ${g}, ${b}, ${a})`;
                }
                
                const topLeftColor = rgbToHex(corners.topLeft.r, corners.topLeft.g, corners.topLeft.b);
                const topRightColor = rgbToHex(corners.topRight.r, corners.topRight.g, corners.topRight.b);
                const bottomRightColor = rgbToHex(corners.bottomRight.r, corners.bottomRight.g, corners.bottomRight.b);
                const bottomLeftColor = rgbToHex(corners.bottomLeft.r, corners.bottomLeft.g, corners.bottomLeft.b);
                
                // 計算中心顏色（logo 位置的平均色，用於起始點，稍微淡化以支持 logo 融入）
                const centerR = Math.round((corners.topLeft.r + corners.topRight.r + corners.bottomLeft.r + corners.bottomRight.r) / 4);
                const centerG = Math.round((corners.topLeft.g + corners.topRight.g + corners.bottomLeft.g + corners.bottomRight.g) / 4);
                const centerB = Math.round((corners.topLeft.b + corners.topRight.b + corners.bottomLeft.b + corners.bottomRight.b) / 4);
                const centerColor = rgbToRgba(centerR, centerG, centerB, 0.95);
                
                // 創建多層 radial-gradient，每個從 logo 中心向對應角落方向獨立輻射
                // 使用不同的半徑和透明度創建自然的融合效果，讓 logo 可以融入背景
                const gradients = [
                    // 左上角顏色從 logo 中心向左上方向輻射
                    `radial-gradient(ellipse ${maxRadius * 0.7}px ${maxRadius * 0.7}px at ${logoCenterX}% ${logoCenterY}%, 
                        ${centerColor} 0%, 
                        ${rgbToRgba(corners.topLeft.r, corners.topLeft.g, corners.topLeft.b, 0.85)} 15%,
                        ${rgbToRgba(corners.topLeft.r, corners.topLeft.g, corners.topLeft.b, 0.65)} 35%,
                        ${rgbToRgba(corners.topLeft.r, corners.topLeft.g, corners.topLeft.b, 0.4)} 60%,
                        ${rgbToRgba(corners.topLeft.r, corners.topLeft.g, corners.topLeft.b, 0.2)} 85%,
                        transparent 100%)`,
                    
                    // 右上角顏色從 logo 中心向右上方向輻射
                    `radial-gradient(ellipse ${maxRadius * 0.7}px ${maxRadius * 0.7}px at ${logoCenterX}% ${logoCenterY}%, 
                        ${centerColor} 0%, 
                        ${rgbToRgba(corners.topRight.r, corners.topRight.g, corners.topRight.b, 0.85)} 15%,
                        ${rgbToRgba(corners.topRight.r, corners.topRight.g, corners.topRight.b, 0.65)} 35%,
                        ${rgbToRgba(corners.topRight.r, corners.topRight.g, corners.topRight.b, 0.4)} 60%,
                        ${rgbToRgba(corners.topRight.r, corners.topRight.g, corners.topRight.b, 0.2)} 85%,
                        transparent 100%)`,
                    
                    // 右下角顏色從 logo 中心向右下方向輻射
                    `radial-gradient(ellipse ${maxRadius * 0.7}px ${maxRadius * 0.7}px at ${logoCenterX}% ${logoCenterY}%, 
                        ${centerColor} 0%, 
                        ${rgbToRgba(corners.bottomRight.r, corners.bottomRight.g, corners.bottomRight.b, 0.85)} 15%,
                        ${rgbToRgba(corners.bottomRight.r, corners.bottomRight.g, corners.bottomRight.b, 0.65)} 35%,
                        ${rgbToRgba(corners.bottomRight.r, corners.bottomRight.g, corners.bottomRight.b, 0.4)} 60%,
                        ${rgbToRgba(corners.bottomRight.r, corners.bottomRight.g, corners.bottomRight.b, 0.2)} 85%,
                        transparent 100%)`,
                    
                    // 左下角顏色從 logo 中心向左下方向輻射
                    `radial-gradient(ellipse ${maxRadius * 0.7}px ${maxRadius * 0.7}px at ${logoCenterX}% ${logoCenterY}%, 
                        ${centerColor} 0%, 
                        ${rgbToRgba(corners.bottomLeft.r, corners.bottomLeft.g, corners.bottomLeft.b, 0.85)} 15%,
                        ${rgbToRgba(corners.bottomLeft.r, corners.bottomLeft.g, corners.bottomLeft.b, 0.65)} 35%,
                        ${rgbToRgba(corners.bottomLeft.r, corners.bottomLeft.g, corners.bottomLeft.b, 0.4)} 60%,
                        ${rgbToRgba(corners.bottomLeft.r, corners.bottomLeft.g, corners.bottomLeft.b, 0.2)} 85%,
                        transparent 100%)`,
                    
                    // 基礎背景色（使用主色調的深色版本，確保覆蓋整個視窗並提供基礎色調）
                    `linear-gradient(135deg, 
                        ${rgbToRgba(centerR, centerG, centerB, 0.25)} 0%, 
                        ${rgbToRgba(centerR, centerG, centerB, 0.4)} 50%,
                        ${rgbToRgba(centerR, centerG, centerB, 0.25)} 100%)`
                ];
                
                // 應用多層漸層（從上到下疊加，創建深度和融合效果）
                document.body.style.background = gradients.join(', ');
                document.body.style.color = `rgb(${textColor.r}, ${textColor.g}, ${textColor.b})`;
                
                // 更新 spinner 顏色
                const spinner = document.querySelector('.spinner');
                if (spinner) {
                    spinner.style.borderTopColor = 'currentColor';
                }
                
                // 優化 logo 顯示效果，支持融入背景（減少陰影，增加透明度過渡）
                logoImg.style.filter = 'drop-shadow(0 2px 12px rgba(0,0,0,0.15))';
                logoImg.style.transition = 'filter 0.3s ease';
            }
            
            // 當圖片載入完成後提取顏色
            const logoContainer = document.querySelector('.logo-container');
            function processLogo() {
                if (logoImg.naturalWidth > 0 && logoImg.naturalHeight > 0) {
                    logoImg.style.display = 'block';
                    logoContainer.classList.add('has-logo');
                    
                    // 等待一小段時間確保 DOM 已更新，然後提取顏色
                    setTimeout(() => {
                        extractCornerColors(logoImg)
                            .then(applyColors)
                            .catch(error => {
                                console.error('[維護模式] 顏色提取失敗，使用默認樣式:', error);
                                // 保持默認樣式，不改變背景
                            });
                    }, 100);
                }
            }
            
            // 處理 CORS：如果圖片是跨域的，嘗試移除 crossorigin 屬性或使用代理
            // 首先嘗試不設置 crossorigin（適用於同域圖片）
            if (logoImg.complete && logoImg.naturalWidth > 0 && logoImg.naturalHeight > 0) {
                processLogo();
            } else {
                logoImg.onload = function() {
                    // 如果圖片載入成功但可能是跨域的，嘗試處理
                    if (logoImg.naturalWidth > 0 && logoImg.naturalHeight > 0) {
                        processLogo();
                    }
                };
                logoImg.onerror = function() {
                    // 如果圖片載入失敗，保持默認樣式
                    console.warn('[維護模式] Logo 圖片載入失敗');
                    logoContainer.classList.remove('has-logo');
                };
            }
            
            // 監聽視窗大小變化，重新計算漸層位置
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    if (logoImg.naturalWidth > 0) {
                        extractCornerColors(logoImg).then(applyColors);
                    }
                }, 300);
            });
        })();
    </script>
</body>
</html>
<?php
    exit;
}

// sitemap.xml（保留 SEO 能力）
$uriPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$uriPath = $uriPath === null ? '/' : (string)$uriPath;
$rel = $uriPath;
if ($basePath !== '/' && gwa_starts_with($rel, $basePath)) {
    $rel = substr($rel, strlen($basePath));
}
$rel = ltrim($rel, '/');
if ($rel === 'sitemap.xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    $pages = $cms->getPages();
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    foreach ($pages as $p) {
        $path = (string)($p['path'] ?? 'home');
        $locPath = $cms->publicUrl($basePath, $path);
        $loc = $scheme . '://' . $host . $locPath;
        $contentFile = $cms->contentPath($path);
        $lastmod = is_file($contentFile) ? date('c', filemtime($contentFile)) : date('c');
        echo '<url><loc>' . htmlspecialchars($loc, ENT_QUOTES) . '</loc><lastmod>' . $lastmod . "</lastmod></url>\n";
    }
    echo "</urlset>";
    exit;
}

$currentPath = gwa_request_path($basePath);
$pages = $cms->getPages();
$pageMeta = $cms->getPage($currentPath);
$pageTitle = $pageMeta ? (string)($pageMeta['title'] ?? 'GeneralWebApp') : 'GeneralWebApp';
$contentHtml = $cms->getContentHtml($currentPath);
$tree = $cms->buildTree($pages);
$breadcrumbs = $cms->buildBreadcrumbs($pages, $currentPath);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$canonical = $scheme . '://' . $host . $cms->publicUrl($basePath, $currentPath);

function render_nav(array $nodes, Cms $cms, string $basePath, string $currentPath): void {
    echo "<ul class=\"nav-list\">";
    foreach ($nodes as $n) {
        $d = (array)($n['data'] ?? []);
        $path = (string)($d['path'] ?? 'home');
        $menuTitle = trim((string)($d['menu_title'] ?? ''));
        $title = trim((string)($d['title'] ?? ''));
        $label = $menuTitle !== '' ? $menuTitle : ($title !== '' ? $title : $path);
        $url = $cms->publicUrl($basePath, $path);
        $active = $path === $currentPath ? 'active' : '';
        $hasChildren = !empty($n['children']);
        // 儲存搜尋用的資料：menu_title, title, path
        $searchData = htmlspecialchars(json_encode([
            'menu_title' => $menuTitle,
            'title' => $title,
            'path' => $path,
            'label' => $label
        ], JSON_UNESCAPED_UNICODE));
        echo "<li class=\"nav-item" . ($hasChildren ? " has-children" : "") . "\">";
        echo "<a class=\"nav-link $active\" href=\"" . htmlspecialchars($url) . "\" data-path=\"" . htmlspecialchars($path) . "\" data-search=\"" . $searchData . "\" data-has-children=\"" . ($hasChildren ? "1" : "0") . "\">";
        echo "<span class=\"nav-link-text\">" . htmlspecialchars($label) . "</span>";
        if ($hasChildren) {
            echo "<span class=\"nav-expand-icon\">";
            echo "<svg width=\"10\" height=\"10\" viewBox=\"0 0 10 10\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\">";
            echo "<path d=\"M3 3.5L5 5.5L7 3.5\"/>";
            echo "</svg>";
            echo "</span>";
        }
        echo "</a>";
        if ($hasChildren) {
            echo "<ul class=\"nav-list nav-children\">";
            render_nav((array)$n['children'], $cms, $basePath, $currentPath);
            echo "</ul>";
        }
        echo "</li>";
    }
    echo "</ul>";
}

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical); ?>">
    <?php if (!empty($brand['icon'])): ?>
        <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($basePath . $brand['icon']); ?>">
    <?php else: ?>
        <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($basePath . 'favicon.ico'); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($basePath . 'assets/base.css'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($basePath . 'assets/site.css'); ?>">
    <link rel="stylesheet" id="themeCss" href="<?php echo htmlspecialchars($basePath . 'assets/themes/' . $theme . '.css'); ?>">
    <?php echo GoogleFonts::linkTag($typography); ?>
    <?php
    $typo = $typography;
    $sel = ['normal' => '.content', 'h1' => '.content h1', 'h2' => '.content h2', 'h3' => '.content h3'];
    $buf = [];
    foreach ($sel as $key => $selector) {
        $t = $typo[$key] ?? [];
        $fam = trim((string)($t['fontFamily'] ?? ''));
        $sz = trim((string)($t['fontSize'] ?? ''));
        $col = trim((string)($t['color'] ?? ''));
        $w = trim((string)($t['fontWeight'] ?? ''));
        if ($fam !== '' || $sz !== '' || $col !== '' || $w !== '') {
            $decl = [];
            if ($fam !== '') $decl[] = 'font-family:' . preg_replace('/[<>]/', '', $fam);
            if ($sz !== '') $decl[] = 'font-size:' . preg_replace('/[<>;"\']/', '', $sz);
            if ($col !== '') $decl[] = 'color:' . preg_replace('/[<>;"\']/', '', $col);
            if ($w !== '') $decl[] = 'font-weight:' . preg_replace('/[^0-9]/', '', $w);
            if ($decl !== []) $buf[] = $selector . '{' . implode(';', $decl) . '}';
        }
    }
    if ($buf !== []): ?>
    <style id="gwa-typography"><?php echo implode("\n", $buf); ?></style>
    <?php endif; ?>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@1.13.0/dist/Control.Geocoder.css" />
    <script src="https://unpkg.com/leaflet-control-geocoder@1.13.0/dist/Control.Geocoder.js"></script>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-inner">
                <button class="nav-toggle" id="navToggle" aria-label="開啟導航選單" aria-expanded="false">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="brand">
                    <?php if (!empty($brand['logo'])): ?>
                        <img src="<?php echo htmlspecialchars($basePath . $brand['logo']); ?>" alt="<?php echo htmlspecialchars($brand['title']); ?>" class="logo" style="max-width: 200px; max-height: 80px; object-fit: contain; margin-right: 12px;">
                    <?php else: ?>
                    <div class="logo" aria-hidden="true"></div>
                    <?php endif; ?>
                    <div>
                        <h1><?php echo htmlspecialchars($brand['title']); ?></h1>
                        <small><?php echo htmlspecialchars($brand['subtitle']); ?></small>
                    </div>
                </div>
                <div class="header-actions">
                    <div class="language-selector" id="languageSelector" style="position: relative; display: inline-block;">
                        <button class="header-btn btn-language" id="btnLanguage" aria-label="選擇語言" title="選擇語言">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="2" y1="12" x2="22" y2="12"/>
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                            </svg>
                        </button>
                        <div class="language-dropdown" id="languageDropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 8px; background: var(--bg, #fff); border: 1px solid var(--border, rgba(0,0,0,0.1)); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; min-width: 180px; max-height: 300px; overflow-y: auto;">
                            <!-- 語言選項將由 JavaScript 動態生成 -->
            </div>
                    </div>
                    <button class="header-btn btn-whatsapp" id="btnWhatsApp" aria-label="WhatsApp 客服" title="聯絡客服" style="display:none;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                        </svg>
                    </button>
                    <button class="header-btn btn-cart" id="btnCart" aria-label="購物車" title="購物車">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
                        </svg>
                        <span class="cart-badge" id="cartBadge" style="display:none;">0</span>
                    </button>
                    <button class="header-btn nav-back" id="navBack" aria-label="返回上一頁" title="返回上一頁 (Alt+←)" style="display:none;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- 手機端抽屜導航 -->
    <div class="nav-drawer" id="navDrawer">
        <div class="nav-drawer-backdrop" id="navDrawerBackdrop"></div>
        <div class="nav-drawer-content">
            <div class="nav-drawer-header">
                <h2>導航選單</h2>
                <button class="nav-drawer-close" id="navDrawerClose" aria-label="關閉導航">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="nav-search-wrap">
                <input type="search" class="nav-search" id="navSearch" placeholder="搜尋頁面..." autocomplete="off">
                <svg class="nav-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
            </div>
            <nav class="nav-wrap" aria-label="主導覽" id="mainNav">
                <?php render_nav($tree, $cms, $basePath, $currentPath); ?>
            </nav>
        </div>
    </div>

    <!-- 桌面端水平導航 -->
    <nav class="nav-desktop" aria-label="主導覽" id="desktopNav">
        <div class="container">
            <div class="nav-desktop-inner">
                <div class="nav-desktop-links">
                    <?php render_nav($tree, $cms, $basePath, $currentPath); ?>
                </div>
                <div class="nav-desktop-search-wrap">
                    <input type="search" class="nav-desktop-search" id="desktopNavSearch" placeholder="搜尋頁面 (Ctrl+K)" autocomplete="off">
                    <svg class="nav-desktop-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                </div>
            </div>
        </div>
    </nav>

    <main class="main">
        <?php
        $layoutFullWidth = (bool)($pageMeta['layout_full_width'] ?? false);
        $layoutBlockAlign = (string)($pageMeta['layout_block_align'] ?? 'center');
        $containerClass = $layoutFullWidth ? 'layout-full-width' : '';
        $mainContentClass = $layoutFullWidth ? 'layout-full-width' : '';
        $blockAlignStyle = '';
        if (!$layoutFullWidth && $layoutBlockAlign !== 'left') {
            $blockAlignStyle = ' style="display: flex; flex-direction: column; align-items: ' . ($layoutBlockAlign === 'center' ? 'center' : 'flex-end') . ';"';
        }
        ?>
        <div class="container <?php echo htmlspecialchars($containerClass); ?>"<?php echo $blockAlignStyle; ?>>
            <nav class="crumbs" aria-label="麵包屑" id="breadcrumbs">
                <a href="<?php echo htmlspecialchars($cms->publicUrl($basePath, 'home')); ?>" class="nav-link" data-path="home" style="padding:0;border:none;background:transparent;">首頁</a>
                <?php foreach ($breadcrumbs as $c): ?>
                    <span aria-hidden="true">›</span>
                    <a href="<?php echo htmlspecialchars($cms->publicUrl($basePath, (string)$c['path'])); ?>" class="nav-link" data-path="<?php echo htmlspecialchars((string)$c['path']); ?>" style="padding:0;border:none;background:transparent;"><?php echo htmlspecialchars((string)$c['title']); ?></a>
                <?php endforeach; ?>
            </nav>

            <article class="content <?php echo htmlspecialchars($mainContentClass); ?>" id="mainContent">
                <?php 
                // 如果是商品頁面，包裝在商品卡片中
                if ($pageMeta && ($pageMeta['type'] ?? 'page') === 'product') {
                    $price = (float)($pageMeta['price'] ?? 0);
                    echo '<div class="gwa-product-card" data-path="' . htmlspecialchars($currentPath) . '" data-title="' . htmlspecialchars($pageMeta['title'] ?? '') . '" data-price="' . htmlspecialchars((string)$price) . '">';
                    echo $contentHtml;
                    echo '<div class="gwa-product-actions">';
                    echo '<span class="gwa-product-price">' . htmlspecialchars($currencySymbol) . ' ' . htmlspecialchars(number_format($price)) . '</span>';
                    echo '<button class="btn btn-add-to-cart" data-path="' . htmlspecialchars($currentPath) . '">';
                    echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>';
                    echo ' 加入購物車</button>';
                    echo '</div>';
                    echo '</div>';
                } else {
                    echo $contentHtml;
                }
                ?>
            </article>
            <div class="footer"><?php echo $footer; ?></div>
        </div>
    </main>

    <script>
        const BASE_PATH = <?php echo json_encode($basePath, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const API_URL = BASE_PATH + 'api.php';

        let isLoading = false;

        function urlFor(path) {
            if (!path || path === 'home') return BASE_PATH;
            return BASE_PATH + path;
        }

        function pathFromLocation() {
            let p = window.location.pathname || '/';
            
            // 移除開頭和結尾的斜線
            p = p.replace(/^\/+/, '').replace(/\/+$/, '');
            
            // 處理 basePath
            if (BASE_PATH !== '/' && BASE_PATH !== '') {
                const basePathTrimmed = BASE_PATH.replace(/^\/+/, '').replace(/\/+$/, '');
                if (basePathTrimmed && p.startsWith(basePathTrimmed)) {
                    p = p.slice(basePathTrimmed.length);
                    p = p.replace(/^\/+/, '');
                }
            }
            
            // 移除已知的檔案前綴（如果被錯誤包含）
            const knownFiles = ['index.php', 'index.html', 'index', 'router.php'];
            for (const file of knownFiles) {
                if (p === file || p.startsWith(file + '/')) {
                    p = p.slice(file.length);
                    p = p.replace(/^\/+/, '');
                    break;
                }
            }
            
            // 清理路徑（移除多餘斜線）
            p = p.replace(/\/+/g, '/').replace(/^\/+/, '').replace(/\/+$/, '');
            
            return p ? p : 'home';
        }

        let navHistory = [];
        let navHistoryIndex = -1;

        function setActive(path) {
            document.querySelectorAll('a.nav-link[data-path]').forEach(a => {
                a.classList.toggle('active', a.getAttribute('data-path') === path);
            });
        }

        function addToHistory(path) {
            if (navHistory.length > 0 && navHistory[navHistoryIndex] === path) return;
            navHistory = navHistory.slice(0, navHistoryIndex + 1);
            navHistory.push(path);
            navHistoryIndex = navHistory.length - 1;
            if (navHistory.length > 50) {
                navHistory.shift();
                navHistoryIndex--;
            }
            updateNavBackButton();
        }

        function goBack() {
            if (navHistoryIndex > 0) {
                navHistoryIndex--;
                const prevPath = navHistory[navHistoryIndex];
                loadPage(prevPath, false);
            }
        }

        function updateNavBackButton() {
            const btn = document.getElementById('navBack');
            const languageSelector = document.getElementById('languageSelector');
            
            if (btn) {
                const shouldShow = navHistoryIndex > 0;
                btn.style.display = shouldShow ? 'flex' : 'none';
                
                // 返回按鈕出現時，收起語言按鈕；反之亦然
                if (languageSelector) {
                    languageSelector.style.display = shouldShow ? 'none' : 'inline-block';
                }
            } else if (languageSelector) {
                // 如果返回按鈕不存在，確保語言按鈕顯示
                languageSelector.style.display = 'inline-block';
            }
        }

        // 導航展開/收起管理（簡化版）
        function toggleNavItem(navItem) {
            if (!navItem?.classList.contains('has-children')) return;
            navItem.classList.toggle('expanded');
        }

        // 自動展開包含當前頁面的父層級（僅手機端，支持多層嵌套）
        function expandActiveNav() {
            if (window.innerWidth >= 768) return; // PC端使用hover，不需要展開
            
            const path = pathFromLocation();
            const activeLink = document.querySelector(`a.nav-link[data-path="${path}"]`);
            if (!activeLink) return;
            
            // 標記當前活動的連結
            activeLink.classList.add('active');
            
            // 展開所有包含當前頁面的父層級（支持多層嵌套）
            let currentItem = activeLink.closest('.nav-item');
            
            while (currentItem) {
                // 如果當前項目有子層級，展開它
                if (currentItem.classList.contains('has-children')) {
                    currentItem.classList.add('expanded');
                }
                
                // 找到父層級：當前項目的父元素應該是 .nav-children，再上一層是 .nav-item
                const parentContainer = currentItem.parentElement;
                if (parentContainer && parentContainer.classList.contains('nav-children')) {
                    // 找到包含這個 .nav-children 的父 .nav-item
                    currentItem = parentContainer.closest('.nav-item');
                } else {
                    // 沒有更多父層級了
                    currentItem = null;
                }
            }
        }

        function renderBreadcrumbs(breadcrumbs) {
            const el = document.getElementById('breadcrumbs');
            if (!el) return;
            const parts = [];
            parts.push(`<a href="${urlFor('home')}" class="nav-link" data-path="home" style="padding:0;border:none;background:transparent;">首頁</a>`);
            (breadcrumbs || []).forEach(c => {
                const p = (c && c.path) ? c.path : '';
                const t = (c && c.title) ? c.title : p;
                parts.push(`<span aria-hidden="true">›</span>`);
                parts.push(`<a href="${urlFor(p)}" class="nav-link" data-path="${escapeHtml(p)}" style="padding:0;border:none;background:transparent;">${escapeHtml(t)}</a>`);
            });
            el.innerHTML = parts.join('');
        }

        function escapeHtml(s) {
            return String(s)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        async function loadPage(path, pushState = true) {
            if (isLoading) return;
            // 修復：防止空路徑或無效路徑觸發加載
            path = path || 'home';
            path = String(path).trim();
            if (!path || path === '') {
                path = 'home';
            }
            isLoading = true;

            const content = document.getElementById('mainContent');
            if (content) content.classList.add('loading');

            try {
                const res = await fetch(`${API_URL}?action=page&path=${encodeURIComponent(path)}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (!data || !data.ok) throw new Error((data && data.error) ? data.error : '載入失敗');

                if (pushState) {
                    window.history.pushState({ path }, '', urlFor(path));
                    addToHistory(path);
                }
                document.title = (data.page && data.page.title) ? data.page.title : document.title;

                const link = document.querySelector('link[rel="canonical"]');
                if (link && data.canonical) link.setAttribute('href', data.canonical);

                if (content) {
                    // 計算新內容的哈希值
                    const newContentHash = hashContent(data.html || '');
                    const storedHash = localStorage.getItem(`${CONTENT_HASH_KEY}_${path}`);
                    
                    // 如果內容發生變更，清除翻譯緩存並重新翻譯
                    if (storedHash && storedHash !== newContentHash) {
                        console.log(`[翻譯] 檢測到內容變更 (${path})，清除舊翻譯並重新翻譯`);
                        // 清除該頁面的翻譯緩存
                        clearTranslationCache(path);
                    }
                    
                    // 保存新的內容哈希值
                    localStorage.setItem(`${CONTENT_HASH_KEY}_${path}`, newContentHash);
                    originalContentHash = newContentHash;
                    
                    // 檢查是否為區塊編輯器數據
                    let html = data.html || '';
                    // 修復：使用多行匹配，支持 JSON 中的換行符
                    const blockDataMatch = html.match(/<div class="gwa-block-editor-data"[^>]*>([\s\S]*?)<\/div>/);
                    
                    if (blockDataMatch) {
                        // 渲染區塊編輯器為多行多列佈局
                        try {
                            // 解碼 HTML 實體（完整解碼）
                            let blockDataJson = blockDataMatch[1];
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = blockDataJson;
                            blockDataJson = tempDiv.textContent || tempDiv.innerText || blockDataJson;
                            
                            const blocks = JSON.parse(blockDataJson);
                            // 檢查是否有填滿效果的區塊
                            const hasCompact = blocks.some(block => {
                                const padding = block.padding || 16;
                                const radius = block.radius || 12;
                                return (padding === 4 && radius === 0);
                            });
                            const gridGap = hasCompact ? '0' : '20px';
                            const gridMargin = hasCompact ? '0' : '20px 0';
                            // 獲取對齊設置
                            const blockAlign = (data.page && data.page.layout_block_align) || 'center';
                            const alignStyle = blockAlign === 'center' ? 'justify-items: center;' : (blockAlign === 'right' ? 'justify-items: end;' : 'justify-items: start;');
                            let blockHtml = `<div class="gwa-block-grid${hasCompact ? ' gwa-block-grid-compact' : ''}" style="display: grid; grid-template-columns: repeat(12, 1fr); gap: ${gridGap}; margin: ${gridMargin}; ${alignStyle}">`;
                            const styleRules = []; // 收集所有樣式規則，統一輸出
                            
                            blocks.forEach((block, index) => {
                                const padding = block.padding || 16;
                                const radius = block.radius || 12;
                                // 檢測「填滿」效果（padding=4, radius=0）
                                const isCompact = (padding === 4 && radius === 0);
                                
                                // 填滿效果：強制全寬
                                const colspan = isCompact ? 12 : Math.min(block.colspan || 4, 12);
                                const bg = block.bg || '#ffffff';
                                
                                // 直屏設置
                                const paddingMobile = block.paddingMobile !== null && block.paddingMobile !== undefined ? block.paddingMobile : padding;
                                const radiusMobile = block.radiusMobile !== null && block.radiusMobile !== undefined ? block.radiusMobile : radius;
                                const isCompactMobile = (paddingMobile === 4 && radiusMobile === 0);
                                const colspanMobile = isCompactMobile ? 12 : (block.colspanMobile || 12);
                                const bgMobile = block.bgMobile || bg;
                                
                                const blockId = `gwa-block-${index}`;
                                const compactClass = isCompact ? ' gwa-block-compact' : '';
                                // 如果區塊不是 100% 寬度，確保有正確的寬度以支持對齊
                                const blockWidthStyle = colspan === 12 ? '' : 'width: 100%; max-width: 100%;';
                                blockHtml += `<div class="gwa-block-item${compactClass}" id="${blockId}" style="grid-column: span ${colspan}; padding: ${padding}px; background: ${bg}; border-radius: ${radius}px; ${blockWidthStyle}">${block.html || ''}</div>`;
                                
                                // 收集直屏樣式規則
                                const compactMobileClass = isCompactMobile ? ' gwa-block-compact' : '';
                                styleRules.push(`#${blockId} { grid-column: span ${colspanMobile} !important; padding: ${paddingMobile}px !important; background: ${bgMobile} !important; border-radius: ${radiusMobile}px !important; }`);
                            });
                            blockHtml += '</div>';
                            
                            // 統一輸出所有樣式（優化性能）
                            if (styleRules.length > 0) {
                                blockHtml += `<style>@media (max-width: 768px) { .gwa-block-grid { grid-template-columns: 1fr !important; } ${styleRules.join(' ')} }</style>`;
                            } else {
                                blockHtml += '<style>@media (max-width: 768px) { .gwa-block-grid { grid-template-columns: 1fr !important; } }</style>';
                            }
                            
                            content.innerHTML = blockHtml;
                        } catch (e) {
                            console.error('渲染區塊編輯器失敗:', e);
                            content.innerHTML = html;
                        }
                    } else {
                        content.innerHTML = html;
                    }
                    
                    // 實作圖片 lazy loading（優化大型環境下的性能）
                    enableImageLazyLoading(content);
                    
                    // 等待內容完全渲染（特別是區塊編輯器）
                    // 使用 requestAnimationFrame 確保 DOM 更新完成
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            // 如果當前語言不是預設語言，應用翻譯
                            // 不再硬編碼 zh-TW，而是根據 languagesData 判斷
                            if (window.languagesData && window.languagesData[currentLang]?.native !== true && !isTranslatingPage) {
                                setTimeout(() => {
                                    if (!isTranslatingPage && content && content.innerHTML.trim() !== '') {
                                        applyTranslations().catch(e => {
                                            console.error('[翻譯] 頁面載入後翻譯失敗:', e);
                                        });
                                    }
                                }, 300);
                            }
                        });
                    });
                } else {
                    // 如果沒有 content 元素，仍然嘗試翻譯（可能在其他地方）
                    if (window.languagesData && window.languagesData[currentLang]?.native !== true && !isTranslatingPage) {
                        setTimeout(() => {
                            if (!isTranslatingPage) {
                                applyTranslations().catch(e => {
                                    console.error('[翻譯] 頁面載入後翻譯失敗:', e);
                                });
                            }
                        }, 300);
                    }
                }
                
                renderBreadcrumbs(data.breadcrumbs || []);
                setActive(path);
                
                // 自動展開包含當前頁面的導航項目（僅手機端）
                expandActiveNav();
                
                // 關閉手機端導航抽屜
                closeNavDrawer();
            } catch (e) {
                if (content) {
                    content.innerHTML = `<h2>載入失敗</h2><p>${escapeHtml(e.message || String(e))}</p><p><a href="${urlFor('home')}" class="nav-link" data-path="home">回到首頁</a></p>`;
                }
            } finally {
                if (content) content.classList.remove('loading');
                isLoading = false;
            }
        }

        // 人性化導航點擊處理（統一所有層級的行為）
        document.addEventListener('click', (e) => {
            const a = e.target.closest('a.nav-link[data-path]');
            if (!a) return;
            
            const isMobile = window.innerWidth < 768;
            const hasChildren = a.getAttribute('data-has-children') === '1';
            const navItem = a.closest('.nav-item');
            const clickedExpandIcon = e.target.closest('.nav-expand-icon');
            const clickedLinkText = e.target.closest('.nav-link-text');
            
            // 人性化邏輯：手機端
            if (isMobile) {
                // 情況1：點擊展開圖示 → 只展開/收起，不導航（適用於所有層級：頂層、子層、多層嵌套）
                if (clickedExpandIcon) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (navItem && hasChildren) {
                        toggleNavItem(navItem);
                    }
                    return;
                }
                
                // 情況2：點擊有子層級的連結文字（適用於所有層級：頂層、子層、多層嵌套）
                // 邏輯：收起狀態 → 點擊展開；展開狀態 → 點擊進入頁面
                if (hasChildren && navItem && clickedLinkText) {
                    const isExpanded = navItem.classList.contains('expanded');
                    if (!isExpanded) {
                        // 當前是收起的，點擊展開
                        e.preventDefault();
                        e.stopPropagation();
                        toggleNavItem(navItem);
                        return;
                    }
                    // 當前是展開的，點擊進入頁面（繼續執行導航邏輯）
                }
                
                // 情況3：點擊沒有子層級的連結 → 正常導航到該頁面
            const href = a.getAttribute('href') || '';
            if (href.startsWith('http://') || href.startsWith('https://')) return;
                
                // 確保路徑有效
                const path = a.getAttribute('data-path');
                if (!path || path.trim() === '') {
                    return; // 如果沒有有效路徑，不執行加載
                }
                
            e.preventDefault();
                loadPage(path, true);
                return;
            }
            
            // PC端：hover 自動展開，點擊直接導航（簡潔邏輯）
            const href = a.getAttribute('href') || '';
            if (href.startsWith('http://') || href.startsWith('https://')) return;
            
            const path = a.getAttribute('data-path');
            if (!path || path.trim() === '') return;
            
            e.preventDefault();
            loadPage(path, true);
        });

        window.addEventListener('popstate', () => {
            loadPage(pathFromLocation(), false);
        });

        // 初始同步 active 狀態（避免硬刷新後 active 失準）
        const initialPath = '<?php echo htmlspecialchars($currentPath); ?>';
        setActive(initialPath);
        // 確保初始狀態下語言選擇器正確顯示（返回按鈕不顯示時）
        updateNavBackButton();
        addToHistory(initialPath);
        expandActiveNav();
        
        // 處理初始頁面內容的圖片 lazy loading（服務端渲染的內容）
        const initialContent = document.getElementById('mainContent');
        if (initialContent) {
            enableImageLazyLoading(initialContent);
        }

        // 導航抽屜控制
        const navToggle = document.getElementById('navToggle');
        const navDrawer = document.getElementById('navDrawer');
        const navDrawerClose = document.getElementById('navDrawerClose');
        const navDrawerBackdrop = document.getElementById('navDrawerBackdrop');
        const navSearch = document.getElementById('navSearch');
        const navBack = document.getElementById('navBack');

        function openNavDrawer() {
            if (navDrawer) {
                navDrawer.classList.add('open');
                if (navToggle) navToggle.setAttribute('aria-expanded', 'true');
                document.body.style.overflow = 'hidden';
                // 確保在打開抽屜時展開當前頁面的父層級
                setTimeout(() => {
                    expandActiveNav();
                    if (navSearch) navSearch.focus();
                }, 100);
            }
        }

        function closeNavDrawer() {
            if (navDrawer) {
                navDrawer.classList.remove('open');
                if (navToggle) navToggle.setAttribute('aria-expanded', 'false');
                document.body.style.overflow = '';
                if (navSearch) navSearch.value = '';
                filterNavItems('');
            }
        }

        navToggle && navToggle.addEventListener('click', openNavDrawer);
        navDrawerClose && navDrawerClose.addEventListener('click', closeNavDrawer);
        navDrawerBackdrop && navDrawerBackdrop.addEventListener('click', closeNavDrawer);
        navBack && navBack.addEventListener('click', goBack);

        // 導航搜尋過濾（支援 menu_title、title、path）
        function filterNavItems(query, navElement) {
            const nav = navElement || document.getElementById('mainNav');
            if (!nav) return;
            const q = (query || '').toLowerCase().trim();
            const items = Array.from(nav.querySelectorAll('.nav-item'));
            let hasMatch = false;
            
            // 遞迴檢查項目及其子項目是否匹配
            function checkItemMatch(item) {
                const link = item.querySelector('.nav-link');
                if (!link) return false;
                
                // 取得搜尋資料
                let searchData = {};
                try {
                    const dataAttr = link.getAttribute('data-search');
                    if (dataAttr) searchData = JSON.parse(dataAttr);
                } catch (e) {}
                
                const menuTitle = (searchData.menu_title || '').toLowerCase();
                const title = (searchData.title || '').toLowerCase();
                const path = (searchData.path || '').toLowerCase();
                const label = (searchData.label || link.textContent || '').toLowerCase();
                
                // 檢查是否匹配：menu_title、title、path、label
                const match = !q || 
                    menuTitle.includes(q) || 
                    title.includes(q) || 
                    path.includes(q) || 
                    label.includes(q);
                
                // 檢查子項目
                const childList = item.querySelector(':scope > .nav-list');
                let childMatch = false;
                if (childList) {
                    const childItems = Array.from(childList.querySelectorAll(':scope > .nav-item'));
                    childItems.forEach(childItem => {
                        if (checkItemMatch(childItem)) {
                            childMatch = true;
                        }
                    });
                }
                
                // 如果自己或子項目匹配，顯示
                const shouldShow = match || childMatch;
                item.style.display = shouldShow ? '' : 'none';
                
                // 如果匹配，確保父層也顯示
                if (shouldShow && q) {
                    let parent = item.parentElement;
                    while (parent && parent !== nav) {
                        if (parent.classList && parent.classList.contains('nav-list')) {
                            parent.style.display = '';
                            // 繼續向上查找父層
                            const parentItem = parent.closest('.nav-item');
                            if (parentItem) {
                                parentItem.style.display = '';
                            }
                        }
                        parent = parent.parentElement;
                    }
                }
                
                if (shouldShow) hasMatch = true;
                return shouldShow;
            }
            
            // 檢查所有項目
            items.forEach(item => checkItemMatch(item));
            
            // 如果沒有匹配結果，顯示提示
            const noResults = nav.querySelector('.nav-no-results');
            if (q && !hasMatch) {
                if (!noResults) {
                    const msg = document.createElement('div');
                    msg.className = 'nav-no-results';
                    msg.textContent = '找不到符合的頁面';
                    msg.style.cssText = 'padding: 20px; text-align: center; color: var(--muted);';
                    nav.appendChild(msg);
                }
            } else if (noResults) {
                noResults.remove();
            }
        }

        navSearch && navSearch.addEventListener('input', (e) => {
            filterNavItems(e.target.value, document.getElementById('mainNav'));
        });

        const desktopNavSearch = document.getElementById('desktopNavSearch');
        desktopNavSearch && desktopNavSearch.addEventListener('input', (e) => {
            filterNavItems(e.target.value, document.getElementById('desktopNav'));
        });

        // 鍵盤快捷鍵
        document.addEventListener('keydown', (e) => {
            // Alt+← 返回
            if (e.altKey && e.key === 'ArrowLeft' && navHistoryIndex > 0) {
                e.preventDefault();
                goBack();
            }
            // Escape 關閉導航抽屜
            if (e.key === 'Escape' && navDrawer && navDrawer.classList.contains('open')) {
                e.preventDefault();
                closeNavDrawer();
            }
            // Ctrl+K 或 / 開啟搜尋（桌面端）
            if ((e.ctrlKey && e.key === 'k') || (e.key === '/' && !e.target.matches('input, textarea'))) {
                e.preventDefault();
                if (window.innerWidth >= 768) {
                    const desktopNav = document.getElementById('desktopNav');
                    const searchInput = desktopNav && desktopNav.querySelector('.nav-search');
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                } else {
                    openNavDrawer();
                }
            }
        });

        // 滑動手勢關閉抽屜（手機端）
        let touchStartX = 0;
        let touchStartY = 0;
        navDrawer && navDrawer.addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        });
        navDrawer && navDrawer.addEventListener('touchmove', (e) => {
            if (!navDrawer.classList.contains('open')) return;
            const touchX = e.touches[0].clientX;
            const touchY = e.touches[0].clientY;
            const deltaX = touchX - touchStartX;
            const deltaY = touchY - touchStartY;
            // 如果主要是水平向左滑動
            if (Math.abs(deltaX) > Math.abs(deltaY) && deltaX < -50) {
                closeNavDrawer();
            }
        });

        // ========== 貨幣設定 ==========
        let CURRENCY_SYMBOL = <?php echo json_encode($currencySymbol, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        
        // 載入貨幣設定
        (async () => {
            try {
                const res = await fetch(`${API_URL}?action=currency_get`);
                const data = await res.json();
                if (data && data.ok && data.symbol) {
                    CURRENCY_SYMBOL = data.symbol;
                }
            } catch (e) {
                console.warn('載入貨幣設定失敗', e);
            }
        })();

        // ========== 多語言翻譯系統 ==========
        const LANG_KEY = 'gwa_language';
        const CONTENT_HASH_KEY = 'gwa_content_hash';
        let currentLang = localStorage.getItem(LANG_KEY) || null; // 不預設為 zh-TW，等待從 API 獲取
        window.languagesData = {};
        window.translationsData = {};
        let originalContentHash = null; // 儲存原始內容的哈希值
        let translationCache = {}; // 臨時緩存 {text: translatedText}，僅用於避免同一請求週期內的重複調用
        let isTranslatingPage = false; // 標誌：是否正在翻譯頁面
        let translationFailures = new Map(); // 記錄失敗的翻譯請求 {text: failureCount}
        const MAX_RETRIES = 2; // 最大重試次數
        
        const langNames = {
            'zh-TW': '繁體中文',
            'en': 'English',
            'zh-CN': '简体中文',
            'ja': '日本語',
            'ko': '한국어',
            'es': 'Español',
            'fr': 'Français',
            'de': 'Deutsch',
            'it': 'Italiano',
            'pt': 'Português',
            'ru': 'Русский',
            'ar': 'العربية',
        };
        
        async function loadLanguages() {
            try {
                const res = await fetch(`${API_URL}?action=languages_get`);
                const data = await res.json();
                if (data && data.ok) {
                    window.languagesData = data.languages || {};
                    window.defaultLanguage = data.default_language || null;
                    const defaultLang = window.defaultLanguage;
                    
                    // 如果 currentLang 不存在或不在支援的語言列表中，使用預設語言
                    if (!currentLang || !window.languagesData[currentLang]) {
                        // 優先使用系統預設語言
                        if (defaultLang && window.languagesData[defaultLang]) {
                            currentLang = defaultLang;
                        } else {
                            // 如果預設語言不存在，找第一個原生語言
                            const nativeLangs = Object.keys(window.languagesData).filter(lang => 
                                window.languagesData[lang] && window.languagesData[lang].native === true
                            );
                            if (nativeLangs.length > 0) {
                                currentLang = nativeLangs[0];
                            } else {
                                // 如果沒有原生語言，使用第一個支援的語言
                                const supportedLangs = Object.keys(window.languagesData);
                                if (supportedLangs.length > 0) {
                                    currentLang = supportedLangs[0];
                                } else {
                                    // 最後的後備選項：zh-TW（僅在完全沒有語言數據時使用）
                                    currentLang = 'zh-TW';
                                }
                            }
                        }
                        localStorage.setItem(LANG_KEY, currentLang);
                    }
                    updateLanguageUI();
                }
                
                const transRes = await fetch(`${API_URL}?action=translations_get&lang=${encodeURIComponent(currentLang)}`);
                const transData = await transRes.json();
                if (transData && transData.ok) {
                    // 確保數據結構正確：{lang: {translatedText: correctedText}}
                    if (!window.translationsData) window.translationsData = {};
                    if (transData.lang) {
                        window.translationsData[transData.lang] = transData.translations || {};
                    } else if (transData.translations) {
                        // 如果沒有 lang 字段，但 translations 是對象，嘗試推斷語言
                        // 這種情況不應該發生，但為了兼容性保留
                        if (typeof transData.translations === 'object' && !Array.isArray(transData.translations)) {
                            // 如果 translations 是對象，可能是 {lang: {...}} 結構
                            Object.keys(transData.translations).forEach(lang => {
                                if (typeof transData.translations[lang] === 'object') {
                                    window.translationsData[lang] = transData.translations[lang];
                                }
                            });
                        }
                    }
                }
                
                console.log(`[語言載入] 當前語言: ${currentLang}, 是否為原生語言: ${window.languagesData[currentLang]?.native}`);
                
                // 如果切換到原生語言，需要重新載入頁面以顯示原文
                // 不再硬編碼 zh-TW，而是根據 languagesData 判斷
                const isNative = (window.languagesData && window.languagesData[currentLang]?.native === true);
                if (isNative) {
                    // 清除臨時緩存（因為切換回原生語言，不需要翻譯）
                    translationCache = {};
                    
                    // 還原導航菜單的原始文本（從 data-search 屬性中讀取）
                    document.querySelectorAll('.nav-link-text').forEach(el => {
                        const link = el.closest('.nav-link');
                        if (link) {
                            const dataSearch = link.getAttribute('data-search');
                            if (dataSearch) {
                                try {
                                    const data = JSON.parse(dataSearch);
                                    const originalLabel = data.label || data.menu_title || data.title || '';
                                    if (originalLabel) {
                                        el.textContent = originalLabel;
                                    }
                                } catch (e) {
                                    console.warn('[語言還原] 無法解析導航數據:', e);
                                }
                            }
                        }
                    });
                    
                    // 使用 pathFromLocation() 正確獲取當前路徑
                    const currentPath = pathFromLocation() || 'home';
                    console.log(`[語言載入] 切換到原生語言，重新載入頁面以顯示原文: ${currentPath}`);
                    
                    // 重新載入當前頁面以顯示原文（不推送歷史記錄）
                    loadPage(currentPath, false);
                } else {
                    console.log('[語言載入] 需要翻譯，延遲調用 applyTranslations()');
                    // 確保 DOM 完全載入後再執行翻譯，並等待內容渲染
                    const tryApplyTranslations = () => {
                        setTimeout(() => {
                            const content = document.getElementById('mainContent');
                            if (content && content.innerHTML.trim() !== '') {
                                if (!isTranslatingPage) {
                                    applyTranslations().catch(e => {
                                        console.error('[翻譯] 初始頁面翻譯失敗:', e);
                                    });
                                }
                            } else {
                                // 內容還沒載入，再等一會兒
                                console.log('[翻譯] 內容尚未載入，繼續等待...');
                                setTimeout(tryApplyTranslations, 200);
                            }
                        }, 500);
                    };
                    
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', tryApplyTranslations);
                    } else {
                        // DOM 已經載入完成
                        tryApplyTranslations();
                    }
                }
            } catch (e) {
                console.warn('載入語言設定失敗', e);
            }
        }
        
        function updateLanguageUI() {
            const btn = document.getElementById('btnLanguage');
            if (!btn) return;
            
            btn.onclick = (e) => {
                e.stopPropagation();
                showLanguageSelector();
            };
        }
        
        function showLanguageSelector() {
            const dropdown = document.getElementById('languageDropdown');
            if (!dropdown) {
                console.error('[語言選擇器] 找不到 languageDropdown 元素');
                return;
            }
            
            const languages = window.languagesData || {};
            const langKeys = Object.keys(languages);
            
            console.log('[語言選擇器] 顯示語言選擇器，語言數據:', languages, '語言數量:', langKeys.length);
            
            if (langKeys.length === 0) {
                console.warn('[語言選擇器] 沒有語言數據，window.languagesData:', languages);
                return;
            }
            
            dropdown.innerHTML = '';
            dropdown.style.display = 'block';
            console.log('[語言選擇器] 下拉選單已顯示，將添加', langKeys.length, '個語言選項');
            
            langKeys.forEach(lang => {
                const langInfo = window.languagesData[lang];
                const item = document.createElement('div');
                item.style.cssText = 'padding: 10px 16px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border, rgba(0,0,0,0.05)); transition: background 0.2s;';
                item.innerHTML = `
                    <span>${langNames[lang] || lang}</span>
                    ${currentLang === lang ? '<span style="color: var(--accent);">✓</span>' : ''}
                `;
                item.onmouseenter = () => item.style.background = 'var(--input-bg, #f5f5f5)';
                item.onmouseleave = () => item.style.background = '';
                item.onclick = () => {
                    console.log(`[語言切換] 切換到語言: ${lang}`);
                    currentLang = lang;
                    localStorage.setItem(LANG_KEY, currentLang);
                    // 清除臨時緩存
                    translationCache = {};
                    dropdown.style.display = 'none';
                    loadLanguages();
                };
                dropdown.appendChild(item);
            });
        }
        
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('languageDropdown');
            const selector = document.getElementById('languageSelector');
            if (dropdown && selector && !selector.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        // 注意：緩存現在由伺服器端管理，前端只保留臨時緩存對象用於避免重複請求
        
        async function translateText(text, targetLang, useCache = true) {
            if (!text || text.trim() === '') return text;
            // 不再硬編碼 zh-TW，而是根據 languagesData 判斷是否為原生語言
            if (window.languagesData && window.languagesData[targetLang]?.native === true) return text;
            
            const originalText = text.trim();
            
            // 注意：緩存現在由伺服器端管理（包含時間戳對比）
            // 前端只保留臨時緩存用於避免同一請求週期內的重複調用
            if (useCache && translationCache[originalText]) {
                return translationCache[originalText];
            }
            
            // 檢查失敗次數，防止瘋狂重試
            const failureCount = translationFailures.get(originalText) || 0;
            if (failureCount >= MAX_RETRIES) {
                console.warn(`[翻譯] 文本 "${originalText.substring(0, 50)}..." 已失敗 ${failureCount} 次，跳過翻譯`);
                return originalText; // 返回原文，不再重試
            }
            
            let translatedText = originalText;
            
            // 第一步：並發請求兩個 API（後端已實現並發，這裡直接調用）
            try {
                // 確定源語言：使用第一個原生語言，如果沒有則使用預設語言
                let sourceLang = null;
                if (window.languagesData) {
                    const nativeLangs = Object.keys(window.languagesData).filter(lang => 
                        window.languagesData[lang]?.native === true
                    );
                    if (nativeLangs.length > 0) {
                        sourceLang = nativeLangs[0]; // 使用第一個原生語言
                    } else {
                        // 如果沒有原生語言，使用預設語言
                        const defaultLang = window.defaultLanguage || null;
                        if (defaultLang && window.languagesData[defaultLang]) {
                            sourceLang = defaultLang;
                        }
                    }
                }
                // 如果仍然沒有，使用 zh-TW 作為後備（向後兼容）
                if (!sourceLang) {
                    sourceLang = 'zh-TW';
                }
                
                // 獲取當前頁面路徑（用於時間戳對比）
                const currentPath = window.location.pathname.split('/').pop() || 'home';
                
                const res = await fetch(`${API_URL}?action=translate`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        text: originalText,
                        source_lang: sourceLang,
                        target_lang: targetLang,
                        page_path: currentPath // 傳遞頁面路徑，用於時間戳對比
                    })
                });
                
                if (!res.ok) {
                    const errorText = await res.text().catch(() => '');
                    let errorData = null;
                    try {
                        errorData = JSON.parse(errorText);
                    } catch (e) {
                        // 忽略 JSON 解析錯誤
                    }
                    
                    // 處理 400 Bad Request：檢查請求格式
                    if (res.status === 400) {
                        const errorMsg = errorData?.error || errorText || '請求格式錯誤';
                        console.error(`[翻譯] 400 Bad Request: ${errorMsg}`);
                        throw new Error(`請求格式錯誤: ${errorMsg}`);
                    }
                    
                    // 處理 429 速率限制
                    if (res.status === 429 || (errorData && errorData.rate_limited)) {
                        console.warn(`[翻譯] 速率限制，等待後重試: "${originalText.substring(0, 50)}..."`);
                        // 等待更長時間後重試（不增加失敗次數）
                        await new Promise(resolve => setTimeout(resolve, 2000));
                        // 返回原文，不拋出錯誤（避免觸發重試循環）
                        return originalText;
                    }
                    
                    throw new Error(`翻譯 API HTTP 錯誤: ${res.status} ${res.statusText}${errorText ? ' - ' + errorText.substring(0, 100) : ''}`);
                }
                
                const data = await res.json();
                if (data && data.ok && data.translated) {
                    translatedText = String(data.translated).trim();
                    // 移除翻譯結果中的 HTML 標籤，只保留純文本
                    translatedText = stripHtmlTags(translatedText);
                    
                    // 注意：措詞功能已在伺服器端處理，前端不需要再次處理
                    // 注意：緩存已由伺服器端管理，前端只保留臨時緩存用於避免重複請求
                    
                    // 保存到臨時緩存（僅用於避免同一請求週期內的重複調用）
                    if (useCache) {
                        translationCache[originalText] = translatedText;
                    }
                    
                    // 清除失敗計數（成功後重置）
                    translationFailures.delete(originalText);
                    
                    return translatedText;
                } else if (data && !data.ok) {
                    // API 返回錯誤，拋出異常
                    throw new Error(data.error || '翻譯 API 返回錯誤');
                } else {
                    throw new Error('翻譯 API 響應格式異常');
                }
            } catch (e) {
                // 記錄失敗次數
                const currentFailures = translationFailures.get(originalText) || 0;
                translationFailures.set(originalText, currentFailures + 1);
                
                // API 失敗時拋出錯誤，不進行降級處理
                console.error(`[翻譯] 翻譯請求失敗 (${currentFailures + 1}/${MAX_RETRIES}):`, e.message || e, { text: originalText.substring(0, 50), targetLang });
                throw e; // 重新拋出錯誤，讓調用者處理
            }
        }
        
        // 輔助函數：轉義正則表達式特殊字符
        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
        
        // 輔助函數：從文本中移除 HTML 標籤，只保留純文本
        function stripHtmlTags(text) {
            if (!text || typeof text !== 'string') return text;
            // 創建臨時 DOM 元素來解析和提取純文本
            const temp = document.createElement('div');
            temp.innerHTML = text;
            return temp.textContent || temp.innerText || text.replace(/<[^>]*>/g, '');
        }
        
        // 計算內容哈希值（簡單的字符串哈希）
        function hashContent(content) {
            let hash = 0;
            if (content.length === 0) return hash.toString();
            for (let i = 0; i < content.length; i++) {
                const char = content.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash | 0; // 轉換為32位整數
            }
            return Math.abs(hash).toString(36);
        }
        
        // 圖片 lazy loading 優化（用於大型環境）
        function enableImageLazyLoading(container) {
            if (!container) return;
            
            // 使用 Intersection Observer API 實現 lazy loading
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                                img.removeAttribute('data-src');
                                img.classList.remove('lazy');
                                img.style.opacity = '1';
                                observer.unobserve(img);
                            }
                        }
                    });
                }, {
                    rootMargin: '50px' // 提前 50px 開始加載
                });
                
                // 處理所有使用 data-src 的圖片（已優化的圖片）
                const lazyImages = container.querySelectorAll('img[data-src]');
                lazyImages.forEach(img => {
                    // 設置佔位符
                    if (!img.src || img.src === '') {
                        img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="1" height="1"%3E%3C/svg%3E';
                        img.style.opacity = '0.3';
                        img.style.transition = 'opacity 0.3s';
                    }
                    img.classList.add('lazy');
                    imageObserver.observe(img);
                });
                
                // 處理已經有 src 的圖片（添加 loading="lazy" 屬性作為後備）
                const regularImages = container.querySelectorAll('img:not([data-src]):not([loading])');
                regularImages.forEach(img => {
                    img.loading = 'lazy';
                });
            } else {
                // 後備方案：使用 loading="lazy" 屬性（現代瀏覽器支持）
                const images = container.querySelectorAll('img:not([loading])');
                images.forEach(img => {
                    img.loading = 'lazy';
                });
            }
        }
        
        // 清除翻譯緩存
        function clearTranslationCache(path) {
            // 清除該頁面的內容哈希
            localStorage.removeItem(`${CONTENT_HASH_KEY}_${path}`);
            // 如果當前頁面是該路徑，重置原始內容哈希
            const currentPath = window.location.pathname.split('/').pop() || 'home';
            if (currentPath === path) {
                originalContentHash = null;
            }
        }
        
        async function applyTranslations() {
            // 不再硬編碼 zh-TW，而是根據 languagesData 判斷是否為原生語言
            if (window.languagesData && window.languagesData[currentLang]?.native === true) return;
            if (isTranslatingPage) {
                // 正在翻譯中，跳過重複請求
                return;
            }
            
            isTranslatingPage = true;
            
            try {
                // 確保翻譯數據已載入（措詞數據，用於顯示）
                if (!window.translationsData || !window.translationsData[currentLang]) {
                    try {
                        const transRes = await fetch(`${API_URL}?action=translations_get&lang=${encodeURIComponent(currentLang)}`);
                        if (!transRes.ok) {
                            throw new Error(`翻譯數據 API 響應錯誤: ${transRes.status} ${transRes.statusText}`);
                        }
                        const transData = await transRes.json();
                        if (transData && transData.ok) {
                            if (!window.translationsData) window.translationsData = {};
                            if (transData.lang) {
                                window.translationsData[transData.lang] = transData.translations || {};
                            } else {
                                window.translationsData[currentLang] = transData.translations || {};
                            }
                        }
                    } catch (e) {
                        console.warn('載入翻譯數據失敗:', e.message || e, { lang: currentLang });
                    }
                }
                
                // 收集所有需要翻譯的文本（去重）
                const textsToTranslate = new Map();
                
                // 收集導航菜單文本
                document.querySelectorAll('.nav-link-text').forEach(el => {
                    const text = el.textContent.trim();
                    if (text && text.length > 0) {
                        textsToTranslate.set(text, { element: el, type: 'nav' });
                    }
                });
                
                // 收集頁面內容文本
                const content = document.getElementById('mainContent');
                if (!content) {
                    console.warn('[翻譯] mainContent 不存在，跳過翻譯');
                    isTranslatingPage = false;
                    return;
                }
                
                // 檢查內容是否已載入（等待區塊編輯器渲染完成）
                const contentText = content.textContent.trim();
                if (contentText === '' || contentText.length < 10) {
                    console.warn('[翻譯] mainContent 內容為空或過短，可能還在載入中，延遲重試');
                    // 延遲重試
                    setTimeout(() => {
                        if (!isTranslatingPage) {
                            applyTranslations().catch(e => {
                                console.error('[翻譯] 延遲重試失敗:', e);
                            });
                        }
                    }, 500);
                    isTranslatingPage = false;
                    return;
                }
                
                // 排除不應該翻譯的元素
                const excludeSelectors = 'script, style, .gwa-map-embed, .gwa-youtube-embed, .gwa-button-embed, [contenteditable="false"], .gwa-block-editor-data';
                
                // 收集標題（包括帶有子元素的標題，如 <strong>、<span> 等）
                content.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach(el => {
                    // 跳過不應該翻譯的元素
                    if (el.closest(excludeSelectors)) return;
                    
                    // 獲取完整的文本內容（包括所有子元素的文本）
                    const text = el.textContent.trim();
                    if (text && text.length > 0 && text.length < 200) {
                        // 檢查是否已經有相同的文本（避免重複翻譯）
                        // 如果沒有，或者當前元素是更優先的標題（h1 > h2 > h3...）
                        const existing = textsToTranslate.get(text);
                        if (!existing || el.tagName < existing.element.tagName) {
                            textsToTranslate.set(text, { element: el, type: 'heading' });
                        }
                    }
                });
                
                // 收集段落和其他文本
                content.querySelectorAll('p, li, span, div').forEach(el => {
                    // 跳過不應該翻譯的元素
                    if (el.closest(excludeSelectors)) return;
                    // 跳過標題元素內的內容（已在上面處理）
                    if (el.closest('h1, h2, h3, h4, h5, h6')) return;
                    
                    if (el.children.length === 0) {
                        const text = el.textContent.trim();
                        if (text && text.length > 0 && text.length < 200) {
                            // 如果該文本還沒有被收集，或者當前元素更優先（例如是段落而非 div）
                            if (!textsToTranslate.has(text) || el.tagName === 'P' || el.tagName === 'LI') {
                                textsToTranslate.set(text, { element: el, type: 'content' });
                            }
                        }
                    }
                });
                
                // 如果沒有收集到任何文本，提前返回
                if (textsToTranslate.size === 0) {
                    console.log('[翻譯] 沒有需要翻譯的文本');
                    isTranslatingPage = false;
                    return;
                }
                
                console.log(`[翻譯] 開始翻譯，已收集 ${textsToTranslate.size} 個唯一文本`);
                
                // 批量翻譯（按批次順序執行，限制並發）
                const batchSize = 5; // 每批處理 5 個文本
                const textArray = Array.from(textsToTranslate.entries());
                
                for (let i = 0; i < textArray.length; i += batchSize) {
                    const batch = textArray.slice(i, i + batchSize);
                    const batchPromises = batch.map(async ([text, { element }]) => {
                        try {
                            const translated = await translateText(text, currentLang, true);
                            if (translated !== text) {
                                // 如果元素包含子元素（如 <strong>、<span>），需要保留 HTML 結構
                                if (element.children.length > 0) {
                                    // 保存第一個子元素的標籤和樣式
                                    const firstChild = element.children[0];
                                    const tagName = firstChild.tagName.toLowerCase();
                                    const style = firstChild.getAttribute('style') || '';
                                    const className = firstChild.className || '';
                                    
                                    // 如果只有一個子元素且其文本內容等於整個元素的文本，則只替換子元素的文本
                                    if (element.children.length === 1 && firstChild.textContent.trim() === text.trim()) {
                                        firstChild.textContent = translated;
                                    } else {
                                        // 多個子元素或複雜結構：保留第一個子元素的樣式，但替換整個內容
                                        const styleAttr = style ? ` style="${style.replace(/"/g, '&quot;')}"` : '';
                                        const classAttr = className ? ` class="${className}"` : '';
                                        element.innerHTML = `<${tagName}${styleAttr}${classAttr}>${translated}</${tagName}>`;
                                    }
                                } else {
                                    // 沒有子元素，直接替換文本
                                    element.textContent = translated;
                                }
                            }
                        } catch (e) {
                            // 翻譯失敗時，保持原文不變，記錄錯誤
                            console.error(`[翻譯] 翻譯失敗，保持原文: "${text}"`, e);
                            // 不更新元素，保持原始文本
                        }
                    });
                    // 等待當前批次完成後再處理下一批次
                    await Promise.all(batchPromises);
                }
                
                // 注意：緩存已由伺服器端管理，前端不需要保存
                
                // 翻譯完成（移除日誌以減少輸出）
            } finally {
                isTranslatingPage = false;
            }
        }
        
        // 載入語言設定（在頁面載入時）
        loadLanguages();
        
        // 測試函數：直接切換到英文並觸發翻譯（僅用於測試）
        window.testSwitchToEnglish = async function() {
            console.log('[測試] 切換到英文並觸發翻譯');
            currentLang = 'en';
            localStorage.setItem(LANG_KEY, currentLang);
            translationCache = {};
            await loadLanguages();
        };
        
        // 自動化測試：如果 URL 包含 ?test=translate，自動執行翻譯測試
        if (window.location.search.includes('test=translate')) {
            console.log('[自動測試] 檢測到 test=translate 參數，開始自動測試翻譯功能');
            setTimeout(async () => {
                try {
                    console.log('[自動測試] 步驟 1: 切換到英文');
                    await window.testSwitchToEnglish();
                    console.log('[自動測試] 步驟 2: 等待翻譯完成...');
                    await new Promise(resolve => setTimeout(resolve, 5000)); // 等待 5 秒讓翻譯完成
                    console.log('[自動測試] 步驟 3: 檢查翻譯結果');
                    const heading = document.querySelector('#mainContent h1, #mainContent h2');
                    if (heading) {
                        const text = heading.textContent.trim();
                        console.log(`[自動測試] 標題文本: "${text}"`);
                        if (text !== '歡迎' && text.toLowerCase().includes('welcome')) {
                            console.log('[自動測試] ✓ 翻譯成功！標題已被翻譯');
                        } else if (text === '歡迎') {
                            console.warn('[自動測試] ✗ 翻譯失敗：標題仍然是中文');
                        } else {
                            console.log(`[自動測試] ? 標題已變更為: "${text}"`);
                        }
                    }
                    console.log('[自動測試] 測試完成');
                } catch (e) {
                    console.error('[自動測試] 測試失敗:', e);
                }
            }, 2000); // 等待頁面完全載入
        }
        
        // 監聽頁面內容變化，檢測內容變更並重新應用翻譯（限制頻率，防止瘋狂重試）
        if (typeof MutationObserver !== 'undefined') {
            let contentCheckTimer = null;
            let isTranslating = false;
            let lastTranslationTime = 0;
            const MIN_TRANSLATION_INTERVAL = 2000; // 最小翻譯間隔：2秒
            
            const observer = new MutationObserver(() => {
                // 不再硬編碼 zh-TW，而是根據 languagesData 判斷
                if (currentLang && window.languagesData && window.languagesData[currentLang]?.native !== true && !isLoading && !isTranslating) {
                    // 防抖處理，避免頻繁檢查
                    if (contentCheckTimer) clearTimeout(contentCheckTimer);
                    contentCheckTimer = setTimeout(() => {
                        const now = Date.now();
                        // 限制翻譯頻率，防止瘋狂重試
                        if (now - lastTranslationTime < MIN_TRANSLATION_INTERVAL) {
                            return; // 跳過，距離上次翻譯時間太短
                        }
                        
                        const mainContent = document.getElementById('mainContent');
                        if (mainContent) {
                            // 計算當前內容的哈希值
                            const currentContentHash = hashContent(mainContent.innerHTML);
                            
                            // 獲取當前路徑
                            const currentPath = window.location.pathname.split('/').pop() || 'home';
                            const storedHash = localStorage.getItem(`${CONTENT_HASH_KEY}_${currentPath}`);
                            
                            // 如果內容哈希發生變化，說明內容已更新，需要重新翻譯
                            if (storedHash && storedHash !== currentContentHash) {
                                // 檢測到內容變更，重新應用翻譯
                                // 更新哈希值
                                localStorage.setItem(`${CONTENT_HASH_KEY}_${currentPath}`, currentContentHash);
                                originalContentHash = currentContentHash;
                                // 重新應用翻譯
                                isTranslating = true;
                                lastTranslationTime = now;
                                applyTranslations().finally(() => {
                                    isTranslating = false;
                                });
                            } else if (!storedHash) {
                                // 如果沒有儲存的哈希值，保存當前內容的哈希
                                localStorage.setItem(`${CONTENT_HASH_KEY}_${currentPath}`, currentContentHash);
                                originalContentHash = currentContentHash;
                                // 應用翻譯
                                isTranslating = true;
                                lastTranslationTime = now;
                                applyTranslations().finally(() => {
                                    isTranslating = false;
                                });
                            }
                        }
                    }, 500); // 500ms 防抖
                }
            });
            const mainContent = document.getElementById('mainContent');
            if (mainContent) {
                observer.observe(mainContent, { childList: true, subtree: true, characterData: true });
            }
        }
        
        // ========== 購物車系統 ==========
        const CART_KEY = 'gwa_cart';
        let cart = loadCart();

        function loadCart() {
            try {
                const stored = localStorage.getItem(CART_KEY);
                return stored ? JSON.parse(stored) : [];
            } catch (e) {
                return [];
            }
        }

        function saveCart() {
            try {
                localStorage.setItem(CART_KEY, JSON.stringify(cart));
                updateCartUI();
            } catch (e) {
                console.warn('購物車儲存失敗', e);
            }
        }

        function addToCart(path, title, price, quantity = 1) {
            const existing = cart.findIndex(item => item.path === path);
            if (existing >= 0) {
                cart[existing].quantity += quantity;
            } else {
                cart.push({ path, title, price, quantity });
            }
            saveCart();
        }

        function removeFromCart(path) {
            cart = cart.filter(item => item.path !== path);
            saveCart();
            // 如果購物車 Modal 是打開的，重新渲染內容
            const modal = document.getElementById('cartModal');
            if (modal && modal.style.display !== 'none') {
                renderCartContent();
            }
        }

        function updateCartQuantity(path, quantity) {
            const item = cart.find(item => item.path === path);
            if (item) {
                if (quantity <= 0) {
                    removeFromCart(path);
                } else {
                    item.quantity = quantity;
                    saveCart();
                    // 如果購物車 Modal 是打開的，重新渲染內容
                    const modal = document.getElementById('cartModal');
                    if (modal && modal.style.display !== 'none') {
                        renderCartContent();
                    }
                }
            }
        }

        function getCartTotal() {
            return cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        }

        function getCartCount() {
            return cart.reduce((sum, item) => sum + item.quantity, 0);
        }

        function updateCartUI() {
            const badge = document.getElementById('cartBadge');
            const btnCart = document.getElementById('btnCart');
            const count = getCartCount();
            
            if (badge && btnCart) {
                const cartSvg = btnCart.querySelector('svg');
                
                if (count > 0) {
                    const oldCount = parseInt(badge.textContent) || 0;
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.style.display = 'inline-block';
                    
                    // 有商品時隱藏 SVG，只顯示 Badge
                    if (cartSvg) {
                        cartSvg.style.display = 'none';
                    }
                    
                    // 如果數量增加，觸發動畫
                    if (count > oldCount && oldCount > 0) {
                        badge.classList.add('new-item');
                        setTimeout(() => badge.classList.remove('new-item'), 500);
                    }
                } else {
                    badge.style.display = 'none';
                    // 沒有商品時顯示 SVG
                    if (cartSvg) {
                        cartSvg.style.display = 'block';
                    }
                }
            }
        }

        // 購物車按鈕點擊
        const btnCart = document.getElementById('btnCart');
        if (btnCart) {
            btnCart.addEventListener('click', () => {
                showCartModal();
            });
        }

        // WhatsApp 按鈕
        const btnWhatsApp = document.getElementById('btnWhatsApp');
        let whatsappNumber = '';
        async function loadWhatsApp() {
            try {
                const res = await fetch(`${API_URL}?action=whatsapp_get`);
                const data = await res.json();
                if (data && data.ok && data.whatsapp) {
                    whatsappNumber = data.whatsapp;
                    if (btnWhatsApp) {
                        btnWhatsApp.style.display = 'flex';
                        btnWhatsApp.onclick = () => {
                            const url = `https://wa.me/${whatsappNumber.replace(/[^0-9]/g, '')}`;
                            window.open(url, '_blank');
                        };
                    }
                }
            } catch (e) {
                console.warn('載入 WhatsApp 設定失敗', e);
            }
        }
        loadWhatsApp();

        // 購物車 Modal
        function showCartModal() {
            const modal = document.getElementById('cartModal') || createCartModal();
            modal.style.display = 'flex';
            renderCartContent();
        }

        function createCartModal() {
            const modal = document.createElement('div');
            modal.id = 'cartModal';
            modal.className = 'gwa-modal';
            modal.style.display = 'none';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 600px; width: 90%;">
                    <div class="modal-header">
                        <h2>購物車</h2>
                        <button class="modal-close" onclick="this.closest('.gwa-modal').style.display='none'">×</button>
                    </div>
                    <div class="modal-body" id="cartModalBody">
                        <p>載入中...</p>
                    </div>
                    <div class="modal-footer">
                        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                            <strong>總計：<span id="cartTotalSymbol"></span><span id="cartTotal">0</span></strong>
                            <button class="btn btn-ok" id="btnCheckout" style="display:none;">結算</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            modal.querySelector('.modal-close').addEventListener('click', () => {
                modal.style.display = 'none';
            });
            
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.style.display = 'none';
            });
            
            document.getElementById('btnCheckout').addEventListener('click', () => {
                checkout();
            });
            
            return modal;
        }

        function renderCartContent() {
            const body = document.getElementById('cartModalBody');
            const totalEl = document.getElementById('cartTotal');
            const checkoutBtn = document.getElementById('btnCheckout');
            
            if (!body) return;
            
            if (cart.length === 0) {
                body.innerHTML = `
                    <div style="text-align: center; padding: 3rem 1rem;">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity: 0.3; margin: 0 auto 1rem; color: var(--muted);">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
                        </svg>
                        <p style="color: var(--muted); font-size: 16px; margin: 0;">購物車是空的</p>
                    </div>
                `;
                if (totalEl) totalEl.textContent = '0';
                const symbolEl = document.getElementById('cartTotalSymbol');
                if (symbolEl) symbolEl.textContent = CURRENCY_SYMBOL;
                if (checkoutBtn) checkoutBtn.style.display = 'none';
                return;
            }
            
            const html = cart.map(item => `
                <div class="cart-item" data-cart-path="${escapeHtml(item.path)}">
                    <div style="flex: 1; min-width: 0;">
                        <strong style="display: block; margin-bottom: 4px; font-size: 15px;">${escapeHtml(item.title)}</strong>
                        <div style="color: var(--muted); font-size: 13px;">${CURRENCY_SYMBOL}${item.price.toFixed(0)} × ${item.quantity} = ${CURRENCY_SYMBOL}${(item.price * item.quantity).toFixed(0)}</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                        <button class="cart-btn-decrease" data-cart-path="${escapeHtml(item.path)}" title="減少">−</button>
                        <span style="min-width: 24px; text-align: center; font-weight: 600;">${item.quantity}</span>
                        <button class="cart-btn-increase" data-cart-path="${escapeHtml(item.path)}" title="增加">+</button>
                        <button class="cart-btn-remove" data-cart-path="${escapeHtml(item.path)}" style="margin-left: 4px; color: var(--danger);" title="刪除">🗑</button>
                    </div>
                </div>
            `).join('');
            
            body.innerHTML = html;
            
            // 使用事件委派處理按鈕點擊
            body.querySelectorAll('.cart-btn-decrease').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const path = e.target.getAttribute('data-cart-path');
                    if (path) {
                        const item = cart.find(i => i.path === path);
                        if (item) {
                            updateCartQuantity(path, item.quantity - 1);
                        }
                    }
                });
            });
            
            body.querySelectorAll('.cart-btn-increase').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const path = e.target.getAttribute('data-cart-path');
                    if (path) {
                        const item = cart.find(i => i.path === path);
                        if (item) {
                            updateCartQuantity(path, item.quantity + 1);
                        }
                    }
                });
            });
            
            body.querySelectorAll('.cart-btn-remove').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const path = e.target.getAttribute('data-cart-path');
                    if (path) {
                        removeFromCart(path);
                    }
                });
            });
            const symbolEl = document.getElementById('cartTotalSymbol');
            if (symbolEl) {
                symbolEl.textContent = CURRENCY_SYMBOL;
            }
            if (totalEl) {
                totalEl.textContent = getCartTotal().toFixed(0);
                totalEl.style.fontSize = '20px';
                totalEl.style.background = 'linear-gradient(135deg, var(--accent), var(--accent2))';
                totalEl.style.webkitBackgroundClip = 'text';
                totalEl.style.webkitTextFillColor = 'transparent';
                totalEl.style.backgroundClip = 'text';
            }
            if (checkoutBtn) {
                checkoutBtn.style.display = 'flex';
                checkoutBtn.style.padding = '12px 24px';
                checkoutBtn.style.fontSize = '15px';
                checkoutBtn.style.fontWeight = '600';
            }
        }

        async function checkout() {
            if (cart.length === 0) {
                alert('購物車是空的');
                return;
            }
            
            // 顯示結算表單
            const formModal = document.getElementById('checkoutFormModal') || createCheckoutFormModal();
            
            // 更新訂單摘要
            const total = getCartTotal();
            const itemCount = getCartCount();
            const summaryEl = formModal.querySelector('.checkout-summary');
            if (summaryEl) {
                summaryEl.innerHTML = `
                    <h3 style="margin: 0 0 12px; font-size: 16px; color: var(--text);">訂單摘要</h3>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="color: var(--muted);">商品數量</span>
                        <strong>${itemCount} 件</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid rgba(124,92,255,0.2);">
                        <span style="font-size: 18px; font-weight: 600;">總計</span>
                        <strong style="font-size: 24px; background: linear-gradient(135deg, var(--accent), var(--accent2)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">${CURRENCY_SYMBOL}${total.toFixed(0)}</strong>
                    </div>
                `;
            }
            
            // 重置表單
            const form = formModal.querySelector('#checkoutForm');
            if (form) form.reset();
            
            // 載入上次儲存的客戶資訊
            try {
                const savedInfo = localStorage.getItem('gwa_customer_info');
                if (savedInfo) {
                    const customerInfo = JSON.parse(savedInfo);
                    const nameInput = formModal.querySelector('#checkoutName');
                    const phoneInput = formModal.querySelector('#checkoutPhone');
                    const emailInput = formModal.querySelector('#checkoutEmail');
                    const addressInput = formModal.querySelector('#checkoutAddress');
                    
                    if (nameInput && customerInfo.name) nameInput.value = customerInfo.name;
                    if (phoneInput && customerInfo.phone) phoneInput.value = customerInfo.phone;
                    if (emailInput && customerInfo.email) emailInput.value = customerInfo.email;
                    if (addressInput && customerInfo.address) addressInput.value = customerInfo.address;
                }
            } catch (e) {
                console.warn('載入客戶資訊失敗', e);
            }
            
            // 清除錯誤訊息
            formModal.querySelectorAll('.form-error').forEach(el => {
                el.style.display = 'none';
            });
            formModal.querySelectorAll('input, textarea').forEach(el => {
                el.style.borderColor = 'var(--border)';
            });
            
            formModal.style.display = 'flex';
            
            // 聚焦到第一個輸入框
            setTimeout(() => {
                const firstInput = formModal.querySelector('#checkoutName');
                if (firstInput) firstInput.focus();
            }, 100);
        }

        function createCheckoutFormModal() {
            const modal = document.createElement('div');
            modal.id = 'checkoutFormModal';
            modal.className = 'gwa-modal';
            modal.style.display = 'none';
            
            const total = getCartTotal();
            const itemCount = getCartCount();
            
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto;">
                    <div class="modal-header" style="position: sticky; top: 0; background: var(--chrome-top, rgba(11,16,32,0.95)); z-index: 10; border-bottom: 1px solid var(--border);">
                        <h2 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4"></path>
                                <circle cx="8.5" cy="7" r="4"></circle>
                                <path d="M20 8v6M23 11h-6"></path>
                            </svg>
                            結算資訊
                        </h2>
                        <button class="modal-close" onclick="this.closest('.gwa-modal').style.display='none'">×</button>
                    </div>
                    <div class="modal-body" style="padding: 24px;">
                        <!-- 訂單摘要 -->
                        <div class="checkout-summary" style="margin-bottom: 24px; padding: 16px; background: rgba(124,92,255,0.08); border: 1px solid rgba(124,92,255,0.2); border-radius: 12px;">
                            <h3 style="margin: 0 0 12px; font-size: 16px; color: var(--text);">訂單摘要</h3>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span style="color: var(--muted);">商品數量</span>
                                <strong>${itemCount} 件</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid rgba(124,92,255,0.2);">
                                <span style="font-size: 18px; font-weight: 600;">總計</span>
                                <strong style="font-size: 24px; background: linear-gradient(135deg, var(--accent), var(--accent2)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">${CURRENCY_SYMBOL}${total.toFixed(0)}</strong>
                            </div>
                        </div>

                        <!-- 表單 -->
                        <form id="checkoutForm" style="display: flex; flex-direction: column; gap: 20px;">
                            <div class="form-group">
                                <label for="checkoutName" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text);">
                                    姓名 <span style="color: #ff4444;">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="checkoutName" 
                                    name="name" 
                                    required 
                                    autocomplete="name"
                                    placeholder="請輸入您的姓名"
                                    style="width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--text); font-size: 15px; transition: all 0.2s;"
                                    onfocus="this.style.borderColor='var(--accent)'; this.style.boxShadow='0 0 0 3px rgba(124,92,255,0.1)';"
                                    onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none';"
                                >
                                <div class="form-error" id="errorName" style="display: none; color: #ff4444; font-size: 13px; margin-top: 4px;"></div>
                            </div>

                            <div class="form-group">
                                <label for="checkoutPhone" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text);">
                                    電話 <span style="color: #ff4444;">*</span>
                                </label>
                                <input 
                                    type="tel" 
                                    id="checkoutPhone" 
                                    name="phone" 
                                    required 
                                    autocomplete="tel"
                                    placeholder="請輸入您的電話號碼"
                                    style="width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--text); font-size: 15px; transition: all 0.2s;"
                                    onfocus="this.style.borderColor='var(--accent)'; this.style.boxShadow='0 0 0 3px rgba(124,92,255,0.1)';"
                                    onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none';"
                                >
                                <div class="form-error" id="errorPhone" style="display: none; color: #ff4444; font-size: 13px; margin-top: 4px;"></div>
                            </div>

                            <div class="form-group">
                                <label for="checkoutEmail" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text);">
                                    Email <span style="color: var(--muted); font-size: 12px;">(選填)</span>
                                </label>
                                <input 
                                    type="email" 
                                    id="checkoutEmail" 
                                    name="email" 
                                    autocomplete="email"
                                    placeholder="example@email.com"
                                    style="width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--text); font-size: 15px; transition: all 0.2s;"
                                    onfocus="this.style.borderColor='var(--accent)'; this.style.boxShadow='0 0 0 3px rgba(124,92,255,0.1)';"
                                    onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none';"
                                >
                                <div class="form-error" id="errorEmail" style="display: none; color: #ff4444; font-size: 13px; margin-top: 4px;"></div>
                            </div>

                            <div class="form-group">
                                <label for="checkoutAddress" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text);">
                                    地址 <span style="color: var(--muted); font-size: 12px;">(選填)</span>
                                </label>
                                <textarea 
                                    id="checkoutAddress" 
                                    name="address" 
                                    rows="3"
                                    autocomplete="street-address"
                                    placeholder="請輸入您的地址"
                                    style="width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--text); font-size: 15px; resize: vertical; min-height: 80px; transition: all 0.2s; font-family: inherit;"
                                    onfocus="this.style.borderColor='var(--accent)'; this.style.boxShadow='0 0 0 3px rgba(124,92,255,0.1)';"
                                    onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none';"
                                ></textarea>
                            </div>

                            <div class="form-group">
                                <label for="checkoutNote" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text);">
                                    備註 <span style="color: var(--muted); font-size: 12px;">(選填)</span>
                                </label>
                                <textarea 
                                    id="checkoutNote" 
                                    name="note" 
                                    rows="3"
                                    placeholder="如有特殊需求或備註，請在此填寫"
                                    style="width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--text); font-size: 15px; resize: vertical; min-height: 80px; transition: all 0.2s; font-family: inherit;"
                                    onfocus="this.style.borderColor='var(--accent)'; this.style.boxShadow='0 0 0 3px rgba(124,92,255,0.1)';"
                                    onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none';"
                                ></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer" style="position: sticky; bottom: 0; background: var(--chrome-top, rgba(11,16,32,0.95)); border-top: 1px solid var(--border); padding: 16px 24px; display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn" onclick="document.getElementById('checkoutFormModal').style.display='none'" style="padding: 12px 24px;">取消</button>
                        <button type="submit" form="checkoutForm" id="checkoutSubmitBtn" class="btn btn-ok" style="padding: 12px 32px; font-weight: 600; min-width: 120px;">
                            <span id="checkoutSubmitText">提交訂單</span>
                            <span id="checkoutSubmitLoading" style="display: none;">處理中...</span>
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // 關閉按鈕
            modal.querySelector('.modal-close').addEventListener('click', () => {
                modal.style.display = 'none';
            });
            
            // 點擊背景關閉
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.style.display = 'none';
            });
            
            // 表單提交
            const form = modal.querySelector('#checkoutForm');
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                await submitCheckoutForm(modal);
            });
            
            // 表單驗證
            setupFormValidation(modal);
            
            return modal;
        }

        function setupFormValidation(modal) {
            const nameInput = modal.querySelector('#checkoutName');
            const phoneInput = modal.querySelector('#checkoutPhone');
            const emailInput = modal.querySelector('#checkoutEmail');
            
            // 姓名驗證
            nameInput.addEventListener('blur', () => {
                const value = nameInput.value.trim();
                const errorEl = modal.querySelector('#errorName');
                if (!value) {
                    errorEl.textContent = '請輸入姓名';
                    errorEl.style.display = 'block';
                    nameInput.style.borderColor = '#ff4444';
                } else if (value.length < 2) {
                    errorEl.textContent = '姓名至少需要 2 個字元';
                    errorEl.style.display = 'block';
                    nameInput.style.borderColor = '#ff4444';
                } else {
                    errorEl.style.display = 'none';
                    nameInput.style.borderColor = 'var(--border)';
                }
            });
            
            // 電話驗證
            phoneInput.addEventListener('blur', () => {
                const value = phoneInput.value.trim();
                const errorEl = modal.querySelector('#errorPhone');
                const phoneRegex = /^[\d\s\-\+\(\)]+$/;
                if (!value) {
                    errorEl.textContent = '請輸入電話號碼';
                    errorEl.style.display = 'block';
                    phoneInput.style.borderColor = '#ff4444';
                } else if (!phoneRegex.test(value) || value.replace(/\D/g, '').length < 8) {
                    errorEl.textContent = '請輸入有效的電話號碼';
                    errorEl.style.display = 'block';
                    phoneInput.style.borderColor = '#ff4444';
                } else {
                    errorEl.style.display = 'none';
                    phoneInput.style.borderColor = 'var(--border)';
                }
            });
            
            // Email 驗證（選填，但如果填了就要驗證格式）
            emailInput.addEventListener('blur', () => {
                const value = emailInput.value.trim();
                const errorEl = modal.querySelector('#errorEmail');
                if (value) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        errorEl.textContent = '請輸入有效的 Email 地址';
                        errorEl.style.display = 'block';
                        emailInput.style.borderColor = '#ff4444';
                    } else {
                        errorEl.style.display = 'none';
                        emailInput.style.borderColor = 'var(--border)';
                    }
                } else {
                    errorEl.style.display = 'none';
                    emailInput.style.borderColor = 'var(--border)';
                }
            });
        }

        async function submitCheckoutForm(formModal) {
            const form = formModal.querySelector('#checkoutForm');
            const submitBtn = formModal.querySelector('#checkoutSubmitBtn');
            const submitText = formModal.querySelector('#checkoutSubmitText');
            const submitLoading = formModal.querySelector('#checkoutSubmitLoading');
            
            // 獲取表單數據
            const formData = new FormData(form);
            const name = (formData.get('name') || '').trim();
            const phone = (formData.get('phone') || '').trim();
            const email = (formData.get('email') || '').trim();
            const address = (formData.get('address') || '').trim();
            const note = (formData.get('note') || '').trim();
            
            // 基本驗證
            if (!name || name.length < 2) {
                alert('請輸入有效的姓名（至少 2 個字元）');
                formModal.querySelector('#checkoutName').focus();
                return;
            }
            
            if (!phone || phone.replace(/\D/g, '').length < 8) {
                alert('請輸入有效的電話號碼');
                formModal.querySelector('#checkoutPhone').focus();
                return;
            }
            
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('請輸入有效的 Email 地址');
                formModal.querySelector('#checkoutEmail').focus();
                return;
            }
            
            // 顯示載入狀態
            submitBtn.disabled = true;
            submitText.style.display = 'none';
            submitLoading.style.display = 'inline';
            
            try {
                const res = await fetch(`${API_URL}?action=checkout`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        items: cart,
                        customer: { name, phone, email, address, note }
                    })
                });
                
                const data = await res.json();
                if (data && data.ok) {
                    // 儲存客戶資訊到 localStorage 以便下次重用
                    try {
                        const customerInfo = {
                            name: name,
                            phone: phone,
                            email: email,
                            address: address,
                            lastUsed: new Date().toISOString()
                        };
                        localStorage.setItem('gwa_customer_info', JSON.stringify(customerInfo));
                    } catch (e) {
                        console.warn('儲存客戶資訊失敗', e);
                    }
                    
                    // 清空購物車
                    cart = [];
                    saveCart();
                    
                    // 關閉表單 Modal
                    formModal.style.display = 'none';
                    
                    // 關閉購物車 Modal
                    const cartModal = document.getElementById('cartModal');
                    if (cartModal) cartModal.style.display = 'none';
                    
                    // 顯示結算成功頁面
                    const checkoutModal = document.getElementById('checkoutModal') || createCheckoutModal();
                    const checkoutContent = document.getElementById('checkoutContent');
                    if (checkoutContent) {
                        checkoutContent.innerHTML = data.checkout_page || '<p style="text-align: center; color: var(--muted);">請按照指示完成付款</p>';
                    }
                    const orderIdEl = document.getElementById('checkoutOrderId');
                    if (orderIdEl) {
                        orderIdEl.textContent = data.order_id || '';
                    }
                    checkoutModal.style.display = 'flex';
                } else {
                    alert('結算失敗：' + (data.error || '未知錯誤'));
                    submitBtn.disabled = false;
                    submitText.style.display = 'inline';
                    submitLoading.style.display = 'none';
                }
            } catch (e) {
                alert('結算失敗：' + e.message);
                submitBtn.disabled = false;
                submitText.style.display = 'inline';
                submitLoading.style.display = 'none';
            }
        }

        function createCheckoutModal() {
            const modal = document.createElement('div');
            modal.id = 'checkoutModal';
            modal.className = 'gwa-modal';
            modal.style.display = 'none';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 600px; width: 90%;">
                    <div class="modal-header">
                        <h2>✨ 訂單確認</h2>
                        <button class="modal-close" onclick="this.closest('.gwa-modal').style.display='none'">×</button>
                    </div>
                    <div class="modal-body" id="checkoutModalBody" style="position: relative;">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <div style="width: 64px; height: 64px; margin: 0 auto 16px; background: linear-gradient(135deg, var(--ok), #27ae60); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(46,204,113,0.4); animation: successPulse 0.6s ease;">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3">
                                    <path d="M20 6L9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <p style="color: var(--muted); margin: 0;">訂單已成功建立</p>
                        </div>
                        <div id="checkoutContent"></div>
                        <div style="margin-top: 20px; padding: 16px; background: rgba(124,92,255,0.1); border: 1px solid rgba(124,92,255,0.3); border-radius: 12px;">
                            <p style="margin: 0 0 8px; font-size: 13px; color: var(--muted);">訂單編號</p>
                            <p style="margin: 0; font-size: 18px; font-weight: 700; font-family: monospace; background: linear-gradient(135deg, var(--accent), var(--accent2)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;" id="checkoutOrderId"></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-ok" onclick="this.closest('.gwa-modal').style.display='none'" style="width: 100%; padding: 12px; font-size: 15px; font-weight: 600;">關閉</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            modal.querySelector('.modal-close').addEventListener('click', () => {
                modal.style.display = 'none';
            });
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.style.display = 'none';
            });
            
            // 加入成功動畫樣式
            if (!document.getElementById('checkoutAnimations')) {
                const style = document.createElement('style');
                style.id = 'checkoutAnimations';
                style.textContent = `
                    @keyframes successPulse {
                        0% { transform: scale(0); opacity: 0; }
                        50% { transform: scale(1.1); }
                        100% { transform: scale(1); opacity: 1; }
                    }
                `;
                document.head.appendChild(style);
            }
            
            return modal;
        }

        // 在頁面載入時檢查是否為商品頁，顯示「加入購物車」按鈕
        async function checkProductPage() {
            const path = pathFromLocation();
            try {
                const res = await fetch(`${API_URL}?action=page&path=${encodeURIComponent(path)}`);
                const data = await res.json();
                if (data && data.ok && data.page && data.page.type === 'product') {
                    const price = data.page.price || 0;
                    const content = document.getElementById('mainContent');
                    if (content && !content.querySelector('.product-add-cart')) {
                        const btn = document.createElement('button');
                        btn.className = 'product-add-cart';
                        btn.innerHTML = `
                            <strong>🛒 加入購物車 - ${CURRENCY_SYMBOL}${price.toFixed(0)}</strong>
                        `;
                        btn.onclick = () => {
                            addToCart(path, data.page.title || data.page.menu_title || path, price, 1);
                            // 觸發徽章動畫
                            const badge = document.getElementById('cartBadge');
                            if (badge) {
                                badge.classList.add('new-item');
                                setTimeout(() => badge.classList.remove('new-item'), 500);
                            }
                            // 顯示成功提示
                            showCartNotification('已加入購物車！');
                        };
                        content.insertBefore(btn, content.firstChild);
                    }
                }
            } catch (e) {
                console.warn('檢查商品頁失敗', e);
            }
        }

        // 監聽頁面載入完成
        const originalLoadPage = loadPage;
        loadPage = async function(path, pushState) {
            await originalLoadPage(path, pushState);
            setTimeout(checkProductPage, 100);
            setTimeout(renderMaps, 200);
            setTimeout(renderButtons, 200);
        };
        
        // 渲染按鈕
        function renderButtons() {
            const buttons = document.querySelectorAll('.gwa-button-link');
            buttons.forEach(button => {
                if (button.hasAttribute('data-rendered')) return;
                button.setAttribute('data-rendered', 'true');
                
                const pagePath = button.getAttribute('data-page-path');
                if (!pagePath) return;
                
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (typeof loadPage === 'function') {
                        loadPage(pagePath, true);
                    } else {
                        window.location.href = pagePath === 'home' ? basePath : `${basePath}${pagePath}`;
                    }
                });
            });
        }
        
        // 渲染內聯頁面
        async function renderPageEmbeds() {
            const pageEmbeds = document.querySelectorAll('.gwa-page-embed');
            pageEmbeds.forEach(async (embed) => {
                // 檢查是否已經渲染過
                if (embed.hasAttribute('data-rendered')) return;
                embed.setAttribute('data-rendered', 'true');
                
                const pagePath = embed.getAttribute('data-page-path');
                if (!pagePath) return;
                
                const container = embed.querySelector('.gwa-page-container');
                if (!container) return;
                
                // 顯示載入狀態
                const contentPreview = container.querySelector('.gwa-page-content-preview');
                if (contentPreview) {
                    contentPreview.innerHTML = '<div style="text-align: center; color: var(--muted, rgba(232,236,255,0.5)); padding: 40px 20px;">載入中...</div>';
                }
                
                try {
                    // 使用 loadPage 函數載入頁面內容（如果可用），否則使用 API
                    let pageHtml = '';
                    if (typeof loadPage === 'function') {
                        // 嘗試使用現有的 loadPage 邏輯
                        const response = await fetch(`${basePath}${pagePath === 'home' ? '' : pagePath}`);
                        pageHtml = await response.text();
                    } else {
                        // 使用 API 端點
                        const response = await fetch(`api.php?action=page&path=${encodeURIComponent(pagePath)}`);
                        const data = await response.json();
                        if (data.ok && data.html) {
                            pageHtml = data.html;
                        }
                    }
                    
                    if (pageHtml && contentPreview) {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(pageHtml, 'text/html');
                        const mainContent = doc.querySelector('#mainContent');
                        
                        if (mainContent) {
                            // 清理內容，移除不需要的元素
                            const cleanContent = mainContent.cloneNode(true);
                            cleanContent.querySelectorAll('script, style, iframe, .gwa-page-embed').forEach(el => el.remove());
                            
                            // 更新內容預覽
                            contentPreview.innerHTML = '';
                            contentPreview.appendChild(cleanContent);
                            
                            // 重新渲染地圖和 YouTube（如果內聯頁面中包含）
                            setTimeout(() => {
                                renderMaps();
                                if (typeof renderYouTube === 'function') renderYouTube();
                            }, 100);
                        } else {
                            contentPreview.innerHTML = '<div style="text-align: center; color: var(--muted, rgba(232,236,255,0.5)); padding: 20px;">無法解析頁面內容</div>';
                        }
                    } else {
                        if (contentPreview) {
                            contentPreview.innerHTML = '<div style="text-align: center; color: var(--muted, rgba(232,236,255,0.5)); padding: 20px;">無法載入頁面內容</div>';
                        }
                    }
                } catch (err) {
                    console.error('[GWA] 載入內聯頁面失敗:', err);
                    if (contentPreview) {
                        contentPreview.innerHTML = '<div style="text-align: center; color: var(--muted, rgba(232,236,255,0.5)); padding: 20px;">載入失敗</div>';
                    }
                }
            });
        }
        
        // 渲染地圖
        function renderMaps() {
            const mapContainers = document.querySelectorAll('.gwa-map-embed .gwa-map-container');
            mapContainers.forEach((container, index) => {
                const mapEmbed = container.closest('.gwa-map-embed');
                if (!mapEmbed) return;
                
                // 檢查是否已經渲染過
                if (container.hasAttribute('data-rendered')) return;
                container.setAttribute('data-rendered', 'true');
                
                // 兼容舊格式和新格式
                let landmarksJson = mapEmbed.getAttribute('data-landmarks');
                if (!landmarksJson) {
                    landmarksJson = mapEmbed.getAttribute('data-addresses');
                }
                const height = parseInt(mapEmbed.getAttribute('data-height') || '400', 10);
                const style = mapEmbed.getAttribute('data-style') || 'light';
                
                if (!landmarksJson) return;
                
                let landmarks = [];
                try {
                    const parsed = JSON.parse(landmarksJson);
                    if (Array.isArray(parsed)) {
                        // 兼容舊格式（字符串數組）和新格式（對象數組）
                        landmarks = parsed.map(item => {
                            if (typeof item === 'string') {
                                return { address: item, description: '' };
                            }
                            return {
                                address: item.address || '',
                                description: item.description || ''
                            };
                        });
                    }
                } catch (e) {
                    console.error('無法解析地標數據', e);
                    return;
                }
                
                if (!Array.isArray(landmarks) || landmarks.length === 0) return;
                
                // 過濾有效地址
                const validLandmarks = landmarks.filter(l => l.address && l.address.trim());
                if (validLandmarks.length === 0) return;
                
                // 設置容器高度
                container.style.height = height + 'px';
                
                // 創建地圖
                const map = L.map(container, {
                    zoomControl: true,
                    attributionControl: true
                });
                
                // 根據樣式選擇圖層
                let tileUrl = '';
                let attribution = '';
                
                switch (style) {
                    case 'dark':
                        tileUrl = 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';
                        attribution = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>';
                        break;
                    case 'satellite':
                        tileUrl = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
                        attribution = '&copy; <a href="https://www.esri.com/">Esri</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';
                        break;
                    default: // light
                        tileUrl = 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
                        attribution = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>';
                }
                
                L.tileLayer(tileUrl, {
                    attribution: attribution,
                    subdomains: style === 'satellite' ? undefined : 'abcd',
                    maxZoom: 19
                }).addTo(map);
                
                // 使用自定義 Geocoder（添加延遲以避免 403 速率限制）
                function geocodeAddress(address, callback, delay = 0) {
                    const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(address)}&limit=5&format=json&addressdetails=1`;
                    
                    setTimeout(() => {
                        fetch(url, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json'
                            }
                        })
                        .then(response => {
                            if (!response.ok) {
                                // 如果 403，記錄錯誤但不中斷
                                if (response.status === 403) {
                                    console.warn('Nominatim 403 錯誤（可能觸發速率限制）:', address);
                                    callback([]);
                                    return;
                                }
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data && Array.isArray(data) && data.length > 0) {
                                callback(data.map(item => ({
                                    name: item.display_name,
                                    center: L.latLng(parseFloat(item.lat), parseFloat(item.lon)),
                                    bbox: item.boundingbox ? L.latLngBounds(
                                        [parseFloat(item.boundingbox[0]), parseFloat(item.boundingbox[2])],
                                        [parseFloat(item.boundingbox[1]), parseFloat(item.boundingbox[3])]
                                    ) : null
                                })));
                            } else {
                                callback([]);
                            }
                        })
                        .catch(err => {
                            console.error('Geocoding 錯誤:', err);
                            callback([]);
                        });
                    }, delay);
                }
                
                const bounds = L.latLngBounds([]);
                let geocodedCount = 0;
                
                validLandmarks.forEach((landmark, idx) => {
                    // 每個請求間隔 1 秒，避免觸發速率限制
                    geocodeAddress(landmark.address, (results) => {
                        if (results && results.length > 0) {
                            const result = results[0];
                            const latlng = result.center;
                            
                            // 構建彈窗內容
                            let popupContent = `<strong>地標 ${idx + 1}</strong><br>${landmark.address}`;
                            if (landmark.description && landmark.description.trim()) {
                                popupContent += `<br><small style="color: #666;">${landmark.description}</small>`;
                            }
                            
                            // 添加標記
                            const marker = L.marker(latlng, {
                                icon: L.divIcon({
                                    className: 'custom-marker',
                                    html: `<div style="background: #4285f4; color: white; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);">${idx + 1}</div>`,
                                    iconSize: [32, 32],
                                    iconAnchor: [16, 16]
                                })
                            }).addTo(map);
                            
                            marker.bindPopup(popupContent);
                            bounds.extend(latlng);
                            
                            geocodedCount++;
                            if (geocodedCount === validLandmarks.length) {
                                // 所有地址解析完成，智能縮放
                                if (bounds.isValid()) {
                                    map.fitBounds(bounds, {
                                        padding: [50, 50],
                                        maxZoom: 16
                                    });
                                }
                            }
                        }
                    });
                });
                
                // 如果沒有結果，設置默認視圖（香港）
                setTimeout(() => {
                    if (geocodedCount === 0 && validLandmarks.length > 0) {
                        map.setView([22.3193, 114.1694], 13);
                    }
                }, 3000);
            });
        }
        
        // 初始渲染地圖和按鈕
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(renderMaps, 300);
                setTimeout(renderButtons, 300);
            });
        } else {
            setTimeout(renderMaps, 300);
            setTimeout(renderButtons, 300);
        }
        
        // 購物車通知
        function showCartNotification(message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                padding: 14px 20px;
                background: linear-gradient(135deg, var(--accent), var(--accent2));
                color: white;
                border-radius: 12px;
                box-shadow: 0 8px 24px rgba(124,92,255,0.4);
                z-index: 1001;
                font-weight: 600;
                animation: slideInRight 0.3s ease, slideOutRight 0.3s ease 2.7s;
                pointer-events: none;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        // 加入動畫樣式
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // 初始載入
        setTimeout(checkProductPage, 500);
        updateCartUI();
    </script>
</body>
</html>



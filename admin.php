<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Cms.php';
require_once __DIR__ . '/app/Auth.php';
require_once __DIR__ . '/app/Settings.php';
require_once __DIR__ . '/app/GoogleFonts.php';

$basePath = gwa_base_path();
$cms = new Cms(__DIR__);
$auth = new Auth(__DIR__);
$settings = new Settings(__DIR__);
$theme = $settings->getTheme();
$themes = $settings->themes();
$brand = $settings->getBrand();

// 版本檢測：根據三個核心檔案的最後修改時間
$coreFiles = [
    __DIR__ . DIRECTORY_SEPARATOR . 'index.php',
    __DIR__ . DIRECTORY_SEPARATOR . 'admin.php',
    __DIR__ . DIRECTORY_SEPARATOR . 'api.php'
];
$latestMtime = 0;
foreach ($coreFiles as $file) {
    if (file_exists($file)) {
        $mtime = filemtime($file);
        if ($mtime > $latestMtime) {
            $latestMtime = $mtime;
        }
    }
}
$version = date('Y-m-d-Hi', $latestMtime);

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postAction = (string)($_POST['action'] ?? '');

    try {
        if ($postAction === 'set_password') {
            $password = (string)($_POST['password'] ?? '');
            $auth->setPassword($password);
            $auth->login($password);
            header('Location: ' . $basePath . 'admin.php');
            exit;
        }

        if ($postAction === 'login') {
            $password = (string)($_POST['password'] ?? '');
            if ($auth->login($password)) {
                header('Location: ' . $basePath . 'admin.php');
                exit;
            }
            $error = '密碼錯誤';
        }

        if ($postAction === 'logout') {
            if ($auth->isLoggedIn()) {
                $auth->requireCsrfFromRequest();
                $auth->logout();
            }
            header('Location: ' . $basePath . 'admin.php');
            exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$configured = $auth->isConfigured();
$loggedIn = $auth->isLoggedIn();
$csrf = $loggedIn ? $auth->csrfToken() : '';
$pages = $cms->getPages();

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理後台</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($basePath . 'assets/base.css'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($basePath . 'assets/admin.css'); ?>">
    <link rel="stylesheet" id="themeCss" href="<?php echo htmlspecialchars($basePath . 'assets/themes/' . $theme . '.css'); ?>">
    <?php echo GoogleFonts::linkTag($settings->getTypography()); ?>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        .block-editor-wrap { margin-top: 16px; }
        .block-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 20px; }
        .block-item { position: relative; background: var(--page-bg, var(--editor-bg, var(--bg))); border: 2px dashed var(--border); border-radius: 12px; padding: 16px; transition: all 0.3s; cursor: move; }
        .block-item-main { border-color: var(--accent); border-style: solid; }
        .block-item:hover { border-color: var(--accent); border-style: solid; box-shadow: 0 4px 16px rgba(124,92,255,0.15); }
        .block-item.dragging { opacity: 0.5; transform: rotate(2deg); }
        .block-actions { position: absolute; top: 8px; right: 8px; display: flex; gap: 6px; z-index: 10; opacity: 0; transition: opacity 0.2s; }
        .block-item:hover .block-actions { opacity: 1; }
        .block-actions button { width: 28px; height: 28px; border-radius: 6px; border: none; background: var(--accent); color: white; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .block-actions button:hover { transform: scale(1.1); background: var(--accent2); }
        .block-actions button.danger { background: #e74c3c; }
        .block-toolbar-wrap { margin-bottom: 8px; }
        .block-toolbar { border: 1px solid var(--border); border-radius: 8px 8px 0 0; }
        .block-toolbar-compact { padding: 4px 8px; }
        .block-toolbar-compact .ql-formats { margin-right: 8px; }
        .block-toolbar-compact .ql-formats:last-child { margin-right: 0; }
        .block-toolbar-compact button, .block-toolbar-compact select { padding: 4px 6px; font-size: 13px; }
        .block-editor { min-height: 200px; }
        .block-editor .ql-container { border: 1px solid var(--border); border-top: none; border-radius: 0 0 8px 8px; font-family: inherit; background: var(--editor-bg, #fff) !important; }
        .block-editor .ql-editor { padding: 12px; min-height: 150px; color: var(--editor-text, #0c1222) !important; background: var(--editor-bg, #fff) !important; }
        .block-editor .ql-editor p:not([style*="color"]),
        .block-editor .ql-editor div:not([style*="color"]),
        .block-editor .ql-editor h1:not([style*="color"]),
        .block-editor .ql-editor h2:not([style*="color"]),
        .block-editor .ql-editor h3:not([style*="color"]),
        .block-editor .ql-editor h4:not([style*="color"]),
        .block-editor .ql-editor h5:not([style*="color"]),
        .block-editor .ql-editor h6:not([style*="color"]),
        .block-editor .ql-editor li:not([style*="color"]),
        .block-editor .ql-editor span:not([style*="color"]),
        .block-editor .ql-editor strong:not([style*="color"]),
        .block-editor .ql-editor em:not([style*="color"]),
        .block-editor .ql-editor u:not([style*="color"]),
        .block-editor .ql-editor a:not([style*="color"]) { color: var(--editor-text, #0c1222) !important; }
        .block-editor .ql-editor u { text-decoration-color: currentColor !important; }
        .block-settings { display: none; position: absolute; top: 40px; right: 0; background: var(--bg); border: 2px solid var(--border); border-radius: 8px; padding: 16px; min-width: 280px; max-width: 320px; z-index: 20; box-shadow: 0 8px 24px rgba(0,0,0,0.15); max-height: 80vh; overflow-y: auto; }
        .block-settings.show { display: block; }
        .block-settings .field { margin-bottom: 12px; }
        .block-settings .field-group { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border); }
        .block-settings .field-group-title { font-size: 13px; font-weight: 600; color: var(--accent); margin-bottom: 12px; }
        .block-settings label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px; font-weight: 500; }
        .block-settings input, .block-settings select { width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text); font-size: 13px; }
        .block-settings .help-text { font-size: 11px; color: var(--muted); margin-top: 4px; }
        /* 直屏響應式：默認一行一列 */
        @media (max-width: 768px) {
            .block-grid { grid-template-columns: 1fr !important; }
            .block-item { grid-column: span 1 !important; }
        }
        /* 圖片編輯器樣式 */
        #editorCanvas { cursor: crosshair; }
        #cropOverlay { background: rgba(124, 92, 255, 0.1); border-color: var(--accent); }
        #imageEditor input[type="range"] { accent-color: var(--accent); }
        #imageEditor input[type="range"]::-webkit-slider-thumb { cursor: pointer; }
        #imageEditor input[type="range"]::-moz-range-thumb { cursor: pointer; }
        @media (max-width: 768px) {
            #imageEditor > div:first-child { grid-template-columns: 1fr !important; }
        }
        .editor-actions .draft-actions { display: flex; align-items: center; gap: 10px; margin-right: 12px; }
        .editor-actions .draft-badge { font-size: 12px; color: var(--accent); background: rgba(124,92,255,0.15); padding: 4px 10px; border-radius: 8px; }
        .editor-actions #btnRestoreLive { font-size: 12px; padding: 6px 12px; }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="container">
            <div class="topbar-inner">
                <div class="title">
                    <div class="logo" aria-hidden="true"></div>
                    <div>
                        <h1>管理後台</h1>
                        <small>版本: <?php echo htmlspecialchars($version); ?></small>
                    </div>
                </div>
                <div class="row">
                    <a class="btn" href="<?php echo htmlspecialchars($basePath); ?>" target="_blank">查看網站</a>
                    <?php if ($loggedIn): ?>
                        <button type="button" class="btn" id="btnChangePassword">更改密碼</button>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="logout">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                            <button type="submit" class="btn btn-danger">登出</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (!$configured): ?>
            <div class="auth panel">
                <h2>初始化管理員密碼</h2>
                <p>這個專案不再提供預設密碼。請先設定一組管理員密碼（至少 10 字元）。</p>
                <form method="POST">
                    <input type="hidden" name="action" value="set_password">
                    <div class="field">
                        <label for="password">新密碼</label>
                        <input type="password" id="password" name="password" required minlength="10">
                    </div>
                    <button type="submit" class="btn btn-ok">設定並登入</button>
                </form>
                <?php if ($error): ?><div class="msg err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            </div>
        <?php elseif (!$loggedIn): ?>
            <div class="auth panel">
                <h2>管理員登入</h2>
                <p>請輸入你設定的管理員密碼。</p>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="field">
                        <label for="password">密碼</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-ok">登入</button>
                </form>
                <?php if ($error): ?><div class="msg err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid">
                <div class="panel">
                    <div class="row" style="justify-content: space-between; align-items: center;">
                        <strong>頁面</strong>
                        <div class="selection-tabs">
                            <button class="selection-tab active" data-selection="content" type="button">內容</button>
                            <button class="selection-tab" data-selection="wrapper" type="button">包裝</button>
                            <button class="selection-tab" data-selection="translations" type="button">措辭</button>
                            <button class="selection-tab" data-selection="orders" type="button">訂單</button>
                            <button class="selection-tab" data-selection="trash" type="button">回收</button>
                        </div>
                    </div>
                    
                    <div id="selectionContent" class="selection-content active">
                        <div class="row" style="justify-content: space-between; align-items: center; margin-top: 12px;">
                            <span></span>
                        <button class="btn" type="button" id="btnNew">新增</button>
                    </div>
                        <div class="msg" id="statusMsg" style="display:none;"></div>
                        <div class="msg" style="margin-top: 12px;">
                            父層 / 排序 直接拖放與修改。
                    </div>
                        <div id="navEditor" class="nav-editor"></div>
                    </div>
                    
                    <div id="selectionWrapper" class="selection-content" style="display:none;">
                    <div class="field" style="margin-top: 12px;">
                        <label for="themeSelect">主題風格</label>
                        <select id="themeSelect">
                            <?php foreach ($themes as $t): ?>
                                <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $t === $theme ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>切換後會立即套用到前台/後台。</small>
                    </div>
                        <div class="field" style="margin-top: 16px;">
                            <button type="button" class="btn" id="btnTypography" style="width: 100%;">協調字體</button>
                            <small style="display: block; margin-top: 6px;">編輯全局文字：Normal、標題1、標題2、標題3 的預設字體、大小、顏色、粗度（會套用至現有網頁內容區）。</small>
                        </div>
                        <div class="field" style="margin-top: 12px;">
                            <label for="brandTitle">網站標題 (Brand Title)</label>
                            <input id="brandTitle" type="text" value="<?php echo htmlspecialchars($brand['title']); ?>" placeholder="例如：General Web App">
                            <small>顯示在網站 header 的標題。</small>
                    </div>
                        <div class="field">
                            <label for="brandSubtitle">網站副標題 (Brand Subtitle)</label>
                            <input id="brandSubtitle" type="text" value="<?php echo htmlspecialchars($brand['subtitle']); ?>" placeholder="例如：檔案式 CMS / 導覽樹 / Quill 編輯">
                            <small>顯示在網站 header 的副標題。</small>
                        </div>
                        <div class="field" style="margin-top: 24px;">
                            <label>Logo + Icon 設置</label>
                            <input type="file" id="brandImageUpload" accept="image/*" style="display: none;">
                            <button type="button" class="btn" id="btnUploadBrandImage" style="width: 100%; margin-bottom: 12px;">上傳圖片（自動生成 Logo + Icon）</button>
                            
                            <!-- 圖片編輯器 -->
                            <div id="imageEditor" style="display: none; margin-top: 16px; padding: 16px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px;">
                                <div style="display: grid; grid-template-columns: 1fr 300px; gap: 16px; margin-bottom: 16px;">
                                    <div>
                                        <div style="font-size: 12px; color: var(--muted); margin-bottom: 8px;">編輯區域</div>
                                        <div style="position: relative; border: 2px solid var(--border); border-radius: 8px; overflow: hidden; background: var(--bg); max-height: 400px; display: flex; align-items: center; justify-content: center;">
                                            <canvas id="editorCanvas" style="max-width: 100%; max-height: 400px; display: block;"></canvas>
                                            <div id="cropOverlay" style="position: absolute; border: 2px dashed var(--accent); pointer-events: none; display: none;"></div>
                                        </div>
                                        <div style="margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap;">
                                            <button type="button" class="btn" id="btnCrop" style="flex: 1; min-width: 80px;">裁剪</button>
                                            <button type="button" class="btn" id="btnResetCrop" style="flex: 1; min-width: 80px;">重置</button>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: var(--muted); margin-bottom: 8px;">調整工具</div>
                                        <div style="display: flex; flex-direction: column; gap: 12px;">
                                            <div>
                                                <label style="display: block; font-size: 11px; color: var(--muted); margin-bottom: 4px;">大小縮放 (%)</label>
                                                <input type="range" id="sliderScale" min="10" max="200" value="100" style="width: 100%;">
                                                <div style="display: flex; justify-content: space-between; font-size: 10px; color: var(--muted);">
                                                    <span>10%</span>
                                                    <span id="scaleValue">100%</span>
                                                    <span>200%</span>
                                                </div>
                                            </div>
                                            <div>
                                                <label style="display: block; font-size: 11px; color: var(--muted); margin-bottom: 4px;">亮度</label>
                                                <input type="range" id="sliderBrightness" min="-100" max="100" value="0" style="width: 100%;">
                                                <div style="display: flex; justify-content: space-between; font-size: 10px; color: var(--muted);">
                                                    <span>-100</span>
                                                    <span id="brightnessValue">0</span>
                                                    <span>100</span>
                                                </div>
                                            </div>
                                            <div>
                                                <label style="display: block; font-size: 11px; color: var(--muted); margin-bottom: 4px;">對比度</label>
                                                <input type="range" id="sliderContrast" min="-100" max="100" value="0" style="width: 100%;">
                                                <div style="display: flex; justify-content: space-between; font-size: 10px; color: var(--muted);">
                                                    <span>-100</span>
                                                    <span id="contrastValue">0</span>
                                                    <span>100</span>
                                                </div>
                                            </div>
                                            <div>
                                                <label style="display: block; font-size: 11px; color: var(--muted); margin-bottom: 4px;">飽和度</label>
                                                <input type="range" id="sliderSaturation" min="-100" max="100" value="0" style="width: 100%;">
                                                <div style="display: flex; justify-content: space-between; font-size: 10px; color: var(--muted);">
                                                    <span>-100</span>
                                                    <span id="saturationValue">0</span>
                                                    <span>100</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="display: flex; gap: 24px; flex-wrap: wrap; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border);">
                                    <div style="flex: 1; min-width: 200px;">
                                        <div style="font-size: 12px; color: var(--muted); margin-bottom: 8px;">Logo 預覽 (200×80)</div>
                                        <div id="logoPreview" style="width: 200px; height: 80px; border: 2px solid var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; background: var(--bg); overflow: hidden;">
                                            <span style="color: var(--muted); font-size: 12px;">無 Logo</span>
                                        </div>
                                    </div>
                                    <div style="flex: 1; min-width: 100px;">
                                        <div style="font-size: 12px; color: var(--muted); margin-bottom: 8px;">Icon 預覽 (64×64)</div>
                                        <div id="iconPreview" style="width: 64px; height: 64px; border: 2px solid var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; background: var(--bg); overflow: hidden;">
                                            <span style="color: var(--muted); font-size: 10px;">無 Icon</span>
                                        </div>
                                    </div>
                                </div>
                                <div style="margin-top: 16px; display: flex; gap: 8px;">
                                    <button type="button" class="btn btn-ok" id="btnApplyBrandImage" style="flex: 1;">應用設置</button>
                                    <button type="button" class="btn" id="btnCancelBrandImage" style="flex: 1;">取消</button>
                                </div>
                            </div>
                            
                            <div id="brandImagePreview" style="display: none; margin-top: 16px; padding: 16px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px;">
                                <div style="display: flex; gap: 24px; flex-wrap: wrap;">
                                    <div style="flex: 1; min-width: 200px;">
                                        <div style="font-size: 12px; color: var(--muted); margin-bottom: 8px;">Logo 預覽 (200×80)</div>
                                        <div id="logoPreviewOld" style="width: 200px; height: 80px; border: 2px solid var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; background: var(--bg); overflow: hidden;">
                                            <span style="color: var(--muted); font-size: 12px;">無 Logo</span>
                                        </div>
                                    </div>
                                    <div style="flex: 1; min-width: 100px;">
                                        <div style="font-size: 12px; color: var(--muted); margin-bottom: 8px;">Icon 預覽 (64×64)</div>
                                        <div id="iconPreviewOld" style="width: 64px; height: 64px; border: 2px solid var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; background: var(--bg); overflow: hidden;">
                                            <span style="color: var(--muted); font-size: 10px;">無 Icon</span>
                                        </div>
                                    </div>
                                </div>
                                <div style="margin-top: 16px; display: flex; gap: 8px;">
                                    <button type="button" class="btn btn-ok" id="btnApplyBrandImageOld" style="flex: 1;">應用設置</button>
                                    <button type="button" class="btn" id="btnCancelBrandImageOld" style="flex: 1;">取消</button>
                                </div>
                            </div>
                            <div id="brandImageCurrent" style="display: none; margin-top: 12px; padding: 12px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px;">
                                <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                                    <div>
                                        <div style="font-size: 12px; color: var(--muted); margin-bottom: 4px;">當前 Logo</div>
                                        <div id="currentLogoPreview" style="width: 100px; height: 40px; border: 1px solid var(--border); border-radius: 4px; overflow: hidden; background: var(--bg);"></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: var(--muted); margin-bottom: 4px;">當前 Icon</div>
                                        <div id="currentIconPreview" style="width: 48px; height: 48px; border: 1px solid var(--border); border-radius: 4px; overflow: hidden; background: var(--bg);"></div>
                                    </div>
                                    <div style="flex: 1;"></div>
                                    <button type="button" class="btn" id="btnRemoveBrandImage">移除</button>
                                </div>
                            </div>
                            <small>上傳一張圖片，系統會自動生成 Logo (200×80) 和 Icon (64×64)。</small>
                        </div>
                        <div class="row" style="margin-top: 12px;">
                            <button class="btn btn-ok" type="button" id="btnSaveBrand">儲存品牌設定</button>
                        </div>
                        <div class="field" style="margin-top: 24px;">
                            <label>頁尾內容 (Footer)</label>
                            <div class="editor-wrap" style="margin-top: 8px;">
                                <div id="footerToolbar" class="ql-toolbar ql-snow">
                                    <span class="ql-formats">
                                        <button class="ql-bold"></button>
                                        <button class="ql-italic"></button>
                                        <button class="ql-underline"></button>
                                        <button class="ql-strike"></button>
                                    </span>
                                    <span class="ql-formats">
                                        <select class="ql-color"></select>
                                        <select class="ql-background"></select>
                                    </span>
                                    <span class="ql-formats">
                                        <button class="ql-link"></button>
                                    </span>
                                    <span class="ql-formats">
                                        <button class="ql-list" value="ordered"></button>
                                        <button class="ql-list" value="bullet"></button>
                                    </span>
                                    <span class="ql-formats">
                                        <select class="ql-align"></select>
                                    </span>
                                </div>
                                <div id="footerEditor" style="min-height: 120px;"></div>
                            </div>
                            <small>編輯頁尾內容，支援富文本格式。</small>
                        </div>
                        <div class="row" style="margin-top: 12px;">
                            <button class="btn btn-ok" type="button" id="btnSaveFooter">儲存頁尾</button>
                        </div>
                        <div class="field" style="margin-top: 24px;">
                            <label for="whatsappNumber">WhatsApp 客服號碼</label>
                            <input id="whatsappNumber" type="text" placeholder="例如：886912345678" value="">
                            <small>輸入 WhatsApp 號碼（含國碼，例如：886912345678），前台將顯示 WhatsApp 按鈕。</small>
                        </div>
                        <div class="row" style="margin-top: 12px;">
                            <button class="btn btn-ok" type="button" id="btnSaveWhatsApp">儲存 WhatsApp</button>
                        </div>
                        <div class="field" style="margin-top: 24px;">
                            <label>結算畫面內容</label>
                            <div class="editor-wrap" style="margin-top: 8px;">
                                <div id="checkoutToolbar" class="ql-toolbar ql-snow">
                                    <span class="ql-formats">
                                        <button class="ql-bold"></button>
                                        <button class="ql-italic"></button>
                                        <button class="ql-underline"></button>
                                    </span>
                                    <span class="ql-formats">
                                        <button class="ql-list" value="ordered"></button>
                                        <button class="ql-list" value="bullet"></button>
                                    </span>
                                </div>
                                <div id="checkoutEditor" style="min-height: 120px;"></div>
                            </div>
                            <small>編輯結算完成後顯示的內容，可包含付款指示等資訊。</small>
                        </div>
                        <div class="row" style="margin-top: 12px;">
                            <button class="btn btn-ok" type="button" id="btnSaveCheckout">儲存結算畫面</button>
                        </div>
                        <div class="field" style="margin-top: 24px;">
                            <label for="currencySelect">貨幣設定</label>
                            <select id="currencySelect">
                                <option value="HKD">HKD (HK$)</option>
                                <option value="TWD">TWD (NT$)</option>
                                <option value="USD">USD ($)</option>
                                <option value="CNY">CNY (¥)</option>
                                <option value="JPY">JPY (¥)</option>
                                <option value="EUR">EUR (€)</option>
                                <option value="GBP">GBP (£)</option>
                                <option value="SGD">SGD (S$)</option>
                            </select>
                            <small>設定前台顯示的貨幣符號（預設：HKD）。</small>
                        </div>
                        <div class="row" style="margin-top: 12px;">
                            <button class="btn btn-ok" type="button" id="btnSaveCurrency">儲存貨幣設定</button>
                        </div>
                        <div class="field" style="margin-top: 24px;">
                            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                                <input type="checkbox" id="maintenanceMode" style="width: auto; cursor: pointer;">
                                <span>啟用維護模式 (Maintenance Mode)</span>
                            </label>
                            <small>啟用後，非管理員訪客將看到「正在升級中」的頁面。</small>
                        </div>
                        <div class="row" style="margin-top: 12px;">
                            <button class="btn btn-ok" type="button" id="btnSaveMaintenance">儲存維護模式設定</button>
                        </div>
                    </div>
                    
                    <div id="selectionTranslations" class="selection-content" style="display:none;">
                        <div style="margin-bottom: 20px;">
                            <h2 style="margin: 0 0 16px;">多語言設置</h2>
                            <p style="color: var(--muted); margin-bottom: 20px;">設置網站支援的語言，並可為非原生語言配置手動翻譯。</p>
                        </div>
                        
                        <div class="field">
                            <label for="defaultLanguage">預設主力語言</label>
                            <select id="defaultLanguage" style="width: 100%;">
                                <option value="zh-TW">繁體中文</option>
                                <option value="en">English</option>
                                <option value="zh-CN">简体中文</option>
                                <option value="ja">日本語</option>
                            </select>
                            <small>設定網站預設顯示的語言。</small>
                        </div>
                        
                        <div class="field" style="margin-top: 24px;">
                            <label>支援的語言</label>
                            <div id="languagesList" style="margin-top: 8px;">
                                <!-- 語言列表將由 JavaScript 動態生成 -->
                            </div>
                            <button class="btn" type="button" id="btnAddLanguage" style="margin-top: 12px;">新增語言</button>
                        </div>
                        
                        <div class="field" style="margin-top: 24px;">
                            <label for="translationLang">措辭字串（手動翻譯覆蓋）</label>
                            <div style="margin-top: 8px;">
                                <select id="translationLang" name="translationLang" style="width: 100%; margin-bottom: 12px;">
                                    <option value="">選擇語言</option>
                                </select>
                                <div style="margin-bottom: 12px;">
                                    <button class="btn" type="button" id="btnAddTranslation" style="width: 100%;">新增措辭字串</button>
                                </div>
                                <div id="translationsList" style="margin-top: 12px;">
                                    <!-- 翻譯列表將由 JavaScript 動態生成 -->
                                </div>
                            </div>
                            <small>系統會先自動翻譯內容，然後使用您在此配置的「措辭字串」修正翻譯結果。請輸入「翻譯後的文本」作為 key，「修正後的措辭」作為 value。</small>
                        </div>
                        
                        <div class="row" style="margin-top: 24px;">
                            <button class="btn btn-ok" type="button" id="btnSaveTranslations">儲存語言設定</button>
                        </div>
                    </div>
                    
                    <div id="selectionTrash" class="selection-content" style="display:none;">
                        <div style="margin-bottom: 20px;">
                            <h2 style="margin: 0 0 16px; display: flex; align-items: center; gap: 8px;">
                                🗑️ 回收站
                            </h2>
                            <p style="color: var(--muted); margin-bottom: 20px;">已刪除的頁面可以在這裡恢復。</p>
                        </div>
                        <div id="trashList" style="display: flex; flex-direction: column; gap: 12px;">
                            <!-- 回收站列表將由 JavaScript 動態生成 -->
                        </div>
                    </div>
                    
                    <div id="selectionOrders" class="selection-content" style="display:none;">
                        <div style="margin-bottom: 20px;">
                            <h2 style="margin: 0 0 16px; display: flex; align-items: center; gap: 8px;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <path d="M20 8v6M23 11h-6"></path>
                                </svg>
                                訂單
                            </h2>
                            
                            <!-- 統計卡片 -->
                            <div id="ordersStats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px;">
                                <!-- 統計將由 JavaScript 動態生成 -->
                            </div>
                            
                            <!-- 搜索和篩選 -->
                            <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px;">
                                <input type="text" id="ordersSearch" placeholder="搜索訂單（訂單號、客戶姓名、電話）" style="flex: 1; min-width: 200px; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--text);">
                                <select id="ordersStatusFilter" style="padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--text);">
                                    <option value="">全部狀態</option>
                                    <option value="pending">待處理</option>
                                    <option value="processing">處理中</option>
                                    <option value="shipped">已出貨</option>
                                    <option value="completed">已完成</option>
                                    <option value="cancelled">已取消</option>
                                </select>
                                <button class="btn" type="button" id="ordersRefresh" style="padding: 10px 20px;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 4px;">
                                        <path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                                    </svg>
                                    刷新
                                </button>
                            </div>
                            
                            <!-- 訂單列表 -->
                            <div id="ordersList" style="display: flex; flex-direction: column; gap: 12px;">
                                <div style="text-align: center; padding: 40px; color: var(--muted);">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity: 0.3; margin: 0 auto 16px;">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="8.5" cy="7" r="4"></circle>
                                        <path d="M20 8v6M23 11h-6"></path>
                                    </svg>
                                    <p>載入中...</p>
                                </div>
                            </div>
                            
                            <!-- 分頁控件 -->
                            <div id="ordersPagination"></div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="row" style="justify-content: space-between; align-items: center;">
                        <strong>編輯</strong>
                        <div style="color: var(--muted); font-size: 12px;">刪除/儲存在底部</div>
                    </div>

                    <input id="parent" type="hidden" value="">
                    <div class="field" style="margin-top: 12px;">
                        <label>所屬父層</label>
                        <input id="parentDisplay" type="text" readonly placeholder="(頂層)">
                        <small>請在左側樹狀 Menu 直接拖放調整父層與排序。</small>
                    </div>
                    <div class="field">
                        <label for="menu_title">頁名（導覽Menu顯示名稱）</label>
                        <input id="menu_title" type="text" autocomplete="off" placeholder="例如：關於我們 / 服務項目">
                        <small style="color: var(--muted);">左側 Menu 會即時顯示這個頁名；拖放只影響 Menu 結構，不會強制改網址。</small>
                    </div>
                    <div class="field">
                        <label for="title">標題（頁面 SEO Title）</label>
                        <input id="title" type="text" autocomplete="off" placeholder="例如：關於我們 - 公司名稱">
                    </div>
                    <div class="field">
                        <label for="path">網址（自動）</label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input id="path" type="text" readonly style="flex: 1;">
                            <button id="btnAssignPath" type="button" class="btn" style="white-space: nowrap;" title="根據標題自動更新網址（包含子頁）">更新網址</button>
                            <button id="btnShowQr" type="button" class="btn" style="white-space: nowrap;" title="顯示 QR 碼">QR 碼</button>
                        </div>
                        <small style="color: var(--muted);">點擊「更新網址」按鈕會根據標題自動更新當前頁面及所有子頁的網址。</small>
                    </div>
                    <div class="field">
                        <label for="pageType">頁面類型</label>
                        <select id="pageType" style="width: 100%;">
                            <option value="page">內容頁</option>
                            <option value="product">商品頁</option>
                        </select>
                        <small style="color: var(--muted);">選擇「商品頁」後可設定價格，前台會顯示「加入購物車」按鈕。</small>
                    </div>
                    <div class="field" id="priceField" style="display: none;">
                        <label for="price">商品價格</label>
                        <input id="price" type="number" step="0.01" min="0" placeholder="0.00" style="width: 100%;">
                        <small style="color: var(--muted);">輸入商品價格（數字）。</small>
                    </div>

                    <!-- 多行多列區塊編輯器（主編輯器為第一個區塊） -->
                    <div class="block-editor-wrap">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding: 12px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;">
                            <div></div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn" id="btnLayoutSettings">布置調整</button>
                                <button type="button" class="btn" id="btnAddBlock">+ 新增區塊</button>
                            </div>
                        </div>
                        <!-- 布置調整設置面板 -->
                        <div id="layoutSettingsPanel" style="display: none; margin-bottom: 16px; padding: 16px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px;">
                            <h3 style="margin: 0 0 16px; font-size: 16px;">布置調整</h3>
                            <div class="field" style="margin-bottom: 16px;">
                                <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                                    <input type="checkbox" id="layoutFullWidth" style="width: auto; cursor: pointer;">
                                    <span>完全填滿容器</span>
                                </label>
                                <small style="display: block; color: var(--muted); margin-top: 4px;">啟用後，container 和 mainContent 將完全填滿，移除內邊距和邊距</small>
                            </div>
                            <div class="field">
                                <label>區塊對齊方式</label>
                                <select id="layoutBlockAlign" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text); font-size: 13px;">
                                    <option value="left">靠左</option>
                                    <option value="center" selected>置中</option>
                                    <option value="right">靠右</option>
                                </select>
                                <small style="display: block; color: var(--muted); margin-top: 4px;">當區塊不是 100% 寬度時的對齊方式</small>
                            </div>
                            <div style="margin-top: 16px;">
                                <button type="button" class="btn btn-ok" id="btnSaveLayoutSettings">儲存布置設定</button>
                            </div>
                        </div>
                        <div id="blockGrid" class="block-grid">
                            <!-- 第一個區塊：主編輯器 -->
                            <div class="block-item block-item-main" data-block-id="main-editor">
                                <div class="block-actions">
                                    <button type="button" class="block-settings-btn" title="更多設置">⚙️</button>
                                </div>
                                <div class="block-settings">
                                    <div class="field">
                                        <label>列寬度 (12列網格)</label>
                                        <select class="block-colspan">
                                            <option value="3">3 列 (25%)</option>
                                            <option value="4" selected>4 列 (33%)</option>
                                            <option value="6">6 列 (50%)</option>
                                            <option value="8">8 列 (67%)</option>
                                            <option value="9">9 列 (75%)</option>
                                            <option value="12">12 列 (100%)</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>效果</label>
                                        <select class="block-spacing" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text); font-size: 13px;">
                                            <option value="compact" data-padding="4" data-radius="0">填滿</option>
                                            <option value="tight" data-padding="8" data-radius="4">緊密</option>
                                            <option value="normal" data-padding="16" data-radius="12" selected>正常</option>
                                            <option value="wide" data-padding="24" data-radius="16">較寬</option>
                                            <option value="spacious" data-padding="32" data-radius="20">空曠</option>
                                        </select>
                                        <input type="hidden" class="block-padding" value="16">
                                        <input type="hidden" class="block-radius" value="12">
                                    </div>
                                    <div class="field">
                                        <label>背景色</label>
                                        <select class="block-bg-mode" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text); font-size: 13px; margin-bottom: 6px;">
                                            <option value="auto" selected>自動（使用主題配色）</option>
                                            <option value="custom">自定義顏色</option>
                                        </select>
                                        <input type="color" class="block-bg" value="#ffffff" style="display: none;">
                                    </div>
                                </div>
                                <div class="block-toolbar-wrap">
                                    <div id="toolbar" class="ql-toolbar ql-snow block-toolbar-compact">
                            <span class="ql-formats">
                                <select class="ql-font">
                                    <option value="sans" selected>Sans</option>
                                    <option value="serif">Serif</option>
                                    <option value="mono">Mono</option>
                                </select>
                                <select class="ql-size">
                                    <option value="12px">12</option>
                                    <option value="14px">14</option>
                                    <option value="16px" selected>16</option>
                                    <option value="18px">18</option>
                                    <option value="20px">20</option>
                                    <option value="24px">24</option>
                                    <option value="28px">28</option>
                                    <option value="32px">32</option>
                                </select>
                                <select class="ql-header">
                                    <option value="1"></option>
                                    <option value="2"></option>
                                    <option value="3"></option>
                                    <option selected></option>
                                </select>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-bold"></button>
                                <button class="ql-italic"></button>
                                <button class="ql-underline"></button>
                                <button class="ql-strike"></button>
                            </span>
                            <span class="ql-formats">
                                <select class="ql-color"></select>
                                <select class="ql-background"></select>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-script" value="sub"></button>
                                <button class="ql-script" value="super"></button>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-list" value="ordered"></button>
                                <button class="ql-list" value="bullet"></button>
                                <button class="ql-indent" value="-1"></button>
                                <button class="ql-indent" value="+1"></button>
                            </span>
                            <span class="ql-formats">
                                <select class="ql-align"></select>
                            </span>
                            <span class="ql-formats">
                                <select class="ql-lineheight">
                                    <option value="1">1</option>
                                    <option value="1.15">1.15</option>
                                    <option value="1.5">1.5</option>
                                    <option value="1.75">1.75</option>
                                    <option value="2">2</option>
                                </select>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-link"></button>
                                <button class="ql-image"></button>
                                            <button class="ql-youtube" type="button" title="插入 YouTube 影片">YouTube</button>
                                            <button class="ql-map" type="button" title="插入地圖">地圖</button>
                                            <button class="ql-button" type="button" title="插入按鈕">按鈕</button>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-undo" type="button">↶</button>
                                <button class="ql-redo" type="button">↷</button>
                                <button class="ql-clean"></button>
                            </span>
                        </div>
                                </div>
                                <div class="block-editor">
                        <div id="editor"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="editor-actions">
                        <div class="draft-actions" id="draftActions">
                            <span class="draft-badge" id="draftBadge">無草稿</span>
                            <button class="btn" type="button" id="btnRestoreLive" title="捨棄草稿並載入已發佈版本">復原至實況版本</button>
                        </div>
                        <div class="spacer"></div>
                        <button class="btn btn-danger" type="button" id="btnDelete">刪除</button>
                        <button class="btn btn-ok" type="button" id="btnSave">儲存</button>
                    </div>
                </div>
            </div>

            <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
            <script src="https://unpkg.com/quill-image-resize-module@3.0.0/image-resize.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@1.13.0/dist/Control.Geocoder.css" />
            <script src="https://unpkg.com/leaflet-control-geocoder@1.13.0/dist/Control.Geocoder.js"></script>
            <script>
                const BASE_PATH = <?php echo json_encode($basePath, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                const API_URL = BASE_PATH + 'api.php';
                let CSRF_TOKEN = <?php echo json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                
                // 提前聲明變量，避免在函數中使用時報錯
                let currentEditingPath = 'home';

                // 選項卡切換邏輯
                const selectionTabs = document.querySelectorAll('.selection-tab');
                const selectionContents = document.querySelectorAll('.selection-content');
                
                function switchTab(selection) {
                    // 移除所有選項卡的 active 狀態
                    selectionTabs.forEach(t => t.classList.remove('active'));
                    // 隱藏所有內容面板
                    selectionContents.forEach(c => {
                        c.classList.remove('active');
                        c.style.display = 'none';
                    });
                    
                    // 激活選中的選項卡
                    const activeTab = document.querySelector(`.selection-tab[data-selection="${selection}"]`);
                    if (activeTab) {
                        activeTab.classList.add('active');
                    }
                    
                    // 顯示對應的內容面板
                    const contentId = 'selection' + (selection.charAt(0).toUpperCase() + selection.slice(1));
                    const content = document.getElementById(contentId);
                    if (content) {
                        content.classList.add('active');
                        content.style.display = 'block';
                        
                        // 如果切換到訂單，自動載入訂單列表
                        // 使用 setTimeout 確保 DOM 已更新
                        if (selection === 'orders') {
                            setTimeout(() => {
                                loadOrders();
                            }, 100);
                        }
                        if (selection === 'translations') {
                            setTimeout(() => {
                                loadTranslations();
                            }, 100);
                        }
                        if (selection === 'trash') {
                            setTimeout(() => {
                                loadTrash();
                            }, 100);
                        }
                    }
                }
                
                // 綁定選項卡點擊事件
                if (selectionTabs.length > 0) {
                    selectionTabs.forEach(tab => {
                        tab.addEventListener('click', () => {
                            const selection = tab.getAttribute('data-selection');
                            if (selection) {
                                switchTab(selection);
                            }
                        });
                    });
                }

                const statusMsg = document.getElementById('statusMsg');
                const navEditor = document.getElementById('navEditor');
                const btnNew = document.getElementById('btnNew');
                const btnSave = document.getElementById('btnSave');
                const btnDelete = document.getElementById('btnDelete');
                const btnChangePassword = document.getElementById('btnChangePassword');
                const themeSelect = document.getElementById('themeSelect');
                const elParent = document.getElementById('parent');
                const elParentDisplay = document.getElementById('parentDisplay');
                const elMenuTitle = document.getElementById('menu_title'); // 頁名
                const elTitle = document.getElementById('title'); // 標題
                const elPath = document.getElementById('path'); // 自動網址
                const elPageType = document.getElementById('pageType'); // 頁面類型
                const elPrice = document.getElementById('price'); // 商品價格
                const elPriceField = document.getElementById('priceField'); // 價格欄位容器

                let pagesCache = [];
                let selectedInTree = 'home';

                // ---- Word-like editor upgrades（與協調字體庫一致）----
                const editorFontList = [
                    { value: 'sans', label: 'Sans' },
                    { value: 'serif', label: 'Serif' },
                    { value: 'mono', label: 'Mono' },
                    { value: 'arial', label: 'Arial' },
                    { value: 'verdana', label: 'Verdana' },
                    { value: 'trebuchet-ms', label: 'Trebuchet MS' },
                    { value: 'courier-new', label: 'Courier New' },
                    { value: 'roboto', label: 'Roboto' },
                    { value: 'open-sans', label: 'Open Sans' },
                    { value: 'lato', label: 'Lato' },
                    { value: 'poppins', label: 'Poppins' },
                    { value: 'montserrat', label: 'Montserrat' },
                    { value: 'oswald', label: 'Oswald' },
                    { value: 'source-sans-3', label: 'Source Sans 3' },
                    { value: 'playfair-display', label: 'Playfair Display' },
                    { value: 'merriweather', label: 'Merriweather' },
                    { value: 'pt-sans', label: 'PT Sans' },
                    { value: 'nunito', label: 'Nunito' },
                    { value: 'raleway', label: 'Raleway' },
                    { value: 'work-sans', label: 'Work Sans' },
                    { value: 'barlow', label: 'Barlow' },
                    { value: 'inter', label: 'Inter' },
                    { value: 'dm-sans', label: 'DM Sans' },
                    { value: 'microsoft-jhenghei', label: '微軟正黑體' },
                    { value: 'pingfang-tc', label: '蘋方-繁' },
                    { value: 'noto-sans-tc', label: '思源黑體 TC' },
                    { value: 'noto-serif-tc', label: '思源宋體 TC' },
                    { value: 'noto-sans-hk', label: '思源黑體 HK' },
                    { value: 'noto-serif-hk', label: '思源宋體 HK' },
                    { value: 'noto-sans-sc', label: '思源黑體 SC' },
                    { value: 'noto-serif-sc', label: '思源宋體 SC' },
                    { value: 'cwtexming', label: '明體 (cwTeXMing)' }
                ];
                const Font = Quill.import('formats/font');
                Font.whitelist = editorFontList.map(f => f.value);
                Quill.register(Font, true);
                function fillQuillFontPickers() {
                    document.querySelectorAll('select.ql-font').forEach(sel => {
                        const cur = sel.value || 'sans';
                        sel.innerHTML = editorFontList.map(o => '<option value="' + o.value + '"' + (o.value === cur ? ' selected' : '') + '>' + (o.label || o.value) + '</option>').join('');
                        if (!cur || !editorFontList.some(f => f.value === cur)) sel.value = 'sans';
                    });
                }

                const Size = Quill.import('attributors/style/size');
                Size.whitelist = ['12px', '14px', '16px', '18px', '20px', '24px', '28px', '32px'];
                Quill.register(Size, true);

                // Line-height format (Word-like)
                const Parchment = Quill.import('parchment');
                const LineHeightStyle = new Parchment.Attributor.Style('lineheight', 'line-height', {
                    scope: Parchment.Scope.BLOCK,
                    whitelist: ['1', '1.15', '1.5', '1.75', '2']
                });
                Quill.register(LineHeightStyle, true);

                const editorModules = {
                    toolbar: {
                        container: '#toolbar',
                        handlers: {
                            image: function() {
                                const input = document.createElement('input');
                                input.type = 'file';
                                input.accept = 'image/*';
                                input.click();
                                input.onchange = () => {
                                    const file = input.files && input.files[0];
                                    if (file) uploadImage(file);
                                };
                            },
                            youtube: function() { showYouTubeInsertDialog(); },
                            map: function() { showMapInsertDialog(); },
                            button: function() { showButtonInsertDialog(); },
                            undo: function() { quill.history.undo(); },
                            redo: function() { quill.history.redo(); },
                            bold: function() {
                                const range = quill.getSelection(true);
                                if (range) {
                                    const format = quill.getFormat(range);
                                    quill.format('bold', !format.bold, 'user');
                                }
                            },
                            underline: function() {
                                const range = quill.getSelection(true);
                                if (range) {
                                    const format = quill.getFormat(range);
                                    quill.format('underline', !format.underline, 'user');
                                }
                            }
                        }
                    },
                    keyboard: { bindings: {} },
                    history: { delay: 600, maxStack: 200, userOnly: true },
                    clipboard: { matchVisual: false },
                    imageResize: {}
                };

                // 註冊 YouTube Blot（自定義嵌入類型）
                const BlockEmbed = Quill.import('blots/block/embed');
                class YouTubeBlot extends BlockEmbed {
                    static blotName = 'youtube';
                    static tagName = 'div';
                    static className = 'gwa-youtube-embed';

                    static create(value) {
                        const node = super.create();
                        const videoId = typeof value === 'string' ? value : (value.videoId || '');
                        const autoplay = value && value.autoplay ? '1' : '0';
                        const startTime = value && value.startTime ? value.startTime : '0';
                        
                        node.setAttribute('data-video-id', videoId);
                        node.setAttribute('data-autoplay', autoplay);
                        node.setAttribute('data-start-time', startTime);
                        node.setAttribute('contenteditable', 'false');
                        
                        // 創建容器
                        const container = document.createElement('div');
                        container.className = 'youtube-container';
                        container.style.cssText = 'position: relative; width: 100%; padding-bottom: 56.25%; height: 0; margin: 20px 0; border-radius: 12px; overflow: hidden; background: #000;';
                        
                        // 創建 iframe
                        const iframe = document.createElement('iframe');
                        let embedUrl = `https://www.youtube.com/embed/${videoId}`;
                        const params = [];
                        if (autoplay === '1') params.push('autoplay=1');
                        if (startTime && startTime !== '0') params.push(`start=${startTime}`);
                        if (params.length > 0) embedUrl += '?' + params.join('&');
                        
                        iframe.setAttribute('src', embedUrl);
                        iframe.setAttribute('frameborder', '0');
                        iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
                        iframe.setAttribute('allowfullscreen', '');
                        iframe.style.cssText = 'position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;';
                        
                        // 創建標題欄（可選）
                        const titleBar = document.createElement('div');
                        titleBar.className = 'youtube-title-bar';
                        titleBar.style.cssText = 'position: absolute; top: 0; left: 0; right: 0; padding: 8px 12px; background: linear-gradient(to bottom, rgba(0,0,0,0.7), transparent); color: white; font-size: 12px; z-index: 1; pointer-events: none;';
                        titleBar.textContent = 'YouTube 影片';
                        
                        container.appendChild(iframe);
                        node.appendChild(container);
                        node.appendChild(titleBar);
                        
                        return node;
                    }

                    static value(node) {
                        const videoId = node.getAttribute('data-video-id') || '';
                        const autoplay = node.getAttribute('data-autoplay') === '1';
                        const startTime = node.getAttribute('data-start-time') || '0';
                        return {
                            videoId,
                            autoplay,
                            startTime
                        };
                                }
                }
                Quill.register(YouTubeBlot, true);

                // 註冊地圖 Blot
                class MapBlot extends BlockEmbed {
                    static blotName = 'map';
                    static tagName = 'div';
                    static className = 'gwa-map-embed';

                    static create(value) {
                        const node = super.create();
                        const config = typeof value === 'object' ? value : { landmarks: [] };
                        // 兼容舊格式（addresses 數組）
                        let landmarks = config.landmarks || [];
                        if (config.addresses && Array.isArray(config.addresses)) {
                            landmarks = config.addresses.map(addr => ({
                                address: typeof addr === 'string' ? addr : (addr.address || ''),
                                description: typeof addr === 'object' ? (addr.description || '') : ''
                            }));
                        }
                        const zoom = config.zoom || 13;
                        const height = config.height || 400;
                        
                        const style = config.style || 'light';
                        
                        node.setAttribute('data-landmarks', JSON.stringify(landmarks));
                        node.setAttribute('data-zoom', zoom);
                        node.setAttribute('data-height', height);
                        node.setAttribute('data-style', style);
                        node.setAttribute('contenteditable', 'false');
                        
                        // 創建地圖容器
                        const mapContainer = document.createElement('div');
                        mapContainer.className = 'gwa-map-container';
                        mapContainer.style.cssText = `width: 100%; height: ${height}px; margin: 20px 0; border-radius: 12px; overflow: hidden; border: 1px solid var(--border, rgba(0,0,0,0.1)); position: relative; cursor: pointer;`;
                        mapContainer.id = 'gwa-map-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
                        
                        // 創建地圖占位符（實際地圖將在前台渲染）
                        const placeholder = document.createElement('div');
                        placeholder.style.cssText = 'width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f0f0f0; color: #666; font-size: 14px; position: relative;';
                        // 占位符不包含「雙擊編輯」，避免被保存到 HTML
                        placeholder.innerHTML = `
                            <div style="margin-bottom: 8px;">🗺️ 地圖：${landmarks.length} 個標記點</div>
                        `;
                        mapContainer.appendChild(placeholder);
                        
                        node.appendChild(mapContainer);
                        
                        // 添加雙擊編輯功能（僅在編輯器中，動態顯示提示，不保存到 HTML）
                        const isInEditor = typeof quill !== 'undefined' && quill && quill.root && quill.root.contains && quill.root.contains(node);
                        if (isInEditor) {
                            // 動態添加「雙擊編輯」提示（不保存到 HTML）
                            const editHint = document.createElement('div');
                            editHint.style.cssText = 'font-size: 12px; color: #999;';
                            editHint.textContent = '雙擊編輯';
                            placeholder.appendChild(editHint);
                            mapContainer.addEventListener('dblclick', (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                
                                try {
                                    const mapValue = MapBlot.value(node);
                                    // 使用 Parchment 來獲取節點索引
                                    const Parchment = Quill.import('parchment');
                                    const blot = Parchment.find(node, true);
                                    
                                    if (!blot) {
                                        console.warn('無法找到地圖 Blot');
                                        showMsg('無法定位地圖位置，請手動刪除後重新插入', 'err');
                                    return;
                                }
                                    
                                    // 查找包含此地圖的編輯器（支持多編輯器環境）
                                    let targetQuill = quill; // 默認使用主編輯器
                                    for (const block of blockEditors) {
                                        if (block.quill && block.quill.root && block.quill.root.contains(node)) {
                                            targetQuill = block.quill;
                                            break;
                                        }
                                    }
                                    
                                    const index = blot.offset(targetQuill.scroll);
                                    showMapInsertDialog(targetQuill, {
                                        landmarks: mapValue.landmarks,
                                        zoom: mapValue.zoom,
                                        height: mapValue.height,
                                        style: mapValue.style || 'light',
                                        node: node,
                                        index: index
                                    });
                                } catch (err) {
                                    console.error('編輯地圖時發生錯誤:', err);
                                    if (typeof showMsg === 'function') {
                                        showMsg('編輯地圖時發生錯誤：' + (err.message || String(err)), 'err');
                                    }
                                }
                            });
                            
                            // 添加懸停提示
                            mapContainer.title = '雙擊編輯地圖';
                            mapContainer.addEventListener('mouseenter', () => {
                                mapContainer.style.borderColor = 'var(--accent, #4285f4)';
                                mapContainer.style.boxShadow = '0 0 0 2px rgba(66, 133, 244, 0.2)';
                            });
                            mapContainer.addEventListener('mouseleave', () => {
                                mapContainer.style.borderColor = 'var(--border, rgba(0,0,0,0.1))';
                                mapContainer.style.boxShadow = 'none';
                            });
                                }
                        
                        return node;
                    }

                    static value(node) {
                        try {
                            const landmarksJson = node.getAttribute('data-landmarks');
                            let landmarks = [];
                            if (landmarksJson) {
                                landmarks = JSON.parse(landmarksJson);
                            } else {
                                // 兼容舊格式
                                const addressesJson = node.getAttribute('data-addresses');
                                if (addressesJson) {
                                    const addresses = JSON.parse(addressesJson);
                                    landmarks = addresses.map(addr => ({
                                        address: typeof addr === 'string' ? addr : (addr.address || ''),
                                        description: typeof addr === 'object' ? (addr.description || '') : ''
                                    }));
                                }
                            }
                            return {
                                landmarks: landmarks,
                                zoom: parseInt(node.getAttribute('data-zoom') || '13', 10),
                                height: parseInt(node.getAttribute('data-height') || '400', 10),
                                style: node.getAttribute('data-style') || 'light'
                            };
                                } catch (e) {
                            return { landmarks: [], zoom: 13, height: 400, style: 'light' };
                        }
                    }
                }
                Quill.register(MapBlot, true);

                // 註冊按鈕 Blot
                class ButtonBlot extends BlockEmbed {
                    static blotName = 'button';
                    static tagName = 'div';
                    static className = 'gwa-button-embed';

                    static create(value) {
                        const node = super.create();
                        const config = typeof value === 'object' ? value : {};
                        const pagePath = config.path || '';
                        const buttonText = config.text || '按鈕';
                        const width = config.width || 'auto';
                        const height = config.height || 'auto';
                        const fontSize = config.fontSize || '16px';
                        const align = config.align || 'left';
                        const effect = config.effect || 'gradient';
                        
                        node.setAttribute('data-page-path', pagePath);
                        node.setAttribute('data-button-text', buttonText);
                        node.setAttribute('data-width', width);
                        node.setAttribute('data-height', height);
                        node.setAttribute('data-font-size', fontSize);
                        node.setAttribute('data-align', align);
                        node.setAttribute('data-effect', effect);
                        node.setAttribute('contenteditable', 'false');
                        
                        // 創建按鈕容器
                        const buttonContainer = document.createElement('div');
                        buttonContainer.className = 'gwa-button-container';
                        buttonContainer.style.cssText = `margin: 20px 0; display: flex; justify-content: ${align === 'left' ? 'flex-start' : align === 'center' ? 'center' : 'flex-end'};`;
                        
                        // 創建按鈕
                        const button = document.createElement('a');
                        button.className = 'gwa-button';
                        button.href = '#';
                        button.textContent = buttonText;
                        
                        // 應用樣式
                        const buttonStyle = {
                            display: 'inline-block',
                            padding: '12px 24px',
                            fontSize: fontSize,
                            fontWeight: '600',
                            textDecoration: 'none',
                            border: 'none',
                            borderRadius: '8px',
                            cursor: 'pointer',
                            transition: 'all 0.3s ease',
                            width: width === 'auto' ? 'auto' : width,
                            height: height === 'auto' ? 'auto' : height,
                            minWidth: width === 'auto' ? '120px' : 'auto',
                            minHeight: height === 'auto' ? '44px' : 'auto'
                        };
                        
                        // 根據特效應用樣式
                        switch (effect) {
                            case 'gradient':
                                buttonStyle.background = 'linear-gradient(135deg, var(--accent, #7c5cff), var(--accent2, #00d4ff))';
                                buttonStyle.color = 'white';
                                buttonStyle.boxShadow = '0 4px 16px rgba(124,92,255,0.3)';
                                break;
                            case 'solid':
                                buttonStyle.background = 'var(--accent, #7c5cff)';
                                buttonStyle.color = 'white';
                                buttonStyle.boxShadow = '0 2px 8px rgba(124,92,255,0.2)';
                                break;
                            case 'outline':
                                buttonStyle.background = 'transparent';
                                buttonStyle.color = 'var(--accent, #7c5cff)';
                                buttonStyle.border = '2px solid var(--accent, #7c5cff)';
                                break;
                            case 'ghost':
                                buttonStyle.background = 'rgba(124,92,255,0.1)';
                                buttonStyle.color = 'var(--accent, #7c5cff)';
                                break;
                            case 'glow':
                                buttonStyle.background = 'var(--accent, #7c5cff)';
                                buttonStyle.color = 'white';
                                buttonStyle.boxShadow = '0 0 20px rgba(124,92,255,0.6), 0 4px 16px rgba(124,92,255,0.3)';
                                break;
                        }
                        
                        Object.assign(button.style, buttonStyle);
                        
                        // 編輯器中的雙擊編輯功能
                        const isInEditor = (() => {
                            const editorContainer = document.getElementById('editor') || document.querySelector('.ql-container');
                            if (!editorContainer) return false;
                            if (typeof quill === 'undefined' || !quill || !quill.root) return false;
                            if (quill.root.contains && quill.root.contains(node)) return true;
                            let parent = node.parentElement;
                            while (parent) {
                                if (parent === quill.root) return true;
                                parent = parent.parentElement;
                            }
                            return false;
                        })();
                        
                        if (isInEditor) {
                            button.addEventListener('click', (e) => {
                                e.preventDefault();
                        });
                            
                            buttonContainer.addEventListener('dblclick', (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                const buttonValue = ButtonBlot.value(node);
                                const Parchment = Quill.import('parchment');
                                const blot = Parchment.find(node, true);
                                
                                if (!blot) {
                                    console.warn('無法找到按鈕 Blot');
                                    showMsg('無法定位按鈕位置，請手動刪除後重新插入', 'err');
                                    return;
                                }
                                
                                // 查找包含此按鈕的編輯器（支持多編輯器環境）
                                let targetQuill = quill; // 默認使用主編輯器
                                for (const block of blockEditors) {
                                    if (block.quill && block.quill.root && block.quill.root.contains(node)) {
                                        targetQuill = block.quill;
                                        break;
                                    }
                                }
                                
                                const index = blot.offset(targetQuill.scroll);
                                showButtonInsertDialog(targetQuill, {
                                    path: buttonValue.path,
                                    text: buttonValue.text,
                                    width: buttonValue.width,
                                    height: buttonValue.height,
                                    fontSize: buttonValue.fontSize,
                                    align: buttonValue.align,
                                    effect: buttonValue.effect,
                                    node: node,
                                    index: index
                                });
                            });
                            buttonContainer.title = '雙擊編輯按鈕';
                            buttonContainer.style.cursor = 'pointer';
                        } else {
                            // 前端：設置跳轉功能（在編輯器中不設置 href，由前端 JavaScript 處理）
                            if (pagePath) {
                                button.setAttribute('data-page-path', pagePath);
                                button.className += ' gwa-button-link';
                                button.href = '#'; // 防止默認跳轉
                            }
                        }
                        
                        buttonContainer.appendChild(button);
                        node.appendChild(buttonContainer);
                        
                        return node;
                    }

                    static value(node) {
                        return {
                            path: node.getAttribute('data-page-path') || '',
                            text: node.getAttribute('data-button-text') || '按鈕',
                            width: node.getAttribute('data-width') || 'auto',
                            height: node.getAttribute('data-height') || 'auto',
                            fontSize: node.getAttribute('data-font-size') || '16px',
                            align: node.getAttribute('data-align') || 'left',
                            effect: node.getAttribute('data-effect') || 'gradient'
                        };
                }
                }
                Quill.register(ButtonBlot, true);

                fillQuillFontPickers();
                // 創建 Quill 編輯器實例（生產環境嚴格模式）
                const quill = new Quill('#editor', {
                    theme: 'snow',
                    modules: editorModules
                });

                console.log('[GWA] editor init', {
                    hasImageResize: !!quill.getModule('imageResize'),
                    hasHistory: !!quill.getModule('history'),
                });

                // ===== 簡單的編輯器滾動保護機制 =====
                let isUserTyping = false; // 標記是否為用戶直接輸入
                let savedScrollTop = 0; // 保存的滾動位置
                
                // 監聽用戶直接輸入（鍵盤輸入）
                function setupScrollProtection(editorQuill, editorElement) {
                    if (!editorQuill || !editorElement) return;
                    
                    // 標記用戶直接輸入
                    editorElement.addEventListener('keydown', (e) => {
                        // 排除功能鍵（Ctrl, Alt, Shift, Meta）和特殊鍵
                        if (!e.ctrlKey && !e.metaKey && !e.altKey && 
                            e.key.length === 1 || 
                            ['Backspace', 'Delete', 'Enter', 'Tab'].includes(e.key)) {
                            isUserTyping = true;
                            // 300ms 後重置標記（足夠處理一次輸入）
                            setTimeout(() => { isUserTyping = false; }, 300);
                        }
                    }, true);
                    
                    // 監聽內容變化，如果不是用戶輸入則保護滾動位置
                    editorQuill.on('text-change', () => {
                        if (!isUserTyping) {
                            // 保存當前滾動位置
                            savedScrollTop = window.pageYOffset || document.documentElement.scrollTop;
                            
                            // 在下一個動畫幀恢復滾動位置（確保 DOM 已更新）
                            requestAnimationFrame(() => {
                                requestAnimationFrame(() => {
                                    window.scrollTo(0, savedScrollTop);
                                });
                            });
                        }
                    });
                }
                
                // 為主編輯器設置滾動保護
                if (quill && quill.root) {
                    setupScrollProtection(quill, quill.root);
                }
                // ===== 滾動保護機制結束 =====

                // 草稿自動儲存：主編輯器內容變更時寫入 localStorage
                quill.on('text-change', () => scheduleDraftSave());

                // ===== 多行多列區塊編輯器系統（主編輯器為第一個區塊） =====
                let blockEditors = []; // 存儲所有區塊編輯器實例 [{id, quill, element, isMain}]
                let sortableInstance = null; // Sortable 實例（提前聲明以避免初始化順序問題）
                const blockGrid = document.getElementById('blockGrid');
                const btnAddBlock = document.getElementById('btnAddBlock');
                const mainBlockItem = document.querySelector('.block-item-main');
                
                // 初始化主編輯器區塊
                if (mainBlockItem) {
                    blockEditors.push({ 
                        id: 'main-editor', 
                        quill: quill, 
                        element: mainBlockItem,
                        isMain: true
                    });
                    
                    // 綁定主編輯器區塊的設置事件
                    const settingsBtn = mainBlockItem.querySelector('.block-settings-btn');
                    const settingsPanel = mainBlockItem.querySelector('.block-settings');
                    if (settingsBtn && settingsPanel) {
                        settingsBtn.addEventListener('click', (e) => {
                            e.stopPropagation();
                            settingsPanel.classList.toggle('show');
                        });
                    }
                    
                    // 主編輯器設置面板事件
                    const colspanSelect = mainBlockItem.querySelector('.block-colspan');
                    const spacingSelect = mainBlockItem.querySelector('.block-spacing');
                    const paddingInput = mainBlockItem.querySelector('.block-padding');
                    const bgModeSelect = mainBlockItem.querySelector('.block-bg-mode');
                    const bgInput = mainBlockItem.querySelector('.block-bg');
                    const radiusInput = mainBlockItem.querySelector('.block-radius');
                    
                    // 初始化效果選擇器
                    initSpacingSelect(spacingSelect);
                    
                    // 效果選擇器切換
                    spacingSelect?.addEventListener('change', () => {
                        const selectedOption = spacingSelect.options[spacingSelect.selectedIndex];
                        const padding = selectedOption.dataset.padding || '16';
                        const radius = selectedOption.dataset.radius || '12';
                        paddingInput.value = padding;
                        radiusInput.value = radius;
                        // 填滿效果：自動設置為全寬（12列）
                        if (spacingSelect.value === 'compact') {
                            if (colspanSelect) colspanSelect.value = '12';
                        }
                        updateBlockStyle('main-editor');
                    });
                    
                    // 背景模式切換
                    bgModeSelect?.addEventListener('change', () => {
                        if (bgModeSelect.value === 'custom') {
                            bgInput.style.display = 'block';
                        } else {
                            bgInput.style.display = 'none';
                        }
                        updateBlockStyle('main-editor');
                    });
                    
                    colspanSelect?.addEventListener('change', () => updateBlockStyle('main-editor'));
                    bgInput?.addEventListener('input', () => updateBlockStyle('main-editor'));
                    
                    updateBlockStyle('main-editor');
                    
                    // 初始化排序功能
                    initSortable();
                }

                // 添加新區塊
                function addBlock(html = '') {
                    const blockId = 'block-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
                    const blockItem = document.createElement('div');
                    blockItem.className = 'block-item';
                    blockItem.dataset.blockId = blockId;
                    
                    blockItem.innerHTML = `
                        <div class="block-actions">
                            <button type="button" class="block-copy" title="複製區塊">📋</button>
                            <button type="button" class="block-move" title="轉移到其他頁面">📤</button>
                            <button type="button" class="block-settings-btn" title="更多設置">⚙️</button>
                            <button type="button" class="block-delete danger" title="刪除區塊">×</button>
                        </div>
                        <div class="block-settings">
                            <div class="field-group">
                                <div class="field-group-title">🖥️ 橫屏設置</div>
                                <div class="field">
                                    <label>列寬度 (12列網格)</label>
                                    <select class="block-colspan">
                                        <option value="3">3 列 (25%)</option>
                                        <option value="4" selected>4 列 (33%)</option>
                                        <option value="6">6 列 (50%)</option>
                                        <option value="8">8 列 (67%)</option>
                                        <option value="9">9 列 (75%)</option>
                                        <option value="12">12 列 (100%)</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label>效果</label>
                                    <select class="block-spacing" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text); font-size: 13px;">
                                        <option value="compact" data-padding="4" data-radius="0">填滿</option>
                                        <option value="tight" data-padding="8" data-radius="4">緊密</option>
                                        <option value="normal" data-padding="16" data-radius="12" selected>正常</option>
                                        <option value="wide" data-padding="24" data-radius="16">較寬</option>
                                        <option value="spacious" data-padding="32" data-radius="20">空曠</option>
                                    </select>
                                    <input type="hidden" class="block-padding" value="16">
                                    <input type="hidden" class="block-radius" value="12">
                                </div>
                                <div class="field">
                                    <label>背景色</label>
                                    <select class="block-bg-mode" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text); font-size: 13px; margin-bottom: 6px;">
                                        <option value="auto" selected>自動（使用主題配色）</option>
                                        <option value="custom">自定義顏色</option>
                                    </select>
                                    <input type="color" class="block-bg" value="#ffffff" style="display: none;">
                                </div>
                            </div>
                            <div class="field-group">
                                <div class="field-group-title">📱 直屏設置</div>
                                <div class="field">
                                    <label>列寬度 (直屏)</label>
                                    <select class="block-colspan-mobile">
                                        <option value="12" selected>12 列 (100%，一行一列)</option>
                                        <option value="6">6 列 (50%，一行兩列)</option>
                                        <option value="4">4 列 (33%，一行三列)</option>
                                        <option value="3">3 列 (25%，一行四列)</option>
                                    </select>
                                    <div class="help-text">直屏下默認一行一列，可手動調整</div>
                                </div>
                                <div class="field">
                                    <label>效果 (直屏)</label>
                                    <select class="block-spacing-mobile" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text); font-size: 13px;">
                                        <option value="desktop" selected>使用橫屏設置</option>
                                        <option value="compact" data-padding="4" data-radius="0">填滿</option>
                                        <option value="tight" data-padding="8" data-radius="4">緊密</option>
                                        <option value="normal" data-padding="16" data-radius="12">正常</option>
                                        <option value="wide" data-padding="24" data-radius="16">較寬</option>
                                        <option value="spacious" data-padding="32" data-radius="20">空曠</option>
                                    </select>
                                    <input type="hidden" class="block-padding-mobile" value="">
                                    <input type="hidden" class="block-radius-mobile" value="">
                                    <div class="help-text">可選擇使用橫屏設置或自定義</div>
                                </div>
                                <div class="field">
                                    <label>背景色 (直屏)</label>
                                    <select class="block-bg-mobile-mode" style="width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text); font-size: 13px; margin-bottom: 6px;">
                                        <option value="auto" selected>自動（使用主題配色）</option>
                                        <option value="desktop">使用橫屏設置</option>
                                        <option value="custom">自定義顏色</option>
                                    </select>
                                    <input type="color" class="block-bg-mobile" value="#ffffff" style="display: none;" data-use-desktop="true">
                                    <div class="help-text">自動模式使用主題的默認配色，保持風格一致</div>
                                </div>
                            </div>
                        </div>
                        <div class="block-toolbar-wrap">
                            <div class="block-toolbar ql-toolbar ql-snow block-toolbar-compact" id="toolbar-${blockId}">
                                <span class="ql-formats">
                                    <select class="ql-font">
                                        <option value="sans" selected>Sans</option>
                                        <option value="serif">Serif</option>
                                        <option value="mono">Mono</option>
                                    </select>
                                    <select class="ql-size">
                                        <option value="12px">12</option>
                                        <option value="14px">14</option>
                                        <option value="16px" selected>16</option>
                                        <option value="18px">18</option>
                                        <option value="20px">20</option>
                                        <option value="24px">24</option>
                                        <option value="28px">28</option>
                                        <option value="32px">32</option>
                                    </select>
                                    <select class="ql-header">
                                        <option value="1"></option>
                                        <option value="2"></option>
                                        <option value="3"></option>
                                        <option selected></option>
                                    </select>
                                </span>
                                <span class="ql-formats">
                                    <button class="ql-bold"></button>
                                    <button class="ql-italic"></button>
                                    <button class="ql-underline"></button>
                                    <button class="ql-strike"></button>
                                </span>
                                <span class="ql-formats">
                                    <select class="ql-color"></select>
                                    <select class="ql-background"></select>
                                </span>
                                <span class="ql-formats">
                                    <button class="ql-script" value="sub"></button>
                                    <button class="ql-script" value="super"></button>
                                </span>
                                <span class="ql-formats">
                                    <button class="ql-list" value="ordered"></button>
                                    <button class="ql-list" value="bullet"></button>
                                    <button class="ql-indent" value="-1"></button>
                                    <button class="ql-indent" value="+1"></button>
                                </span>
                                <span class="ql-formats">
                                    <select class="ql-align"></select>
                                </span>
                                <span class="ql-formats">
                                    <select class="ql-lineheight">
                                        <option value="1">1</option>
                                        <option value="1.15">1.15</option>
                                        <option value="1.5">1.5</option>
                                        <option value="1.75">1.75</option>
                                        <option value="2">2</option>
                                    </select>
                                </span>
                                <span class="ql-formats">
                                    <button class="ql-link"></button>
                                    <button class="ql-image"></button>
                                    <button class="ql-youtube" type="button" title="插入 YouTube 影片">YouTube</button>
                                    <button class="ql-map" type="button" title="插入地圖">地圖</button>
                                    <button class="ql-button" type="button" title="插入按鈕">按鈕</button>
                                </span>
                                <span class="ql-formats">
                                    <button class="ql-undo" type="button">↶</button>
                                    <button class="ql-redo" type="button">↷</button>
                                    <button class="ql-clean"></button>
                                </span>
                            </div>
                        </div>
                        <div class="block-editor" id="editor-${blockId}"></div>
                    `;
                    
                    blockGrid.appendChild(blockItem);
                    fillQuillFontPickers();
                    // 創建 Quill 編輯器（使用完整的工具欄，與主編輯器一致）
                    const editorEl = blockItem.querySelector(`#editor-${blockId}`);
                    const toolbarEl = blockItem.querySelector(`#toolbar-${blockId}`);
                    const blockQuill = new Quill(editorEl, {
                        theme: 'snow',
                        modules: {
                            toolbar: {
                                container: toolbarEl,
                                handlers: {
                                    image: function() {
                                        const input = document.createElement('input');
                                        input.type = 'file';
                                        input.accept = 'image/*';
                                        input.click();
                                        input.onchange = () => {
                                            const file = input.files && input.files[0];
                                            if (file) uploadImage(file, blockQuill);
                                        };
                                    },
                                    youtube: function() { showYouTubeInsertDialog(blockQuill); },
                                    map: function() { showMapInsertDialog(blockQuill); },
                                    button: function() { showButtonInsertDialog(blockQuill); },
                                    undo: function() { blockQuill.history.undo(); },
                                    redo: function() { blockQuill.history.redo(); },
                                    bold: function() {
                                        const range = blockQuill.getSelection(true);
                                        if (range) {
                                            const format = blockQuill.getFormat(range);
                                            blockQuill.format('bold', !format.bold, 'user');
                                        }
                                    },
                                    underline: function() {
                                        const range = blockQuill.getSelection(true);
                                        if (range) {
                                            const format = blockQuill.getFormat(range);
                                            blockQuill.format('underline', !format.underline, 'user');
                                        }
                                    }
                                }
                            },
                            history: { delay: 1000, maxStack: 50, userOnly: true },
                            clipboard: { matchVisual: false }
                        }
                    });
                    
                    if (html) {
                        blockQuill.clipboard.dangerouslyPasteHTML(0, html, 'silent');
                    }
                    
                    blockEditors.push({ id: blockId, quill: blockQuill, element: blockItem });
                    blockQuill.on('text-change', () => scheduleDraftSave());
                    // 為區塊編輯器設置滾動保護和貼上處理
                    if (blockQuill && blockQuill.root) {
                        setupScrollProtection(blockQuill, blockQuill.root);
                        setupPasteHandler(blockQuill);
                    }
                    
                    // 綁定事件
                    const copyBtn = blockItem.querySelector('.block-copy');
                    const moveBtn = blockItem.querySelector('.block-move');
                    const deleteBtn = blockItem.querySelector('.block-delete');
                    const settingsBtn = blockItem.querySelector('.block-settings-btn');
                    const settingsPanel = blockItem.querySelector('.block-settings');
                    
                    copyBtn?.addEventListener('click', () => copyBlock(blockId));
                    moveBtn?.addEventListener('click', () => showMoveBlockDialog(blockId));
                    deleteBtn?.addEventListener('click', () => deleteBlock(blockId));
                    settingsBtn?.addEventListener('click', (e) => {
                        e.stopPropagation();
                        settingsPanel.classList.toggle('show');
                    });
                    
                    // 設置面板事件（橫屏）
                    const colspanSelect = blockItem.querySelector('.block-colspan');
                    const spacingSelect = blockItem.querySelector('.block-spacing');
                    const paddingInput = blockItem.querySelector('.block-padding');
                    const bgModeSelect = blockItem.querySelector('.block-bg-mode');
                    const bgInput = blockItem.querySelector('.block-bg');
                    const radiusInput = blockItem.querySelector('.block-radius');
                    
                    // 設置面板事件（直屏）
                    const colspanMobileSelect = blockItem.querySelector('.block-colspan-mobile');
                    const spacingMobileSelect = blockItem.querySelector('.block-spacing-mobile');
                    const paddingMobileInput = blockItem.querySelector('.block-padding-mobile');
                    const bgMobileModeSelect = blockItem.querySelector('.block-bg-mobile-mode');
                    const bgMobileInput = blockItem.querySelector('.block-bg-mobile');
                    const radiusMobileInput = blockItem.querySelector('.block-radius-mobile');
                    
                    // 初始化效果選擇器（橫屏）
                    initSpacingSelect(spacingSelect);
                    
                    // 效果選擇器切換（橫屏）
                    spacingSelect?.addEventListener('change', () => {
                        const selectedOption = spacingSelect.options[spacingSelect.selectedIndex];
                        const padding = selectedOption.dataset.padding || '16';
                        const radius = selectedOption.dataset.radius || '12';
                        paddingInput.value = padding;
                        radiusInput.value = radius;
                        // 填滿效果：自動設置為全寬（12列）
                        if (spacingSelect.value === 'compact') {
                            if (colspanSelect) colspanSelect.value = '12';
                        }
                        updateBlockStyle(blockId);
                    });
                    
                    // 初始化效果選擇器（直屏）
                    if (spacingMobileSelect) {
                        if (spacingMobileSelect.value === 'desktop') {
                            if (paddingMobileInput) paddingMobileInput.value = '';
                            if (radiusMobileInput) radiusMobileInput.value = '';
                        } else {
                            initSpacingSelect(spacingMobileSelect);
                        }
                    }
                    
                    // 效果選擇器切換（直屏）
                    spacingMobileSelect?.addEventListener('change', () => {
                        if (spacingMobileSelect.value === 'desktop') {
                            if (paddingMobileInput) paddingMobileInput.value = '';
                            if (radiusMobileInput) radiusMobileInput.value = '';
                        } else {
                            const selectedOption = spacingMobileSelect.options[spacingMobileSelect.selectedIndex];
                            const padding = selectedOption.dataset.padding || '16';
                            const radius = selectedOption.dataset.radius || '12';
                            if (paddingMobileInput) paddingMobileInput.value = padding;
                            if (radiusMobileInput) radiusMobileInput.value = radius;
                        }
                    });
                    
                    // 背景模式切換（橫屏）
                    bgModeSelect?.addEventListener('change', () => {
                        if (bgModeSelect.value === 'custom') {
                            bgInput.style.display = 'block';
                        } else {
                            bgInput.style.display = 'none';
                        }
                        updateBlockStyle(blockId);
                    });
                    
                    // 背景模式切換（直屏）
                    bgMobileModeSelect?.addEventListener('change', () => {
                        if (bgMobileModeSelect.value === 'custom') {
                            bgMobileInput.style.display = 'block';
                            bgMobileInput.dataset.useDesktop = 'false';
                        } else if (bgMobileModeSelect.value === 'desktop') {
                            bgMobileInput.style.display = 'none';
                            bgMobileInput.dataset.useDesktop = 'true';
                        } else {
                            bgMobileInput.style.display = 'none';
                            bgMobileInput.dataset.useDesktop = 'true';
                        }
                    });
                    
                    colspanSelect?.addEventListener('change', () => updateBlockStyle(blockId));
                    bgInput?.addEventListener('input', () => updateBlockStyle(blockId));
                    
                    // 直屏設置不需要實時更新樣式（只在保存時應用），但可以添加監聽器以備將來使用
                    colspanMobileSelect?.addEventListener('change', () => {});
                    // 修復：當用戶選擇顏色時，標記為不使用橫屏設置
                    bgMobileInput?.addEventListener('input', (e) => {
                        if (e.target.value) {
                            e.target.dataset.useDesktop = 'false';
                            if (bgMobileModeSelect) bgMobileModeSelect.value = 'custom';
                        }
                    });
                    
                    // 點擊外部關閉設置面板
                    document.addEventListener('click', (e) => {
                        if (!blockItem.contains(e.target)) {
                            settingsPanel.classList.remove('show');
                        }
                    });
                    
                    updateBlockStyle(blockId);
                    initSortable();
                }

                function copyBlock(blockId) {
                    const block = blockEditors.find(b => b.id === blockId);
                    if (!block) return;
                    const html = block.quill.root.innerHTML;
                    addBlock(html);
                }

                // 取得單一區塊的完整資料（與 getBlockEditorHTML 中一筆相同結構），供轉移使用
                function getBlockData(blockId) {
                    const block = blockEditors.find(b => b.id === blockId);
                    if (!block) return null;
                    const item = block.element;
                    const html = block.quill.root.innerHTML;
                    const colspan = parseInt(item.querySelector('.block-colspan')?.value || '4');
                    const spacingSelect = item.querySelector('.block-spacing');
                    let padding = 16, radius = 12;
                    if (spacingSelect?.options[spacingSelect.selectedIndex]) {
                        const opt = spacingSelect.options[spacingSelect.selectedIndex];
                        padding = parseInt(opt.dataset.padding || '16');
                        radius = parseInt(opt.dataset.radius || '12');
                    }
                    const paddingInput = item.querySelector('.block-padding');
                    const radiusInput = item.querySelector('.block-radius');
                    if (paddingInput?.value !== '') padding = parseInt(paddingInput.value) || padding;
                    if (radiusInput?.value !== '') radius = parseInt(radiusInput.value) || radius;
                    const bgMode = item.querySelector('.block-bg-mode')?.value || 'auto';
                    const bg = bgMode === 'auto' ? null : (item.querySelector('.block-bg')?.value || '#ffffff');
                    const colspanMobileEl = item.querySelector('.block-colspan-mobile');
                    const paddingMobileEl = item.querySelector('.block-padding-mobile');
                    const bgMobileModeSelect = item.querySelector('.block-bg-mobile-mode');
                    const bgMobileEl = item.querySelector('.block-bg-mobile');
                    const radiusMobileEl = item.querySelector('.block-radius-mobile');
                    const spacingMobileSelect = item.querySelector('.block-spacing-mobile');
                    let colspanMobile = colspanMobileEl ? parseInt(colspanMobileEl.value || '12') : 12;
                    let paddingMobile = null, radiusMobile = null, bgMobile = null;
                    if (spacingMobileSelect) {
                        if (spacingMobileSelect.value === 'desktop') {
                            paddingMobile = null;
                            radiusMobile = null;
                        } else {
                            paddingMobile = paddingMobileEl?.value !== '' && paddingMobileEl?.value != null ? parseInt(paddingMobileEl.value) : null;
                            radiusMobile = radiusMobileEl?.value !== '' && radiusMobileEl?.value != null ? parseInt(radiusMobileEl.value) : null;
                        }
                    } else {
                        paddingMobile = paddingMobileEl?.value !== '' && paddingMobileEl?.value != null ? parseInt(paddingMobileEl.value) : null;
                        radiusMobile = radiusMobileEl?.value !== '' && radiusMobileEl?.value != null ? parseInt(radiusMobileEl.value) : null;
                    }
                    if (bgMobileModeSelect && bgMobileEl) {
                        const m = bgMobileModeSelect.value;
                        if (m === 'custom' && bgMobileEl.value) bgMobile = bgMobileEl.value;
                        else if (m === 'desktop' && bg) bgMobile = bg;
                    }
                    return { html, colspan, padding, bg, radius, colspanMobile, paddingMobile, bgMobile, radiusMobile };
                }

                async function moveBlockToPage(blockId, targetPath) {
                    const blockData = getBlockData(blockId);
                    if (!blockData) {
                        showMsg('無法取得區塊資料', 'err');
                        return;
                    }
                    const currentPath = currentEditingPath || 'home';
                    if (targetPath === currentPath) {
                        showMsg('請選擇其他頁面作為目標', 'err');
                        return;
                    }
                    try {
                        const targetResponse = await fetch(`${API_URL}?action=page&path=${encodeURIComponent(targetPath)}`, { headers: { 'Accept': 'application/json' } });
                        const targetData = await targetResponse.json();
                        if (!targetData || targetData.page == null) {
                            showMsg('無法載入目標頁面', 'err');
                            return;
                        }
                        const targetPage = targetData.page || {};
                        let targetHtml = (targetData.html != null ? targetData.html : '') || '';
                        const blockDataMatch = targetHtml.match(/<div class="gwa-block-editor-data"[^>]*>([\s\S]*?)<\/div>/);
                        let blocks;
                        if (blockDataMatch) {
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = blockDataMatch[1];
                            const blockDataJson = tempDiv.textContent || tempDiv.innerText || blockDataMatch[1];
                            blocks = JSON.parse(blockDataJson);
                            if (!Array.isArray(blocks)) blocks = [];
                        } else {
                            blocks = [{ html: '<p><br></p>', colspan: 12, padding: 16, bg: null, radius: 12, colspanMobile: 12, paddingMobile: null, bgMobile: null, radiusMobile: null }];
                        }
                        blocks.push(blockData);
                        const blockDataStr = JSON.stringify(blocks);
                        const encDiv = document.createElement('div');
                        encDiv.textContent = blockDataStr;
                        const encodedData = encDiv.innerHTML;
                        const newTargetHtml = '<div class="gwa-block-editor-data" style="display:none;">' + encodedData + '</div>';
                        await apiJson(`${API_URL}?action=save_page`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                            body: JSON.stringify({ page: targetPage, html: newTargetHtml, oldPath: targetPath })
                        });
                        await saveDraftForPath(targetPath, blockDataStr);
                        const index = blockEditors.findIndex(b => b.id === blockId);
                        if (index !== -1 && blockEditors.length > 1) {
                            const block = blockEditors[index];
                            if (block.isMain) {
                                showMsg('主編輯器區塊不可轉移', 'err');
                                return;
                            }
                            block.element.remove();
                            blockEditors.splice(index, 1);
                            const currentHtml = getBlockEditorHTML();
                            const encCurrent = document.createElement('div');
                            encCurrent.textContent = currentHtml || '[]';
                            const currentEncoded = encCurrent.innerHTML;
                            const currentPageHtml = currentHtml ? ('<div class="gwa-block-editor-data" style="display:none;">' + currentEncoded + '</div>') : '';
                            const currentPage = {
                                path: currentPath,
                                title: (elTitle && elTitle.value) || '',
                                menu_title: (elMenuTitle && elMenuTitle.value) || '',
                                type: (elPageType && elPageType.value) || 'page',
                                price: (elPageType && elPageType.value === 'product' && elPrice) ? parseFloat(elPrice.value || 0) : 0,
                                layout_full_width: document.getElementById('layoutFullWidth')?.checked || false,
                                layout_block_align: document.getElementById('layoutBlockAlign')?.value || 'center'
                            };
                            if (!currentPath || currentPath === '') currentPage.parent = (elParent && elParent.value) || '';
                            await apiJson(`${API_URL}?action=save_page`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                                body: JSON.stringify({ page: currentPage, html: currentPageHtml, oldPath: currentPath })
                            });
                            initSortable();
                            scheduleDraftSave();
                            showMsg('已轉移至目標頁面', 'ok');
                        }
                    } catch (e) {
                        console.error('[GWA] 轉移區塊失敗', e);
                        showMsg(e.message || '轉移失敗', 'err');
                    }
                }

                function showMoveBlockDialog(blockId) {
                    const block = blockEditors.find(b => b.id === blockId);
                    if (!block) return;
                    if (block.isMain) {
                        showMsg('主編輯器區塊不可轉移', 'err');
                        return;
                    }
                    if (!getBlockData(blockId)) {
                        showMsg('無法取得區塊資料', 'err');
                        return;
                    }
                    const currentPath = currentEditingPath || 'home';
                    const modal = document.createElement('div');
                    modal.className = 'modal-overlay';
                    modal.style.cssText = 'position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center;';
                    const content = document.createElement('div');
                    content.style.cssText = 'background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 8px 32px rgba(0,0,0,0.3);';
                    const title = document.createElement('h3');
                    title.textContent = '轉移區塊到其他頁面';
                    title.style.cssText = 'margin: 0 0 16px 0; color: var(--text);';
                    const hint = document.createElement('p');
                    hint.textContent = '選擇目標頁面後，此區塊會從本頁移除並加入目標頁末尾。';
                    hint.style.cssText = 'margin: 0 0 12px 0; font-size: 13px; color: var(--muted);';
                    const searchInput = document.createElement('input');
                    searchInput.type = 'text';
                    searchInput.placeholder = '搜尋頁面…';
                    searchInput.style.cssText = 'width: 100%; padding: 10px 12px; margin-bottom: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg2); color: var(--text); font-size: 14px;';
                    const listEl = document.createElement('div');
                    listEl.style.cssText = 'max-height: 280px; overflow-y: auto; margin-bottom: 16px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg2);';
                    let selectedPath = null;
                    function renderList(filter) {
                        const f = (filter || '').toLowerCase();
                        const pages = (pagesCache || []).filter(p => {
                            const path = (p.path || '').toLowerCase();
                            if (path === currentPath) return false;
                            if (!f) return true;
                            const title = (p.title || '').toLowerCase();
                            const menu = (p.menu_title || '').toLowerCase();
                            return title.includes(f) || menu.includes(f) || path.includes(f);
                        });
                        listEl.innerHTML = '';
                        if (pages.length === 0) {
                            listEl.innerHTML = '<div style="padding: 16px; text-align: center; color: var(--muted);">無可選頁面</div>';
                            return;
                        }
                        pages.forEach(p => {
                            const row = document.createElement('div');
                            row.style.cssText = 'padding: 12px 14px; border-bottom: 1px solid var(--border); cursor: pointer;' + (selectedPath === (p.path || '') ? ' background: var(--accent, rgba(124,92,255,0.2));' : '');
                            row.innerHTML = '<div style="font-weight: 500;">' + (p.menu_title || p.title || p.path) + '</div><div style="font-size: 12px; color: var(--muted);">' + (p.path || '') + '</div>';
                            row.onclick = () => {
                                selectedPath = p.path || '';
                                listEl.querySelectorAll('div[data-page-row]').forEach(el => { el.style.background = ''; });
                                row.style.background = 'var(--accent, rgba(124,92,255,0.2))';
                            };
                            row.dataset.pageRow = '1';
                            listEl.appendChild(row);
                        });
                    }
                    searchInput.addEventListener('input', () => renderList(searchInput.value));
                    renderList();
                    const btnRow = document.createElement('div');
                    btnRow.style.cssText = 'display: flex; gap: 10px; justify-content: flex-end;';
                    const btnCancel = document.createElement('button');
                    btnCancel.className = 'btn';
                    btnCancel.textContent = '取消';
                    btnCancel.onclick = () => modal.remove();
                    const btnMove = document.createElement('button');
                    btnMove.className = 'btn btn-ok';
                    btnMove.textContent = '轉移';
                    btnMove.onclick = async () => {
                        if (!selectedPath) {
                            showMsg('請先選擇目標頁面', 'err');
                            return;
                        }
                        modal.remove();
                        await moveBlockToPage(blockId, selectedPath);
                    };
                    btnRow.appendChild(btnCancel);
                    btnRow.appendChild(btnMove);
                    content.appendChild(title);
                    content.appendChild(hint);
                    content.appendChild(searchInput);
                    content.appendChild(listEl);
                    content.appendChild(btnRow);
                    modal.appendChild(content);
                    modal.onclick = (e) => { if (e.target === modal) modal.remove(); };
                    content.onclick = (e) => e.stopPropagation();
                    document.body.appendChild(modal);
                }

                function deleteBlock(blockId) {
                    // 原子性檢查：確保至少保留一個區塊
                    if (blockEditors.length <= 1) {
                        showMsg('至少需要保留一個區塊', 'err');
                        return;
                    }
                    
                    // 檢查是否為主編輯器
                    const block = blockEditors.find(b => b.id === blockId);
                    if (block && block.isMain) {
                        showMsg('主編輯器不能刪除', 'err');
                        return;
                    }
                    
                    if (!confirm('確定要刪除此區塊嗎？')) return;
                    
                    // 原子性刪除：先檢查再執行
                    const index = blockEditors.findIndex(b => b.id === blockId);
                    if (index !== -1 && blockEditors.length > 1) {
                        blockEditors[index].element.remove();
                        blockEditors.splice(index, 1);
                        initSortable(); // 重新初始化排序
                    }
                }

                // 根據 padding 和 radius 值找到對應的效果選項
                function findSpacingOption(padding, radius) {
                    // 確保是數字類型
                    const p = parseInt(padding) || 0;
                    const r = parseInt(radius) || 0;
                    const spacingMap = {
                        '4-0': 'compact',
                        '8-4': 'tight',
                        '16-12': 'normal',
                        '24-16': 'wide',
                        '32-20': 'spacious'
                    };
                    const key = `${p}-${r}`;
                    return spacingMap[key] || 'normal';
                }
                
                // 設置效果選擇器
                function setSpacingSelect(select, padding, radius) {
                    if (!select) return;
                    // 確保是數字類型
                    const p = parseInt(padding) || 0;
                    const r = parseInt(radius) || 0;
                    const option = findSpacingOption(p, r);
                    select.value = option;
                    // 更新隱藏的 input 值（使用實際值，即使不匹配預設選項）
                    const paddingInput = select.closest('.field')?.querySelector('.block-padding');
                    const radiusInput = select.closest('.field')?.querySelector('.block-radius');
                    if (paddingInput) paddingInput.value = p;
                    if (radiusInput) radiusInput.value = r;
                }
                
                // 初始化效果選擇器（確保隱藏的 input 有正確的默認值）
                function initSpacingSelect(select) {
                    if (!select) return;
                    const selectedOption = select.options[select.selectedIndex];
                    if (selectedOption) {
                        const padding = selectedOption.dataset.padding || '16';
                        const radius = selectedOption.dataset.radius || '12';
                        const paddingInput = select.closest('.field')?.querySelector('.block-padding');
                        const radiusInput = select.closest('.field')?.querySelector('.block-radius');
                        if (paddingInput) paddingInput.value = padding;
                        if (radiusInput) radiusInput.value = radius;
                    }
                }
                
                function updateBlockStyle(blockId) {
                    const block = blockEditors.find(b => b.id === blockId);
                    if (!block) return;
                    const item = block.element;
                    const colspan = parseInt(item.querySelector('.block-colspan')?.value || '4');
                    const padding = parseInt(item.querySelector('.block-padding')?.value || '16');
                    const bgMode = item.querySelector('.block-bg-mode')?.value || 'auto';
                    const bg = item.querySelector('.block-bg')?.value || '#ffffff';
                    const radius = parseInt(item.querySelector('.block-radius')?.value || '12');
                    
                    // 12 列網格系統，每列佔 1/12
                    item.style.gridColumn = `span ${Math.min(colspan, 12)}`;
                    item.style.padding = `${padding}px`;
                    // 自動模式：移除背景色樣式，使用主題的 --page-bg
                    if (bgMode === 'auto') {
                        item.style.backgroundColor = '';
                    } else {
                        item.style.backgroundColor = bg;
                    }
                    item.style.borderRadius = `${radius}px`;
                }

                function initSortable() {
                    if (typeof Sortable !== 'undefined') {
                        if (sortableInstance) {
                            sortableInstance.destroy();
                        }
                        sortableInstance = new Sortable(blockGrid, {
                            animation: 200,
                            handle: '.block-item',
                            ghostClass: 'dragging',
                            onStart: (e) => e.item.classList.add('dragging'),
                            onEnd: (e) => {
                                e.item.classList.remove('dragging');
                                // 重新排序 blockEditors 數組以匹配 DOM 順序
                                const newOrder = [];
                                Array.from(blockGrid.children).forEach(child => {
                                    const blockId = child.dataset.blockId;
                                    const block = blockEditors.find(b => b.id === blockId);
                                    if (block) newOrder.push(block);
                                });
                                blockEditors = newOrder;
                            }
                        });
                    }
                }

                if (btnAddBlock) {
                    btnAddBlock.addEventListener('click', () => addBlock());
                }

                // 布置調整按鈕
                const btnLayoutSettings = document.getElementById('btnLayoutSettings');
                const layoutSettingsPanel = document.getElementById('layoutSettingsPanel');
                if (btnLayoutSettings && layoutSettingsPanel) {
                    btnLayoutSettings.addEventListener('click', () => {
                        layoutSettingsPanel.style.display = layoutSettingsPanel.style.display === 'none' ? 'block' : 'none';
                    });
                }

                // 保存布置設置（不需要單獨保存，會在 savePage 時一起保存）
                const btnSaveLayoutSettings = document.getElementById('btnSaveLayoutSettings');
                if (btnSaveLayoutSettings) {
                    btnSaveLayoutSettings.addEventListener('click', () => {
                        showMsg('布置設置將在保存頁面時一起保存', 'ok');
                        if (layoutSettingsPanel) {
                            layoutSettingsPanel.style.display = 'none';
                        }
                    });
                }

                // 獲取區塊編輯器 HTML（原子性操作）
                function getBlockEditorHTML() {
                    if (blockEditors.length === 0) return null;
                    
                    // 原子性收集：確保所有區塊數據完整
                    const blocks = [];
                    try {
                        blockEditors.forEach(block => {
                            const item = block.element;
                            const html = block.quill.root.innerHTML;
                            // 橫屏設置
                            const colspan = parseInt(item.querySelector('.block-colspan')?.value || '4');
                            
                            // 確保從效果選擇器獲取最新的值
                            const spacingSelect = item.querySelector('.block-spacing');
                            let padding = 16;
                            let radius = 12;
                            if (spacingSelect) {
                                const selectedOption = spacingSelect.options[spacingSelect.selectedIndex];
                                if (selectedOption) {
                                    padding = parseInt(selectedOption.dataset.padding || '16');
                                    radius = parseInt(selectedOption.dataset.radius || '12');
                                }
                            }
                            // 如果沒有效果選擇器或獲取失敗，從隱藏的 input 讀取（向後兼容）
                            const paddingInput = item.querySelector('.block-padding');
                            const radiusInput = item.querySelector('.block-radius');
                            if (paddingInput && paddingInput.value !== '') {
                                padding = parseInt(paddingInput.value) || padding;
                            }
                            if (radiusInput && radiusInput.value !== '') {
                                radius = parseInt(radiusInput.value) || radius;
                            }
                            
                            const bgMode = item.querySelector('.block-bg-mode')?.value || 'auto';
                            // 自動模式：保存為 null，否則保存顏色值
                            const bg = bgMode === 'auto' ? null : (item.querySelector('.block-bg')?.value || '#ffffff');
                            // 直屏設置（如果存在）
                            const colspanMobileEl = item.querySelector('.block-colspan-mobile');
                            const paddingMobileEl = item.querySelector('.block-padding-mobile');
                            const bgMobileModeSelect = item.querySelector('.block-bg-mobile-mode');
                            const bgMobileEl = item.querySelector('.block-bg-mobile');
                            const radiusMobileEl = item.querySelector('.block-radius-mobile');
                            
                            const colspanMobile = colspanMobileEl ? parseInt(colspanMobileEl.value || '12') : 12;
                            
                            // 直屏效果設置
                            const spacingMobileSelect = item.querySelector('.block-spacing-mobile');
                            let paddingMobile = null;
                            let radiusMobile = null;
                            if (spacingMobileSelect) {
                                if (spacingMobileSelect.value === 'desktop') {
                                    // 使用橫屏設置，保存為 null
                                    paddingMobile = null;
                                    radiusMobile = null;
                                } else {
                                    // 自定義效果
                                    const paddingMobileVal = paddingMobileEl?.value;
                                    const radiusMobileVal = radiusMobileEl?.value;
                                    paddingMobile = paddingMobileVal !== '' && paddingMobileVal !== null && paddingMobileVal !== undefined ? parseInt(paddingMobileVal) : null;
                                    radiusMobile = radiusMobileVal !== '' && radiusMobileVal !== null && radiusMobileVal !== undefined ? parseInt(radiusMobileVal) : null;
                                }
                            } else {
                                // 兼容舊版本：如果沒有效果選擇器，使用舊邏輯
                                const paddingMobileVal = paddingMobileEl?.value;
                                const radiusMobileVal = radiusMobileEl?.value;
                                paddingMobile = paddingMobileVal !== '' && paddingMobileVal !== null ? parseInt(paddingMobileVal) : null;
                                radiusMobile = radiusMobileVal !== '' && radiusMobileVal !== null ? parseInt(radiusMobileVal) : null;
                            }
                            
                            // 直屏背景設置
                            let bgMobile = null;
                            if (bgMobileEl && bgMobileModeSelect) {
                                const bgMobileMode = bgMobileModeSelect.value || 'auto';
                                if (bgMobileMode === 'custom') {
                                    const bgMobileVal = bgMobileEl.value || '';
                                    if (bgMobileVal !== '') {
                                        bgMobile = bgMobileVal;
                                    }
                                } else if (bgMobileMode === 'desktop' && bg !== null) {
                                    // 使用橫屏設置
                                    bgMobile = bg;
                                }
                                // auto 模式：bgMobile 保持為 null
                            }
                            
                            blocks.push({ 
                                html, 
                                colspan, padding, bg, radius,
                                colspanMobile, paddingMobile, bgMobile, radiusMobile
                            });
                        });
                        return blocks.length > 0 ? JSON.stringify(blocks) : null;
                    } catch (e) {
                        console.error('獲取區塊數據失敗:', e);
                        return null;
                    }
                }

                // ===== 草稿（IndexedDB 儲存，避免 localStorage 配額不足）=====
                const DRAFT_DB_NAME = 'gwa_drafts_db';
                const DRAFT_STORE = 'drafts';
                function draftPath(path) { return String(path || 'home').toLowerCase(); }
                function openDraftDB() {
                    return new Promise((resolve, reject) => {
                        const r = indexedDB.open(DRAFT_DB_NAME, 1);
                        r.onerror = () => reject(r.error);
                        r.onsuccess = () => resolve(r.result);
                        r.onupgradeneeded = (e) => { e.target.result.createObjectStore(DRAFT_STORE, { keyPath: 'path' }); };
                    });
                }
                async function saveDraftToStorage(path) {
                    const json = getBlockEditorHTML();
                    if (!json) return;
                    return saveDraftForPath(path, json);
                }
                /** 將指定 blocksJson 寫入某頁的草稿（用於搬遷區塊後同步目標頁草稿） */
                async function saveDraftForPath(path, blocksJson) {
                    if (!blocksJson || String(blocksJson).trim().length === 0) return;
                    const key = draftPath(path);
                    const record = { path: key, blocksJson: blocksJson, savedAt: Date.now() };
                    try {
                        const db = await openDraftDB();
                        return new Promise((resolve, reject) => {
                            const tx = db.transaction(DRAFT_STORE, 'readwrite');
                            tx.onerror = () => reject(tx.error);
                            tx.oncomplete = () => resolve();
                            tx.objectStore(DRAFT_STORE).put(record);
                        }).finally(() => db.close());
                    } catch (e) {
                        console.warn('[GWA] draft save failed', e);
                        if (typeof showMsg === 'function') showMsg('草稿無法儲存：' + (e.message || String(e)), 'err');
                    }
                }
                async function loadDraftFromStorage(path) {
                    try {
                        const db = await openDraftDB();
                        return new Promise((resolve, reject) => {
                            const tx = db.transaction(DRAFT_STORE, 'readonly');
                            const req = tx.objectStore(DRAFT_STORE).get(draftPath(path));
                            req.onerror = () => reject(req.error);
                            req.onsuccess = () => resolve(req.result || null);
                        }).finally(() => db.close());
                    } catch (e) { return null; }
                }
                async function hasValidDraft(path) {
                    const draft = await loadDraftFromStorage(path);
                    return !!(draft && draft.blocksJson && String(draft.blocksJson).trim().length > 0);
                }
                async function removeDraft(path) {
                    try {
                        const db = await openDraftDB();
                        return new Promise((resolve, reject) => {
                            const tx = db.transaction(DRAFT_STORE, 'readwrite');
                            tx.onerror = () => reject(tx.error);
                            tx.oncomplete = () => resolve();
                            tx.objectStore(DRAFT_STORE).delete(draftPath(path));
                        }).finally(() => db.close());
                    } catch (e) {}
                }
                async function updateDraftUI() {
                    const path = currentEditingPath || 'home';
                    const hasDraft = await hasValidDraft(path);
                    const el = document.getElementById('draftActions');
                    const badge = document.getElementById('draftBadge');
                    const btnRestore = document.getElementById('btnRestoreLive');
                    if (el) el.style.display = 'flex';
                    if (badge) badge.textContent = hasDraft ? '草稿中' : '無草稿';
                    if (btnRestore) { btnRestore.style.display = hasDraft ? '' : 'none'; btnRestore.disabled = !hasDraft; }
                }
                let draftSaveTimer = null;
                function scheduleDraftSave() {
                    if (draftSaveTimer) clearTimeout(draftSaveTimer);
                    draftSaveTimer = setTimeout(() => {
                        draftSaveTimer = null;
                        saveDraftToStorage(currentEditingPath || 'home').then(() => updateDraftUI()).catch(() => {});
                    }, 1500);
                }

                // 載入區塊編輯器 HTML（原子性操作）
                function loadBlockEditorHTML(jsonStr) {
                    if (!jsonStr) {
                        // 如果沒有區塊數據，確保主編輯器存在
                        if (blockEditors.length === 0 && mainBlockItem) {
                            blockEditors.push({ 
                                id: 'main-editor', 
                                quill: quill, 
                                element: mainBlockItem,
                                isMain: true
                            });
                        }
                        return;
                    }
                    
                    try {
                        const blocks = JSON.parse(jsonStr);
                        if (!Array.isArray(blocks) || blocks.length === 0) return;
                        
                        // 原子性載入：先準備所有數據，再一次性應用
                        // 載入主編輯器（第一個區塊）
                        if (blocks.length > 0 && mainBlockItem) {
                            const mainBlock = blocks[0];
                            quill.setContents([], 'silent');
                            quill.clipboard.dangerouslyPasteHTML(0, mainBlock.html || '', 'silent');
                            
                            const colspanSelect = mainBlockItem.querySelector('.block-colspan');
                            const spacingSelect = mainBlockItem.querySelector('.block-spacing');
                            const paddingInput = mainBlockItem.querySelector('.block-padding');
                            const bgModeSelect = mainBlockItem.querySelector('.block-bg-mode');
                            const bgInput = mainBlockItem.querySelector('.block-bg');
                            const radiusInput = mainBlockItem.querySelector('.block-radius');
                            
                            // 使用 nullish coalescing 確保 0 值不會被替換
                            const padding = mainBlock.padding !== null && mainBlock.padding !== undefined ? mainBlock.padding : 16;
                            const radius = mainBlock.radius !== null && mainBlock.radius !== undefined ? mainBlock.radius : 12;
                            // 填滿效果：自動設置為全寬（12列）
                            const isCompact = (padding === 4 && radius === 0);
                            if (colspanSelect) colspanSelect.value = isCompact ? 12 : (mainBlock.colspan || 4);
                            setSpacingSelect(spacingSelect, padding, radius);
                            // 背景模式：如果 bg 為 null，使用自動模式
                            if (bgModeSelect) {
                                if (mainBlock.bg === null || mainBlock.bg === undefined) {
                                    bgModeSelect.value = 'auto';
                                    if (bgInput) bgInput.style.display = 'none';
                                } else {
                                    bgModeSelect.value = 'custom';
                                    if (bgInput) {
                                        bgInput.value = mainBlock.bg || '#ffffff';
                                        bgInput.style.display = 'block';
                                    }
                                }
                            }
                            updateBlockStyle('main-editor');
                            
                            // 確保主編輯器在 blockEditors 中
                            const mainIndex = blockEditors.findIndex(b => b.id === 'main-editor');
                            if (mainIndex === -1) {
                                blockEditors.push({ 
                                    id: 'main-editor', 
                                    quill: quill, 
                                    element: mainBlockItem,
                                    isMain: true
                                });
                            }
                        }
                        
                        // 載入其他區塊（從第二個開始）
                        const otherBlocks = blocks.slice(1);
                        // 先移除所有非主編輯器的區塊
                        blockEditors.filter(b => !b.isMain).forEach(b => b.element.remove());
                        blockEditors = blockEditors.filter(b => b.isMain);
                        
                        // 添加其他區塊
                        otherBlocks.forEach(block => {
                            addBlock(block.html);
                            const currentBlock = blockEditors[blockEditors.length - 1];
                            if (currentBlock) {
                                const item = currentBlock.element;
                                // 橫屏設置
                                const colspanSelect = item.querySelector('.block-colspan');
                                const spacingSelect = item.querySelector('.block-spacing');
                                const paddingInput = item.querySelector('.block-padding');
                                const bgModeSelect = item.querySelector('.block-bg-mode');
                                const bgInput = item.querySelector('.block-bg');
                                const radiusInput = item.querySelector('.block-radius');
                                
                                // 使用 nullish coalescing 確保 0 值不會被替換
                                const padding = block.padding !== null && block.padding !== undefined ? block.padding : 16;
                                const radius = block.radius !== null && block.radius !== undefined ? block.radius : 12;
                                // 填滿效果：自動設置為全寬（12列）
                                const isCompact = (padding === 4 && radius === 0);
                                if (colspanSelect) colspanSelect.value = isCompact ? 12 : (block.colspan || 4);
                                setSpacingSelect(spacingSelect, padding, radius);
                                // 背景模式：如果 bg 為 null，使用自動模式
                                if (bgModeSelect) {
                                    if (block.bg === null || block.bg === undefined) {
                                        bgModeSelect.value = 'auto';
                                        if (bgInput) bgInput.style.display = 'none';
                                    } else {
                                        bgModeSelect.value = 'custom';
                                        if (bgInput) {
                                            bgInput.value = block.bg || '#ffffff';
                                            bgInput.style.display = 'block';
                                        }
                                    }
                                }
                                
                                // 直屏設置（如果存在）
                                const colspanMobileEl = item.querySelector('.block-colspan-mobile');
                                const spacingMobileSelect = item.querySelector('.block-spacing-mobile');
                                const paddingMobileEl = item.querySelector('.block-padding-mobile');
                                const bgMobileModeSelect = item.querySelector('.block-bg-mobile-mode');
                                const bgMobileEl = item.querySelector('.block-bg-mobile');
                                const radiusMobileEl = item.querySelector('.block-radius-mobile');
                                
                                if (colspanMobileEl) colspanMobileEl.value = block.colspanMobile || 12;
                                if (spacingMobileSelect) {
                                    const paddingMobileVal = block.paddingMobile;
                                    const radiusMobileVal = block.radiusMobile;
                                    
                                    // 檢查是否使用橫屏設置
                                    if (paddingMobileVal === null || paddingMobileVal === undefined || 
                                        (paddingMobileVal === padding && (radiusMobileVal === null || radiusMobileVal === undefined || radiusMobileVal === radius))) {
                                        // 使用橫屏設置
                                        spacingMobileSelect.value = 'desktop';
                                        if (paddingMobileEl) paddingMobileEl.value = '';
                                        if (radiusMobileEl) radiusMobileEl.value = '';
                                    } else {
                                        // 自定義效果
                                        const paddingMobile = paddingMobileVal !== null && paddingMobileVal !== undefined ? paddingMobileVal : padding;
                                        const radiusMobile = radiusMobileVal !== null && radiusMobileVal !== undefined ? radiusMobileVal : radius;
                                        setSpacingSelect(spacingMobileSelect, paddingMobile, radiusMobile);
                                    }
                                }
                                if (bgMobileModeSelect && bgMobileEl) {
                                    if (block.bgMobile === null || block.bgMobile === undefined) {
                                        // 自動模式
                                        bgMobileModeSelect.value = 'auto';
                                        bgMobileEl.style.display = 'none';
                                        bgMobileEl.dataset.useDesktop = 'true';
                                    } else if (block.bgMobile === block.bg) {
                                        // 使用橫屏設置
                                        bgMobileModeSelect.value = 'desktop';
                                        bgMobileEl.style.display = 'none';
                                        bgMobileEl.dataset.useDesktop = 'true';
                                    } else {
                                        // 自定義顏色
                                        bgMobileModeSelect.value = 'custom';
                                        bgMobileEl.value = block.bgMobile;
                                        bgMobileEl.style.display = 'block';
                                        bgMobileEl.dataset.useDesktop = 'false';
                                    }
                                }
                                
                                updateBlockStyle(currentBlock.id);
                            }
                        });
                        
                        initSortable();
                    } catch (e) {
                        console.error('載入區塊失敗:', e);
                        showMsg('載入區塊數據失敗', 'err');
                    }
                }

                // ===== 多行多列區塊編輯器系統結束 =====

                // Ctrl+S 儲存（更像 Word）
                document.addEventListener('keydown', (e) => {
                    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
                        e.preventDefault();
                        btnSave && btnSave.click();
                    }
                });

                // 拖放圖片上傳（更像 Word）
                quill.root.addEventListener('drop', (e) => {
                    const dt = e.dataTransfer;
                    if (!dt || !dt.files || !dt.files.length) return;
                    const file = dt.files[0];
                    if (!file || !file.type || !file.type.startsWith('image/')) return;
                    e.preventDefault();
                    uploadImage(file);
                });

                // Word/Office paste cleaner (best-effort)
                function stripWordJunk(html) {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    // remove comments
                    const walker = doc.createTreeWalker(doc, NodeFilter.SHOW_COMMENT, null);
                    const comments = [];
                    while (walker.nextNode()) comments.push(walker.currentNode);
                    comments.forEach(n => n.parentNode && n.parentNode.removeChild(n));
                    // remove dangerous tags
                    doc.querySelectorAll('meta,style,script,link,xml').forEach(n => n.remove());
                    // drop mso attrs/styles/classes
                    doc.querySelectorAll('*').forEach(el => {
                        const cls = el.getAttribute('class') || '';
                        if (/Mso|mso/i.test(cls)) el.removeAttribute('class');
                        const style = el.getAttribute('style') || '';
                        if (/mso-|tab-stops|font-variant|text-underline|page-break|vnd\.ms/i.test(style)) {
                            el.removeAttribute('style');
                        } else if (style) {
                            // keep only very safe styles
                            const keep = [];
                            style.split(';').forEach(rule => {
                                const r = rule.trim().toLowerCase();
                                if (!r) return;
                                if (r.startsWith('text-align') || r.startsWith('color') || r.startsWith('background-color') || r.startsWith('font-weight') || r.startsWith('font-style') || r.startsWith('text-decoration') || r.startsWith('line-height')) {
                                    keep.push(rule.trim());
                                }
                            });
                            if (keep.length) el.setAttribute('style', keep.join('; '));
                            else el.removeAttribute('style');
                        }
                        el.removeAttribute('lang');
                    });
                    doc.querySelectorAll('o\\:p').forEach(n => n.remove());
                    return doc.body ? doc.body.innerHTML : html;
                }

                quill.root.addEventListener('paste', function(e) {
                    const htmlData = e.clipboardData && e.clipboardData.getData('text/html');
                    if (!htmlData) return;

                    // 簡化判斷，避免不同瀏覽器對 regex escape 的解析差異
                    const looksLikeWordPattern = new RegExp('(class=["\']?Mso|mso-|<!--\\[if\\s+gte\\s+mso)', 'i');
                    const looksLikeWord = looksLikeWordPattern.test(htmlData);
                    const hasBase64Pattern = new RegExp('<img[^>]+src=["\']data:', 'i');
                    const hasBase64 = hasBase64Pattern.test(htmlData);
                    if (!looksLikeWord && !hasBase64) return;

                    e.preventDefault();
                    const sel = quill.getSelection(true);
                    const idx = sel ? sel.index : quill.getLength();

                    const tmp = document.createElement('div');
                    tmp.innerHTML = htmlData;
                    // upload base64 images then remove them from html
                    const imgs = tmp.querySelectorAll('img[src^="data:"]');
                    imgs.forEach(img => {
                        const src = img.getAttribute('src') || '';
                        const base64 = src.split(',')[1] || '';
                        const mimeMatch = src.match(/data:([^;]+);/);
                        if (!mimeMatch) { img.remove(); return; }
                        const mime = mimeMatch[1];
                        try {
                            const bytes = atob(base64);
                            const arr = new Uint8Array(bytes.length);
                            for (let i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);
                            const blob = new Blob([arr], { type: mime });
                            const ext = (mime.split('/')[1] || 'png');
                            const file = new File([blob], 'pasted.' + ext, { type: mime });
                            uploadImage(file);
                        } catch (ex) {}
                        img.remove();
                    });

                    const cleaned = stripWordJunk(tmp.innerHTML);
                    quill.clipboard.dangerouslyPasteHTML(idx, cleaned, 'user');
                });

                function showMsg(text, kind) {
                    statusMsg.style.display = 'block';
                    statusMsg.className = 'msg ' + (kind || '');
                    statusMsg.textContent = text;
                    clearTimeout(showMsg._t);
                    showMsg._t = setTimeout(() => { statusMsg.style.display = 'none'; }, 2500);
                }

                function hash36(str) {
                    let h = 5381;
                    const s = String(str || '');
                    for (let i = 0; i < s.length; i++) {
                        h = ((h << 5) + h) ^ s.charCodeAt(i);
                    }
                    return (h >>> 0).toString(36);
                }

                function slugify(text) {
                    let s = String(text || '').trim().toLowerCase();
                    // 盡量把空白/分隔符變成 -
                    s = s.replace(/[\s_]+/g, '-');
                    // 非允許字元 -> -
                    s = s.replace(/[^a-z0-9-]/g, '-');
                    s = s.replace(/-+/g, '-').replace(/^-+|-+$/g, '');
                    return s;
                }

                function ensureUniquePath(path, oldPath) {
                    const existing = new Set((pagesCache || []).map(p => p.path));
                    if (oldPath) existing.delete(oldPath);
                    if (!existing.has(path)) return path;
                    let i = 2;
                    while (existing.has(path + '-' + i)) i++;
                    return path + '-' + i;
                }

                function computePath() {
                    // home 固定
                    if (currentEditingPath === 'home') return 'home';

                    // 編輯既有頁面：網址保持不變（Menu 父層拖放不影響網址）
                    if (currentEditingPath && currentEditingPath !== '') return currentEditingPath;

                    const name = (elMenuTitle.value || '').trim();
                    const title = (elTitle.value || '').trim();
                    const seed = name || title;

                    let slug = slugify(seed);
                    if (!slug) {
                        slug = 'p-' + hash36(seed || (Date.now() + '')).slice(0, 8);
                    }
                    if (slug === 'home') slug = 'home-' + hash36(seed).slice(0, 6);

                    return ensureUniquePath(slug, currentEditingPath || '');
                }

                function getFullUrl(path) {
                    if (!path || path === '') return '';
                    const baseUrl = window.location.origin + BASE_PATH;
                    if (path === 'home') {
                        return baseUrl.replace(/\/$/, '');
                    }
                    return baseUrl + path;
                }

                function updateAutoPath() {
                    const p = computePath();
                    const path = (currentEditingPath === 'home') ? 'home' : (p || '');
                    // 顯示完整 URL 而不是相對路徑
                    elPath.value = getFullUrl(path);
                }

                elMenuTitle.addEventListener('input', () => {
                    // 便利：如果標題沒填，先用頁名帶一下
                    if (!(elTitle.value || '').trim()) {
                        elTitle.value = (elMenuTitle.value || '').trim();
                    }
                    updateAutoPath();
                });
                elTitle.addEventListener('input', updateAutoPath);
                
                // 切換價格欄位顯示/隱藏
                function togglePriceField(show) {
                    const priceField = document.getElementById('priceField');
                    if (priceField) {
                        priceField.style.display = show ? 'block' : 'none';
                    }
                }
                
                // 頁面類型變更時顯示/隱藏價格欄位
                if (elPageType) {
                    elPageType.addEventListener('change', () => {
                        togglePriceField(elPageType.value === 'product');
                        if (elPageType.value !== 'product' && elPrice) {
                            elPrice.value = '0';
                        }
                    });
                }

                async function apiJson(url, options) {
                    const res = await fetch(url, options);
                    const data = await res.json().catch(() => null);
                    if (!data || !data.ok) {
                        const errorMsg = (data && data.error) ? data.error : 'API 失敗';
                        console.error('[API Error]', {
                            url,
                            status: res.status,
                            statusText: res.statusText,
                            error: errorMsg,
                            data: data
                        });
                        throw new Error(errorMsg);
                    }
                    if (data.csrfToken) CSRF_TOKEN = data.csrfToken;
                    return data;
                }

                function escapeHtml(s) {
                    return String(s)
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll('"', '&quot;')
                        .replaceAll("'", '&#039;');
                }

                function labelFor(p) {
                    const mt = (p.menu_title || '').trim();
                    if (mt) return mt;
                    const tt = (p.title || '').trim();
                    return tt || p.path;
                }

                function effectiveParentClient(p) {
                    const parent = (p.parent || '').replace(/^\/+|\/+$/g,'');
                    const explicit = !!p.parent_explicit;
                    const path = String(p.path || '');
                    
                    // 如果 parent 已明確設置（parent_explicit = true），直接返回，不從路徑推導
                    // 這確保用戶拖動設定的 parent 不會被覆蓋
                    if (explicit) {
                        return parent;
                    }
                    
                    // 如果 parent 未明確設置且為空，但路徑包含 /，則從路徑推導
                    // 這確保了即使數據不一致，也能正確構建樹結構
                    if (!parent && path.includes('/') && path !== 'home') {
                        return path.split('/').slice(0, -1).join('/');
                    }
                    return parent;
                }

                function buildTreeClient(pages) {
                    const map = new Map();
                    pages.forEach(p => map.set(p.path, { data: p, children: [] }));
                    
                    pages.forEach(p => {
                        const parent = effectiveParentClient(p);
                        if (parent && map.has(parent)) {
                            map.get(parent).children.push(map.get(p.path));
                        }
                    });
                    const roots = [];
                    map.forEach((node) => {
                        const parent = effectiveParentClient(node.data);
                        if (!parent || !map.has(parent)) {
                            roots.push(node);
                        }
                    });
                    const sortRec = (nodes) => {
                        nodes.sort((a,b) => {
                            const ao = (a.data.order ?? 0);
                            const bo = (b.data.order ?? 0);
                            if (ao !== bo) return ao - bo;
                            return String(a.data.path).localeCompare(String(b.data.path));
                        });
                        nodes.forEach(n => sortRec(n.children));
                    };
                    sortRec(roots);
                    return roots;
                }

                function collectNavUpdatesFromUl(ul, parentPath, out) {
                    if (!ul) return;
                    const lis = Array.from(ul.children).filter(x => x.classList && x.classList.contains('nav-node'));
                    lis.forEach((li, idx) => {
                        const path = li.dataset.path;
                        if (!path || path === 'home') return;
                        const p = pagesCache.find(x => x.path === path) || { path };
                        const menuTitle = (p.menu_title || '').trim();
                        // 確保 parentPath 是字符串，空字符串表示頂層
                        const finalParent = (parentPath && parentPath !== 'home') ? String(parentPath) : '';
                        out.push({ path, parent: finalParent, order: idx, menu_title: menuTitle });
                        // 查找子 ul，使用更寬鬆的選擇器以確保找到所有子節點
                        const childUl = li.querySelector(':scope > ul.nav-tree') || li.querySelector('ul.nav-tree');
                        if (childUl) {
                            collectNavUpdatesFromUl(childUl, path, out);
                        }
                    });
                }

                async function syncNavFromDom() {
                    if (!navEditor) return;
                    const rootUl = navEditor.querySelector('ul.nav-tree');
                    if (!rootUl) return;
                    const updates = [];
                    collectNavUpdatesFromUl(rootUl, '', updates);
                    
                    // 調試：檢查更新數據
                    console.log('[GWA] Nav updates:', updates);
                    
                    // 記錄當前編輯路徑
                    const oldPath = currentEditingPath;
                    
                    try {
                        const result = await apiJson(`${API_URL}?action=nav_update`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                        body: JSON.stringify({ updates })
                    });
                        if (result.ok) {
                            showMsg('導航結構已更新', 'ok');
                            
                            // 檢查路徑變更
                            const pathChanges = result.pathChanges || {};
                            const newPath = pathChanges[oldPath];
                            
                            // 刷新列表與樹
                            await refreshListAndSelect(null);
                            
                            // 如果當前編輯頁面的路徑改變了，重新載入新路徑的頁面
                            if (newPath && newPath !== oldPath) {
                                console.log(`[GWA] Path changed: ${oldPath} -> ${newPath}`);
                                await loadPage(newPath);
                            } else if (oldPath && oldPath !== 'home') {
                                // 即使路徑未變，parent 可能改變，重新載入以更新表單顯示
                                const currentPage = pagesCache.find(p => p.path === oldPath);
                                if (currentPage) {
                                    await loadPage(oldPath);
                                }
                            }
                        }
                    } catch (e) {
                        console.error('[GWA] Nav update error:', e);
                        showMsg('更新導航結構時發生錯誤：' + (e.message || String(e)), 'err');
                        throw e;
                    }
                }

                function renderNavTree() {
                    if (!navEditor) return;
                    const nodes = buildTreeClient(pagesCache || []);
                    navEditor.innerHTML = '';

                    const mkUl = (nodes) => {
                        const ul = document.createElement('ul');
                        ul.className = 'nav-tree';
                        nodes.forEach(n => {
                            const li = document.createElement('li');
                            li.className = 'nav-node';
                            li.dataset.path = n.data.path;
                            li.innerHTML = `
                                <div class="nav-row ${n.data.path === selectedInTree ? 'active' : ''}">
                                    <span class="drag" title="拖放排序/父層">⋮⋮</span>
                                    <button type="button" class="nav-open">${escapeHtml(labelFor(n.data))}</button>
                                    <button type="button" class="nav-add-child" title="新增子頁">＋</button>
                                    <button type="button" class="nav-rename" title="改頁名">✎</button>
                                </div>
                                <div class="nav-rename-row" style="display:none;">
                                    <input class="nav-name" type="text" value="${escapeHtml(labelFor(n.data))}">
                                    <button type="button" class="btn btn-ok nav-name-save">套用</button>
                                </div>
                            `;

                            li.querySelector('.nav-open').onclick = () => {
                                loadPage(n.data.path).catch(e => showMsg(e.message || String(e), 'err'));
                            };

                            const addBtn = li.querySelector('.nav-add-child');
                            addBtn.onclick = async () => {
                                try {
                                    const r = await apiJson(`${API_URL}?action=create_child_page`, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                                        body: JSON.stringify({ parent: n.data.path })
                                    });
                                    showMsg('已新增子頁：' + r.path, 'ok');
                                    await refreshListAndSelect(r.path);
                                    // 聚焦頁名輸入，方便立刻改名
                                    setTimeout(() => { elMenuTitle && elMenuTitle.focus(); }, 50);
                                } catch (e) {
                                    showMsg(e.message || String(e), 'err');
                                }
                            };

                            const renameBtn = li.querySelector('.nav-rename');
                            const renameRow = li.querySelector('.nav-rename-row');
                            const nameInput = li.querySelector('.nav-name');
                            const saveBtn = li.querySelector('.nav-name-save');
                            renameBtn.onclick = () => {
                                renameRow.style.display = renameRow.style.display === 'none' ? 'flex' : 'none';
                                if (renameRow.style.display !== 'none') nameInput.focus();
                            };
                            saveBtn.onclick = () => {
                                const v = (nameInput.value || '').trim();
                                const p = pagesCache.find(x => x.path === n.data.path);
                                if (p) p.menu_title = v;
                                renameRow.style.display = 'none';
                                syncNavFromDom().catch(e => showMsg(e.message || String(e), 'err'));
                            };

                            ul.appendChild(li);

                            // children
                            const childUl = mkUl(n.children || []);
                            li.appendChild(childUl);
                        });

                        new Sortable(ul, {
                            group: 'nav',
                            animation: 150,
                            handle: '.drag',
                            draggable: '.nav-node',
                            filter: '[data-path="home"] .drag',
                            onEnd: () => { syncNavFromDom().catch(e => showMsg(e.message || String(e), 'err')); }
                        });

                        return ul;
                    };

                    navEditor.appendChild(mkUl(nodes));
                }

                // 正規化對齊格式：將 style="text-align: *" 轉換為 Quill 的 ql-align-* class
                // 同時確保已有的 ql-align-* class 被保留
                function normalizeAlignmentForQuill(html) {
                    if (!html) return html;
                    
                    // 創建臨時 DOM 來處理 HTML
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    // 處理所有包含 text-align 樣式的元素
                    const elements = tempDiv.querySelectorAll('[style*="text-align"]');
                    elements.forEach(el => {
                        const style = el.getAttribute('style') || '';
                        const alignMatch = style.match(/text-align\s*:\s*([^;]+)/i);
                        if (alignMatch) {
                            const alignValue = alignMatch[1].trim().toLowerCase();
                            let alignClass = '';
                            
                            if (alignValue === 'center' || alignValue === 'centre') {
                                alignClass = 'ql-align-center';
                            } else if (alignValue === 'right') {
                                alignClass = 'ql-align-right';
                            } else if (alignValue === 'justify') {
                                alignClass = 'ql-align-justify';
                            } else {
                                // left 或默認，Quill 不需要 class
                                alignClass = '';
                            }
                            
                            // 移除 text-align 樣式
                            const newStyle = style.replace(/text-align\s*:\s*[^;]+;?/gi, '').trim();
                            if (newStyle) {
                                el.setAttribute('style', newStyle);
                            } else {
                                el.removeAttribute('style');
                            }
                            
                            // 添加 Quill 對齊 class
                            if (alignClass) {
                                const currentClass = el.getAttribute('class') || '';
                                if (!currentClass.includes(alignClass)) {
                                    el.setAttribute('class', (currentClass + ' ' + alignClass).trim());
                                }
                            }
                        }
                    });
                    
                    // 確保已有的 ql-align-* class 被保留（移除可能存在的 text-align 樣式衝突）
                    const alignedElements = tempDiv.querySelectorAll('.ql-align-center, .ql-align-right, .ql-align-justify');
                    alignedElements.forEach(el => {
                        const style = el.getAttribute('style') || '';
                        if (style.includes('text-align')) {
                            // 移除 text-align 樣式，因為已經有 class
                            const newStyle = style.replace(/text-align\s*:\s*[^;]+;?/gi, '').trim();
                            if (newStyle) {
                                el.setAttribute('style', newStyle);
                            } else {
                                el.removeAttribute('style');
                            }
                        }
                    });
                    
                    return tempDiv.innerHTML;
                }
                
                // 標準化保存的 HTML：確保對齊格式一致
                function normalizeHtmlForSave(html) {
                    if (!html) return html;
                    
                    // 創建臨時 DOM 來處理 HTML
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    // 處理所有包含 ql-align-* class 的元素
                    // 確保它們沒有衝突的 text-align 樣式
                    const alignedElements = tempDiv.querySelectorAll('[class*="ql-align-"]');
                    alignedElements.forEach(el => {
                        const classAttr = el.getAttribute('class') || '';
                        const style = el.getAttribute('style') || '';
                        
                        // 檢查是否有 ql-align-* class
                        let alignClass = '';
                        if (classAttr.includes('ql-align-center')) {
                            alignClass = 'ql-align-center';
                        } else if (classAttr.includes('ql-align-right')) {
                            alignClass = 'ql-align-right';
                        } else if (classAttr.includes('ql-align-justify')) {
                            alignClass = 'ql-align-justify';
                        }
                        
                        if (alignClass && style.includes('text-align')) {
                            // 移除 text-align 樣式，因為已經有 class
                            const newStyle = style.replace(/text-align\s*:\s*[^;]+;?/gi, '').trim();
                            if (newStyle) {
                                el.setAttribute('style', newStyle);
                            } else {
                                el.removeAttribute('style');
                            }
                        }
                    });
                    
                    return tempDiv.innerHTML;
                }

                async function loadPage(path, options) {
                    path = path || 'home';
                    options = options || {};
                    // 僅在「切換到不同頁」時存草稿，避免首次打開同一頁時把空白存成草稿
                    if (!options.skipSaveDraft && currentEditingPath && path !== currentEditingPath && blockEditors.length > 0) {
                        await saveDraftToStorage(currentEditingPath);
                    }
                    // 清理舊頁面的區塊編輯器（保留主編輯器）
                    blockEditors.filter(b => !b.isMain).forEach(b => {
                        if (b.quill) b.quill = null;
                        if (b.element) b.element.remove();
                    });
                    blockEditors = blockEditors.filter(b => b.isMain);
                    if (sortableInstance) {
                        sortableInstance.destroy();
                        sortableInstance = null;
                    }
                    
                    const data = await apiJson(`${API_URL}?action=page&path=${encodeURIComponent(path)}`, { headers: { 'Accept': 'application/json' } });
                    const page = data.page || { path, title: '', menu_title: '', parent: '', type: 'page', price: 0 };
                    currentEditingPath = page.path || path || 'home';

                    elTitle.value = page.title || '';
                    elMenuTitle.value = page.menu_title || '';
                    
                    // 載入頁面類型和價格
                    const pageType = page.type || 'page';
                    if (elPageType) {
                        elPageType.value = pageType;
                        togglePriceField(pageType === 'product');
                    }
                    if (elPrice) {
                        elPrice.value = (pageType === 'product' && page.price !== undefined) ? page.price : 0;
                    }

                    // 載入布置設置
                    const layoutFullWidth = document.getElementById('layoutFullWidth');
                    const layoutBlockAlign = document.getElementById('layoutBlockAlign');
                    if (layoutFullWidth) {
                        layoutFullWidth.checked = page.layout_full_width || false;
                    }
                    if (layoutBlockAlign) {
                        layoutBlockAlign.value = page.layout_block_align || 'center';
                    }

                    if (currentEditingPath === 'home') {
                        elParent.value = '';
                        if (elParentDisplay) elParentDisplay.value = '';
                        elPath.value = getFullUrl('home');
                    } else {
                        // parent 若沒存，嘗試從 path 推導
                        let parentVal = page.parent || '';
                        const parentExplicit = !!page.parent_explicit;
                        if (!parentExplicit && !parentVal && String(currentEditingPath).includes('/')) {
                            parentVal = String(currentEditingPath).split('/').slice(0, -1).join('/');
                        }
                        elParent.value = parentVal;
                        if (elParentDisplay) elParentDisplay.value = parentVal;
                        updateAutoPath();
                    }

                    // 僅在有有效草稿時載入草稿，否則一律用伺服器內容
                    const hasDraft = await hasValidDraft(currentEditingPath);
                    const draft = hasDraft ? await loadDraftFromStorage(currentEditingPath) : null;
                    let html = data.html || '';
                    const blockDataMatch = !draft && html ? html.match(/<div class="gwa-block-editor-data"[^>]*>([\s\S]*?)<\/div>/) : null;

                    if (draft && draft.blocksJson) {
                        try {
                            loadBlockEditorHTML(draft.blocksJson);
                        } catch (e) {
                            console.warn('載入草稿失敗，改載伺服器內容', e);
                            if (blockDataMatch) {
                                let blockDataJson = blockDataMatch[1];
                                const tempDiv = document.createElement('div');
                                tempDiv.innerHTML = blockDataJson;
                                blockDataJson = tempDiv.textContent || tempDiv.innerText || blockDataJson;
                                loadBlockEditorHTML(blockDataJson);
                            } else {
                                html = normalizeAlignmentForQuill(html);
                                quill.setContents([], 'silent');
                                quill.clipboard.dangerouslyPasteHTML(0, html, 'silent');
                            }
                        }
                    } else if (blockDataMatch) {
                        // 區塊編輯器模式：載入區塊數據
                        try {
                            // 解碼 HTML 實體（完整解碼）
                            let blockDataJson = blockDataMatch[1];
                            // 創建臨時元素來解碼所有 HTML 實體
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = blockDataJson;
                            blockDataJson = tempDiv.textContent || tempDiv.innerText || blockDataJson;
                            loadBlockEditorHTML(blockDataJson);
                        } catch (e) {
                            console.error('載入區塊數據失敗:', e);
                            // 後備：使用主編輯器載入原始 HTML
                            html = html.replace(/<div class="gwa-block-editor-data"[^>]*>[\s\S]*?<\/div>/gi, '');
                            html = normalizeAlignmentForQuill(html);
                    quill.setContents([], 'silent');
                    quill.clipboard.dangerouslyPasteHTML(0, html, 'silent');
                        }
                    } else {
                        // 傳統單一編輯器模式：載入到主編輯器
                        html = normalizeAlignmentForQuill(html);
                        quill.setContents([], 'silent');
                        quill.clipboard.dangerouslyPasteHTML(0, html, 'silent');
                    }

                    await updateDraftUI();
                    selectedInTree = currentEditingPath;
                    renderNavTree();
                }

                async function refreshListAndSelect(pathToSelect) {
                    const data = await apiJson(`${API_URL}?action=pages`, { headers: { 'Accept': 'application/json' } });
                    pagesCache = (data.pages || []).map(p => ({
                        path: p.path || 'home',
                        title: p.title || '',
                        menu_title: p.menu_title || '',
                        parent: p.parent || '',
                        parent_explicit: !!p.parent_explicit,
                        order: (p.order ?? 0),
                    }));

                    // 若正在編輯某頁，更新父層顯示（避免拖放後被舊值覆蓋）
                    if (currentEditingPath && currentEditingPath !== 'home') {
                        const cur = pagesCache.find(x => x.path === currentEditingPath);
                        if (cur) {
                            elParent.value = cur.parent || '';
                            if (elParentDisplay) elParentDisplay.value = cur.parent || '';
                        }
                    }

                    renderNavTree();
                    if (pathToSelect) await loadPage(pathToSelect);
                }

                // 原子式更新：更新所有頁面中引用舊路徑的內聯頁面嵌入
                // 原子式更新所有引用：更新所有頁面中引用舊路徑的嵌入內容（內聯頁面、按鈕等）
                async function updateAllReferencesInAllPages(oldPath, newPath, newTitle) {
                    try {
                        const response = await fetch(`${API_URL}?action=pages`);
                        const data = await response.json();
                        if (!data.ok || !Array.isArray(data.pages)) return;
                        
                        const pagesToUpdate = [];
                        const escapedOldPath = oldPath.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                        
                        for (const page of data.pages) {
                            const contentResponse = await fetch(`${API_URL}?action=page&path=${encodeURIComponent(page.path)}`, {
                                headers: {
                                    'X-CSRF-Token': CSRF_TOKEN
                                }
                            });
                            const contentData = await contentResponse.json();
                            if (contentData.ok && contentData.html) {
                                let updatedHtml = contentData.html;
                                let hasChanges = false;
                                
                                // 檢查是否包含引用舊路徑的內容
                                if (contentData.html.includes(`data-page-path="${oldPath}"`)) {
                                    hasChanges = true;
                                    
                                    // 1. 更新內聯頁面嵌入（gwa-page-embed）
                                    updatedHtml = updatedHtml.replace(
                                        new RegExp(`(class="[^"]*gwa-page-embed[^"]*"[^>]*data-page-path=")${escapedOldPath}(")`, 'g'),
                                        `$1${newPath}$2`
                                    );
                                    
                                    // 更新內聯頁面嵌入的標題
                                    updatedHtml = updatedHtml.replace(
                                        new RegExp(`(class="[^"]*gwa-page-embed[^"]*"[^>]*data-page-path="${escapedOldPath}"[^>]*data-page-title=")[^"]*(")`, 'g'),
                                        `$1${newTitle}$2`
                                    );
                                    
                                    // 2. 更新按鈕嵌入（gwa-button-embed）
                                    updatedHtml = updatedHtml.replace(
                                        new RegExp(`(class="[^"]*gwa-button-embed[^"]*"[^>]*data-page-path=")${escapedOldPath}(")`, 'g'),
                                        `$1${newPath}$2`
                                    );
                                    
                                    // 更新按鈕內部的 data-page-path（在按鈕元素上）
                                    updatedHtml = updatedHtml.replace(
                                        new RegExp(`(class="[^"]*gwa-button[^"]*"[^>]*data-page-path=")${escapedOldPath}(")`, 'g'),
                                        `$1${newPath}$2`
                                    );
                                    
                                    // 3. 更新其他可能的引用（通用模式，處理所有 data-page-path）
                                    updatedHtml = updatedHtml.replace(
                                        new RegExp(`data-page-path="${escapedOldPath}"`, 'g'),
                                        `data-page-path="${newPath}"`
                                    );
                                }
                                
                                if (hasChanges) {
                                    pagesToUpdate.push({
                                        path: page.path,
                                        html: updatedHtml
                                    });
                                }
                            }
                        }
                        
                        // 批量更新所有受影響的頁面（原子性：要麼全部成功，要麼全部失敗）
                        const updatePromises = pagesToUpdate.map(async update => {
                            // 獲取完整的頁面元數據，確保包含所有必要字段
                            const pageData = pagesCache.find(p => p.path === update.path);
                            if (!pageData) {
                                console.warn(`[GWA] 找不到頁面元數據: ${update.path}`);
                                return;
                            }
                            
                            return apiJson(`${API_URL}?action=save_page`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                                body: JSON.stringify({
                                    page: {
                                        path: update.path,
                                        title: pageData.title || '',
                                        menu_title: pageData.menu_title || '',
                                        type: pageData.type || 'page',
                                        price: pageData.price || 0
                                    },
                                    html: update.html,
                                    oldPath: null  // 這些是內容更新，不是路徑更改
                                })
                            });
                        });
                        
                        // 等待所有更新完成
                        await Promise.all(updatePromises);
                    } catch (err) {
                        console.error('[GWA] 更新所有引用失敗:', err);
                        throw err; // 重新拋出錯誤，讓調用者知道更新失敗
                    }
                }
                
                // 保留舊函數名以向後兼容（已棄用，使用 updateAllReferencesInAllPages）
                const updatePageEmbedsInAllPages = updateAllReferencesInAllPages;

                async function savePage() {
                    const name = (elMenuTitle.value || '').trim();
                    let title = (elTitle.value || '').trim();
                    if (!title && name) {
                        title = name;
                        elTitle.value = title;
                    }
                    if (!name) {
                        showMsg('請先填寫頁名', 'err');
                        return;
                    }
                    if (!title) {
                        showMsg('請先填寫標題', 'err');
                        return;
                    }
                    const path = computePath();
                    if (!path) {
                        showMsg('無法產生網址（請更換頁名）', 'err');
                        return;
                    }
                    elPath.value = getFullUrl(path);
                    const page = {
                        path: path,
                        title: title,
                        menu_title: name,
                        type: (elPageType && elPageType.value) || 'page',
                        price: (elPageType && elPageType.value === 'product' && elPrice) ? parseFloat(elPrice.value || 0) : 0,
                        layout_full_width: document.getElementById('layoutFullWidth')?.checked || false,
                        layout_block_align: document.getElementById('layoutBlockAlign')?.value || 'center'
                    };
                    
                    // 原子式更新：如果路徑改變，更新所有引用該頁面的嵌入內容（內聯頁面、按鈕等）
                    const oldPath = currentEditingPath;
                    if (oldPath && oldPath !== path) {
                        // 更新當前編輯器中的內聯頁面嵌入
                        const pageEmbeds = quill.root.querySelectorAll('.gwa-page-embed[data-page-path="' + oldPath + '"]');
                        pageEmbeds.forEach(embed => {
                            embed.setAttribute('data-page-path', path);
                            embed.setAttribute('data-page-title', title);
                            // 更新標題欄
                            const titleBar = embed.querySelector('.gwa-page-title-bar');
                            if (titleBar) {
                                const titleText = titleBar.querySelector('span:nth-child(2)');
                                if (titleText) titleText.textContent = title;
                            }
                            // 重新載入預覽
                            const contentPreview = embed.querySelector('.gwa-page-content-preview');
                            if (contentPreview) {
                                contentPreview.innerHTML = '<div style="text-align: center; color: var(--muted, rgba(232,236,255,0.5)); padding: 40px 20px;">載入中...</div>';
                                loadPagePreview(path, contentPreview);
                            }
                        });
                        
                        // 更新當前編輯器中的按鈕嵌入
                        const buttonEmbeds = quill.root.querySelectorAll('.gwa-button-embed[data-page-path="' + oldPath + '"]');
                        buttonEmbeds.forEach(embed => {
                            embed.setAttribute('data-page-path', path);
                            // 更新按鈕內部的 data-page-path
                            const button = embed.querySelector('.gwa-button[data-page-path="' + oldPath + '"]');
                            if (button) {
                                button.setAttribute('data-page-path', path);
                            }
                        });
                        
                        // 更新所有其他頁面中引用該頁面的所有嵌入內容（原子性同步）
                        updateAllReferencesInAllPages(oldPath, path, title).catch(err => {
                            console.warn('[GWA] 更新所有引用失敗:', err);
                            showMsg('頁面已保存，但更新其他頁面中的引用時發生錯誤，請手動檢查', 'err');
                        });
                    }
                    
                    // 僅「新頁」帶 parent；編輯既有頁面不動父層（避免覆蓋拖放結果）
                    if (!currentEditingPath || currentEditingPath === '') {
                        page.parent = (elParent.value || '').trim();
                    }
                    
                    // 獲取 HTML（原子性操作：確保數據完整性）
                    let html = '';
                    try {
                        // 區塊編輯器模式：保存為 JSON 格式的 HTML
                        const blockData = getBlockEditorHTML();
                        if (blockData) {
                            // 修復：使用 textContent 安全地編碼 JSON，避免 HTML 實體問題
                            const tempDiv = document.createElement('div');
                            tempDiv.textContent = blockData;
                            const encodedData = tempDiv.innerHTML;
                            html = '<div class="gwa-block-editor-data" style="display:none;">' + encodedData + '</div>';
                        } else {
                            // 如果沒有區塊數據，使用主編輯器內容作為後備
                            html = quill.root.innerHTML;
                            html = html.replace(/<div[^>]*>雙擊編輯<\/div>/gi, '');
                            html = html.replace(/雙擊編輯/gi, '');
                            html = normalizeHtmlForSave(html);
                        }
                    } catch (e) {
                        console.error('保存區塊數據失敗:', e);
                        showMsg('保存失敗：數據處理錯誤', 'err');
                        return; // 原子性：失敗時不保存
                    }
                    
                    await apiJson(`${API_URL}?action=save_page`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                        body: JSON.stringify({ page, html, oldPath: (currentEditingPath || null) })
                    });
                    await removeDraft(path);
                    await updateDraftUI();
                    showMsg('已儲存', 'ok');
                    await refreshListAndSelect(path);
                }

                async function deletePage() {
                    if (currentEditingPath === 'home') {
                        showMsg('home 不允許刪除', 'err');
                        return;
                    }
                    if (!confirm('確定要刪除此頁面嗎？頁面將移至回收站，可稍後恢復。')) return;
                    await apiJson(`${API_URL}?action=delete_page`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                        body: JSON.stringify({ path: currentEditingPath })
                    });
                    showMsg('已移至回收站', 'ok');
                    await refreshListAndSelect('home');
                }
                
                async function loadTrash() {
                    const trashList = document.getElementById('trashList');
                    if (!trashList) return;
                    
                    try {
                        const data = await apiJson(`${API_URL}?action=trash_list`, {
                            headers: { 'Accept': 'application/json', 'X-CSRF-Token': CSRF_TOKEN }
                        });
                        
                        if (!data.ok || !Array.isArray(data.trash)) {
                            trashList.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--muted);">無法載入回收站</div>';
                            return;
                        }
                        
                        if (data.trash.length === 0) {
                            trashList.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--muted);">回收站是空的</div>';
                            return;
                        }
                        
                        trashList.innerHTML = data.trash.map(item => {
                            const page = item.page || {};
                            const deletedAt = new Date((item.deleted_at || 0) * 1000).toLocaleString('zh-TW');
                            return `
                                <div style="border: 1px solid var(--border); border-radius: 8px; padding: 16px; background: rgba(255,255,255,0.04);">
                                    <div style="display: flex; justify-content: space-between; align-items: start; gap: 12px;">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 500; margin-bottom: 4px;">${escapeHtml(page.title || item.original_path || '未命名')}</div>
                                            <div style="font-size: 12px; color: var(--muted); margin-bottom: 8px;">原始路徑: ${escapeHtml(item.original_path || '')}</div>
                                            <div style="font-size: 12px; color: var(--muted);">刪除時間: ${deletedAt}</div>
                                        </div>
                                        <button class="btn btn-ok" onclick="restorePage('${escapeHtml(item.trash_path)}')" style="white-space: nowrap;">恢復</button>
                                    </div>
                                </div>
                            `;
                        }).join('');
                    } catch (e) {
                        console.error('載入回收站失敗:', e);
                        trashList.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--muted);">載入失敗</div>';
                    }
                }
                
                async function restorePage(trashPath) {
                    if (!confirm('確定要恢復此頁面嗎？')) return;
                    try {
                        await apiJson(`${API_URL}?action=restore_page`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                            body: JSON.stringify({ trash_path: trashPath })
                        });
                        showMsg('已恢復', 'ok');
                        await loadTrash();
                        await refreshListAndSelect(null);
                    } catch (e) {
                        showMsg('恢復失敗：' + (e.message || String(e)), 'err');
                    }
                }

                // YouTube URL 解析函數
                function extractYouTubeId(url) {
                    if (!url || typeof url !== 'string') return null;
                    
                    // 多種 YouTube URL 格式支持
                    const patterns = [
                        // 標準格式：https://www.youtube.com/watch?v=VIDEO_ID
                        /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/,
                        // 帶時間戳：https://www.youtube.com/watch?v=VIDEO_ID&t=123s
                        /youtube\.com\/.*[?&]v=([a-zA-Z0-9_-]{11})/,
                        // 短鏈接：https://youtu.be/VIDEO_ID
                        /youtu\.be\/([a-zA-Z0-9_-]{11})/,
                        // 嵌入格式：https://www.youtube.com/embed/VIDEO_ID
                        /youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/,
                        // 移動端：https://m.youtube.com/watch?v=VIDEO_ID
                        /m\.youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/,
                    ];
                    
                    for (const pattern of patterns) {
                        const match = url.match(pattern);
                        if (match && match[1]) return match[1];
                    }
                    
                    // 如果直接是 11 字元的 video ID
                    const clean = url.trim();
                    if (/^[a-zA-Z0-9_-]{11}$/.test(clean)) return clean;
                    
                    return null;
                }

                // 提取時間戳（秒數）
                function extractStartTime(url) {
                    if (!url || typeof url !== 'string') return '0';
                    const match = url.match(/[?&]t=(\d+)/);
                    if (match && match[1]) {
                        // 如果是 "123s" 格式，提取數字
                        const seconds = parseInt(match[1].replace('s', ''));
                        return seconds ? seconds.toString() : '0';
                    }
                    return '0';
                }

                // YouTube 插入對話框
                function showYouTubeInsertDialog(targetQuill = null) {
                    const targetEditor = targetQuill || quill;
                    if (!targetEditor) return;
                    
                    const modal = document.createElement('div');
                    modal.className = 'modal-overlay';
                    modal.style.cssText = 'position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center;';
                    
                    const content = document.createElement('div');
                    content.style.cssText = 'background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; max-width: 600px; width: 90%; box-shadow: 0 8px 32px rgba(0,0,0,0.3);';
                    
                    const title = document.createElement('h3');
                    title.textContent = '插入 YouTube 影片';
                    title.style.cssText = 'margin: 0 0 20px 0; color: var(--text); display: flex; align-items: center; gap: 8px;';
                    const titleIcon = document.createElement('span');
                    titleIcon.innerHTML = '🎬';
                    titleIcon.style.cssText = 'font-size: 24px;';
                    title.insertBefore(titleIcon, title.firstChild);
                    
                    // URL 輸入框
                    const urlLabel = document.createElement('label');
                    urlLabel.textContent = 'YouTube 網址或影片 ID';
                    urlLabel.style.cssText = 'display: block; margin-bottom: 8px; color: var(--text); font-weight: 500;';
                    const urlInput = document.createElement('input');
                    urlInput.type = 'text';
                    urlInput.placeholder = '例如：https://www.youtube.com/watch?v=dQw4w9WgXcQ 或 dQw4w9WgXcQ';
                    urlInput.style.cssText = 'width: 100%; padding: 12px; margin-bottom: 16px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg2); color: var(--text); font-size: 14px;';
                    
                    // 進階選項
                    const advancedToggle = document.createElement('button');
                    advancedToggle.type = 'button';
                    advancedToggle.textContent = '進階選項 ▼';
                    advancedToggle.className = 'btn';
                    advancedToggle.style.cssText = 'width: 100%; margin-bottom: 12px; text-align: left;';
                    
                    const advancedPanel = document.createElement('div');
                    advancedPanel.id = 'youtubeAdvanced';
                    advancedPanel.style.cssText = 'display: none; margin-bottom: 16px; padding: 16px; background: var(--bg2); border-radius: 8px; border: 1px solid var(--border);';
                    
                    // 自動播放選項
                    const autoplayLabel = document.createElement('label');
                    autoplayLabel.style.cssText = 'display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;';
                    const autoplayCheck = document.createElement('input');
                    autoplayCheck.type = 'checkbox';
                    autoplayCheck.id = 'youtubeAutoplay';
                    autoplayLabel.appendChild(autoplayCheck);
                    autoplayLabel.appendChild(document.createTextNode('自動播放'));
                    
                    // 開始時間
                    const startTimeLabel = document.createElement('label');
                    startTimeLabel.textContent = '開始時間（秒）';
                    startTimeLabel.style.cssText = 'display: block; margin-bottom: 8px; color: var(--text);';
                    const startTimeInput = document.createElement('input');
                    startTimeInput.type = 'number';
                    startTimeInput.min = '0';
                    startTimeInput.value = '0';
                    startTimeInput.id = 'youtubeStartTime';
                    startTimeInput.style.cssText = 'width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg); color: var(--text);';
                    
                    advancedPanel.appendChild(autoplayLabel);
                    advancedPanel.appendChild(startTimeLabel);
                    advancedPanel.appendChild(startTimeInput);
                    
                    advancedToggle.onclick = () => {
                        const isVisible = advancedPanel.style.display !== 'none';
                        advancedPanel.style.display = isVisible ? 'none' : 'block';
                        advancedToggle.textContent = isVisible ? '進階選項 ▼' : '進階選項 ▲';
                    };
                    
                    // 預覽區域
                    const previewLabel = document.createElement('label');
                    previewLabel.textContent = '預覽';
                    previewLabel.style.cssText = 'display: block; margin-bottom: 8px; color: var(--text); font-weight: 500;';
                    const previewContainer = document.createElement('div');
                    previewContainer.id = 'youtubePreview';
                    previewContainer.style.cssText = 'width: 100%; padding-bottom: 56.25%; position: relative; background: #000; border-radius: 8px; margin-bottom: 16px; overflow: hidden; display: none;';
                    
                    // 實時預覽
                    urlInput.addEventListener('input', () => {
                        const url = urlInput.value.trim();
                        const videoId = extractYouTubeId(url);
                        if (videoId) {
                            previewContainer.style.display = 'block';
                            previewContainer.innerHTML = `
                                <iframe 
                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;"
                                    src="https://www.youtube.com/embed/${videoId}" 
                                    frameborder="0" 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                    allowfullscreen>
                                </iframe>
                            `;
                            
                            // 自動提取開始時間
                            const startTime = extractStartTime(url);
                            if (startTime !== '0') {
                                startTimeInput.value = startTime;
                            }
                        } else {
                            previewContainer.style.display = 'none';
                        }
                    });
                    
                    // 按鈕組
                    const btnGroup = document.createElement('div');
                    btnGroup.style.cssText = 'display: flex; gap: 12px;';
                    
                    const btnInsert = document.createElement('button');
                    btnInsert.textContent = '插入';
                    btnInsert.className = 'btn btn-ok';
                    btnInsert.style.cssText = 'flex: 1;';
                    btnInsert.onclick = () => {
                        const url = urlInput.value.trim();
                        if (!url) {
                            showMsg('請輸入 YouTube 網址或影片 ID', 'err');
                            return;
                        }
                        
                        const videoId = extractYouTubeId(url);
                        if (!videoId) {
                            showMsg('無法解析 YouTube 網址，請確認格式正確', 'err');
                            return;
                        }
                        
                        const r = targetEditor.getSelection(true);
                        const index = r ? r.index : targetEditor.getLength();
                        
                        const autoplay = autoplayCheck.checked;
                        const startTime = startTimeInput.value || '0';
                        
                        targetEditor.insertEmbed(index, 'youtube', {
                            videoId: videoId,
                            autoplay: autoplay,
                            startTime: startTime
                        }, 'user');
                        targetEditor.insertText(index + 1, '\n', 'user');
                        targetEditor.setSelection(index + 2, 0, 'silent');
                        
                        // 標記為已修改（如果相關函數存在）
                        if (typeof markDirty === 'function') markDirty();
                        if (typeof scheduleDraftSave === 'function') scheduleDraftSave();
                        if (typeof updateDocStats === 'function') updateDocStats();
                        
                        modal.remove();
                    };
                    
                    const btnCancel = document.createElement('button');
                    btnCancel.textContent = '取消';
                    btnCancel.className = 'btn';
                    btnCancel.style.cssText = 'flex: 1;';
                    btnCancel.onclick = () => modal.remove();
                    
                    btnGroup.appendChild(btnInsert);
                    btnGroup.appendChild(btnCancel);
                    
                    content.appendChild(title);
                    content.appendChild(urlLabel);
                    content.appendChild(urlInput);
                    content.appendChild(advancedToggle);
                    content.appendChild(advancedPanel);
                    content.appendChild(previewLabel);
                    content.appendChild(previewContainer);
                    content.appendChild(btnGroup);
                    modal.appendChild(content);
                    document.body.appendChild(modal);
                    
                    // 自動聚焦輸入框
                    setTimeout(() => urlInput.focus(), 100);
                    
                    // 點擊背景關閉
                    modal.onclick = (e) => {
                        if (e.target === modal) modal.remove();
                    };
                    
                    // ESC 關閉
                    const escHandler = (e) => {
                        if (e.key === 'Escape') {
                            modal.remove();
                            document.removeEventListener('keydown', escHandler);
                        }
                    };
                    document.addEventListener('keydown', escHandler);
                }

                // 地圖插入對話框（支持編輯模式）
                function showMapInsertDialog(targetQuill = null, editData = null) {
                    const targetEditor = targetQuill || quill;
                    if (!targetEditor) return;
                    
                    const isEditMode = editData !== null;
                    const modal = document.createElement('div');
                    modal.className = 'modal-overlay';
                    modal.style.cssText = 'position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center;';
                    
                    const content = document.createElement('div');
                    content.style.cssText = 'background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; max-width: 900px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 8px 32px rgba(0,0,0,0.3);';
                    
                    const title = document.createElement('h3');
                    title.textContent = isEditMode ? '編輯地圖' : '插入地圖';
                    title.style.cssText = 'margin: 0 0 20px 0; color: var(--text); display: flex; align-items: center; gap: 8px;';
                    const titleIcon = document.createElement('span');
                    titleIcon.innerHTML = '🗺️';
                    titleIcon.style.cssText = 'font-size: 24px;';
                    title.insertBefore(titleIcon, title.firstChild);
                    
                    // 地標列表容器
                    const landmarksLabel = document.createElement('label');
                    landmarksLabel.textContent = '地標列表';
                    landmarksLabel.style.cssText = 'display: block; margin-bottom: 12px; color: var(--text); font-weight: 500;';
                    
                    const landmarksContainer = document.createElement('div');
                    landmarksContainer.id = 'landmarksContainer';
                    landmarksContainer.style.cssText = 'margin-bottom: 16px;';
                    
                    // 地標數據（如果是編輯模式，載入現有數據）
                    let landmarks = [];
                    let currentMapNode = null;
                    let currentMapIndex = null;
                    
                    if (isEditMode && editData) {
                        landmarks = Array.isArray(editData.landmarks) ? editData.landmarks.map(l => ({
                            address: l.address || '',
                            description: l.description || ''
                        })) : [];
                        if (editData.node) currentMapNode = editData.node;
                        if (editData.index !== undefined) currentMapIndex = editData.index;
                    }
                    
                    // 創建地標項目的函數
                    function createLandmarkItem(index, address = '', description = '') {
                        const item = document.createElement('div');
                        item.className = 'landmark-item';
                        item.style.cssText = 'display: flex; gap: 12px; margin-bottom: 12px; padding: 12px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; align-items: flex-start;';
                        
                        const number = document.createElement('div');
                        number.textContent = index + 1;
                        number.style.cssText = 'min-width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; background: var(--accent); color: white; border-radius: 50%; font-weight: bold; font-size: 14px; flex-shrink: 0;';
                        
                        const inputs = document.createElement('div');
                        inputs.style.cssText = 'flex: 1; display: flex; flex-direction: column; gap: 8px;';
                        
                        const addressInput = document.createElement('input');
                        addressInput.type = 'text';
                        addressInput.placeholder = '地址（例如：香港市情義區信心路7號）';
                        addressInput.value = address;
                        addressInput.style.cssText = 'width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg); color: var(--text); font-size: 14px;';
                        
                        const descInput = document.createElement('input');
                        descInput.type = 'text';
                        descInput.placeholder = '說明文字（可選）';
                        descInput.value = description;
                        descInput.style.cssText = 'width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg); color: var(--text); font-size: 14px;';
                        
                        const deleteBtn = document.createElement('button');
                        deleteBtn.textContent = '刪除';
                        deleteBtn.className = 'btn';
                        deleteBtn.style.cssText = 'padding: 6px 12px; font-size: 12px; flex-shrink: 0;';
                        deleteBtn.onclick = () => {
                            item.remove();
                            landmarks.splice(index, 1);
                            updateLandmarkNumbers();
                            scheduleMapUpdate();
                        };
                        
                        // 監聽輸入變化，觸發定時更新
                        let inputTimeout = null;
                        const scheduleInputUpdate = () => {
                            clearTimeout(inputTimeout);
                            inputTimeout = setTimeout(() => {
                                landmarks[index] = {
                                    address: addressInput.value.trim(),
                                    description: descInput.value.trim()
                                };
                                scheduleMapUpdate();
                            }, 1000);
                        };
                        
                        addressInput.addEventListener('input', scheduleInputUpdate);
                        descInput.addEventListener('input', scheduleInputUpdate);
                        
                        inputs.appendChild(addressInput);
                        inputs.appendChild(descInput);
                        item.appendChild(number);
                        item.appendChild(inputs);
                        item.appendChild(deleteBtn);
                        
                        landmarks[index] = { address: address, description: description };
                        
                        return item;
                    }
                    
                    // 更新地標編號
                    function updateLandmarkNumbers() {
                        const items = landmarksContainer.querySelectorAll('.landmark-item');
                        items.forEach((item, idx) => {
                            const number = item.querySelector('div:first-child');
                            if (number) number.textContent = idx + 1;
                        });
                    }
                    
                    // 添加地標按鈕
                    const addLandmarkBtn = document.createElement('button');
                    addLandmarkBtn.textContent = '+ 添加地標';
                    addLandmarkBtn.className = 'btn';
                    addLandmarkBtn.style.cssText = 'width: 100%; margin-bottom: 16px;';
                    addLandmarkBtn.onclick = () => {
                        const item = createLandmarkItem(landmarks.length);
                        landmarksContainer.appendChild(item);
                        updateLandmarkNumbers();
                    };
                    
                    // 地圖預覽容器
                    const mapPreviewLabel = document.createElement('label');
                    mapPreviewLabel.textContent = '地圖預覽';
                    mapPreviewLabel.style.cssText = 'display: block; margin-bottom: 8px; color: var(--text); font-weight: 500;';
                    const mapPreviewContainer = document.createElement('div');
                    mapPreviewContainer.id = 'mapPreviewContainer';
                    mapPreviewContainer.style.cssText = 'width: 100%; height: 400px; margin-bottom: 16px; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: #f0f0f0; position: relative;';
                    
                    let mapInstance = null;
                    let markers = [];
                    let updateTimer = null;
                    
                    // 地址解析和地圖更新函數
                    function updateMapPreview() {
                        // 收集有效的地標
                        const validLandmarks = landmarks.filter(l => l.address && l.address.trim());
                        
                        if (validLandmarks.length === 0) {
                            if (mapInstance) {
                                mapInstance.remove();
                                mapInstance = null;
                            }
                            mapPreviewContainer.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #999;">請添加地標以顯示地圖</div>';
                            return;
                        }
                        
                        // 清除舊地圖
                        if (mapInstance) {
                            mapInstance.remove();
                            markers.forEach(m => m.remove());
                            markers = [];
                        }
                        
                        mapPreviewContainer.innerHTML = '';
                        
                        // 創建新地圖（使用 Google Maps 風格的 OSM 圖層）
                        mapInstance = L.map(mapPreviewContainer, {
                            zoomControl: true,
                            attributionControl: true
                        });
                        
                        // 根據樣式選擇圖層
                        const mapStyle = (typeof styleSelect !== 'undefined' && styleSelect) ? styleSelect.value : 'light';
                        let tileUrl = '';
                        let attribution = '';
                        
                        switch (mapStyle) {
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
                        
                        const mapStyleValue = (typeof styleSelect !== 'undefined' && styleSelect) ? styleSelect.value : 'light';
                        L.tileLayer(tileUrl, {
                            attribution: attribution,
                            subdomains: mapStyleValue === 'satellite' ? undefined : 'abcd',
                            maxZoom: 19
                        }).addTo(mapInstance);
                        
                        // 使用自定義 Geocoder
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
                        
                        validLandmarks.forEach((landmark, index) => {
                            // 每個請求間隔 1 秒，避免觸發速率限制
                            geocodeAddress(landmark.address, (results) => {
                                if (results && results.length > 0) {
                                    const result = results[0];
                                    const latlng = result.center;
                                    
                                    // 構建彈窗內容
                                    let popupContent = `<strong>地標 ${index + 1}</strong><br>${landmark.address}`;
                                    if (landmark.description && landmark.description.trim()) {
                                        popupContent += `<br><small style="color: #666;">${landmark.description}</small>`;
                                    }
                                    
                                    // 添加標記
                                    const marker = L.marker(latlng, {
                                        icon: L.divIcon({
                                            className: 'custom-marker',
                                            html: `<div style="background: #4285f4; color: white; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);">${index + 1}</div>`,
                                            iconSize: [32, 32],
                                            iconAnchor: [16, 16]
                                        })
                                    }).addTo(mapInstance);
                                    
                                    marker.bindPopup(popupContent);
                                    markers.push(marker);
                                    bounds.extend(latlng);
                                    
                                    geocodedCount++;
                                    if (geocodedCount === validLandmarks.length) {
                                        // 所有地址解析完成，智能縮放
                                        if (bounds.isValid()) {
                                            mapInstance.fitBounds(bounds, {
                                                padding: [50, 50],
                                                maxZoom: 16
                                            });
                                        }
                                    }
                                }
                            }, index * 1000); // 每個請求間隔 1 秒
                        });
                        
                        // 如果沒有結果，設置默認視圖（台北）
                        setTimeout(() => {
                            if (markers.length === 0 && validLandmarks.length > 0) {
                                mapInstance.setView([25.0330, 121.5654], 13);
                            }
                        }, 2000);
                    }
                    
                    // 定時更新地圖（每2秒檢查一次）
                    function scheduleMapUpdate() {
                        clearTimeout(updateTimer);
                        updateTimer = setTimeout(updateMapPreview, 2000);
                    }
                    
                    // 初始化地標列表（如果是編輯模式）
                    if (isEditMode && landmarks.length > 0) {
                        landmarks.forEach((landmark, idx) => {
                            const item = createLandmarkItem(idx, landmark.address, landmark.description);
                            landmarksContainer.appendChild(item);
                        });
                        updateLandmarkNumbers();
                        scheduleMapUpdate();
                    }
                    
                    // 進階選項
                    const advancedToggle = document.createElement('button');
                    advancedToggle.type = 'button';
                    advancedToggle.textContent = '進階選項 ▼';
                    advancedToggle.className = 'btn';
                    advancedToggle.style.cssText = 'width: 100%; margin-bottom: 12px; text-align: left;';
                    
                    const advancedPanel = document.createElement('div');
                    advancedPanel.id = 'mapAdvanced';
                    advancedPanel.style.cssText = 'display: none; margin-bottom: 16px; padding: 16px; background: var(--bg2); border-radius: 8px; border: 1px solid var(--border);';
                    
                    // 地圖高度設置
                    const heightLabel = document.createElement('label');
                    heightLabel.textContent = '地圖高度（像素）';
                    heightLabel.style.cssText = 'display: block; margin-bottom: 8px; color: var(--text); font-weight: 500;';
                    const heightInput = document.createElement('input');
                    heightInput.type = 'number';
                    heightInput.min = '200';
                    heightInput.max = '800';
                    heightInput.value = isEditMode && editData ? (editData.height || 400) : 400;
                    heightInput.style.cssText = 'width: 100%; padding: 8px; margin-bottom: 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg); color: var(--text);';
                    
                    // 初始縮放級別
                    const zoomLabel = document.createElement('label');
                    zoomLabel.textContent = '初始縮放級別（1-19）';
                    zoomLabel.style.cssText = 'display: block; margin-bottom: 8px; color: var(--text); font-weight: 500;';
                    const zoomInput = document.createElement('input');
                    zoomInput.type = 'number';
                    zoomInput.min = '1';
                    zoomInput.max = '19';
                    zoomInput.value = isEditMode && editData ? (editData.zoom || 13) : 13;
                    zoomInput.style.cssText = 'width: 100%; padding: 8px; margin-bottom: 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg); color: var(--text);';
                    
                    // 地圖樣式選擇
                    const styleLabel = document.createElement('label');
                    styleLabel.textContent = '地圖樣式';
                    styleLabel.style.cssText = 'display: block; margin-bottom: 8px; color: var(--text); font-weight: 500;';
                    const styleSelect = document.createElement('select');
                    styleSelect.id = 'mapStyle';
                    styleSelect.style.cssText = 'width: 100%; padding: 8px; margin-bottom: 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg); color: var(--text);';
                    
                    const styles = [
                        { value: 'light', label: '淺色（Google Maps 風格）' },
                        { value: 'dark', label: '深色' },
                        { value: 'satellite', label: '衛星圖' }
                    ];
                    styles.forEach(style => {
                        const option = document.createElement('option');
                        option.value = style.value;
                        option.textContent = style.label;
                        if (style.value === (isEditMode && editData ? (editData.style || 'light') : 'light')) {
                            option.selected = true;
                        }
                        styleSelect.appendChild(option);
                    });
                    
                    advancedPanel.appendChild(heightLabel);
                    advancedPanel.appendChild(heightInput);
                    advancedPanel.appendChild(zoomLabel);
                    advancedPanel.appendChild(zoomInput);
                    advancedPanel.appendChild(styleLabel);
                    advancedPanel.appendChild(styleSelect);
                    
                    advancedToggle.onclick = () => {
                        const isVisible = advancedPanel.style.display !== 'none';
                        advancedPanel.style.display = isVisible ? 'none' : 'block';
                        advancedToggle.textContent = isVisible ? '進階選項 ▼' : '進階選項 ▲';
                    };
                    
                    // 按鈕組
                    const btnGroup = document.createElement('div');
                    btnGroup.style.cssText = 'display: flex; gap: 12px;';
                    
                    const btnInsert = document.createElement('button');
                    btnInsert.textContent = '插入';
                    btnInsert.className = 'btn btn-ok';
                    btnInsert.style.cssText = 'flex: 1;';
                    btnInsert.onclick = () => {
                        const validLandmarks = landmarks.filter(l => l.address && l.address.trim());
                        if (validLandmarks.length === 0) {
                            showMsg('請至少添加一個有效地址', 'err');
                            return;
                        }
                        
                        const height = parseInt(heightInput.value, 10) || 400;
                        const zoom = parseInt(zoomInput.value, 10) || 13;
                        const style = styleSelect.value || 'light';
                        
                        if (isEditMode && currentMapNode && currentMapIndex !== null) {
                            // 編輯模式：更新現有地圖節點屬性
                            currentMapNode.setAttribute('data-landmarks', JSON.stringify(validLandmarks));
                            currentMapNode.setAttribute('data-zoom', zoom);
                            currentMapNode.setAttribute('data-height', height);
                            currentMapNode.setAttribute('data-style', style);
                            
                            // 更新占位符顯示（不包含「雙擊編輯」，避免被保存到 HTML）
                            const placeholder = currentMapNode.querySelector('.gwa-map-container > div');
                            if (placeholder) {
                                placeholder.innerHTML = `
                                    <div style="margin-bottom: 8px;">🗺️ 地圖：${validLandmarks.length} 個標記點</div>
                                `;
                                // 動態添加「雙擊編輯」提示（僅在編輯器中，不保存到 HTML）
                                const isInEditor = typeof targetEditor !== 'undefined' && targetEditor && targetEditor.root && targetEditor.root.contains && targetEditor.root.contains(currentMapNode);
                                if (isInEditor) {
                                    const editHint = document.createElement('div');
                                    editHint.style.cssText = 'font-size: 12px; color: #999;';
                                    editHint.textContent = '雙擊編輯';
                                    placeholder.appendChild(editHint);
                                }
                            }
                            
                            // 更新容器高度
                            const mapContainer = currentMapNode.querySelector('.gwa-map-container');
                            if (mapContainer) {
                                mapContainer.style.height = height + 'px';
                            }
                            
                            if (typeof markDirty === 'function') markDirty();
                            if (typeof scheduleDraftSave === 'function') scheduleDraftSave();
                            if (typeof updateDocStats === 'function') updateDocStats();
                        } else {
                            // 插入模式：插入新地圖
                            const r = targetEditor.getSelection(true);
                            const index = r ? r.index : targetEditor.getLength();
                            
                            targetEditor.insertEmbed(index, 'map', {
                                landmarks: validLandmarks,
                                zoom: zoom,
                                height: height,
                                style: style
                            }, 'user');
                            targetEditor.insertText(index + 1, '\n', 'user');
                            targetEditor.setSelection(index + 2, 0, 'silent');
                            
                            if (typeof markDirty === 'function') markDirty();
                            if (typeof scheduleDraftSave === 'function') scheduleDraftSave();
                            if (typeof updateDocStats === 'function') updateDocStats();
                        }
                        
                        if (mapInstance) {
                            mapInstance.remove();
                            mapInstance = null;
                        }
                        modal.remove();
                    };
                    
                    btnInsert.textContent = isEditMode ? '更新' : '插入';
                    
                    const btnCancel = document.createElement('button');
                    btnCancel.textContent = '取消';
                    btnCancel.className = 'btn';
                    btnCancel.style.cssText = 'flex: 1;';
                    btnCancel.onclick = () => {
                        if (mapInstance) {
                            mapInstance.remove();
                            mapInstance = null;
                        }
                        clearTimeout(updateTimer);
                        modal.remove();
                    };
                    
                    btnGroup.appendChild(btnInsert);
                    btnGroup.appendChild(btnCancel);
                    
                    content.appendChild(title);
                    content.appendChild(landmarksLabel);
                    content.appendChild(landmarksContainer);
                    content.appendChild(addLandmarkBtn);
                    content.appendChild(advancedToggle);
                    content.appendChild(advancedPanel);
                    content.appendChild(mapPreviewLabel);
                    content.appendChild(mapPreviewContainer);
                    content.appendChild(btnGroup);
                    
                    modal.appendChild(content);
                    document.body.appendChild(modal);
                    
                    // 點擊背景關閉
                    modal.onclick = (e) => {
                        if (e.target === modal) {
                            if (mapInstance) {
                                mapInstance.remove();
                                mapInstance = null;
                            }
                            clearTimeout(updateTimer);
                            modal.remove();
                        }
                    };
                }

                // 按鈕插入對話框
                function showButtonInsertDialog(targetQuill = null, editData = null) {
                    const targetEditor = targetQuill || quill;
                    if (!targetEditor) return;
                    
                    const isEditMode = editData !== null;
                    const modal = document.createElement('div');
                    modal.className = 'modal-overlay';
                    modal.style.cssText = 'position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center;';
                    
                    const content = document.createElement('div');
                    content.style.cssText = 'background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; max-width: 800px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 8px 32px rgba(0,0,0,0.3);';
                    
                    const title = document.createElement('h3');
                    title.textContent = isEditMode ? '編輯內聯頁面' : '插入內聯頁面';
                    title.style.cssText = 'margin: 0 0 20px 0; color: var(--text); display: flex; align-items: center; gap: 8px;';
                    const titleIcon = document.createElement('span');
                    titleIcon.innerHTML = '📄';
                    titleIcon.style.cssText = 'font-size: 24px;';
                    title.insertBefore(titleIcon, title.firstChild);
                    
                    // 按鈕文字輸入
                    const textLabel = document.createElement('label');
                    textLabel.textContent = '按鈕文字';
                    textLabel.style.cssText = 'display: block; margin-bottom: 8px; color: var(--text); font-weight: 500;';
                    const textInput = document.createElement('input');
                    textInput.type = 'text';
                    textInput.placeholder = '例如：立即查看';
                    textInput.value = isEditMode && editData ? (editData.text || '按鈕') : '按鈕';
                    textInput.style.cssText = 'width: 100%; padding: 12px; margin-bottom: 16px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg2); color: var(--text); font-size: 14px;';
                    
                    // 搜尋框
                    const searchLabel = document.createElement('label');
                    searchLabel.textContent = '跳轉頁面（選填）';
                    searchLabel.style.cssText = 'display: block; margin-bottom: 8px; color: var(--text); font-weight: 500;';
                    const searchInput = document.createElement('input');
                    searchInput.type = 'text';
                    searchInput.placeholder = '輸入頁面標題或路徑進行搜尋...';
                    searchInput.style.cssText = 'width: 100%; padding: 12px; margin-bottom: 16px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg2); color: var(--text); font-size: 14px;';
                    
                    // 頁面列表容器
                    const pagesList = document.createElement('div');
                    pagesList.id = 'pagesList';
                    pagesList.style.cssText = 'max-height: 300px; overflow-y: auto; margin-bottom: 16px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg2); display: none;';
                    
                    let allPages = [];
                    let selectedPage = null;
                    let currentButtonNode = null;
                    let currentButtonIndex = null;
                    
                    if (isEditMode && editData) {
                        selectedPage = { path: editData.path || '', title: '' };
                        if (editData.node) currentButtonNode = editData.node;
                        if (editData.index !== undefined) currentButtonIndex = editData.index;
                    }
                    
                    // 載入頁面列表
                    async function loadPages() {
                        try {
                            const response = await fetch(`${API_URL}?action=pages`);
                            const data = await response.json();
                            if (data.ok && Array.isArray(data.pages)) {
                                allPages = data.pages;
                                renderPagesList();
                            } else {
                                pagesList.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--muted);">無法載入頁面列表</div>';
                            }
                        } catch (err) {
                            console.error('[GWA] 載入頁面列表失敗:', err);
                            pagesList.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--muted);">載入失敗</div>';
                        }
                    }
                    
                    // 渲染頁面列表
                    function renderPagesList(filter = '') {
                        const filterLower = filter.toLowerCase();
                        const filteredPages = allPages.filter(page => {
                            const title = (page.title || '').toLowerCase();
                            const menuTitle = (page.menu_title || '').toLowerCase();
                            const path = (page.path || '').toLowerCase();
                            return title.includes(filterLower) || menuTitle.includes(filterLower) || path.includes(filterLower);
                        });
                        
                        if (filteredPages.length === 0) {
                            pagesList.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--muted);">沒有找到匹配的頁面</div>';
                            return;
                        }
                        
                        pagesList.innerHTML = '';
                        filteredPages.forEach(page => {
                            const pageItem = document.createElement('div');
                            pageItem.className = 'page-item';
                            const isSelected = selectedPage && selectedPage.path === page.path;
                            pageItem.style.cssText = `padding: 14px 16px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.2s; ${isSelected ? 'background: var(--accent, rgba(124,92,255,0.2)); border-left: 3px solid var(--accent, rgba(124,92,255,0.8));' : 'background: transparent;'}`;
                            
                            const pageTitle = document.createElement('div');
                            pageTitle.textContent = page.menu_title || page.title || page.path;
                            pageTitle.style.cssText = 'font-weight: 600; color: var(--text); margin-bottom: 4px;';
                            
                            const pagePath = document.createElement('div');
                            pagePath.textContent = page.path;
                            pagePath.style.cssText = 'font-size: 12px; color: var(--muted);';
                            
                            if (page.title && page.title !== (page.menu_title || page.path)) {
                                const pageSubtitle = document.createElement('div');
                                pageSubtitle.textContent = page.title;
                                pageSubtitle.style.cssText = 'font-size: 12px; color: var(--muted); margin-top: 2px;';
                                pageItem.appendChild(pageSubtitle);
                            }
                            
                            pageItem.appendChild(pageTitle);
                            pageItem.appendChild(pagePath);
                            
                            pageItem.onclick = () => {
                                // 移除之前的選中狀態
                                pagesList.querySelectorAll('.page-item').forEach(item => {
                                    item.style.background = 'transparent';
                                    item.style.borderLeft = 'none';
                                });
                                // 設置新的選中狀態
                                pageItem.style.background = 'var(--accent, rgba(124,92,255,0.2))';
                                pageItem.style.borderLeft = '3px solid var(--accent, rgba(124,92,255,0.8))';
                                selectedPage = { path: page.path, title: page.menu_title || page.title || page.path };
                            };
                            
                            pageItem.onmouseenter = () => {
                                if (!isSelected) {
                                    pageItem.style.background = 'var(--surface, rgba(255,255,255,0.06))';
                                }
                            };
                            
                            pageItem.onmouseleave = () => {
                                if (!isSelected) {
                                    pageItem.style.background = 'transparent';
                                }
                            };
                            
                            pagesList.appendChild(pageItem);
                        });
                    }
                    
                    // 搜尋功能
                    let searchTimeout = null;
                    searchInput.addEventListener('input', (e) => {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(() => {
                            if (e.target.value.trim()) {
                                pagesList.style.display = 'block';
                                renderPagesList(e.target.value);
                            } else {
                                pagesList.style.display = 'none';
                            }
                        }, 300);
                    });
                    
                    // 尺寸設置
                    const sizeLabel = document.createElement('label');
                    sizeLabel.textContent = '尺寸設置';
                    sizeLabel.style.cssText = 'display: block; margin-bottom: 8px; color: var(--text); font-weight: 500; margin-top: 16px;';
                    
                    const sizeRow = document.createElement('div');
                    sizeRow.style.cssText = 'display: flex; gap: 12px; margin-bottom: 16px;';
                    
                    const widthLabel = document.createElement('label');
                    widthLabel.textContent = '寬度';
                    widthLabel.style.cssText = 'display: block; margin-bottom: 4px; color: var(--muted); font-size: 12px;';
                    const widthInput = document.createElement('input');
                    widthInput.type = 'text';
                    widthInput.placeholder = 'auto 或 200px';
                    widthInput.value = isEditMode && editData ? (editData.width || 'auto') : 'auto';
                    widthInput.style.cssText = 'flex: 1; padding: 8px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2); color: var(--text); font-size: 14px;';
                    
                    const heightLabel = document.createElement('label');
                    heightLabel.textContent = '高度';
                    heightLabel.style.cssText = 'display: block; margin-bottom: 4px; color: var(--muted); font-size: 12px;';
                    const heightInput = document.createElement('input');
                    heightInput.type = 'text';
                    heightInput.placeholder = 'auto 或 50px';
                    heightInput.value = isEditMode && editData ? (editData.height || 'auto') : 'auto';
                    heightInput.style.cssText = 'flex: 1; padding: 8px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2); color: var(--text); font-size: 14px;';
                    
                    const fontSizeLabel = document.createElement('label');
                    fontSizeLabel.textContent = '字體大小';
                    fontSizeLabel.style.cssText = 'display: block; margin-bottom: 4px; color: var(--muted); font-size: 12px;';
                    const fontSizeInput = document.createElement('input');
                    fontSizeInput.type = 'text';
                    fontSizeInput.placeholder = '16px';
                    fontSizeInput.value = isEditMode && editData ? (editData.fontSize || '16px') : '16px';
                    fontSizeInput.style.cssText = 'flex: 1; padding: 8px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2); color: var(--text); font-size: 14px;';
                    
                    const widthCol = document.createElement('div');
                    widthCol.style.cssText = 'flex: 1;';
                    widthCol.appendChild(widthLabel);
                    widthCol.appendChild(widthInput);
                    
                    const heightCol = document.createElement('div');
                    heightCol.style.cssText = 'flex: 1;';
                    heightCol.appendChild(heightLabel);
                    heightCol.appendChild(heightInput);
                    
                    const fontSizeCol = document.createElement('div');
                    fontSizeCol.style.cssText = 'flex: 1;';
                    fontSizeCol.appendChild(fontSizeLabel);
                    fontSizeCol.appendChild(fontSizeInput);
                    
                    sizeRow.appendChild(widthCol);
                    sizeRow.appendChild(heightCol);
                    sizeRow.appendChild(fontSizeCol);
                    
                    // 位置設置
                    const alignLabel = document.createElement('label');
                    alignLabel.textContent = '位置對齊';
                    alignLabel.style.cssText = 'display: block; margin-bottom: 8px; color: var(--text); font-weight: 500;';
                    const alignSelect = document.createElement('select');
                    alignSelect.style.cssText = 'width: 100%; padding: 12px; margin-bottom: 16px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg2); color: var(--text); font-size: 14px;';
                    const alignOptions = [
                        { value: 'left', label: '左對齊' },
                        { value: 'center', label: '居中' },
                        { value: 'right', label: '右對齊' }
                    ];
                    alignOptions.forEach(opt => {
                        const option = document.createElement('option');
                        option.value = opt.value;
                        option.textContent = opt.label;
                        if (opt.value === (isEditMode && editData ? (editData.align || 'left') : 'left')) {
                            option.selected = true;
                        }
                        alignSelect.appendChild(option);
                    });
                    
                    // 特效設置
                    const effectLabel = document.createElement('label');
                    effectLabel.textContent = '按鈕特效';
                    effectLabel.style.cssText = 'display: block; margin-bottom: 8px; color: var(--text); font-weight: 500;';
                    const effectSelect = document.createElement('select');
                    effectSelect.style.cssText = 'width: 100%; padding: 12px; margin-bottom: 16px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg2); color: var(--text); font-size: 14px;';
                    const effectOptions = [
                        { value: 'gradient', label: '漸變（預設）' },
                        { value: 'solid', label: '實心' },
                        { value: 'outline', label: '外框' },
                        { value: 'ghost', label: '幽靈' },
                        { value: 'glow', label: '發光' }
                    ];
                    effectOptions.forEach(opt => {
                        const option = document.createElement('option');
                        option.value = opt.value;
                        option.textContent = opt.label;
                        if (opt.value === (isEditMode && editData ? (editData.effect || 'gradient') : 'gradient')) {
                            option.selected = true;
                        }
                        effectSelect.appendChild(option);
                    });
                    
                    // 按鈕組
                    const btnGroup = document.createElement('div');
                    btnGroup.style.cssText = 'display: flex; gap: 12px;';
                    
                    const btnInsert = document.createElement('button');
                    btnInsert.textContent = isEditMode ? '更新' : '插入';
                    btnInsert.className = 'btn btn-ok';
                    btnInsert.style.cssText = 'flex: 1;';
                    btnInsert.onclick = () => {
                        const buttonText = textInput.value.trim() || '按鈕';
                        const pagePath = selectedPage ? selectedPage.path : '';
                        
                        if (isEditMode && currentButtonNode && currentButtonIndex !== null) {
                            // 編輯模式：更新現有按鈕節點屬性
                            currentButtonNode.setAttribute('data-page-path', pagePath);
                            currentButtonNode.setAttribute('data-button-text', buttonText);
                            currentButtonNode.setAttribute('data-width', widthInput.value || 'auto');
                            currentButtonNode.setAttribute('data-height', heightInput.value || 'auto');
                            currentButtonNode.setAttribute('data-font-size', fontSizeInput.value || '16px');
                            currentButtonNode.setAttribute('data-align', alignSelect.value || 'left');
                            currentButtonNode.setAttribute('data-effect', effectSelect.value || 'gradient');
                            
                            // 重新創建按鈕以應用新樣式
                            const container = currentButtonNode.querySelector('.gwa-button-container');
                            if (container) {
                                container.innerHTML = '';
                                const newButton = ButtonBlot.create({
                                    path: pagePath,
                                    text: buttonText,
                                    width: widthInput.value || 'auto',
                                    height: heightInput.value || 'auto',
                                    fontSize: fontSizeInput.value || '16px',
                                    align: alignSelect.value || 'left',
                                    effect: effectSelect.value || 'gradient'
                                });
                                const newButtonEl = newButton.querySelector('.gwa-button-container');
                                if (newButtonEl) {
                                    container.appendChild(newButtonEl.firstChild);
                                }
                            }
                            
                            if (typeof markDirty === 'function') markDirty();
                            if (typeof scheduleDraftSave === 'function') scheduleDraftSave();
                            if (typeof updateDocStats === 'function') updateDocStats();
                        } else {
                            // 插入模式：插入新按鈕
                            const r = targetEditor.getSelection(true);
                            const index = r ? r.index : targetEditor.getLength();
                            
                            targetEditor.insertEmbed(index, 'button', {
                                path: pagePath,
                                text: buttonText,
                                width: widthInput.value || 'auto',
                                height: heightInput.value || 'auto',
                                fontSize: fontSizeInput.value || '16px',
                                align: alignSelect.value || 'left',
                                effect: effectSelect.value || 'gradient'
                            }, 'user');
                            targetEditor.insertText(index + 1, '\n', 'user');
                            targetEditor.setSelection(index + 2, 0, 'silent');
                            
                            if (typeof markDirty === 'function') markDirty();
                            if (typeof scheduleDraftSave === 'function') scheduleDraftSave();
                            if (typeof updateDocStats === 'function') updateDocStats();
                        }
                        
                        modal.remove();
                    };
                    
                    const btnCancel = document.createElement('button');
                    btnCancel.textContent = '取消';
                    btnCancel.className = 'btn';
                    btnCancel.style.cssText = 'flex: 1;';
                    btnCancel.onclick = () => {
                        modal.remove();
                    };
                    
                    btnGroup.appendChild(btnInsert);
                    btnGroup.appendChild(btnCancel);
                    
                    content.appendChild(title);
                    content.appendChild(textLabel);
                    content.appendChild(textInput);
                    content.appendChild(searchLabel);
                    content.appendChild(searchInput);
                    content.appendChild(pagesList);
                    content.appendChild(sizeLabel);
                    content.appendChild(sizeRow);
                    content.appendChild(alignLabel);
                    content.appendChild(alignSelect);
                    content.appendChild(effectLabel);
                    content.appendChild(effectSelect);
                    content.appendChild(btnGroup);
                    
                    modal.appendChild(content);
                    document.body.appendChild(modal);
                    
                    // 載入頁面列表
                    loadPages();
                }

                // 獲取當前活動的編輯器（用於貼上等操作）
                function getActiveQuill() {
                    // 檢查是否有焦點在區塊編輯器中
                    const activeElement = document.activeElement;
                    if (activeElement) {
                        // 查找包含活動元素的區塊編輯器
                        for (const block of blockEditors) {
                            if (block.quill && block.quill.root && block.quill.root.contains(activeElement)) {
                                return block.quill;
                            }
                        }
                    }
                    // 默認返回主編輯器
                    return quill;
                }

                async function uploadImage(file, targetQuill = null) {
                    const targetEditor = targetQuill || getActiveQuill();
                    if (!targetEditor) return;
                    
                    const form = new FormData();
                    form.append('image', file);
                    form.append('page_path', currentEditingPath || '');

                    const range = targetEditor.getSelection(true);
                    const index = range ? range.index : targetEditor.getLength();
                    const loadingText = '上傳中...';
                    targetEditor.insertText(index, loadingText, { color: '#999' });
                    const loadingTextLength = loadingText.length; // 保存插入文本的長度

                    try {
                    const res = await fetch(`${API_URL}?action=upload_image`, {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': CSRF_TOKEN },
                        body: form
                    });
                    const data = await res.json().catch(() => null);
                        
                        // 確保完全刪除「上傳中...」文本
                        targetEditor.deleteText(index, loadingTextLength);
                        
                    if (!data || !data.ok) {
                        targetEditor.insertText(index, '上傳失敗', { color: '#f00' });
                        return;
                    }
                    targetEditor.insertEmbed(index, 'image', data.url);
                    } catch (error) {
                        // 如果發生錯誤，也要確保刪除「上傳中...」文本
                        targetEditor.deleteText(index, loadingTextLength);
                        targetEditor.insertText(index, '上傳失敗', { color: '#f00' });
                    }
                }

                // 貼上 base64 圖片處理（把 data: 轉為實體檔案上傳）
                // 為所有編輯器添加貼上事件監聽器
                function setupPasteHandler(editorQuill) {
                    if (!editorQuill || !editorQuill.root) return;
                    
                    editorQuill.root.addEventListener('paste', function(e) {
                        const htmlData = e.clipboardData && e.clipboardData.getData('text/html');
                        if (!htmlData) return;
                        const tmp = document.createElement('div');
                        tmp.innerHTML = htmlData;
                        const images = tmp.querySelectorAll('img[src^="data:"]');
                        if (!images.length) return;
                        e.preventDefault();

                        images.forEach(img => {
                            const src = img.getAttribute('src') || '';
                            const base64 = src.split(',')[1] || '';
                            const mimeMatch = src.match(/data:([^;]+);/);
                            if (!mimeMatch) return;
                            const mime = mimeMatch[1];
                            const bytes = atob(base64);
                            const arr = new Uint8Array(bytes.length);
                            for (let i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);
                            const blob = new Blob([arr], { type: mime });
                            const ext = (mime.split('/')[1] || 'png');
                            const file = new File([blob], 'pasted.' + ext, { type: mime });
                            uploadImage(file, editorQuill);  // 傳遞當前編輯器
                        });

                        const text = e.clipboardData.getData('text/plain');
                        if (text) {
                            const sel = editorQuill.getSelection(true);
                            if (sel) {
                                editorQuill.insertText(sel.index, text);
                            }
                        }
                    });
                }
                
                // 為主編輯器設置貼上處理
                setupPasteHandler(quill);

                btnNew.onclick = async () => {
                    const r = await apiJson(`${API_URL}?action=create_random_example_page`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                        body: JSON.stringify({})
                    });
                    showMsg('已新增示例頁：' + r.path, 'ok');
                    await refreshListAndSelect(r.path);
                };
                async function assignPath() {
                    if (!currentEditingPath || currentEditingPath === '') {
                        showMsg('請先選擇或建立一個頁面', 'err');
                        return;
                    }
                    if (currentEditingPath === 'home') {
                        showMsg('home 頁面不允許更改網址', 'err');
                        return;
                    }
                    
                    const title = (elTitle.value || '').trim();
                    if (!title) {
                        showMsg('請先填寫標題', 'err');
                        return;
                    }
                    
                    // 從 pagesCache 獲取當前頁面的實際 parent（優先於表單值）
                    const currentPageData = pagesCache.find(p => p.path === currentEditingPath);
                    let currentParent = '';
                    
                    // 優先使用 pagesCache 中的 parent（即使為空字符串，也表示這是頂層頁面）
                    if (currentPageData && 'parent' in currentPageData) {
                        currentParent = (currentPageData.parent || '').trim();
                    } else {
                        // 如果 pagesCache 中沒有，嘗試從表單獲取
                        currentParent = (elParent.value || '').trim();
                        
                        // 只有在完全沒有 parent 信息時，才從當前路徑推導（僅當路徑包含 '/' 時）
                        // 但這只適用於數據不一致的情況，正常情況下應該避免
                        if (!currentParent && currentEditingPath.includes('/')) {
                            // 檢查推導出的 parent 是否存在，如果不存在，則視為頂層頁面
                            const derivedParent = currentEditingPath.split('/').slice(0, -1).join('/');
                            const derivedParentExists = pagesCache.some(p => p.path === derivedParent);
                            if (derivedParentExists) {
                                currentParent = derivedParent;
                            } else {
                                // 推導出的 parent 不存在，視為頂層頁面
                                currentParent = '';
                            }
                        }
                    }
                    
                    // 驗證 parent 是否存在（如果 parent 不為空）
                    if (currentParent && currentParent !== 'home') {
                        const parentExists = pagesCache.some(p => p.path === currentParent);
                        if (!parentExists) {
                            showMsg(`父層頁面「${currentParent}」不存在，請先建立父層頁面或調整父層設定`, 'err');
                            return;
                        }
                    }
                    
                    // 根據標題計算新路徑
                    const name = (elMenuTitle.value || '').trim();
                    const seed = name || title;
                    let slug = slugify(seed);
                    if (!slug) {
                        slug = 'p-' + hash36(seed || (Date.now() + '')).slice(0, 8);
                    }
                    if (slug === 'home') slug = 'home-' + hash36(seed).slice(0, 6);
                    
                    // 構建新路徑（保留原有的父層關係）
                    let newPath = slug;
                    if (currentParent && currentParent !== 'home') {
                        newPath = currentParent + '/' + slug;
                    }
                    
                    // 確保路徑唯一（排除當前路徑）
                    newPath = ensureUniquePath(newPath, currentEditingPath);
                    
                    // 檢查是否有子頁面（用於提示）
                    const childPages = pagesCache.filter(p => {
                        const pParent = effectiveParentClient(p);
                        return pParent === currentEditingPath;
                    });
                    
                    // 調試信息
                    console.log('[assignPath]', {
                        title,
                        name,
                        seed,
                        slug,
                        currentParent,
                        currentEditingPath,
                        newPath,
                        childCount: childPages.length
                    });
                    
                    if (!newPath || newPath === currentEditingPath) {
                        showMsg(`網址無需更新（當前：${currentEditingPath}，計算結果：${newPath}）`, 'info');
                        return;
                    }
                    
                    // 構建確認訊息
                    let confirmMsg = `確定要將網址從「${currentEditingPath}」更新為「${newPath}」嗎？`;
                    if (childPages.length > 0) {
                        confirmMsg += `\n\n這會同時更新 ${childPages.length} 個子頁的網址：\n`;
                        childPages.slice(0, 5).forEach(p => {
                            confirmMsg += `  - ${p.menu_title || p.title || p.path}\n`;
                        });
                        if (childPages.length > 5) {
                            confirmMsg += `  ... 還有 ${childPages.length - 5} 個子頁\n`;
                        }
                    }
                    
                    if (!confirm(confirmMsg)) {
                        return;
                    }
                    
                    try {
                        // 使用當前表單中的值，保留用戶正在編輯但尚未儲存的內容
                        const currentTitle = (elTitle.value || '').trim();
                        const currentMenuTitle = (elMenuTitle.value || '').trim();
                        const currentType = (elPageType && elPageType.value) || 'page';
                        const currentPrice = (currentType === 'product' && elPrice) ? parseFloat(elPrice.value || 0) : 0;
                        
                        // 清理 HTML（移除「雙擊編輯」提示）
                        let currentHtml = quill.root.innerHTML;
                        currentHtml = currentHtml.replace(/<div[^>]*>雙擊編輯<\/div>/gi, '');
                        currentHtml = currentHtml.replace(/雙擊編輯/gi, '');
                        currentHtml = normalizeHtmlForSave(currentHtml);
                        
                        // 更新路徑並保存（這會觸發子頁路徑的自動更新）
                        elPath.value = getFullUrl(newPath);
                        await apiJson(`${API_URL}?action=save_page`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                            body: JSON.stringify({
                                page: {
                                    path: newPath,
                                    title: currentTitle,
                                    menu_title: currentMenuTitle,
                                    parent: currentParent,  // 明確設置 parent，確保一致性
                                    type: currentType,
                                    price: currentPrice
                                },
                                html: currentHtml,
                                oldPath: currentEditingPath
                            })
                        });
                        
                        // 更新表單中的 parent 顯示（使用明確設置的 currentParent，而不是從路徑推導）
                        // 這樣確保頂層頁面即使路徑包含 '/'，parent 也會保持為空
                        if (elParent) elParent.value = currentParent || '';
                        if (elParentDisplay) elParentDisplay.value = currentParent || '';
                        
                        showMsg(`網址已更新為「${newPath}」${childPages.length > 0 ? `，${childPages.length} 個子頁路徑也已自動更新` : ''}`, 'ok');
                        await refreshListAndSelect(newPath);
                    } catch (e) {
                        console.error('[assignPath] 錯誤:', e);
                        showMsg(e.message || String(e), 'err');
                    }
                }
                
                const btnAssignPath = document.getElementById('btnAssignPath');
                if (btnAssignPath) {
                    btnAssignPath.onclick = () => assignPath().catch(e => showMsg(e.message || String(e), 'err'));
                }
                
                function showQrCode() {
                    // 檢查 QRCode 庫是否已載入，如果沒有則等待載入
                    if (typeof QRCode === 'undefined') {
                        // 檢查是否已經有腳本標籤正在載入
                        let script = document.querySelector('script[src*="qrcode"]');
                        if (!script) {
                            // 如果沒有，創建新的腳本標籤
                            script = document.createElement('script');
                            script.src = 'https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js';
                            script.async = true;
                            document.head.appendChild(script);
                        }
                        
                        // 等待庫載入（最多等待 5 秒）
                        let attempts = 0;
                        const maxAttempts = 50; // 50 * 100ms = 5秒
                        const checkInterval = setInterval(() => {
                            attempts++;
                            if (typeof QRCode !== 'undefined') {
                                clearInterval(checkInterval);
                                // 庫已載入，重新調用函數
                                showQrCode();
                            } else if (attempts >= maxAttempts) {
                                clearInterval(checkInterval);
                                showMsg('QRCode 庫載入超時，請檢查網路連線或重新整理頁面', 'err');
                            }
                        }, 100);
                        
                        // 如果正在載入，顯示提示
                        if (attempts === 0) {
                            showMsg('正在載入 QRCode 庫，請稍候...', 'info');
                        }
                        return;
                    }
                    
                    // 從 path input 獲取完整 URL（現在已經顯示完整 URL）
                    let fullUrl = elPath.value || '';
                    
                    // 如果 path input 為空，從 currentEditingPath 構建完整 URL
                    if (!fullUrl) {
                        const path = currentEditingPath || '';
                        if (!path || path === '') {
                            return; // 靜默返回，不顯示錯誤
                        }
                        fullUrl = getFullUrl(path);
                    }
                    
                    if (!fullUrl || fullUrl === '') {
                        return; // 靜默返回
                    }
                    
                    // 如果已經有模態框打開，先移除
                    const existingModal = document.querySelector('.modal-overlay');
                    if (existingModal) {
                        document.body.removeChild(existingModal);
                    }
                    
                    // 創建模態框
                    const modal = document.createElement('div');
                    modal.className = 'modal-overlay';
                    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center;';
                    
                    const modalContent = document.createElement('div');
                    modalContent.style.cssText = 'background: var(--bg, #fff); padding: 24px; border-radius: 12px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.3);';
                    
                    const title = document.createElement('div');
                    title.style.cssText = 'font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text, #333);';
                    title.textContent = '完整 URL';
                    
                    const urlText = document.createElement('div');
                    urlText.style.cssText = 'font-size: 14px; color: var(--muted, #666); margin-bottom: 20px; word-break: break-all; padding: 8px; background: var(--input-bg, #f5f5f5); border-radius: 6px;';
                    urlText.textContent = fullUrl;
                    
                    const qrContainer = document.createElement('div');
                    qrContainer.id = 'qrCodeContainer';
                    qrContainer.style.cssText = 'margin: 20px 0; display: flex; justify-content: center; cursor: pointer;';
                    qrContainer.title = '點擊下載 QR 碼';
                    
                    const closeBtn = document.createElement('button');
                    closeBtn.className = 'btn';
                    closeBtn.textContent = '關閉';
                    closeBtn.style.cssText = 'margin-top: 16px;';
                    closeBtn.onclick = () => document.body.removeChild(modal);
                    
                    modalContent.appendChild(title);
                    modalContent.appendChild(urlText);
                    modalContent.appendChild(qrContainer);
                    modalContent.appendChild(closeBtn);
                    
                    modal.appendChild(modalContent);
                    document.body.appendChild(modal);
                    
                    // 生成 QR 碼
                    const canvas = document.createElement('canvas');
                    QRCode.toCanvas(canvas, fullUrl, {
                        width: 300,
                        margin: 2,
                        color: {
                            dark: '#000000',
                            light: '#FFFFFF'
                        }
                    }, (error) => {
                        if (error) {
                            showMsg('生成 QR 碼失敗：' + error.message, 'err');
                            document.body.removeChild(modal);
                            return;
                        }
                        qrContainer.appendChild(canvas);
                        
                        // 點擊下載 QR 碼
                        qrContainer.onclick = () => {
                            canvas.toBlob((blob) => {
                                const url = URL.createObjectURL(blob);
                                const a = document.createElement('a');
                                a.href = url;
                                // 從完整 URL 提取路徑用於檔案名
                                const urlPath = fullUrl.replace(window.location.origin + BASE_PATH, '').replace(/^\/+|\/+$/g, '') || 'home';
                                a.download = `qr-${urlPath.replace(/\//g, '-')}.png`;
                                document.body.appendChild(a);
                                a.click();
                                document.body.removeChild(a);
                                URL.revokeObjectURL(url);
                                showMsg('QR 碼已下載', 'ok');
                            }, 'image/png');
                        };
                    });
                    
                    // 點擊背景關閉
                    modal.onclick = (e) => {
                        if (e.target === modal) {
                            document.body.removeChild(modal);
                        }
                    };
                }
                
                const btnShowQr = document.getElementById('btnShowQr');
                if (btnShowQr) {
                    btnShowQr.onclick = showQrCode;
                }
                
                btnSave.onclick = () => savePage().catch(e => showMsg(e.message || String(e), 'err'));
                btnDelete.onclick = () => deletePage().catch(e => showMsg(e.message || String(e), 'err'));
                const btnRestoreLive = document.getElementById('btnRestoreLive');
                if (btnRestoreLive) {
                    btnRestoreLive.onclick = async () => {
                        if (!confirm('確定捨棄草稿並載入已發佈版本？')) return;
                        await removeDraft(currentEditingPath || 'home');
                        await loadPage(currentEditingPath || 'home', { skipSaveDraft: true }).catch(e => showMsg(e.message || String(e), 'err'));
                    };
                }
                if (btnChangePassword) {
                    btnChangePassword.onclick = async () => {
                        const currentPassword = prompt('請輸入舊密碼：');
                        if (currentPassword === null) return;
                        const newPassword = prompt('請輸入新密碼（至少 10 字元）：');
                        if (newPassword === null) return;
                        if (String(newPassword).length < 10) {
                            showMsg('新密碼至少 10 字元', 'err');
                            return;
                        }
                        await apiJson(`${API_URL}?action=change_password`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                            body: JSON.stringify({ currentPassword, newPassword })
                        });
                        showMsg('密碼已更新', 'ok');
                    };
                }

                // 主題樣式一致性驗證函數
                function validateThemeConsistency() {
                    const root = document.documentElement;
                    const computed = getComputedStyle(root);
                    const editorEl = document.querySelector('.ql-container.ql-snow .ql-editor');
                    if (editorEl) {
                        const editorComputed = getComputedStyle(editorEl);
                        const editorBg = editorComputed.backgroundColor;
                        const editorColor = editorComputed.color;
                        const expectedBg = computed.getPropertyValue('--page-bg').trim() || computed.getPropertyValue('--editor-bg').trim();
                        const expectedColor = computed.getPropertyValue('--editor-text').trim() || computed.getPropertyValue('--text').trim();
                        console.log('[Theme Validator]', {
                            editorBg,
                            editorColor,
                            expectedBg,
                            expectedColor,
                            vars: {
                                pageBg: computed.getPropertyValue('--page-bg'),
                                editorBg: computed.getPropertyValue('--editor-bg'),
                                editorText: computed.getPropertyValue('--editor-text'),
                                text: computed.getPropertyValue('--text')
                            }
                        });
                    }
                }
                
                if (themeSelect) {
                    themeSelect.onchange = async () => {
                        const theme = themeSelect.value;
                        await apiJson(`${API_URL}?action=theme_set`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                            body: JSON.stringify({ theme })
                        });
                        // 即時套用（加上 cache bust）
                        const link = document.getElementById('themeCss');
                        if (link) {
                            link.setAttribute('href', `${BASE_PATH}assets/themes/${theme}.css?v=${Date.now()}`);
                            link.onload = () => setTimeout(validateThemeConsistency, 100);
                        }
                        showMsg('主題已切換：' + theme, 'ok');
                        setTimeout(validateThemeConsistency, 200);
                    };
                }
                
                // 頁面載入時驗證
                setTimeout(validateThemeConsistency, 500);

                // ========== 包裝選項卡功能初始化 ==========
                // Footer 編輯器
                const footerEditorEl = document.getElementById('footerEditor');
                let footerQuill = null;
                if (footerEditorEl) {
                    footerQuill = new Quill('#footerEditor', {
                        theme: 'snow',
                        modules: {
                            toolbar: '#footerToolbar',
                            history: { delay: 600, maxStack: 50, userOnly: true },
                            clipboard: { matchVisual: false }
                        }
                    });
                    (async () => {
                        try {
                            const data = await apiJson(`${API_URL}?action=footer_get`, {
                                headers: { 'Accept': 'application/json', 'X-CSRF-Token': CSRF_TOKEN }
                            });
                            if (data && data.ok && data.footer) {
                                footerQuill.root.innerHTML = data.footer;
                            }
                        } catch (e) {
                            console.warn('載入頁尾失敗:', e);
                        }
                    })();
                }

                // Checkout 編輯器
                const checkoutEditorEl = document.getElementById('checkoutEditor');
                let checkoutQuill = null;
                if (checkoutEditorEl) {
                    checkoutQuill = new Quill('#checkoutEditor', {
                        theme: 'snow',
                        modules: {
                            toolbar: '#checkoutToolbar',
                            history: { delay: 600, maxStack: 50, userOnly: true },
                            clipboard: { matchVisual: false }
                        }
                    });
                    (async () => {
                        try {
                            const data = await apiJson(`${API_URL}?action=checkout_page_get`, {
                                headers: { 'Accept': 'application/json', 'X-CSRF-Token': CSRF_TOKEN }
                            });
                            if (data && data.ok && data.checkout_page) {
                                checkoutQuill.root.innerHTML = data.checkout_page;
                            }
                        } catch (e) {
                            console.warn('載入結算畫面失敗:', e);
                        }
                    })();
                }

                // Brand 設定
                const brandTitle = document.getElementById('brandTitle');
                const brandSubtitle = document.getElementById('brandSubtitle');
                const btnSaveBrand = document.getElementById('btnSaveBrand');
                const btnUploadBrandImage = document.getElementById('btnUploadBrandImage');
                const brandImageUpload = document.getElementById('brandImageUpload');
                const brandImagePreview = document.getElementById('brandImagePreview');
                const logoPreview = document.getElementById('logoPreview');
                const iconPreview = document.getElementById('iconPreview');
                const btnApplyBrandImage = document.getElementById('btnApplyBrandImage');
                const btnCancelBrandImage = document.getElementById('btnCancelBrandImage');
                const brandImageCurrent = document.getElementById('brandImageCurrent');
                const currentLogoPreview = document.getElementById('currentLogoPreview');
                const currentIconPreview = document.getElementById('currentIconPreview');
                const btnRemoveBrandImage = document.getElementById('btnRemoveBrandImage');
                
                let currentLogoUrl = '';
                let currentIconUrl = '';
                let pendingLogoUrl = '';
                let pendingIconUrl = '';
                
                // 載入現有的 logo 和 icon
                (async () => {
                    try {
                        const data = await apiJson(`${API_URL}?action=brand_get`, {
                            headers: { 'Accept': 'application/json', 'X-CSRF-Token': CSRF_TOKEN }
                        });
                        if (data && data.ok && data.brand) {
                            currentLogoUrl = data.brand.logo || '';
                            currentIconUrl = data.brand.icon || '';
                            
                            if (currentLogoUrl || currentIconUrl) {
                                if (currentLogoUrl) {
                                    const logoUrlFull = currentLogoUrl.startsWith('http') ? currentLogoUrl : BASE_PATH + currentLogoUrl;
                                    currentLogoPreview.innerHTML = `<img src="${escapeHtml(logoUrlFull)}" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
                                }
                                if (currentIconUrl) {
                                    const iconUrlFull = currentIconUrl.startsWith('http') ? currentIconUrl : BASE_PATH + currentIconUrl;
                                    currentIconPreview.innerHTML = `<img src="${escapeHtml(iconUrlFull)}" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
                                }
                                brandImageCurrent.style.display = 'block';
                            } else {
                                // 嘗試載入 favicon.ico
                                const faviconUrl = `${API_URL.replace('/api.php', '')}favicon.ico`;
                                try {
                                    const testResponse = await fetch(faviconUrl, { method: 'HEAD' });
                                    if (testResponse.ok) {
                                        currentLogoUrl = faviconUrl;
                                        currentIconUrl = faviconUrl;
                                        currentLogoPreview.innerHTML = `<img src="${faviconUrl}?t=${Date.now()}" style="max-width: 100%; max-height: 100%; object-fit: contain;" onerror="this.parentElement.innerHTML='<span style=\\'color: var(--muted); font-size: 10px;\\'>無 Logo</span>';">`;
                                        currentIconPreview.innerHTML = `<img src="${faviconUrl}?t=${Date.now()}" style="max-width: 100%; max-height: 100%; object-fit: contain;" onerror="this.parentElement.innerHTML='<span style=\\'color: var(--muted); font-size: 10px;\\'>無 Icon</span>';">`;
                                        brandImageCurrent.style.display = 'block';
                                    }
                                } catch (e) {
                                    // 忽略錯誤
                                }
                            }
                        }
                    } catch (e) {
                        console.warn('載入品牌設定失敗:', e);
                    }
                })();
                
                // 圖片編輯器相關元素
                const imageEditor = document.getElementById('imageEditor');
                const editorCanvas = document.getElementById('editorCanvas');
                const cropOverlay = document.getElementById('cropOverlay');
                const btnCrop = document.getElementById('btnCrop');
                const btnResetCrop = document.getElementById('btnResetCrop');
                const sliderScale = document.getElementById('sliderScale');
                const sliderBrightness = document.getElementById('sliderBrightness');
                const sliderContrast = document.getElementById('sliderContrast');
                const sliderSaturation = document.getElementById('sliderSaturation');
                const scaleValue = document.getElementById('scaleValue');
                const brightnessValue = document.getElementById('brightnessValue');
                const contrastValue = document.getElementById('contrastValue');
                const saturationValue = document.getElementById('saturationValue');
                
                let originalImage = null;
                let ctx = null;
                let cropState = { x: 0, y: 0, w: 0, h: 0, active: false };
                let editParams = { scale: 100, brightness: 0, contrast: 0, saturation: 0 };
                let originalImageData = null;
                let canvasOffset = { x: 0, y: 0 };
                let canvasScale = 1;
                
                // 初始化編輯器
                if (editorCanvas) {
                    ctx = editorCanvas.getContext('2d');
                }
                
                // 應用圖片效果
                function applyImageEffects() {
                    if (!originalImage || !ctx) return;
                    
                    const canvas = editorCanvas;
                    const img = originalImage;
                    const scale = editParams.scale / 100;
                    const crop = cropState.active && cropState.w > 0 && cropState.h > 0 
                        ? cropState 
                        : { x: 0, y: 0, w: img.width, h: img.height };
                    
                    const displayW = crop.w * scale;
                    const displayH = crop.h * scale;
                    const maxW = 600;
                    const maxH = 400;
                    const canvasW = Math.min(maxW, Math.max(400, displayW));
                    const canvasH = Math.min(maxH, Math.max(300, displayH));
                    
                    canvas.width = canvasW;
                    canvas.height = canvasH;
                    
                    canvasOffset.x = (canvasW - displayW) / 2;
                    canvasOffset.y = (canvasH - displayH) / 2;
                    canvasScale = scale;
                    
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.save();
                    
                    // 繪製圖片
                    ctx.drawImage(img, crop.x, crop.y, crop.w, crop.h, 
                        canvasOffset.x, canvasOffset.y, displayW, displayH);
                    
                    // 應用濾鏡效果
                    if (editParams.brightness !== 0 || editParams.contrast !== 0 || editParams.saturation !== 0) {
                        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        const data = imageData.data;
                        const brightness = editParams.brightness;
                        const contrast = (editParams.contrast + 100) / 100;
                        const saturation = (editParams.saturation + 100) / 100;
                        
                        for (let i = 0; i < data.length; i += 4) {
                            // 亮度
                            data[i] = Math.max(0, Math.min(255, data[i] + brightness));
                            data[i + 1] = Math.max(0, Math.min(255, data[i + 1] + brightness));
                            data[i + 2] = Math.max(0, Math.min(255, data[i + 2] + brightness));
                            
                            // 對比度
                            data[i] = Math.max(0, Math.min(255, (data[i] - 128) * contrast + 128));
                            data[i + 1] = Math.max(0, Math.min(255, (data[i + 1] - 128) * contrast + 128));
                            data[i + 2] = Math.max(0, Math.min(255, (data[i + 2] - 128) * contrast + 128));
                            
                            // 飽和度
                            const gray = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
                            data[i] = Math.max(0, Math.min(255, gray + (data[i] - gray) * saturation));
                            data[i + 1] = Math.max(0, Math.min(255, gray + (data[i + 1] - gray) * saturation));
                            data[i + 2] = Math.max(0, Math.min(255, gray + (data[i + 2] - gray) * saturation));
                        }
                        ctx.putImageData(imageData, 0, 0);
                    }
                    
                    ctx.restore();
                    updatePreviews();
                    if (cropState.active) updateCropOverlay();
                }
                
                // 更新預覽
                function updatePreviews() {
                    if (!editorCanvas) return;
                    
                    const canvas = editorCanvas;
                    const logoCanvas = document.createElement('canvas');
                    const iconCanvas = document.createElement('canvas');
                    logoCanvas.width = 200;
                    logoCanvas.height = 80;
                    iconCanvas.width = 64;
                    iconCanvas.height = 64;
                    
                    const logoCtx = logoCanvas.getContext('2d');
                    const iconCtx = iconCanvas.getContext('2d');
                    
                    // 繪製 Logo
                    logoCtx.drawImage(canvas, 0, 0, logoCanvas.width, logoCanvas.height);
                    logoPreview.innerHTML = `<img src="${logoCanvas.toDataURL()}" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
                    
                    // 繪製 Icon
                    iconCtx.drawImage(canvas, 0, 0, iconCanvas.width, iconCanvas.height);
                    iconPreview.innerHTML = `<img src="${iconCanvas.toDataURL()}" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
                }
                
                // 裁剪功能
                if (btnCrop && editorCanvas) {
                    let isDragging = false;
                    let startX = 0, startY = 0;
                    let cropCanvasX = 0, cropCanvasY = 0;
                    
                    function canvasToImage(x, y) {
                        return {
                            x: (x - canvasOffset.x) / canvasScale,
                            y: (y - canvasOffset.y) / canvasScale
                        };
                    }
                    
                    editorCanvas.addEventListener('mousedown', (e) => {
                        if (!cropState.active) return;
                        isDragging = true;
                        const rect = editorCanvas.getBoundingClientRect();
                        const canvasX = e.clientX - rect.left;
                        const canvasY = e.clientY - rect.top;
                        startX = canvasX;
                        startY = canvasY;
                        const imgPos = canvasToImage(canvasX, canvasY);
                        cropCanvasX = imgPos.x;
                        cropCanvasY = imgPos.y;
                    });
                    
                    editorCanvas.addEventListener('mousemove', (e) => {
                        if (!cropState.active) return;
                        const rect = editorCanvas.getBoundingClientRect();
                        const canvasX = e.clientX - rect.left;
                        const canvasY = e.clientY - rect.top;
                        
                        if (isDragging) {
                            const imgPos = canvasToImage(canvasX, canvasY);
                            cropState.x = Math.max(0, Math.min(cropCanvasX, imgPos.x));
                            cropState.y = Math.max(0, Math.min(cropCanvasY, imgPos.y));
                            cropState.w = Math.abs(imgPos.x - cropCanvasX);
                            cropState.h = Math.abs(imgPos.y - cropCanvasY);
                            cropState.w = Math.min(cropState.w, originalImage.width - cropState.x);
                            cropState.h = Math.min(cropState.h, originalImage.height - cropState.y);
                            updateCropOverlay();
                        }
                    });
                    
                    editorCanvas.addEventListener('mouseup', () => {
                        isDragging = false;
                        if (cropState.active && cropState.w > 0 && cropState.h > 0) {
                            applyImageEffects();
                        }
                    });
                    
                    btnCrop.onclick = () => {
                        cropState.active = !cropState.active;
                        if (cropState.active && originalImage) {
                            cropState.x = originalImage.width * 0.1;
                            cropState.y = originalImage.height * 0.1;
                            cropState.w = originalImage.width * 0.8;
                            cropState.h = originalImage.height * 0.8;
                            editorCanvas.style.cursor = 'crosshair';
                            applyImageEffects();
                        } else {
                            editorCanvas.style.cursor = 'default';
                            cropOverlay.style.display = 'none';
                        }
                    };
                    
                    btnResetCrop.onclick = () => {
                        cropState = { x: 0, y: 0, w: 0, h: 0, active: false };
                        cropOverlay.style.display = 'none';
                        editorCanvas.style.cursor = 'default';
                        applyImageEffects();
                    };
                }
                
                function updateCropOverlay() {
                    if (!cropState.active || !cropOverlay || !originalImage || cropState.w === 0 || cropState.h === 0) {
                        if (cropOverlay) cropOverlay.style.display = 'none';
                        return;
                    }
                    const rect = editorCanvas.getBoundingClientRect();
                    const displayX = canvasOffset.x + cropState.x * canvasScale;
                    const displayY = canvasOffset.y + cropState.y * canvasScale;
                    const displayW = cropState.w * canvasScale;
                    const displayH = cropState.h * canvasScale;
                    
                    cropOverlay.style.display = 'block';
                    cropOverlay.style.left = (rect.left + displayX) + 'px';
                    cropOverlay.style.top = (rect.top + displayY) + 'px';
                    cropOverlay.style.width = displayW + 'px';
                    cropOverlay.style.height = displayH + 'px';
                }
                
                // 滑桿事件
                if (sliderScale) {
                    sliderScale.addEventListener('input', (e) => {
                        editParams.scale = parseInt(e.target.value);
                        scaleValue.textContent = editParams.scale + '%';
                        applyImageEffects();
                    });
                }
                if (sliderBrightness) {
                    sliderBrightness.addEventListener('input', (e) => {
                        editParams.brightness = parseInt(e.target.value);
                        brightnessValue.textContent = editParams.brightness;
                        applyImageEffects();
                    });
                }
                if (sliderContrast) {
                    sliderContrast.addEventListener('input', (e) => {
                        editParams.contrast = parseInt(e.target.value);
                        contrastValue.textContent = editParams.contrast;
                        applyImageEffects();
                    });
                }
                if (sliderSaturation) {
                    sliderSaturation.addEventListener('input', (e) => {
                        editParams.saturation = parseInt(e.target.value);
                        saturationValue.textContent = editParams.saturation;
                        applyImageEffects();
                    });
                }
                
                // 上傳品牌圖片
                if (btnUploadBrandImage && brandImageUpload) {
                    btnUploadBrandImage.onclick = () => brandImageUpload.click();
                    brandImageUpload.addEventListener('change', async (e) => {
                        const file = e.target.files?.[0];
                        if (!file) return;
                        
                        // 如果是 SVG，直接上傳
                        if (file.type === 'image/svg+xml' || file.name.toLowerCase().endsWith('.svg')) {
                            const form = new FormData();
                            form.append('image', file);
                            try {
                                showMsg('上傳中...', 'info');
                                const res = await fetch(`${API_URL}?action=upload_brand_image`, {
                                    method: 'POST',
                                    headers: { 'X-CSRF-Token': CSRF_TOKEN },
                                    body: form
                                });
                                const data = await res.json().catch(() => null);
                                if (data && data.ok) {
                                    pendingLogoUrl = data.logo || data.url;
                                    pendingIconUrl = data.icon || data.url;
                                    const logoUrlFull = pendingLogoUrl.startsWith('http') ? pendingLogoUrl : BASE_PATH + pendingLogoUrl;
                                    const iconUrlFull = pendingIconUrl.startsWith('http') ? pendingIconUrl : BASE_PATH + pendingIconUrl;
                                    logoPreview.innerHTML = `<img src="${escapeHtml(logoUrlFull)}" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
                                    iconPreview.innerHTML = `<img src="${escapeHtml(iconUrlFull)}" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
                                    imageEditor.style.display = 'none';
                                    brandImagePreview.style.display = 'block';
                                    showMsg('上傳成功，請點擊「應用設置」保存', 'ok');
                                } else {
                                    showMsg(data?.error || '上傳失敗', 'err');
                                }
                            } catch (e) {
                                showMsg('上傳失敗：' + (e.message || String(e)), 'err');
                            }
                            return;
                        }
                        
                        // 載入圖片到編輯器
                        const reader = new FileReader();
                        reader.onload = (event) => {
                            const img = new Image();
                            img.onload = () => {
                                originalImage = img;
                                cropState = { x: 0, y: 0, w: img.width, h: img.height, active: false };
                                editParams = { scale: 100, brightness: 0, contrast: 0, saturation: 0 };
                                if (sliderScale) sliderScale.value = 100;
                                if (sliderBrightness) sliderBrightness.value = 0;
                                if (sliderContrast) sliderContrast.value = 0;
                                if (sliderSaturation) sliderSaturation.value = 0;
                                if (scaleValue) scaleValue.textContent = '100%';
                                if (brightnessValue) brightnessValue.textContent = '0';
                                if (contrastValue) contrastValue.textContent = '0';
                                if (saturationValue) saturationValue.textContent = '0';
                                applyImageEffects();
                                imageEditor.style.display = 'block';
                                brandImagePreview.style.display = 'none';
                            };
                            img.src = event.target.result;
                        };
                        reader.readAsDataURL(file);
                    });
                }
                
                // 應用品牌圖片
                if (btnApplyBrandImage) {
                    btnApplyBrandImage.onclick = async () => {
                        try {
                            showMsg('處理中...', 'info');
                            
                            // 如果有編輯器且已載入圖片，使用編輯後的圖片
                            if (imageEditor && imageEditor.style.display !== 'none' && editorCanvas && originalImage) {
                                // 生成最終圖片（使用裁剪區域或完整圖片）
                                const finalCanvas = document.createElement('canvas');
                                const crop = cropState.active && cropState.w > 0 && cropState.h > 0 
                                    ? cropState 
                                    : { x: 0, y: 0, w: originalImage.width, h: originalImage.height };
                                
                                finalCanvas.width = crop.w;
                                finalCanvas.height = crop.h;
                                const finalCtx = finalCanvas.getContext('2d');
                                
                                // 繪製裁剪區域
                                finalCtx.drawImage(originalImage, crop.x, crop.y, crop.w, crop.h, 0, 0, crop.w, crop.h);
                                
                                // 應用效果
                                if (editParams.brightness !== 0 || editParams.contrast !== 0 || editParams.saturation !== 0) {
                                    const imageData = finalCtx.getImageData(0, 0, finalCanvas.width, finalCanvas.height);
                                    const data = imageData.data;
                                    const brightness = editParams.brightness;
                                    const contrast = (editParams.contrast + 100) / 100;
                                    const saturation = (editParams.saturation + 100) / 100;
                                    
                                    for (let i = 0; i < data.length; i += 4) {
                                        data[i] = Math.max(0, Math.min(255, data[i] + brightness));
                                        data[i + 1] = Math.max(0, Math.min(255, data[i + 1] + brightness));
                                        data[i + 2] = Math.max(0, Math.min(255, data[i + 2] + brightness));
                                        data[i] = Math.max(0, Math.min(255, (data[i] - 128) * contrast + 128));
                                        data[i + 1] = Math.max(0, Math.min(255, (data[i + 1] - 128) * contrast + 128));
                                        data[i + 2] = Math.max(0, Math.min(255, (data[i + 2] - 128) * contrast + 128));
                                        const gray = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
                                        data[i] = Math.max(0, Math.min(255, gray + (data[i] - gray) * saturation));
                                        data[i + 1] = Math.max(0, Math.min(255, gray + (data[i + 1] - gray) * saturation));
                                        data[i + 2] = Math.max(0, Math.min(255, gray + (data[i + 2] - gray) * saturation));
                                    }
                                    finalCtx.putImageData(imageData, 0, 0);
                                }
                                
                                // 轉換為 Blob 並上傳
                                finalCanvas.toBlob(async (blob) => {
                                    if (!blob) {
                                        showMsg('圖片處理失敗', 'err');
                                        return;
                                    }
                                    
                                    const form = new FormData();
                                    form.append('image', blob, 'edited.png');
                                    
                                    try {
                                        const res = await fetch(`${API_URL}?action=upload_brand_image`, {
                                            method: 'POST',
                                            headers: { 'X-CSRF-Token': CSRF_TOKEN },
                                            body: form
                                        });
                                        const data = await res.json().catch(() => null);
                                        
                                        if (!data || !data.ok) {
                                            showMsg(data?.error || '上傳失敗', 'err');
                                            return;
                                        }
                                        
                                        pendingLogoUrl = data.logo || data.url;
                                        pendingIconUrl = data.icon || data.url;
                                    } catch (e) {
                                        showMsg('上傳失敗：' + (e.message || String(e)), 'err');
                                        return;
                                    }
                                    
                                    // 應用設置
                                    currentLogoUrl = pendingLogoUrl;
                                    currentIconUrl = pendingIconUrl;
                                    
                                    const logoUrlFull = currentLogoUrl.startsWith('http') ? currentLogoUrl : BASE_PATH + currentLogoUrl;
                                    const iconUrlFull = currentIconUrl.startsWith('http') ? currentIconUrl : BASE_PATH + currentIconUrl;
                                    currentLogoPreview.innerHTML = `<img src="${escapeHtml(logoUrlFull)}" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
                                    currentIconPreview.innerHTML = `<img src="${escapeHtml(iconUrlFull)}" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
                                    brandImageCurrent.style.display = 'block';
                                    imageEditor.style.display = 'none';
                                    brandImagePreview.style.display = 'none';
                                    pendingLogoUrl = '';
                                    pendingIconUrl = '';
                                    originalImage = null;
                                    
                                    await saveBrandSettings();
                                }, 'image/png');
                            } else if (pendingLogoUrl && pendingIconUrl) {
                                // 使用已上傳的圖片（SVG 情況）
                                currentLogoUrl = pendingLogoUrl;
                                currentIconUrl = pendingIconUrl;
                                
                                const logoUrlFull = currentLogoUrl.startsWith('http') ? currentLogoUrl : BASE_PATH + currentLogoUrl;
                                const iconUrlFull = currentIconUrl.startsWith('http') ? currentIconUrl : BASE_PATH + currentIconUrl;
                                currentLogoPreview.innerHTML = `<img src="${escapeHtml(logoUrlFull)}" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
                                currentIconPreview.innerHTML = `<img src="${escapeHtml(iconUrlFull)}" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
                                brandImageCurrent.style.display = 'block';
                                brandImagePreview.style.display = 'none';
                                pendingLogoUrl = '';
                                pendingIconUrl = '';
                                
                                await saveBrandSettings();
                            }
                        } catch (e) {
                            showMsg('處理失敗：' + (e.message || String(e)), 'err');
                        }
                    };
                }
                
                // 取消品牌圖片
                if (btnCancelBrandImage) {
                    btnCancelBrandImage.onclick = () => {
                        imageEditor.style.display = 'none';
                        brandImagePreview.style.display = 'none';
                        pendingLogoUrl = '';
                        pendingIconUrl = '';
                        originalImage = null;
                        cropState = { x: 0, y: 0, w: 0, h: 0, active: false };
                        editParams = { scale: 100, brightness: 0, contrast: 0, saturation: 0 };
                        if (brandImageUpload) brandImageUpload.value = '';
                    };
                }
                
                // 處理舊的預覽按鈕（向後兼容）
                const btnApplyBrandImageOld = document.getElementById('btnApplyBrandImageOld');
                const btnCancelBrandImageOld = document.getElementById('btnCancelBrandImageOld');
                const logoPreviewOld = document.getElementById('logoPreviewOld');
                const iconPreviewOld = document.getElementById('iconPreviewOld');
                
                if (btnApplyBrandImageOld) {
                    btnApplyBrandImageOld.onclick = btnApplyBrandImage.onclick;
                }
                if (btnCancelBrandImageOld) {
                    btnCancelBrandImageOld.onclick = btnCancelBrandImage.onclick;
                }
                
                // 移除品牌圖片
                if (btnRemoveBrandImage) {
                    btnRemoveBrandImage.onclick = async () => {
                        currentLogoUrl = '';
                        currentIconUrl = '';
                        currentLogoPreview.innerHTML = '<span style="color: var(--muted); font-size: 10px;">無 Logo</span>';
                        currentIconPreview.innerHTML = '<span style="color: var(--muted); font-size: 10px;">無 Icon</span>';
                        brandImageCurrent.style.display = 'none';
                        await saveBrandSettings();
                    };
                }
                
                // 保存品牌設定
                async function saveBrandSettings() {
                    try {
                        await apiJson(`${API_URL}?action=brand_set`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                            body: JSON.stringify({
                                title: brandTitle.value,
                                subtitle: brandSubtitle.value,
                                logo: currentLogoUrl,
                                icon: currentIconUrl
                            })
                        });
                        showMsg('品牌設定已儲存', 'ok');
                    } catch (e) {
                        showMsg(e.message || '儲存失敗', 'err');
                    }
                }
                
                if (btnSaveBrand && brandTitle && brandSubtitle) {
                    btnSaveBrand.onclick = saveBrandSettings;
                }

                // 協調字體
                const btnTypography = document.getElementById('btnTypography');
                if (btnTypography) {
                    btnTypography.onclick = async () => {
                        const modal = document.createElement('div');
                        modal.className = 'modal-overlay';
                        modal.style.cssText = 'position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center;';
                        const content = document.createElement('div');
                        content.style.cssText = 'background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; max-width: 520px; width: 95%; max-height: 90vh; overflow-y: auto; box-shadow: 0 8px 32px rgba(0,0,0,0.3);';
                        const title = document.createElement('h3');
                        title.textContent = '協調字體';
                        title.style.cssText = 'margin: 0 0 8px 0; color: var(--text);';
                        const hint = document.createElement('p');
                        hint.textContent = '設定全局內容區的預設字體、大小、顏色、粗度，會套用至現有網頁。留空表示使用主題預設。';
                        hint.style.cssText = 'margin: 0 0 20px 0; font-size: 13px; color: var(--muted);';
                        const keys = [
                            { key: 'normal', label: 'Normal（內文）' },
                            { key: 'h1', label: '標題 1' },
                            { key: 'h2', label: '標題 2' },
                            { key: 'h3', label: '標題 3' }
                        ];
                        const fontLibrary = [
                            { group: '西文字體', options: [
                                { name: '使用主題預設', value: '' },
                                { name: '系統預設 (sans-serif)', value: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif' },
                                { name: '系統襯線 (serif)', value: 'Georgia, "Times New Roman", serif' },
                                { name: 'Arial', value: 'Arial, Helvetica, sans-serif' },
                                { name: 'Verdana', value: 'Verdana, Geneva, sans-serif' },
                                { name: 'Trebuchet MS', value: '"Trebuchet MS", Helvetica, sans-serif' },
                                { name: 'Courier New', value: '"Courier New", monospace' },
                                { name: 'Roboto', value: '"Roboto", sans-serif' },
                                { name: 'Open Sans', value: '"Open Sans", sans-serif' },
                                { name: 'Lato', value: '"Lato", sans-serif' },
                                { name: 'Poppins', value: '"Poppins", sans-serif' },
                                { name: 'Montserrat', value: '"Montserrat", sans-serif' },
                                { name: 'Oswald', value: '"Oswald", sans-serif' },
                                { name: 'Source Sans 3', value: '"Source Sans 3", sans-serif' },
                                { name: 'Playfair Display', value: '"Playfair Display", serif' },
                                { name: 'Merriweather', value: '"Merriweather", serif' },
                                { name: 'PT Sans', value: '"PT Sans", sans-serif' },
                                { name: 'Nunito', value: '"Nunito", sans-serif' },
                                { name: 'Raleway', value: '"Raleway", sans-serif' },
                                { name: 'Work Sans', value: '"Work Sans", sans-serif' },
                                { name: 'Barlow', value: '"Barlow", sans-serif' },
                                { name: 'Inter', value: '"Inter", sans-serif' },
                                { name: 'DM Sans', value: '"DM Sans", sans-serif' }
                            ]},
                            { group: '中文字體', options: [
                                { name: '微軟正黑體', value: '"Microsoft JhengHei", "微軟正黑體", sans-serif' },
                                { name: '蘋方-繁', value: '"PingFang TC", "蘋方-繁", "Helvetica Neue", sans-serif' },
                                { name: '思源黑體 TC', value: '"Noto Sans TC", sans-serif' },
                                { name: '思源宋體 TC', value: '"Noto Serif TC", serif' },
                                { name: '思源黑體 HK', value: '"Noto Sans HK", sans-serif' },
                                { name: '思源宋體 HK', value: '"Noto Serif HK", serif' },
                                { name: '思源黑體 SC', value: '"Noto Sans SC", sans-serif' },
                                { name: '思源宋體 SC', value: '"Noto Serif SC", serif' },
                                { name: '明體 (cwTeXMing)', value: '"cwTeXMing", "明體", serif' },
                                { name: '圓體 (Barlow)', value: '"Barlow", "Noto Sans TC", sans-serif' }
                            ]},
                            { group: '自訂', options: [{ name: '自訂字體名稱…', value: '__custom__' }] }
                        ];
                        const inputs = {};
                        keys.forEach(({ key, label }) => {
                            const group = document.createElement('div');
                            group.style.cssText = 'margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border);';
                            const lab = document.createElement('div');
                            lab.textContent = label;
                            lab.style.cssText = 'font-weight: 600; margin-bottom: 10px; color: var(--text);';
                            group.appendChild(lab);
                            const row1 = document.createElement('div');
                            row1.style.cssText = 'display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 8px;';
                            const fontWrap = document.createElement('div');
                            fontWrap.style.cssText = 'display: flex; flex-direction: column; gap: 6px;';
                            const fontFamily = document.createElement('select');
                            fontFamily.style.cssText = 'padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2); color: var(--text); font-size: 13px;';
                            fontLibrary.forEach(({ group: gName, options }) => {
                                const optgroup = document.createElement('optgroup');
                                optgroup.label = gName;
                                options.forEach(({ name, value }) => {
                                    const opt = document.createElement('option');
                                    opt.textContent = name;
                                    opt.value = value;
                                    optgroup.appendChild(opt);
                                });
                                fontFamily.appendChild(optgroup);
                            });
                            const fontCustom = document.createElement('input');
                            fontCustom.type = 'text';
                            fontCustom.placeholder = '自訂：如 "My Font", sans-serif';
                            fontCustom.style.cssText = 'padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2); color: var(--text); font-size: 13px; display: none;';
                            fontFamily.addEventListener('change', () => { fontCustom.style.display = fontFamily.value === '__custom__' ? 'block' : 'none'; });
                            fontWrap.appendChild(fontFamily);
                            fontWrap.appendChild(fontCustom);
                            const fontSize = document.createElement('input');
                            fontSize.type = 'text';
                            fontSize.placeholder = '大小 (如 16px)';
                            fontSize.style.cssText = 'padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2); color: var(--text); font-size: 13px;';
                            row1.appendChild(fontWrap);
                            row1.appendChild(fontSize);
                            const row2 = document.createElement('div');
                            row2.style.cssText = 'display: grid; grid-template-columns: 1fr auto 1fr; gap: 10px; align-items: center;';
                            const color = document.createElement('input');
                            color.type = 'text';
                            color.placeholder = '顏色 (如 #333 或留空)';
                            color.style.cssText = 'padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2); color: var(--text); font-size: 13px;';
                            const colorPick = document.createElement('input');
                            colorPick.type = 'color';
                            colorPick.title = '選色';
                            colorPick.style.cssText = 'width: 40px; height: 36px; padding: 2px; border: 1px solid var(--border); border-radius: 6px; cursor: pointer;';
                            colorPick.oninput = () => { color.value = colorPick.value; };
                            const fontWeight = document.createElement('select');
                            fontWeight.style.cssText = 'padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg2); color: var(--text); font-size: 13px;';
                            ['400', '500', '600', '700'].forEach(w => {
                                const opt = document.createElement('option');
                                opt.value = w;
                                opt.textContent = w + ' ' + (w === '400' ? '一般' : w === '700' ? '粗體' : '');
                                fontWeight.appendChild(opt);
                            });
                            row2.appendChild(color);
                            row2.appendChild(colorPick);
                            row2.appendChild(fontWeight);
                            group.appendChild(row1);
                            group.appendChild(row2);
                            content.appendChild(group);
                            inputs[key] = { fontFamily, fontCustom, fontSize, color, colorPick, fontWeight };
                        });
                        const btnRow = document.createElement('div');
                        btnRow.style.cssText = 'display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;';
                        const btnCancel = document.createElement('button');
                        btnCancel.className = 'btn';
                        btnCancel.textContent = '取消';
                        btnCancel.onclick = () => modal.remove();
                        const btnSave = document.createElement('button');
                        btnSave.className = 'btn btn-ok';
                        btnSave.textContent = '儲存';
                        btnSave.onclick = async () => {
                            try {
                                const typography = {};
                                keys.forEach(({ key }) => {
                                    const i = inputs[key];
                                    const fam = i.fontFamily.value === '__custom__' ? (i.fontCustom.value || '').trim() : (i.fontFamily.value || '').trim();
                                    typography[key] = {
                                        fontFamily: fam,
                                        fontSize: (i.fontSize.value || '').trim(),
                                        color: (i.color.value || '').trim(),
                                        fontWeight: (i.fontWeight.value || '400')
                                    };
                                });
                                await apiJson(`${API_URL}?action=typography_set`, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                                    body: JSON.stringify({ typography })
                                });
                                modal.remove();
                                showMsg('協調字體已儲存，已套用至現有網頁', 'ok');
                            } catch (e) {
                                showMsg(e.message || '儲存失敗', 'err');
                            }
                        };
                        btnRow.appendChild(btnCancel);
                        btnRow.appendChild(btnSave);
                        content.appendChild(btnRow);
                        modal.appendChild(content);
                        content.insertBefore(hint, content.firstChild);
                        content.insertBefore(title, content.firstChild);
                        modal.onclick = (e) => { if (e.target === modal) modal.remove(); };
                        content.onclick = (e) => e.stopPropagation();
                        document.body.appendChild(modal);
                        try {
                            const data = await apiJson(`${API_URL}?action=typography_get`, { headers: { 'Accept': 'application/json', 'X-CSRF-Token': CSRF_TOKEN } });
                            if (data && data.ok && data.typography) {
                                const t = data.typography;
                                const allValues = [];
                                fontLibrary.forEach(({ options }) => { options.forEach(({ value }) => { if (value && value !== '__custom__') allValues.push(value); }); });
                                keys.forEach(({ key }) => {
                                    const o = t[key] || {};
                                    const i = inputs[key];
                                    const fam = (o.fontFamily || '').trim();
                                    if (fam && allValues.indexOf(fam) >= 0) {
                                        i.fontFamily.value = fam;
                                        i.fontCustom.style.display = 'none';
                                        i.fontCustom.value = '';
                                    } else {
                                        i.fontFamily.value = '__custom__';
                                        i.fontCustom.style.display = 'block';
                                        i.fontCustom.value = fam;
                                    }
                                    i.fontSize.value = o.fontSize || '';
                                    i.color.value = o.color || '';
                                    i.colorPick.value = o.color && /^#[0-9A-Fa-f]{6}$/.test(o.color) ? o.color : '#333333';
                                    i.fontWeight.value = (o.fontWeight || '400').toString();
                                });
                            }
                        } catch (e) {
                            console.warn('載入協調字體失敗', e);
                        }
                    };
                }

                // Footer 儲存
                const btnSaveFooter = document.getElementById('btnSaveFooter');
                if (btnSaveFooter && footerQuill) {
                    btnSaveFooter.onclick = async () => {
                        try {
                            const html = footerQuill.root.innerHTML;
                            await apiJson(`${API_URL}?action=footer_set`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                                body: JSON.stringify({ html })
                            });
                            showMsg('頁尾已儲存', 'ok');
                        } catch (e) {
                            showMsg(e.message || '儲存失敗', 'err');
                        }
                    };
                }

                // WhatsApp 設定
                const whatsappNumberEl = document.getElementById('whatsappNumber');
                const btnSaveWhatsApp = document.getElementById('btnSaveWhatsApp');
                if (whatsappNumberEl && btnSaveWhatsApp) {
                    (async () => {
                        try {
                            const data = await apiJson(`${API_URL}?action=whatsapp_get`, {
                                headers: { 'Accept': 'application/json' }
                            });
                            if (data && data.ok && data.whatsapp) {
                                whatsappNumberEl.value = data.whatsapp;
                            }
                        } catch (e) {
                            console.warn('載入 WhatsApp 設定失敗:', e);
                        }
                    })();
                    
                    btnSaveWhatsApp.onclick = async () => {
                        try {
                            await apiJson(`${API_URL}?action=whatsapp_set`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                                body: JSON.stringify({ number: whatsappNumberEl.value })
                            });
                            showMsg('WhatsApp 設定已儲存', 'ok');
                        } catch (e) {
                            showMsg(e.message || '儲存失敗', 'err');
                        }
                    };
                }

                // Checkout 頁面儲存
                const btnSaveCheckout = document.getElementById('btnSaveCheckout');
                if (btnSaveCheckout && checkoutQuill) {
                    btnSaveCheckout.onclick = async () => {
                        try {
                            const html = checkoutQuill.root.innerHTML;
                            await apiJson(`${API_URL}?action=checkout_page_set`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                                body: JSON.stringify({ html })
                            });
                            showMsg('結算畫面已儲存', 'ok');
                        } catch (e) {
                            showMsg(e.message || '儲存失敗', 'err');
                        }
                    };
                }

                // 貨幣設定
                const currencySelect = document.getElementById('currencySelect');
                // ========== 語言和翻譯系統 ==========
                let languagesData = {};
                let translationsData = {};
                
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
                
                async function loadTranslations() {
                    try {
                        const data = await apiJson(`${API_URL}?action=languages_get`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (data && data.ok) {
                            languagesData = data.languages || {};
                            // 如果沒有語言數據，初始化預設語言
                            if (Object.keys(languagesData).length === 0) {
                                languagesData = {
                                    'zh-TW': { name: '繁體中文', native: true }
                                };
                            }
                            const defaultLang = data.default_language || 'zh-TW';
                            
                            // 確保所有語言的 native 屬性都是明確的布林值
                            Object.keys(languagesData).forEach(lang => {
                                if (languagesData[lang]) {
                                    // 如果 native 屬性不存在或不是布林值，根據預設語言判斷
                                    if (typeof languagesData[lang].native !== 'boolean') {
                                        languagesData[lang].native = (lang === defaultLang);
                                    }
                                }
                            });
                            const defaultLanguageSelect = document.getElementById('defaultLanguage');
                            if (defaultLanguageSelect) {
                                defaultLanguageSelect.value = defaultLang;
                                
                                // 綁定預設語言變更事件（確保預設語言在支援的語言清單中）
                                if (!defaultLanguageSelect.hasAttribute('data-bound')) {
                                    defaultLanguageSelect.addEventListener('change', () => {
                                        const newDefaultLang = defaultLanguageSelect.value;
                                        // 確保預設語言在支援的語言清單中
                                        if (newDefaultLang && !languagesData[newDefaultLang]) {
                                            languagesData[newDefaultLang] = {
                                                name: langNames[newDefaultLang] || newDefaultLang,
                                                native: true
                                            };
                                            renderLanguagesList();
                                            updateTranslationLangSelect();
                                            
                                            // 標記為已修改
                                            const saveBtn = document.getElementById('btnSaveTranslations');
                                            if (saveBtn && !saveBtn.classList.contains('btn-modified')) {
                                                saveBtn.classList.add('btn-modified');
                                                saveBtn.style.background = 'var(--warning, #f39c12)';
                                                saveBtn.textContent = '儲存語言設定（有未保存的變更）';
                                            }
                                        } else if (newDefaultLang && languagesData[newDefaultLang]) {
                                            // 如果預設語言已存在，確保它標記為原生語言
                                            languagesData[newDefaultLang].native = true;
                                            renderLanguagesList();
                                            
                                            // 標記為已修改
                                            const saveBtn = document.getElementById('btnSaveTranslations');
                                            if (saveBtn && !saveBtn.classList.contains('btn-modified')) {
                                                saveBtn.classList.add('btn-modified');
                                                saveBtn.style.background = 'var(--warning, #f39c12)';
                                                saveBtn.textContent = '儲存語言設定（有未保存的變更）';
                                            }
                                        }
                                    });
                                    defaultLanguageSelect.setAttribute('data-bound', 'true');
                                }
                            }
                            renderLanguagesList();
                        }
                        
                        const transData = await apiJson(`${API_URL}?action=translations_get`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (transData && transData.ok) {
                            translationsData = transData.translations || {};
                            
                            // 過濾掉原生語言的翻譯數據（確保同步）
                            Object.keys(translationsData).forEach(lang => {
                                if (languagesData[lang] && languagesData[lang].native) {
                                    delete translationsData[lang];
                                }
                            });
                            
                            updateTranslationLangSelect();
                        }
                    } catch (e) {
                        console.warn('載入語言設定失敗:', e);
                        // 如果載入失敗，初始化預設數據
                        if (Object.keys(languagesData).length === 0) {
                            languagesData = {
                                'zh-TW': { name: '繁體中文', native: true }
                            };
                            renderLanguagesList();
                        }
                    }
                }
                
                // 確保在頁面載入時初始化語言數據
                if (Object.keys(languagesData).length === 0) {
                    languagesData = {
                        'zh-TW': { name: '繁體中文', native: true }
                    };
                }
                
                function renderLanguagesList() {
                    const list = document.getElementById('languagesList');
                    if (!list) return;
                    
                    list.innerHTML = '';
                    
                    // 計算原生語言數量
                    const nativeCount = Object.keys(languagesData).filter(lang => languagesData[lang].native).length;
                    const canToggleNative = nativeCount > 1; // 如果有多於一個原生語言，允許切換
                    
                    Object.keys(languagesData).forEach(lang => {
                        const langInfo = languagesData[lang];
                        const div = document.createElement('div');
                        div.style.cssText = 'display: flex; align-items: center; gap: 12px; padding: 8px; background: var(--input-bg, #f5f5f5); border-radius: 6px; margin-bottom: 8px;';
                        
                        // 如果有多於一個原生語言，顯示切換按鈕；否則只顯示文字
                        const nativeButton = canToggleNative ? 
                            `<button class="btn" type="button" onclick="toggleLanguageNative('${lang}')" style="padding: 4px 12px; ${langInfo.native ? 'background: var(--ok, #27ae60); color: white;' : 'background: var(--muted, #95a5a6); color: white;'}">${langInfo.native ? '原生' : '翻譯'}</button>` :
                            `<span style="color: var(--muted); font-size: 12px;">${langInfo.native ? '原生' : '翻譯'}</span>`;
                        
                        div.innerHTML = `
                            <span style="flex: 1;">${langNames[lang] || lang} (${lang})</span>
                            ${nativeButton}
                            <button class="btn" type="button" onclick="removeLanguage('${lang}')" style="padding: 4px 12px;">移除</button>
                        `;
                        list.appendChild(div);
                    });
                }
                
                // 切換語言的「原生/翻譯」狀態
                window.toggleLanguageNative = function(lang) {
                    if (!languagesData[lang]) return;
                    
                    // 計算當前原生語言數量
                    const currentNativeCount = Object.keys(languagesData).filter(l => languagesData[l].native).length;
                    
                    // 如果要切換為原生，且當前只有一個原生語言，需要確認
                    if (!languagesData[lang].native && currentNativeCount === 1) {
                        if (!confirm(`確定要將「${langNames[lang] || lang}」設為原生語言嗎？\n\n這會使系統擁有多個原生語言。\n\n注意：切換為原生語言後，該語言的翻譯覆蓋數據將被清除。`)) {
                        return;
                    }
                    }
                    
                    // 如果切換為原生語言，清除該語言的翻譯數據
                    if (!languagesData[lang].native) {
                        if (translationsData[lang]) {
                            delete translationsData[lang];
                        }
                    }
                    
                    // 切換狀態
                    languagesData[lang].native = !languagesData[lang].native;
                    
                    // 重新渲染列表
                    renderLanguagesList();
                    updateTranslationLangSelect();
                    
                    // 如果切換為翻譯語言，清空當前選擇的翻譯語言（如果剛切換的語言被選中）
                    const translationLangSelect = document.getElementById('translationLang');
                    if (translationLangSelect && translationLangSelect.value === lang && languagesData[lang].native) {
                        translationLangSelect.value = '';
                        const translationsList = document.getElementById('translationsList');
                        if (translationsList) translationsList.innerHTML = '';
                    }
                    
                    // 標記為有未保存的變更
                    const saveBtn = document.getElementById('btnSaveTranslations');
                    if (saveBtn) {
                        saveBtn.textContent = '儲存語言設定（有未保存的變更）';
                        saveBtn.style.background = 'var(--warning, #f39c12)';
                    }
                }
                
                let translationLangSelectBound = false;
                
                function updateTranslationLangSelect() {
                    const select = document.getElementById('translationLang');
                    if (!select) return;
                    
                    // 保存當前選擇的值（如果存在且仍然是有效的非原生語言）
                    const currentValue = select.value;
                    
                    select.innerHTML = '<option value="">選擇語言</option>';
                    
                    // 只顯示非原生語言（嚴格檢查 native 屬性）
                    Object.keys(languagesData).forEach(lang => {
                        const langInfo = languagesData[lang];
                        // 嚴格檢查：只有明確標記為非原生（native === false）的語言才顯示
                        // 如果 native 是 undefined 或 true，都不應該顯示在翻譯語言選擇器中
                        if (langInfo && langInfo.native === false) {
                            const option = document.createElement('option');
                            option.value = lang;
                            option.textContent = `${langNames[lang] || lang} (${lang})`;
                            // 如果之前選擇的語言仍然有效，恢復選擇
                            if (currentValue === lang) {
                                option.selected = true;
                            }
                            select.appendChild(option);
                        }
                    });
                    
                    // 如果之前選擇的語言現在是原生語言或不存在，清空選擇並清空翻譯列表
                    if (currentValue && (!languagesData[currentValue] || languagesData[currentValue].native !== false)) {
                        select.value = '';
                        const list = document.getElementById('translationsList');
                        if (list) list.innerHTML = '';
                    }
                    
                    if (!translationLangSelectBound) {
                        select.addEventListener('change', () => {
                            if (select.value) {
                                renderTranslationsList(select.value);
                            } else {
                                const list = document.getElementById('translationsList');
                                if (list) list.innerHTML = '';
                            }
                        });
                        translationLangSelectBound = true;
                    }
                }
                
                function renderTranslationsList(lang) {
                    const list = document.getElementById('translationsList');
                    if (!list) return;
                    
                    list.innerHTML = '';
                    // 確保 translationsData 是對象而不是數組
                    if (Array.isArray(translationsData)) {
                        translationsData = {};
                    }
                    const translations = translationsData[lang] || {};
                    
                    if (Object.keys(translations).length === 0) {
                        list.innerHTML = '<p style="color: var(--muted);">尚未配置措辭字串。點擊「新增措辭字串」按鈕來添加。</p>';
                        return;
                    }
                    
                    Object.keys(translations).forEach(translatedText => {
                        const div = document.createElement('div');
                        div.style.cssText = 'margin-bottom: 16px; padding: 16px; background: var(--input-bg, #f5f5f5); border-radius: 8px; border: 1px solid var(--border, rgba(0,0,0,0.1));';
                        
                        const keySafe = escapeHtml(translatedText);
                        const keyId = `translation-key-${lang}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
                        
                        div.innerHTML = `
                            <div style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                                <div style="flex: 1;">
                                    <div style="font-size: 12px; color: var(--muted); margin-bottom: 4px;">翻譯後的文本（Key）</div>
                                    <div style="font-weight: 600; color: var(--text); padding: 8px; background: white; border-radius: 4px; border: 1px solid var(--border, rgba(0,0,0,0.1)); word-break: break-word;">${keySafe}</div>
                                </div>
                                <button class="btn btn-remove-translation" type="button" data-lang="${lang}" data-key="${keySafe.replace(/"/g, '&quot;').replace(/'/g, '&#39;')}" style="padding: 6px 12px; background: var(--danger, #e74c3c); color: white; margin-left: 12px; flex-shrink: 0;">刪除</button>
                            </div>
                            <div style="margin-bottom: 8px;">
                                <label for="translation-value-${lang}-${escapeHtml(translatedText).replace(/[^a-zA-Z0-9]/g, '-')}" style="display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px;">修正後的措辭（Value）</label>
                                <textarea id="translation-value-${lang}-${escapeHtml(translatedText).replace(/[^a-zA-Z0-9]/g, '-')}" name="translation[${lang}][${escapeHtml(translatedText)}]" class="field translation-textarea" style="width: 100%; min-height: 80px; font-size: 14px;" data-lang="${lang}" data-key="${translatedText.replace(/"/g, '&quot;').replace(/'/g, '&#39;')}">${escapeHtml(translations[translatedText])}</textarea>
                            </div>
                        `;
                        list.appendChild(div);
                        
                        // 綁定刪除按鈕事件
                        const removeBtn = div.querySelector('.btn-remove-translation');
                        if (removeBtn) {
                            removeBtn.addEventListener('click', function() {
                                const btnLang = this.getAttribute('data-lang');
                                const btnKey = this.getAttribute('data-key');
                                if (btnLang && btnKey) {
                                    removeTranslation(btnLang, btnKey);
                                }
                            });
                        }
                    });
                    
                    list.querySelectorAll('.translation-textarea').forEach(textarea => {
                        textarea.addEventListener('blur', function() {
                            const lang = this.getAttribute('data-lang');
                            let key = this.getAttribute('data-key');
                            const value = this.value.trim();
                            if (lang && key) {
                                // 解碼 HTML 實體以匹配原始 key
                                key = key.replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&amp;/g, '&');
                                if (!translationsData[lang]) translationsData[lang] = {};
                                if (value) {
                                    translationsData[lang][key] = value;
                                } else {
                                    delete translationsData[lang][key];
                                }
                                const saveBtn = document.getElementById('btnSaveTranslations');
                                if (saveBtn && !saveBtn.classList.contains('btn-modified')) {
                                    saveBtn.classList.add('btn-modified');
                                    saveBtn.style.background = 'var(--warning, #f39c12)';
                                    saveBtn.textContent = '儲存語言設定（有未保存的變更）';
                                }
                            }
                        });
                    });
                }
                
                window.removeLanguage = function(lang) {
                    if (!confirm(`確定要移除語言「${lang}」嗎？`)) return;
                    delete languagesData[lang];
                    renderLanguagesList();
                    updateTranslationLangSelect();
                };
                
                window.removeTranslation = function(lang, key) {
                    if (!confirm('確定要刪除此措辭字串嗎？')) return;
                    if (!translationsData[lang]) translationsData[lang] = {};
                    // 解碼 HTML 實體以匹配原始 key
                    const decodedKey = key.replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&amp;/g, '&');
                    delete translationsData[lang][decodedKey];
                    renderTranslationsList(lang);
                    
                    // 標記為已修改
                    const saveBtn = document.getElementById('btnSaveTranslations');
                    if (saveBtn && !saveBtn.classList.contains('btn-modified')) {
                        saveBtn.classList.add('btn-modified');
                        saveBtn.style.background = 'var(--warning, #f39c12)';
                        saveBtn.textContent = '儲存語言設定（有未保存的變更）';
                    }
                };
                
                const btnAddTranslation = document.getElementById('btnAddTranslation');
                if (btnAddTranslation) {
                    btnAddTranslation.onclick = () => {
                        const langSelect = document.getElementById('translationLang');
                        if (!langSelect || !langSelect.value) {
                            showMsg('請先選擇語言', 'err');
                            return;
                        }
                        
                        const lang = langSelect.value;
                        
                        const modal = document.createElement('div');
                        modal.className = 'modal-overlay';
                        modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center;';
                        
                        const modalContent = document.createElement('div');
                        modalContent.style.cssText = 'background: var(--bg, #fff); padding: 24px; border-radius: 12px; max-width: 500px; width: 90%;';
                        
                        const title = document.createElement('h3');
                        title.textContent = '新增措辭字串';
                        title.style.cssText = 'margin: 0 0 20px;';
                        
                        const form = document.createElement('div');
                        form.style.cssText = 'display: flex; flex-direction: column; gap: 16px;';
                        
                        const keyLabel = document.createElement('label');
                        keyLabel.textContent = '翻譯後的文本（Key）';
                        keyLabel.style.cssText = 'font-weight: 600;';
                        
                        const keyInput = document.createElement('input');
                        keyInput.type = 'text';
                        keyInput.placeholder = '例如：Home（系統自動翻譯的結果）';
                        keyInput.style.cssText = 'width: 100%; padding: 8px; border: 1px solid var(--border, rgba(0,0,0,0.1)); border-radius: 6px;';
                        
                        const valueLabel = document.createElement('label');
                        valueLabel.textContent = '修正後的措辭（Value）';
                        valueLabel.style.cssText = 'font-weight: 600;';
                        
                        const valueInput = document.createElement('textarea');
                        valueInput.placeholder = '例如：Homepage（您想要顯示的修正措辭）';
                        valueInput.style.cssText = 'width: 100%; min-height: 80px; padding: 8px; border: 1px solid var(--border, rgba(0,0,0,0.1)); border-radius: 6px; resize: vertical;';
                        
                        const btnGroup = document.createElement('div');
                        btnGroup.style.cssText = 'display: flex; gap: 8px; justify-content: flex-end; margin-top: 8px;';
                        
                        const cancelBtn = document.createElement('button');
                        cancelBtn.className = 'btn';
                        cancelBtn.textContent = '取消';
                        cancelBtn.onclick = () => document.body.removeChild(modal);
                        
                        const saveBtn = document.createElement('button');
                        saveBtn.className = 'btn btn-ok';
                        saveBtn.textContent = '儲存';
                        saveBtn.onclick = () => {
                            const key = keyInput.value.trim();
                            const value = valueInput.value.trim();
                            
                            if (!key) {
                                showMsg('請輸入翻譯後的文本', 'err');
                                return;
                            }
                            
                            if (!value) {
                                showMsg('請輸入修正後的措辭', 'err');
                                return;
                            }
                            
                            if (!translationsData[lang]) translationsData[lang] = {};
                            
                            if (translationsData[lang][key]) {
                                if (!confirm('該措辭字串已存在，是否要覆蓋？')) {
                                    return;
                                }
                            }
                            
                            translationsData[lang][key] = value;
                            renderTranslationsList(lang);
                            document.body.removeChild(modal);
                            showMsg('措辭字串已新增', 'ok');
                        };
                        
                        form.appendChild(keyLabel);
                        form.appendChild(keyInput);
                        form.appendChild(valueLabel);
                        form.appendChild(valueInput);
                        btnGroup.appendChild(cancelBtn);
                        btnGroup.appendChild(saveBtn);
                        modalContent.appendChild(title);
                        modalContent.appendChild(form);
                        modalContent.appendChild(btnGroup);
                        modal.appendChild(modalContent);
                        document.body.appendChild(modal);
                        
                        keyInput.focus();
                        
                        modal.onclick = (e) => {
                            if (e.target === modal) {
                                document.body.removeChild(modal);
                            }
                        };
                    };
                }
                
                const btnAddLanguage = document.getElementById('btnAddLanguage');
                if (btnAddLanguage) {
                    btnAddLanguage.onclick = () => {
                        const lang = prompt('請輸入語言代碼（例如：en, ja, ko）：');
                        if (lang && lang.trim()) {
                            const langCode = lang.trim();
                            if (!languagesData[langCode]) {
                                languagesData[langCode] = { name: langCode, native: false };
                                renderLanguagesList();
                                updateTranslationLangSelect();
                            } else {
                                showMsg('該語言已存在', 'err');
                            }
                        }
                    };
                }
                
                const btnSaveTranslations = document.getElementById('btnSaveTranslations');
                if (btnSaveTranslations) {
                    btnSaveTranslations.onclick = async () => {
                        try {
                            const defaultLang = document.getElementById('defaultLanguage').value;
                            
                            // 確保所有 textarea 的變更都已保存到 translationsData
                            document.querySelectorAll('.translation-textarea').forEach(textarea => {
                                const lang = textarea.getAttribute('data-lang');
                                let key = textarea.getAttribute('data-key');
                                const value = textarea.value.trim();
                                
                                if (lang && key) {
                                    // 解碼 HTML 實體以匹配原始 key
                                    key = key.replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&amp;/g, '&');
                                    if (!translationsData[lang]) translationsData[lang] = {};
                                    if (value) {
                                        translationsData[lang][key] = value;
                                    } else {
                                        delete translationsData[lang][key];
                                    }
                                }
                            });
                            
                            // 清理空的語言對象
                            Object.keys(translationsData).forEach(lang => {
                                if (Object.keys(translationsData[lang] || {}).length === 0) {
                                    delete translationsData[lang];
                                }
                            });
                            
                            // 確保預設語言在支援的語言清單中
                            if (defaultLang && !languagesData[defaultLang]) {
                                languagesData[defaultLang] = {
                                    name: langNames[defaultLang] || defaultLang,
                                    native: true
                                };
                            } else if (defaultLang && languagesData[defaultLang]) {
                                // 確保預設語言標記為原生語言
                                languagesData[defaultLang].native = true;
                            }
                            
                            // 重新渲染語言列表以反映變更
                            renderLanguagesList();
                            updateTranslationLangSelect();
                            
                            // 保存語言更新
                            await apiJson(`${API_URL}?action=languages_set`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                                body: JSON.stringify({
                                    languages: languagesData,
                                    default_language: defaultLang
                                })
                            });
                            
                            // 過濾掉原生語言的翻譯數據（原生語言不應該有翻譯覆蓋）
                            const filteredTranslations = {};
                            Object.keys(translationsData).forEach(lang => {
                                // 只保存非原生語言的翻譯數據
                                if (languagesData[lang] && !languagesData[lang].native) {
                                    filteredTranslations[lang] = translationsData[lang];
                                }
                            });
                            
                            // 保存翻譯更新（只保存非原生語言）
                            await apiJson(`${API_URL}?action=translations_set`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                                body: JSON.stringify({ translations: filteredTranslations })
                            });
                            
                            // 同步更新本地 translationsData（移除原生語言的數據）
                            Object.keys(languagesData).forEach(lang => {
                                if (languagesData[lang].native && translationsData[lang]) {
                                    delete translationsData[lang];
                                }
                            });
                            
                            showMsg('語言設定已儲存', 'ok');
                            
                            btnSaveTranslations.classList.remove('btn-modified');
                            btnSaveTranslations.style.background = '';
                            btnSaveTranslations.textContent = '儲存語言設定';
                        } catch (e) {
                            showMsg(e.message || '儲存失敗', 'err');
                        }
                    };
                }
                
                const btnSaveCurrency = document.getElementById('btnSaveCurrency');
                if (currencySelect && btnSaveCurrency) {
                    (async () => {
                        try {
                            const data = await apiJson(`${API_URL}?action=currency_get`, {
                                headers: { 'Accept': 'application/json' }
                            });
                            if (data && data.ok && data.currency) {
                                currencySelect.value = data.currency;
                            }
                        } catch (e) {
                            console.warn('載入貨幣設定失敗:', e);
                        }
                    })();
                    
                    btnSaveCurrency.onclick = async () => {
                        try {
                            await apiJson(`${API_URL}?action=currency_set`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                                body: JSON.stringify({ currency: currencySelect.value })
                            });
                            showMsg('貨幣設定已儲存', 'ok');
                        } catch (e) {
                            showMsg(e.message || '儲存失敗', 'err');
                        }
                    };
                }

                // 維護模式設定
                const maintenanceMode = document.getElementById('maintenanceMode');
                const btnSaveMaintenance = document.getElementById('btnSaveMaintenance');
                if (maintenanceMode && btnSaveMaintenance) {
                    (async () => {
                        try {
                            const data = await apiJson(`${API_URL}?action=maintenance_get`, {
                                headers: { 'Accept': 'application/json' }
                            });
                            if (data && data.ok && typeof data.maintenance_mode === 'boolean') {
                                maintenanceMode.checked = data.maintenance_mode;
                            }
                        } catch (e) {
                            console.warn('載入維護模式設定失敗:', e);
                        }
                    })();
                    
                    btnSaveMaintenance.onclick = async () => {
                        try {
                            await apiJson(`${API_URL}?action=maintenance_set`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                                body: JSON.stringify({ enabled: maintenanceMode.checked })
                            });
                            showMsg('維護模式設定已儲存', 'ok');
                        } catch (e) {
                            showMsg(e.message || '儲存失敗', 'err');
                        }
                    };
                }

                // 初始載入：先抓 pages 並渲染樹，再載入 home
                refreshListAndSelect('home').catch(() => {});

                // ========== 訂單系統 ==========
                let allOrders = [];
                let currentPage = 1;
                let ordersPerPage = 10; // 每頁顯示的訂單數量
                
                // 延遲獲取元素，確保 DOM 已完全載入
                function getOrdersElements() {
                    return {
                        ordersList: document.getElementById('ordersList'),
                        ordersSearch: document.getElementById('ordersSearch'),
                        ordersStatusFilter: document.getElementById('ordersStatusFilter'),
                        ordersRefresh: document.getElementById('ordersRefresh'),
                        ordersStats: document.getElementById('ordersStats'),
                        ordersPagination: document.getElementById('ordersPagination')
                    };
                }
                
                let ordersElements = getOrdersElements();

                const statusConfig = {
                    'pending': { label: '待處理', color: '#f39c12', icon: '⏳' },
                    'processing': { label: '處理中', color: '#3498db', icon: '⚙️' },
                    'shipped': { label: '已出貨', color: '#9b59b6', icon: '🚚' },
                    'completed': { label: '已完成', color: '#27ae60', icon: '✅' },
                    'cancelled': { label: '已取消', color: '#e74c3c', icon: '❌' }
                };

                async function loadOrders() {
                    // 重新獲取元素，確保它們存在
                    ordersElements = getOrdersElements();
                    const { ordersList, ordersStats } = ordersElements;
                    
                    if (!ordersList) {
                        console.error('[訂單] ordersList 元素未找到');
                        return;
                    }
                    
                    // 顯示載入中狀態
                    ordersList.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: var(--muted);">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity: 0.3; margin: 0 auto 16px;">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="8.5" cy="7" r="4"></circle>
                                <path d="M20 8v6M23 11h-6"></path>
                            </svg>
                            <p>載入中...</p>
                        </div>
                    `;
                    
                    try {
                        const res = await fetch(`${API_URL}?action=orders`, {
                            headers: { 'X-CSRF-Token': CSRF_TOKEN }
                        });
                        const data = await res.json();
                        console.log('[訂單] API 響應:', data);
                        
                        if (data && data.ok) {
                            allOrders = data.orders || [];
                            console.log('[訂單] 載入訂單數量:', allOrders.length);
                            renderOrdersStats();
                            renderOrdersList();
                        } else {
                            const errorMsg = data?.error || '載入訂單失敗';
                            console.error('[訂單] 錯誤:', errorMsg);
                            showMsg(errorMsg, 'err');
                            ordersList.innerHTML = `
                                <div style="text-align: center; padding: 40px; color: var(--muted);">
                                    <p style="color: var(--error, #e74c3c);">${errorMsg}</p>
                                </div>
                            `;
                        }
                    } catch (e) {
                        console.error('[訂單] 異常:', e);
                        showMsg('載入訂單失敗：' + e.message, 'err');
                        ordersList.innerHTML = `
                            <div style="text-align: center; padding: 40px; color: var(--muted);">
                                <p style="color: var(--error, #e74c3c);">載入失敗：${e.message}</p>
                            </div>
                        `;
                    }
                }

                function renderOrdersStats() {
                    ordersElements = getOrdersElements();
                    const { ordersStats } = ordersElements;
                    if (!ordersStats) {
                        console.error('[訂單] ordersStats 元素未找到');
                        console.error('[訂單] 當前 ordersElements:', ordersElements);
                        return;
                    }
                    console.log('[訂單] 渲染統計，訂單數量:', allOrders.length);
                    const stats = {
                        total: allOrders.length,
                        pending: allOrders.filter(o => o.status === 'pending').length,
                        processing: allOrders.filter(o => o.status === 'processing').length,
                        shipped: allOrders.filter(o => o.status === 'shipped').length,
                        completed: allOrders.filter(o => o.status === 'completed').length,
                        cancelled: allOrders.filter(o => o.status === 'cancelled').length
                    };

                    const totalAmount = allOrders.reduce((sum, o) => sum + (parseFloat(o.total) || 0), 0);

                    ordersStats.innerHTML = `
                        <div style="padding: 16px; background: rgba(124,92,255,0.1); border: 1px solid rgba(124,92,255,0.2); border-radius: 12px;">
                            <div style="font-size: 13px; color: var(--muted); margin-bottom: 4px;">總訂單數</div>
                            <div style="font-size: 24px; font-weight: 700; background: linear-gradient(135deg, var(--accent), var(--accent2)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">${stats.total}</div>
                        </div>
                        <div style="padding: 16px; background: rgba(46,204,113,0.1); border: 1px solid rgba(46,204,113,0.2); border-radius: 12px;">
                            <div style="font-size: 13px; color: var(--muted); margin-bottom: 4px;">總金額</div>
                            <div style="font-size: 24px; font-weight: 700; color: #27ae60;">HK$${totalAmount.toFixed(0)}</div>
                        </div>
                        <div style="padding: 16px; background: rgba(243,156,18,0.1); border: 1px solid rgba(243,156,18,0.2); border-radius: 12px;">
                            <div style="font-size: 13px; color: var(--muted); margin-bottom: 4px;">待處理</div>
                            <div style="font-size: 24px; font-weight: 700; color: #f39c12;">${stats.pending}</div>
                        </div>
                        <div style="padding: 16px; background: rgba(52,152,219,0.1); border: 1px solid rgba(52,152,219,0.2); border-radius: 12px;">
                            <div style="font-size: 13px; color: var(--muted); margin-bottom: 4px;">處理中</div>
                            <div style="font-size: 24px; font-weight: 700; color: #3498db;">${stats.processing}</div>
                        </div>
                    `;
                }

                function renderOrdersList() {
                    ordersElements = getOrdersElements();
                    const { ordersList, ordersSearch, ordersStatusFilter } = ordersElements;
                    if (!ordersList) {
                        console.error('[訂單] ordersList 元素未找到');
                        console.error('[訂單] 當前 ordersElements:', ordersElements);
                        return;
                    }
                    console.log('[訂單] 渲染訂單列表，訂單數量:', allOrders.length);
                    const searchTerm = (ordersSearch?.value || '').toLowerCase();
                    const statusFilter = ordersStatusFilter?.value || '';

                    let filtered = allOrders.filter(order => {
                        if (searchTerm) {
                            const searchable = [
                                order.id || '',
                                order.customer?.name || '',
                                order.customer?.phone || '',
                                order.customer?.email || ''
                            ].join(' ').toLowerCase();
                            if (!searchable.includes(searchTerm)) return false;
                        }
                        if (statusFilter && order.status !== statusFilter) return false;
                        return true;
                    });

                    if (filtered.length === 0) {
                        ordersList.innerHTML = `
                            <div style="text-align: center; padding: 60px 20px; color: var(--muted);">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity: 0.3; margin: 0 auto 16px;">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <path d="M20 8v6M23 11h-6"></path>
                                </svg>
                                <p style="margin: 0; font-size: 16px;">沒有找到訂單</p>
                            </div>
                        `;
                        // 清空分頁
                        if (ordersElements.ordersPagination) {
                            ordersElements.ordersPagination.innerHTML = '';
                        }
                        return;
                    }

                    // 計算分頁
                    const totalPages = Math.ceil(filtered.length / ordersPerPage);
                    const startIndex = (currentPage - 1) * ordersPerPage;
                    const endIndex = startIndex + ordersPerPage;
                    const paginatedOrders = filtered.slice(startIndex, endIndex);

                    ordersList.innerHTML = paginatedOrders.map(order => {
                        const status = statusConfig[order.status] || statusConfig.pending;
                        const createdDate = new Date(order.created_at || Date.now());
                        const dateStr = createdDate.toLocaleString('zh-TW', { 
                            year: 'numeric', 
                            month: '2-digit', 
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit'
                        });

                        return `
                            <div class="order-card" data-order-id="${order.id}" style="padding: 20px; background: var(--bg); border: 1px solid var(--border); border-radius: 12px; transition: all 0.2s; cursor: pointer;" 
                                 onmouseover="this.style.borderColor='var(--accent)'; this.style.boxShadow='0 4px 12px rgba(124,92,255,0.15)';"
                                 onmouseout="this.style.borderColor='var(--border)'; this.style.boxShadow='none';"
                                 onclick="showOrderDetail('${order.id}')">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                    <div style="flex: 1;">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <span style="font-family: monospace; font-size: 16px; font-weight: 700; color: var(--text);">${order.id}</span>
                                            <span style="padding: 4px 12px; background: ${status.color}20; color: ${status.color}; border-radius: 6px; font-size: 12px; font-weight: 600;">
                                                ${status.icon} ${status.label}
                                            </span>
                                        </div>
                                        <div style="color: var(--muted); font-size: 13px; margin-bottom: 4px;">
                                            <strong>${order.customer?.name || '未提供'}</strong> · ${order.customer?.phone || '未提供'}
                                        </div>
                                        <div style="color: var(--muted); font-size: 12px;">${dateStr}</div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 20px; font-weight: 700; background: linear-gradient(135deg, var(--accent), var(--accent2)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 4px;">
                                            HK$${parseFloat(order.total || 0).toFixed(0)}
                                        </div>
                                        <div style="color: var(--muted); font-size: 12px;">${(order.items || []).length} 件商品</div>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);">
                                    ${(order.items || []).slice(0, 3).map(item => `
                                        <span style="padding: 4px 8px; background: rgba(124,92,255,0.1); border-radius: 6px; font-size: 12px; color: var(--text);">
                                            ${(item.menu_title || item.title || item.path).replace(/'/g, "\\'")} × ${item.quantity}
                                        </span>
                                    `).join('')}
                                    ${(order.items || []).length > 3 ? `<span style="padding: 4px 8px; color: var(--muted); font-size: 12px;">+${(order.items || []).length - 3} 更多</span>` : ''}
                                </div>
                            </div>
                        `;
                    }).join('');

                    // 渲染分頁控件
                    renderOrdersPagination(filtered.length, totalPages);
                }

                function renderOrdersPagination(totalOrders, totalPages) {
                    ordersElements = getOrdersElements();
                    const { ordersPagination } = ordersElements;
                    if (!ordersPagination) {
                        console.warn('[訂單] ordersPagination 元素未找到');
                        return;
                    }

                    if (totalPages <= 1) {
                        ordersPagination.innerHTML = '';
                        return;
                    }

                    let paginationHTML = '<div style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 24px; padding: 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 12px;">';
                    
                    // 上一頁按鈕
                    paginationHTML += `
                        <button class="btn" type="button" onclick="goToOrdersPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>
                            ← 上一頁
                        </button>
                    `;

                    // 頁碼按鈕
                    const maxVisiblePages = 7;
                    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
                    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
                    
                    if (endPage - startPage < maxVisiblePages - 1) {
                        startPage = Math.max(1, endPage - maxVisiblePages + 1);
                    }

                    if (startPage > 1) {
                        paginationHTML += `<button class="btn" type="button" onclick="goToOrdersPage(1)">1</button>`;
                        if (startPage > 2) {
                            paginationHTML += `<span style="padding: 0 8px; color: var(--muted);">...</span>`;
                        }
                    }

                    for (let i = startPage; i <= endPage; i++) {
                        paginationHTML += `
                            <button class="btn" type="button" onclick="goToOrdersPage(${i})" 
                                style="${i === currentPage ? 'background: var(--accent); color: var(--bg); font-weight: 600;' : ''}">
                                ${i}
                            </button>
                        `;
                    }

                    if (endPage < totalPages) {
                        if (endPage < totalPages - 1) {
                            paginationHTML += `<span style="padding: 0 8px; color: var(--muted);">...</span>`;
                        }
                        paginationHTML += `<button class="btn" type="button" onclick="goToOrdersPage(${totalPages})">${totalPages}</button>`;
                    }

                    // 下一頁按鈕
                    paginationHTML += `
                        <button class="btn" type="button" onclick="goToOrdersPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>
                            下一頁 →
                        </button>
                    `;

                    // 顯示訂單範圍信息
                    const startOrder = totalOrders === 0 ? 0 : startIndex + 1;
                    const endOrder = Math.min(endIndex, totalOrders);
                    paginationHTML += `
                        <span style="margin-left: 16px; color: var(--muted); font-size: 13px;">
                            顯示 ${startOrder}-${endOrder} / 共 ${totalOrders} 筆訂單
                        </span>
                    `;

                    paginationHTML += '</div>';
                    ordersPagination.innerHTML = paginationHTML;
                }

                function goToOrdersPage(page) {
                    const totalFiltered = allOrders.filter(order => {
                        const searchTerm = (ordersElements.ordersSearch?.value || '').toLowerCase();
                        const statusFilter = ordersElements.ordersStatusFilter?.value || '';
                        
                        if (searchTerm) {
                            const searchable = [
                                order.id || '',
                                order.customer?.name || '',
                                order.customer?.phone || '',
                                order.customer?.email || ''
                            ].join(' ').toLowerCase();
                            if (!searchable.includes(searchTerm)) return false;
                        }
                        if (statusFilter && order.status !== statusFilter) return false;
                        return true;
                    });
                    
                    const totalPages = Math.ceil(totalFiltered.length / ordersPerPage);
                    if (page < 1 || page > totalPages) return;
                    
                    currentPage = page;
                    renderOrdersList();
                    
                    // 滾動到列表頂部
                    if (ordersElements.ordersList) {
                        ordersElements.ordersList.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }

                // 將函數暴露到全局作用域
                window.goToOrdersPage = goToOrdersPage;

                function showOrderDetail(orderId) {
                    const order = allOrders.find(o => o.id === orderId);
                    if (!order) return;

                    const status = statusConfig[order.status] || statusConfig.pending;
                    const createdDate = new Date(order.created_at || Date.now());
                    const dateStr = createdDate.toLocaleString('zh-TW');

                    let modal = document.getElementById('orderDetailModal');
                    if (!modal) {
                        modal = document.createElement('div');
                        modal.id = 'orderDetailModal';
                        modal.className = 'gwa-modal';
                        modal.style.display = 'none';
                        document.body.appendChild(modal);
                    }

                    modal.innerHTML = `
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>訂單詳情</h2>
                                <button class="modal-close" type="button">×</button>
                            </div>
                            <div class="modal-body">
                                <div id="orderDetailContent"></div>
                            </div>
                        </div>
                    `;

                    const content = modal.querySelector('#orderDetailContent');
                    content.innerHTML = `
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                            <div style="padding: 16px; background: rgba(124,92,255,0.1); border-radius: 12px;">
                                <div style="font-size: 13px; color: var(--muted); margin-bottom: 4px;">訂單編號</div>
                                <div style="font-family: monospace; font-size: 18px; font-weight: 700;">${order.id}</div>
                            </div>
                            <div style="padding: 16px; background: ${status.color}20; border-radius: 12px;">
                                <div style="font-size: 13px; color: var(--muted); margin-bottom: 4px;">訂單狀態</div>
                                <div style="font-size: 18px; font-weight: 700; color: ${status.color};">
                                    ${status.icon} ${status.label}
                                </div>
                            </div>
                            <div style="padding: 16px; background: rgba(46,204,113,0.1); border-radius: 12px;">
                                <div style="font-size: 13px; color: var(--muted); margin-bottom: 4px;">訂單總額</div>
                                <div style="font-size: 18px; font-weight: 700; color: #27ae60;">HK$${parseFloat(order.total || 0).toFixed(0)}</div>
                            </div>
                            <div style="padding: 16px; background: rgba(52,152,219,0.1); border-radius: 12px;">
                                <div style="font-size: 13px; color: var(--muted); margin-bottom: 4px;">建立時間</div>
                                <div style="font-size: 14px; font-weight: 600;">${dateStr}</div>
                            </div>
                        </div>

                        <div style="margin-bottom: 24px;">
                            <h3 style="margin: 0 0 16px; font-size: 18px;">客戶資訊</h3>
                            <div style="padding: 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 12px;">
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                                    <div>
                                        <div style="font-size: 12px; color: var(--muted); margin-bottom: 4px;">姓名</div>
                                        <div style="font-weight: 600;">${(order.customer?.name || '未提供').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: var(--muted); margin-bottom: 4px;">電話</div>
                                        <div>${(order.customer?.phone || '未提供').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: var(--muted); margin-bottom: 4px;">Email</div>
                                        <div>${(order.customer?.email || '未提供').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>
                                    </div>
                                    <div style="grid-column: 1 / -1;">
                                        <div style="font-size: 12px; color: var(--muted); margin-bottom: 4px;">地址</div>
                                        <div>${(order.customer?.address || '未提供').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>
                                    </div>
                                    ${order.customer?.note ? `
                                    <div style="grid-column: 1 / -1;">
                                        <div style="font-size: 12px; color: var(--muted); margin-bottom: 4px;">備註</div>
                                        <div>${order.customer.note.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>

                        <div style="margin-bottom: 24px;">
                            <h3 style="margin: 0 0 16px; font-size: 18px;">商品清單</h3>
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                ${(order.items || []).map(item => `
                                    <div style="padding: 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 12px; display: flex; justify-content: space-between; align-items: center;">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; margin-bottom: 4px;">${(item.menu_title || item.title || item.path).replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>
                                            <div style="font-size: 13px; color: var(--muted);">數量：${item.quantity} × HK$${parseFloat(item.price || 0).toFixed(0)}</div>
                                        </div>
                                        <div style="font-size: 18px; font-weight: 700; color: var(--accent);">
                                            HK$${(parseFloat(item.price || 0) * parseInt(item.quantity || 1)).toFixed(0)}
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>

                        <div style="margin-bottom: 24px;">
                            <h3 style="margin: 0 0 16px; font-size: 18px;">訂單操作</h3>
                            <div style="padding: 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 12px;">
                                <div style="margin-bottom: 12px;">
                                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">更新訂單狀態</label>
                                    <select id="orderStatusSelect" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--text);">
                                        ${Object.entries(statusConfig).map(([key, config]) => `
                                            <option value="${key}" ${order.status === key ? 'selected' : ''}>${config.icon} ${config.label}</option>
                                        `).join('')}
                                    </select>
                                </div>
                                <div style="margin-bottom: 12px;">
                                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">管理員備註</label>
                                    <textarea id="orderAdminNote" rows="3" placeholder="可選：添加管理員備註" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--text); resize: vertical;">${(order.admin_note || '').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</textarea>
                                </div>
                                <button class="btn btn-ok" id="btnUpdateOrderStatus" style="width: 100%; padding: 12px; font-weight: 600;">
                                    更新訂單狀態
                                </button>
                            </div>
                        </div>
                    `;

                    modal.style.display = 'flex';
                    
                    // 關閉按鈕事件
                    const closeBtn = modal.querySelector('.modal-close');
                    if (closeBtn) {
                        closeBtn.addEventListener('click', () => {
                            modal.style.display = 'none';
                        });
                    }
                    
                    // 點擊背景關閉
                    modal.addEventListener('click', (e) => {
                        if (e.target === modal) {
                            modal.style.display = 'none';
                        }
                    });
                    
                    // 更新訂單狀態按鈕事件
                    const updateBtn = modal.querySelector('#btnUpdateOrderStatus');
                    if (updateBtn) {
                        updateBtn.addEventListener('click', async () => {
                            await updateOrderStatus(order.id);
                        });
                    }
                }

                async function updateOrderStatus(orderId) {
                    const statusSelect = document.getElementById('orderStatusSelect');
                    const adminNote = document.getElementById('orderAdminNote');
                    
                    if (!statusSelect) {
                        console.error('[訂單] updateOrderStatus: statusSelect 元素未找到');
                        showMsg('無法找到狀態選擇器', 'err');
                        return;
                    }

                    const status = statusSelect.value;
                    const note = adminNote?.value?.trim() || null;
                    
                    if (!status) {
                        showMsg('請選擇訂單狀態', 'err');
                        return;
                    }

                    console.log('[訂單] 更新訂單狀態:', { orderId, status, note });

                    try {
                        const requestBody = {
                            order_id: orderId,
                            status: status,
                            admin_note: note
                        };
                        
                        console.log('[訂單] 發送請求:', requestBody);
                        
                        const res = await fetch(`${API_URL}?action=order_update`, {
                            method: 'POST',
                            headers: { 
                                'Content-Type': 'application/json', 
                                'X-CSRF-Token': CSRF_TOKEN 
                            },
                            body: JSON.stringify(requestBody)
                        });

                        console.log('[訂單] API 響應狀態:', res.status);
                        
                        if (!res.ok) {
                            const errorText = await res.text();
                            console.error('[訂單] API 錯誤響應:', errorText);
                            let errorData;
                            try {
                                errorData = JSON.parse(errorText);
                            } catch (e) {
                                errorData = { error: `HTTP ${res.status}: ${res.statusText}` };
                            }
                            throw new Error(errorData.error || `HTTP ${res.status}`);
                        }

                        const data = await res.json();
                        console.log('[訂單] API 響應數據:', data);
                        
                        if (data && data.ok) {
                            const message = data.message || '訂單狀態已更新';
                            showMsg(message, 'ok');
                            
                            // 關閉訂單詳情模態框
                            const modal = document.getElementById('orderDetailModal');
                            if (modal) {
                                modal.style.display = 'none';
                            }
                            
                            // 重新載入訂單列表
                            await loadOrders();
                        } else {
                            const errorMsg = data?.error || '未知錯誤';
                            console.error('[訂單] 更新失敗:', errorMsg);
                            showMsg('更新失敗：' + errorMsg, 'err');
                        }
                    } catch (e) {
                        console.error('[訂單] 更新異常:', e);
                        showMsg('更新失敗：' + (e.message || String(e)), 'err');
                    }
                }

                // 訂單事件監聽
                // 使用事件委派，因為元素可能在選項卡切換時才顯示
                document.addEventListener('input', (e) => {
                    if (e.target && e.target.id === 'ordersSearch') {
                        currentPage = 1; // 重置到第一頁
                        renderOrdersList();
                    }
                });
                document.addEventListener('change', (e) => {
                    if (e.target && e.target.id === 'ordersStatusFilter') {
                        currentPage = 1; // 重置到第一頁
                        renderOrdersList();
                    }
                });
                document.addEventListener('click', (e) => {
                    if (e.target && e.target.id === 'ordersRefresh') {
                        currentPage = 1; // 重置到第一頁
                        loadOrders();
                    }
                });

                // 將函數暴露到全局作用域
                window.showOrderDetail = showOrderDetail;
                window.updateOrderStatus = updateOrderStatus;
            </script>
        <?php endif; ?>
    </div>
</body>
</html>



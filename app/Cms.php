<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

final class Cms {
    private string $rootDir;
    private string $dataDir;
    private string $contentDir;
    private string $trashDir;
    private string $pagesFile;
    private string $trashFile;

    public function __construct(string $rootDir) {
        $this->rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->dataDir = $this->rootDir . 'data' . DIRECTORY_SEPARATOR;
        $this->contentDir = $this->rootDir . 'content' . DIRECTORY_SEPARATOR;
        $this->trashDir = $this->rootDir . 'trash' . DIRECTORY_SEPARATOR;
        $this->pagesFile = $this->dataDir . 'pages.json';
        $this->trashFile = $this->dataDir . 'trash.json';
        $this->ensureDirs();
        $this->ensureHomePage();
    }

    public function getPages(): array {
        $pages = gwa_read_json_file($this->pagesFile, []);
        if (!is_array($pages)) $pages = [];
        $pages = array_values(array_filter($pages, fn($p) => is_array($p) && isset($p['path'])));
        $this->ensureHomePage($pages);
        $pages = $this->normalizePages($pages);
        // 不再在讀取時自動修復一致性，避免覆蓋用戶拖動的 parent 設定
        return $pages;
    }

    public function getPage(string $path): ?array {
        $path = gwa_sanitize_path($path);
        foreach ($this->getPages() as $p) {
            if (($p['path'] ?? '') === $path) return $p;
        }
        return null;
    }

    public function savePage(array $page, string $html, ?string $oldPath = null): void {
        $oldPath = $oldPath === null ? null : trim((string)$oldPath);
        $oldPath = ($oldPath === null || $oldPath === '') ? null : gwa_sanitize_path($oldPath);

        $path = gwa_sanitize_path((string)($page['path'] ?? ''));
        $title = trim((string)($page['title'] ?? ''));
        $menuTitle = trim((string)($page['menu_title'] ?? ''));
        $type = isset($page['type']) ? trim((string)($page['type'] ?? 'page')) : 'page';
        $type = ($type === 'product' || $type === 'page') ? $type : 'page';
        $price = isset($page['price']) ? (float)($page['price'] ?? 0) : 0;
        $layoutFullWidth = isset($page['layout_full_width']) ? (bool)($page['layout_full_width'] ?? false) : false;
        $layoutBlockAlign = isset($page['layout_block_align']) ? trim((string)($page['layout_block_align'] ?? 'center')) : 'center';
        $hasParent = array_key_exists('parent', $page);
        $parent = $hasParent ? trim((string)($page['parent'] ?? '')) : '';
        $parent = $parent === '' ? '' : gwa_sanitize_path($parent);

        if ($title === '') {
            throw new InvalidArgumentException('缺少 title');
        }

        // home 特例：不允許 parent
        if ($path === 'home') {
            $parent = '';
            $hasParent = true;
        }
        
        $pages = $this->getPages();
        
        // 確保 parent 和 path 的一致性
        // 如果用戶明確設置了 parent（hasParent = true），則完全尊重用戶的設置
        // 只有在 parent 未設置（hasParent = false）或為空時，才從路徑推導 parent
        if ($path !== 'home' && strpos($path, '/') !== false) {
            $pathParent = substr($path, 0, strrpos($path, '/'));
            // 只有在沒有明確設置 parent，或者 parent 為空時，才從路徑推導
            if (!$hasParent || $parent === '') {
                $parent = $pathParent;
                $hasParent = true;
            }
            // 如果用戶明確設置了 parent，即使與路徑不一致，也完全尊重用戶的設置
            // 這樣允許用戶手動管理架構，即使路徑結構不完全匹配
            // 注意：如果 parent 與路徑不一致，buildTree 會使用 effectiveParent 動態推導
        }
        
        // 驗證 parent 是否存在（如果 parent 不為空且不是 home）
        if ($parent !== '' && $parent !== 'home') {
            $parentExists = false;
            foreach ($pages as $p0) {
                if (($p0['path'] ?? '') === $parent) {
                    $parentExists = true;
                    break;
                }
            }
            // 如果 parent 不存在，但用戶明確設置了，仍然允許（可能父層稍後會建立）
            // 但如果 parent 不存在且是從路徑推導的，則設為空（成為根節點）
            if (!$parentExists && !$hasParent) {
                $parent = '';
            }
        }

        // 防止覆蓋：如果是新頁或改名，且目標 path 已存在 -> 拒絕
        $exists = false;
        foreach ($pages as $p0) {
            if (($p0['path'] ?? '') === $path) { $exists = true; break; }
        }
        if ($exists && ($oldPath === null || $oldPath !== $path)) {
            throw new InvalidArgumentException('path 已存在，請換一個名稱或父層');
        }

        $oldToNew = null; // rename 時收集 [舊路徑 => 新路徑]，供批次更新內聯連結

        // rename：從 oldPath 移到新 path（避免留下孤兒檔案/資料）
        if ($oldPath !== null && $oldPath !== $path) {
            if ($oldPath === 'home' || $path === 'home') {
                throw new InvalidArgumentException('home 不允許改名或被覆蓋');
            }
            
            // 更新所有子層的 parent 引用和路徑（在移除舊 meta 之前）
            $pathUpdates = []; // 收集需要更新的路徑映射 [oldChildPath => newChildPath]
            foreach ($pages as &$p) {
                $pParent = $this->effectiveParent($p);
                $pPath = (string)($p['path'] ?? '');
                
                if ($pParent === $oldPath) {
                    // 這個頁面的父層是 oldPath，需要更新為新的 path
                    $p['parent'] = $path;
                    $p['parent_explicit'] = true;
                    
                    // 如果子頁面路徑包含父路徑前綴，需要更新子路徑
                    if (gwa_starts_with($pPath, $oldPath . '/')) {
                        $suffix = substr($pPath, strlen($oldPath) + 1);
                        $newChildPath = $path . '/' . $suffix;
                        // 確保新路徑不與現有路徑衝突
                        $newChildPath = $this->ensureUniqueChildPath($pages, $newChildPath, $pPath);
                        $pathUpdates[$pPath] = $newChildPath;
                        $p['path'] = $newChildPath;
                    }
                } elseif (gwa_starts_with($pPath, $oldPath . '/')) {
                    // 子頁面的路徑包含舊父路徑前綴（即使 parent 欄位可能不同步）
                    $suffix = substr($pPath, strlen($oldPath) + 1);
                    $newChildPath = $path . '/' . $suffix;
                    // 確保新路徑不與現有路徑衝突
                    $newChildPath = $this->ensureUniqueChildPath($pages, $newChildPath, $pPath);
                    $pathUpdates[$pPath] = $newChildPath;
                    $p['path'] = $newChildPath;
                    // 如果 parent 是 oldPath，也更新 parent
                    if ($pParent === $oldPath) {
                        $p['parent'] = $path;
                        $p['parent_explicit'] = true;
                    }
                }
            }
            unset($p);
            
            // 更新所有引用這些子路徑的頁面的 parent（遞迴處理多層嵌套）
            foreach ($pathUpdates as $oldChildPath => $newChildPath) {
                foreach ($pages as &$p2) {
                    $p2Parent = $this->effectiveParent($p2);
                    if ($p2Parent === $oldChildPath) {
                        $p2['parent'] = $newChildPath;
                        $p2['parent_explicit'] = true;
                    }
                }
                unset($p2);
            }
            
            // 先移動內容檔案（在更新 meta 之前），確保文件操作成功
            // 如果文件移動失敗，拋出異常，避免數據不一致
            $oldFile = $this->contentFile($oldPath);
            $newFile = $this->contentFile($path);
            $newDir = dirname($newFile);
            gwa_mkdirp($newDir);
            if (is_file($oldFile)) {
                if (is_file($newFile)) {
                    // 如果目標文件已存在，先刪除（可能是之前的失敗操作留下的）
                    @unlink($newFile);
                }
                if (!@rename($oldFile, $newFile)) {
                    throw new RuntimeException("無法移動父頁面文件：{$oldFile} -> {$newFile}");
                }
            }
            
            // 移動子頁面的內容檔案
            $failedMoves = [];
            foreach ($pathUpdates as $oldChildPath => $newChildPath) {
                $oldChildFile = $this->contentFile($oldChildPath);
                $newChildFile = $this->contentFile($newChildPath);
                $newChildDir = dirname($newChildFile);
                gwa_mkdirp($newChildDir);
                if (is_file($oldChildFile)) {
                    if (is_file($newChildFile)) {
                        // 如果目標文件已存在，先刪除
                        @unlink($newChildFile);
                    }
                    if (!@rename($oldChildFile, $newChildFile)) {
                        $failedMoves[] = "{$oldChildPath} -> {$newChildPath}";
                    }
                }
            }
            
            // 如果有文件移動失敗，回滾父頁面文件並拋出異常
            if (!empty($failedMoves)) {
                // 嘗試回滾父頁面文件
                if (is_file($newFile) && !is_file($oldFile)) {
                    @rename($newFile, $oldFile);
                }
                throw new RuntimeException("無法移動子頁面文件：" . implode(', ', $failedMoves));
            }
            
            // 所有文件移動成功後，才移除舊 meta
            $pages = array_values(array_filter($pages, fn($p1) => (($p1['path'] ?? '') !== $oldPath)));
            $oldToNew = array_merge([$oldPath => $path], $pathUpdates);
        }

        $updated = false;
        foreach ($pages as &$p) {
            if (($p['path'] ?? '') === $path) {
                $p['title'] = $title;
                $p['menu_title'] = $menuTitle;
                $p['type'] = $type;
                if ($type === 'product') {
                    $p['price'] = $price;
                } elseif (isset($p['price'])) {
                    unset($p['price']);
                }
                $p['layout_full_width'] = $layoutFullWidth;
                $p['layout_block_align'] = $layoutBlockAlign;
                if ($hasParent) {
                    $p['parent'] = $parent;
                    $p['parent_explicit'] = true;
                }
                $updated = true;
                break;
            }
        }
        unset($p);
        if (!$updated) {
            $order = $this->nextSiblingOrder($pages, $parent);
            $newPage = [
                'path' => $path,
                'title' => $title,
                'menu_title' => $menuTitle,
                'type' => $type,
                'parent' => $parent,
                'parent_explicit' => true,
                'order' => $order,
                'layout_full_width' => $layoutFullWidth,
                'layout_block_align' => $layoutBlockAlign,
            ];
            if ($type === 'product') {
                $newPage['price'] = $price;
            }
            $pages[] = $newPage;
        }

        // 檢查循環引用（在寫入前）
        $this->assertNoCycles($pages);

        $this->writePages($pages);
        $this->writeContentHtml($path, $html);

        // 路徑變更時，批次更新所有內容檔中的內聯連結（href、data-page-path），避免死連結
        if ($oldToNew !== null) {
            $this->updateInternalLinksInAllContent($oldToNew);
        }

        // 清理未使用的圖片
        $this->cleanupUnusedImages();
    }

    public function deletePage(string $path): void {
        $path = gwa_sanitize_path($path);
        if ($path === 'home') {
            throw new InvalidArgumentException('home 不允許刪除');
        }
        
        // 獲取頁面數據
        $page = $this->getPage($path);
        if (!$page) {
            throw new InvalidArgumentException('頁面不存在');
        }
        
        // 生成唯一的回收站路徑（防止重名）
        $timestamp = time();
        $trashPath = $path . '_' . $timestamp;
        $trashFile = $this->trashDir . str_replace('/', DIRECTORY_SEPARATOR, $trashPath) . '.html';
        $trashDir = dirname($trashFile);
        gwa_mkdirp($trashDir);
        
        // 移動內容文件到回收站
        $file = $this->contentFile($path);
        if (is_file($file)) {
            if (!@rename($file, $trashFile)) {
                throw new RuntimeException("無法移動文件到回收站：{$file}");
            }
        }
        
        // 保存到回收站記錄
        $trash = gwa_read_json_file($this->trashFile, []);
        if (!is_array($trash)) $trash = [];
        $trash[] = [
            'original_path' => $path,
            'trash_path' => $trashPath,
            'page' => $page,
            'deleted_at' => $timestamp
        ];
        gwa_write_json_file_atomic($this->trashFile, $trash);
        
        // 從頁面列表中移除
        $pages = array_values(array_filter($this->getPages(), fn($p) => (($p['path'] ?? '') !== $path)));
        $this->writePages($pages);
    }
    
    public function getTrash(): array {
        $trash = gwa_read_json_file($this->trashFile, []);
        if (!is_array($trash)) return [];
        return $trash;
    }
    
    public function restorePage(string $trashPath): void {
        $trash = $this->getTrash();
        $item = null;
        $itemIndex = -1;
        foreach ($trash as $i => $t) {
            if (($t['trash_path'] ?? '') === $trashPath) {
                $item = $t;
                $itemIndex = $i;
                break;
            }
        }
        if (!$item) {
            throw new InvalidArgumentException('回收站項目不存在');
        }
        
        $originalPath = $item['original_path'] ?? '';
        if ($originalPath === '') {
            throw new InvalidArgumentException('原始路徑無效');
        }
        
        // 檢查原始路徑是否已存在
        $existing = $this->getPage($originalPath);
        if ($existing) {
            // 如果已存在，生成新路徑
            $counter = 1;
            $newPath = $originalPath;
            while ($this->getPage($newPath)) {
                $newPath = $originalPath . '_restored_' . $counter;
                $counter++;
            }
            $originalPath = $newPath;
        }
        
        // 移動文件回內容目錄
        $trashFile = $this->trashDir . str_replace('/', DIRECTORY_SEPARATOR, $trashPath) . '.html';
        $contentFile = $this->contentFile($originalPath);
        $contentDir = dirname($contentFile);
        gwa_mkdirp($contentDir);
        
        if (is_file($trashFile)) {
            if (!@rename($trashFile, $contentFile)) {
                throw new RuntimeException("無法恢復文件：{$trashFile}");
            }
        }
        
        // 恢復頁面到列表
        $page = $item['page'] ?? [];
        $page['path'] = $originalPath;
        $pages = $this->getPages();
        $pages[] = $page;
        $this->writePages($pages);
        
        // 從回收站移除
        array_splice($trash, $itemIndex, 1);
        gwa_write_json_file_atomic($this->trashFile, $trash);
    }

    public function getContentHtml(string $path): string {
        $path = gwa_sanitize_path($path);
        $file = $this->contentFile($path);
        if (is_file($file)) {
            $html = file_get_contents($file);
            if ($html !== false) return $html;
            return '<h1>內容讀取失敗</h1>';
        }
        if ($path === 'home') {
            $default = '<h1>歡迎</h1><p>請到後台建立內容。</p>';
            try {
            $this->writeContentHtml('home', $default);
            } catch (\Exception $e) {
                // 如果寫入失敗，仍返回預設內容
            }
            return $default;
        }
        return '<h1>頁面不存在</h1>';
    }

    public function buildTree(array $pages): array {
        $map = [];
        foreach ($pages as $p) {
            $path = (string)($p['path'] ?? '');
            $map[$path] = ['data' => $p, 'children' => []];
        }

        foreach ($pages as $p) {
            $path = (string)($p['path'] ?? '');
            $parent = $this->effectiveParent($p);
            if ($parent !== '' && isset($map[$parent])) {
                $map[$parent]['children'][] = &$map[$path];
            }
        }

        $roots = [];
        foreach ($map as $path => $node) {
            $parent = $this->effectiveParent((array)$node['data']);
            if ($parent === '' || !isset($map[$parent])) {
                $roots[] = $node;
            }
        }
        $this->sortTree($roots);
        return $roots;
    }

    public function buildBreadcrumbs(array $pages, string $currentPath): array {
        $currentPath = gwa_sanitize_path($currentPath);
        if ($currentPath === 'home') return [];

        $crumbs = [];
        $parts = explode('/', $currentPath);
        $acc = '';
        foreach ($parts as $part) {
            $acc = $acc === '' ? $part : ($acc . '/' . $part);
            foreach ($pages as $p) {
                if (($p['path'] ?? '') === $acc) {
                    $title = (string)($p['menu_title'] ?? '');
                    $title = $title !== '' ? $title : (string)($p['title'] ?? $acc);
                    $crumbs[] = ['path' => $acc, 'title' => $title];
                    break;
                }
            }
        }
        return $crumbs;
    }

    public function publicUrl(string $basePath, string $path): string {
        $basePath = $basePath === '' ? '/' : $basePath;
        $path = gwa_sanitize_path($path);
        return $path === 'home' ? $basePath : ($basePath . $path);
    }

    public function contentPath(string $path): string {
        return $this->contentFile($path);
    }

    public function getContentDirAbsolute(): string {
        return $this->contentDir;
    }

    private function ensureDirs(): void {
        gwa_mkdirp($this->dataDir);
        gwa_mkdirp($this->contentDir);
        gwa_mkdirp($this->trashDir);
    }

    private function ensureHomePage(?array &$pagesRef = null): void {
        $pages = $pagesRef ?? gwa_read_json_file($this->pagesFile, []);
        if (!is_array($pages)) $pages = [];
        $hasHome = false;
        foreach ($pages as $p) {
            if (($p['path'] ?? '') === 'home') {
                $hasHome = true;
                break;
            }
        }
        if (!$hasHome) {
            $pages[] = ['path' => 'home', 'title' => '首頁', 'menu_title' => '首頁', 'parent' => ''];
            $this->writePages($pages);
        }
        if ($pagesRef !== null) $pagesRef = $pages;
    }

    private function writePages(array $pages): void {
        // 檔案內排序：home 第一，其餘按 path（實際顯示排序交由 order + buildTree）
        usort($pages, function($a, $b) {
            $pa = (string)($a['path'] ?? '');
            $pb = (string)($b['path'] ?? '');
            if ($pa === 'home' && $pb !== 'home') return -1;
            if ($pb === 'home' && $pa !== 'home') return 1;
            return strcmp($pa, $pb);
        });
        gwa_write_json_file_atomic($this->pagesFile, array_values($pages));
    }

    public function updateNav(array $updates): array {
        $pages = $this->getPages();
        $byPath = [];
        foreach ($pages as $p) {
            $byPath[(string)($p['path'] ?? '')] = $p;
        }

        // 記錄路徑變更映射 [oldPath => newPath]
        $pathChanges = [];
        
        // 第一遍：收集所有路徑變更
        foreach ($updates as $u) {
            if (!is_array($u)) continue;
            $path = gwa_sanitize_path((string)($u['path'] ?? ''));
            if ($path === '' || !isset($byPath[$path])) continue;
            if ($path === 'home') continue;

            $parent = (string)($u['parent'] ?? '');
            $parent = trim($parent);
            $parent = $parent === '' ? '' : gwa_sanitize_path($parent);
            if ($parent === 'home') $parent = '';
            if ($parent === $path) {
                throw new InvalidArgumentException('不允許把頁面設為自己的父層');
            }
            
            $p0 = $byPath[$path];
            $oldParent = $this->effectiveParent($p0);
            
            // 如果 parent 改變，需要更新 path
            if ($parent !== $oldParent) {
                // 計算新路徑（暫時使用原始 parent，稍後會更新）
                $newPath = $this->calculateNewPath($path, $parent, $byPath);
                
                if ($newPath !== $path) {
                    $pathChanges[$path] = $newPath;
                }
            }
        }
        
        // 第二遍：應用所有更新（包括 parent 和 order）
        foreach ($updates as $u) {
            if (!is_array($u)) continue;
            $path = gwa_sanitize_path((string)($u['path'] ?? ''));
            if ($path === '' || !isset($byPath[$path])) continue;
            if ($path === 'home') continue;

            $parent = (string)($u['parent'] ?? '');
            $parent = trim($parent);
            $parent = $parent === '' ? '' : gwa_sanitize_path($parent);
            if ($parent === 'home') $parent = '';
            
            // 如果 parent 的路徑也改變了，使用新的 parent 路徑
            if ($parent !== '' && isset($pathChanges[$parent])) {
                $parent = $pathChanges[$parent];
            }
            
            // 檢查 parent 是否存在
            if ($parent !== '' && !isset($byPath[$parent])) {
                // 父層不存在 -> 視為頂層
                $parent = '';
            }

            $order = (int)($u['order'] ?? 0);
            $menuTitle = isset($u['menu_title']) ? trim((string)$u['menu_title']) : null;

            // 如果路徑改變了，使用新路徑
            $actualPath = $path;
            if (isset($pathChanges[$path])) {
                $actualPath = $pathChanges[$path];
                if (!isset($byPath[$actualPath])) {
                    // 路徑已更新，但 byPath 還沒更新，需要更新
                    $p0 = $byPath[$path];
                    unset($byPath[$path]);
                    $p0['path'] = $actualPath;
                    $byPath[$actualPath] = $p0;
                }
            }
            
            $p0 = &$byPath[$actualPath];
            $p0['parent'] = $parent;
            $p0['parent_explicit'] = true;
            $p0['order'] = $order;
            if ($menuTitle !== null) {
                $p0['menu_title'] = $menuTitle;
            }
            unset($p0);
        }

        // 更新所有子頁面的路徑和 parent 引用
        foreach ($pathChanges as $oldPath => $newPath) {
            $this->updateChildrenPaths($byPath, $oldPath, $newPath, $pathChanges);
        }

        // 移動內容文件（按路徑長度排序，先移動父頁面，再移動子頁面）
        $sortedChanges = [];
        foreach ($pathChanges as $oldPath => $newPath) {
            $sortedChanges[] = ['old' => $oldPath, 'new' => $newPath, 'depth' => substr_count($oldPath, '/')];
        }
        usort($sortedChanges, fn($a, $b) => $a['depth'] <=> $b['depth']);
        foreach ($sortedChanges as $change) {
            $this->moveContentFile($change['old'], $change['new']);
        }

        // 更新所有引用舊路徑的 parent
        foreach ($pathChanges as $oldPath => $newPath) {
            foreach ($byPath as &$p) {
                if (($p['parent'] ?? '') === $oldPath) {
                    $p['parent'] = $newPath;
                    $p['parent_explicit'] = true;
                }
            }
            unset($p);
        }

        $newPages = array_values($byPath);
        $this->assertNoCycles($newPages);
        $newPages = $this->normalizePages($newPages);
        $this->writePages($newPages);
        
        // 返回路徑變更映射
        return $pathChanges;
    }

    private function calculateNewPath(string $currentPath, string $newParent, array $byPath): string {
        // 從子頁變為頂層頁時，路徑從 parent/child 更新為 child
        if ($newParent === '') {
            // 提取最後一段作為新路徑
            $parts = explode('/', $currentPath);
            $newPath = end($parts);
        } else {
            // 從頂層變為子頁，或從一個父層變為另一個父層
            $parts = explode('/', $currentPath);
            $lastPart = end($parts);
            $newPath = $newParent . '/' . $lastPart;
        }
        
        // 確保新路徑唯一
        $newPath = $this->ensureUniquePath($newPath, $currentPath, $byPath);
        return $newPath;
    }

    private function ensureUniquePath(string $proposedPath, string $excludePath, array $byPath): string {
        // 如果路徑不存在，或存在但就是排除的路徑，則可以使用
        if (!isset($byPath[$proposedPath])) {
            return $proposedPath;
        }
        $existingPage = $byPath[$proposedPath];
        $existingPath = (string)($existingPage['path'] ?? '');
        if ($existingPath === $excludePath) {
            return $proposedPath;
        }
        
        // 如果路徑已存在，添加數字後綴
        $base = $proposedPath;
        $counter = 2;
        while (isset($byPath[$base . '-' . $counter])) {
            $counter++;
        }
        return $base . '-' . $counter;
    }

    private function updateChildrenPaths(array &$byPath, string $oldParentPath, string $newParentPath, array &$pathChanges): void {
        // 先收集需要更新的頁面，避免在循環中修改數組
        $toUpdate = [];
        foreach ($byPath as $path => $page) {
            $currentParent = $this->effectiveParent($page);
            if ($currentParent === $oldParentPath) {
                // 這個頁面的父層是舊路徑，需要更新
                $parts = explode('/', $path);
                $lastPart = end($parts);
                $newChildPath = $newParentPath . '/' . $lastPart;
                
                // 確保新路徑唯一
                $newChildPath = $this->ensureUniquePath($newChildPath, $path, $byPath);
                
                if ($newChildPath !== $path) {
                    $toUpdate[] = ['old' => $path, 'new' => $newChildPath, 'page' => $page, 'updateParent' => true];
                }
            } elseif (gwa_starts_with($path, $oldParentPath . '/')) {
                // 路徑包含舊父路徑前綴，需要更新
                $suffix = substr($path, strlen($oldParentPath) + 1);
                $newChildPath = $newParentPath . '/' . $suffix;
                $newChildPath = $this->ensureUniquePath($newChildPath, $path, $byPath);
                
                if ($newChildPath !== $path) {
                    $toUpdate[] = ['old' => $path, 'new' => $newChildPath, 'page' => $page, 'updateParent' => ($currentParent === $oldParentPath)];
                }
            }
        }
        
        // 執行更新
        foreach ($toUpdate as $update) {
            $oldPath = $update['old'];
            $newPath = $update['new'];
            $page = $update['page'];
            
            $pathChanges[$oldPath] = $newPath;
            unset($byPath[$oldPath]);
            $page['path'] = $newPath;
            if ($update['updateParent']) {
                $page['parent'] = $newParentPath;
                $page['parent_explicit'] = true;
            }
            $byPath[$newPath] = $page;
            
            // 遞迴更新子頁面
            $this->updateChildrenPaths($byPath, $oldPath, $newPath, $pathChanges);
        }
    }

    private function moveContentFile(string $oldPath, string $newPath): void {
        $oldFile = $this->contentFile($oldPath);
        $newFile = $this->contentFile($newPath);
        $newDir = dirname($newFile);
        gwa_mkdirp($newDir);
        
        if (is_file($oldFile)) {
            if (is_file($newFile)) {
                // 如果目標文件已存在，先刪除（可能是之前的失敗操作留下的）
                @unlink($newFile);
            }
            if (!@rename($oldFile, $newFile)) {
                throw new RuntimeException("無法移動內容文件：{$oldFile} -> {$newFile}");
            }
        }
    }

    private function normalizePages(array $pages): array {
        $out = [];
        $i = 0;
        foreach ($pages as $p) {
            if (!is_array($p)) continue;
            $path = (string)($p['path'] ?? '');
            if ($path === '') continue;
            $p['title'] = (string)($p['title'] ?? '');
            $p['menu_title'] = (string)($p['menu_title'] ?? '');
            if (!array_key_exists('type', $p)) {
                $p['type'] = 'page';
            } else {
                $p['type'] = (string)($p['type'] ?? 'page');
                if ($p['type'] !== 'product' && $p['type'] !== 'page') {
                    $p['type'] = 'page';
                }
            }
            if ($p['type'] === 'product' && !array_key_exists('price', $p)) {
                $p['price'] = 0;
            } elseif ($p['type'] === 'page' && array_key_exists('price', $p)) {
                unset($p['price']);
            }

            if (!array_key_exists('parent', $p)) {
                $p['parent'] = '';
            } else {
                $p['parent'] = trim((string)$p['parent']);
            }
            if (!array_key_exists('parent_explicit', $p)) {
                $p['parent_explicit'] = false;
            } else {
                $p['parent_explicit'] = (bool)$p['parent_explicit'];
            }

            if (!array_key_exists('order', $p)) {
                $p['order'] = $i;
            } else {
                $p['order'] = (int)$p['order'];
            }

            // home 強制為 root 且 explicit
            if ($path === 'home') {
                $p['parent'] = '';
                $p['parent_explicit'] = true;
                $p['order'] = -1000000;
            }

            $out[] = $p;
            $i++;
        }
        return $out;
    }

    private function effectiveParent(array $page): string {
        $path = (string)($page['path'] ?? '');
        $parent = trim((string)($page['parent'] ?? ''), '/');
        $explicit = (bool)($page['parent_explicit'] ?? false);
        
        // 如果 parent 已明確設置（parent_explicit = true），直接返回，不從路徑推導
        // 這確保用戶拖動設定的 parent 不會被覆蓋
        if ($explicit) {
            return $parent;
        }
        
        // 如果 parent 未明確設置且為空，但路徑包含 /，則從路徑推導
        // 這確保了即使數據不一致，也能正確構建樹結構
        if ($parent === '' && $path !== '' && $path !== 'home' && strpos($path, '/') !== false) {
            return substr($path, 0, strrpos($path, '/'));
        }
        return $parent;
    }

    private function sortTree(array &$nodes): void {
        usort($nodes, function($a, $b) {
            $ao = (int)(($a['data']['order'] ?? 0));
            $bo = (int)(($b['data']['order'] ?? 0));
            if ($ao !== $bo) return $ao <=> $bo;
            $ap = (string)(($a['data']['path'] ?? ''));
            $bp = (string)(($b['data']['path'] ?? ''));
            return strcmp($ap, $bp);
        });
        foreach ($nodes as &$n) {
            if (!empty($n['children'])) {
                $this->sortTree($n['children']);
            }
        }
        unset($n);
    }

    private function ensureUniqueChildPath(array $pages, string $proposedPath, string $excludePath): string {
        $existingPaths = [];
        foreach ($pages as $p) {
            $pPath = (string)($p['path'] ?? '');
            if ($pPath !== '' && $pPath !== $excludePath) {
                $existingPaths[$pPath] = true;
            }
        }
        
        if (!isset($existingPaths[$proposedPath])) {
            return $proposedPath;
        }
        
        // 如果路徑已存在，添加數字後綴
        $base = $proposedPath;
        $counter = 2;
        while (isset($existingPaths[$base . '-' . $counter])) {
            $counter++;
        }
        return $base . '-' . $counter;
    }

    private function assertNoCycles(array $pages): void {
        $parentOf = [];
        foreach ($pages as $p) {
            $path = (string)($p['path'] ?? '');
            if ($path === '') continue;
            if ($path === 'home') {
                $parentOf[$path] = '';
                continue;
            }
            $parentOf[$path] = $this->effectiveParent($p);
        }
        foreach ($parentOf as $node => $parent) {
            $seen = [];
            $cur = $node;
            while (true) {
                if (isset($seen[$cur])) {
                    throw new InvalidArgumentException('偵測到父層循環：' . $node);
                }
                $seen[$cur] = true;
                $p = $parentOf[$cur] ?? '';
                if ($p === '' || $p === $cur) break;
                $cur = $p;
            }
        }
    }

    private function nextSiblingOrder(array $pages, string $parent): int {
        $max = 0;
        foreach ($pages as $p) {
            $pp = $this->effectiveParent($p);
            if ($pp !== $parent) continue;
            $o = (int)($p['order'] ?? 0);
            if ($o > $max) $max = $o;
        }
        return $max + 1;
    }

    private function contentFile(string $path): string {
        $path = gwa_sanitize_path($path);
        return $this->contentDir . str_replace('/', DIRECTORY_SEPARATOR, $path) . '.html';
    }

    private function writeContentHtml(string $path, string $html): void {
        $path = gwa_sanitize_path($path);
        $file = $this->contentFile($path);
        $dir = dirname($file);
        gwa_mkdirp($dir);
        // 原子寫入：避免寫到一半被讀到/或寫入失敗留下半檔
        $tmp = $file . '.tmp.' . bin2hex(random_bytes(8));
        if (file_put_contents($tmp, $html, LOCK_EX) === false) {
            @unlink($tmp);
            throw new RuntimeException('內容寫入失敗');
        }
        if (!rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('內容原子寫入失敗');
        }
    }

    /**
     * 當頁面路徑變更時，批次更新所有內容檔中的內聯連結（href）與 data-page-path，
     * 避免死連結。$oldToNew 為 [ 舊路徑 => 新路徑 ]，依鍵長度由長到短處理。
     */
    private function updateInternalLinksInAllContent(array $oldToNew): void {
        if ($oldToNew === []) {
            return;
        }
        // 長路徑優先，避免 product/ev 被當成 product 替換
        uksort($oldToNew, fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        $pages = $this->getPages();
        foreach ($pages as $page) {
            $pagePath = (string)($page['path'] ?? '');
            $file = $this->contentFile($pagePath);
            if (!is_file($file)) {
                continue;
            }
            $html = file_get_contents($file);
            if ($html === false) {
                continue;
            }
            $updated = $this->replaceInternalLinksInHtml($html, $oldToNew);
            if ($updated !== $html) {
                $this->writeContentHtml($pagePath, $updated);
            }
        }
    }

    /**
     * 在 HTML 中將指向舊路徑的 href 與 data-page-path 替換為新路徑。
     */
    private function replaceInternalLinksInHtml(string $html, array $oldToNew): string {
        foreach ($oldToNew as $oldPath => $newPath) {
            $oldPath = (string) $oldPath;
            $newPath = (string) $newPath;
            if ($oldPath === '' || $oldPath === $newPath) {
                continue;
            }

            // 1. href="..." 或 href='...'：僅當路徑部分等於 oldPath 或以 oldPath/ 開頭時替換
            $html = preg_replace_callback(
                '/href=(["\'])([^"\']*)\1/i',
                function (array $m) use ($oldPath, $newPath): string {
                    $quote = $m[1];
                    $val = $m[2];
                    $path = $this->hrefValueToPath($val);
                    if ($path === '' || ($path !== $oldPath && !gwa_starts_with($path, $oldPath . '/'))) {
                        return $m[0];
                    }
                    $replacedPath = ($path === $oldPath) ? $newPath : ($newPath . substr($path, strlen($oldPath)));
                    return $this->pathBackToHrefValue($val, $replacedPath, $quote);
                },
                $html
            );

            // 2. data-page-path="oldPath" 或 data-page-path='oldPath'
            $html = str_replace('data-page-path="' . $oldPath . '"', 'data-page-path="' . $newPath . '"', $html);
            $html = str_replace("data-page-path='" . $oldPath . "'", "data-page-path='" . $newPath . "'", $html);
        }
        return $html;
    }

    private function hrefValueToPath(string $href): string {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $href)) {
            $path = parse_url($href, PHP_URL_PATH);
            $path = $path === null ? '' : (string) $path;
        } else {
            $path = preg_replace('/[#?].*$/s', '', $href);
        }
        return trim(str_replace('\\', '/', $path), '/');
    }

    private function pathBackToHrefValue(string $original, string $newPath, string $quote): string {
        if (preg_match('#^https?://#i', $original)) {
            $parsed = parse_url($original);
            $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : 'https://';
            $host = $parsed['host'] ?? '';
            $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
            $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
            $path = '/' . ltrim($newPath, '/');
            return 'href=' . $quote . $scheme . $host . $path . $query . $fragment . $quote;
        }
        $suffix = '';
        if (preg_match('/^([^#?]*)([#?].*)$/s', $original, $m)) {
            $suffix = $m[2];
        }
        $leadingSlash = (strlen($original) > 0 && $original[0] === '/') ? '/' : '';
        return 'href=' . $quote . $leadingSlash . $newPath . $suffix . $quote;
    }

    /**
     * 清理 content/images 目錄中未被使用的圖片檔案
     */
    private function cleanupUnusedImages(): void {
        $imagesDir = $this->contentDir . 'images' . DIRECTORY_SEPARATOR;
        if (!is_dir($imagesDir)) {
            return;
        }

        // 1. 收集所有頁面中使用的圖片 URL
        $usedImagePaths = [];
        $pages = $this->getPages();
        foreach ($pages as $page) {
            $path = (string)($page['path'] ?? '');
            $html = $this->getContentHtml($path);
            
            // 使用正則表達式找出所有 img src
            if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
                foreach ($matches[1] as $url) {
                    $filePath = $this->urlToImagePath($url);
                    if ($filePath !== null) {
                        $usedImagePaths[$filePath] = true;
                    }
                }
            }
        }

        // 2. 遞迴掃描 images 目錄找出所有圖片檔案
        $allImageFiles = $this->scanImageFiles($imagesDir);

        // 3. 刪除未使用的檔案
        $deletedCount = 0;
        foreach ($allImageFiles as $filePath) {
            if (!isset($usedImagePaths[$filePath])) {
                if (@unlink($filePath)) {
                    $deletedCount++;
                    // 嘗試刪除空的父目錄
                    $parentDir = dirname($filePath);
                    while ($parentDir !== $imagesDir && $parentDir !== $this->contentDir) {
                        if (@rmdir($parentDir)) {
                            $parentDir = dirname($parentDir);
                        } else {
                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * 將圖片 URL 轉換為檔案系統路徑
     */
    private function urlToImagePath(string $url): ?string {
        // 移除查詢字串和錨點
        $url = preg_replace('/[?#].*$/', '', $url);
        
        // 匹配 content/images/ 開頭的 URL
        // 格式可能是：/content/images/filename.jpg 或 /content/images/path/filename.jpg
        // 或完整 URL：http://example.com/content/images/filename.jpg
        if (preg_match('#/content/images/(.+)$#', $url, $matches)) {
            $relativePath = $matches[1];
            // 移除開頭的斜線
            $relativePath = ltrim($relativePath, '/');
            // URL 解碼
            $relativePath = urldecode($relativePath);
            
            // 安全檢查：不允許包含 .. 或絕對路徑
            if (strpos($relativePath, '..') !== false || 
                strpos($relativePath, DIRECTORY_SEPARATOR) === 0) {
                return null;
            }
            
            // 轉換為檔案系統路徑
            $filePath = $this->contentDir . 'images' . DIRECTORY_SEPARATOR . 
                       str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            
            // 標準化路徑（移除多餘的斜線）
            $filePath = str_replace(
                DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR,
                DIRECTORY_SEPARATOR,
                $filePath
            );
            
            // 確保路徑在 images 目錄內（安全檢查）
            $imagesDir = rtrim($this->contentDir . 'images' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR);
            $normalizedPath = rtrim($filePath, DIRECTORY_SEPARATOR);
            $normalizedImagesDir = rtrim($imagesDir, DIRECTORY_SEPARATOR);
            
            if (strpos($normalizedPath, $normalizedImagesDir) !== 0) {
                return null;
            }
            
            return $filePath;
        }
        
        return null;
    }

    /**
     * 遞迴掃描 images 目錄找出所有圖片檔案
     */
    private function scanImageFiles(string $dir): array {
        $files = [];
        if (!is_dir($dir)) {
            return $files;
        }
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        try {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, $allowedExtensions, true)) {
                        $realPath = $file->getRealPath();
                        if ($realPath !== false) {
                            $files[] = $realPath;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // 如果掃描失敗，返回已收集的檔案（部分結果）
            // 不拋出異常，避免影響主要功能
        }
        
        return $files;
    }
}



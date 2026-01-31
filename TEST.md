# GeneralWebApp 標準化測試指南

## 測試前準備

1. **確保無 alert/confirm/prompt 彈窗**
   - 所有原生彈窗已替換為內建對話框
   - 使用 `showMsg()` 進行通知
   - 使用 `showConfirm()` 進行確認
   - 使用 `showInput()` 進行輸入

## 執行測試

### 方法 1: 瀏覽器 Console

1. 開啟管理後台 (`admin.php`)
2. 登入後，在瀏覽器 Console 輸入：
   ```javascript
   window.GWA_TEST = true;
   location.reload();
   ```
3. 測試結果會顯示在 Console 和頁面通知中

### 方法 2: URL 參數（開發模式）

在 URL 後加上 `?test=1`，系統會自動啟用測試模式。

## 測試項目

### 1. 基本功能測試
- ✓ `showMsg` 函數存在
- ✓ `showConfirm` 函數存在
- ✓ `showInput` 函數存在
- ✓ `quill` 實例存在

### 2. DOM 元素測試
- ✓ `editor` 元素存在
- ✓ `toolbar` 元素存在
- ✓ `findModal` 元素存在
- ✓ `confirmModal` 元素存在
- ✓ `inputModal` 元素存在

### 3. 狀態管理測試
- ✓ `isDirty` 為布林值
- ✓ `isFullscreen` 為布林值
- ✓ `pagesCache` 為陣列

### 4. 函數完整性測試
- ✓ `loadPage` 函數存在
- ✓ `savePage` 函數存在
- ✓ `deletePage` 函數存在
- ✓ `uploadImage` 函數存在
- ✓ `updateDocStats` 函數存在
- ✓ `markDirty` 函數存在
- ✓ `markSaved` 函數存在

### 5. 無原生彈窗測試
- ✓ 無 `alert()` 使用
- ✓ 無 `confirm()` 使用
- ✓ 無 `prompt()` 使用

## 測試結果

測試完成後會顯示：
- 通過的測試數量
- 失敗的測試數量
- 詳細錯誤訊息（如有）

所有測試通過會顯示：`[GWA] ✓ 所有測試通過！`

## 手動功能測試清單

### 編輯器功能
- [ ] 文字輸入與格式化
- [ ] 插入圖片（拖放/上傳）
- [ ] 插入表格
- [ ] 尋找/取代功能（Ctrl+F）
- [ ] 插入分頁（Ctrl+Enter）
- [ ] 列印功能
- [ ] 全螢幕模式（ESC 退出）

### 頁面管理
- [ ] 新增頁面
- [ ] 編輯頁面
- [ ] 儲存頁面（Ctrl+S）
- [ ] 刪除頁面（使用內建確認對話框）
- [ ] 拖放調整頁面順序

### 草稿功能
- [ ] 自動儲存草稿
- [ ] 還原草稿
- [ ] 丟棄草稿
- [ ] 未儲存狀態提示

### 密碼管理
- [ ] 更改密碼（使用內建輸入對話框）

### 快捷鍵
- [ ] Ctrl+S 儲存
- [ ] Ctrl+F 尋找
- [ ] Ctrl+Enter 插入分頁
- [ ] ESC 關閉視窗/退出全螢幕

## 注意事項

- 測試模式僅在開發環境使用
- 生產環境請移除或禁用測試代碼
- 所有通知使用內建 `showMsg()`，不使用 `alert()`


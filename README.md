# GeneralWebApp

檔案式 CMS，支援導覽樹、Quill 編輯、區塊式版面、多語言、主題與維護模式。

## 需求

- PHP 7.4+
- 可寫入的 `data/` 目錄

## 安裝

1. 複製或 clone 到網站根目錄。
2. 建立 `data/` 目錄並設定可寫入權限：
   ```bash
   mkdir -p data/brand data/orders data/translations
   chmod 755 data data/brand data/orders data/translations
   ```
3. 在瀏覽器開啟 `admin.php`，首次會要求設定管理員密碼。
4. 在 `admin.php` 管理頁面、導覽、設定與內容。

## 目錄結構（簡要）

- `index.php` — 前台
- `admin.php` — 管理後台
- `api.php` — API
- `app/` — 核心類別（Auth, Cms, Settings 等）
- `assets/` — CSS 與主題
- `content/` — 頁面內容（由後台寫入）
- `data/` — **不納入版控**，存放設定、密碼雜湊、訂單、上傳圖片等，需自行建立並設定權限

## 授權

依專案需求自行決定。

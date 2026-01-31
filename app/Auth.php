<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

final class Auth {
    private string $adminFile;
    private string $sessionKey = 'gwa_admin_logged_in';
    private string $csrfKey = 'gwa_csrf';

    public function __construct(string $rootDir) {
        $rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->adminFile = $rootDir . 'data' . DIRECTORY_SEPARATOR . 'admin.json';
    }

    public function isConfigured(): bool {
        $data = gwa_read_json_file($this->adminFile, []);
        return isset($data['password_hash']) && is_string($data['password_hash']) && $data['password_hash'] !== '';
    }

    public function isLoggedIn(): bool {
        $this->startSession();
        return (bool)($_SESSION[$this->sessionKey] ?? false);
    }

    public function csrfToken(): string {
        $this->startSession();
        if (!isset($_SESSION[$this->csrfKey]) || !is_string($_SESSION[$this->csrfKey]) || $_SESSION[$this->csrfKey] === '') {
            $_SESSION[$this->csrfKey] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION[$this->csrfKey];
    }

    public function verifyCsrf(?string $token): bool {
        $this->startSession();
        if ($token === null || $token === '') return false;
        $current = (string)($_SESSION[$this->csrfKey] ?? '');
        return $current !== '' && hash_equals($current, $token);
    }

    public function requireCsrfFromRequest(): void {
        $token = null;
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
        } elseif (isset($_POST['csrf_token'])) {
            $token = (string)$_POST['csrf_token'];
        }
        if (!$this->verifyCsrf($token)) {
            gwa_json(['ok' => false, 'error' => 'CSRF 驗證失敗'], 403);
        }
    }

    public function setPassword(string $password): void {
        $password = trim($password);
        if (strlen($password) < 10) {
            throw new InvalidArgumentException('密碼至少 10 字元');
        }
        if ($this->isConfigured()) {
            throw new RuntimeException('已設定過密碼');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            throw new RuntimeException('密碼雜湊失敗');
        }
        gwa_write_json_file_atomic($this->adminFile, [
            'password_hash' => $hash,
            'created_at' => date('c'),
        ]);
    }

    public function login(string $password): bool {
        $password = (string)$password;
        $data = gwa_read_json_file($this->adminFile, []);
        $hash = (string)($data['password_hash'] ?? '');
        if ($hash === '') return false;

        if (!password_verify($password, $hash)) {
            return false;
        }
        $this->startSession();
        session_regenerate_id(true);
        $_SESSION[$this->sessionKey] = true;
        $this->csrfToken();
        return true;
    }

    public function changePassword(string $currentPassword, string $newPassword): void {
        if (!$this->isConfigured()) {
            throw new RuntimeException('尚未設定管理員密碼');
        }
        $currentPassword = (string)$currentPassword;
        $newPassword = trim((string)$newPassword);
        if (strlen($newPassword) < 10) {
            throw new InvalidArgumentException('新密碼至少 10 字元');
        }

        $data = gwa_read_json_file($this->adminFile, []);
        $hash = (string)($data['password_hash'] ?? '');
        if ($hash === '') {
            throw new RuntimeException('密碼資料異常');
        }
        if (!password_verify($currentPassword, $hash)) {
            throw new InvalidArgumentException('舊密碼錯誤');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!is_string($newHash) || $newHash === '') {
            throw new RuntimeException('密碼雜湊失敗');
        }

        $data['password_hash'] = $newHash;
        $data['updated_at'] = date('c');
        gwa_write_json_file_atomic($this->adminFile, $data);

        // 更新 session/CSRF
        $this->startSession();
        session_regenerate_id(true);
        $_SESSION[$this->sessionKey] = true;
        $this->csrfToken();
    }

    public function logout(): void {
        $this->startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function requireLoggedInJson(): void {
        if (!$this->isLoggedIn()) {
            gwa_json(['ok' => false, 'error' => '未登入'], 401);
        }
    }

    private function startSession(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // 設置安全的 session cookie 參數
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                       (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            
            session_set_cookie_params([
                'lifetime' => 0, // 瀏覽器關閉時過期
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps, // HTTPS 時啟用
                'httponly' => true, // 防止 JavaScript 訪問
                'samesite' => 'Strict' // 防止 CSRF
            ]);
            
            session_start();
            
            // 防止 session fixation 攻擊
            if (!isset($_SESSION['created'])) {
                session_regenerate_id(true);
                $_SESSION['created'] = true;
            }
        }
    }
}



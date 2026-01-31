<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

final class Settings {
    private string $file;

    /** @var array<string, true> */
    private array $allowedThemes = [
        'neon-dark' => true,
        'paper-light' => true,
        'graphite' => true,
        'minimal-efficient' => true,
        'modern-futuristic' => true,
        'cyberpunk' => true,
        'glassmorphism' => true,
        'neon-purple' => true,
        'christmas' => true,
        'new-year' => true,
        'valentine' => true,
        'passover' => true,
        'easter' => true,
    ];

    public function __construct(string $rootDir) {
        $rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->file = $rootDir . 'data' . DIRECTORY_SEPARATOR . 'settings.json';
    }

    public function getTheme(): string {
        $data = $this->read();
        $theme = (string)($data['theme'] ?? '');
        if ($theme === '' || !isset($this->allowedThemes[$theme])) {
            return 'neon-dark';
        }
        return $theme;
    }

    public function setTheme(string $theme): void {
        $theme = trim($theme);
        if (!isset($this->allowedThemes[$theme])) {
            throw new InvalidArgumentException('不支援的主題');
        }
        $data = $this->read();
        $data['theme'] = $theme;
        $data['updated_at'] = date('c');
        gwa_write_json_file_atomic($this->file, $data);
    }

    public function themes(): array {
        return array_keys($this->allowedThemes);
    }

    public function getBrand(): array {
        $data = $this->read();
        return [
            'title' => (string)($data['brand']['title'] ?? 'General Web App'),
            'subtitle' => (string)($data['brand']['subtitle'] ?? '檔案式 CMS / 導覽樹 / Quill 編輯'),
            'logo' => (string)($data['brand']['logo'] ?? ''),
            'icon' => (string)($data['brand']['icon'] ?? ''),
        ];
    }

    public function setBrand(string $title, string $subtitle, string $logo = '', string $icon = ''): void {
        $data = $this->read();
        $data['brand'] = [
            'title' => trim($title),
            'subtitle' => trim($subtitle),
            'logo' => trim($logo),
            'icon' => trim($icon),
        ];
        $data['updated_at'] = date('c');
        gwa_write_json_file_atomic($this->file, $data);
    }

    public function getFooter(): string {
        $data = $this->read();
        return (string)($data['footer'] ?? '提示：這裡保留純超連結可用性，同時提供 SPA 式無刷新切換。');
    }

    public function setFooter(string $html): void {
        $data = $this->read();
        $data['footer'] = trim($html);
        $data['updated_at'] = date('c');
        gwa_write_json_file_atomic($this->file, $data);
    }

    public function getWhatsApp(): string {
        $data = $this->read();
        return (string)($data['whatsapp'] ?? '');
    }

    public function setWhatsApp(string $number): void {
        $data = $this->read();
        $data['whatsapp'] = trim($number);
        $data['updated_at'] = date('c');
        gwa_write_json_file_atomic($this->file, $data);
    }

    public function getCheckoutPage(): string {
        $data = $this->read();
        return (string)($data['checkout_page'] ?? '<h2>訂單已建立</h2><p>請按照以下方式完成付款：</p><p>付款方式將由客服人員與您聯繫確認。</p>');
    }

    public function setCheckoutPage(string $html): void {
        $data = $this->read();
        $data['checkout_page'] = trim($html);
        $data['updated_at'] = date('c');
        gwa_write_json_file_atomic($this->file, $data);
    }

    public function getCurrency(): string {
        $data = $this->read();
        $currency = (string)($data['currency'] ?? 'HKD');
        $allowed = ['HKD', 'TWD', 'USD', 'CNY', 'JPY', 'EUR', 'GBP', 'SGD'];
        return in_array($currency, $allowed, true) ? $currency : 'HKD';
    }

    public function setCurrency(string $currency): void {
        $currency = strtoupper(trim($currency));
        $allowed = ['HKD', 'TWD', 'USD', 'CNY', 'JPY', 'EUR', 'GBP', 'SGD'];
        if (!in_array($currency, $allowed, true)) {
            throw new InvalidArgumentException('不支援的貨幣');
        }
        $data = $this->read();
        $data['currency'] = $currency;
        $data['updated_at'] = date('c');
        gwa_write_json_file_atomic($this->file, $data);
    }

    public function getCurrencySymbol(): string {
        $currency = $this->getCurrency();
        $symbols = [
            'HKD' => 'HK$',
            'TWD' => 'NT$',
            'USD' => '$',
            'CNY' => '¥',
            'JPY' => '¥',
            'EUR' => '€',
            'GBP' => '£',
            'SGD' => 'S$',
        ];
        return $symbols[$currency] ?? 'HK$';
    }

    public function getLanguages(): array {
        $data = $this->read();
        return (array)($data['languages'] ?? [
            'zh-TW' => ['name' => '繁體中文', 'native' => true],
            'en' => ['name' => 'English', 'native' => false],
            'zh-CN' => ['name' => '简体中文', 'native' => false],
            'ja' => ['name' => '日本語', 'native' => false],
        ]);
    }

    public function setLanguages(array $languages): void {
        $data = $this->read();
        $data['languages'] = $languages;
        $data['updated_at'] = date('c');
        gwa_write_json_file_atomic($this->file, $data);
    }

    public function getDefaultLanguage(): string {
        $data = $this->read();
        return (string)($data['default_language'] ?? 'zh-TW');
    }

    public function setDefaultLanguage(string $lang): void {
        $data = $this->read();
        $data['default_language'] = trim($lang);
        $data['updated_at'] = date('c');
        gwa_write_json_file_atomic($this->file, $data);
    }

    public function getTranslations(): array {
        $data = $this->read();
        return (array)($data['translations'] ?? []);
    }

    public function setTranslations(array $translations): void {
        $data = $this->read();
        $data['translations'] = $translations;
        $data['updated_at'] = date('c');
        gwa_write_json_file_atomic($this->file, $data);
    }

    public function getMaintenanceMode(): bool {
        $data = $this->read();
        return (bool)($data['maintenance_mode'] ?? false);
    }

    public function setMaintenanceMode(bool $enabled): void {
        $data = $this->read();
        $data['maintenance_mode'] = $enabled;
        $data['updated_at'] = date('c');
        gwa_write_json_file_atomic($this->file, $data);
    }

    /** 協調字體：Normal / H1 / H2 / H3 的預設字體、大小、顏色、粗度，套用於全局內容區 */
    public function getTypography(): array {
        $data = $this->read();
        $raw = (array)($data['typography'] ?? []);
        $defaults = [
            'normal' => ['fontFamily' => '', 'fontSize' => '16px', 'color' => '', 'fontWeight' => '400'],
            'h1' => ['fontFamily' => '', 'fontSize' => '28px', 'color' => '', 'fontWeight' => '700'],
            'h2' => ['fontFamily' => '', 'fontSize' => '22px', 'color' => '', 'fontWeight' => '600'],
            'h3' => ['fontFamily' => '', 'fontSize' => '18px', 'color' => '', 'fontWeight' => '600'],
        ];
        foreach (array_keys($defaults) as $key) {
            $v = (array)($raw[$key] ?? []);
            $defaults[$key] = [
                'fontFamily' => (string)($v['fontFamily'] ?? $defaults[$key]['fontFamily']),
                'fontSize' => (string)($v['fontSize'] ?? $defaults[$key]['fontSize']),
                'color' => (string)($v['color'] ?? $defaults[$key]['color']),
                'fontWeight' => (string)($v['fontWeight'] ?? $defaults[$key]['fontWeight']),
            ];
        }
        return $defaults;
    }

    public function setTypography(array $typography): void {
        $data = $this->read();
        $data['typography'] = $typography;
        $data['updated_at'] = date('c');
        gwa_write_json_file_atomic($this->file, $data);
    }

    private function read(): array {
        return gwa_read_json_file($this->file, []);
    }
}




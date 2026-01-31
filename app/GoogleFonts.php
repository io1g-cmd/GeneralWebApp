<?php
declare(strict_types=1);

/**
 * 單一來源：前後端共用的 Google Fonts 清單與載入連結
 */
final class GoogleFonts
{
    /** 本專案會用到的 Google Font 名稱（與編輯器字體選單一致） */
    public const NAMES = [
        'Roboto', 'Open Sans', 'Lato', 'Poppins', 'Montserrat', 'Oswald',
        'Source Sans 3', 'Playfair Display', 'Merriweather', 'PT Sans',
        'Nunito', 'Raleway', 'Work Sans', 'Barlow', 'Inter', 'DM Sans',
        'Noto Sans TC', 'Noto Serif TC', 'Noto Sans HK', 'Noto Serif HK',
        'Noto Sans SC', 'Noto Serif SC', 'cwTeXMing',
    ];

    /**
     * 回傳 Google Fonts 的 <link> 標籤（單一載入，前後端共用）
     * @param array $typography 協調字體設定，可從中額外納入用到的字體（可為空）
     */
    public static function linkTag(array $typography = []): string
    {
        $need = array_fill_keys(self::NAMES, true);
        foreach ($typography as $t) {
            $fam = trim((string)($t['fontFamily'] ?? ''));
            if ($fam === '') continue;
            foreach (self::NAMES as $name) {
                if (stripos($fam, $name) !== false) {
                    $need[$name] = true;
                    break;
                }
            }
        }
        $families = [];
        foreach (array_keys($need) as $name) {
            $families[] = str_replace(' ', '+', $name) . ':wght@400;500;600;700';
        }
        if ($families === []) {
            return '';
        }
        $url = 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $families) . '&display=swap';
        return '<link rel="stylesheet" href="' . htmlspecialchars($url) . '" id="gwa-google-fonts">';
    }
}

<?php
/* Language engine: English / Hindi / Marathi.
   Stored data stays in English; screen text is translated while the page is sent. */
const LANGS = ['en' => 'English', 'hi' => 'हिन्दी', 'mr' => 'मराठी'];
$GLOBALS['WL'] = ['lang' => 'en', 't' => [], 'p' => [], 'months' => []];

function lang(): string { return $GLOBALS['WL']['lang']; }
function __(string $s): string { return $GLOBALS['WL']['t'][$s] ?? $s; }

function i18n_core(string $plain) {
    $W = $GLOBALS['WL'];
    if (isset($W['t'][$plain])) return $W['t'][$plain];
    foreach ($W['p'] as $pat) {
        if (preg_match($pat[0], $plain, $m)) {
            $out = $pat[1];
            foreach ($m as $i => $v) if ($i > 0) $out = str_replace('{' . $i . '}', $v, $out);
            return $out;
        }
    }
    return null;
}
function i18n_dates(string $s): string {
    $mo = $GLOBALS['WL']['months'];
    if (!$mo) return $s;
    $en = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return preg_replace_callback('/\b(\d{1,2}) (Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) (\d{4})\b/', function ($m) use ($en, $mo) {
        return $m[1] . ' ' . $mo[array_search($m[2], $en)] . ' ' . $m[3];
    }, $s);
}
function i18n_text(string $raw): string {
    if (trim($raw) === '' || !preg_match('/^(\s*)(.*?)(\s*)$/su', $raw, $m)) return $raw;
    $plain = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
    $tr = i18n_core($plain);
    if ($tr === null && strpos($plain, ' · ') !== false) {
        $parts = explode(' · ', $plain); $changed = false;
        foreach ($parts as $k => $p) { $x = i18n_core($p); if ($x !== null) { $parts[$k] = $x; $changed = true; } }
        if ($changed) $tr = implode(' · ', $parts);
    }
    if ($tr === null && preg_match('/^(.+ — .+) (per (?:piece|hour|day|job|kg|metre))$/u', $plain, $mm) && isset($GLOBALS['WL']['t'][$mm[2]])) {
        $tr = $mm[1] . ' ' . $GLOBALS['WL']['t'][$mm[2]];
    }
    $out = $tr === null ? $m[2] : htmlspecialchars($tr, ENT_NOQUOTES, 'UTF-8');
    return $m[1] . i18n_dates($out) . $m[3];
}
function i18n_attr(array $m): string {
    $tr = i18n_core(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'));
    return $tr === null ? $m[0] : $m[1] . '="' . htmlspecialchars($tr, ENT_QUOTES, 'UTF-8') . '"';
}
function i18n_buffer(string $html): string {
    if (stripos(ltrim($html), '<!doctype') !== 0) return $html;   // leave CSV and other output alone
    $parts = preg_split('~(<script\b.*?</script>|<style\b.*?</style>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) return $html;
    foreach ($parts as $i => $part) {
        if ($i % 2 === 1) continue;
        $p = preg_replace_callback('/\b(placeholder|data-confirm|aria-label)="([^"]*)"/u', 'i18n_attr', $part);
        if ($p === null) continue;
        $p = preg_replace_callback('/>([^<>]+)(?=<)/u', function ($m) { return '>' . i18n_text($m[1]); }, $p);
        if ($p !== null) $parts[$i] = $p;
    }
    return implode('', $parts);
}

/* Pick the language */
(function () {
    $lang = DEFAULT_LANG;
    if (LANG_LOCK !== '') {
        $lang = LANG_LOCK;
    } else {
        if (isset($_GET['lang']) && is_string($_GET['lang']) && array_key_exists($_GET['lang'], LANGS)) {
            $_SESSION['lang'] = $_GET['lang'];
            if (!headers_sent()) setcookie('wl_lang', $_GET['lang'], time() + 31536000, '/');
        }
        $c = $_SESSION['lang'] ?? ($_COOKIE['wl_lang'] ?? '');
        if (is_string($c) && array_key_exists($c, LANGS)) $lang = $c;
    }
    $file = __DIR__ . '/../lang/' . $lang . '.php';
    if ($lang !== 'en' && is_file($file)) {
        $d = require $file;
        $GLOBALS['WL'] = ['lang' => $lang, 't' => $d['t'], 'p' => $d['p'], 'months' => $d['months']];
        if (PHP_SAPI !== 'cli') ob_start('i18n_buffer');
    }
})();

/* Helpers used by the layout */
function lang_url(string $code): string {
    $q = $_GET; $q['lang'] = $code;
    return basename($_SERVER['PHP_SELF']) . '?' . http_build_query($q);
}
function lang_switcher(): string {
    if (LANG_LOCK !== '') return '';
    $h = '<div class="langs">';
    foreach (LANGS as $c => $n) $h .= '<a href="' . e(lang_url($c)) . '"' . ($c === lang() ? ' class="on" aria-current="true"' : '') . '>' . e($n) . '</a>';
    return $h . '</div>';
}
function lang_fonts(): string {
    return lang() === 'en' ? '' : '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;600;700&display=swap">';
}
function js_i18n(): string {
    return '<script>window.WL_I18N=' . json_encode(['show' => __('Show'), 'hide' => __('Hide')], JSON_UNESCAPED_UNICODE) . ';</script>';
}

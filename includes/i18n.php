<?php
// Internationalization & Translation Helper for AniStream

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Allow switching language via ?lang=code
if (!empty($_GET['lang'])) {
    $requestedLang = preg_replace('/[^a-z0-9_-]/i', '', $_GET['lang']);
    $_SESSION['app_lang'] = strtolower($requestedLang);
}

function current_lang() {
    global $pdo;
    if (!empty($_SESSION['app_lang'])) {
        return $_SESSION['app_lang'];
    }
    // Check default from languages table
    static $defaultLang = null;
    if ($defaultLang === null && isset($pdo)) {
        try {
            $stmt = $pdo->query("SELECT code FROM languages WHERE is_default = 1 LIMIT 1");
            $defaultLang = $stmt->fetchColumn() ?: 'en';
        } catch (Exception $e) {
            $defaultLang = 'en';
        }
    }
    return $defaultLang ?: 'en';
}

function is_rtl() {
    global $pdo;
    $lang = current_lang();
    static $rtlCache = [];
    if (isset($rtlCache[$lang])) {
        return $rtlCache[$lang];
    }
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT rtl FROM languages WHERE code = ? LIMIT 1");
            $stmt->execute([$lang]);
            $res = (int)$stmt->fetchColumn();
            $rtlCache[$lang] = ($res === 1);
            return $rtlCache[$lang];
        } catch (Exception $e) {}
    }
    $rtlCache[$lang] = ($lang === 'ar' || $lang === 'fa' || $lang === 'he');
    return $rtlCache[$lang];
}

function load_translations($lang) {
    global $pdo;
    static $cache = [];
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }
    $cache[$lang] = [];
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT trans_key, trans_val FROM translations WHERE lang_code = ?");
            $stmt->execute([$lang]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cache[$lang][$row['trans_key']] = $row['trans_val'];
            }
        } catch (Exception $e) {}
    }
    return $cache[$lang];
}

function __($key, $default = '') {
    $lang = current_lang();
    $translations = load_translations($lang);
    if (isset($translations[$key]) && trim($translations[$key]) !== '') {
        return $translations[$key];
    }
    // Fallback to English if not current
    if ($lang !== 'en') {
        $enTranslations = load_translations('en');
        if (isset($enTranslations[$key]) && trim($enTranslations[$key]) !== '') {
            return $enTranslations[$key];
        }
    }
    return $default !== '' ? $default : $key;
}

function _e($key, $default = '') {
    echo htmlspecialchars(__($key, $default), ENT_QUOTES, 'UTF-8');
}

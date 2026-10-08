<?php
// includes/lang.php - Multilingual Support System (English, Kannada, Hindi)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$supported_languages = ['en', 'kn', 'hi'];

// Function to set active language dynamically
if (!function_exists('setLanguage')) {
    function setLanguage($lang) {
        global $current_lang, $translations, $translations_en, $supported_languages;
        if (in_array($lang, $supported_languages)) {
            $_SESSION['lang'] = $lang;
            $current_lang = $lang;
        } else {
            $current_lang = 'en';
        }
        $base_file = __DIR__ . '/../lang/en.php';
        $translations_en = file_exists($base_file) ? require $base_file : [];
        $lang_file = __DIR__ . "/../lang/{$current_lang}.php";
        $translations = file_exists($lang_file) ? require $lang_file : $translations_en;
    }
}

// Update language if requested via GET
if (isset($_GET['lang']) && in_array($_GET['lang'], $supported_languages)) {
    $_SESSION['lang'] = $_GET['lang'];
}

// Current language code (default: English)
$current_lang = $_SESSION['lang'] ?? 'en';
if (!in_array($current_lang, $supported_languages)) {
    $current_lang = 'en';
    $_SESSION['lang'] = 'en';
}

// Load primary English file as fallback base
$base_file = __DIR__ . '/../lang/en.php';
$translations_en = file_exists($base_file) ? require $base_file : [];

// Load selected language file
$lang_file = __DIR__ . "/../lang/{$current_lang}.php";
$translations = file_exists($lang_file) ? require $lang_file : $translations_en;

/**
 * Translate helper function
 * @param string $key Translation key
 * @param string|null $default Fallback string if key not found
 * @return string
 */
if (!function_exists('t')) {
    function t($key, $default = null) {
        global $translations, $translations_en;
        if (isset($translations[$key]) && $translations[$key] !== '') {
            return $translations[$key];
        }
        if (isset($translations_en[$key]) && $translations_en[$key] !== '') {
            return $translations_en[$key];
        }
        return $default !== null ? $default : $key;
    }
}

/**
 * Get active language code
 */
if (!function_exists('getCurrentLang')) {
    function getCurrentLang() {
        global $current_lang;
        return $current_lang;
    }
}

/**
 * Get readable label for language code
 */
if (!function_exists('getLangLabel')) {
    function getLangLabel($code) {
        $names = [
            'en' => 'English',
            'kn' => 'ಕನ್ನಡ (Kannada)',
            'hi' => 'हिन्दी (Hindi)'
        ];
        return $names[$code] ?? $code;
    }
}
?>

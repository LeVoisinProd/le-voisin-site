<?php
/**
 * Le Voisin — amorçage de l'application (site public et administration).
 */

define('LV_ROOT', dirname(__DIR__));
define('LV_APP', __DIR__);
define('LV_UPLOADS', LV_ROOT . '/uploads');

// ---- Configuration ---------------------------------------------------------
if (!is_file(LV_ROOT . '/config.php')) {
    // Site pas encore installé
    if (is_dir(LV_ROOT . '/install')) {
        header('Location: install/');
        exit('Site not installed. <a href="install/">Run the installer</a>.');
    }
    http_response_code(500);
    exit('Missing config.php');
}
$GLOBALS['LV_CONFIG'] = require LV_ROOT . '/config.php';

function cfg(string $key, $default = null)
{
    $parts = explode('.', $key);
    $val = $GLOBALS['LV_CONFIG'];
    foreach ($parts as $p) {
        if (!is_array($val) || !array_key_exists($p, $val)) return $default;
        $val = $val[$p];
    }
    return $val;
}

error_reporting(E_ALL);
if (cfg('debug')) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LV_APP . '/logs/php-error.log');
}

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Zurich');

// ---- Autoload ---------------------------------------------------------------
spl_autoload_register(function (string $class): void {
    foreach (['/lib/', '/models/'] as $dir) {
        $file = LV_APP . $dir . $class . '.php';
        if (is_file($file)) { require $file; return; }
    }
});

DB::init(cfg('db'));

// ---- Aides globales ---------------------------------------------------------

/** Échappement HTML. */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** URL absolue du site. url('/fr/projets') */
function url(string $path = ''): string
{
    $base = rtrim(cfg('base_url', ''), '/');
    if ($path === '') return $base . '/';
    return $base . '/' . ltrim($path, '/');
}

/** URL d'un fichier uploadé. */
function upload_url(string $path): string
{
    return url('/uploads/' . ltrim($path, '/'));
}

function redirect(string $to): never
{
    header('Location: ' . (str_starts_with($to, 'http') ? $to : url($to)));
    exit;
}

function json_out($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Transforme un texte en slug URL. */
function slugify(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $map = ['à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','å'=>'a','ç'=>'c','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'î'=>'i','ï'=>'i','í'=>'i','ô'=>'o','ö'=>'o','ó'=>'o','õ'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
            'ÿ'=>'y','ñ'=>'n','œ'=>'oe','æ'=>'ae','ß'=>'ss','&'=>'-'];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'n-a';
}

/** Démarre la session (cookie sécurisé) si besoin. */
function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('lv_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

/** Valeur d'un réglage du CMS (table settings). */
function setting(string $key, string $default = ''): string
{
    return Settings::get($key, $default);
}

/**
 * Un réglage qui porte un SECRET, déchiffré au vol. [16.08.2026]
 *
 * POURQUOI IL EXISTE. Relevé ce jour-là: `smtp_pass` et `skribble_api_key`
 * étaient en clair dans `settings`. Un dump de cette base est du matériel
 * sensible — la journée l'a établi pour les AVS et les IBAN — et un mot de
 * passe SMTP qui part dans une sauvegarde sert à envoyer du courrier au nom du
 * Voisin sans que rien ne le signale.
 *
 * IL LIT LES DEUX FORMES. Une valeur écrite avant le chiffrement n'a pas le
 * préfixe `sb1:` et sort telle quelle; elle est remplacée dès qu'on ressaisit
 * le réglage. C'est la migration silencieuse des fiches de collaborateur, et
 * elle a le même défaut connu: elle ne se termine pas toute seule. D'où
 * `db/chiffrer_reglages.php`, qui la termine d'un coup.
 *
 * NE PAS L'UTILISER POUR UN MOT DE PASSE QU'ON NE FAIT QUE VÉRIFIER. Le mot de
 * passe du Catalogue, lui, se hache: on n'a jamais besoin de le relire, et un
 * haché ne se déchiffre pas même avec la clef du `config.php`.
 */
function secret(string $key, string $default = ''): string
{
    $v = trim(Settings::get($key, ''));
    if ($v === '') return $default;
    if (!str_starts_with($v, 'sb1:')) return $v;
    $clair = Crypto::dechiffrer($v);
    return $clair === null ? $default : trim($clair);
}

/** Range un secret, chiffré. Une chaîne vide efface. */
function set_secret(string $key, string $valeur): void
{
    $valeur = trim($valeur);
    Settings::set($key, $valeur === '' ? '' : Crypto::chiffrer($valeur));
}

/** Réglage bilingue : setting_i18n('footer_note') → footer_note_fr / _en avec repli. */
function setting_i18n(string $key, string $default = ''): string
{
    $val = Settings::get($key . '_' . I18n::$lang, '');
    if ($val === '') $val = Settings::get($key . '_' . I18n::$default, $default);
    return $val;
}

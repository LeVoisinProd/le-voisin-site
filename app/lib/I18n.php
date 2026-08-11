<?php
/**
 * Gestion des langues.   [V13-MOTDEPASSE] — t() accepte des valeurs, comme ta()
 *
 * Deux langues cohabitent, et il ne faut surtout pas les confondre :
 *
 *  — la langue du SITE (I18n::$lang) : celle du visiteur, celle dans laquelle
 *    les contenus sont affichés. La première langue de la configuration est la
 *    langue par défaut : si un contenu n'est pas traduit, c'est elle qui sort.
 *
 *  — la langue de l'ADMINISTRATION (I18n::$admin) : celle des menus, des
 *    boutons et des explications du CMS. Elle appartient à la personne
 *    connectée et n'a aucun effet sur le site public. Le français en est la
 *    langue de référence : un libellé oublié s'affiche en français plutôt que
 *    de disparaître.
 *
 * Deux raccourcis servent à traduire l'administration :
 *    ta('nav_dash')            → un libellé du dictionnaire app/i18n/admin.*.php
 *    tc(['fr' => …, 'en' => …]) → un libellé écrit dans un fichier de configuration
 */
class I18n
{
    public static string $lang = 'en';
    public static string $default = 'en';
    /** @var string[] */
    public static array $langs = ['en', 'fr'];
    private static array $dict = [];

    /** Langue de l'interface d'administration. */
    public static string $admin = 'fr';
    /** Langue de référence de l'administration : le repli quand un libellé manque. */
    public const ADMIN_DEFAULT = 'fr';
    /** Langues proposées pour l'administration. */
    public const ADMIN_LANGS = ['fr', 'en'];

    /**
     * Langue de l'interface réellement servie : celle de l'administration sur
     * les pages du CMS, celle du visiteur sur le site et l'espace collaborateur.
     * Elle sert aux messages techniques communs aux deux (« Fichier trop lourd »,
     * « Format accepté : … ») affichés par tu().
     */
    public static string $ui = 'fr';
    private static array $adict = [];

    public static function init(): void
    {
        $langs = cfg('languages', ['en', 'fr']);
        self::$langs = $langs;
        self::$default = $langs[0];
        self::$lang = $langs[0];
        self::$ui = $langs[0];
    }

    public static function setLang(string $lang): void
    {
        if (in_array($lang, self::$langs, true)) { self::$lang = $lang; self::$ui = $lang; }
    }

    /** La langue demandée par le navigateur, parmi celles du site. */
    public static function browserLang(): string
    {
        $header = mb_strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        foreach (explode(',', $header) as $part) {
            $code = substr(trim($part), 0, 2);
            if (in_array($code, self::$langs, true)) return $code;
        }
        return self::$default;
    }

    // ---------------------------------------------------------------
    // Administration
    // ---------------------------------------------------------------

    /**
     * Choisit la langue de l'administration, dans cet ordre :
     * le paramètre ?lang= (clic sur le sélecteur), puis la session, puis le
     * cookie (pour retrouver sa langue après une déconnexion), puis le français.
     */
    public static function initAdmin(): void
    {
        $sources = [
            $_GET['lang']            ?? null,
            $_SESSION['lv_alang']    ?? null,
            $_COOKIE['lv_alang']     ?? null,
        ];
        foreach ($sources as $lang) {
            if (is_string($lang) && in_array($lang, self::ADMIN_LANGS, true)) {
                self::setAdmin($lang);
                return;
            }
        }
        self::setAdmin(self::ADMIN_DEFAULT);
    }

    /** Fixe la langue de l'administration et la retient (session + cookie d'un an). */
    public static function setAdmin(string $lang): void
    {
        if (!in_array($lang, self::ADMIN_LANGS, true)) $lang = self::ADMIN_DEFAULT;
        self::$admin = $lang;
        self::$ui = $lang;
        if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['lv_alang'] = $lang;
        if (!headers_sent() && (string)($_COOKIE['lv_alang'] ?? '') !== $lang) {
            setcookie('lv_alang', $lang, [
                'expires'  => time() + 31536000,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
            $_COOKIE['lv_alang'] = $lang;
        }
    }

    /** Libellé de l'administration (app/i18n/admin.{lang}.php). */
    public static function ta(string $key, ?string $lang = null): string
    {
        $lang ??= self::$admin;
        if (!isset(self::$adict[$lang])) {
            $file = LV_APP . '/i18n/admin.' . $lang . '.php';
            self::$adict[$lang] = is_file($file) ? require $file : [];
        }
        if (isset(self::$adict[$lang][$key])) return self::$adict[$lang][$key];
        if ($lang !== self::ADMIN_DEFAULT) return self::ta($key, self::ADMIN_DEFAULT);
        return $key;
    }

    /**
     * Libellé venant d'un fichier de configuration.
     * Accepte soit ['fr' => …, 'en' => …], soit une simple chaîne — laissée
     * telle quelle, pour qu'une configuration pas encore traduite continue
     * de fonctionner sans rien casser.
     */
    public static function tc($value, ?string $lang = null): string
    {
        if (!is_array($value)) return (string)$value;
        $lang ??= self::$admin;
        if (isset($value[$lang]))                return (string)$value[$lang];
        if (isset($value[self::ADMIN_DEFAULT]))  return (string)$value[self::ADMIN_DEFAULT];
        $first = reset($value);
        return is_string($first) ? $first : '';
    }

    /**
     * Fait le même travail que tc() sur toute une liste d'options
     * ['clef' => ['fr' => …, 'en' => …]] → ['clef' => 'libellé'].
     */
    public static function tcList(array $list, ?string $lang = null): array
    {
        $out = [];
        foreach ($list as $k => $v) $out[$k] = self::tc($v, $lang);
        return $out;
    }

    /**
     * Champ bilingue avec repli sur la langue par défaut.
     * f($row, 'title') → $row['title_fr'] sinon $row['title_en'].
     */
    public static function f(?array $row, string $field, ?string $lang = null): string
    {
        if (!$row) return '';
        $lang ??= self::$lang;
        $val = trim((string)($row[$field . '_' . $lang] ?? ''));
        if ($val === '' && $lang !== self::$default) {
            $val = trim((string)($row[$field . '_' . self::$default] ?? ''));
        }
        return $val;
    }

    /** Libellé d'interface traduit (app/i18n/{lang}.php). */
    public static function t(string $key, ?string $lang = null): string
    {
        $lang ??= self::$lang;
        if (!isset(self::$dict[$lang])) {
            $file = LV_APP . '/i18n/' . $lang . '.php';
            self::$dict[$lang] = is_file($file) ? require $file : [];
        }
        if (isset(self::$dict[$lang][$key])) return self::$dict[$lang][$key];
        if ($lang !== self::$default) return self::t($key, self::$default);
        return $key;
    }
}

/** Raccourcis globaux. */
function f(?array $row, string $field, ?string $lang = null): string
{
    return I18n::f($row, $field, $lang);
}
/**
 * Libellé du site public. Comme ta(), les valeurs suivantes remplacent les
 * %s / %d du libellé : t('member_pw_intro', $email). Sans valeur, le libellé
 * sort tel quel — un « % » écrit dans un texte reste donc intact.
 */
function t(string $key, ...$args): string
{
    $s = I18n::t($key);
    return $args ? vsprintf($s, $args) : $s;
}

/**
 * Libellé de l'administration. Les valeurs suivantes remplacent les %s / %d
 * du libellé : ta('lst_del_confirm', $titre).
 */
function ta(string $key, ...$args): string
{
    $s = I18n::ta($key);
    return $args ? vsprintf($s, $args) : $s;
}

/** Libellé bilingue écrit dans un fichier de configuration. */
function tc($value): string
{
    return I18n::tc($value);
}

/**
 * Message technique commun à l'administration et à l'espace collaborateur
 * (erreurs de téléversement, formats acceptés…). Il est écrit dans les mêmes
 * dictionnaires que l'administration — app/i18n/admin.fr.php et admin.en.php,
 * clefs « sys_… » — mais s'affiche dans la langue de l'interface en cours :
 * celle du CMS côté administration, celle du visiteur côté espace.
 */
function tu(string $key, ...$args): string
{
    $s = I18n::ta($key, I18n::$ui);
    return $args ? vsprintf($s, $args) : $s;
}

/**
 * Champ bilingue d'un contenu, affiché DANS L'ADMINISTRATION.
 * Identique à f(), mais suit la langue du CMS et non celle du site : quand
 * Anna travaille en français, les titres de pages et de projets s'affichent
 * en français. Si la traduction manque, la langue par défaut du site sort.
 */
function fa(?array $row, string $field): string
{
    return I18n::f($row, $field, I18n::$admin);
}

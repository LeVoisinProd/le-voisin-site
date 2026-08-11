<?php
/**
 * Authentification de l'administration + protection CSRF.
 * [V10-CMS-BILINGUE] — message de jeton invalide traduit (clef « sys_csrf »).
 */
class Auth
{
    public const MAX_ATTEMPTS = 10;   // essais par IP
    public const WINDOW_MIN   = 10;   // fenêtre en minutes

    public static function user(): ?array
    {
        session_boot();
        if (empty($_SESSION['lv_admin_id'])) return null;
        static $u = false;
        if ($u === false) {
            $u = DB::one('SELECT id, email, name FROM users WHERE id = ?', [$_SESSION['lv_admin_id']]);
        }
        return $u ?: null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function ip(): string
    {
        return substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 60);
    }

    public static function throttled(): bool
    {
        $n = (int)DB::val(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at > (NOW() - INTERVAL ' . self::WINDOW_MIN . ' MINUTE)',
            [self::ip()]
        );
        return $n >= self::MAX_ATTEMPTS;
    }

    public static function login(string $email, string $pass): bool
    {
        session_boot();
        if (self::throttled()) return false;
        $u = DB::one('SELECT * FROM users WHERE email = ?', [trim(mb_strtolower($email))]);
        if (!$u || !password_verify($pass, $u['pass_hash'])) {
            DB::insert('login_attempts', ['ip' => self::ip(), 'email' => substr($email, 0, 180)]);
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['lv_admin_id'] = (int)$u['id'];
        DB::run('UPDATE users SET last_login = NOW() WHERE id = ?', [$u['id']]);
        DB::delete('login_attempts', 'ip = ?', [self::ip()]);
        if (password_needs_rehash($u['pass_hash'], PASSWORD_DEFAULT)) {
            DB::update('users', ['pass_hash' => password_hash($pass, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
        }
        return true;
    }

    public static function logout(): void
    {
        session_boot();
        unset($_SESSION['lv_admin_id']);
        session_regenerate_id(true);
    }

    /** À appeler en tête de chaque page d'administration. */
    public static function requireAdmin(bool $api = false): void
    {
        if (self::check()) return;
        if ($api) json_out(['error' => 'auth'], 401);
        redirect('/admin/login.php');
    }

    // ---- CSRF ----------------------------------------------------------------

    public static function csrf(): string
    {
        session_boot();
        if (empty($_SESSION['lv_csrf'])) $_SESSION['lv_csrf'] = bin2hex(random_bytes(16));
        return $_SESSION['lv_csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::csrf()) . '">';
    }

    public static function csrfOk(?string $token = null): bool
    {
        session_boot();
        $token ??= $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
        return !empty($_SESSION['lv_csrf']) && is_string($token) && hash_equals($_SESSION['lv_csrf'], $token);
    }

    /** Interrompt la requête si le jeton CSRF est absent/faux. */
    public static function requireCsrf(bool $api = false): void
    {
        if (self::csrfOk()) return;
        /* 403 et non 419 : Apache ne connaît que les codes du barème. Il note
           bien 419 dans son journal, mais met « 500 Internal Server Error » sur
           le fil — la personne reçoit alors une page d'erreur serveur au lieu
           de l'explication, et certains hébergeurs remplacent le corps d'un 500
           par le leur, ce qui l'efface tout à fait. */
        if ($api) json_out(['error' => 'csrf'], 403);
        http_response_code(403);
        exit(tu('sys_csrf'));
    }
}

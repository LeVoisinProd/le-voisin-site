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

        if (password_needs_rehash($u['pass_hash'], PASSWORD_DEFAULT)) {
            DB::update('users', ['pass_hash' => password_hash($pass, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
        }

        /* ── LE MOT DE PASSE NE SUFFIT PLUS QUAND LE DEUXIÈME FACTEUR EST POSÉ.
           [revue de sécurité, 22.08.2026, point 3]

           LA SESSION N'EST PAS OUVERTE ICI dans ce cas: on retient seulement
           QUI vient de prouver son mot de passe, dans une clef qui n'ouvre rien.
           Tant que le code n'est pas donné, `lv_admin_id` reste absent et
           `Auth::check()` répond non — donc aucune page du dashboard ni de
           l'administration ne s'ouvre. C'est la seule façon sûre de faire deux
           étapes: la première n'accorde rien du tout.

           Les tentatives ne sont pas effacées non plus: le compteur ne retombe
           qu'une fois les deux facteurs donnés. */
        if ((int)($u['totp_actif'] ?? 0) === 1 && trim((string)($u['totp_secret'] ?? '')) !== '') {
            $_SESSION['lv_2fa_id']   = (int)$u['id'];
            $_SESSION['lv_2fa_vu']   = time();
            return true;
        }

        $_SESSION['lv_admin_id'] = (int)$u['id'];
        DB::run('UPDATE users SET last_login = NOW() WHERE id = ?', [$u['id']]);
        DB::delete('login_attempts', 'ip = ?', [self::ip()]);
        return true;
    }

    /** Le mot de passe est prouvé, le code ne l'est pas encore. */
    public static function attendCode(): bool
    {
        session_boot();
        if (empty($_SESSION['lv_2fa_id'])) return false;
        /* CINQ MINUTES POUR SORTIR SON TÉLÉPHONE, pas davantage. Une étape
           laissée ouverte est un mot de passe prouvé qui attend sur un poste
           qu'on a peut-être quitté. */
        if (time() - (int)($_SESSION['lv_2fa_vu'] ?? 0) > 300) {
            unset($_SESSION['lv_2fa_id'], $_SESSION['lv_2fa_vu']);
            return false;
        }
        return true;
    }

    /**
     * Deuxième étape: le code. Ouvre la session s'il est juste.
     *
     * LE PAS ACCEPTÉ EST RETENU, et c'est ce qui empêche de rejouer un code.
     * Un code vaut trente secondes; sans cette mémoire, quelqu'un qui l'a vu
     * par-dessus une épaule s'en sert dans la foulée.
     */
    public static function loginCode(string $code): bool
    {
        session_boot();
        if (!self::attendCode()) return false;
        if (self::throttled()) return false;

        $id = (int)$_SESSION['lv_2fa_id'];
        $u  = DB::one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$u) return false;

        $secret = Crypto::dechiffrer((string)($u['totp_secret'] ?? ''));
        $pas = $secret === '' ? null
             : Totp::verifier($secret, $code, $u['totp_dernier_pas'] !== null ? (int)$u['totp_dernier_pas'] : null);

        if ($pas === null) {
            DB::insert('login_attempts', ['ip' => self::ip(), 'email' => substr((string)$u['email'], 0, 180)]);
            return false;
        }

        session_regenerate_id(true);
        unset($_SESSION['lv_2fa_id'], $_SESSION['lv_2fa_vu']);
        $_SESSION['lv_admin_id'] = $id;
        DB::update('users', ['totp_dernier_pas' => $pas], 'id = ?', [$id]);
        DB::run('UPDATE users SET last_login = NOW() WHERE id = ?', [$id]);
        DB::delete('login_attempts', 'ip = ?', [self::ip()]);
        return true;
    }

    public static function logout(): void
    {
        session_boot();
        unset($_SESSION['lv_admin_id'], $_SESSION['lv_2fa_id'], $_SESSION['lv_2fa_vu']);
        session_regenerate_id(true);
    }

    /**
     * Qui entre dans quelle partie de l'administration du site.
     * [22.08.2026, après la faille trouvée par Anna]
     *
     * ELLE EST ÉCRITE COMME CELLE DU DASHBOARD, et pour la même raison: une
     * table qu'on lit d'un regard vaut mieux qu'une condition dispersée dans
     * douze fichiers. Le défaut est le refus — une zone non déclarée n'est
     * ouverte à personne d'autre que la direction.
     *
     * `entite:projects` EST OUVERTE À LA PRODUCTION. Anna: « Mirta peut éditer
     * la partie projets ». Ce sont les fiches du catalogue public — titre,
     * présentation, distribution, images. C'est le travail de production, et
     * c'est déjà elle qui écrit ces mêmes pièces dans le dashboard.
     *
     * CE QUI RESTE FERMÉ, ET POURQUOI. Les comptes et les réglages, parce qu'y
     * toucher c'est se donner des droits. Les collaborateurs et leurs documents,
     * parce que ce sont des contrats, des fiches de salaire et des pièces
     * d'identité — le même contenu que l'écran Personnel, fermé de l'autre côté.
     * Le journal des accès, parce qu'il dit qui a lu quoi. Et les pages du site,
     * parce qu'une page publique engage la maison autrement qu'une fiche.
     */
    public const ADMIN_ACCES = [
        'dash'             => ['direction', 'production'],
        'entite:projects'  => ['direction', 'production'],
    ];

    /** Le rôle du compte connecté, lu en base. '' si personne n'est connecté. */
    public static function role(): string
    {
        $u = self::user();
        if (!$u) return '';
        static $r = null;
        if ($r === null) $r = (string)(DB::val('SELECT role_dash FROM users WHERE id = ?', [(int)$u['id']]) ?: '');
        return $r;
    }

    /**
     * À appeler en tête de chaque page d'administration.
     *
     * ELLE EXIGE MAINTENANT LE RÔLE `direction`.  [22.08.2026, trouvé par Anna]
     *
     * « o perfil da Alessandra no CMS vê tudo como se fosse eu, ela pode até
     * mudar as palavras-chave. »
     *
     * ELLE AVAIT RAISON, ET C'ÉTAIT PIRE QUE ÇA. Les douze pages de `/admin/`
     * n'exigeaient que `Auth::check()` — « cette personne a un compte du
     * bureau » — et aucune ne regardait le rôle. La grille de droits, si
     * soigneusement écrite, ne protège que le dashboard. L'administration du
     * site, elle, était ouverte à tout compte.
     *
     * CE QUE CELA PERMETTAIT, ET C'EST UNE ÉLÉVATION DE PRIVILÈGE COMPLÈTE:
     * `users.php` change le mot de passe de n'importe qui, y compris celui de la
     * direction. Un compte `production` pouvait donc se donner la direction en
     * deux clics, et de là ouvrir Personnel et ses 91 AVS et IBAN. S'y ajoutaient
     * les fiches des collaborateurs, leurs documents — contrats, salaires,
     * pièces d'identité — les réglages du site et le journal des accès.
     *
     * LE TROU S'EST OUVERT LE MATIN MÊME, en créant deux comptes. Tant qu'il n'y
     * en avait qu'un, la distinction ne se voyait pas. C'est le propre de ce
     * genre de faille: elle n'existe qu'au moment où l'on croit avoir refermé
     * quelque chose.
     *
     * ET MA REVUE DE SÉCURITÉ NE L'A PAS VUE. J'ai vérifié branche par branche
     * la grille du dashboard et écrit « l'accès est vérifié à la porte » — cette
     * porte-là est `dashboard.php`. Je n'ai pas ouvert celle de `/admin/`.
     *
     * `direction` POUR TOUT, ET ON RELÂCHERA ENSUITE. Refuser d'abord et ouvrir
     * page par page quand le besoin se dit est le seul ordre sûr. L'inverse
     * laisse ouvert ce qu'on a oublié de fermer.
     */
    /** Ce rôle peut-il ouvrir cette zone de l'administration? */
    public static function zoneOuverte(string $zone, ?string $role = null): bool
    {
        $r = $role ?? self::role();
        if ($r === 'direction') return true;
        return $r !== '' && in_array($r, self::ADMIN_ACCES[$zone] ?? [], true);
    }

    public static function requireAdmin(bool $api = false, string $zone = ''): void
    {
        if (self::check() && ($zone !== '' ? self::zoneOuverte($zone) : self::role() === 'direction')) return;
        if (self::check()) {
            /* Connecté mais pas au bon niveau: on le dit, on ne renvoie pas vers
               la page de connexion — qui laisserait croire à une session
               expirée et ferait ressaisir un mot de passe pour rien. */
            if ($api) json_out(['error' => 'role'], 403);
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><meta charset="utf-8"><title>Réservé</title>'
               . '<style>body{font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,'
               . 'Arial,sans-serif;max-width:34rem;margin:16vh auto;padding:0 24px;color:#141414}'
               . 'a{color:#141414}</style>'
               . '<h1 style="font-size:19px">Cette partie est réservée à la direction.</h1>'
               . '<p>L’administration du site — les pages publiques, les comptes, les réglages — '
               . 'ne s’ouvre qu’avec le rôle « direction ». Votre travail se fait dans le '
               . '<a href="/dashboard.php">dashboard</a>.</p>';
            exit;
        }
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

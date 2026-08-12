<?php
/**
 * Authentification des collaborateurs (espace privé, distinct de l'administration).
 * [V13-MOTDEPASSE] — lien à usage unique pour choisir son mot de passe.
 *
 * Un mot de passe n'est jamais conservé tel quel, ici pas plus qu'ailleurs :
 * la base ne garde qu'une empreinte, calculée dans un seul sens. Personne ne
 * peut donc relire le mot de passe d'un collaborateur, pas même
 * l'administration. Pour dépanner quelqu'un qui a perdu le sien, on lui
 * fabrique un lien : voir lienNouveau() plus bas.
 */
class MemberAuth
{
    public const MAX_ATTEMPTS = 8;
    public const WINDOW_MIN   = 10;
    /** Durée de validité d'un lien de choix de mot de passe, en jours. */
    public const LIEN_JOURS   = 7;

    /* [12.08.2026] Combien de temps une session reste ouverte sans rien faire.

       Il n'y en avait aucune limite : le cookie mourait à la fermeture du
       navigateur, et un navigateur qu'on ne ferme jamais gardait la session
       ouverte indéfiniment. Derrière cette porte il y a des IBAN, des numéros
       AVS et des fiches de salaire, souvent consultés depuis un ordinateur
       partagé ou une salle de production.

       Deux heures : assez pour remplir la fiche personnelle sans être coupé au
       milieu — c'est le formulaire le plus long de l'espace —, assez peu pour
       qu'un onglet oublié le soir ne soit plus ouvert le lendemain.

       Le compteur repart à chaque page. Ce n'est donc pas une durée de session
       mais une durée d'inaction, et quelqu'un qui travaille une matinée entière
       n'est jamais interrompu. */
    public const INACTIF_MAX = 7200;

    public static function member(): ?array
    {
        session_boot();
        if (empty($_SESSION['lv_member_id'])) return null;

        /* L'inaction se mesure avant tout le reste : une session périmée ne
           doit pas même faire une requête en base. */
        $vu = (int)($_SESSION['lv_member_vu'] ?? 0);
        if ($vu > 0 && (time() - $vu) > self::INACTIF_MAX) {
            self::logout();
            return null;
        }
        $_SESSION['lv_member_vu'] = time();

        static $m = false;
        if ($m === false) {
            $m = DB::one('SELECT * FROM collaborators WHERE id = ? AND active = 1', [$_SESSION['lv_member_id']]);
        }
        return $m ?: null;
    }

    public static function check(): bool
    {
        return self::member() !== null;
    }

    private static function ip(): string
    {
        return substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 60);
    }

    public static function throttled(): bool
    {
        $n = (int)DB::val(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at > (NOW() - INTERVAL ' . self::WINDOW_MIN . ' MINUTE)',
            ['m:' . self::ip()]
        );
        return $n >= self::MAX_ATTEMPTS;
    }

    public static function login(string $email, string $pass): bool
    {
        session_boot();
        if (self::throttled()) return false;
        $m = DB::one('SELECT * FROM collaborators WHERE email = ? AND active = 1', [trim(mb_strtolower($email))]);
        if (!$m || $m['pass_hash'] === '' || !password_verify($pass, $m['pass_hash'])) {
            DB::insert('login_attempts', ['ip' => 'm:' . self::ip(), 'email' => substr($email, 0, 180)]);
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['lv_member_id'] = (int)$m['id'];
        $_SESSION['lv_member_vu'] = time();
        // [V27-ACCES] Une vraie connexion referme toute visite en cours : sans
        // cela, une personne qui se connecte sur l'ordinateur du bureau
        // hériterait du bandeau de visite laissé par l'administration.
        unset($_SESSION['lv_member_visite']);
        DB::run('UPDATE collaborators SET last_login = NOW() WHERE id = ?', [$m['id']]);
        DB::delete('login_attempts', 'ip = ?', ['m:' . self::ip()]);
        AccessLog::ecrire((int)$m['id'], 'member', null, 'login'); // [V39-JOURNAL]
        if (password_needs_rehash($m['pass_hash'], PASSWORD_DEFAULT)) {
            DB::update('collaborators', ['pass_hash' => password_hash($pass, PASSWORD_DEFAULT)], 'id = ?', [$m['id']]);
        }
        return true;
    }

    public static function logout(): void
    {
        session_boot();
        unset($_SESSION['lv_member_id'], $_SESSION['lv_member_visite'], $_SESSION['lv_member_vu']);
        session_regenerate_id(true);
    }

    // ---- Visite depuis l'administration   [V27-ACCES] -----------------------
    //
    // « Voir son espace » : l'administration ouvre l'espace d'un collaborateur
    // pour vérifier de ses propres yeux ce que la personne y trouve. Aucun mot
    // de passe n'est demandé ni révélé : ce n'est pas une connexion, c'est un
    // regard. C'est la seule façon honnête de répondre à la question « est-ce
    // que cette personne a bien accès ? » — puisque personne, pas même le
    // bureau, ne peut relire un mot de passe pour aller essayer à sa place.
    //
    // Deux précautions portent tout le reste :
    //   — la visite ne touche jamais « last_login ». Sinon la fiche dirait
    //     qu'une personne s'est connectée alors que c'est le bureau qui a
    //     regardé, et l'on perdrait la seule preuve qu'un accès fonctionne ;
    //   — un bandeau reste affiché en haut de chaque page pendant la visite.
    //     On doit savoir à tout instant qu'on est chez quelqu'un d'autre.

    /** Vrai si la page en cours est regardée par l'administration. */
    public static function visite(): bool
    {
        session_boot();
        return !empty($_SESSION['lv_member_visite']) && !empty($_SESSION['lv_member_id']);
    }

    /**
     * Ouvre la visite. Refuse un compte désactivé : l'espace le renverrait de
     * toute façon vers la page de connexion, autant le dire tout de suite.
     */
    public static function visiteOuvrir(int $id): bool
    {
        session_boot();
        $m = DB::one('SELECT id FROM collaborators WHERE id = ? AND active = 1', [$id]);
        if (!$m) return false;
        $_SESSION['lv_member_id']     = (int)$m['id'];
        $_SESSION['lv_member_visite'] = 1;
        $_SESSION['lv_member_vu']     = time();
        // [V39-JOURNAL] Un seul endroit ouvre une visite, quel que soit
        // l'écran qui l'a demandée : la journaliser ici la couvre partout.
        AccessLog::ecrire((int)$m['id'], 'admin', (int)($_SESSION['lv_admin_id'] ?? 0) ?: null, 'visite');
        return true;
    }

    /** Referme la visite. La session d'administration, elle, n'est pas touchée. */
    public static function visiteFermer(): void
    {
        session_boot();
        unset($_SESSION['lv_member_id'], $_SESSION['lv_member_visite']);
    }

    public static function requireMember(): void
    {
        if (self::check()) return;
        redirect('/espace/login.php');
    }

    // ---- Lien de choix du mot de passe --------------------------------------

    /**
     * Fabrique un lien à usage unique. Le nouveau jeton remplace le précédent :
     * une seule adresse est valable à la fois pour une même personne, et elle
     * cesse de fonctionner dès que le mot de passe est choisi.
     */
    public static function lienNouveau(int $id): string
    {
        $jeton = bin2hex(random_bytes(32));
        DB::update('collaborators', [
            'reset_token'   => $jeton,
            'reset_expires' => date('Y-m-d H:i:s', time() + self::LIEN_JOURS * 86400),
        ], 'id = ?', [$id]);
        return $jeton;
    }

    /** Annule le lien en cours sans toucher au mot de passe. */
    public static function lienAnnuler(int $id): void
    {
        DB::update('collaborators', ['reset_token' => null, 'reset_expires' => null], 'id = ?', [$id]);
    }

    /** L'adresse complète à communiquer à la personne. */
    public static function lienUrl(string $jeton): string
    {
        return url('/espace/mot-de-passe.php?jeton=' . urlencode($jeton));
    }

    /** Le collaborateur désigné par ce jeton, si le lien n'a pas expiré. */
    public static function parJeton(string $jeton): ?array
    {
        $jeton = trim($jeton);
        if (!preg_match('/^[a-f0-9]{32,64}$/', $jeton)) return null;
        $m = DB::one(
            'SELECT * FROM collaborators WHERE reset_token = ? AND reset_expires IS NOT NULL AND reset_expires > NOW()',
            [$jeton]
        );
        return $m ?: null;
    }

    /**
     * Enregistre un nouveau mot de passe, referme le lien et lève le blocage
     * des essais manqués — sinon la personne qui vient de choisir son mot de
     * passe se verrait refuser l'entrée par le compteur de tentatives.
     */
    public static function motDePasse(int $id, string $pass): void
    {
        $champs = [
            'pass_hash'     => password_hash($pass, PASSWORD_DEFAULT),
            'reset_token'   => null,
            'reset_expires' => null,
        ];
        try {
            DB::update('collaborators', $champs + ['pass_set_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        } catch (Throwable $e) {
            // « pass_set_at » n'existe qu'après « Mettre à jour la base » :
            // sans cette colonne on enregistre quand même le mot de passe.
            DB::update('collaborators', $champs, 'id = ?', [$id]);
        }
        $email = (string)DB::val('SELECT email FROM collaborators WHERE id = ?', [$id]);
        if ($email !== '') DB::delete('login_attempts', 'email = ?', [$email]);
    }

    // ---- CSRF (jeton dédié à l'espace) --------------------------------------
    public static function csrf(): string
    {
        session_boot();
        if (empty($_SESSION['lv_member_csrf'])) $_SESSION['lv_member_csrf'] = bin2hex(random_bytes(16));
        return $_SESSION['lv_member_csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::csrf()) . '">';
    }

    public static function requireCsrf(): void
    {
        session_boot();
        $token = $_POST['_csrf'] ?? '';
        if (!empty($_SESSION['lv_member_csrf']) && is_string($token) && hash_equals($_SESSION['lv_member_csrf'], $token)) return;
        /* 403 : voir Auth::requireCsrf(). Apache traduit un 419 par un 500. */
        http_response_code(403);
        exit(tu('sys_csrf'));
    }
}

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
    /** Durée de validité d'un lien de choix de mot de passe, en jours. Conservée
        pour les appels qui la demandent encore ; l'invitation, elle, n'expire plus. */
    public const LIEN_JOURS   = 7;

    /* [13.08.2026] Combien de temps le navigateur garde la personne reconnue.

       Depuis que l'entrée se fait par courriel et qu'il n'y a plus de mot de
       passe, une session de deux heures obligerait à redemander une clé
       plusieurs fois par jour. Un mois est le compromis : on redemande une clé
       en changeant de machine, ou après une longue absence, pas en revenant
       l'après-midi.

       Ce souvenir tient dans un cookie signé, et non dans une colonne : la base
       n'est pas modifiée, donc le paquet n'a pas besoin de « Mettre à jour la
       base ». Le prix est assumé et écrit ici pour qu'il ne se redécouvre pas :
       on ne peut pas expulser UN navigateur en particulier. Désactiver un
       compte, en revanche, coupe l'accès partout, parce que member() ne charge
       que les comptes actifs. */
    public const SOUVENIR_JOURS = 30;
    private const SOUVENIR_NOM  = 'lv_souvenir';

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
            /* [13.08.2026] La session se ferme, LE SOUVENIR RESTE. C'est ce qui
               concilie les deux décisions : celle du 12.08, une session qui ne
               dure pas indéfiniment derrière une porte où il y a des IBAN, et
               celle du 13.08, un navigateur reconnu pendant un mois.
               La personne qui revient ne réécrit pas son adresse et n'attend
               aucun courriel : la page d'entrée la reconnaît et lui propose un
               bouton. Un geste, mais un geste conscient, ce qui est justement
               ce qu'un écran resté ouvert ne fait pas tout seul. */
            self::sessionFermer();
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

    /**
     * Trop d'essais depuis cette adresse IP, sur ce guichet.
     *
     * [13.08.2026] Le préfixe devient un argument. Chaque guichet compte à part :
     * « m: » l'ancien mot de passe, « e: » la demande de clé d'entrée. Sans cela,
     * quelqu'un qui redemande sa clé six fois bloquerait aussi la connexion de
     * l'administration, et le compteur d'un guichet servirait de bélier contre
     * l'autre. Même découpage que le Catalogue, qui compte sous « c: ».
     */
    public static function throttled(string $prefixe = 'm:'): bool
    {
        $n = (int)DB::val(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at > (NOW() - INTERVAL ' . self::WINDOW_MIN . ' MINUTE)',
            [$prefixe . self::ip()]
        );
        return $n >= self::MAX_ATTEMPTS;
    }

    /**
     * Compte un essai. Appelé QUELLE QUE SOIT l'issue de la demande de clé.
     *
     * C'est la condition pour ne pas renseigner l'attaquant : si l'on ne
     * comptait que les adresses inconnues, la vitesse de la réponse dirait
     * lesquelles existent, et le travail de la réponse unique serait perdu.
     */
    public static function noter(string $prefixe, string $email = ''): void
    {
        DB::insert('login_attempts', ['ip' => $prefixe . self::ip(), 'email' => substr($email, 0, 180)]);
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

    /**
     * Ouvre la session d'un collaborateur, sans mot de passe.   [13.08.2026]
     *
     * C'est login() moins la vérification du mot de passe : la preuve d'identité
     * a été apportée ailleurs, par une clé reçue dans la boîte aux lettres de la
     * personne. Tout le reste est identique, et volontairement : l'identifiant
     * de session est renouvelé, une visite en cours est refermée, la date de
     * dernière connexion est écrite, et le journal reçoit la ligne.
     *
     * Cette méthode ne vérifie rien. Elle est appelée après une clé valide ou un
     * souvenir valide, et c'est à l'appelant de l'avoir établi.
     */
    public static function entrer(int $id, bool $souvenir = true): bool
    {
        session_boot();
        $m = DB::one('SELECT * FROM collaborators WHERE id = ? AND active = 1', [$id]);
        if (!$m) return false;

        session_regenerate_id(true);
        $_SESSION['lv_member_id'] = (int)$m['id'];
        $_SESSION['lv_member_vu'] = time();
        unset($_SESSION['lv_member_visite']);
        DB::run('UPDATE collaborators SET last_login = NOW() WHERE id = ?', [$m['id']]);
        DB::delete('login_attempts', 'ip = ?', ['e:' . self::ip()]);
        AccessLog::ecrire((int)$m['id'], 'member', null, 'login');
        if ($souvenir) self::souvenirPoser((int)$m['id']);
        return true;
    }

    // ---- Le souvenir du navigateur   [13.08.2026] ---------------------------
    //
    // Un cookie signé, valable trente jours, qui dit « ce navigateur est celui
    // d'une personne qui a déjà prouvé son identité ». Il ne contient aucun
    // secret : seulement un identifiant, une date limite, et une signature qui
    // interdit d'en changer un caractère.
    //
    // La clé de signature est celle de config.php, isolée par un suffixe qui
    // lui est propre, comme le fait déjà Crypto pour les IBAN. On la LIT, on n'y
    // touche jamais : la modifier rendrait illisibles tous les IBAN et tous les
    // numéros AVS déjà chiffrés, en silence.

    private static function souvenirSigne(int $id, int $fin): string
    {
        return hash_hmac('sha256', $id . '|' . $fin, (string)cfg('secret', '') . '|member-souvenir-v1');
    }

    public static function souvenirPoser(int $id): void
    {
        $fin = time() + self::SOUVENIR_JOURS * 86400;
        setcookie(self::SOUVENIR_NOM, $id . '.' . $fin . '.' . self::souvenirSigne($id, $fin), [
            'expires'  => $fin,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * L'identifiant que porte le souvenir, ou null.
     *
     * `hash_equals` et non `===` : la comparaison de deux signatures se fait en
     * temps constant, sinon la durée de la réponse renseigne sur le nombre de
     * caractères devinés.
     */
    public static function souvenirLire(): ?int
    {
        $v = (string)($_COOKIE[self::SOUVENIR_NOM] ?? '');
        if ($v === '' || substr_count($v, '.') !== 2) return null;
        [$id, $fin, $sig] = explode('.', $v);
        if (!ctype_digit($id) || !ctype_digit($fin)) return null;
        if ((int)$fin < time()) return null;
        if (!hash_equals(self::souvenirSigne((int)$id, (int)$fin), $sig)) return null;
        return (int)$id;
    }

    public static function souvenirEffacer(): void
    {
        setcookie(self::SOUVENIR_NOM, '', ['expires' => time() - 3600, 'path' => '/']);
        unset($_COOKIE[self::SOUVENIR_NOM]);
    }

    /** Ferme la session en cours. Le souvenir du navigateur n'est pas touché. */
    private static function sessionFermer(): void
    {
        session_boot();
        unset($_SESSION['lv_member_id'], $_SESSION['lv_member_visite'], $_SESSION['lv_member_vu']);
        session_regenerate_id(true);
    }

    /**
     * Se déconnecter pour de bon : la session ET le souvenir.
     *
     * Sans l'oubli du souvenir, le bouton « Se déconnecter » n'aurait aucun
     * effet visible, puisque la page suivante reconnaîtrait le navigateur et
     * proposerait de rentrer d'un clic. C'est le geste de quelqu'un qui quitte
     * un ordinateur partagé, et il doit tenir.
     */
    public static function logout(): void
    {
        self::sessionFermer();
        self::souvenirEffacer();
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
        redirect('/espace/entrer.php');
    }

    // ---- Lien de choix du mot de passe --------------------------------------

    /**
     * Fabrique une clé d'entrée à usage unique. La nouvelle remplace la
     * précédente : une seule adresse est valable à la fois pour une personne,
     * et elle cesse de fonctionner dès qu'elle a servi.
     *
     * [13.08.2026] L'échéance devient un argument, parce que les deux usages
     * n'ont pas du tout le même besoin :
     *
     *   — L'INVITATION, celle qu'on envoie à toute l'équipe, n'expire pas.
     *     Décision d'Anna, et la raison est le mois d'août : une clé de sept
     *     jours envoyée à soixante-dix-sept personnes en vacances arrive morte,
     *     et chacune de ces personnes doit alors écrire au bureau. Elle meurt
     *     en servant, ce qui reste la vraie protection.
     *
     *   — LA CLÉ DEMANDÉE depuis la page d'entrée vaut trente minutes. Elle est
     *     demandée et utilisée dans la même minute ; une durée courte ne gêne
     *     personne et referme la fenêtre pour une boîte aux lettres lue par
     *     quelqu'un d'autre.
     *
     * @param int|null $minutes Durée de validité, ou null pour aucune échéance.
     */
    public static function lienNouveau(int $id, ?int $minutes = null): string
    {
        $jeton = bin2hex(random_bytes(32));
        DB::update('collaborators', [
            'reset_token'   => $jeton,
            'reset_expires' => $minutes === null
                ? null
                : date('Y-m-d H:i:s', time() + $minutes * 60),
        ], 'id = ?', [$id]);
        return $jeton;
    }

    /** Annule le lien en cours sans toucher au mot de passe. */
    public static function lienAnnuler(int $id): void
    {
        DB::update('collaborators', ['reset_token' => null, 'reset_expires' => null], 'id = ?', [$id]);
    }

    /** L'adresse complète à communiquer à la personne. */
    /**
     * L'adresse complète d'une clé.
     *
     * [13.08.2026] Elle mène à entrer.php et non plus à mot-de-passe.php : la
     * clé fait entrer, elle ne fait plus choisir un mot de passe. Les clés déjà
     * dans une boîte aux lettres continuent de fonctionner, parce que
     * mot-de-passe.php renvoie vers la nouvelle porte en gardant le jeton.
     */
    public static function lienUrl(string $jeton): string
    {
        return url('/espace/entrer.php?jeton=' . urlencode($jeton));
    }

    /** Le collaborateur désigné par ce jeton, si le lien n'a pas expiré. */
    public static function parJeton(string $jeton): ?array
    {
        $jeton = trim($jeton);
        if (!preg_match('/^[a-f0-9]{32,64}$/', $jeton)) return null;
        /* [13.08.2026] `reset_expires` à NULL veut désormais dire « n'expire
           pas », et non plus « pas de lien ». C'est ce que la condition disait
           avant, et c'est ce qui aurait rejeté toutes les invitations. Le jeton
           lui-même reste obligatoire : une ligne sans jeton ne remonte pas,
           puisque la comparaison porte dessus. */
        $m = DB::one(
            'SELECT * FROM collaborators WHERE reset_token = ?'
          . ' AND (reset_expires IS NULL OR reset_expires > NOW())',
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

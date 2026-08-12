<?php
/**
 * La porte du Catalogue — un mot de passe unique, partagé.   [V42-CATALOGUE]
 *
 * Ce n'est ni Auth (le bureau, comptes nominatifs) ni MemberAuth (les
 * collaborateurs, un compte chacun). C'est une troisième chose, et elle est
 * délibérément plus simple : un seul mot de passe, le même pour tout le monde,
 * qu'on écrit dans un e-mail à un programmateur.
 *
 * Pourquoi ne pas donner un compte à chaque programmateur. Parce qu'on écrit à
 * des dizaines de personnes par saison, qu'on ne les connaît pas encore, et
 * qu'un compte à créer avant chaque envoi ferait qu'on n'enverrait rien. Le
 * mot de passe unique se change le jour où il a trop circulé, et c'est le seul
 * entretien qu'il demande.
 *
 * Ce qu'il protège, et ce qu'il ne protège pas. Il tient le Catalogue hors des
 * moteurs de recherche et hors du passage — ce que le lien Drive fait
 * aujourd'hui, en moins commode. Il ne protège pas d'un programmateur qui
 * transmet le mot de passe à un confrère : c'est accepté, et c'est même le
 * comportement qu'on espère.
 *
 * Le mot de passe se range dans les réglages du CMS. Il y est écrit en clair la
 * première fois, puis remplacé par son empreinte à la première connexion
 * réussie — voir verifier(). Personne n'a à savoir hacher quoi que ce soit, et
 * la valeur lisible ne traîne pas.
 */
class CatalogAuth
{
    /** Le réglage qui porte le mot de passe, en clair puis haché. */
    private const REGLAGE = 'catalogue_password';

    public const MAX_ATTEMPTS = 10;
    public const WINDOW_MIN   = 10;

    /** La session est-elle ouverte sur le Catalogue ? */
    public static function check(): bool
    {
        session_boot();
        return !empty($_SESSION['lv_catalog_ok']);
    }

    /**
     * Le Catalogue est-il utilisable ?
     *
     * Tant qu'aucun mot de passe n'est renseigné, la porte n'a pas de serrure :
     * plutôt que d'ouvrir à tout le monde en silence — ce qui est exactement ce
     * qu'on veut éviter —, on refuse l'accès et on le dit dans la page. Une
     * fonctionnalité qui s'ouvre toute seule parce qu'un réglage est vide est
     * le défaut que ce projet a déjà rencontré quatre fois.
     */
    public static function configure(): bool
    {
        return trim((string)setting(self::REGLAGE, '')) !== '';
    }

    private static function ip(): string
    {
        return substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 60);
    }

    /**
     * Trop d'essais depuis cette adresse ?
     *
     * On réutilise la table login_attempts des deux autres portes, avec un
     * préfixe distinct : un programmateur qui se trompe dix fois ne doit pas
     * bloquer la connexion du bureau, ni l'inverse.
     */
    public static function throttled(): bool
    {
        $n = (int)DB::val(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at > (NOW() - INTERVAL ' . self::WINDOW_MIN . ' MINUTE)',
            ['c:' . self::ip()]
        );
        return $n >= self::MAX_ATTEMPTS;
    }

    /**
     * Vérifie le mot de passe et ouvre la session.
     *
     * La valeur du réglage peut être de deux formes, et c'est voulu :
     *
     *   — une empreinte, reconnaissable à son préfixe « $2y$ ». C'est l'état
     *     normal, et password_verify() fait le travail ;
     *   — du texte en clair, tel qu'on vient de l'écrire dans les réglages. On
     *     compare alors avec hash_equals(), puis on remplace immédiatement le
     *     réglage par l'empreinte. La bascule est invisible et n'arrive
     *     qu'une fois par mot de passe.
     *
     * Ce détour évite d'ajouter un écran « changer le mot de passe » et évite
     * surtout que quelqu'un doive produire un hachage à la main. Le prix est
     * une fenêtre courte — entre l'écriture du réglage et la première
     * connexion — pendant laquelle la valeur est lisible en base par qui a
     * déjà l'administration.
     */
    public static function verifier(string $saisi): bool
    {
        session_boot();
        if ($saisi === '' || self::throttled()) return false;

        $ref = trim((string)setting(self::REGLAGE, ''));
        if ($ref === '') return false;

        $ok = str_starts_with($ref, '$2y$')
            ? password_verify($saisi, $ref)
            : hash_equals($ref, $saisi);

        if (!$ok) {
            DB::insert('login_attempts', ['ip' => 'c:' . self::ip(), 'email' => 'catalogue']);
            return false;
        }

        if (!str_starts_with($ref, '$2y$')) {
            try {
                Settings::set(self::REGLAGE, password_hash($saisi, PASSWORD_DEFAULT));
            } catch (Throwable $e) {
                /* Le hachage est un confort, pas la serrure : si l'écriture
                   échoue, la connexion réussit quand même et la valeur reste
                   en clair jusqu'à la prochaine fois. Mieux vaut cela qu'un
                   programmateur bloqué à la porte. */
            }
        }

        session_regenerate_id(true);
        $_SESSION['lv_catalog_ok'] = 1;
        DB::delete('login_attempts', 'ip = ?', ['c:' . self::ip()]);
        return true;
    }

    public static function fermer(): void
    {
        session_boot();
        unset($_SESSION['lv_catalog_ok']);
        session_regenerate_id(true);
    }

    // ---- Jeton anti-CSRF, propre au Catalogue -------------------------------

    public static function csrf(): string
    {
        session_boot();
        if (empty($_SESSION['lv_catalog_csrf'])) {
            $_SESSION['lv_catalog_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['lv_catalog_csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::csrf()) . '">';
    }

    public static function requireCsrf(): void
    {
        session_boot();
        $t = $_POST['_csrf'] ?? '';
        if (!empty($_SESSION['lv_catalog_csrf']) && is_string($t)
            && hash_equals($_SESSION['lv_catalog_csrf'], $t)) return;
        http_response_code(403);
        exit(tu('sys_csrf'));
    }
}

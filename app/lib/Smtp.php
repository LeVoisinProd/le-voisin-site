<?php
/**
 * Client SMTP authentifié — [V10-CMS-BILINGUE] (30.07.2026).
 *
 * V10 : les messages d'échec sont traduits (clefs « sys_smtp_… »), et un code
 * court non traduit — self::$code — dit désormais la nature du refus, pour que
 * la page de diagnostic n'ait plus à reconnaître une phrase française.
 *
 * V9-MOTDEPASSE (29.07.2026).
 *
 * Pourquoi cette classe existe
 * ----------------------------
 * Le site envoyait ses emails avec la fonction mail() de PHP. Sur le serveur
 * d'Infomaniak cette fonction n'existe tout simplement pas : elle est
 * désactivée par l'hébergeur. PHP lève alors « Call to undefined function
 * mail() » et aucun envoi ne peut aboutir, quoi qu'on écrive dans le
 * formulaire.
 *
 * La seule voie possible est l'envoi authentifié : on se connecte au serveur
 * de messagerie comme le ferait un logiciel de courrier, avec une vraie
 * adresse et son mot de passe. C'est ce que fait cette classe, sans aucune
 * bibliothèque externe — uniquement les fonctions réseau de PHP, pour qu'il
 * n'y ait rien à installer sur le serveur.
 *
 * Elle est volontairement bavarde en cas d'échec : chaque refus du serveur
 * est renvoyé mot pour mot dans self::$erreur, puis consigné dans le journal
 * par Mailer. Un « 535 Authentication failed » est autrement plus utile
 * qu'un « l'envoi a échoué ».
 */
class Smtp
{
    /** Dernier message d'erreur, en clair, pour le journal et la page de diagnostic. */
    public static string $erreur = '';

    /**
     * Nature du dernier échec, en un mot stable et non traduit.   [V10-CMS-BILINGUE]
     *
     * $erreur est écrit pour être lu par Anna, donc traduit ; on ne peut plus
     * le reconnaître en y cherchant un bout de phrase française. Ce code-ci ne
     * change jamais, quelle que soit la langue : la page de diagnostic s'y fie
     * pour savoir qu'un refus vient du mot de passe et non du réseau.
     *
     * Valeurs : nohost, conn, starttls, from, rcpt, nouser, auth, nomethod,
     *           timeout, closed, reply.
     */
    public static string $code = '';

    /** Dialogue complet avec le serveur — utile seulement pour le diagnostic. */
    public static array $trace = [];

    /** Lignes de la dernière réponse du serveur (capacités annoncées après EHLO). */
    private static array $dernieresLignes = [];

    private const TIMEOUT = 20;

    /**
     * Envoie un message déjà entièrement formé (en-têtes + corps).
     *
     * @param string   $enveloppe  adresse d'expédition de l'enveloppe
     * @param string[] $destinataires
     * @param string   $message    message RFC-822 complet, lignes en CRLF
     */
    public static function send(string $enveloppe, array $destinataires, string $message): bool
    {
        self::$erreur = '';
        self::$code   = '';
        self::$trace  = [];

        $hote = trim((string)setting('smtp_host', ''));
        $user = trim((string)setting('smtp_user', ''));
        // Le mot de passe est débarrassé des espaces de bord, comme l'identifiant.
        // Un mot de passe collé depuis un gestionnaire ou un courriel emporte
        // souvent un espace ou un retour à la ligne invisible ; le serveur
        // répondait alors « 535 mot de passe invalide » sans qu'on puisse voir
        // pourquoi, puisque le mot de passe avait l'air juste.
        $pass = trim((string)setting('smtp_pass', ''));
        $port = (int)setting('smtp_port', '587');
        $secu = strtolower(trim((string)setting('smtp_secure', 'tls')));

        if ($hote === '') { self::$erreur = tu('sys_smtp_nohost'); self::$code = 'nohost'; return false; }
        if ($port <= 0)   { $port = 587; }

        // « ssl » chiffre dès la connexion (port 465). « tls » se connecte en
        // clair puis passe en chiffré avec STARTTLS (port 587, le cas courant).
        $adresse = ($secu === 'ssl' ? 'ssl://' : '') . $hote . ':' . $port;

        $ctx = stream_context_create(['ssl' => ['SNI_enabled' => true]]);
        $sock = @stream_socket_client($adresse, $errno, $errstr, self::TIMEOUT,
            STREAM_CLIENT_CONNECT, $ctx);

        if (!$sock) {
            self::$erreur = tu('sys_smtp_conn', $adresse)
                          . ($errstr !== '' ? ' — ' . $errstr : '') . ' (code ' . $errno . ')';
            self::$code = 'conn';
            return false;
        }
        stream_set_timeout($sock, self::TIMEOUT);

        try {
            if (!self::attend($sock, 220)) return false;

            $nomLocal = parse_url((string)cfg('base_url', ''), PHP_URL_HOST) ?: 'localhost';
            $capacites = self::ehlo($sock, $nomLocal);
            if ($capacites === null) return false;

            if ($secu === 'tls') {
                if (!self::commande($sock, 'STARTTLS', 220)) return false;
                $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (!@stream_socket_enable_crypto($sock, true, $crypto)) {
                    self::$erreur = tu('sys_smtp_starttls');
                    self::$code = 'starttls';
                    return false;
                }
                // Après STARTTLS le dialogue recommence à zéro : il faut
                // redire EHLO pour connaître les capacités réelles.
                $capacites = self::ehlo($sock, $nomLocal);
                if ($capacites === null) return false;
            }

            if ($user !== '') {
                if (!self::auth($sock, $capacites, $user, $pass)) return false;
            }

            if (!self::commande($sock, 'MAIL FROM:<' . $enveloppe . '>', 250)) {
                self::$erreur = tu('sys_smtp_from', $enveloppe, self::$erreur);
                self::$code = 'from';
                return false;
            }

            $acceptes = 0;
            foreach ($destinataires as $d) {
                if (self::commande($sock, 'RCPT TO:<' . $d . '>', [250, 251])) $acceptes++;
                else self::$trace[] = tu('sys_smtp_rcpt', $d, self::$erreur);
            }
            if ($acceptes === 0) {
                self::$erreur = tu('sys_smtp_rcpt_all', self::$erreur);
                self::$code = 'rcpt';
                return false;
            }

            if (!self::commande($sock, 'DATA', 354)) return false;

            // Un point seul en début de ligne signifie « fin du message » : on
            // le double pour qu'il ne coupe pas le message en plein milieu.
            $corps = preg_replace('/^\./m', '..', $message);
            @fwrite($sock, $corps . "\r\n.\r\n");
            if (!self::attend($sock, 250)) return false;

            self::commande($sock, 'QUIT', [221, 250]);
            return true;

        } finally {
            @fclose($sock);
        }
    }

    /**
     * Vérifie la configuration sans envoyer de message : connexion,
     * chiffrement et authentification. Sert à la page de diagnostic.
     */
    public static function verifie(): bool
    {
        self::$erreur = '';
        self::$code   = '';
        self::$trace  = [];

        $hote = trim((string)setting('smtp_host', ''));
        $user = trim((string)setting('smtp_user', ''));
        // Voir la note dans send() : espaces de bord retirés.
        $pass = trim((string)setting('smtp_pass', ''));
        $port = (int)setting('smtp_port', '587') ?: 587;
        $secu = strtolower(trim((string)setting('smtp_secure', 'tls')));

        if ($hote === '') { self::$erreur = tu('sys_smtp_nohost'); self::$code = 'nohost'; return false; }

        $adresse = ($secu === 'ssl' ? 'ssl://' : '') . $hote . ':' . $port;
        $sock = @stream_socket_client($adresse, $errno, $errstr, self::TIMEOUT);
        if (!$sock) {
            self::$erreur = tu('sys_smtp_conn', $adresse)
                          . ($errstr !== '' ? ' — ' . $errstr : '');
            self::$code = 'conn';
            return false;
        }
        stream_set_timeout($sock, self::TIMEOUT);

        try {
            if (!self::attend($sock, 220)) return false;
            $nomLocal = parse_url((string)cfg('base_url', ''), PHP_URL_HOST) ?: 'localhost';
            $cap = self::ehlo($sock, $nomLocal);
            if ($cap === null) return false;

            if ($secu === 'tls') {
                if (!self::commande($sock, 'STARTTLS', 220)) return false;
                if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    self::$erreur = tu('sys_smtp_starttls');
                    self::$code = 'starttls';
                    return false;
                }
                $cap = self::ehlo($sock, $nomLocal);
                if ($cap === null) return false;
            }

            if ($user === '') { self::$erreur = tu('sys_smtp_nouser'); self::$code = 'nouser'; return false; }
            if (!self::auth($sock, $cap, $user, $pass)) return false;

            self::commande($sock, 'QUIT', [221, 250]);
            return true;
        } finally {
            @fclose($sock);
        }
    }

    // ------------------------------------------------------------------

    /** @return string[]|null capacités annoncées, ou null si le serveur refuse */
    private static function ehlo($sock, string $nomLocal): ?array
    {
        if (!self::commande($sock, 'EHLO ' . $nomLocal, 250)) {
            // Serveur ancien : on retente avec la formule d'origine.
            if (!self::commande($sock, 'HELO ' . $nomLocal, 250)) return null;
            return [];
        }
        return self::$dernieresLignes;
    }

    private static function auth($sock, array $capacites, string $user, string $pass): bool
    {
        $annonce = strtoupper(implode(' ', $capacites));

        // AUTH LOGIN d'abord : c'est la méthode qu'annonce Infomaniak et la
        // plus largement acceptée. AUTH PLAIN sert de repli.
        if (str_contains($annonce, 'LOGIN')) {
            if (!self::commande($sock, 'AUTH LOGIN', 334)) return false;
            if (!self::commande($sock, base64_encode($user), 334, true)) {
                self::$erreur = tu('sys_smtp_baduser', self::$erreur); self::$code = 'auth'; return false;
            }
            if (!self::commande($sock, base64_encode($pass), 235, true)) {
                self::$erreur = tu('sys_smtp_badauth', $user, self::$erreur);
                self::$code = 'auth';
                return false;
            }
            return true;
        }

        if (str_contains($annonce, 'PLAIN')) {
            $jeton = base64_encode("\0" . $user . "\0" . $pass);
            if (!self::commande($sock, 'AUTH PLAIN ' . $jeton, 235, true)) {
                self::$erreur = tu('sys_smtp_badauth', $user, self::$erreur);
                self::$code = 'auth';
                return false;
            }
            return true;
        }

        self::$erreur = tu('sys_smtp_nomethod');
        self::$code = 'nomethod';
        return false;
    }

    /**
     * @param int|int[] $attendu
     * @param bool      $masquer  vrai pour les lignes contenant l'identifiant
     *                            ou le mot de passe : la trace est affichée
     *                            par la page de diagnostic, rien de secret ne
     *                            doit y figurer, même encodé en base64.
     */
    private static function commande($sock, string $ligne, $attendu, bool $masquer = false): bool
    {
        self::$trace[] = '> ' . ($masquer ? tu('sys_smtp_masked') : $ligne);
        @fwrite($sock, $ligne . "\r\n");
        return self::attend($sock, $attendu);
    }

    /** @param int|int[] $attendu */
    private static function attend($sock, $attendu): bool
    {
        $codes  = (array)$attendu;
        $lignes = [];
        $code   = 0;

        while (!feof($sock)) {
            $l = @fgets($sock, 4096);
            if ($l === false) {
                $meta = stream_get_meta_data($sock);
                self::$erreur = !empty($meta['timed_out'])
                    ? tu('sys_smtp_timeout', (string)self::TIMEOUT)
                    : tu('sys_smtp_closed');
                self::$code = !empty($meta['timed_out']) ? 'timeout' : 'closed';
                return false;
            }
            $l = rtrim($l, "\r\n");
            $lignes[] = $l;
            self::$trace[] = '< ' . $l;
            $code = (int)substr($l, 0, 3);
            // « 250-… » annonce une suite, « 250 … » clôt la réponse.
            if (strlen($l) < 4 || $l[3] !== '-') break;
        }

        self::$dernieresLignes = $lignes;

        if (in_array($code, $codes, true)) return true;

        self::$erreur = tu('sys_smtp_reply', trim(implode(' / ', $lignes)));
        self::$code = 'reply';
        return false;
    }
}

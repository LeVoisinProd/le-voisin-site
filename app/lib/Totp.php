<?php
/**
 * Le deuxième facteur, en code à six chiffres.  [revue de sécurité, 22.08.2026]
 *
 * Point 3 de la revue. C'est la mesure qui change le plus le pire scénario: un
 * mot de passe volé n'ouvre plus rien tout seul.
 *
 * TOTP, DÉCRIT PAR LA RFC 6238, et rien d'autre. C'est ce que parlent Google
 * Authenticator, Aegis, 1Password, Bitwarden, le trousseau d'Apple et tous les
 * autres. Pas de SMS — une carte SIM se détourne, et un opérateur n'est pas un
 * facteur d'authentification. Pas de service tiers non plus: le secret ne sort
 * jamais d'ici.
 *
 * ÉCRIT À LA MAIN PLUTÔT QU'AJOUTÉ EN DÉPENDANCE, et c'est un choix, pas de
 * l'orgueil. L'algorithme tient en trente lignes: un HMAC-SHA1 sur le numéro de
 * la tranche de trente secondes, dont on extrait quatre octets à une position
 * que le dernier octet indique. Ce site n'a aucun gestionnaire de paquets, et
 * ajouter une chaîne de dépendances pour trente lignes serait une plus grande
 * surface qu'un fichier qu'on peut lire en entier.
 *
 * LA FENÊTRE EST D'UN PAS DE CHAQUE CÔTÉ. Une horloge de téléphone dérive de
 * quelques secondes; refuser un code juste à cause de cela apprend à désactiver
 * le deuxième facteur. Trois pas — quatre-vingt-dix secondes — est le compromis
 * que tout le monde emploie.
 */
declare(strict_types=1);

final class Totp
{
    /** Trente secondes par pas, six chiffres, SHA1: ce que lisent toutes les applications. */
    private const PAS      = 30;
    private const CHIFFRES = 6;
    private const FENETRE  = 1;   // un pas avant, un pas après

    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Un secret neuf: 160 bits, ce que la RFC recommande pour SHA1. */
    public static function secret(): string
    {
        $o = random_bytes(20);
        $bits = '';
        for ($i = 0; $i < strlen($o); $i++) $bits .= str_pad(decbin(ord($o[$i])), 8, '0', STR_PAD_LEFT);
        $s = '';
        foreach (str_split($bits, 5) as $g) {
            $s .= self::B32[bindec(str_pad($g, 5, '0', STR_PAD_RIGHT))];
        }
        return $s;
    }

    /** Le secret en groupes de quatre, parce qu'on le recopie à la main. */
    public static function lisible(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    /**
     * L'adresse `otpauth://` que les applications savent lire.
     *
     * L'ÉMETTEUR ET LE COMPTE SONT DANS L'ADRESSE, parce qu'une application
     * d'authentification affiche une liste: « Le Voisin — anna@le-voisin.com »
     * se retrouve, « 6 chiffres » ne se retrouve pas.
     */
    public static function uri(string $secret, string $compte, string $emetteur = 'Le Voisin'): string
    {
        return 'otpauth://totp/' . rawurlencode($emetteur . ':' . $compte)
             . '?secret=' . $secret
             . '&issuer=' . rawurlencode($emetteur)
             . '&algorithm=SHA1&digits=' . self::CHIFFRES . '&period=' . self::PAS;
    }

    /** Le numéro de la tranche de trente secondes en cours. */
    public static function pas(?int $t = null): int
    {
        return (int)floor(($t ?? time()) / self::PAS);
    }

    /** Le code attendu pour un pas donné. */
    public static function code(string $secret, int $pas): string
    {
        $clef = self::debase32($secret);
        if ($clef === '') return '';

        $msg  = pack('J', $pas);                       // huit octets, gros-boutiste
        $hash = hash_hmac('sha1', $msg, $clef, true);

        /* La troncature dynamique de la RFC: les quatre bits de poids faible du
           dernier octet donnent la position des quatre octets à lire. */
        $dec = ord($hash[19]) & 0x0f;
        $bin = ((ord($hash[$dec])     & 0x7f) << 24)
             | ((ord($hash[$dec + 1]) & 0xff) << 16)
             | ((ord($hash[$dec + 2]) & 0xff) << 8)
             |  (ord($hash[$dec + 3]) & 0xff);

        return str_pad((string)($bin % (10 ** self::CHIFFRES)), self::CHIFFRES, '0', STR_PAD_LEFT);
    }

    /**
     * Vérifie un code et renvoie le pas accepté, ou `null`.
     *
     * ON RENVOIE LE PAS ET NON `true`, pour que l'appelant puisse le retenir et
     * refuser qu'il resserve. Sans cette mémoire, un code intercepté vaut
     * trente secondes — assez pour être rejoué.
     *
     * LA COMPARAISON EST À TEMPS CONSTANT. Comparer deux chaînes avec `===`
     * s'arrête au premier caractère différent, et le temps que ça prend se
     * mesure: c'est une fuite, petite mais gratuite à fermer.
     */
    public static function verifier(string $secret, string $saisi, ?int $dernierPas = null): ?int
    {
        $saisi = preg_replace('/\D/', '', $saisi) ?? '';
        if (strlen($saisi) !== self::CHIFFRES) return null;

        $maintenant = self::pas();
        for ($d = -self::FENETRE; $d <= self::FENETRE; $d++) {
            $p = $maintenant + $d;
            if ($dernierPas !== null && $p <= $dernierPas) continue;   // déjà servi
            if (hash_equals(self::code($secret, $p), $saisi)) return $p;
        }
        return null;
    }

    /** Base32 vers octets. Rend '' si le secret n'est pas du base32 valide. */
    private static function debase32(string $s): string
    {
        $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s) ?? '');
        if ($s === '') return '';
        $bits = '';
        for ($i = 0; $i < strlen($s); $i++) {
            $v = strpos(self::B32, $s[$i]);
            if ($v === false) return '';
            $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $o) {
            if (strlen($o) === 8) $out .= chr(bindec($o));
        }
        return $out;
    }
}

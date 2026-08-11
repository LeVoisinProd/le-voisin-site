<?php
/**
 * Liens Instagram : reconnaissance d'une adresse collée et construction du
 * bloc intégré.   [V28-INSTAGRAM]
 *
 * Même principe que pour Spotify : aucune clef d'API, aucun compte
 * développeur. Instagram accepte d'afficher n'importe quelle publication
 * publique dans une simple <iframe> construite à partir du code contenu dans
 * l'adresse — c'est ce qu'utilise le bouton « Intégrer » de l'application.
 *
 * Ce qu'il faut savoir, et qui explique la forme du champ dans le CMS : il
 * n'existe aucun moyen d'afficher automatiquement « les dernières
 * publications » d'un compte sans passer par un compte développeur Meta et
 * une clef qui expire tous les soixante jours. On affiche donc ce que le
 * bureau a choisi de mettre en avant, plus un lien vers le compte — qui, lui,
 * ne périme jamais.
 */
class Instagram
{
    /** Les segments qui désignent une publication, et non un compte. */
    private const TYPES = ['p', 'reel', 'reels', 'tv'];

    /**
     * Segments réservés par Instagram : ce ne sont jamais des noms de compte.
     * Sans cette liste, « instagram.com/explore/… » serait pris pour le compte
     * « explore ».
     */
    private const RESERVES = [
        'p', 'reel', 'reels', 'tv', 'stories', 'explore', 'accounts', 'direct',
        'about', 'legal', 'developer', 'developers', 'privacy', 'terms', 'emails',
        'challenge', 'oauth', 'graphql', 'api', 'web', 'static', 'session',
        'ajax', 'igtv', 'lite', 'download', 'help', 'press', 'blog',
    ];

    /**
     * Reconnaît une adresse collée.
     * Accepte les formes rencontrées en pratique :
     *   https://www.instagram.com/p/CODE/?igsh=…      (bouton « Partager »)
     *   https://www.instagram.com/reel/CODE/          (un reel)
     *   https://www.instagram.com/levoisin/p/CODE/    (publication vue du compte)
     *   https://instagram.com/levoisin/               (le compte)
     *   https://www.instagram.com/levoisin?igsh=…     (le compte, lien partagé)
     *   @levoisin                                     (le nom, arobase exigée)
     *
     * Retourne ['kind' => 'post', 'type' => 'p', 'id' => 'CODE']
     *       ou ['kind' => 'account', 'handle' => 'levoisin']
     *       ou null si l'adresse n'est pas comprise.
     */
    public static function parse(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') return null;

        /* Une publication : le code se trouve après p/, reel/, reels/ ou tv/,
           quel que soit ce qui précède (le nom du compte, souvent). */
        $motif = '#instagram\.com/(?:[A-Za-z0-9._]+/)?(' . implode('|', self::TYPES) . ')/([A-Za-z0-9_-]{5,})#i';
        if (preg_match($motif, $url, $m)) {
            $type = strtolower($m[1]);
            if ($type === 'reels') $type = 'reel';   // les deux mènent au même endroit
            return ['kind' => 'post', 'type' => $type, 'id' => $m[2]];
        }

        /* Un compte : instagram.com/nom, avec ou sans www, avec ou sans le
           « ?igsh=… » que l'application ajoute au lien partagé. */
        if (preg_match('#instagram\.com/([A-Za-z0-9._]{1,30})#i', $url, $m)) {
            $h = strtolower(rtrim($m[1], '.'));
            if ($h !== '' && !in_array($h, self::RESERVES, true)) {
                return ['kind' => 'account', 'handle' => $h];
            }
            return null;
        }

        /* Simplement « @levoisin », si elle n'a pas collé d'adresse complète.
           L'arobase est exigée : sans elle, un mot resté sur une ligne par
           mégarde — une note, un début de phrase — deviendrait silencieusement
           un compte, et la fiche afficherait un bouton « Voir toutes les
           publications » menant nulle part. Avec l'arobase, l'intention est
           déclarée ; sans elle, la ligne est refusée en le disant. */
        if (preg_match('#^@([A-Za-z0-9._]{1,30})$#', $url, $m)) {
            $h = strtolower(rtrim($m[1], '.'));
            if ($h !== '' && !in_array($h, self::RESERVES, true) && strpos($h, '.') !== 0) {
                return ['kind' => 'account', 'handle' => $h];
            }
        }
        return null;
    }

    /**
     * Adresse publique remise au propre. Le « ?igsh=… » ajouté par le bouton
     * Partager est un identifiant de suivi : il n'est pas conservé.
     */
    public static function pageUrl(array $ig): string
    {
        return $ig['kind'] === 'post'
            ? 'https://www.instagram.com/' . $ig['type'] . '/' . $ig['id'] . '/'
            : 'https://www.instagram.com/' . $ig['handle'] . '/';
    }

    /** Adresse du bloc intégré (publications seulement ; un compte ne s'intègre pas). */
    public static function embedUrl(array $ig): string
    {
        if ($ig['kind'] !== 'post') return '';
        return 'https://www.instagram.com/' . $ig['type'] . '/' . $ig['id'] . '/embed/';
    }

    /** Vrai si l'adresse est comprise (utilisé par le formulaire du CMS). */
    public static function valide(string $url): bool
    {
        return self::parse($url) !== null;
    }

    /**
     * Lit le champ du CMS — une adresse par ligne — et en tire une liste
     * propre. Retourne ['ok' => [ …parse()… ], 'refus' => [ …lignes… ]].
     *
     * Les doublons sont écartés, et l'ordre saisi est conservé : la première
     * publication de la liste est celle qui s'affichera en premier.
     */
    public static function lire(string $brut): array
    {
        $ok = $refus = $vus = [];
        foreach (preg_split('/[\r\n]+/', $brut) ?: [] as $ligne) {
            $ligne = trim($ligne);
            if ($ligne === '') continue;
            $ig = self::parse($ligne);
            if ($ig === null) { $refus[] = $ligne; continue; }
            $cle = self::pageUrl($ig);
            if (isset($vus[$cle])) continue;
            $vus[$cle] = true;
            $ok[] = $ig;
        }
        return ['ok' => $ok, 'refus' => $refus];
    }

    /** Le champ remis au propre, une adresse canonique par ligne. */
    public static function normaliser(string $brut): string
    {
        $l = self::lire($brut);
        $out = [];
        foreach ($l['ok'] as $ig) $out[] = self::pageUrl($ig);
        return implode("\n", $out);
    }
}

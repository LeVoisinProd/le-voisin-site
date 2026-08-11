<?php
/**
 * Liens Spotify : reconnaissance d'une adresse collée et construction du
 * lecteur intégré.   [V26-SPOTIFY]
 *
 * Aucune clef d'API, aucun compte développeur : le lecteur officiel de
 * Spotify s'affiche dans une simple <iframe> construite à partir du seul
 * identifiant contenu dans l'adresse partagée depuis l'application ou
 * depuis le site open.spotify.com.
 */
class Spotify
{
    /** Ce que le lecteur de Spotify sait afficher. */
    private const TYPES = ['artist', 'album', 'playlist', 'track', 'show', 'episode'];

    /**
     * Reconnaît une adresse collée et en tire le type et l'identifiant.
     * Accepte toutes les formes rencontrées en pratique :
     *   https://open.spotify.com/artist/XXXX?si=8f2…   (bouton « Partager »)
     *   https://open.spotify.com/intl-fr/album/XXXX    (appli en français)
     *   https://open.spotify.com/embed/artist/XXXX     (code d'intégration)
     *   https://open.spotify.com/user/anna/playlist/XX (ancienne forme)
     *   spotify:playlist:XXXX                          (adresse interne)
     *
     * Retourne ['kind' => 'artist', 'id' => 'XXXX'], ou null si l'adresse
     * n'est pas un lien Spotify intégrable.
     */
    public static function parse(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') return null;
        // Les segments qui précèdent le type (intl-fr, embed, user/anna…) sont
        // acceptés et ignorés : seuls comptent le type et l'identifiant.
        // Délimiteur « # » et non « ~ » : le tilde est un caractère d'adresse
        // ordinaire, il n'a rien à faire en délimiteur d'expression ici.
        $motif = '#(?:open\.spotify\.com/(?:[A-Za-z0-9_.~-]+/)*|spotify:(?:user:[^:]+:)?)'
               . '(' . implode('|', self::TYPES) . ')[/:]([A-Za-z0-9]{16,32})#i';
        if (!preg_match($motif, $url, $m)) return null;
        return ['kind' => strtolower($m[1]), 'id' => $m[2]];
    }

    /**
     * Adresse publique remise au propre. Le « ?si=… » que Spotify ajoute au
     * lien partagé est un identifiant de suivi : il n'est pas conservé.
     */
    public static function pageUrl(string $kind, string $id): string
    {
        return 'https://open.spotify.com/' . $kind . '/' . $id;
    }

    /** Adresse du lecteur intégré. */
    public static function embedUrl(string $kind, string $id): string
    {
        return 'https://open.spotify.com/embed/' . $kind . '/' . $id . '?utm_source=generator';
    }

    /**
     * Spotify n'accepte que quelques hauteurs fixes pour son lecteur. Un
     * morceau ou un épisode seul tient dans la version compacte (152 px) ;
     * un artiste, un album ou une playlist ont besoin de 352 px pour
     * afficher la pochette et la liste des titres.
     */
    public static function height(string $kind): int
    {
        return in_array($kind, ['track', 'episode'], true) ? 152 : 352;
    }

    /** Vrai si l'adresse est comprise (utilisé par le formulaire du CMS). */
    public static function valide(string $url): bool
    {
        return self::parse($url) !== null;
    }
}

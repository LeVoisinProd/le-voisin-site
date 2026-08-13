<?php
/**
 * Ico — les petits signes du site, dessinés plutôt qu'écrits.
 * [V33-VECTORIEL] (03.08.2026)
 *
 * Jusqu'ici ces signes étaient écrits avec des caractères : « ↗ » pour un lien
 * qui ouvre un autre site, « ✂ » pour recadrer une image, « ✔ » et « ✘ » pour
 * les contrôles de l'installateur. Sur un ordinateur, ils s'affichaient comme
 * du texte : le même trait fin, la même couleur que les mots autour.
 *
 * Sur un téléphone, non. Android et iPhone reconnaissent ces caractères-là
 * comme des émojis et vont les chercher, non pas dans la police du site, mais
 * dans leur police d'images en couleur. Le trait fin devient alors un petit
 * dessin épais, bleu ou noir selon l'appareil, qui ne ressemble plus au reste
 * de la page et qui change d'un téléphone à l'autre.
 *
 * On les dessine donc nous-mêmes, en vectoriel. Un dessin vectoriel n'est pas
 * une image : c'est une suite de traits que le navigateur trace lui-même, à la
 * demande. Il reste net à n'importe quelle taille, prend automatiquement la
 * couleur du texte qui l'entoure, et s'affiche exactement de la même façon
 * partout — téléphone comme ordinateur.
 *
 * Règle qui vaut pour la suite : aucun signe de ce genre ne doit plus être
 * écrit en toutes lettres dans une page. Il passe par ici.
 *
 * L'habillage (taille, épaisseur du trait) est dans les feuilles de style,
 * sous la classe « .ico » — présente aussi bien dans assets/css/site.css que
 * dans admin/assets/admin.css, puisque ces signes servent des deux côtés.
 */
class Ico
{
    /**
     * Le tronc commun : on ne remplit pas, on trace, et la couleur vient du
     * texte environnant. « aria-hidden » parce que ces signes ne disent rien
     * de plus que le texte qu'ils accompagnent : une machine à lire les pages
     * doit passer devant sans s'arrêter.
     */
    private static function svg(string $classe, string $vue, string $trace): string
    {
        return '<svg class="ico ' . $classe . '" viewBox="' . $vue . '" '
             . 'aria-hidden="true" focusable="false">' . $trace . '</svg>';
    }

    /**
     * La flèche des liens qui ouvrent un autre site — l'ancien « ↗ ».
     * Elle se pose après le texte du lien, séparée par une espace.
     */
    public static function ext(): string
    {
        return self::svg('ico-ext', '0 0 12 12',
            '<path d="M2.7 9.3 9.3 2.7M4.7 2.7h4.6v4.6"/>');
    }

    /**
     * La flèche des téléchargements : vers le bas, et un sol sous elle.
     *
     * Elle ne se confond pas avec ext(), et c'est tout son intérêt. La flèche
     * diagonale dit « ceci ouvre un autre site » ; posée sur « Télécharger »
     * elle mentirait, et l'on n'y regarde pas à deux fois avant de cliquer.
     */
    public static function bas(): string
    {
        return self::svg('ico-bas', '0 0 12 12',
            '<path d="M6 1.7v6.1M3.4 5.5 6 8.1l2.6-2.6M2.4 10.3h7.2"/>');
    }

    /** Les ciseaux du bouton « recadrer » — l'ancien « ✂ ». */
    public static function ciseaux(): string
    {
        return self::svg('ico-ciseaux', '0 0 16 16',
            '<path d="M3.7 2.3 9.7 10.3M12.3 2.3 6.3 10.3"/>'
          . '<circle cx="4.7" cy="12.5" r="1.9"/><circle cx="11.3" cy="12.5" r="1.9"/>');
    }

    /** Le crochet des contrôles réussis — l'ancien « ✔ ». */
    public static function oui(): string
    {
        return self::svg('ico-oui', '0 0 14 14', '<path d="m2.6 7.4 3 3 5.8-6.8"/>');
    }

    /** La croix des contrôles manqués — l'ancien « ✘ ». */
    public static function non(): string
    {
        return self::svg('ico-non', '0 0 14 14', '<path d="m3.4 3.4 7.2 7.2M10.6 3.4 3.4 10.6"/>');
    }
}

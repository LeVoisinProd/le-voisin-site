<?php
/**
 * La nomenclature des pièces déposées.     [V43-NOMS] [13.08.2026]
 *
 * POURQUOI CE FICHIER EXISTE
 *
 * Deux portes reçoivent des justificatifs — le formulaire public, pour qui n'a
 * pas de compte, et l'espace, pour qui en a un — et chacune nommait ses fichiers
 * à sa façon :
 *
 *   formulaire   2100_CHF_FraisDeProduction_TournageNostalgia_PERRINLuca.pdf
 *   espace       2026_08_PERRIN-Luca_Facture.pdf
 *
 * Le même justificatif portait donc deux noms selon le chemin emprunté, et le
 * bureau, qui les range dans les mêmes dossiers, devait apprendre les deux. Ni
 * l'un ni l'autre ne disait QUELLE ASSOCIATION paie, alors qu'il y en a treize.
 *
 * L'ORDRE, décidé le 13.08.2026 :
 *
 *   montant _ devise _ ASSOCIATION _ type _ projet et lieu _ NOM _ Prénom
 *   2100_CHF_ENCONTRO_FraisDeProduction_TournageNostalgia_PERRIN_Luca.pdf
 *
 * Il se lit de gauche à droite dans l'ordre où l'on cherche : combien, qui
 * paie, à quel titre, pour quoi, et pour qui. Un morceau absent disparaît sans
 * laisser de trou — un reçu de train déposé sans projet donne simplement un nom
 * plus court, jamais un « __ » au milieu.
 *
 * ÉCRIT ICI ET PAS DEUX FOIS. C'est la leçon du 13.08, apprise quatre fois dans
 * la même journée : une règle recopiée à deux endroits se corrige à un seul.
 */
class NomFichier
{
    /**
     * Un morceau de nom : lisible, sans accent, sans séparateur.
     *
     * Les espaces et la ponctuation disparaissent au lieu de devenir des tirets,
     * parce que « Tournage Nostalgia, clip » deviendrait sinon
     * « Tournage-Nostalgia--clip » et que le souligné sépare déjà les morceaux :
     * deux séparateurs dans un nom, c'est un de trop.
     */
    public static function morceau(string $s): string
    {
        $s = trim($s);
        if ($s === '') return '';

        /* UN MONTANT GARDE SES DÉCIMALES, et c'est la seule exception.

           Sans elle « 44.50 » devenait « 4450 » : quarante-quatre francs
           cinquante nommés quatre mille quatre cent cinquante, dans un dossier
           de comptabilité. La virgule devient un point, parce qu'un nom de
           fichier ne se trie correctement qu'avec un séparateur décimal fixe. */
        if (preg_match('/^[0-9]+([.,][0-9]+)?$/', $s)) return str_replace(',', '.', $s);

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if (is_string($ascii) && $ascii !== '') $s = $ascii;

        /* Les mots se collent avec leur initiale en capitale plutôt qu'en un
           bloc minuscule : « Frais de production » donne « FraisDeProduction »
           et non « fraisdeproduction », qui ne se lit plus. Un mot déjà tout en
           capitales le reste — quelqu'un qui écrit son nom PERRIN le voit
           écrit PERRIN. */
        $mots = preg_split('/[^A-Za-z0-9]+/', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $s = implode('', array_map(static fn(string $m): string => ucfirst($m), $mots));
        return mb_substr($s, 0, 60);
    }

    /**
     * Le nom complet, à partir des morceaux qu'on a.
     *
     * @param array<int, string> $morceaux dans l'ordre voulu ; les vides sautent
     */
    public static function construire(array $morceaux, string $ext, string $defaut = 'document'): string
    {
        $bouts = [];
        foreach ($morceaux as $m) {
            $p = self::morceau((string)$m);
            if ($p !== '') $bouts[] = $p;
        }
        $base = implode('_', $bouts);
        if ($base === '') $base = $defaut;
        $ext = (string)preg_replace('/[^A-Za-z0-9]+/', '', $ext);
        return $base . ($ext !== '' ? '.' . mb_strtolower($ext) : '');
    }
}

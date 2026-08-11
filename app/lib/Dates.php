<?php
/**
 * Les dates, telles qu'on les écrit ici.   [V16-DATES]
 *
 * Une base de données range toujours une date à l'anglaise — 2026-07-31 —
 * parce que c'est le seul ordre qui se trie correctement. Mais personne
 * n'écrit une date comme cela, et une date de naissance affichée 1990-04-07
 * se lit mal : on hésite entre le 4 juillet et le 7 avril.
 *
 * Cette classe tient les deux bouts. « afficher() » sort le jour d'abord,
 * séparé par des points, comme on l'écrit en Suisse ; « versIso() » relit ce
 * qu'on a tapé et le rend à la base dans son ordre à elle. Rien n'est stocké
 * différemment : c'est uniquement une question de lecture et d'écriture.
 *
 * On ne s'appuie pas sur le format automatique du navigateur : un champ
 * « type=date » s'affiche dans la langue du système, donc 07/04/1990 chez
 * l'un et 04/07/1990 chez l'autre, pour la même date. Pour une date de
 * naissance, cette ambiguïté n'est pas acceptable.
 */
final class Dates
{
    /** Le format que l'on montre et que l'on demande. */
    public const FORMAT   = 'd.m.Y';
    /** Ce qu'on écrit dans un champ vide, en repère. */
    public const GABARIT  = ['fr' => 'JJ.MM.AAAA', 'en' => 'DD.MM.YYYY'];

    /**
     * Une date de la base (2026-07-31, avec ou sans heure) telle qu'on la lit :
     * 31.07.2026. Une valeur vide ou incompréhensible ressort telle quelle,
     * plutôt que remplacée par une date inventée.
     */
    public static function afficher(?string $valeur): string
    {
        $v = trim((string)$valeur);
        if ($v === '' || str_starts_with($v, '0000')) return '';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) {
            return $m[3] . '.' . $m[2] . '.' . $m[1];
        }
        return $v;
    }

    /** Comme afficher(), mais en gardant l'heure : 31.07.2026 14:05. */
    public static function afficherHeure(?string $valeur): string
    {
        $v = trim((string)$valeur);
        if ($v === '') return '';
        $jour = self::afficher($v);
        if (preg_match('/[ T](\d{2}):(\d{2})/', $v, $m)) return $jour . ' ' . $m[1] . ':' . $m[2];
        return $jour;
    }

    /**
     * Ce qu'on a tapé, rendu à la base. On accepte large — points, barres,
     * traits d'union, et même une date déjà à l'anglaise — parce qu'un
     * formulaire refusé pour un séparateur est une perte de temps pure.
     * Retourne null si ce n'est décidément pas une date.
     */
    public static function versIso(?string $saisie): ?string
    {
        $v = trim((string)$saisie);
        if ($v === '') return null;

        // Déjà dans l'ordre de la base.
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $v, $m)) {
            return self::assembler((int)$m[1], (int)$m[2], (int)$m[3]);
        }
        // Jour d'abord, avec n'importe quel séparateur usuel.
        if (preg_match('/^(\d{1,2})[.\/\- ](\d{1,2})[.\/\- ](\d{2,4})$/', $v, $m)) {
            $an = (int)$m[3];
            // Une année à deux chiffres : 26 vaut 2026, 74 vaut 1974. La
            // bascule à 40 laisse de la place aux dates de naissance.
            if ($an < 100) $an += $an < 40 ? 2000 : 1900;
            return self::assembler($an, (int)$m[2], (int)$m[1]);
        }
        // Huit chiffres collés : 31072026.
        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $v, $m)) {
            return self::assembler((int)$m[3], (int)$m[2], (int)$m[1]);
        }
        return null;
    }

    /** Un jour qui existe vraiment, sinon rien : le 31.02 n'est pas une date. */
    private static function assembler(int $an, int $mois, int $jour): ?string
    {
        if ($an < 1900 || $an > 2200 || !checkdate($mois, $jour, $an)) return null;
        return sprintf('%04d-%02d-%02d', $an, $mois, $jour);
    }

    /* -----------------------------------------------------------------
       Les mois, écrits.                                  [V36-FACTURES]

       PHP sait formater une date dans une langue, mais seulement si
       l'extension intl est installée et que le serveur possède la locale
       demandée — deux conditions qu'un hébergement mutualisé ne garantit
       pas, et dont l'échec se voit tout de suite : « July 2026 » au milieu
       d'une page française. Douze mots par langue coûtent moins cher
       qu'une dépendance qui peut manquer.
       ----------------------------------------------------------------- */
    public const MOIS = [
        'fr' => ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
        'en' => ['January', 'February', 'March', 'April', 'May', 'June',
                 'July', 'August', 'September', 'October', 'November', 'December'],
    ];

    /** Le nom d'un mois, de 1 à 12. Un numéro hors bornes ne donne rien. */
    public static function mois(int $n, ?string $lang = null): string
    {
        $l = ($lang === 'en') ? 'en' : 'fr';
        return self::MOIS[$l][$n - 1] ?? '';
    }

    /** « 2026-07 » (ou une date complète) tel qu'on le lit : « Juillet 2026 ». */
    public static function moisAn(string $ym, ?string $lang = null): string
    {
        if (!preg_match('/^(\d{4})-(\d{1,2})/', trim($ym), $m)) return trim($ym);
        $nom = self::mois((int)$m[2], $lang);
        return $nom === '' ? trim($ym) : $nom . ' ' . $m[1];
    }

    /** Le repère à afficher dans un champ vide, selon la langue en cours. */
    public static function gabarit(?string $lang = null): string
    {
        $l = $lang ?: (I18n::$admin ?: 'fr');
        return self::GABARIT[$l] ?? self::GABARIT['fr'];
    }

    /**
     * Le champ de saisie d'une date. Volontairement un champ texte : c'est le
     * seul moyen d'être certain que le jour vient en premier, quelle que soit
     * la langue du navigateur. Le petit script joint pose les points tout seul
     * pendant la frappe, et le champ reste utilisable sans lui.
     */
    public static function champ(string $nom, ?string $iso, ?string $lang = null, string $id = ''): string
    {
        return '<input type="text" class="lv-date" inputmode="numeric" autocomplete="off"'
             . ($id !== '' ? ' id="' . e($id) . '"' : '')
             . ' name="' . e($nom) . '" value="' . e(self::afficher($iso)) . '"'
             . ' placeholder="' . e(self::gabarit($lang)) . '" maxlength="10"'
             . ' pattern="\d{1,2}[.\/\-]\d{1,2}[.\/\-]\d{2,4}">';
    }

    /**
     * Le script de confort, à poser une seule fois par page. Il se contente
     * d'ajouter les points au bon endroit : il ne corrige pas, ne refuse rien
     * et ne remplace jamais ce qui a été tapé.
     */
    public static function script(): string
    {
        return <<<'HTML'
<script>
/* [V16-DATES] Aide à la frappe : 31072026 devient 31.07.2026 tout seul. */
(function () {
  document.querySelectorAll('input.lv-date').forEach(function (ch) {
    ch.addEventListener('input', function () {
      var brut = ch.value.replace(/[^\d]/g, '').slice(0, 8), out = brut;
      if (brut.length > 4)      out = brut.slice(0, 2) + '.' + brut.slice(2, 4) + '.' + brut.slice(4);
      else if (brut.length > 2) out = brut.slice(0, 2) + '.' + brut.slice(2);
      /* On ne réécrit que si l'on ajoute des chiffres à la fin : sinon on
         empêcherait d'effacer un point ou de corriger au milieu. */
      if (ch.selectionStart === ch.value.length && out !== ch.value) ch.value = out;
    });
  });
})();
</script>
HTML;
    }
}

<?php
/**
 * Fondre les associations en double. [16.08.2026]
 *
 *   php db/fusionner_assos.php [--ecrire]
 *
 * LE DOUBLE VIENT DE DEUX REPRISES QUI NE SE CONNAISSAIENT PAS. Une première
 * avait créé les associations avec leur nom lisible (`source = 'assoc'`); celle
 * du dashboard les a recréées avec la clef comme nom (`source = 'dashboard'`),
 * parce que la fiche `lv-assoc-fiches` N'A PAS DE CHAMP DE NOM et que la clef
 * était tout ce qu'il y avait. Résultat mesuré sur la base locale: treize
 * associations, vingt-six lignes.
 *
 * CE QUE ÇA CASSE, ET CE N'EST PAS QUE DE L'ESTHÉTIQUE. La liste des
 * associations d'une fiche de contact affichait « Gran Chicornia » ET
 * « chicornia » comme deux choix distincts. Deux personnes cochent chacune la
 * sienne, et le filtre ne retrouve plus la moitié des contacts — c'est-à-dire
 * qu'on n'écrit pas à des gens à qui on voulait écrire.
 *
 * LA RÈGLE DE FUSION, ET ELLE EST DISSYMÉTRIQUE PARCE QUE LES DEUX LIGNES NE SE
 * VALENT PAS:
 *
 *   survivante   la plus ancienne (`id` le plus bas). C'est elle que les autres
 *                tables désignent déjà — 148 tâches, les fiches de production —
 *                et déplacer des références coûte plus cher que de les garder.
 *   son nom      gagne toujours. « Gran Chicornia » est un nom, « chicornia »
 *                est une clef.
 *   ses champs   ne sont remplis que s'ils sont VIDES. La ligne du dashboard
 *                apporte tout ce qui manque — direction artistique, IDE, AVS,
 *                IBAN, assurances, régime fiscal — et n'écrase rien.
 *   la doublure  est effacée en douceur (`supprime_le`), jamais détruite. Si la
 *                fusion s'est trompée quelque part, tout est encore là.
 *
 * SANS OUBLIER `source_ref`: la survivante reprend celle de la doublure si elle
 * n'en a pas, sinon les prochaines reprises recréeraient le double.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

$ecrire = in_array('--ecrire', $argv, true);
echo $ecrire ? "ÉCRITURE\n\n" : "SIMULATION — rien n'est écrit. --ecrire pour appliquer.\n\n";

/* Les colonnes qu'on peut recopier: tout sauf l'identité et les horodatages. */
$sauf = ['id', 'source', 'source_ref', 'cree_le', 'modifie_le', 'supprime_le'];
$cols = [];
foreach (DB::all("SHOW COLUMNS FROM organisation") as $c) {
    if (!in_array($c['Field'], $sauf, true)) $cols[] = (string)$c['Field'];
}

$groupes = DB::all("SELECT source_ref FROM organisation
                     WHERE supprime_le IS NULL AND source_ref IS NOT NULL AND source_ref <> ''
                     GROUP BY source_ref HAVING COUNT(*) > 1 ORDER BY source_ref");

if (!$groupes) { echo "  Aucun double. Rien à faire.\n"; exit; }

$fondus = 0;
foreach ($groupes as $g) {
    $ref = (string)$g['source_ref'];
    $l = DB::all("SELECT * FROM organisation WHERE supprime_le IS NULL AND source_ref = ?
                   ORDER BY id ASC", [$ref]);
    $garde = array_shift($l);

    printf("  %-12s garde #%-3d « %s »\n", $ref, $garde['id'], $garde['nom']);

    $maj = [];
    foreach ($l as $d) {
        printf("               fond #%-3d « %s »\n", $d['id'], $d['nom']);
        foreach ($cols as $c) {
            $aGarder = trim((string)($maj[$c] ?? $garde[$c] ?? ''));
            $aPrendre = trim((string)($d[$c] ?? ''));
            /* Vide, ou égal à la clef — « chicornia » comme nom n'est pas un nom. */
            $creux = $aGarder === '' || $aGarder === $ref;
            if ($creux && $aPrendre !== '' && $aPrendre !== $ref) $maj[$c] = $d[$c];
        }
    }

    foreach ($maj as $c => $v) printf("               + %-18s %s\n", $c, mb_substr((string)$v, 0, 46));
    if (!$maj) echo "               rien à reprendre, la survivante est complète\n";

    if ($ecrire) {
        if ($maj) {
            $sets = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($maj)));
            DB::run("UPDATE organisation SET $sets WHERE id = ?",
                    [...array_values($maj), (int)$garde['id']]);
        }
        foreach ($l as $d) DB::run("UPDATE organisation SET supprime_le = NOW() WHERE id = ?", [(int)$d['id']]);
    }
    $fondus += count($l);
}

printf("\n  %d groupes · %d lignes effacées en douceur\n", count($groupes), $fondus);
if (!$ecrire) echo "  Relance avec --ecrire pour appliquer.\n";

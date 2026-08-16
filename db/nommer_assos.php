<?php
/**
 * Donner aux associations leur vrai nom. [16.08.2026]
 *
 * L'IMPORT LES A NOMMÉES AVEC LEURS CLEFS. `chicornia`, `lv-ch`, `watering`.
 * Ce n'est pas une négligence de l'importateur: la fiche du dashboard
 * (`lv-assoc-fiches`) N'A PAS DE CHAMP DE NOM. Ses seize colonnes sont adresse,
 * avs_asso, banque_*, da_contact, email, email_mdp, ide, instagram*, pays, ree,
 * reg, site, type — et rien qui dise comment l'association s'appelle. La clef
 * était donc la seule chose disponible, et l'importateur a eu raison de ne pas
 * inventer.
 *
 * D'OÙ VIENNENT LES VRAIS NOMS. Des Shared Drives, qui portent chacun le nom
 * de son association sous la forme `LV_<PAYS>_<CANTON>_<Nom>`. C'est la source
 * la plus fiable qu'on ait: elle est nommée à la main par Anna, elle sert tous
 * les jours, et elle donne en prime le pays et le canton. Recoupée avec la
 * liste écrite dans `_contexto/artistes.md` et avec les adresses e-mail des
 * fiches — les trois concordent sur les treize.
 *
 * `_meta` N'EST PAS UNE ASSOCIATION. C'est la ligne de service du dashboard,
 * importée avec les autres parce que rien ne la distinguait. Elle est effacée
 * ici, doucement (`supprime_le`), pas détruite: si elle porte quelque chose
 * qu'on n'a pas vu, elle est encore là.
 *
 * IDEMPOTENT, ET LA GARDE COMPTE. On ne renomme QUE si le nom actuel est encore
 * égal à la clef. Le jour où quelqu'un corrige un nom à l'écran, ce script
 * repasse sans rien écraser — c'est la différence entre un script qu'on peut
 * relancer et un script qu'on relance en retenant son souffle.
 *
 *   php db/nommer_assos.php          montre ce qu'il ferait
 *   php db/nommer_assos.php --ecrire écrit
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

$ecrire = in_array('--ecrire', $argv, true);

/* Clef du dashboard => nom, canton, pays, dossier Drive.
   Les cantons sont ceux du nom de Drive, pas devinés d'après l'adresse. */
const ASSOS = [
    'lv-ch'     => ['Le Voisin CH',         'GE', 'Suisse',   'LV_CH_GE_LV_CH'],
    'lv-fr'     => ['Le Voisin FR',         '',   'France',   'LV_FR_LV_FR'],
    /* « Gran Chicornia », pas « GrandChicorcnia ». Le dossier du Bureau porte la
       seconde graphie et le Drive l'appelle LV_CH_BE_Annina; c'est la base
       locale, déjà nommée à la main, qui tranche. */
    'chicornia' => ['Gran Chicornia',       'BE', 'Suisse',   'LV_CH_BE_Annina'],
    'mixt'      => ['Mixt Forma',           'BE', 'Suisse',   'LV_CH_BE_Mixt_Forma'],
    'encontro'  => ['Encontro',             'GE', 'Suisse',   'LV_CH_GE_Encontro'],
    'flux'      => ['Flux Poreux',          'GE', 'Suisse',   'LV_CH_GE_Flux_Poreux'],
    'hibiscus'  => ['Hibiscus Culturiste',  'GE', 'Suisse',   'LV_CH_GE_Hibiscus'],
    'riva'      => ['Riva & Repele',        'GE', 'Suisse',   'LV_CH_GE_Riva&Repele'],
    'tupi'      => ['Tupi 19',              'GE', 'Suisse',   'LV_CH_GE_Tupi_19'],
    'tympan'    => ['Tympan',               'GE', 'Suisse',   'LV_CH_GE_Tympan'],
    'watering'  => ['Watering Hole',        'GE', 'Suisse',   'LV_CH_GE_WateringHole'],
    'diesel'    => ['DieselReclame',        'JU', 'Suisse',   'LV_CH_JU_DieselReclame'],
    'crile'     => ['CRILE',                'TI', 'Suisse',   'LV_CH_TI_CRILE'],
];

/* Deux associations existent — Anna les a écrites dans `_contexto/artistes.md`
   et elles ont chacune leur Shared Drive — mais n'ont AUCUNE fiche dans le
   dashboard. On les crée vides plutôt que de les taire: une association absente
   de l'écran est une association qu'on oublie de déclarer. */
const ASSOS_SANS_FICHE = [
    'improvavel' => ['Improvável Produções', '',   'Brésil',   'LV_BR_RJ_Improvável'],
    'taina'      => ['Tainá E I O U',        '',   'Portugal', 'LV_PT_TAINA_E_I_O_U'],
];

echo $ecrire ? "ÉCRITURE\n\n" : "SIMULATION — rien n'est écrit. --ecrire pour appliquer.\n\n";

$renommes = $inchanges = $absents = $crees = 0;

foreach (ASSOS as $ref => [$nom, $canton, $pays, $drive]) {
    $o = DB::one("SELECT id, nom, canton, pays FROM organisation WHERE source_ref = ?", [$ref]);
    if (!$o) { printf("  %-12s ABSENTE de la base\n", $ref); $absents++; continue; }

    /* La garde: on ne touche qu'un nom encore égal à la clef. */
    if ((string)$o['nom'] !== $ref) {
        printf("  %-12s déjà nommée « %s », on n'y touche pas\n", $ref, $o['nom']);
        $inchanges++;
        continue;
    }

    printf("  %-12s → %-22s %s\n", $ref, $nom, $canton ? "($canton)" : "($pays)");
    if ($ecrire) {
        /* canton et pays seulement s'ils sont vides: la fiche du dashboard peut
           les porter, et elle est plus précise qu'un nom de dossier. */
        DB::run("UPDATE organisation
                    SET nom = ?, nom_legal = COALESCE(NULLIF(nom_legal,''), ?),
                        canton = COALESCE(NULLIF(canton,''), ?),
                        pays   = COALESCE(NULLIF(pays,''), ?)
                  WHERE id = ?", [$nom, $nom, $canton ?: null, $pays, $o['id']]);
    }
    $renommes++;
}

foreach (ASSOS_SANS_FICHE as $ref => [$nom, $canton, $pays, $drive]) {
    if (DB::one("SELECT id FROM organisation WHERE source_ref = ?", [$ref])) {
        printf("  %-12s existe déjà\n", $ref); continue;
    }
    printf("  %-12s → %-22s (%s) — créée, sans fiche dashboard\n", $ref, $nom, $pays);
    if ($ecrire) {
        DB::run("INSERT INTO organisation (source, source_ref, genre, nom, nom_legal,
                                           canton, pays, statut, notes)
                 VALUES ('drive', ?, 'association', ?, ?, ?, ?, 'actif', ?)",
                [$ref, $nom, $nom, $canton ?: null, $pays,
                 "Créée depuis le Shared Drive $drive et la liste de _contexto/artistes.md. "
               . "Aucune fiche dans le dashboard: les données légales restent à saisir."]);
    }
    $crees++;
}

/* `_meta` dehors. */
$m = DB::one("SELECT id, supprime_le FROM organisation WHERE source_ref = '_meta'");
if ($m && !$m['supprime_le']) {
    echo "\n  _meta        n'est pas une association — effacée en douceur\n";
    if ($ecrire) DB::run("UPDATE organisation SET supprime_le = NOW() WHERE id = ?", [$m['id']]);
}

/* Ce que le Drive porte et que personne ne réclame. On le dit, on ne le crée
   pas: un dossier n'est pas la preuve qu'une association nous concerne. */
$orphelins = ['LV_CH_BS_LaSecousse', 'Mandarina & Co Verein'];
echo "\n  À DÉCIDER — deux Shared Drives sans fiche et absents de artistes.md:\n";
foreach ($orphelins as $d) echo "    · $d\n";
echo "    Ils ne sont pas créés. Dis lesquels sont des associations du Le Voisin.\n";

printf("\n  %d renommées · %d créées · %d inchangées · %d absentes\n",
       $renommes, $crees, $inchanges, $absents);
if (!$ecrire) echo "  Relance avec --ecrire pour appliquer.\n";

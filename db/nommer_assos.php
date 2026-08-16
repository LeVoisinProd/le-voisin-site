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

/* DEUX ASSOCIATIONS QUE LE LE VOISIN N'ADMINISTRE PAS. Anna, le 16.08.2026:
   « sao artistas novos, mas que nao gerenciamos a associacao, so a venda de
   shows ». Elles n'ont aucune fiche dans le dashboard, et ce n'est pas un
   oubli — c'est qu'il n'y a rien à y mettre: les statuts, l'AVS, la LPP et
   l'impôt ne nous regardent pas. Elles sont créées en `gestion = 'diffusion'`
   pour que les cinq onglets de conformité ne les réclament jamais. */
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
        DB::run("INSERT INTO organisation (source, source_ref, genre, gestion, nom, nom_legal,
                                           canton, pays, statut, notes)
                 VALUES ('drive', ?, 'association', 'diffusion', ?, ?, ?, ?, 'actif', ?)",
                [$ref, $nom, $nom, $canton ?: null, $pays,
                 "Créée depuis le Shared Drive $drive. Le Voisin n'administre pas cette "
               . "association — seulement la vente des spectacles. Il n'y a donc ni statuts, "
               . "ni AVS, ni LPP, ni régime fiscal à saisir: ce n'est pas un dossier en retard."]);
    }
    $crees++;
}

/* Les deux existantes qu'on n'administre pas non plus, si elles ont été créées
   avant que la colonne existe. */
foreach (array_keys(ASSOS_SANS_FICHE) as $r) {
    $o = DB::one("SELECT id, gestion FROM organisation WHERE source_ref = ?", [$r]);
    if ($o && $o['gestion'] !== 'diffusion') {
        printf("  %-12s passe en « diffusion » — Le Voisin ne l'administre pas\n", $r);
        if ($ecrire) DB::run("UPDATE organisation SET gestion = 'diffusion' WHERE id = ?", [(int)$o['id']]);
    }
}

/* `_meta` dehors. */
$m = DB::one("SELECT id, supprime_le FROM organisation WHERE source_ref = '_meta'");
if ($m && !$m['supprime_le']) {
    echo "\n  _meta        n'est pas une association — effacée en douceur\n";
    if ($ecrire) DB::run("UPDATE organisation SET supprime_le = NOW() WHERE id = ?", [$m['id']]);
}

/* Ce que le Drive porte et que personne ne réclame. On le dit, on ne le crée
   pas: un dossier n'est pas la preuve qu'une association nous concerne. */
/* Tranché par Anna le 16.08.2026: « sao assos antigas que paramos de trabalhar ».
   Leurs Shared Drives restent — on n'efface pas des archives comptables — mais
   elles n'entrent pas en base. Une association terminée dans la liste des
   associations actives est une ligne que quelqu'un relancera un jour pour rien. */
echo "\n  Hors base, et volontairement — anciennes associations, collaboration terminée:\n"
   . "    · LV_CH_BS_LaSecousse\n    · Mandarina & Co Verein\n"
   . "    Leurs Drives restent en place. Ne pas les recréer au prochain import.\n";

printf("\n  %d renommées · %d créées · %d inchangées · %d absentes\n",
       $renommes, $crees, $inchanges, $absents);
if (!$ecrire) echo "  Relance avec --ecrire pour appliquer.\n";

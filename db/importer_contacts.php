<?php
/**
 * Reprise des contacts du dashboard vers la table contact. [16.08.2026]
 *
 *   php db/importer_contacts.php <fichier.json>
 *
 * Le fichier attendu est le tableau des fiches tel que le dashboard les porte,
 * extrait de DEFAULT_CONTACTS ou exporté de la feuille Google.
 *
 * IDEMPOTENT PAR CONSTRUCTION. L'appariement se fait sur `ref`, l'identifiant
 * d'origine, en INSERT ... ON DUPLICATE KEY UPDATE. Relancer le script sur le
 * même fichier ne crée pas de doublon et ne perd rien: c'est ce qui permet de
 * le rejouer après avoir corrigé la source, sans vider la table d'abord.
 *
 * CE QU'IL NE FAIT PAS, ET POURQUOI. Il ne supprime jamais. Une fiche présente
 * en base et absente du fichier est laissée telle quelle. Une reprise qui
 * efface ce qui manque transforme un export partiel en perte de données, et un
 * export partiel est exactement ce qui arrive quand on se trompe de filtre.
 *
 * DEUX COLONNES NE SONT PAS REPRISES: instagram et linkedin. Recompté le
 * 16.08.2026 sur les 8432 fiches — et pas sur 7841, ce qui était le compte
 * d'un export partiel: elles sont vides toutes les deux.
 *
 * `pronom` et `adresse2` L'ÉTAIENT AUSSI DANS CETTE LISTE, à tort. Le recompte
 * donne 236 pronoms et 27 secondes lignes d'adresse. Elles étaient perdues à
 * chaque reprise, et personne ne l'aurait vu: une colonne qu'on n'importe pas
 * ne laisse pas de trace, elle laisse un vide qui ressemble à la réalité.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

const IGNORE = ['instagram', 'linkedin'];

$source = $argv[1] ?? '';
if ($source === '' || !is_file($source)) {
    fwrite(STDERR, "Usage: php db/importer_contacts.php <fichier.json>\n");
    exit(1);
}

$fiches = json_decode((string)file_get_contents($source), true);
if (!is_array($fiches)) {
    fwrite(STDERR, "Le fichier n'est pas un tableau JSON lisible.\n");
    exit(1);
}

$colonnes = ['ref', 'nom', 'prenom', 'nom_famille', 'fonction', 'structure', 'categorie',
             'ville_struct', 'pays_struct', 'region', 'adresse', 'cp', 'ville', 'dept', 'pays',
             'email1', 'email2', 'email_pro1', 'tel1', 'tel_pro1', 'site',
             'mots_cles', 'description', 'participations', 'photo', 'date_mois',
             'date_notes', 'notes'];

$pdo = DB::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$maj = array_map(fn(string $c): string => "$c=VALUES($c)",
                 array_slice($colonnes, 1));   // tout sauf ref, qui est la clef
$sql = 'INSERT INTO contact (' . implode(',', $colonnes) . ') VALUES ('
     . implode(',', array_fill(0, count($colonnes), '?')) . ') '
     . 'ON DUPLICATE KEY UPDATE ' . implode(',', $maj);
$st = $pdo->prepare($sql);

/* La longueur est vérifiée AVANT d'écrire, et non laissée à la base. MariaDB
   tronque en silence hors mode strict, et une note coupée à 200 caractères ne
   se remarque que le jour où on la relit. */
$tailles = [];
foreach ($pdo->query("SELECT COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH
                        FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact'
                         AND CHARACTER_MAXIMUM_LENGTH IS NOT NULL")->fetchAll() as $r) {
    $tailles[$r['COLUMN_NAME']] = (int)$r['CHARACTER_MAXIMUM_LENGTH'];
}

$t0 = microtime(true);
$n = $trop = 0;
$debordements = [];

$pdo->beginTransaction();
foreach ($fiches as $f) {
    $ref = trim((string)($f['id'] ?? ''));
    if ($ref === '') continue;

    $vals = [];
    foreach ($colonnes as $c) {
        $v = $c === 'ref' ? $ref : trim((string)($f[$c] ?? ''));
        if (isset($tailles[$c]) && mb_strlen($v) > $tailles[$c]) {
            $debordements[$c] = ($debordements[$c] ?? 0) + 1;
            $trop++;
            $v = mb_substr($v, 0, $tailles[$c]);
        }
        $vals[] = $v === '' ? null : $v;
    }
    $vals[1] = $vals[1] ?? '(sans nom)';   // nom est NOT NULL
    $st->execute($vals);
    $n++;
}
$pdo->commit();

$ms = (int)round((microtime(true) - $t0) * 1000);
printf("  %d fiches reprises en %d ms\n", $n, $ms);
printf("  %d en base au total\n", (int)$pdo->query('SELECT COUNT(*) FROM contact')->fetchColumn());

if ($debordements) {
    echo "\n  VALEURS TRONQUÉES, la colonne est trop courte:\n";
    foreach ($debordements as $c => $k) printf("    %-16s %d fois\n", $c, $k);
    echo "  Élargir la colonne dans une migration, puis relancer ce script:\n";
    echo "  il est idempotent, la reprise écrasera les valeurs coupées.\n";
}

$vides = array_intersect(IGNORE, array_keys((array)($fiches[0] ?? [])));
if ($vides) printf("\n  Colonnes du dashboard volontairement non reprises: %s\n", implode(', ', $vides));

<?php
/**
 * Reprise des associations et des artistes. [16.08.2026]
 *
 *   php db/importer_organisations.php assocs  <fichier.json>   ASSOC_UNIFIED
 *   php db/importer_organisations.php fiches  <fichier.json>   les fiches légales
 *   php db/importer_organisations.php artists <fichier.json>   DEFAULT_ARTISTS
 *   php db/importer_organisations.php site                     la table artists du CMS
 *
 * QUATRE SOURCES POUR DEUX NOTIONS, et c'est le désordre qu'on range:
 *
 *   ASSOC_UNIFIED   13 associations, nom, pays, direction artistique, comité
 *   les fiches      11 des mêmes, avec IDE, AVS employeur, banque
 *   DEFAULT_ARTISTS 11 artistes, discipline, début de collaboration
 *   artists du CMS  46 lignes, ce qui est publié sur le site
 *
 * Les deux premières décrivent LES MÊMES entités et se complètent: on les charge
 * l'une après l'autre sur la même clef `source_ref`, et la seconde enrichit sans
 * écraser ce que la première a posé.
 *
 * L'ORDRE COMPTE. assocs d'abord, fiches ensuite: les fiches n'ont pas le nom,
 * seulement l'identifiant. Passer fiches en premier créerait des lignes sans nom.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$mode = $argv[1] ?? '';
$f    = $argv[2] ?? '';
if (!in_array($mode, ['assocs', 'fiches', 'artists', 'site'], true)) {
    fwrite(STDERR, "Usage: php db/importer_organisations.php assocs|fiches|artists|site [fichier]\n");
    exit(1);
}

$pdo = DB::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * Écrit une organisation. Les champs vides du fichier n'écrasent JAMAIS ce qui
 * est déjà en base: c'est ce qui permet d'enchaîner plusieurs sources qui se
 * complètent, chacune apportant ce qu'elle sait.
 */
function poser(PDO $pdo, string $source, string $ref, array $d): void
{
    $existe = $pdo->prepare('SELECT id FROM organisation WHERE source = ? AND source_ref = ?');
    $existe->execute([$source, $ref]);
    $id = (int)$existe->fetchColumn();

    $d = array_filter($d, fn($v) => $v !== null && $v !== '');
    if (!$d && $id) return;

    if ($id) {
        $set = implode(',', array_map(fn($k) => "$k=?", array_keys($d)));
        $pdo->prepare("UPDATE organisation SET $set WHERE id = ?")
            ->execute([...array_values($d), $id]);
    } else {
        $d['source'] = $source; $d['source_ref'] = $ref;
        $d['nom'] ??= '(sans nom)';
        $cols = implode(',', array_keys($d));
        $q    = implode(',', array_fill(0, count($d), '?'));
        $pdo->prepare("INSERT INTO organisation ($cols) VALUES ($q)")->execute(array_values($d));
    }
}

$lire = function () use ($f) {
    if (!is_file($f)) { fwrite(STDERR, "Fichier introuvable: $f\n"); exit(1); }
    return json_decode((string)file_get_contents($f), true) ?: [];
};

$n = 0;

if ($mode === 'assocs') {
    foreach ($lire() as $a) {
        poser($pdo, 'assoc', (string)($a['id'] ?? ''), [
            'genre'     => 'association',
            'nom'       => $a['nom'] ?? null,
            'nom_legal' => $a['nomLegal'] ?? null,
            'pays'      => $a['pays'] ?? null,
            'adresse'   => $a['adresse'] ?? null,
            'email'     => $a['email'] ?? null,
            'site'      => $a['site'] ?? null,
            'direction' => $a['da'] ?? null,
            'comite'    => is_array($a['comite'] ?? null) ? implode(', ', $a['comite']) : ($a['comite'] ?? null),
        ]);
        $n++;
    }
}

if ($mode === 'fiches') {
    foreach ($lire() as $a) {
        poser($pdo, 'assoc', (string)($a['id'] ?? ''), [
            'genre'         => 'association',
            'ide'           => $a['ide'] ?? null,
            'registre'      => $a['reg'] ?? null,
            'avs_employeur' => $a['avs_asso'] ?? null,
            'ree'           => $a['ree'] ?? null,
            'pays'          => $a['pays'] ?? null,
            'adresse'       => $a['adresse'] ?? null,
            'email'         => $a['email'] ?? null,
            'site'          => $a['site'] ?? null,
            'instagram'     => $a['instagram'] ?? null,
            'banque_nom'    => $a['banque_nom'] ?? null,
            'banque_iban'   => $a['banque_iban'] ?? null,
            'banque_bic'    => $a['banque_bic'] ?? null,
        ]);
        $n++;
    }
}

if ($mode === 'artists') {
    $st = ['actif' => 'actif', 'pause' => 'pause', 'terminé' => 'termine', 'termine' => 'termine'];
    foreach ($lire() as $a) {
        poser($pdo, 'artiste', (string)($a['id'] ?? ''), [
            'genre'        => 'artiste',
            'nom'          => $a['name'] ?? null,
            'discipline'   => $a['disc'] ?? null,
            'pays'         => $a['country'] ?? null,
            'canton'       => $a['canton'] ?? null,
            'direction'    => $a['da'] ?? null,
            'email'        => $a['email'] ?? null,
            'telephone'    => $a['phone'] ?? null,
            'site'         => $a['web'] ?? null,
            'adresse'      => $a['saddress'] ?? null,
            'notes'        => $a['notes'] ?? null,
            'statut'       => $st[strtolower(trim((string)($a['statut'] ?? '')))] ?? 'actif',
            'debut_collab' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($a['debutCollab'] ?? ''))
                              ? $a['debutCollab'] : null,
        ]);
        $n++;
    }
}

if ($mode === 'site') {
    /* Le CMS publie 46 artistes, ce qui est plus que les 11 du dashboard: il y a
       là les collaborations passées. On les charge avec leur statut du site,
       « former » devenant « termine ». */
    foreach ($pdo->query("SELECT id, name, status, website_url FROM artists")->fetchAll() as $a) {
        poser($pdo, 'site', 'ar' . $a['id'], [
            'genre'  => 'artiste',
            'nom'    => $a['name'],
            'site'   => $a['website_url'] ?: null,
            'statut' => ($a['status'] ?? '') === 'former' ? 'termine' : 'actif',
        ]);
        $n++;
    }
}

printf("  %d ligne(s) traitée(s) depuis %s\n", $n, $mode);
foreach ($pdo->query("SELECT genre, COUNT(*) n FROM organisation
                       WHERE supprime_le IS NULL GROUP BY genre")->fetchAll() as $r) {
    printf("  %-14s %d\n", $r['genre'], $r['n']);
}

/* Les mêmes noms venus de deux sources: le dashboard et le site nomment les
   mêmes compagnies. On les signale sans fusionner, comme pour les bookings. */
$d = $pdo->query("SELECT nom, COUNT(*) n, GROUP_CONCAT(source) s FROM organisation
                   WHERE supprime_le IS NULL GROUP BY nom HAVING COUNT(DISTINCT source) > 1")->fetchAll();
if ($d) {
    printf("\n  %d nom(s) présents dans plusieurs sources:\n", count($d));
    foreach (array_slice($d, 0, 12) as $x) printf("    %-42s %s\n", mb_substr($x['nom'], 0, 42), $x['s']);
}

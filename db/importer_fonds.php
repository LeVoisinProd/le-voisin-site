<?php
/**
 * Reprise des demandes de fonds depuis l'export du dashboard. [16.08.2026]
 *
 *   php db/importer_fonds.php <fichier.json>
 *
 * Le fichier attendu est l'export complet du dashboard — celui que le bouton
 * de sauvegarde produit — dont on ne lit que la clef `lv-grants`.
 *
 * IDEMPOTENT PAR `ref`, l'identifiant du dashboard: rejouer la reprise met à
 * jour au lieu de dupliquer. Sans cela, deux passages donneraient 174 demandes
 * dont la moitié fantômes, et le taux d'obtention serait faux de moitié.
 *
 * CE QU'IL NE DEVINE PAS. Un statut ou une priorité que le modèle ne connaît
 * pas retombe sur la valeur par défaut au lieu de faire échouer la ligne: une
 * reprise qui s'arrête à la douzième demande sur 87 est pire qu'une reprise
 * complète avec deux valeurs à corriger, qui se voient à l'écran.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$fichier = $argv[1] ?? '';
if ($fichier === '' || !is_file($fichier)) {
    fwrite(STDERR, "Usage: php db/importer_fonds.php <export.json>\n");
    exit(1);
}

$j = json_decode((string)file_get_contents($fichier), true);
if (!is_array($j) || !isset($j['lv-grants']) || !is_array($j['lv-grants'])) {
    fwrite(STDERR, "Ce fichier ne porte pas de clef `lv-grants`.\n");
    exit(1);
}

const PRIOS_OK   = ['P0','P1','P2','P3','P4'];
const STATUTS_OK = ['a-preparer','en-cours','soumis','en-attente','en-suspens',
                    'accorde','refuse','decompte'];

$pdo = DB::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$cols = ['ref','asso','inst','proj','type','canton','priorite','statut',
         'demande','accorde','delai','reponse','notes'];
$st = $pdo->prepare('INSERT INTO demande_fonds (' . implode(',', $cols) . ') VALUES ('
    . implode(',', array_fill(0, count($cols), '?')) . ') ON DUPLICATE KEY UPDATE '
    . implode(',', array_map(fn($c) => "$c=VALUES($c)", array_slice($cols, 1))));

$vide = static fn($x): ?string => ($x ?? '') !== '' ? (string)$x : null;
$date = static function ($x): ?string {
    $x = trim((string)($x ?? ''));
    return $x !== '' && strtotime($x) !== false ? date('Y-m-d', strtotime($x)) : null;
};

$n = $corriges = 0;
foreach ($j['lv-grants'] as $g) {
    if (!is_array($g)) continue;
    $p = (string)($g['priority'] ?? '');
    $s = (string)($g['status'] ?? '');
    if (!in_array($p, PRIOS_OK, true))   { $p = 'P2';         $corriges++; }
    if (!in_array($s, STATUTS_OK, true)) { $s = 'a-preparer'; $corriges++; }

    $st->execute([
        $vide($g['id'] ?? null),
        (string)($g['asso'] ?? '—'),
        (string)($g['inst'] ?? '—'),
        $vide($g['proj'] ?? null),
        $vide($g['type'] ?? null),
        $vide($g['canton'] ?? null),
        $p, $s,
        ((float)($g['amount']  ?? 0)) ?: null,
        ((float)($g['granted'] ?? 0)) ?: null,
        $date($g['deadline'] ?? null),
        $date($g['response'] ?? null),
        $vide($g['notes'] ?? null),
    ]);
    $n++;
}

$t = DB::one('SELECT COUNT(*) n, SUM(demande) d, SUM(accorde) a
                FROM demande_fonds WHERE supprime_le IS NULL');
printf("%d demandes reprises%s\n", $n, $corriges ? " ($corriges valeur(s) inconnue(s) ramenée(s) au défaut)" : '');
printf("%d en base — demandé %s, accordé %s, taux %.0f %%\n",
    (int)$t['n'], number_format((float)$t['d'], 0, ',', ' '),
    number_format((float)$t['a'], 0, ',', ' '),
    (float)$t['d'] > 0 ? (float)$t['a'] / (float)$t['d'] * 100 : 0);

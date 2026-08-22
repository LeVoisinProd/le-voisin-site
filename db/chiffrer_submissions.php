<?php
/**
 * Chiffre les copies de formulaires restées en clair.  [22.08.2026]
 *
 * Point 1 de la revue de sécurité du 22.08. La table `submissions` garde une
 * copie de chaque envoi de formulaire; celui des notes de frais porte un IBAN.
 * Mesuré ce jour-là: 28 lignes, 28 IBAN lisibles, la dernière écrite la veille.
 * Et 18 se retrouvaient en clair dans le dump déposé sur le Drive.
 *
 *   php db/chiffrer_submissions.php              dit ce qu'il ferait, n'écrit rien
 *   php db/chiffrer_submissions.php --appliquer  écrit
 *
 * IL EST BÂTI SUR `chiffrer_fiches.php`, QUI A DÉJÀ SERVI EN PRODUCTION le
 * 16.08 sur 51 valeurs sans une seule perte. Les trois gardes sont les mêmes,
 * parce qu'elles ont été écrites contre des dangers réels:
 *
 *   1. ON VÉRIFIE QUE LA CLEF ACTUELLE OUVRE CE QUI EST DÉJÀ CHIFFRÉ. Si le
 *      `secret` de `config.php` a changé depuis, chiffrer davantage avec la
 *      nouvelle clef rendrait le reste définitivement illisible. On refuse.
 *
 *   2. ALLER-RETOUR SUR CHAQUE VALEUR AVANT D'ÉCRIRE. On chiffre, on déchiffre,
 *      on compare à l'original. Une seule différence et rien n'est écrit du
 *      tout — pas « on saute celle-là », rien.
 *
 *   3. ON ÉCRIT DANS UNE TRANSACTION. Vingt-huit lignes passent ensemble ou
 *      aucune ne passe.
 *
 * CE QU'IL NE FAIT PAS: toucher aux dumps déjà déposés sur le Drive. Ceux-là
 * portent les IBAN en clair et resteront ainsi. Il faudra soit les remplacer par
 * un dump pris après ce script, soit les traiter pour ce qu'ils sont — des
 * fichiers de données bancaires nominatives.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../app/bootstrap.php';

const PREFIXE = 'sb1:';

$appliquer = in_array('--appliquer', $argv, true);

echo "\n  Chiffrement des copies de formulaires\n";
echo "  ─────────────────────────────────────\n\n";
if (!$appliquer) {
    echo "  LECTURE SEULE. Rien ne sera écrit.\n";
    echo "  Pour écrire: php db/chiffrer_submissions.php --appliquer\n\n";
}

/* ── Garde 1: la clef actuelle ouvre-t-elle ce qui est déjà chiffré? ───────── */
$deja = DB::val('SELECT `data` FROM submissions WHERE `data` LIKE ? LIMIT 1', [PREFIXE . '%']);
if ($deja !== null) {
    $essai = Crypto::dechiffrer((string)$deja);
    if ($essai === '' || json_decode($essai, true) === null) {
        fwrite(STDERR, "  ARRÊT: une copie déjà chiffrée ne s'ouvre pas avec la clef actuelle.\n"
                     . "  Le `secret` de config.php a changé. Rien n'a été touché.\n\n");
        exit(1);
    }
    echo "  La clef actuelle ouvre ce qui est déjà chiffré.\n";
}

/* ── Inventaire ───────────────────────────────────────────────────────────── */
$lignes    = DB::all('SELECT id, form, `data`, created_at FROM submissions ORDER BY id');
$aFaire    = [];
$dejaFait  = 0;
$vides     = 0;
$parForm   = [];

foreach ($lignes as $l) {
    $v = (string)($l['data'] ?? '');
    if (trim($v) === '')                     { $vides++;    continue; }
    if (str_starts_with($v, PREFIXE))        { $dejaFait++; continue; }
    $aFaire[] = ['id' => (int)$l['id'], 'clair' => $v, 'created_at' => $l['created_at']];
    $f = (string)$l['form'];
    $parForm[$f] = ($parForm[$f] ?? 0) + 1;
}

printf("  %d ligne(s) en tout · %d à chiffrer · %d déjà chiffrée(s) · %d vide(s)\n",
       count($lignes), count($aFaire), $dejaFait, $vides);
foreach ($parForm as $f => $n) printf("     formulaire « %s »: %d\n", $f, $n);

/* Ce qu'on est en train de protéger, dit en clair une dernière fois. */
$avecIban = 0;
foreach ($aFaire as $t) {
    $d = json_decode($t['clair'], true);
    if (!is_array($d)) continue;
    foreach ($d as $k => $_) if (stripos((string)$k, 'iban') !== false) { $avecIban++; break; }
}
printf("  dont %d avec un IBAN lisible\n\n", $avecIban);

if (!$aFaire) { echo "  Rien à faire.\n\n"; exit(0); }

if (!$appliquer) {
    echo "  Rien n'a été écrit. Relancer avec --appliquer pour le faire.\n\n";
    exit(0);
}

/* ── Garde 2: l'aller-retour, sur chacune, avant d'écrire quoi que ce soit ── */
$prets = [];
foreach ($aFaire as $t) {
    $chiffre = Crypto::chiffrer($t['clair']);
    if (Crypto::dechiffrer($chiffre) !== $t['clair']) {
        fwrite(STDERR, "  ARRÊT: aller-retour raté sur la ligne {$t['id']}. Rien n'est écrit.\n\n");
        exit(1);
    }
    $prets[] = ['id' => $t['id'], 'chiffre' => $chiffre, 'created_at' => $t['created_at']];
}
echo "  Aller-retour vérifié sur les " . count($prets) . " valeurs.\n";

/* ── Garde 3: tout ou rien ────────────────────────────────────────────────── */
$pdo = DB::pdo();
$pdo->beginTransaction();
try {
    /* `created_at` est réécrit tel quel: la colonne pourrait porter un
       ON UPDATE, et vingt-huit copies qui prendraient toutes la date
       d'aujourd'hui feraient croire que les gens ont renvoyé leurs notes de
       frais ce matin. */
    $st = $pdo->prepare('UPDATE submissions SET `data` = ?, created_at = ? WHERE id = ?');
    foreach ($prets as $p) $st->execute([$p['chiffre'], $p['created_at'], $p['id']]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "  ARRÊT: l'écriture a échoué, rien n'a changé. " . $e->getMessage() . "\n\n");
    exit(1);
}

/* ── Vérification par l'extérieur, pas par le script qui vient d'écrire ───── */
$reste = (int)DB::val('SELECT COUNT(*) FROM submissions WHERE `data` <> "" AND `data` NOT LIKE ?',
                      [PREFIXE . '%']);
$ok    = 0;
foreach (DB::all('SELECT id, `data` FROM submissions WHERE `data` LIKE ?', [PREFIXE . '%']) as $l) {
    $j = json_decode(Crypto::dechiffrer((string)$l['data']), true);
    if (is_array($j)) $ok++;
}
printf("\n  Écrit. %d valeur(s) chiffrée(s) se rouvrent et rendent du JSON valide.\n", $ok);
printf("  Restant en clair: %d\n\n", $reste);

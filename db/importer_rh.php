<?php
/**
 * Reprise du personnel et des engagements. [16.08.2026]
 *
 *   php db/importer_rh.php <export.json> [--ecrire]
 *
 * Lit `lv-rh-employees` (89) et `lv-rh-engagements` (72).
 *
 * L'AVS ET L'IBAN SONT CHIFFRÉS À L'ENTRÉE, jamais écrits en clair. Ce matin la
 * base a été nettoyée de 36 valeurs en clair; une reprise qui les remettrait
 * défairait la journée en une commande. `Crypto::` est celui des fiches, avec
 * la même clef.
 *
 * LE RATTACHEMENT AU COMPTE DU CMS SE FAIT PAR L'E-MAIL, et seulement s'il est
 * exact. 89 personnes ici, 49 comptes là: l'écart n'est pas une erreur, c'est
 * le personnel de tournée qui n'ouvre jamais l'espace personnel. Rapprocher sur
 * le nom donnerait des faux — deux « Alessandra » dans la maison — et un faux
 * lien ferait lire l'AVS de quelqu'un d'autre.
 *
 * IDEMPOTENT par `source_ref`. Relancer met à jour, ne duplique pas.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

$fichier = '';
foreach (array_slice($argv, 1) as $a) if ($a !== '--ecrire') { $fichier = $a; break; }
$ecrire = in_array('--ecrire', $argv, true);

if ($fichier === '' || !is_file($fichier)) {
    fwrite(STDERR, "Usage: php db/importer_rh.php <export.json> [--ecrire]\n");
    exit(1);
}
$j = json_decode((string)file_get_contents($fichier), true);
if (!is_array($j)) { fwrite(STDERR, "JSON illisible.\n"); exit(1); }

$liste = static function ($x): array {
    if (!is_array($x)) return [];
    return array_is_list($x) ? $x : array_values($x);
};
$emps = $liste($j['lv-rh-employees']   ?? []);
$engs = $liste($j['lv-rh-engagements'] ?? []);

echo $ecrire ? "ÉCRITURE\n\n" : "SIMULATION — rien n'est écrit. --ecrire pour appliquer.\n\n";

/* Les comptes du CMS, par e-mail en minuscules. */
$comptes = [];
foreach (DB::all('SELECT id, email FROM collaborators') as $c) {
    $e = mb_strtolower(trim((string)$c['email']));
    if ($e !== '') $comptes[$e] = (int)$c['id'];
}
/** Une forme comparable: sans accents, sans casse, sans ponctuation. */
$norm = static function (string $x): string {
    $x = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $x) ?: $x;
    return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($x)) ?? '');
};

/* LES ASSOCIATIONS SE RAPPROCHENT PAR LE NOM, PAS PAR LA CLEF. Mesuré avant
   d'écrire: le champ `asso` de `lv-rh-employees` porte « Watering Hole »,
   « Le Voisin FR », « Hibiscus Culturiste » — le nom lisible, jamais la clef
   `watering` ou `lv-fr`. La première version rapprochait sur la clef et
   rattachait zéro personne sur 89, en silence. */
$assos = [];
foreach (DB::all("SELECT id, nom, source_ref FROM organisation
                   WHERE supprime_le IS NULL") as $o) {
    $assos[$norm((string)$o['nom'])] ??= (int)$o['id'];
    if ($o['source_ref'] !== null) $assos[$norm((string)$o['source_ref'])] ??= (int)$o['id'];
}

$txt  = static fn($x, int $max = 190): ?string
    => trim((string)($x ?? '')) !== '' ? mb_substr(trim((string)$x), 0, $max) : null;
$num  = static function ($x): ?float {
    $t = str_replace([' ', "'", ' ', ','], ['', '', '', '.'], trim((string)($x ?? '')));
    return $t !== '' && is_numeric($t) ? (float)$t : null;
};
$date = static function ($x): ?string {
    $s = trim((string)($x ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
    if (preg_match('#^(\d{2})[./](\d{2})[./](\d{4})$#', $s, $m)) return "$m[3]-$m[2]-$m[1]";
    return null;
};
/* Le secret ne traverse jamais la base en clair. */
$secr = static fn($x): ?string
    => trim((string)($x ?? '')) !== '' ? Crypto::chiffrer(trim((string)$x)) : null;

// ── Les personnes ───────────────────────────────────────────────────────────
$nEmp = $majEmp = $lies = 0;
$parRef = [];        // id dashboard  => id en base
$parNom = [];        // nom normalisé => id en base
$assoInconnue = [];
foreach ($emps as $e) {
    $ref = $txt($e['id'] ?? '', 32);
    $nom = $txt($e['nom'] ?? '', 96);
    if ($nom === null) continue;

    $mail = mb_strtolower(trim((string)($e['email'] ?? '')));
    $cid  = $mail !== '' ? ($comptes[$mail] ?? null) : null;
    if ($cid) $lies++;

    $aref = $txt($e['asso'] ?? '', 32);
    $aid  = $aref !== null ? ($assos[$norm($aref)] ?? null) : null;
    if ($aref !== null && $aid === null) $assoInconnue[$aref] = ($assoInconnue[$aref] ?? 0) + 1;
    $champs = [
        'collaborator_id' => $cid,
        'prenom'          => $txt($e['prenom'] ?? '', 96),
        'nom'             => $nom,
        'pronom'          => $txt($e['pronom'] ?? '', 32),
        'email'           => $txt($e['email'] ?? ''),
        'telephone'       => $txt($e['tel'] ?? '', 48),
        'asso_ref'        => $aref,
        'organisation_id' => $aid,
        'fonction'        => $txt($e['fonction'] ?? ''),
        'type_engagement' => $txt($e['type'] ?? '', 60),
        'paie_mensuelle'  => $num($e['paieM'] ?? null),
        'paie_horaire'    => $num($e['paieH'] ?? null),
        'naissance'       => $date($e['naissance'] ?? null),
        'nationalite'     => $txt($e['nationalite'] ?? '', 96),
        'permis'          => $txt($e['permis'] ?? '', 60),
        'rue'             => $txt($e['rue'] ?? ''),
        'cp'              => $txt($e['cp'] ?? '', 20),
        'ville'           => $txt($e['ville'] ?? '', 96),
        'pays'            => $txt($e['pays'] ?? '', 64),
        'avs'             => $secr($e['avs'] ?? null),
        'iban'            => $secr($e['iban'] ?? null),
    ];

    $ex = $ref !== null ? DB::one('SELECT id FROM rh_employe WHERE source_ref = ?', [$ref]) : null;
    if ($ex) {
        $majEmp++;
        if ($ecrire) {
            /* L'AVS et l'IBAN ne sont réécrits que si la reprise en apporte un:
               un champ vide côté dashboard ne doit pas effacer ce qui a été
               saisi ici depuis. */
            $m = $champs;
            if ($m['avs']  === null) unset($m['avs']);
            if ($m['iban'] === null) unset($m['iban']);
            $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($m)));
            DB::run("UPDATE rh_employe SET $sets WHERE id = ?", [...array_values($m), (int)$ex['id']]);
        }
        $parRef[$ref] = (int)$ex['id'];
        $parNom[$norm(trim(((string)$champs['prenom']) . ' ' . $nom))] = (int)$ex['id'];
    } else {
        $nEmp++;
        if ($ecrire) {
            $cols = array_merge(['source_ref'], array_keys($champs));
            $tr   = implode(', ', array_fill(0, count($cols), '?'));
            DB::run("INSERT INTO rh_employe (`" . implode('`, `', $cols) . "`) VALUES ($tr)",
                    [$ref, ...array_values($champs)]);
            $id = (int)DB::pdo()->lastInsertId();
            $parRef[$ref] = $id;
            $parNom[$norm(trim(((string)$champs['prenom']) . ' ' . $nom))] = $id;
        }
    }
}
printf("  personnes    %d nouvelles · %d mises à jour · %d rattachées à un compte du CMS\n",
       $nEmp, $majEmp, $lies);
if ($assoInconnue) {
    echo "               associations non reconnues: ";
    foreach ($assoInconnue as $a => $n) echo "$a ($n) ";
    echo "\n";
}

// ── Les engagements ─────────────────────────────────────────────────────────
$nEng = $majEng = $orph = 0;
foreach ($engs as $g) {
    $ref = $txt($g['id'] ?? '', 32);
    $nom = $txt($g['empNom'] ?? '');
    /* LE RAPPROCHEMENT SE FAIT PAR LE NOM, PAS PAR `empId`. Mesuré: 2 des 72
       `empId` correspondent à un `id` d'employé — les autres pointent vers une
       numérotation antérieure du dashboard. Le nom, lui, est rempli sur les 72.
       On garde `empId` en second recours pour les deux qui marchent. */
    $eref = $txt($g['empId'] ?? '', 32);
    $eid  = 0;
    if ($nom !== null) $eid = $parNom[$norm($nom)] ?? 0;
    if (!$eid && $eref !== null) {
        $eid = $parRef[$eref] ?? (int)(DB::val('SELECT id FROM rh_employe WHERE source_ref = ?', [$eref]) ?: 0);
    }
    if (!$eid && $nom !== null) {
        $eid = (int)(DB::val("SELECT id FROM rh_employe
                               WHERE supprime_le IS NULL
                                 AND LOWER(CONCAT(COALESCE(prenom,''), ' ', nom)) = LOWER(?)",
                             [$nom]) ?: 0);
    }
    if (!$eid) $orph++;

    $aref = $txt($g['asso'] ?? '', 32);
    $deb  = $date($g['debut'] ?? null);
    $champs = [
        'employe_id'      => $eid ?: null,
        'employe_nom'     => $nom,
        'asso_ref'        => $aref,
        'organisation_id' => $aref !== null ? ($assos[$norm($aref)] ?? null) : null,
        'projet'          => $txt($g['projet'] ?? ''),
        'debut'           => $deb,
        'fin'             => $date($g['fin'] ?? null),
        /* `mois` sert au groupement des salaires. Le dashboard le porte parfois;
           sinon on le déduit de la date de début, jamais de la date de fin —
           un engagement à cheval sur deux mois se paie sur celui où il commence. */
        'mois'            => $txt($g['mois'] ?? '', 7) ?? ($deb !== null ? substr($deb, 0, 7) : null),
        'jours'           => $num($g['nbJ'] ?? null),
        'heures'          => $num($g['nbH'] ?? null),
        'duree_jours'     => $num($g['dureeJ'] ?? null),
        'paie_mensuelle'  => $num($g['paieM'] ?? null),
        'paie_horaire'    => $num($g['paieH'] ?? null),
        'statut'          => $txt($g['statut'] ?? '', 40),
    ];

    $ex = $ref !== null ? DB::one('SELECT id FROM rh_engagement WHERE source_ref = ?', [$ref]) : null;
    if ($ex) {
        $majEng++;
        if ($ecrire) {
            $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($champs)));
            DB::run("UPDATE rh_engagement SET $sets WHERE id = ?", [...array_values($champs), (int)$ex['id']]);
        }
    } else {
        $nEng++;
        if ($ecrire) {
            $cols = array_merge(['source_ref'], array_keys($champs));
            $tr   = implode(', ', array_fill(0, count($cols), '?'));
            DB::run("INSERT INTO rh_engagement (`" . implode('`, `', $cols) . "`) VALUES ($tr)",
                    [$ref, ...array_values($champs)]);
        }
    }
}
printf("  engagements  %d nouveaux · %d mis à jour", $nEng, $majEng);
if ($orph) printf(" · %d sans personne rattachée (le nom reste lisible)", $orph);
echo "\n";

if (!$ecrire) echo "\n  Relance avec --ecrire pour appliquer.\n";

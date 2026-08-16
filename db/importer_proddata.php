<?php
/**
 * Reprendre les couches de production du dashboard. [17.08.2026]
 *
 *   php db/importer_proddata.php [--ecrire]
 *
 * `lv-prodData` porte dix lignes et c'est la table qui correspond exactement à
 * `projet_prod.donnees` — mêmes onglets, mêmes noms de clefs. La colonne était
 * VIDE sur les seize fiches, mesuré avant d'écrire ceci.
 *
 * CE QU'ELLE CONTIENT VRAIMENT, après lecture des dix lignes:
 *
 *   `p4`            une déclaration SSA renseignée — genre, langue, producteur,
 *                   date de première — et une date de jeu. C'est du travail
 *                   fait, et l'écran des droits d'auteur l'attend.
 *   `p-dv-2026-*`   neuf lignes, la MÊME formation de six musiciens répétée.
 *                   Ce sont les neuf mois de tournée de Dolce Vita, pas neuf
 *                   spectacles.
 *
 * LES NEUF NE SONT PAS NEUF PROJETS, et c'est le piège de cette table. Le
 * dashboard mélange les niveaux — une pièce, un mois de tournée d'une pièce,
 * un artiste — et la reprise des projets avait déjà écarté ces neuf pour cette
 * raison. Leur formation, elle, est celle de la pièce: on la reprend UNE fois,
 * sur Dolce Vita, et non neuf fois sur neuf lignes qui n'existent pas.
 *
 * `empId` NE MÈNE NULLE PART et on ne s'en sert pas. Le dashboard porte une
 * numérotation antérieure: sur les 72 engagements, 2 identifiants sur 72
 * retombaient sur la bonne personne. Le rapprochement se fait sur le nom, et
 * ce qu'il ne trouve pas est écrit quand même — un musicien sans fiche RH
 * reste un musicien de la formation.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

$ecrire = in_array('--ecrire', $argv, true);
$src    = null;
foreach (array_slice($argv, 1) as $a) if ($a !== '--ecrire') { $src = $a; break; }
$src ??= getenv('HOME') . '/export.json';

if (!is_file($src)) { fwrite(STDERR, "Export introuvable: $src\n"); exit(1); }
$export = json_decode((string)file_get_contents($src), true);
if (!is_array($export)) { fwrite(STDERR, "Export illisible.\n"); exit(1); }

echo $ecrire ? "ÉCRITURE\n\n" : "SIMULATION — rien n'est écrit. --ecrire pour appliquer.\n\n";

$prodData = is_array($export['lv-prodData'] ?? null) ? $export['lv-prodData'] : [];
if (!$prodData) { echo "  lv-prodData vide.\n"; exit; }

/* ── Où va chaque ligne ─────────────────────────────────────────────────── */

$parRef = [];
foreach (DB::all("SELECT project_id, source_ref FROM projet_prod WHERE source_ref IS NOT NULL AND source_ref <> ''") as $r)
    $parRef[(string)$r['source_ref']] = (int)$r['project_id'];

/* Les neuf mois de tournée n'ont pas de fiche à eux. Leur contenu appartient
   à la pièce, et la pièce est nommée ici plutôt que devinée: « Dolce Vita —
   Août 2026 » ressemble assez à « Dolce Vita - TO! » pour qu'un rapprochement
   automatique se trompe de cible sans le dire. */
$dolce = DB::val("SELECT id FROM projects
                   WHERE title_en LIKE 'Dolce Vita%' OR title_fr LIKE 'Dolce Vita%'
                   ORDER BY id LIMIT 1");

$cible = function (string $ref) use ($parRef, $dolce): ?int {
    if (isset($parRef[$ref])) return $parRef[$ref];
    if (str_starts_with($ref, 'p-dv-')) return $dolce ? (int)$dolce : null;
    return null;
};

/* ── Les personnes, par leur nom ────────────────────────────────────────── */

$gens = [];
foreach (DB::all("SELECT id, prenom, nom FROM rh_employe WHERE supprime_le IS NULL") as $e) {
    $k = mb_strtolower(trim($e['prenom'] . ' ' . $e['nom']));
    $gens[$k] = (int)$e['id'];
}

/* ── La reprise ─────────────────────────────────────────────────────────── */

$vus = $faits = $sansCible = 0;
$equipeFaite = [];          // pour ne pas écrire neuf fois la même formation

foreach ($prodData as $ref => $src2) {
    if (!is_array($src2)) continue;
    $vus++;
    $ref = (string)$ref;
    $pid = $cible($ref);
    if ($pid === null) {
        printf("  ✗ %-16s aucune fiche correspondante — LAISSÉE\n", $ref);
        $sansCible++;
        continue;
    }

    $d   = ProdFiche::donnees($pid);
    $chg = [];

    /* — La formation — */
    if (is_array($src2['equipe'] ?? null) && $src2['equipe']
        && !($d['equipe'] ?? []) && !isset($equipeFaite[$pid])) {
        $eq = [];
        foreach ($src2['equipe'] as $m) {
            if (!is_array($m)) continue;
            $pr = trim((string)($m['prenom'] ?? ''));
            $no = trim((string)($m['nom'] ?? ''));
            if ($pr === '' && $no === '') continue;
            $eq[] = [
                'fonction' => trim((string)($m['fonction'] ?? '')),
                'prenom'   => $pr,
                'nom'      => $no,
                /* l'identifiant de CHEZ NOUS, retrouvé par le nom, ou rien */
                'empId'    => (string)($gens[mb_strtolower("$pr $no")] ?? ''),
            ];
        }
        if ($eq) {
            $d['equipe'] = $eq;
            $lies = count(array_filter($eq, fn($x) => $x['empId'] !== ''));
            $chg[] = sprintf('equipe(%d, dont %d avec fiche RH)', count($eq), $lies);
            $equipeFaite[$pid] = true;
        }
    }

    /* — La déclaration SSA — */
    $ssa = $src2['droits']['ssa'] ?? null;
    if (is_array($ssa) && !($d['droits']['ssa'] ?? [])) {
        $garde = array_filter($ssa, fn($v) => is_string($v) ? trim($v) !== '' : !empty($v));
        if ($garde) { $d['droits']['ssa'] = $garde; $chg[] = 'droits.ssa(' . count($garde) . ')'; }
    }
    foreach (['cols', 'auteurs'] as $c) {
        if (!empty($src2['droits'][$c]) && empty($d['droits'][$c])) {
            $d['droits'][$c] = $src2['droits'][$c];
            $chg[] = "droits.$c(" . count($src2['droits'][$c]) . ')';
        }
    }
    foreach (['editeur', 'repartition', 'notes'] as $c) {
        $v = trim((string)($src2['droits'][$c] ?? ''));
        if ($v !== '' && trim((string)($d['droits'][$c] ?? '')) === '') {
            $d['droits'][$c] = $v; $chg[] = "droits.$c";
        }
    }

    /* — Les dates de travail —
         Elles s'ajoutent à celles déjà là, sans doublon: une même date peut
         venir des deux côtés, et deux lignes identiques sur un planning se
         voient tout de suite mais se corrigent une par une. */
    $dates = $src2['planning']['dates'] ?? [];
    if (is_array($dates) && $dates) {
        $vues = [];
        foreach ((array)($d['planning']['dates'] ?? []) as $x)
            if (is_array($x)) $vues[($x['debut'] ?? '') . '|' . ($x['fin'] ?? '')] = true;
        $add = 0;
        foreach ($dates as $x) {
            if (!is_array($x) || trim((string)($x['debut'] ?? '')) === '') continue;
            $k = ($x['debut'] ?? '') . '|' . ($x['fin'] ?? '');
            if (isset($vues[$k])) continue;
            $vues[$k] = true;
            $d['planning']['dates'][] = $x;
            $add++;
        }
        if ($add) $chg[] = "planning.dates(+$add)";
    }

    /* — Les textes libres, seulement s'ils sont vides ici — */
    foreach (['resume', 'coproductions', 'soutiens'] as $c) {
        $v = trim((string)($src2[$c] ?? ''));
        if ($v !== '' && trim((string)($d[$c] ?? '')) === '') { $d[$c] = $v; $chg[] = $c; }
    }
    foreach (['dossier' => ['lettre','description','intention','calendrier','publicCible','benefice'],
              'statistiques' => ['representations','spectateurs','recettes','villes','notes'],
              'diffusionDocs' => ['dossier','fiches','photos']] as $sec => $champs) {
        foreach ($champs as $c) {
            $v = trim((string)($src2[$sec][$c] ?? ''));
            if ($v !== '' && trim((string)($d[$sec][$c] ?? '')) === '') { $d[$sec][$c] = $v; $chg[] = "$sec.$c"; }
        }
    }
    foreach (['remuneration', 'budget', 'partenaires'] as $c) {
        if (!empty($src2[$c]) && empty($d[$c])) { $d[$c] = $src2[$c]; $chg[] = "$c(" . count($src2[$c]) . ')'; }
    }
    foreach (ProdFiche::LOGI as $c => $_) {
        if (!empty($src2['logistique'][$c]) && empty($d['logistique'][$c])) {
            $d['logistique'][$c] = $src2['logistique'][$c];
            $chg[] = "logistique.$c(" . count($src2['logistique'][$c]) . ')';
        }
    }

    if (!$chg) continue;
    printf("  · %-16s → #%-3d  %s\n", $ref, $pid, implode(', ', $chg));
    $faits++;
    if ($ecrire) ProdFiche::ecrire($pid, $d);
}

printf("\n  %d ligne(s) lue(s) · %d reprise(s)", $vus, $faits);
if ($sansCible) printf(" · %d sans fiche", $sansCible);
echo "\n";
if (!$ecrire) echo "  Relance avec --ecrire pour appliquer.\n";

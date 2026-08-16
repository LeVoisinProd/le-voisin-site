<?php
/**
 * Reprendre les quatorze fiches de spectacle du dashboard. [17.08.2026]
 *
 *   php db/importer_fiches.php [--ecrire]
 *
 * Anna, la veille au soir: « extraia o maximo de informacao do dashboard do
 * script para (…) os dados completos dos projetos ». `lv-fiches` — 14 lignes,
 * 46 champs — n'avait jamais été repris, et c'est là que vit tout ce qui fait
 * un dossier: la distribution, la bio, le calendrier, les liens, la technique.
 *
 * CE QUI EST DÉJÀ ÉCRIT N'EST JAMAIS ÉCRASÉ. Chaque champ n'est rempli que
 * s'il est vide. Le CMS porte des textes retravaillés à la main, en français
 * et en anglais, et une reprise qui passe par-dessus efface un travail qu'on
 * ne sait pas refaire. La règle vaut aussi pour la fiche JSON.
 *
 * LE RAPPROCHEMENT SE FAIT PAR LE NOM, NORMALISÉ, ET IL EST MONTRÉ. Les
 * identifiants du dashboard ne mènent nulle part ici — c'est déjà ce qui avait
 * fait échouer le rapprochement des engagements, où 2 `empId` sur 72
 * correspondaient. Une fiche qu'on ne sait pas rattacher est DITE et laissée,
 * jamais rattachée au hasard: accrocher la distribution de Bestiarium au
 * mauvais spectacle est pire que ne pas la reprendre.
 *
 * LES QUATRE-VINGT-DIX POUR CENT DE CE FICHIER SONT UNE TABLE DE
 * CORRESPONDANCE, et c'est normal: 46 champs à ranger dans trois destinations
 * — la table `projects` du CMS, la colonne `projet_prod`, et le JSON de la
 * fiche. La logique, elle, tient en vingt lignes.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

$ecrire = in_array('--ecrire', $argv, true);
$src    = $argv[1] ?? null;
if ($src === '--ecrire') $src = $argv[2] ?? null;
$src  ??= getenv('HOME') . '/export.json';

if (!is_file($src)) { fwrite(STDERR, "Export introuvable: $src\n"); exit(1); }
$export = json_decode((string)file_get_contents($src), true);
if (!is_array($export)) { fwrite(STDERR, "Export illisible.\n"); exit(1); }

echo $ecrire ? "ÉCRITURE\n\n" : "SIMULATION — rien n'est écrit. --ecrire pour appliquer.\n\n";

$lignes = fn(string $t) => array_values(is_array($export[$t] ?? null) ? $export[$t] : []);

/** Normalise un titre pour le rapprochement: sans accents, sans ponctuation. */
function cle(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
                    'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c']);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? '';
    return trim(preg_replace('/\s+/', ' ', $s) ?? '');
}

/* ── Les rôles de la distribution ────────────────────────────────────────────
   Le dashboard les stocke en treize colonnes `d_*`. Ici ils deviennent des
   lignes d'équipe, dans l'ordre où une distribution se lit sur un dossier:
   la mise en scène d'abord, le jeu ensuite, les concepteurs après.

   ON NE DÉCOUPE PAS SUR LA VIRGULE. « Lukas Schneider (collaboration
   artistique, construction et scénographie) » en porte une, à l'intérieur
   d'une parenthèse, et le découpage donnerait deux personnes dont une nommée
   « construction et scénographie) ». Le champ reste entier: il s'imprime bien,
   et une distribution mal découpée part sur un dossier de subvention. */
const ROLES = [
    'd_mes'         => 'Mise en scène',
    'd_jeu'         => 'Jeu',
    'd_dramaturgie' => 'Dramaturgie',
    'd_regard'      => 'Regard extérieur',
    'd_coaching'    => 'Coaching',
    'd_sceno'       => 'Scénographie',
    'd_lumieres'    => 'Lumières',
    'd_musique'     => 'Musique',
    'd_video'       => 'Vidéo',
    'd_photo'       => 'Photographie',
];

/* Les champs techniques qui ont une case dans la fiche technique. Ils sont
   tous vides dans l'export d'aujourd'hui, et on les branche quand même: le
   jour où quelqu'un les saisit dans le dashboard, la reprise les trouve. */
const TECH = [
    't_hauteur'    => ['plateau', 'hauteur'],
    't_largeur'    => ['plateau', 'ouverture'],
    't_profondeur' => ['plateau', 'profondeur'],
    't_jauge'      => ['plateau', 'jauge'],
    't_montage'    => ['temps',   'montage'],
    't_demontage'  => ['temps',   'demontage'],
    't_transport'  => ['besoins', 'transport'],
    't_tournee'    => ['besoins', 'tourneeNb'],
];

/* ── L'index des spectacles du site ────────────────────────────────────── */

$index = [];
foreach (DB::all("SELECT id, title_en, title_fr FROM projects") as $p) {
    foreach ([$p['title_en'], $p['title_fr']] as $t) {
        if ($t !== null && trim((string)$t) !== '') $index[cle((string)$t)] ??= (int)$p['id'];
    }
}
/* Le titre du dashboard, aussi: `projet_prod.source_ref` porte l'identifiant
   `lv-prods` et c'est par lui qu'on retrouve le nom sous lequel Anna range. */
$nomProd = [];
foreach ($lignes('lv-prods') as $p) $nomProd[(string)($p['id'] ?? '')] = (string)($p['name'] ?? '');
foreach (DB::all("SELECT project_id, source_ref FROM projet_prod WHERE source_ref IS NOT NULL") as $r) {
    $n = $nomProd[(string)$r['source_ref']] ?? '';
    if ($n !== '') $index[cle($n)] ??= (int)$r['project_id'];
}

/* Ce que le nom seul ne rapproche pas. Relevé à la main, en lisant les deux
   listes côte à côte — c'est le seul moyen honnête. */
const CORRESPONDANCES = [
    'large ensemble feat joyce moreno' => 'louis matute large ensemble joyce moreno',
    'rectum crocodile'                 => 'rectum crocodile varia',
    'spooning entre les montagnes'     => 'spooning entre les montagnes le pommier',
    'audiobebedanse'                   => 'audiobebedanse',
    'dolce vita'                       => 'dolce vita new morning',
];

/* ── La reprise ─────────────────────────────────────────────────────────── */

$faits = $sansCible = 0;
$journal = [];

foreach ($lignes('lv-fiches') as $f) {
    $nom = trim((string)($f['nom'] ?? ''));
    if ($nom === '') continue;

    $k  = cle($nom);
    $k  = CORRESPONDANCES[$k] ?? $k;
    $pid = $index[$k] ?? null;

    if ($pid === null) {                       // essai plus large: préfixe
        foreach ($index as $ik => $iv) {
            if ($ik !== '' && (str_starts_with($ik, $k) || str_starts_with($k, $ik))) { $pid = $iv; break; }
        }
    }
    /* Une fiche sans spectacle devient un spectacle, CACHÉ. [17.08.2026]
       « CAPTAINS OF THE IMAGINATION — NEW ALBUM » est le seul cas sur les
       quatorze: un album de Watering Hole que le CMS ne connaît pas. Le
       laisser tomber perdrait sa bio, ses genres et son calendrier, et
       personne ne saurait qu'ils ont existé.

       IL NAÎT INVISIBLE ET EN BROUILLON, et ce n'est pas une précaution
       molle: `projects` est la table du SITE PUBLIC. Une ligne visible de
       plus, c'est une page qui s'ouvre sur le monde sans que personne l'ait
       relue. Le dashboard, lui, voit tout — donc la donnée est disponible là
       où on en a besoin, et nulle part ailleurs. */
    if ($pid === null) {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', cle($nom)) ?? '', '-') ?: 'sans-titre';
        printf("  + %-34s  AUCUN spectacle de ce nom — créé en brouillon, invisible\n",
               mb_substr($nom, 0, 34));
        $sansCible++;
        if (!$ecrire) continue;
        DB::insert('projects', [
            'title_en' => $nom, 'title_fr' => $nom,
            'slug_en'  => $slug, 'slug_fr' => $slug,
            'status'   => 'draft', 'visible' => 0,
            'catalog_visible' => 0, 'catalog_status' => 'draft',
            'media_slug' => $slug,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $pid = (int)DB::val("SELECT id FROM projects WHERE slug_en = ? ORDER BY id DESC LIMIT 1", [$slug]);
        if (!$pid) { echo "        ⚠ création échouée, fiche laissée\n"; continue; }
        printf("        → #%d\n", $pid);
    }

    $p = DB::one("SELECT * FROM projects WHERE id = ?", [$pid]);
    $d = ProdFiche::donnees($pid);
    $chg = [];                                  // ce qu'on écrit, pour le journal
    $colP = [];                                 // colonnes de `projects`

    /* — Le texte libre qui a sa place dans la fiche — */
    $pose = function (array $chemin, $val) use (&$d, &$chg) {
        $val = is_string($val) ? trim($val) : $val;
        if ($val === '' || $val === null || $val === []) return;
        $ref = &$d;
        foreach ($chemin as $seg) { $ref[$seg] ??= ''; $ref = &$ref[$seg]; }
        if ($ref !== '' && $ref !== [] && $ref !== null) return;   // déjà écrit
        $ref = $val;
        $chg[] = implode('.', $chemin);
    };

    $pose(['bio'],           $f['bio']          ?? '');
    $pose(['production'],    $f['d_production'] ?? '');
    $pose(['coproductions'], $f['d_coprod']     ?? '');
    $pose(['soutiens'],      $f['d_soutiens']   ?? '');
    $pose(['dossier','description'], $f['note'] ?? '');
    $pose(['diffusionDocs','dossier'], $f['dossier_link'] ?? '');
    $pose(['diffusionDocs','photos'],  $f['photos_link']  ?? '');
    $pose(['technique','adaptations'], $f['t_conditions']  ?? '');

    /* — Le calendrier: une liste de lignes datées, mise à plat en texte —
         Le dashboard la stocke en [{type, date}] où `date` est déjà une phrase
         entière (« 18.04.2026 — Künstler*innenbörse – Thun, CH »). La rendre
         en liste structurée demanderait de deviner où finit la date et où
         commence le lieu, sur quatorze formats différents. */
    if (is_array($f['calendrier'] ?? null) && $f['calendrier']) {
        $l = [];
        foreach ($f['calendrier'] as $c) {
            $t = trim((string)(is_array($c) ? ($c['date'] ?? '') : $c));
            if ($t !== '') $l[] = $t;
        }
        if ($l) $pose(['dossier','calendrier'], implode("\n", $l));
    }

    /* — Les lieux de tournée: eux SONT structurés à la source — */
    if (is_array($f['lieux_tournee'] ?? null) && $f['lieux_tournee'] && !($d['tournee'] ?? [])) {
        $t = [];
        foreach ($f['lieux_tournee'] as $x) {
            if (!is_array($x)) continue;
            $lieu = trim((string)($x['lieu'] ?? ''));
            if ($lieu !== '') $t[] = ['lieu' => $lieu, 'saison' => trim((string)($x['saison'] ?? ''))];
        }
        if ($t) { $d['tournee'] = $t; $chg[] = 'tournee(' . count($t) . ')'; }
    }

    /* — La distribution — */
    if (!($d['equipe'] ?? [])) {
        $eq = [];
        foreach (ROLES as $col => $lib) {
            $v = trim((string)($f[$col] ?? ''));
            if ($v !== '') $eq[] = ['fonction' => $lib, 'nom' => $v, 'prenom' => '', 'empId' => ''];
        }
        if ($eq) { $d['equipe'] = $eq; $chg[] = 'equipe(' . count($eq) . ')'; }
    }

    /* — La technique — */
    foreach (TECH as $col => [$grp, $champ]) {
        $pose(['technique', $grp, $champ], $f[$col] ?? '');
    }
    /* `t_artistes` et `t_scene` n'ont pas de case et n'en méritent pas une:
       ce sont des phrases (« Annina Mosimann (1 personne) »). Elles vont dans
       les notes techniques, où elles se lisent, plutôt que nulle part. */
    $notes = [];
    if (trim((string)($f['t_artistes'] ?? '')) !== '') $notes[] = 'Artistes: ' . trim((string)$f['t_artistes']);
    if (trim((string)($f['t_scene']    ?? '')) !== '') $notes[] = 'En scène: ' . trim((string)$f['t_scene']);
    if ($notes) $pose(['technique','notes'], implode("\n", $notes));

    /* La durée sert deux fois: en minutes pour le catalogue qui trie dessus,
       en toutes lettres sur la fiche technique où « 50 min » se lit mieux. */
    $pose(['technique','temps','duree'], $f['duree'] ?? '');

    /* — Les colonnes du CMS, uniquement si vides — */
    if (preg_match('/(\d+)/', (string)($f['duree'] ?? ''), $m) && !$p['duration_min'])
        $colP['duration_min'] = (int)$m[1];
    if (trim((string)($f['age'] ?? '')) !== '' && !$p['age_conseille'])
        $colP['age_conseille'] = trim((string)$f['age']);
    if (trim((string)($f['genres'] ?? '')) !== '' && !trim((string)$p['tags']))
        $colP['tags'] = trim((string)$f['genres']);
    if (trim((string)($f['photos_credits'] ?? '')) !== '' && !trim((string)$p['photo_credit']))
        $colP['photo_credit'] = trim((string)$f['photos_credits']);

    /* La distribution en toutes lettres, pour la page publique. Construite des
       mêmes `d_*`, mais seulement si le CMS n'en a pas déjà une: la sienne est
       relue et mise en forme, la nôtre est brute. */
    if (!trim((string)$p['distribution_fr'])) {
        $l = [];
        foreach (ROLES as $col => $lib) {
            $v = trim((string)($f[$col] ?? ''));
            if ($v !== '') $l[] = "$lib : $v";
        }
        if ($l) $colP['distribution_fr'] = implode("\n", $l);
    }

    /* — La ligne de production — */
    $pp   = DB::one("SELECT * FROM projet_prod WHERE project_id = ?", [$pid]) ?: [];
    $colPP = [];
    if (trim((string)($f['lieu_creation'] ?? '')) !== '' && !trim((string)($pp['lieu_creation'] ?? '')))
        $colPP['lieu_creation'] = trim((string)$f['lieu_creation']);
    if (trim((string)($f['devise'] ?? '')) !== '' && trim((string)($pp['devise'] ?? '')) === '')
        $colPP['devise'] = mb_substr(trim((string)$f['devise']), 0, 3);

    /* — Les vidéos: elles vivent dans leur table, pas dans le JSON — */
    $vids = [];
    foreach ((array)($f['videos'] ?? []) as $v) {
        if (!is_array($v)) continue;
        $url = trim((string)($v['url'] ?? ''));
        if ($url === '') continue;
        $deja = DB::val("SELECT COUNT(*) FROM videos WHERE owner_type='project' AND owner_id=? AND url=?",
                        [$pid, $url]);
        if (!$deja) $vids[] = ['url' => $url, 'titre' => trim((string)($v['titre'] ?? ''))];
    }

    if (!$chg && !$colP && !$colPP && !$vids) continue;

    printf("  · %-30s → #%-3d %s\n", mb_substr($nom, 0, 30), $pid, mb_substr((string)$p['title_en'], 0, 28));
    if ($colP)  printf("        projects    %s\n", implode(', ', array_keys($colP)));
    if ($colPP) printf("        projet_prod %s\n", implode(', ', array_keys($colPP)));
    if ($chg)   printf("        fiche       %s\n", implode(', ', $chg));
    if ($vids)  printf("        vidéos      %d\n", count($vids));
    $faits++;

    if ($ecrire) {
        if ($chg)   ProdFiche::ecrire($pid, $d);
        if ($colP)  DB::update('projects', $colP, 'id = ?', [$pid]);
        if ($colPP) DB::update('projet_prod', $colPP, 'project_id = ?', [$pid]);
        foreach ($vids as $i => $v) {
            preg_match('~vimeo\.com/(\d+)~', $v['url'], $mv);
            preg_match('~(?:youtu\.be/|v=)([\w-]{6,})~', $v['url'], $my);
            DB::insert('videos', [
                'owner_type' => 'project', 'owner_id' => $pid,
                'provider'   => $mv ? 'vimeo' : ($my ? 'youtube' : ''),
                'vid'        => $mv[1] ?? ($my[1] ?? ''),
                'url'        => $v['url'], 'title' => $v['titre'],
                'sort'       => 100 + $i,
            ]);
        }
    }
    $journal[] = $nom;
}

printf("\n  %d fiche(s) reprise(s)", $faits);
if ($sansCible) printf(" · %d sans spectacle correspondant", $sansCible);
echo "\n";
if (!$ecrire) echo "  Relance avec --ecrire pour appliquer.\n";

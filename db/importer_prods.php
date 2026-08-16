<?php
/**
 * Reprise de lv-prods vers la couche production des projets. [16.08.2026]
 *
 *   php db/importer_prods.php <fichier.json>
 *
 * LE RAPPROCHEMENT SE FAIT SUR LE NOM, et c'est le point délicat. lv-prods et
 * `projects` du CMS décrivent les mêmes spectacles sans partager d'identifiant:
 * l'un dit « Bestiarium », l'autre « BESTIARIUM » ou « Bestiarium (2026) ».
 *
 * On compare donc des noms normalisés: minuscules, sans accents, sans
 * ponctuation, espaces réduits. Ce qui ne s'apparie pas n'est PAS créé: c'est
 * listé, et quelqu'un regarde. Créer une ligne orpheline ferait exactement le
 * quatrième doublon que cette migration existe pour éviter.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$f = $argv[1] ?? '';
if (!is_file($f)) { fwrite(STDERR, "Usage: php db/importer_prods.php <fichier.json>\n"); exit(1); }

$pdo = DB::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/** Réduit un titre à ce qui permet de le comparer sans se tromper. */
function cle(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
                    'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c']);
    $s = preg_replace('/\(.*?\)/', '', $s);          // « (2026) » et autres précisions
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

// L'index des projets du CMS, par nom normalisé.
$index = [];
foreach ($pdo->query("SELECT id, title_fr, title_en FROM projects")->fetchAll() as $p) {
    foreach ([$p['title_fr'], $p['title_en']] as $t) {
        if ($t) $index[cle($t)] = (int)$p['id'];
    }
}

// L'index des organisations, pour rattacher le porteur juridique.
$orgs = [];
foreach ($pdo->query("SELECT id, nom FROM organisation WHERE supprime_le IS NULL")->fetchAll() as $o) {
    $orgs[cle($o['nom'])] = (int)$o['id'];
}

$PHASES = ['dev','creation','production','promo','tournee','cloture'];

$st = $pdo->prepare(
    'INSERT INTO projet_prod (project_id, phase, responsable, valide_par, budget, devise,
                              organisation_id, lieu_creation, raci, notes)
     VALUES (?,?,?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE phase=VALUES(phase), responsable=VALUES(responsable),
        valide_par=VALUES(valide_par), budget=VALUES(budget), devise=VALUES(devise),
        organisation_id=VALUES(organisation_id), lieu_creation=VALUES(lieu_creation),
        raci=VALUES(raci), notes=VALUES(notes)');

$n = 0;
$orphelins = [];

foreach (json_decode((string)file_get_contents($f), true) ?: [] as $p) {
    $nom = (string)($p['name'] ?? '');
    $pid = $index[cle($nom)] ?? null;
    if (!$pid) { $orphelins[] = $nom; continue; }

    $budget = (string)($p['budget'] ?? '');
    $budget = $budget === '' ? null : (float)str_replace([',', ' ', "'"], ['.', '', ''], $budget);
    $phase  = in_array($p['phase'] ?? '', $PHASES, true) ? $p['phase'] : 'dev';

    $st->execute([
        $pid, $phase,
        ($p['resp']  ?? '') ?: null,
        ($p['valid'] ?? '') ?: null,
        $budget, 'CHF',
        $orgs[cle((string)($p['artist'] ?? ''))] ?? null,
        ($p['lieu'] ?? '') ?: null,
        isset($p['raci']) && $p['raci'] !== '' ? json_encode($p['raci'], JSON_UNESCAPED_UNICODE) : null,
        ($p['notes'] ?? '') ?: null,
    ]);
    $n++;
}

printf("  %d projet(s) enrichi(s)\n", $n);
printf("  %d ligne(s) dans projet_prod\n", (int)$pdo->query('SELECT COUNT(*) FROM projet_prod')->fetchColumn());

if ($orphelins) {
    printf("\n  %d projet(s) de lv-prods SANS correspondance dans le CMS.\n", count($orphelins));
    echo   "  Rien n'a été créé pour eux: ce serait le quatrième doublon.\n";
    echo   "  Soit le titre diffère, soit le projet n'est pas publié sur le site.\n\n";
    foreach ($orphelins as $o) printf("    %s\n", $o);
}

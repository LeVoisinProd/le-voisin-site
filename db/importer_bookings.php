<?php
/**
 * Reprise des dates vers la table booking. [16.08.2026]
 *
 *   php db/importer_bookings.php events            depuis la table events du CMS
 *   php db/importer_bookings.php tour <fichier>    depuis lv-tour du dashboard
 *
 * DEUX SOURCES QUI DÉCRIVENT LA MÊME CHOSE et qui ne se sont jamais parlé:
 * `events` du CMS, 51 lignes, et `lv-tour` du dashboard, 35 lignes. La
 * synchronisation censée les relier pousse vers /admin/api/sync.php, un fichier
 * qui n'existe ni dans le dépôt ni sur le serveur, vérifié le 16.08.2026.
 *
 * L'appariement se fait sur (source, source_ref), donc rejouer une reprise met à
 * jour au lieu de dupliquer, et les deux sources cohabitent sans se marcher
 * dessus. LE RAPPROCHEMENT DES DOUBLONS ENTRE LES DEUX N'EST PAS FAIT ICI: une
 * même date peut exister des deux côtés avec des libellés différents, et
 * décider laquelle gagne demande de les regarder. Le script les charge, les
 * signale, et laisse le choix à un humain.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$mode = $argv[1] ?? '';
if (!in_array($mode, ['events', 'tour'], true)) {
    fwrite(STDERR, "Usage: php db/importer_bookings.php events|tour [fichier]\n");
    exit(1);
}

$pdo = DB::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$cols = ['source', 'source_ref', 'projet', 'artiste', 'venue', 'venue_url', 'ville', 'pays',
         'date_debut', 'date_fin', 'date_texte', 'statut'];
$maj  = array_map(fn($c) => "$c=VALUES($c)", array_slice($cols, 2));
$st   = $pdo->prepare('INSERT INTO booking (' . implode(',', $cols) . ') VALUES ('
      . implode(',', array_fill(0, count($cols), '?')) . ') ON DUPLICATE KEY UPDATE '
      . implode(',', $maj));

/**
 * « Genève, CH » et « Nürnberg, Allemagne » portent la ville et le pays dans une
 * seule chaîne. On coupe sur la dernière virgule: une ville peut en contenir une
 * (« La Chaux-de-Fonds, CH »), un pays jamais.
 */
function couper_lieu(string $brut): array
{
    $brut = trim($brut);
    $i = mb_strrpos($brut, ',');
    if ($i === false) return [$brut, null];
    $ville = trim(mb_substr($brut, 0, $i));
    $pays  = trim(mb_substr($brut, $i + 1));
    // Deux lettres, c'est un code pays. Plus, c'est probablement un nom.
    return [$ville, $pays === '' ? null : $pays];
}

$n = 0;

if ($mode === 'events') {
    $rows = $pdo->query(
        "SELECT e.id, e.date_text_fr, e.date_text_en, e.date_sort, e.date_end,
                e.venue, e.venue_url, e.city,
                p.title_fr AS projet_fr, p.title_en AS projet_en,
                a.name AS artiste
           FROM events e
           LEFT JOIN projects p ON p.id = e.project_id
           LEFT JOIN artists  a ON a.id = e.artist_id
          WHERE e.visible = 1")->fetchAll();

    foreach ($rows as $r) {
        [$ville, $pays] = couper_lieu((string)($r['city'] ?? ''));
        /* Le CMS ne porte aucun statut: une date publiée sur le site est une
           date qui a lieu. On la marque confirmée, ce qui est vrai et ce qui
           évite de créer une colonne « inconnu » que personne ne nettoiera. */
        $st->execute(['events', 'ev' . $r['id'],
            $r['projet_fr'] ?: $r['projet_en'], $r['artiste'],
            $r['venue'], $r['venue_url'], $ville, $pays,
            $r['date_sort'], $r['date_end'],
            $r['date_text_fr'] ?: $r['date_text_en'], 'confirmed']);
        $n++;
    }
}

if ($mode === 'tour') {
    $f = $argv[2] ?? '';
    if (!is_file($f)) { fwrite(STDERR, "Fichier introuvable: $f\n"); exit(1); }
    $dates = json_decode((string)file_get_contents($f), true) ?: [];
    foreach ($dates as $d) {
        [$ville, $pays] = couper_lieu((string)($d['city'] ?? ''));
        $st->execute(['tour', (string)($d['id'] ?? ''),
            $d['show'] ?? null, $d['artist'] ?? null,
            $d['venue'] ?? null, $d['url'] ?? null, $ville, $pays,
            ($d['start'] ?? '') ?: null, ($d['end'] ?? '') ?: null,
            $d['dateLabel'] ?? null, 'confirmed']);
        $n++;
    }
}

printf("  %d dates reprises depuis %s\n", $n, $mode);
printf("  %d bookings en base\n", (int)$pdo->query('SELECT COUNT(*) FROM booking')->fetchColumn());

/* Les doublons probables entre les deux sources: même date, même ville. On les
   montre sans les fusionner, parce que choisir laquelle gagne demande de lire
   les deux, et qu'une fusion automatique perdrait ce que l'autre portait. */
$dbl = $pdo->query(
    "SELECT date_debut, ville, COUNT(*) n, GROUP_CONCAT(CONCAT(source,':',venue) SEPARATOR ' | ') q
       FROM booking WHERE supprime_le IS NULL AND date_debut IS NOT NULL
      GROUP BY date_debut, ville HAVING COUNT(DISTINCT source) > 1")->fetchAll();
if ($dbl) {
    printf("\n  %d date(s) présentes dans LES DEUX sources, à rapprocher à la main:\n", count($dbl));
    foreach ($dbl as $d) printf("    %s  %-22s %s\n", $d['date_debut'], $d['ville'], $d['q']);
}

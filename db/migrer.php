<?php
/**
 * Le moteur de migrations. [16.08.2026]
 *
 * POURQUOI IL EXISTE. Le schéma de la base de ce site n'existait nulle part:
 * pas de fichier .sql, pas de dossier install/, rien dans l'historique git. Il
 * avait été supprimé après l'installation, comme le README le prescrivait. La
 * conséquence a été mesurée le 15.08.2026: on ne pouvait pas recréer la base à
 * partir de zéro, donc on ne pouvait pas monter d'environnement de test sans
 * copier la production, donc on travaillait directement sur le public.
 *
 * Ce fichier ferme cette porte. Chaque changement de schéma est un fichier
 * numéroté dans db/migrations/, appliqué une fois et une seule, et le tout est
 * suivi par git.
 *
 * CE QU'IL GARANTIT, et ce sont les trois propriétés qui comptent ici:
 *
 *   IDEMPOTENT   le relancer ne fait rien de plus. C'est indispensable sur un
 *                serveur où l'installateur écrit sans effacer et où le même
 *                paquet peut être posé deux fois.
 *   ORDONNÉ      par le numéro du fichier, jamais par la date du système de
 *                fichiers, qui ment après une copie.
 *   BAVARD       il dit ce qu'il fait et s'arrête à la première erreur, sans
 *                marquer comme appliquée une migration qui a échoué à mi-chemin.
 *
 * COMMENT ON S'EN SERT:
 *
 *   php db/migrer.php            applique ce qui manque
 *   php db/migrer.php --etat     dit où on en est, sans rien écrire
 *
 * En local, PATH sur php@8.4. Sur le serveur, /opt/php8.4/bin/php.
 *
 * IL N'Y A PAS DE MARCHE ARRIÈRE, et c'est voulu. Une migration qui se défait
 * donne l'illusion qu'on peut revenir en arrière, alors qu'une colonne
 * supprimée a déjà emporté ses données. Le retour en arrière ici, c'est la
 * sauvegarde: Infomaniak en garde sept jours.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

const DOSSIER = __DIR__ . '/migrations';

/** Le journal des migrations appliquées. Se crée lui-même au premier passage. */
function table_journal(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS schema_migration (
            fichier    VARCHAR(190) NOT NULL PRIMARY KEY,
            applique_a DATETIME     NOT NULL,
            duree_ms   INT UNSIGNED NOT NULL,
            empreinte  CHAR(64)     NOT NULL
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * Découpe un fichier .sql en instructions.
 *
 * Un simple explode(';') coupe au milieu d'une chaîne qui contient un
 * point-virgule, et il y en a dans les notes de contacts. On balaie donc en
 * tenant compte des guillemets et des commentaires.
 */
function instructions(string $sql): array
{
    $out = [];
    $courant = '';
    $q = null;
    $n = strlen($sql);
    for ($i = 0; $i < $n; $i++) {
        $c = $sql[$i];
        if ($q !== null) {
            $courant .= $c;
            if ($c === '\\') { $courant .= $sql[++$i] ?? ''; continue; }
            if ($c === $q) $q = null;
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') { $q = $c; $courant .= $c; continue; }
        if ($c === '-' && ($sql[$i + 1] ?? '') === '-') {
            while ($i < $n && $sql[$i] !== "\n") $i++;
            continue;
        }
        if ($c === ';') {
            if (trim($courant) !== '') $out[] = trim($courant);
            $courant = '';
            continue;
        }
        $courant .= $c;
    }
    if (trim($courant) !== '') $out[] = trim($courant);
    return $out;
}

function fichiers(): array
{
    $f = glob(DOSSIER . '/*.sql') ?: [];
    sort($f, SORT_STRING);   // par le numéro du nom, jamais par la date du disque
    return $f;
}

// ---------------------------------------------------------------------------

$pdo = DB::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
table_journal($pdo);

$faites = $pdo->query('SELECT fichier, empreinte FROM schema_migration')
              ->fetchAll(PDO::FETCH_KEY_PAIR);
$etatSeul = in_array('--etat', $argv, true);

printf("base    %s sur %s\n", cfg('db.name'), cfg('db.host'));
printf("moteur  %s\n\n", $pdo->query('SELECT VERSION()')->fetchColumn());

$aFaire = 0;
foreach (fichiers() as $chemin) {
    $nom = basename($chemin);
    $sql = file_get_contents($chemin);
    $emp = hash('sha256', $sql);

    if (isset($faites[$nom])) {
        /* Une migration déjà appliquée dont le contenu a changé est un piège:
           le disque et la base ne disent plus la même chose. On le signale au
           lieu de la rejouer, parce que la rejouer casserait plus. */
        $etat = $faites[$nom] === $emp ? 'déjà appliquée' : 'DÉJÀ APPLIQUÉE MAIS LE FICHIER A CHANGÉ';
        printf("  %-46s %s\n", $nom, $etat);
        continue;
    }

    $aFaire++;
    if ($etatSeul) { printf("  %-46s à appliquer\n", $nom); continue; }

    $t0 = microtime(true);
    try {
        foreach (instructions($sql) as $i) $pdo->exec($i);
    } catch (Throwable $e) {
        printf("  %-46s ÉCHEC\n\n  %s\n\n", $nom, $e->getMessage());
        printf("  Rien n'a été inscrit au journal pour ce fichier: la prochaine\n");
        printf("  exécution le retentera. Corrigez le .sql avant.\n");
        exit(1);
    }
    $ms = (int)round((microtime(true) - $t0) * 1000);
    $pdo->prepare('INSERT INTO schema_migration (fichier, applique_a, duree_ms, empreinte)
                   VALUES (?, NOW(), ?, ?)')->execute([$nom, $ms, $emp]);
    printf("  %-46s appliquée en %d ms\n", $nom, $ms);
}

echo "\n";
if ($aFaire === 0)      echo "Rien à faire, la base est à jour.\n";
elseif ($etatSeul)      printf("%d migration(s) en attente. Relancez sans --etat pour appliquer.\n", $aFaire);
else                    printf("%d migration(s) appliquée(s).\n", $aFaire);

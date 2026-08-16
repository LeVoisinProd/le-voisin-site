<?php
/**
 * L'équipe interne, que la première reprise avait laissée dehors. [17.08.2026]
 *
 *   php db/importer_personnel.php [--ecrire]
 *
 * Anna: « extraia o maximo de informacao do dashboard do script para, os dados
 * dos employes ». En comparant les tables une à une, `lv-personnel` en porte
 * 93 quand `lv-rh-employees`, celle qui avait servi, en porte 89.
 *
 * LES QUATRE MANQUANTES SONT LE BUREAU: Anna Ladeira, Mirta Gariboldi,
 * Alessandra Moura, Félicia Jeanneret. Elles étaient absentes parce qu'elles
 * ne sont pas engagées sur un spectacle — elles font tourner la maison — et
 * `lv-rh-employees` ne liste que les engagé·e·s. Résultat: les quatre
 * personnes qui utilisent le dashboard n'existaient pas dans la base du
 * dashboard, et aucun écran ne pouvait proposer « responsable: Mirta ».
 *
 * ELLES N'ONT PAS DE FICHE DE PAIE ICI, et c'est normal: ni AVS, ni IBAN, ni
 * taux horaire. Ce fichier ne leur en invente pas. Il écrit ce que le
 * dashboard porte — nom, courriel, téléphone, rôle, couleur — et laisse le
 * reste vide, à remplir le jour où on tiendra leurs salaires ici.
 *
 * POUR LES 89 AUTRES, il ne fait qu'une chose: compléter `role_interne` et
 * `couleur` si le dashboard les portait. Aucune valeur existante n'est
 * écrasée.
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

$gens = array_values(is_array($export['lv-personnel'] ?? null) ? $export['lv-personnel'] : []);
if (!$gens) { echo "  lv-personnel vide.\n"; exit; }

/* Le Voisin CH porte le bureau. Si on ne le trouve pas, on ne devine pas: une
   personne rattachée à la mauvaise association apparaît dans les décomptes de
   quelqu'un d'autre. */
$voisin = DB::val("SELECT id FROM organisation
                    WHERE supprime_le IS NULL AND (nom LIKE 'Le Voisin%' OR nom_legal LIKE 'Le Voisin%')
                    ORDER BY (nom LIKE '%CH%') DESC, id LIMIT 1");

$deja = [];
foreach (DB::all("SELECT id, source_ref, prenom, nom, role_interne, couleur FROM rh_employe WHERE supprime_le IS NULL") as $e) {
    $deja[(string)$e['source_ref']] = $e;
    $deja[mb_strtolower(trim($e['prenom'] . ' ' . $e['nom']))] = $e;
}

$creees = $completees = 0;

foreach ($gens as $g) {
    $ref = (string)($g['id'] ?? '');
    /* `lv-personnel` mélange deux formats de nom: `prenom`/`nom` pour les
       engagé·e·s, `fn`/`ln` pour le bureau. Les deux disent la même chose. */
    $prenom = trim((string)($g['prenom'] ?? $g['fn'] ?? ''));
    $nom    = trim((string)($g['nom']    ?? $g['ln'] ?? ''));
    if ($prenom === '' && $nom === '') continue;

    $role    = trim((string)($g['role'] ?? ''));
    $couleur = trim((string)($g['color'] ?? ''));
    $e       = $deja[$ref] ?? $deja[mb_strtolower("$prenom $nom")] ?? null;

    if ($e) {
        $maj = [];
        if ($role    !== '' && trim((string)$e['role_interne']) === '') $maj['role_interne'] = $role;
        if ($couleur !== '' && trim((string)$e['couleur'])      === '') $maj['couleur']      = mb_substr($couleur, 0, 7);
        if (!$maj) continue;
        printf("  · %-26s complété: %s\n", mb_substr("$prenom $nom", 0, 26), implode(', ', array_keys($maj)));
        if ($ecrire) DB::update('rh_employe', $maj, 'id = ?', [(int)$e['id']]);
        $completees++;
        continue;
    }

    /* Une personne à créer. */
    $l = [
        'source_ref'      => $ref,
        'prenom'          => $prenom,
        'nom'             => $nom,
        'email'           => trim((string)($g['email'] ?? '')) ?: null,
        'telephone'       => trim((string)($g['phone'] ?? $g['tel'] ?? '')) ?: null,
        'fonction'        => trim((string)($g['fonction'] ?? '')) ?: null,
        'role_interne'    => $role ?: null,
        'couleur'         => $couleur ? mb_substr($couleur, 0, 7) : null,
        'type_engagement' => trim((string)($g['type'] ?? 'interne')),
        'organisation_id' => $voisin ? (int)$voisin : null,
        'asso_ref'        => trim((string)($g['asso'] ?? '')) ?: null,
        'devise'          => 'CHF',
        'actif'           => 1,
    ];
    printf("  + %-26s %-24s %s\n", mb_substr("$prenom $nom", 0, 26),
           mb_substr($role, 0, 24), (string)$l['email']);
    if (!$voisin) echo "        ⚠ aucune association « Le Voisin » trouvée — créée sans rattachement\n";
    $creees++;
    if ($ecrire) DB::insert('rh_employe', array_filter($l, fn($v) => $v !== null));
}

printf("\n  %d créée(s) · %d complétée(s)\n", $creees, $completees);
if (!$ecrire) echo "  Relance avec --ecrire pour appliquer.\n";

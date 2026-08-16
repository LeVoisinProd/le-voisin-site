<?php
/**
 * Chiffrement des fiches personnelles restées en clair. [16.08.2026]
 *
 * POURQUOI IL EXISTE. Crypto.php chiffre `data` et `prefill` de
 * member_profiles, et il le fait bien — mais seulement au moment où la fiche
 * est enregistrée. Son en-tête l'annonce: « migration silencieuse », une valeur
 * écrite avant le chiffrement reste en clair et « redevient illisible dès le
 * prochain enregistrement de la fiche ».
 *
 * Le mot qui coûte cher est « prochain ». Une collaboratrice qui n'ouvre plus
 * son espace n'enregistre plus rien, donc sa fiche ne se chiffre jamais.
 * Mesuré le 16.08.2026 sur le dump du jour: 15 valeurs chiffrées, 24 numéros
 * AVS et 32 IBAN en texte clair. La migration ne se termine pas toute seule,
 * et chaque sauvegarde de la base emporte ces numéros lisibles.
 *
 * Ce script termine la migration sans passer par MemberProfile::save(), qui
 * demanderait de reconstruire la fiche entière et toucherait `bio` et
 * `photo_image_id`. Il travaille colonne par colonne: si la valeur ne commence
 * pas par « sb1: », il la chiffre et la réécrit. Rien d'autre ne bouge.
 *
 * COMMENT ON S'EN SERT:
 *
 *   php db/chiffrer_fiches.php               dit ce qu'il ferait, n'écrit rien
 *   php db/chiffrer_fiches.php --appliquer   écrit
 *
 * En local, PATH sur php@8.4. Sur le serveur, /opt/php8.4/bin/php.
 *
 * POURQUOI LE DÉFAUT N'EST PAS D'APPLIQUER, contrairement à migrer.php. Une
 * migration de schéma ratée se voit tout de suite. Ici, chiffrer avec une
 * mauvaise clé ne casse rien sur le moment: dechiffrer() renvoie '' quand
 * l'ouverture échoue, « mieux vaut une fiche vide qu'un message d'erreur ».
 * L'erreur serait donc silencieuse et découverte des semaines plus tard, sur
 * des fiches devenues vides. Un tel geste se demande explicitement.
 *
 * LES TROIS GARDES, dans l'ordre où elles se déclenchent:
 *
 *   1. LA CLÉ OUVRE-T-ELLE CE QUI EST DÉJÀ CHIFFRÉ. S'il existe des valeurs en
 *      « sb1: », on en ouvre une avant tout le reste. Si elle ne s'ouvre pas,
 *      le secret de config.php n'est pas celui qui a chiffré la base, et on
 *      s'arrête net: chiffrer davantage avec cette clé rendrait la table
 *      illisible pour de bon.
 *   2. ALLER-RETOUR SUR CHAQUE VALEUR. On chiffre, on rouvre, on compare à
 *      l'original. On n'écrit que si c'est identique, octet pour octet.
 *   3. updated_at EST PRÉSERVÉ. La colonne porte ON UPDATE current_timestamp,
 *      donc une écriture nue ferait croire que les 42 personnes ont mis leur
 *      fiche à jour aujourd'hui. On la réécrit à sa propre valeur.
 *
 * CE QU'IL NE TOUCHE PAS, ET IL FAUT LE DIRE. La table `submissions` contient
 * 12 IBAN en clair (mesuré le 16.08.2026) et n'est chiffrée nulle part: aucun
 * appel à Crypto:: ne la concerne, ni en écriture ni en lecture. La chiffrer
 * ici casserait toutes les lectures, puisque rien ne la déchiffre. Elle demande
 * d'abord une modification du code. C'est un chantier séparé, pas un oubli.
 *
 * IL N'Y A PAS DE MARCHE ARRIÈRE. Le retour, c'est la sauvegarde: le dump du
 * jour est dans le Drive, et Infomaniak en garde sept.
 */
declare(strict_types=1);

/**
 * EN LIGNE DE COMMANDE, ET NULLE PART AILLEURS.
 *
 * db/ n'est pas protégé sur le serveur: mesuré le 16.08.2026, il n'y a pas de
 * .htaccess dans ce dossier, et la règle du CMS ne renvoie vers index.php que
 * ce qui n'existe pas sur le disque (RewriteCond !-f). Un fichier .php déposé
 * ici est donc exécuté par Apache pour qui le demande.
 *
 * Sans cette garde, n'importe quel visiteur pourrait appeler ce script et lire
 * la liste des fiches. Le 404 est volontaire: il ne dit même pas que le
 * fichier existe.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

const PREFIXE  = 'sb1:';
const COLONNES = ['data', 'prefill'];

$appliquer = in_array('--appliquer', $argv, true);

echo $appliquer
    ? "Chiffrement des fiches — ÉCRITURE\n\n"
    : "Chiffrement des fiches — état seulement, rien ne sera écrit.\n"
      . "Pour écrire: php db/chiffrer_fiches.php --appliquer\n\n";

/* ── Le secret existe-t-il ────────────────────────────────────────────── */
if ((string)cfg('secret', '') === '') {
    fwrite(STDERR, "ARRÊT: config.secret est vide. Sans lui, rien à faire ici.\n");
    exit(1);
}

/* ── Garde 1: la clé ouvre-t-elle ce qui est déjà chiffré ─────────────── */
$temoin = null;
foreach (COLONNES as $col) {
    $v = DB::val("SELECT `$col` FROM member_profiles WHERE `$col` LIKE ? LIMIT 1", [PREFIXE . '%']);
    if ($v !== null && $v !== false) { $temoin = (string)$v; break; }
}
if ($temoin !== null) {
    if (Crypto::dechiffrer($temoin) === '') {
        fwrite(STDERR,
            "ARRÊT: il existe des fiches chiffrées que la clé actuelle n'ouvre pas.\n" .
            "Le secret de config.php n'est pas celui qui a chiffré cette base.\n" .
            "Chiffrer davantage maintenant rendrait la table illisible. On ne touche à rien.\n");
        exit(2);
    }
    echo "Garde 1 — la clé ouvre bien les fiches déjà chiffrées.\n";
} else {
    echo "Garde 1 — aucune fiche chiffrée pour l'instant, rien à vérifier.\n";
}

/* ── Recensement ──────────────────────────────────────────────────────── */
$lignes = DB::all(
    'SELECT collaborator_id, `data`, `prefill`, updated_at FROM member_profiles ORDER BY collaborator_id'
);
echo 'Fiches: ' . count($lignes) . "\n\n";

$aFaire = [];
foreach ($lignes as $l) {
    foreach (COLONNES as $col) {
        $v = $l[$col];
        if ($v === null || $v === '') continue;            // vide: rien à chiffrer
        if (str_starts_with((string)$v, PREFIXE)) continue; // déjà fait
        $aFaire[] = ['id' => (int)$l['collaborator_id'], 'col' => $col,
                     'clair' => (string)$v, 'updated_at' => $l['updated_at']];
    }
}

$dejaFait = 0;
foreach ($lignes as $l) foreach (COLONNES as $col) {
    if ($l[$col] !== null && str_starts_with((string)$l[$col], PREFIXE)) $dejaFait++;
}

echo "Déjà chiffrées : $dejaFait valeur(s)\n";
echo 'En clair       : ' . count($aFaire) . " valeur(s)\n\n";

if (!$aFaire) { echo "Rien à faire.\n"; exit(0); }

foreach ($aFaire as $t) {
    printf("  fiche %-6d %-8s %d octets\n", $t['id'], $t['col'], strlen($t['clair']));
}
echo "\n";

if (!$appliquer) {
    echo "Rien n'a été écrit. Relancer avec --appliquer pour le faire.\n";
    exit(0);
}

/* ── Écriture, avec l'aller-retour sur chaque valeur ──────────────────── */
$pdo = DB::pdo();
$pdo->beginTransaction();
$ok = 0;
try {
    foreach ($aFaire as $t) {
        $chiffre = Crypto::chiffrer($t['clair']);

        // Garde 2: on rouvre avant d'écrire. Si l'aller-retour ne rend pas
        // exactement l'original, on abandonne tout — pas seulement cette ligne.
        if (Crypto::dechiffrer($chiffre) !== $t['clair']) {
            throw new RuntimeException(
                "aller-retour raté sur la fiche {$t['id']}, colonne {$t['col']}. Rien n'est écrit.");
        }

        // Garde 3: updated_at réécrit à sa propre valeur, sinon ON UPDATE
        // current_timestamp ferait croire à une mise à jour d'aujourd'hui.
        $st = $pdo->prepare(
            "UPDATE member_profiles SET `{$t['col']}` = ?, updated_at = ? WHERE collaborator_id = ?");
        $st->execute([$chiffre, $t['updated_at'], $t['id']]);
        $ok++;
        printf("  chiffré  fiche %-6d %s\n", $t['id'], $t['col']);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "\nARRÊT: " . $e->getMessage() . "\nLa transaction a été annulée.\n");
    exit(3);
}

echo "\n$ok valeur(s) chiffrée(s).\n";

/* ── Vérification après coup ──────────────────────────────────────────── */
$reste = 0;
foreach (DB::all('SELECT `data`, `prefill` FROM member_profiles') as $l) {
    foreach (COLONNES as $col) {
        if ($l[$col] !== null && $l[$col] !== '' && !str_starts_with((string)$l[$col], PREFIXE)) $reste++;
    }
}
echo $reste === 0
    ? "Vérifié: plus aucune valeur en clair dans member_profiles.\n"
    : "ATTENTION: il reste $reste valeur(s) en clair.\n";

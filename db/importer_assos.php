<?php
/**
 * Reprise des associations depuis l'export du dashboard. [16.08.2026]
 *
 *   php db/importer_assos.php <export.json>
 *
 * Lit la clef `lv-assoc-fiches`: un objet dont la CLEF est l'identifiant de
 * l'association et la valeur sa fiche. D'où la reprise par `source_ref` — ce
 * n'est pas un champ de la fiche, c'est la clef du dictionnaire, et l'oublier
 * ferait un doublon par passage.
 *
 * LES DEUX MOTS DE PASSE SONT CHIFFRÉS À L'ENTRÉE. Ils arrivent en clair de
 * l'export — 11 fiches portent un mot de passe e-mail, 1 un mot de passe
 * Instagram — et repartent chiffrés par Crypto.php, comme tout ce qui touche
 * aux secrets dans cette base depuis le 16.08.2026. Un import qui les recopie
 * en clair annulerait la décision de la veille sans que personne ne le voie.
 *
 * IDEMPOTENT. Rejouer met à jour au lieu de dupliquer, y compris pour les mots
 * de passe: chiffrer deux fois la même valeur donne deux textes différents —
 * le nonce change — mais la valeur déchiffrée reste la même, et c'est elle qui
 * compte.
 *
 * CE QU'IL NE FAIT PAS: deviner le nom. Le dashboard n'en porte pas dans la
 * fiche, il est dans la clef. On reprend donc la clef comme nom, et l'écran
 * permet de le corriger — mieux vaut « p-levoisin-ch » visible et faux qu'une
 * fiche sans nom qu'on ne retrouve pas.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$source = $argv[1] ?? '';
if ($source === '' || !is_file($source)) {
    fwrite(STDERR, "Usage: php db/importer_assos.php <export.json>\n");
    exit(1);
}
$j = json_decode((string)file_get_contents($source), true);
$fiches = $j['lv-assoc-fiches'] ?? null;
if (!is_array($fiches)) {
    fwrite(STDERR, "Ce fichier ne porte pas de clef `lv-assoc-fiches`.\n");
    exit(1);
}

/* La correspondance entre les noms du dashboard et les colonnes d'ici. Écrite
   à plat plutôt que devinée: deux noms se ressemblent sans dire la même chose
   — `avs_asso` est le numéro d'employeur, `reg` le numéro du registre. */
const MAP = [
    'type'           => 'forme_juridique',
    'date_creation'  => 'date_creation',
    'ide'            => 'ide',
    'reg'            => 'registre',
    'avs_asso'       => 'avs_employeur',
    'ree'            => 'ree',
    'adresse'        => 'adresse',
    'ville'          => 'ville',
    'pays'           => 'pays',
    'email'          => 'email',
    'tel'            => 'telephone',
    'site'           => 'site',
    'instagram'      => 'instagram',
    'da_contact'     => 'direction',
    'banque_nom'     => 'banque_nom',
    'banque_iban'    => 'banque_iban',
    'banque_bic'     => 'banque_bic',
    'fisc_ch_canton' => 'canton_fiscal',
];

$pdo = DB::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$n = $mdp = 0;
foreach ($fiches as $ref => $f) {
    if (!is_array($f)) continue;
    $ref = (string)$ref;

    $vals = ['source' => 'dashboard', 'source_ref' => $ref, 'genre' => 'association'];
    foreach (MAP as $de => $vers) {
        $v = trim((string)($f[$de] ?? ''));
        if ($v !== '') $vals[$vers] = mb_substr($v, 0, 190);
    }

    /* L'adresse du dashboard tient parfois trois lignes dans un seul champ.
       On la garde entière — la découper à la virgule casserait « Case postale
       1211 Genève 1 » en deux moitiés fausses. */
    if (isset($f['adresse'])) $vals['adresse'] = mb_substr(trim((string)$f['adresse']), 0, 190);

    foreach (['email_mdp' => 'email_mdp', 'instagram_mdp' => 'instagram_mdp'] as $de => $vers) {
        $v = trim((string)($f[$de] ?? ''));
        if ($v !== '') { $vals[$vers] = Crypto::chiffrer($v); $mdp++; }
    }

    $exist = DB::one('SELECT id FROM organisation WHERE source = ? AND source_ref = ?',
                     ['dashboard', $ref]);
    if ($exist) {
        $set = implode(',', array_map(fn($c) => "$c=?", array_keys($vals)));
        $pdo->prepare("UPDATE organisation SET $set WHERE id = ?")
            ->execute([...array_values($vals), (int)$exist['id']]);
    } else {
        /* Le nom vient de la clef: la fiche n'en porte pas. Mieux vaut une
           étiquette visible et à corriger qu'une fiche sans nom. */
        $vals['nom'] = mb_substr($ref, 0, 190);
        $pdo->prepare('INSERT INTO organisation (' . implode(',', array_keys($vals)) . ') VALUES ('
            . implode(',', array_fill(0, count($vals), '?')) . ')')
            ->execute(array_values($vals));
    }
    $n++;
}

printf("%d associations reprises, %d mot(s) de passe chiffré(s)\n", $n, $mdp);
printf("%d en base au total\n",
    (int)DB::val('SELECT COUNT(*) FROM organisation WHERE supprime_le IS NULL'));
echo "\n  Les noms viennent des clefs du dashboard: à corriger dans l'écran.\n";

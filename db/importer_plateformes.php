<?php
/**
 * Les plateformes professionnelles de panch.li dans le carnet. [17.08.2026]
 *
 *   php db/importer_plateformes.php <plateformes.json> [--ecrire]
 *
 * Anna: « inclure dans contacts toutes les infos de ces plateformes
 * professionelles: https://panch.li/platforms/ ». 115 entrées — festivals,
 * lieux, réseaux, écoles, archives — dont 64 en Suisse.
 *
 * IL N'EN CRÉE PAS 115, ET C'EST TOUT L'ENJEU. Mesuré avant d'écrire une
 * ligne: Arsenic porte déjà 8 fiches dans le carnet, La Bâtie 8, LADA 10, BONE
 * 6, Le Grütli 6, Belluard 5. Ce sont des PERSONNES chez ces structures, et un
 * import naïf aurait posé une 9e ligne « Arsenic » à côté des huit
 * programmateurs d'Arsenic. Sur 8432 contacts construits en des années, ce
 * genre de doublon ne se répare plus: on ne sait plus lequel est le bon.
 *
 * DONC DEUX GESTES ET NON UN:
 *
 *   la structure est déjà connue  →  on ne crée rien. On pose l'étiquette sur
 *                                    les fiches existantes, et on complète le
 *                                    site et la description SI ELLES SONT
 *                                    VIDES. Rien n'est écrasé.
 *   la structure est inconnue     →  une fiche de structure est créée.
 *
 * L'ÉTIQUETTE EST CE QUI REND LA CHOSE UTILE. Anna, le 16.08: « ataca as tags,
 * é uma funcao muito importante para eu poder fazer pesquisa ». Une plateforme
 * noyée dans 8432 fiches sans étiquette n'est pas retrouvable; avec
 * « plateforme panch » elle l'est en un filtre.
 *
 * LES QUINZE FERMÉES SONT REPRISES QUAND MÊME, étiquetées « fermé » et avec
 * l'année dans les notes. Une plateforme morte reste une piste — elle dit qui
 * programmait ce genre de travail, et les gens qui la faisaient font autre
 * chose aujourd'hui. Ce qu'il ne faut pas, c'est la démarcher: d'où l'étiquette,
 * qui permet de l'exclure d'un envoi.
 *
 * LA CATÉGORIE EST DÉDUITE DE LA DESCRIPTION et le fichier dit comment. Ce
 * n'est pas exact à cent pour cent — un « espace » peut programmer comme un
 * festival — mais les deux valeurs possibles existent déjà dans le carnet et
 * aucune n'est fausse au point de gêner une recherche.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

$fichier = '';
foreach (array_slice($argv, 1) as $a) if ($a !== '--ecrire') { $fichier = $a; break; }
$ecrire = in_array('--ecrire', $argv, true);

if ($fichier === '' || !is_file($fichier)) {
    fwrite(STDERR, "Usage: php db/importer_plateformes.php <plateformes.json> [--ecrire]\n");
    exit(1);
}
$lignes = json_decode((string)file_get_contents($fichier), true);
if (!is_array($lignes)) { fwrite(STDERR, "JSON illisible.\n"); exit(1); }

echo $ecrire ? "ÉCRITURE\n\n" : "SIMULATION — rien n'est écrit. --ecrire pour appliquer.\n\n";

const TAG = 'plateforme panch';

/** Le nom d'une structure, réduit à ce qui l'identifie. */
function cle(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i',
                    'ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ø'=>'o','å'=>'a']);
    /* Ce qui suit un tiret cadratin est une précision de lieu — « HKB —
       Hochschule der Künste Bern » — et le carnet n'écrit que le sigle. */
    $s = (string)preg_replace('/\s+[—–-]\s+.*$/u', '', $s);
    $s = (string)preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim((string)preg_replace('/\s+/', ' ', $s));
}

/** Diffuseur ou partenaire, selon ce que la description décrit. */
function categorie(string $desc): string {
    $d = mb_strtolower($desc);
    foreach (['reseau','réseau','association','agence','plateforme','plataform','archive',
              'umbrella','organisation','prix','award','ecole','école','school','academy',
              'schule','haute ecole','haute école','university','formation','institut',
              'recherche','publications'] as $mot) {
        if (str_contains($d, $mot)) return 'Partenaire institutionnel';
    }
    return 'Diffuseur/euse';
}

/* L'index du carnet: on cherche la structure ET le nom, parce qu'une fiche de
   lieu porte parfois le lieu dans les deux — 3373 fiches sur 8432 le font. */
$index = [];
foreach (DB::all("SELECT id, nom, structure, site, description, mots_cles
                    FROM contact WHERE supprime_le IS NULL") as $c) {
    foreach ([$c['structure'], $c['nom']] as $n) {
        $k = cle((string)$n);
        if ($k !== '') $index[$k][] = $c;
    }
}

/* ── LA RÉFÉRENCE, QUI N'EST PAS FACULTATIVE ──────────────────── [17.08.2026]
   `contact.ref` porte une clef UNIQUE, `u_ref`, et son défaut est la chaîne
   vide: deux fiches créées sans référence entrent en collision, et la seconde
   fait tomber tout le script. C'est exactement ce qui est arrivé — une seule
   plateforme créée sur cent, sans un mot, parce que l'erreur PDO n'allait ni
   dans stderr ni dans le journal du site.

   Les 8432 fiches du carnet en portent une, de la forme `c001` … `c8432`. On
   reprend la suite plutôt que d'inventer un préfixe: une référence qui ne
   ressemble pas aux autres se remarque comme une anomalie et finit par être
   « corrigée » par quelqu'un. */
$suite = (int)DB::val("SELECT COALESCE(MAX(CAST(SUBSTRING(ref, 2) AS UNSIGNED)), 0)
                         FROM contact WHERE ref REGEXP '^c[0-9]+$'");
$refSuivante = static function () use (&$suite): string {
    return sprintf('c%03d', ++$suite);
};

$crees = $enrichis = $touchees = $inchanges = $proches = 0;

foreach ($lignes as $x) {
    $nom  = trim((string)($x['nom'] ?? ''));
    if ($nom === '') continue;
    $url  = trim((string)($x['url'] ?? ''));
    if ($url !== '' && !preg_match('~^https?://~', $url)) $url = 'https://' . $url;
    $desc = trim((string)($x['description'] ?? ''));
    $pays = trim((string)($x['pays'] ?? ''));
    $reg  = trim((string)($x['region'] ?? ''));
    $ferme = trim((string)($x['ferme'] ?? ''));

    $tags = ['performance', TAG];
    if ($ferme !== '') $tags[] = 'fermé';
    $tagTexte = implode(', ', $tags);

    $deja = $index[cle($nom)] ?? [];
    $par  = 'nom exact';

    /* DEUXIÈME PASSAGE, PARCE QUE LE CARNET N'ÉCRIT PAS DEUX FOIS PAREIL.
       Mesuré avant de l'ajouter: « Belluard » y figure sous QUATRE graphies —
       « Belluard », « BELLUARD BOLLWERK INTERNATIONAL », « Belluard Bollwerk,
       Fribourg », « Festival Belluard Bollwerk » — et panch écrit encore
       autrement. Le nom exact n'en trouvait aucune, et l'on aurait posé une
       cinquième graphie sur un contact déjà éclaté en quatre.

       LA RÈGLE: tous les mots DISTINCTIFS du nom doivent se retrouver, comme
       mots entiers, dans la fiche du carnet. « festival », « international »,
       « performance » et consorts n'en sont pas — ils apparaissent dans un
       tiers du carnet et feraient tout correspondre à tout.

       ET LE RAPPROCHEMENT EST IMPRIMÉ, toujours. Un appariement flou qui se
       tait est pire qu'un doublon: le doublon se voit, l'appariement muet a
       fusionné deux choses différentes et personne ne le saura. */
    $voisin = '';   // un nom proche, trouvé mais jugé insuffisant

    if (!$deja) {
        $stop = ['festival','international','performance','art','arts','the','de','des','du',
                 'la','le','les','centre','center','space','project','platform','plateforme',
                 'schweizer','swiss','and','für','fur','von','zur','act','open','days'];
        /* `array_unique` AVANT DE COMPTER: « perform perform » n'apporte qu'un
           mot distinctif, pas deux, et sans cela il passait pour un nom riche. */
        $mots = array_values(array_unique(array_filter(explode(' ', cle($nom)),
            fn($m) => mb_strlen($m) >= 4 && !in_array($m, $stop, true))));

        foreach ($index as $k => $fiches) {
            /* `(string)` ET NON `$k` NU: PHP convertit en ENTIER toute clef de
               tableau qui ressemble à un nombre, et le carnet en porte — une
               fiche nommée « 2024 » suffit. Sous `strict_types`, `explode()`
               sur un entier est une erreur fatale, et elle arrivait à la
               deuxième plateforme sur cent quinze. */
            $kt = explode(' ', (string)$k);
            $tous = $mots !== [];
            foreach ($mots as $m) if (!in_array($m, $kt, true)) { $tous = false; break; }
            if (!$tous) continue;

            /* UN SEUL MOT COMMUN NE SUFFIT PAS, ET LA SIMULATION L'A PROUVÉ.
               Sur vingt-quatre rapprochements flous, huit étaient faux et tous
               par le même mécanisme: le mot unique était un NOM DE VILLE ou un
               mot passe-partout. « HEAD — Genève » tombait sur « performing
               arts unit head at Abu Dhabi », « Performance Art Bergen » sur
               « Bergen International Festival », « ZAZ Festival Israel » sur
               « arma theatre et festivals de rue en israel ».

               Deux mots distinctifs communs, ou un seul mais long — huit
               lettres — parce qu'un mot long est un nom propre inventé
               (« Stromereien ») et non une ville. Une liste de villes à
               exclure ne tiendrait pas: il y en a autant que de contacts. */
            if (count($mots) < 2 && mb_strlen($mots[0] ?? '') < 8) {
                if ($voisin === '') $voisin = (string)$k;
                continue;
            }
            $deja = $fiches;
            $par  = 'mots « ' . implode(' ', $mots) . ' » → « ' . $k . ' »';
            break;
        }
    }

    if ($deja) {
        /* Connue: on complète, on n'écrase pas, et on ne crée surtout pas. */
        $n = 0;
        foreach ($deja as $c) {
            $maj = [];
            $mc = trim((string)$c['mots_cles']);
            if (!str_contains(mb_strtolower($mc), TAG))
                $maj['mots_cles'] = $mc === '' ? $tagTexte : $mc . ', ' . $tagTexte;
            if ($url !== '' && trim((string)$c['site']) === '')        $maj['site'] = $url;
            if ($desc !== '' && trim((string)$c['description']) === '') $maj['description'] = $desc;
            if (!$maj) continue;
            $n++;
            if ($ecrire) DB::update('contact', $maj, 'id = ?', [(int)$c['id']]);
        }
        if ($n) { printf("  ~ %-40s %d fiche(s) · rapproché par %s\n", mb_substr($nom, 0, 40), $n, $par);
                  $enrichis++; $touchees += $n; }
        else    { printf("  = %-40s déjà complète · rapproché par %s\n", mb_substr($nom, 0, 40), $par);
                  $inchanges++; }
        continue;
    }

    /* Inconnue: une fiche de structure. `nom` et `structure` portent tous deux
       le nom, comme les 3373 fiches de lieu déjà dans le carnet: c'est la forme
       qu'il a, et s'en écarter rendrait la nouvelle invisible aux recherches
       qui marchent aujourd'hui. */
    $l = [
        'nom'          => $nom,
        'structure'    => $nom,
        'categorie'    => categorie($desc),
        'pays_struct'  => $pays,
        'region'       => $reg !== '' && $reg !== $pays ? $reg : '',
        'site'         => $url,
        'description'  => $desc,
        'mots_cles'    => $tagTexte,
        'notes'        => 'Repris de panch.li/platforms le 17.08.2026.'
                        . ($ferme !== '' ? " Plateforme arrêtée en $ferme." : ''),
    ];
    printf("  + %-42s %-4s %-14s %s%s\n", mb_substr($nom, 0, 42), $pays,
           mb_substr($l['categorie'], 0, 14), mb_substr($url, 0, 34),
           $ferme !== '' ? "  [fermée $ferme]" : '');
    /* UN NOM PROCHE JUGÉ INSUFFISANT EST DIT, ET LA FICHE EST CRÉÉE QUAND MÊME.
       Les deux erreurs possibles n'ont pas le même prix: fusionner à tort deux
       structures différentes est irréparable — on ne sait plus lequel des deux
       contacts était le bon — alors qu'un quasi-doublon se voit et se fusionne
       à la main. On penche donc du côté qui se répare, et on l'annonce. */
    if ($voisin !== '') {
        printf("      ⚠ le carnet porte « %s » — vérifier si c'est la même\n", mb_substr($voisin, 0, 56));
        $proches++;
    }
    $crees++;
    if ($ecrire) {
        $l['ref'] = $refSuivante();
        DB::insert('contact', array_filter($l, fn($v) => $v !== ''));
    }
}

printf("\n  %d créée(s) · %d déjà connue(s), %d fiche(s) complétée(s) · %d sans changement\n",
       $crees, $enrichis, $touchees, $inchanges);
if ($proches) printf("  %d création(s) avec un nom proche dans le carnet, signalé ci-dessus.\n", $proches);
printf("  Étiquette pour les retrouver: « %s »\n", TAG);
if (!$ecrire) echo "\n  Relance avec --ecrire pour appliquer.\n";

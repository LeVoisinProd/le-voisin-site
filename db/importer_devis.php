<?php
/**
 * Les devis envoyés deviennent des offres du pipeline. [16.08.2026]
 *
 *   php db/importer_devis.php <devis.json> [--ecrire]
 *
 * POURQUOI ILS N'Y ÉTAIENT PAS. Anna, en ouvrant l'écran: « na parte offres, kd
 * a lista dos devis de bestiarium ». Ils existent — huit devis du Bestiarium,
 * en PDF sur le Drive, produits par la skill `/devis` du dépôt de travail — et
 * le dashboard n'en connaissait aucun. La table `offer` était à zéro.
 *
 * ET C'EST LE BON ENDROIT POUR EUX. Un devis envoyé à un lieu EST une offre en
 * cours: on a proposé la pièce, à un prix, à une date, et on attend une réponse.
 * L'écran Offres était pensé pour les demandes qui arrivent; il porte aussi
 * bien les propositions qui partent, et c'est même la moitié qui manquait —
 * sans elle le pipeline ne montrait jamais ce qu'on avait engagé.
 *
 * LE PRIX EST CELUI DE LA LIGNE DE GRILLE CORRESPONDANT AU NOMBRE DE
 * REPRÉSENTATIONS DEMANDÉ, lu dans le devis lui-même. Il est repris tel quel et
 * jamais recalculé: le calcul vit dans la skill `/devis`, avec les barèmes, et
 * le refaire ici donnerait deux chiffres qui divergeraient au premier barème
 * changé. Ce qui est importé est ce qui a été ENVOYÉ, ce qui est exactement la
 * valeur qu'on veut retrouver dans six mois.
 *
 * IDEMPOTENT PAR (projet, lieu). Relancer ne duplique pas; une ligne déjà
 * traitée — contre-proposée, acceptée, refusée — n'est jamais rétrogradée.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

$fichier = '';
foreach (array_slice($argv, 1) as $a) if ($a !== '--ecrire') { $fichier = $a; break; }
$ecrire = in_array('--ecrire', $argv, true);

if ($fichier === '' || !is_file($fichier)) {
    fwrite(STDERR, "Usage: php db/importer_devis.php <devis.json> [--ecrire]\n");
    exit(1);
}
$lignes = json_decode((string)file_get_contents($fichier), true);
if (!is_array($lignes)) { fwrite(STDERR, "JSON illisible.\n"); exit(1); }

echo $ecrire ? "ÉCRITURE\n\n" : "SIMULATION — rien n'est écrit. --ecrire pour appliquer.\n\n";

$neufs = $vus = 0;
foreach ($lignes as $d) {
    $lieu   = trim((string)($d['lieu'] ?? ''));
    $projet = trim((string)($d['projet'] ?? 'Bestiarium'));
    if ($lieu === '') continue;

    /* La devise vient du pays, pas d'un champ: les devis du Bestiarium sont
       tous libellés en CHF — c'est une association suisse qui vend — même pour
       un lieu français. Le lire du prix affiché évite de l'inventer. */
    $devise = trim((string)($d['devise'] ?? 'CHF'));

    $existe = DB::one('SELECT id, statut FROM offer WHERE projet = ? AND venue = ?', [$projet, $lieu]);
    if ($existe) {
        printf("  %-38s déjà dans le pipeline (%s)\n", mb_substr($lieu, 0, 38), $existe['statut']);
        $vus++;
        continue;
    }

    printf("  %-38s %-24s %s %s · %s repr.\n", mb_substr($lieu, 0, 38),
           mb_substr(trim((string)($d['ville'] ?? '') . ', ' . (string)($d['pays'] ?? ''), ', '), 0, 24),
           $devise, number_format((float)($d['prix'] ?? 0), 0, ',', ' '), (string)($d['repr'] ?? '?'));
    $neufs++;

    if (!$ecrire) continue;

    /* `message` porte le texte du devis: les dates envisagées et la demande,
       mot pour mot. C'est ce qu'on relit avant de relancer, et le reformuler
       ferait perdre la nuance — « à confirmer avec la direction artistique »
       n'est pas « à définir ». */
    $msg = trim(implode("\n", array_filter([
        ($d['dates']   ?? '') !== '' ? 'Dates envisagées: ' . $d['dates']   : '',
        ($d['demande'] ?? '') !== '' ? 'Demande: '          . $d['demande'] : '',
    ])));

    DB::insert('offer', [
        'projet'          => $projet,
        'venue'           => mb_substr($lieu, 0, 190),
        'ville'           => mb_substr(trim((string)($d['ville'] ?? '')), 0, 96) ?: null,
        'pays'            => mb_substr(trim((string)($d['pays'] ?? '')), 0, 64) ?: null,
        'date_texte'      => mb_substr(trim((string)($d['dates'] ?? '')), 0, 190) ?: null,
        'representations' => (int)($d['repr'] ?? 0) ?: null,
        'budget'          => (float)($d['prix'] ?? 0) ?: null,
        'devise'          => in_array($devise, ['CHF','EUR'], true) ? $devise : 'CHF',
        /* Le contact reste vide: le devis ne le porte pas, et inventer un nom
           ferait croire qu'on sait à qui écrire. */
        'contact_nom'     => mb_substr(trim((string)($d['contact'] ?? '')), 0, 190) ?: 'à renseigner',
        'message'         => $msg ?: null,
        'notes_internes'  => 'Devis envoyé le ' . (string)($d['date'] ?? '') . '. Fichier: '
                           . (string)($d['fichier'] ?? '') . ' — sur le Drive de l\'association.',
        /* « en_discussion » et non « nouvelle »: une offre nouvelle est une
           demande qui vient d'arriver et qu'on n'a pas encore lue. Un devis
           parti est déjà une conversation engagée, et le compteur de « nouvelles
           à traiter » du tableau de bord ne doit pas s'en alarmer. */
        'statut'          => 'en_discussion',
        'ip'              => 'devis',
    ]);
}

printf("\n  %d à créer · %d déjà présents\n", $neufs, $vus);
if (!$ecrire) echo "  Relance avec --ecrire pour appliquer.\n";

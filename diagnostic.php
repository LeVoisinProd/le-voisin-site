<?php
/**
 * Où l'espace se casse.                             [DIAG-500] [13.08.2026]
 *
 * L'installateur valide chaque fichier avec token_get_all avant de l'écrire et
 * refuse le paquet entier au premier défaut de syntaxe. Un 500 après une
 * installation réussie n'est donc jamais une faute d'écriture : c'est une
 * erreur d'EXÉCUTION — une méthode qui n'existe pas, une classe absente, un
 * argument de trop — et celle-là ne se voit qu'en la provoquant.
 *
 * Cette page la provoque, une étape à la fois, en écrivant AVANT chaque essai
 * ce qu'elle s'apprête à faire et en vidant le tampon. Si le processus meurt,
 * la dernière ligne affichée nomme l'étape fautive. C'est la seule méthode qui
 * marche quand on n'a pas la main sur les journaux du serveur.
 *
 * ELLE NE S'OUVRE QU'AVEC LA SESSION DU CMS, et sans mot de passe propre : un
 * secret écrit dans un fichier du dépôt est un secret publié. Si l'espace est
 * cassé mais que cette page s'affiche, c'est déjà une information — le socle
 * tient et le défaut est plus haut.
 *
 * À SUPPRIMER une fois le défaut trouvé. Elle affiche des chemins de fichiers,
 * ce qui n'a rien à faire sur un site en production plus longtemps qu'il ne
 * faut.
 */
require __DIR__ . '/app/bootstrap.php';

/* Les erreurs s'affichent ICI et nulle part ailleurs. Le site en production
   les cache, ce qui est juste partout sauf sur cette page. */
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

Auth::requireAdmin();

header('Content-Type: text/plain; charset=utf-8');

$etape = 0;
function pas(string $quoi): void
{
    echo str_pad(++$GLOBALS['etape'] . '.', 4) . $quoi . "\n";
    @ob_flush();
    @flush();
}
function bilan(string $quoi, $valeur): void
{
    echo '      ' . str_pad($quoi, 34) . (is_bool($valeur) ? ($valeur ? 'oui' : 'NON') : (string)$valeur) . "\n";
    @ob_flush();
    @flush();
}

echo "DIAGNOSTIC DE L'ESPACE   " . date('d.m.Y H:i:s') . "\n";
echo str_repeat('=', 62) . "\n\n";

pas('PHP et socle');
bilan('version de PHP', PHP_VERSION);
bilan('ZipArchive', class_exists('ZipArchive'));

pas('Les classes que l\'espace charge');
foreach (['I18n', 'DB', 'Auth', 'MemberAuth', 'MemberDocs', 'Docs', 'Dates',
          'Settings', 'Invitations', 'Img', 'Skribble', 'Ico',
          'NomFichier', 'Forms', 'MemberNotify', 'Mailer', 'Catalog'] as $c) {
    bilan($c, class_exists($c));
}

pas('Les méthodes ajoutées aujourd\'hui');
$attendus = [
    ['NomFichier',   'construire'],
    ['NomFichier',   'morceau'],
    ['MemberDocs',   'nomDepot'],
    ['MemberDocs',   'ancre'],
    ['MemberDocs',   'catLabel'],
    ['MemberDocs',   'dir'],
    ['MemberDocs',   'projetChoix'],
    ['Forms',        'adresseComptable'],
    ['MemberNotify', 'factureDeposee'],
    ['Ico',          'bas'],
    ['Docs',         'human'],
];
foreach ($attendus as [$c, $m]) bilan("$c::$m()", method_exists($c, $m));

pas('Le nombre d\'arguments de factureDeposee');
try {
    $r = new ReflectionMethod('MemberNotify', 'factureDeposee');
    bilan('arguments attendus', $r->getNumberOfParameters());
    bilan('obligatoires', $r->getNumberOfRequiredParameters());
} catch (Throwable $e) {
    bilan('erreur', $e->getMessage());
}

pas('Les libellés');
I18n::init();
bilan('langue', I18n::$lang);
foreach (['member_depot_montant', 'member_depot_montant_ph', 'member_depot_devise',
          'member_depot_montant_err', 'member_download'] as $k) {
    bilan($k, t($k) !== $k ? 'présent' : 'ABSENT');
}
foreach (['doc_do_ack', 'doc_f_ok', 'nav_documents', 'doc_title'] as $k) {
    bilan($k . ' (admin)', tu($k) !== $k ? 'présent' : 'ABSENT');
}

pas('Les colonnes de member_documents');
try {
    $cols = DB::all('SHOW COLUMNS FROM member_documents');
    bilan('colonnes', implode(', ', array_map(fn($c) => (string)$c['Field'], $cols)));
} catch (Throwable $e) {
    bilan('erreur', $e->getMessage());
}

pas('Les fonctions de l\'espace');
require_once __DIR__ . '/espace/_docs.php';
foreach (['espace_periodes', 'espace_facture_form', 'espace_docs_depot'] as $f) {
    bilan($f . '()', function_exists($f));
}

pas('Le formulaire de dépôt, rendu pour de bon');
/* C'est l'étape qui compte : si le 500 vient de là, le processus meurt ici et
   la dernière ligne lue sera celle-ci. */
$m = DB::one('SELECT * FROM collaborators WHERE active = 1 ORDER BY id LIMIT 1');
if (!$m) {
    bilan('aucun collaborateur actif', 'rien à rendre');
} else {
    bilan('personne d\'essai', (string)($m['name'] ?? $m['email']));
    $html = espace_facture_form($m);
    bilan('longueur du rendu', strlen($html) . ' octets');
}

pas('La définition du formulaire d\'infos');
try {
    $def = Forms::def('form_infos');
    bilan('champs', is_array($def['fields'] ?? null) ? count($def['fields']) : 'ABSENT');
} catch (Throwable $e) {
    bilan('erreur', $e->getMessage());
}

echo "\n" . str_repeat('=', 62) . "\n";
echo "TOUT EST PASSÉ. Le défaut n'est pas dans ce qui vient d'être essayé.\n";

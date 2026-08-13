<?php
/** Espace collaborateur — amorçage + gabarit (distinct de l'administration).   [V12-ESPACE] */
require dirname(__DIR__) . '/app/bootstrap.php';
I18n::init();
session_boot();
/* [V16-ESPACE-BILINGUE] La langue de l'espace collaborateur.
 *
 * Tout le site existe en deux langues ; l'espace ne faisait pas exception en
 * principe, mais en pratique il n'offrait aucun moyen d'en changer : la langue
 * était celle inscrite sur la fiche du collaborateur, et lui seul ne pouvait
 * pas la corriger. Une personne inscrite en français y restait, même si elle
 * lit l'anglais.
 *
 * Trois sources, dans cet ordre : le bouton FR / EN de l'en-tête (?lang=…),
 * puis le choix fait plus tôt pendant la visite, puis la langue notée sur la
 * fiche. À défaut de tout cela, le français.
 *
 * Le choix fait avec le bouton est aussi recopié sur la fiche : la personne
 * retrouve ainsi sa langue à la prochaine connexion, y compris depuis un autre
 * appareil, et les courriels que le bureau lui adresse suivent le même
 * réglage. La colonne « lang » n'existe qu'une fois la base mise à jour : tant
 * qu'elle manque, le choix vaut pour la visite en cours seulement — mieux vaut
 * cela qu'une page blanche juste après une connexion réussie.
 */
$__ml = '';
$__demandee = strtolower(trim((string)($_GET['lang'] ?? '')));
if (in_array($__demandee, I18n::$langs, true)) {
    $__ml = $__demandee;
    $_SESSION['lv_espace_lang'] = $__ml;
    if (!empty($_SESSION['lv_member_id'])) {
        try {
            DB::update('collaborators', ['lang' => $__ml], 'id = ?', [(int)$_SESSION['lv_member_id']]);
        } catch (Throwable $__e) {
            // Le choix tient pour la visite en cours : rien à signaler.
        }
    }
}
if ($__ml === '' && !empty($_SESSION['lv_espace_lang']) && in_array($_SESSION['lv_espace_lang'], I18n::$langs, true)) {
    $__ml = (string)$_SESSION['lv_espace_lang'];
}
if ($__ml === '' && !empty($_SESSION['lv_member_id'])) {
    try {
        $__r = DB::one('SELECT lang FROM collaborators WHERE id = ?', [(int)$_SESSION['lv_member_id']]);
        if ($__r && !empty($__r['lang']) && in_array($__r['lang'], I18n::$langs, true)) $__ml = (string)$__r['lang'];
    } catch (Throwable $__e) {
        // Langue par défaut : rien à signaler au collaborateur.
    }
}
I18n::setLang($__ml !== '' ? $__ml : 'fr');

/**
 * L'ancre de la partie où vit un document.               [12.08.2026]
 *
 * Les formulaires de l'espace renvoient vers la partie d'où l'on vient, sinon
 * on retombe en haut de la page après chaque geste. L'ancre était écrite en
 * dur — « #partie-contrats » — à l'époque où les factures y vivaient. Depuis
 * qu'elles ont la leur, une ancre fixe renvoyait au mauvais onglet : le dépôt
 * fonctionnait et semblait n'avoir rien fait, puisqu'on atterrissait là où le
 * document n'est pas.
 *
 * Elle se déduit maintenant du volet, comme le classement lui-même. Ajouter
 * une partie demandera de nommer sa section « partie-<volet> » et rien de plus.
 */
function espace_ancre(string $volet): string
{
    return MemberDocs::ancre($volet);
}

function espace_url(string $path = ''): string
{
    return url('/espace/' . ltrim($path, '/'));
}

/**
 * L'adresse d'une feuille de style ou d'un script, suivie de ?v=…
 * Sans cela, le navigateur garde l'ancienne version en mémoire et les
 * corrections d'apparence restent invisibles pendant des jours.
 */
function espace_asset(string $chemin): string
{
    $chemin = '/' . ltrim($chemin, '/');
    return url($chemin) . '?v=' . (@filemtime(LV_ROOT . $chemin) ?: 1);
}

/**
 * L'adresse de la page en cours, dans l'autre langue.   [V16-ESPACE-BILINGUE]
 *
 * On reste exactement où l'on est — même page, mêmes paramètres — et l'on
 * change seulement la langue. Changer de langue au milieu d'une fiche ne doit
 * pas renvoyer à l'accueil.
 */
function espace_lang_url(string $lg): string
{
    $uri   = (string)($_SERVER['REQUEST_URI'] ?? '/espace/');
    $bouts = explode('?', $uri, 2);
    $q     = [];
    if (isset($bouts[1])) parse_str($bouts[1], $q);
    $q['lang'] = $lg;
    return $bouts[0] . '?' . http_build_query($q);
}

/**
 * Le texte du bandeau de visite, dans la langue de l'administration. [V27-ACCES]
 *
 * Tout le reste de la page est dans la langue du collaborateur — c'est le
 * principe même : on regarde sa page telle qu'il la voit. Mais le bandeau,
 * lui, ne s'adresse pas à lui : il s'adresse à qui regarde. Rendu dans la
 * langue du collaborateur, il pourrait n'être compris de personne — or il dit
 * chez qui l'on est, et ce qu'on y a le droit de faire. [V37-FICHE-BUREAU]
 */
function espace_visite_t(string $cle, ...$vals): string
{
    $lg = (string)($_SESSION['lv_alang'] ?? $_COOKIE['lv_alang'] ?? I18n::ADMIN_DEFAULT);
    if (!in_array($lg, I18n::ADMIN_LANGS, true)) $lg = I18n::ADMIN_DEFAULT;
    $s = I18n::t($cle, $lg);
    return $vals ? vsprintf($s, $vals) : $s;
}

function espace_top(string $title, bool $withNav = true): void
{
    $m = $withNav ? MemberAuth::member() : null;
    ?><!DOCTYPE html>
<html lang="<?= e(I18n::$lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> — Le Voisin</title>
<link rel="stylesheet" href="<?= e(espace_asset('/assets/css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(espace_asset('/assets/css/site.css')) ?>">
</head>
<body class="espace-body<?= MemberAuth::visite() ? ' en-visite' : '' ?>">
<?php /* [V27-ACCES] Bandeau de visite. Quand l'administration regarde l'espace
         de quelqu'un, cela doit se voir en permanence et de loin : on est chez
         une autre personne, et ce qu'on lit là — salaire, IBAN, documents — ne
         nous appartient pas. Le bandeau reste en haut de chaque page tant que
         la visite dure, et il porte le seul bouton qui la referme.
         [V37-FICHE-BUREAU] Il ne promet plus que rien n'est modifié : la fiche
         s'écrit maintenant aussi depuis le bureau. Le bandeau dit où l'on est ;
         ce qui peut être écrit se dit là où on l'écrit. */ ?>
<?php if (MemberAuth::visite()): $__v = MemberAuth::member(); ?>
<div class="espace-visite">
  <p><strong><?= e(espace_visite_t('member_visit_on')) ?></strong>
    <?= e(espace_visite_t('member_visit_who', $__v ? ($__v['name'] ?: $__v['email']) : '')) ?></p>
  <p><a class="btn small btn-visite" href="<?= e(espace_url('logout.php')) ?>"><?= e(espace_visite_t('member_visit_back')) ?></a></p>
</div>
<?php endif; ?>
<header class="espace-head">
  <a class="logo" href="<?= e(url('/')) ?>" aria-label="Le Voisin">
    <?php if (is_file(LV_ROOT . '/assets/img/logo-levoisin.png')): ?>
    <img src="<?= e(url('/assets/img/logo-levoisin.png')) ?>" alt="Le Voisin">
    <?php else: ?>LE&nbsp;VOISIN<?php endif; ?>
  </a>
  <?php /* [V16-ESPACE-BILINGUE] Le choix FR / EN, au même endroit et dans le
           même style que sur le site : en haut à droite, la langue en cours en
           noir. Il figure aussi sur la page de connexion — une personne qui ne
           lit pas le français doit pouvoir lire la page avant d'entrer. */ ?>
  <div class="espace-head-right">
    <p class="nav-langs espace-langs">
      <?php foreach (I18n::$langs as $lg): ?>
      <a href="<?= e(espace_lang_url($lg)) ?>" hreflang="<?= e($lg) ?>"<?= $lg === I18n::$lang ? ' class="on" aria-current="true"' : '' ?>><?= e(strtoupper($lg)) ?></a>
      <?php endforeach; ?>
    </p>
    <?php if ($m): ?>
    <div class="espace-user">
      <span><?= e($m['name'] ?: $m['email']) ?></span>
      <?php /* [V27-ACCES] Pendant une visite, ce bouton disparaît : il dirait
               « Déconnexion », ce qui serait faux — on n'est pas connecté à la
               place de quelqu'un, on regarde. La sortie est unique, elle est
               dans le bandeau noir, et l'on ne peut pas se tromper. */ ?>
      <?php if (!MemberAuth::visite()): ?>
      <a class="btn small" href="<?= e(espace_url('logout.php')) ?>"><?= e(t('member_logout')) ?></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</header>
<main class="espace-main"><div class="wrap narrow">
<?php
}

/**
 * L'état d'un document, et le bouton qui le fait avancer.   [V36-FACTURES]
 *
 * Deux choses au même endroit parce qu'elles se lisent ensemble : où en est ce
 * document, et qu'ai-je à faire. « Payée le 12.08 » suivi de « J'ai bien reçu »
 * se comprend sans explication ; les mêmes séparés se cherchent.
 *
 * Le bouton n'apparaît que si MemberDocs::statutSuivant() dit qu'il y a
 * quelque chose à poser. Ce n'est pas l'affichage qui décide du droit — le
 * traitement le revérifie, et un bouton absent n'a jamais empêché personne de
 * fabriquer un envoi à la main —, mais les deux consultent la même règle, si
 * bien qu'ils ne peuvent pas se contredire.
 *
 * Pendant une visite de l'administration, le bouton reste visible et éteint :
 * on voit ce que la personne peut faire, sans pouvoir le faire à sa place.
 */
function espace_doc_statut(array $d): string
{
    if (!MemberDocs::colonneStatut()) return '';

    $out  = '';
    $clef = MemberDocs::statutClef($d);
    if ($clef !== '') {
        $quand = Dates::afficher((string)($d['status_at'] ?? ''));
        $out  .= '<span class="mdoc-st mdoc-st-' . e((string)$d['status']) . '">'
               . e(tu($clef)) . ($quand !== '' ? ' <span class="mdoc-st-d">' . e($quand) . '</span>' : '')
               . '</span>';
    }

    $vers = MemberDocs::statutSuivant($d, 'member');
    if ($vers === '') return $out;

    $visite = MemberAuth::visite();
    $ancre = espace_ancre(MemberDocs::volet((string)($d['category'] ?? '')));
    $out .= '<form class="mdoc-do" method="post" action="' . e(espace_url()) . $ancre . '">'
          . MemberAuth::csrfField()
          . '<input type="hidden" name="doc" value="statut">'
          . '<input type="hidden" name="id" value="' . (int)$d['id'] . '">'
          . '<input type="hidden" name="vers" value="' . e($vers) . '">'
          . '<button class="btn small" type="submit"' . ($visite ? ' disabled' : '') . '>'
          . e(tu(MemberDocs::boutonClef($d, $vers))) . '</button></form>';

    return $out;
}

/**
 * Une liste de documents, sans titre au-dessus.        [V33-ESPACE-3] [V34-ONGLETS]
 *
 * L'espace affiche les mêmes documents sous deux classements — par association
 * pour les pièces contractuelles, par projet pour celles de production. Le
 * rendu d'une ligne, lui, est le même dans les deux cas : bouton de
 * téléchargement, état de signature, lien Skribble. Il est donc écrit une seule
 * fois ici, pour qu'une correction sur un bouton n'ait pas à être faite à
 * quatre endroits — et oubliée au troisième.
 *
 * [13.08.2026] LES TITRES DE RUBRIQUE REVIENNENT, mais seulement quand il y a
 * plus d'une rubrique à séparer.
 *
 * Ils avaient été retirés parce qu'un titre par rubrique produisait une cascade
 * de titres pour un ou deux fichiers chacun. C'était vrai un espace vide, et
 * cela cesse de l'être au bout d'un an : les fiches de salaire arrivent douze
 * fois par an dans le même onglet que les contrats, qui sont deux ou trois. Le
 * contrat finit noyé, et c'est le document qu'on vient chercher.
 *
 * Donc : une seule rubrique, pas de titre, la page d'hier. Plusieurs rubriques,
 * un titre chacune, et la rubrique quitte la ligne grise puisqu'elle est écrite
 * au-dessus. Le plus récent d'abord, parce que l'ordre de dépôt mettait le
 * contrat de 2024 au-dessus de la fiche de salaire de ce mois-ci.
 *
 * Et les fiches de salaire se replient par année. C'est la seule rubrique qui
 * grossit sans fin ; les autres se comptent sur une main.
 *
 * @param array<int, array<string, mixed>> $docs
 */
function espace_liste_docs(array $docs): string
{
    if (!$docs) return '';

    $par = [];
    foreach ($docs as $d) $par[(string)($d['category'] ?? 'other')][] = $d;

    /* Une seule rubrique : rien ne change, et la rubrique reste sur la ligne
       grise. C'est le cas de la plupart des fiches aujourd'hui. */
    if (count($par) < 2) return espace_liste_docs_brut($docs, true);

    $out = '';
    foreach ($par as $cat => $lot) {
        usort($lot, static fn($a, $b) => strcmp(
            (string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
        $out .= '<h4 class="mdoc-cat">' . e(MemberDocs::catLabel($cat, I18n::$lang)) . '</h4>';
        $out .= $cat === 'payslip' ? espace_docs_par_annee($lot) : espace_liste_docs_brut($lot, false);
    }
    return $out;
}

/**
 * Les fiches de salaire, par année, l'année en cours ouverte.
 *
 * Douze par an, pour toujours : au bout de trois ans une seule liste en compte
 * trente-six, et celle qu'on cherche est toujours l'une des deux dernières.
 * Les années passées se replient, avec leur compte écrit sur le repli pour
 * qu'on sache ce qu'il y a derrière sans l'ouvrir.
 */
function espace_docs_par_annee(array $docs): string
{
    $par = [];
    foreach ($docs as $d) {
        $an = substr((string)($d['created_at'] ?? ''), 0, 4) ?: '—';
        $par[$an][] = $d;
    }
    krsort($par);
    $courante = date('Y');
    $out = '';
    foreach ($par as $an => $lot) {
        if ((string)$an === $courante || count($par) === 1) {
            $out .= espace_liste_docs_brut($lot, false);
            continue;
        }
        $out .= '<details class="mdoc-annee"><summary>' . e($an) . ' (' . count($lot) . ')</summary>'
              . espace_liste_docs_brut($lot, false) . '</details>';
    }
    return $out;
}

/** Le rendu d'une liste, sans titre ni regroupement. */
function espace_liste_docs_brut(array $docs, bool $avecRubrique = true): string
{
    if (!$docs) return '';
    ob_start();
    ?>
  <ul class="mdoc-list">
    <?php foreach ($docs as $d): ?>
    <li class="mdoc">
      <div class="mdoc-main">
        <span class="mdoc-title"><?= e($d['title'] ?: $d['filename']) ?></span>
        <?php /* [V34-ONGLETS] Ce qu'on lit sous le nom : la rubrique, puis le
                 format, le poids et la date de dépôt. Ni l'association ni le
                 projet — ils sont écrits en titre juste au-dessus, les répéter
                 ici n'apprend rien et, quand le rangement change, finit par les
                 contredire. La date sert à distinguer deux fiches de salaire
                 dont le nom, par construction, se ressemble. */ ?>
        <span class="mdoc-meta"><?= e(implode(' · ', array_filter([
            $avecRubrique ? MemberDocs::catLabel((string)$d['category'], I18n::$lang) : '',
            strtoupper((string)$d['ext']),
            Docs::human((int)$d['size']),
            Dates::afficher((string)($d['created_at'] ?? '')),
        ]))) ?></span>
      </div>
      <div class="mdoc-actions">
        <?= espace_doc_statut($d) ?>
        <?php if ((int)$d['needs_signature'] === 1): ?>
          <?php if ($d['sign_status'] === 'signed'): ?>
          <span class="sig-badge signed">✓ <?= e(t('member_signed')) ?></span>
          <?php elseif ($d['skribble_signing_url'] !== ''): ?>
          <a class="btn small" href="<?= e($d['skribble_signing_url']) ?>" target="_blank" rel="noopener"><?= e(t('member_sign')) ?> <?= Ico::ext() ?></a>
          <?php else: ?>
          <span class="sig-badge tosign"><?= e(t('member_to_sign')) ?></span>
          <?php endif; ?>
        <?php endif; ?>
        <a class="btn small ghost" href="<?= e(espace_url('download.php?doc=' . (int)$d['id'])) ?>"><?= e(t('member_download')) ?></a>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
    <?php
    return (string)ob_get_clean();
}

function espace_bottom(): void
{
    ?></div></main>
<script src="<?= e(espace_asset('/assets/js/site.js')) ?>"></script>
<?php /* [V16-DATES] Aide à la frappe des dates ; sans effet s'il n'y en a pas. */ ?>
<?= Dates::script() ?>
</body>
</html>
<?php
}

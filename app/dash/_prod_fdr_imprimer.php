<?php
/**
 * La feuille de route, en document.  [Anna, 22.08.2026]
 *
 * « a feuille de route tem que estar assim, com as infos da logistique e infos
 * principais dos contatos da equipe e da equipe do teatro. »
 *
 * CE QU'ELLE ÉTAIT: un bloc de texte brut, écrit par un générateur, imprimé tel
 * quel dans un `<pre>`. Elle listait les noms de l'équipe et les lignes de
 * logistique bout à bout, SANS UN SEUL NUMÉRO DE TÉLÉPHONE — ni des nôtres, ni
 * de ceux du lieu. Or c'est exactement ce qu'on cherche à l'arrivée: un
 * téléphone, tout de suite, sans ouvrir un autre document.
 *
 * LES CONTACTS DE NOTRE ÉQUIPE NE SE RESAISISSENT PAS. Ils sont dans les fiches
 * du Personnel — 88 courriels et 56 téléphones sur 91 fiches, mesuré — et on les
 * y retrouve par le nom, comme les portraits du dossier. Les recopier ici ferait
 * deux vérités, et c'est celle du papier qui serait périmée.
 *
 * LA LOGISTIQUE EST RANGÉE PAR PERSONNE, et non par type. Sur place, la question
 * n'est jamais « quels sont les voyages » mais « comment arrive Dominique, où
 * dort-elle, qui la ramène ». Ce qui n'est attaché à personne — un décor qui
 * voyage, un repas d'équipe — se range à part, à la fin.
 *
 * LE TEXTE LIBRE RESTE, EN TÊTE. Si quelqu'un a écrit une feuille à la main dans
 * l'onglet, c'est elle qui compte: elle porte ce qu'aucun champ ne sait dire.
 *
 * Attend $p, $d, $prod, $pcms.
 */
declare(strict_types=1);
/** @var array $p */ /** @var array $d */ /** @var array $prod */ /** @var int $pcms */

$titre = trim((string)($p['title_fr'] ?: $p['title_en'])) ?: 'Spectacle';

$org = $prod['organisation_id']
    ? DB::one('SELECT nom FROM organisation WHERE id = ?', [(int)$prod['organisation_id']])
    : null;

/* ── Les dates du déplacement, déduites des étapes ─────────────────────────── */
$bornes = [];
foreach (($d['planning']['dates'] ?? []) as $e) {
    foreach (['debut', 'fin'] as $c) {
        $v = trim((string)($e[$c] ?? ''));
        if ($v !== '') $bornes[] = $v;
    }
}
sort($bornes);
$jour = static fn($x) => ($t = strtotime((string)$x)) ? date('d.m.Y', $t) : (string)$x;

/* ── Notre équipe, avec ce que le Personnel sait d'elle ────────────────────── */
$fiches = [];
try {
    foreach (DB::all("SELECT prenom, nom, email, telephone, fonction
                        FROM rh_employe WHERE supprime_le IS NULL") as $f) {
        $n = trim(((string)$f['prenom']) . ' ' . ((string)$f['nom']));
        if ($n !== '') $fiches[mb_strtolower($n)] = $f;
    }
} catch (Throwable $ex) { /* les contacts sont un plus, jamais une condition */ }

$equipe = [];
foreach (($d['equipe'] ?? []) as $m) {
    $n = trim(((string)($m['prenom'] ?? '')) . ' ' . ((string)($m['nom'] ?? '')));
    if ($n === '') continue;
    $f = $fiches[mb_strtolower($n)] ?? null;
    $equipe[] = [
        'nom'      => $n,
        'fonction' => (string)($m['fonction'] ?? '') ?: (string)($f['fonction'] ?? ''),
        'tel'      => (string)($f['telephone'] ?? ''),
        'email'    => (string)($f['email'] ?? ''),
    ];
}

/* ── L'équipe du lieu ─────────────────────────────────────────────────────── */
$lieu = $d['technique']['contacts'] ?? [];
/* L'ancien champ unique, s'il porte quelque chose: il ne se perd pas parce
   qu'une liste est arrivée après lui. */
$seul = $d['technique']['contact'] ?? [];
if (array_filter(array_map('strval', $seul))) {
    array_unshift($lieu, $seul + ['role' => ((string)($seul['role'] ?? '') ?: 'notre référent')]);
}

/* ── La logistique, rangée par personne ───────────────────────────────────── */
$parPersonne = [];
$sansNom     = [];
foreach (ProdFiche::LOGI as $cle => $lib) {
    foreach (($d['logistique'][$cle] ?? []) as $l) {
        $ligne = [
            'quoi'   => $lib,
            'quand'  => (string)($l['quand'] ?? ''),
            'libelle'=> (string)($l['libelle'] ?? ''),
            'trajet' => trim(((string)($l['depart'] ?? ''))
                       . ((($l['depart'] ?? '') !== '' && ($l['arrivee'] ?? '') !== '') ? ' → ' : '')
                       . ((string)($l['arrivee'] ?? ''))),
            'note'   => (string)($l['reference'] ?? ''),
        ];
        $qui = trim((string)($l['qui'] ?? ''));
        if ($qui === '') $sansNom[] = $ligne;
        else $parPersonne[$qui][] = $ligne;
    }
}

$texte = trim((string)($d['fdr']['texte'] ?? ''));

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= e($titre) ?> — feuille de route</title>
<style>
  @page { margin: 15mm; }
  * { box-sizing: border-box; }
  body { margin:0; padding:28px 32px 24px;
         font:13px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
         color:#111; background:#fff; }
  h1 { font-size:24px; margin:0 0 14px; letter-spacing:-.01em; }
  .meta { margin:0 0 24px; font-size:13px; line-height:1.75; }
  h2 { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.1em;
       margin:26px 0 10px; padding-bottom:6px; border-bottom:2px solid #111;
       break-after:avoid; page-break-after:avoid; }

  /* LES CONTACTS EN DEUX COLONNES: notre équipe et la leur, côte à côte. C'est
     ainsi qu'on les lit — « qui chez nous, qui chez eux » — et non l'une après
     l'autre. */
  .deux { display:grid; grid-template-columns:1fr 1fr; gap:0 44px; align-items:start; }
  .ct { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.09em;
        color:#8a8a8a; margin:0 0 8px; }
  .p { padding:5px 0; border-bottom:1px solid #e8e8e4; break-inside:avoid; }
  .p-n { font-weight:600; }
  .p-f { color:#666; }
  .p-c { display:block; font-size:12px; margin-top:1px; }
  .p-c a { color:#111; text-decoration:none; }
  .vide { color:#8a8a8a; }

  /* LA LOGISTIQUE PAR PERSONNE: un bloc par nom, insécable. Une personne coupée
     entre deux pages oblige à revenir en arrière, un téléphone à la main. */
  .lg { break-inside:avoid; page-break-inside:avoid; margin:0 0 12px; }
  .lg-n { font-weight:600; margin:0 0 3px; }
  .lg-l { display:flex; gap:10px; align-items:baseline; padding:2px 0;
          border-bottom:1px solid #f0f0ec; font-size:12.5px; }
  .lg-q { flex:0 0 auto; color:#8a8a8a; white-space:nowrap; }
  .lg-t { flex:0 0 auto; font-weight:600; }
  .lg-r { flex:1 1 auto; color:#444; }
  .lg-x { flex:0 0 auto; color:#8a8a8a; font-size:11.5px; }

  pre.t { white-space:pre-wrap; font:inherit; margin:0 0 8px; }
  footer { margin-top:26px; padding-top:9px; border-top:2px solid #111;
           display:flex; justify-content:space-between; gap:20px;
           color:#6b6b6b; font-size:10.5px; }
  @media print { body { padding:0 } }
</style>
</head>
<body>

<h1><?= e($titre) ?></h1>
<p class="meta">
  Feuille de route<?= $org ? ' · <b>' . e((string)$org['nom']) . '</b>' : '' ?><br>
  <?php if ($bornes): ?>Du <b><?= e($jour($bornes[0])) ?></b> au
    <b><?= e($jour($bornes[count($bornes) - 1])) ?></b><br><?php endif; ?>
  imprimée le <?= date('d.m.Y') ?>
</p>

<?php if ($texte !== ''): ?>
  <h2>À savoir</h2>
  <pre class="t"><?= e($texte) ?></pre>
<?php endif; ?>

<h2>Contacts</h2>
<div class="deux">
  <div>
    <p class="ct">Notre équipe</p>
    <?php if (!$equipe): ?><p class="vide">Personne saisie dans l’onglet Synthèse.</p><?php endif; ?>
    <?php foreach ($equipe as $m): ?>
      <div class="p">
        <span class="p-n"><?= e($m['nom']) ?></span><?php
          if ($m['fonction'] !== ''): ?> <span class="p-f">— <?= e($m['fonction']) ?></span><?php endif; ?>
        <?php $c = array_filter([$m['tel'], $m['email']]); ?>
        <?php if ($c): ?><span class="p-c"><?= e(implode(' · ', $c)) ?></span><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div>
    <p class="ct">L’équipe du lieu</p>
    <?php if (!$lieu): ?>
      <p class="vide">Personne encore — elle se saisit dans l’onglet Fiche technique.</p>
    <?php endif; ?>
    <?php foreach ($lieu as $c): ?>
      <div class="p">
        <span class="p-n"><?= e((string)($c['nom'] ?? '')) ?: '—' ?></span><?php
          if (($c['role'] ?? '') !== ''): ?> <span class="p-f">— <?= e((string)$c['role']) ?></span><?php endif; ?>
        <?php $x = array_filter([(string)($c['tel'] ?? ''), (string)($c['email'] ?? '')]); ?>
        <?php if ($x): ?><span class="p-c"><?= e(implode(' · ', $x)) ?></span><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($parPersonne || $sansNom): ?>
  <h2>Logistique</h2>
  <?php foreach ($parPersonne as $qui => $lignes): ?>
    <div class="lg">
      <p class="lg-n"><?= e((string)$qui) ?></p>
      <?php foreach ($lignes as $l): ?>
        <div class="lg-l">
          <span class="lg-q"><?= e($l['quand']) ?: '—' ?></span>
          <span class="lg-t"><?= e($l['libelle']) ?></span>
          <span class="lg-r"><?= e($l['trajet']) ?></span>
          <?php if ($l['note'] !== ''): ?><span class="lg-x"><?= e($l['note']) ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <?php if ($sansNom): ?>
    <div class="lg">
      <p class="lg-n">Sans personne</p>
      <?php foreach ($sansNom as $l): ?>
        <div class="lg-l">
          <span class="lg-q"><?= e($l['quand']) ?: '—' ?></span>
          <span class="lg-t"><?= e($l['libelle']) ?></span>
          <span class="lg-r"><?= e($l['trajet']) ?></span>
          <?php if ($l['note'] !== ''): ?><span class="lg-x"><?= e($l['note']) ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if (($d['planning']['dates'] ?? [])): ?>
  <h2>Étapes</h2>
  <?php foreach ($d['planning']['dates'] as $et): ?>
    <div class="lg-l">
      <span class="lg-q"><?= e($jour($et['debut'] ?? '')) ?><?php
        if (($et['fin'] ?? '') !== '' && ($et['fin'] ?? '') !== ($et['debut'] ?? '')):
          ?> → <?= e($jour($et['fin'])) ?><?php endif; ?></span>
      <span class="lg-t"><?= e(ProdFiche::PHASES[$et['phase'] ?? ''] ?? '') ?></span>
      <span class="lg-r"><?= e(trim(((string)($et['lieu'] ?? '')) . ' '
                             . ((string)($et['ville'] ?? '')) . ' '
                             . ((string)($et['pays'] ?? '')))) ?></span>
      <?php if (($et['adresse'] ?? '') !== ''): ?>
        <span class="lg-x"><?= e((string)$et['adresse']) ?></span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<footer>
  <span>Le Voisin Productions · anna@le-voisin.com · +41 78 257 13 17</span>
  <span><?= e($titre) ?> — feuille de route</span>
</footer>

</body>
</html>
<?php return;

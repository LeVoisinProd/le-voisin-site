<?php
/**
 * Le dossier et la feuille de route, en version imprimable. [16.08.2026]
 *
 * Même parti que le relevé des Finances, et la même raison: le site n'a aucune
 * bibliothèque de génération de PDF, et le navigateur sait déjà faire un PDF
 * d'une page. Le résultat est un vrai PDF, sélectionnable et cherchable.
 *
 * CE SONT LES DEUX SEULS ONGLETS QUI SORTENT DE LA MAISON. Un dossier part
 * chez un financeur, une feuille de route part chez l'équipe et au lieu. Les
 * autres onglets se lisent à l'écran et n'ont pas besoin de papier.
 *
 * La page est nue: ni menu, ni onglets, ni liens.
 *
 * Attend $p, $onglet, $pcms.
 */
declare(strict_types=1);
/** @var array $p */ /** @var string $onglet */ /** @var int $pcms */

$d = ProdFiche::donnees($pcms);
$titre = trim((string)($p['title_fr'] ?: $p['title_en'])) ?: 'Spectacle';
$quoi  = $onglet === 'dossier' ? 'Dossier de demande de fonds' : 'Feuille de route';
?><!doctype html>
<html lang="fr"><head>
<meta charset="utf-8">
<meta name="robots" content="noindex">
<title><?= e($quoi) ?> — <?= e($titre) ?></title>
<style>
  body{margin:0;padding:26px;background:#fff;color:#111;
    font:13px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif}
  h1{font-size:21px;margin:0 0 2px;letter-spacing:-.01em}
  .st{color:#666;font-size:12.5px;margin:0 0 22px;padding-bottom:14px;border-bottom:1px solid #ddd}
  h2{font-size:11.5px;text-transform:uppercase;letter-spacing:.1em;color:#555;
    margin:24px 0 6px;font-weight:700}
  p.t{margin:0 0 12px;white-space:pre-wrap;max-width:88ch}
  pre.t{margin:0 0 12px;white-space:pre-wrap;font:inherit;max-width:88ch}
  .imp{margin:0 0 20px;padding:8px 12px;background:#f3f3f1;font-size:12px}
  .vide{color:#888;font-style:italic}
  @media print{ .imp{display:none} body{padding:0} @page{margin:16mm} h2{break-after:avoid} }
</style>
</head><body>
<p class="imp">Faites « Imprimer », puis « Enregistrer au format PDF ». Ce bandeau ne s'imprime pas.</p>

<h1><?= e($titre) ?></h1>
<p class="st"><?= e($quoi) ?> · Le Voisin · établi le <?= date('d.m.Y') ?></p>

<?php if ($onglet === 'dossier'):
    $bl = [
        'Lettre de motivation' => (string)$d['dossier']['lettre'],
        'Description du projet' => (string)$d['dossier']['description'],
        'Note d\'intention'     => (string)$d['dossier']['intention'],
    ];
    foreach ($bl as $t => $v): if (trim($v) === '') continue; ?>
      <h2><?= e($t) ?></h2>
      <p class="t"><?= e($v) ?></p>
    <?php endforeach; ?>

    <?php $cal = trim((string)$d['dossier']['calendrier']);
          if ($cal === '') $cal = ProdFiche::calendrierDepuisPlanning($d);
          if ($cal !== ''): ?>
      <h2>Calendrier</h2>
      <pre class="t"><?= e($cal) ?></pre>
    <?php endif; ?>

    <?php foreach (['Public cible' => (string)$d['dossier']['publicCible'],
                    'Bénéfice pour la ville' => (string)$d['dossier']['benefice'],
                    'Résumé' => (string)$d['resume'],
                    'Coproductions' => (string)$d['coproductions'],
                    'Soutiens' => (string)$d['soutiens']] as $t => $v):
          if (trim($v) === '') continue; ?>
      <h2><?= e($t) ?></h2>
      <p class="t"><?= e($v) ?></p>
    <?php endforeach; ?>

<?php else:
    $txt = trim((string)$d['fdr']['texte']);
    if ($txt === '') $txt = ProdFiche::feuilleDeRoute($p, $d); ?>
    <?php if (trim($txt) !== ''): ?>
      <pre class="t"><?= e($txt) ?></pre>
    <?php else: ?>
      <p class="vide">La feuille de route est vide. Remplissez le Planning, l'Équipe et la
         Logistique, puis générez-la depuis l'onglet.</p>
    <?php endif; ?>
<?php endif; ?>

</body></html>

<?php
/**
 * Le presskit d'un spectacle. [16.08.2026]
 *
 * Anna: « une page presskit par projet, pour partager fiches techniques,
 * photos et documents techniques. » C'est le lien qu'on envoie à un
 * programmateur intéressé, sans lui demander d'ouvrir quoi que ce soit.
 *
 * IL NE RECOPIE RIEN. Tout vient du CMS: le titre, l'intro, la distribution,
 * les infos, la couverture, la galerie, les documents. Une deuxième saisie
 * divergerait au premier changement — le défaut même que la spécification
 * reproche à l'existant.
 *
 * LES FICHES TECHNIQUES PASSENT PAR document.php, à qui l'on a appris ce
 * jeton, et jamais par un lien direct vers uploads/. C'est la leçon du
 * 13.08.2026, où l'on a découvert que les riders restaient joignables par leur
 * adresse: « le mot de passe protégeait la page, pas les fichiers. »
 *
 * L'ADRESSE:  https://le-voisin.com/presskit.php?t=<jeton>
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

I18n::init();

$jeton = (string)($_GET['t'] ?? '');
$lien  = Presskit::parJeton($jeton);

if (!$lien) {
    http_response_code(404);
    $titre = 'Lien introuvable';
    $corps = '<p>Ce lien n\'est plus valable. Il a peut-être expiré, ou été remplacé.</p>
              <p>Écrivez à la personne qui vous l\'a envoyé: elle peut en ouvrir un
              nouveau en un clic.</p>';
    require __DIR__ . '/app/page_publique.php';
    exit;
}

$pid = (int)$lien['project_id'];
$p   = Presskit::projet($pid);
if (!$p) { http_response_code(404); exit('Introuvable'); }

Presskit::noterVisite($pid);

$images = Presskit::images($pid);
$docs   = Presskit::documents($pid);

/* La langue de la fiche suit celle du navigateur quand le champ existe: un
   programmateur allemand lira l'anglais plutôt que rien. */
$fr = I18n::$ui !== 'en';
$g  = static fn(string $base): string
    => trim((string)($p[$base . ($fr ? '_fr' : '_en')] ?? '')) !== ''
       ? (string)$p[$base . ($fr ? '_fr' : '_en')]
       : (string)($p[$base . ($fr ? '_en' : '_fr')] ?? '');

$titre = trim($g('title')) ?: 'Presskit';

ob_start();
?>
<?php $cover = null; foreach ($images as $im) { if ($im['zone'] === 'cover') { $cover = $im; break; } } ?>
<?php if ($cover): ?>
  <img class="cover" src="<?= e(upload_url('i/' . (int)$cover['id'] . '/orig.' . $cover['ext'])) ?>"
       alt="<?= e((string)($cover[$fr ? 'alt_fr' : 'alt_en'] ?? '')) ?>">
<?php endif; ?>

<p class="chapo">
  <?php if ($p['year_creation']): ?><?= e((string)$p['year_creation']) ?> · <?php endif; ?>
  <?php if ($p['duration_min']): ?><?= (int)$p['duration_min'] ?> min<?php endif; ?>
  <?php if ($p['public_cible']): ?> · <?= e((string)$p['public_cible']) ?><?php endif; ?>
</p>

<?php if (trim($g('intro')) !== ''): ?>
  <div class="intro"><?= nl2br(e($g('intro'))) ?></div>
<?php endif; ?>

<?php if (trim($g('body')) !== ''): ?>
  <h2>Le spectacle</h2>
  <div class="corps"><?= nl2br(e(strip_tags($g('body')))) ?></div>
<?php endif; ?>

<?php if (trim($g('distribution')) !== ''): ?>
  <h2>Distribution</h2>
  <div class="corps"><?= nl2br(e(strip_tags($g('distribution')))) ?></div>
<?php endif; ?>

<?php if (trim($g('infos')) !== ''): ?>
  <h2>Informations</h2>
  <div class="corps"><?= nl2br(e(strip_tags($g('infos')))) ?></div>
<?php endif; ?>

<?php $galerie = array_values(array_filter($images, fn($i) => $i['zone'] === 'gallery')); ?>
<?php if ($galerie): ?>
  <h2>Photos</h2>
  <div class="gal">
    <?php foreach ($galerie as $im): ?>
      <a href="<?= e(upload_url('i/' . (int)$im['id'] . '/orig.' . $im['ext'])) ?>" target="_blank" rel="noopener">
        <img src="<?= e(upload_url('i/' . (int)$im['id'] . '/orig.' . $im['ext'])) ?>"
             alt="<?= e((string)($im[$fr ? 'alt_fr' : 'alt_en'] ?? '')) ?>" loading="lazy">
      </a>
    <?php endforeach; ?>
  </div>
  <p class="cons">Cliquez pour ouvrir en grand. Ces photos sont libres pour votre
     communication autour des dates que nous ferons ensemble.</p>
<?php endif; ?>

<?php if ($docs): ?>
  <h2>Documents</h2>
  <ul class="docs">
    <?php foreach ($docs as $d): ?>
      <li>
        <a href="document.php?d=<?= (int)$d['id'] ?>&amp;pk=<?= e($jeton) ?>">
          <?= e(trim((string)($d[$fr ? 'title_fr' : 'title_en'] ?? '')) ?: (string)$d['filename']) ?>
        </a>
        <span class="n"><?= e(strtoupper((string)$d['ext'])) ?><?php
          if ((int)$d['size'] > 0): ?> · <?= (int)round((int)$d['size'] / 1024) ?> Ko<?php endif; ?>
          <?php if ($d['zone'] === 'doc'): ?> · fiche technique<?php endif; ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if (!$docs && !$galerie && trim($g('intro')) === ''): ?>
  <p class="vide">Ce presskit n'a pas encore de contenu. Nous vous préviendrons
     dès qu'il est prêt.</p>
<?php endif; ?>

<p class="pied">Une question, ou une date en tête ?
   <a href="demande.php">Écrivez-nous par ici</a>.</p>

<style>
.cover{width:100%;height:auto;border-radius:6px;margin-bottom:18px;display:block}
.intro{font-size:17.5px;line-height:1.5;margin-bottom:8px}
.corps{max-width:66ch}
.gal{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;margin-bottom:10px}
.gal img{width:100%;height:130px;object-fit:cover;border-radius:4px;display:block}
.gal a:focus-visible img{outline:2px solid var(--encre);outline-offset:2px}
.docs{list-style:none;margin:0;padding:0}
.docs li{padding:10px 0;border-bottom:1px solid var(--trait)}
.docs .n{font-size:12.5px;color:var(--doux);margin-left:8px}
</style>
<?php
$corps = (string)ob_get_clean();
require __DIR__ . '/app/page_publique.php';

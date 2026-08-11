<?php
/* Page simple : titre, texte, boutons vers les sous-pages, galerie, documents.

   Le bouton vers la page « Soutenez-nous » n'apparaît plus ici : ce lien est
   désormais porté par le bouton « Partenaires & dons » du pied de page, et
   l'afficher aux deux endroits faisait doublon. Les autres sous-pages
   (Formulaires…) gardent leur bouton. */
if (!function_exists('lv_page_dons')) {
    function lv_page_dons(): ?array
    {
        static $cherchee = false, $memoire = null;
        if ($cherchee) return $memoire;
        $cherchee = true;
        $raccourcis = ['support-us', 'soutenez-nous', 'nous-soutenir', 'soutenir',
                       'faire-un-don', 'dons', 'donate', 'donations'];
        foreach (Pages::all() as $p) {
            if (empty($p['visible'])) continue;
            foreach (I18n::$langs as $lg) {
                $slug = strtolower(trim((string)($p['slug_' . $lg] ?? '')));
                if ($slug !== '' && in_array($slug, $raccourcis, true)) return $memoire = $p;
            }
        }
        return $memoire = null;
    }
}

$lvDons = lv_page_dons();
$lvCartes = [];
foreach (($children ?? []) as $c) {
    if (!$c['in_nav'] && !$c['visible']) continue;
    if ($lvDons && (int)$c['id'] === (int)$lvDons['id']) continue;
    $lvCartes[] = $c;
}
?>
<article class="section">
  <div class="wrap">
    <h1><?= e(f($page, 'title')) ?></h1>
    <div class="page-body">
      <div class="rich"><?= f($page, 'body') ?></div>
      <?php if ($lvCartes): ?>
      <div class="child-grid">
        <?php foreach ($lvCartes as $c): ?>
        <a class="child-card" href="<?= e(Pages::url($c)) ?>">
          <span><?= e(f($c, 'title')) ?></span>
          <span class="child-arrow">→</span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?= gallery_grid($gallery ?? []) ?>
    <?= docs_list($documents ?? []) ?>
  </div>
</article>

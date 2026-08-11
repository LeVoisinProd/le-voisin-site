<?php
/** Structure du site : arborescence des pages.   [V10-CMS-BILINGUE] */
require __DIR__ . '/_inc.php';
Auth::requireAdmin();

// Nouvelle page
if (isset($_GET['new'])) {
    $parent = (int)($_GET['parent'] ?? 0) ?: null;
    $sort = 1 + (int)DB::val('SELECT COALESCE(MAX(sort),0) FROM pages WHERE ' . ($parent ? 'parent_id = ' . $parent : 'parent_id IS NULL'));
    $id = DB::insert('pages', ['parent_id' => $parent, 'visible' => 0, 'in_nav' => 1, 'sort' => $sort]);
    redirect('/admin/page-edit.php?id=' . $id . '&new=1');
}

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    Auth::requireCsrf();
    $pg = Pages::byId((int)$_POST['id']);
    if ($pg && $pg['template'] === 'home') {
        flash(ta('pg_home_keep'), 'err');
    } else {
        Pages::delete((int)$_POST['id']);
        flash(ta('pg_deleted'));
    }
    redirect('/admin/pages.php');
}

// Bascule visibilité rapide
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'togglevis') {
    Auth::requireCsrf();
    DB::run('UPDATE pages SET visible = 1 - visible WHERE id = ?', [(int)$_POST['id']]);
    redirect('/admin/pages.php');
}

function page_branch(?int $parentId): void
{
    $children = Pages::children($parentId);
    ?>
    <div class="pages-branch js-pages-branch" data-parent="<?= (int)$parentId ?>">
      <?php foreach ($children as $p): ?>
      <div class="page-node" data-id="<?= (int)$p['id'] ?>">
        <div class="page-row">
          <span class="vid-drag" title="<?= e(ta('pg_move')) ?>">⋮⋮</span>
          <a class="page-title" href="<?= e(admin_url('page-edit.php?id=' . $p['id'])) ?>">
            <?= e(fa($p, 'title') ?: ta('com_untitled')) ?>
          </a>
          <?php if ($p['template'] === 'home'): ?><span class="badge mod"><?= e(ta('pg_home')) ?></span><?php endif; ?>
          <?php if ($p['module']): ?><span class="badge mod"><?= e(tc(Pages::MODULES[$p['module']] ?? $p['module'])) ?></span><?php endif; ?>
          <?php if (!$p['in_nav']): ?><span class="badge"><?= e(ta('pg_offmenu')) ?></span><?php endif; ?>
          <form method="post" class="inline-form">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="togglevis"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="badge-btn <?= $p['visible'] ? 'pub' : 'draft' ?>" title="<?= e(ta('pg_toggle')) ?>">
              <?= e($p['visible'] ? ta('com_online') : ta('com_offline')) ?></button>
          </form>
          <a class="icon-btn" href="<?= e(admin_url('pages.php?new=1&parent=' . $p['id'])) ?>" title="<?= e(ta('pg_addsub')) ?>">+</a>
          <a class="icon-btn" href="<?= e(Pages::url($p, I18n::$default)) ?>" target="_blank" title="<?= e(ta('pg_viewpage')) ?>"><?= Ico::ext() ?></a>
          <?php if ($p['template'] !== 'home'): ?>
          <form method="post" class="inline-form js-confirm" data-confirm="<?= e(ta('pg_del_confirm', fa($p, 'title') ?: ta('com_untitled'))) ?>">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="icon-btn danger" type="submit" title="<?= e(ta('com_delete')) ?>">×</button>
          </form>
          <?php endif; ?>
        </div>
        <?php page_branch((int)$p['id']); ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php
}

admin_top(ta('nav_pages'), 'pages');
?>
<div class="page-head">
  <h1><?= e(ta('nav_pages')) ?></h1>
  <a class="btn" href="<?= e(admin_url('pages.php?new=1')) ?>"><?= e(ta('pg_new')) ?></a>
</div>
<div class="panel">
  <p class="hint"><?= e(ta('pg_hint')) ?></p>
  <?php page_branch(null); ?>
</div>
<?php admin_bottom(); ?>

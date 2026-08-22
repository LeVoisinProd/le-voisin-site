<?php
/** Liste d'un module.   [V10-CMS-BILINGUE] [V14-DUPLIQUER] */
require __DIR__ . '/_inc.php';
Auth::requireAdmin(false, 'entite:' . (string)($_GET['e'] ?? ''));

$entity = (string)($_GET['e'] ?? '');
$def = Content::def($entity);

// Création rapide (bouton « + Nouveau »)
if (isset($_GET['new'])) {
    $id = Content::createDraft($entity);
    redirect('/admin/edit.php?e=' . $entity . '&id=' . $id . '&new=1');
}

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    Auth::requireCsrf();
    Content::delete($entity, (int)$_POST['id']);
    flash(ta('lst_deleted', tc($def['label'])));
    redirect('/admin/list.php?e=' . $entity);
}

// Duplication (copie non publiée) — modules marqués 'duplicable' dans la config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'duplicate') {
    Auth::requireCsrf();
    if (empty($def['duplicable'])) redirect('/admin/list.php?e=' . $entity);
    $newId = Content::duplicate($entity, (int)$_POST['id']);
    flash(dup_message($def));
    redirect('/admin/edit.php?e=' . $entity . '&id=' . $newId);
}

$rows = Content::listAll($entity);
$listDef = $def['list'];

admin_top(tc($def['plural']), 'e:' . $entity);
?>
<div class="page-head">
  <h1><?= e(tc($def['plural'])) ?></h1>
  <a class="btn" href="<?= e(admin_url('list.php?e=' . $entity . '&new=1')) ?>"><?= e(ta('com_new')) ?></a>
</div>

<?php if (!$rows): ?>
<div class="panel"><p class="hint"><?= e(ta('lst_empty')) ?></p></div>
<?php else: ?>
<div class="panel">
  <div class="rowlist<?= ($def['sortable'] ?? false) ? ' js-sortable' : '' ?>" data-table="<?= e($def['table']) ?>">
    <?php foreach ($rows as $r):
        $title = '';
        if (isset($listDef['title'])) {
            $col = $listDef['title'];
            $title = isset($r[$col]) ? (string)$r[$col] : html_entity_decode(fa($r, $col));
            if ($title === '' && isset($r[$col . '_en'])) $title = html_entity_decode(fa($r, $col));
        }
        if ($entity === 'team') $title = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        if ($title === '') $title = ta('com_untitled');
        $img = null;
        if (!empty($listDef['cover']) && !empty($r[$listDef['cover']])) $img = Img::row((int)$r[$listDef['cover']]);
        $extra = '';
        if (($listDef['extra'] ?? '') === 'categories') {
            $cats = Content::related('category', $def['fields']['categories'], (int)$r['id'], false);
            $extra = implode(', ', array_map(fn($c) => fa($c, 'name'), $cats));
        } elseif (($listDef['extra'] ?? '') === 'event_info') {
            $bits = [];
            $bits[] = fa($r, 'date_text') ?: ($r['date_text_fr'] ?: $r['date_text_en']);
            if ($r['artist_id']) $bits[] = (string)DB::val('SELECT name FROM artists WHERE id=?', [$r['artist_id']]);
            if ($r['project_id']) { $p = DB::one('SELECT title_en,title_fr FROM projects WHERE id=?', [$r['project_id']]); if ($p) $bits[] = html_entity_decode(fa($p, 'title')); }
            $extra = implode(' · ', array_filter($bits));
            $title = $r['venue'] . ($r['city'] ? ' — ' . $r['city'] : '');
        } elseif (($listDef['extra'] ?? '') === 'team_info') {
            $extra = fa($r, 'role');
        }
    ?>
    <div class="rowitem" data-id="<?= (int)$r['id'] ?>">
      <?php if ($def['sortable'] ?? false): ?><span class="vid-drag" title="<?= e(ta('lst_drag')) ?>">⋮⋮</span><?php endif; ?>
      <?php if (!empty($listDef['cover'])): ?>
      <span class="row-thumb"><?php if ($img) { Img::ensure($img, 'thumb'); ?><img src="<?= e(Img::fileUrl($img, 'thumb', 'jpg')) ?>" alt=""><?php } ?></span>
      <?php endif; ?>
      <a class="row-main" href="<?= e(admin_url('edit.php?e=' . $entity . '&id=' . $r['id'])) ?>">
        <strong><?= e($title) ?></strong>
        <?php if ($extra): ?><em><?= e($extra) ?></em><?php endif; ?>
      </a>
      <?php if (array_key_exists('visible', $r)): ?>
      <span class="badge <?= $r['visible'] ? 'pub' : 'draft' ?>"><?= e($r['visible'] ? ta('com_online') : ta('com_offline')) ?></span>
      <?php endif; ?>
      <?php if (!empty($def['duplicable'])): ?>
      <form method="post" title="<?= e(ta('com_duplicate')) ?>">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="duplicate">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <button class="icon-btn" type="submit" title="<?= e(ta('lst_dup_title', $title)) ?>">⧉</button>
      </form>
      <?php endif; ?>
      <form method="post" class="js-confirm" data-confirm="<?= e(ta('lst_del_confirm', $title)) ?>">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <button class="icon-btn danger" type="submit" title="<?= e(ta('com_delete')) ?>">×</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if ($def['sortable'] ?? false): ?><p class="hint"><?= e(ta('lst_sort_hint')) ?></p><?php endif; ?>
</div>
<?php endif; ?>
<?php admin_bottom(); ?>

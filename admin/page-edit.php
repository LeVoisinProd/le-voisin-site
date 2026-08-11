<?php
/** Modification d'une page du site.   [V10-CMS-BILINGUE] */
require __DIR__ . '/_inc.php';
Auth::requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$row = Pages::byId($id);
if (!$row) { flash(ta('pge_notfound'), 'err'); redirect('/admin/pages.php'); }
$isHome = $row['template'] === 'home';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    $langs = I18n::$langs;
    $data = [];
    $filled = false;
    foreach ($langs as $lg) {
        $data['title_' . $lg] = trim((string)($_POST['title_' . $lg] ?? ''));
        if ($data['title_' . $lg] !== '') $filled = true;
        $data['body_' . $lg] = (string)($_POST['body_' . $lg] ?? '');
        $data['meta_title_' . $lg] = trim((string)($_POST['meta_title_' . $lg] ?? ''));
        $data['meta_desc_' . $lg] = trim((string)($_POST['meta_desc_' . $lg] ?? ''));
    }
    if (!$filled) $errors[] = ta('pge_title_req');

    if (!$isHome) {
        foreach ($langs as $lg) {
            $slug = trim((string)($_POST['slug_' . $lg] ?? ''));
            if ($slug === '') $slug = $data['title_' . $lg] !== '' ? $data['title_' . $lg] : ($data['title_' . I18n::$default] ?? '');
            $slug = slugify($slug);
            $parentId = (int)($_POST['parent_id'] ?? 0) ?: null;
            $col = 'slug_' . $lg;
            $exists = DB::val(
                "SELECT id FROM pages WHERE `$col` = ? AND id <> ? AND " . ($parentId ? 'parent_id = ' . $parentId : 'parent_id IS NULL'),
                [$slug, $id]
            );
            if ($exists) $slug .= '-' . $id;
            $data[$col] = $slug;
        }
        $parentId = (int)($_POST['parent_id'] ?? 0) ?: null;
        if ($parentId === $id || in_array($id, $parentId ? [$parentId] : [], true)
            || ($parentId && in_array($parentId, Pages::descendantIds($id), true))) {
            $parentId = $row['parent_id'] ? (int)$row['parent_id'] : null;
        }
        $data['parent_id'] = $parentId;
        $data['module'] = in_array((string)($_POST['module'] ?? ''), array_keys(Pages::MODULES), true) && $_POST['module'] !== ''
            ? (string)$_POST['module'] : null;
        $data['in_nav'] = empty($_POST['in_nav']) ? 0 : 1;
        $data['visible'] = empty($_POST['visible']) ? 0 : 1;
    }
    $og = (int)($_POST['og_image_id'] ?? 0);
    $data['og_image_id'] = $og > 0 ? $og : null;

    if (!$errors) {
        DB::update('pages', $data, 'id = ?', [$id]);
        Pages::reset();
        flash(ta('pge_saved'));
        redirect('/admin/page-edit.php?id=' . $id);
    }
    $row = array_merge($row, $_POST);
}

admin_top(ta('pge_page') . ' — ' . (fa($row, 'title') ?: ta('pge_new_short')), 'pages');
?>
<div class="page-head">
  <h1><?= e(ta('pge_page')) ?> <span class="crumb">→ <?= e(fa($row, 'title') ?: ta('pge_new')) ?></span></h1>
  <a class="btn ghost" href="<?= e(admin_url('pages.php')) ?>"><?= e(ta('pge_all')) ?></a>
</div>

<?php if ($errors): ?>
<div class="flash err"><?php foreach ($errors as $er) echo e($er) . '<br>'; ?></div>
<?php endif; ?>

<form method="post" class="editform js-dirty">
  <?= Auth::csrfField() ?>
  <div class="editgrid">
    <div class="editmain panel">
      <?= render_field('title', ['type' => 'i18n_text', 'label' => ta('pge_f_title'), 'required' => true], $row, 'page') ?>
      <?php if (!$isHome): ?>
      <?= render_field('slug', ['type' => 'i18n_slug', 'from' => 'title', 'label' => ta('pge_f_slug')], $row, 'page') ?>
      <?php endif; ?>
      <?= render_field('body', ['type' => 'i18n_html', 'label' => ta('pge_f_body')], $row, 'page') ?>
      <?php if ($isHome): ?>
      <?= render_field('videos', ['type' => 'videos', 'label' => ta('pge_f_videos'),
          'help' => ta('pge_f_videos_help')], $row, 'page') ?>
      <?php endif; ?>
      <?= render_field('gallery', ['type' => 'gallery', 'zone' => 'gallery', 'label' => ta('pge_f_gallery')], $row, 'page') ?>
      <?= render_field('documents', ['type' => 'documents', 'label' => ta('pge_f_documents')], $row, 'page') ?>
    </div>
    <div class="editside">
      <div class="panel">
        <?php if (!$isHome): ?>
        <div class="f"><label class="f-label"><?= e(ta('pge_parent')) ?></label>
          <select name="parent_id">
            <option value=""><?= e(ta('pge_root')) ?></option>
            <?php
            $excl = array_merge([$id], Pages::descendantIds($id));
            $walk = function (?int $pid, int $depth) use (&$walk, $excl, $row) {
                foreach (Pages::children($pid) as $p) {
                    if (in_array((int)$p['id'], $excl, true) || $p['template'] === 'home') continue;
                    echo '<option value="' . (int)$p['id'] . '"' . ((int)($row['parent_id'] ?? 0) === (int)$p['id'] ? ' selected' : '') . '>'
                        . str_repeat('— ', $depth) . e(fa($p, 'title') ?: ta('com_untitled')) . '</option>';
                    $walk((int)$p['id'], $depth + 1);
                }
            };
            $walk(null, 0);
            ?>
          </select></div>
        <div class="f"><label class="f-label"><?= e(ta('pge_module')) ?></label>
          <select name="module">
            <?php foreach (Pages::MODULES as $k => $lbl): ?>
            <option value="<?= e($k) ?>"<?= (string)($row['module'] ?? '') === $k ? ' selected' : '' ?>><?= e(tc($lbl)) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="f-help"><?= e(ta('pge_module_help')) ?></p></div>
        <?= render_field('in_nav', ['type' => 'toggle', 'label' => ta('pge_in_nav')], $row, 'page') ?>
        <?= render_field('visible', ['type' => 'toggle', 'label' => ta('com_online')], $row, 'page') ?>
        <?php else: ?>
        <p class="hint"><?= e(ta('pge_is_home')) ?></p>
        <?php endif; ?>
        <?php /* [V30-VOIR-LA-PAGE] « Voir la page » existait déjà ici, mais en
                 petit lien discret sous le bouton d'enregistrement — on ne le
                 voyait pas. Il passe au-dessus, en bouton pleine largeur, et
                 se présente exactement comme sur les fiches projet, artiste et
                 agenda, qui viennent d'en recevoir un. */ ?>
        <?php /* [V31-ESPACE-VOIR] Même enveloppe que sur les fiches : voir
                 admin/edit.php. */ ?>
        <div class="ed-voir">
          <a class="btn wide ghost" href="<?= e(Pages::url($row, I18n::$default)) ?>" target="_blank" rel="noopener"><?= e(ta('pge_view')) ?> <?= Ico::ext() ?></a>
          <?php if (($row['template'] ?? '') !== 'home' && empty($row['visible'])): ?>
          <p class="hint"><?= e(ta('ed_view_page_hidden')) ?></p>
          <?php endif; ?>
          <p class="hint" id="viewHint" hidden><?= e(ta('ed_view_page_dirty')) ?></p>
        </div>
        <button class="btn wide" type="submit"><?= e(ta('com_save')) ?></button>
      </div>
      <div class="panel">
        <?= render_field('seo', ['type' => 'seo', 'label' => ta('pge_seo')], $row, 'page') ?>
      </div>
    </div>
  </div>
</form>
<?php admin_bottom(['linkList' => admin_link_list()]); ?>

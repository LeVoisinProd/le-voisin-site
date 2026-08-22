<?php
/** Tableau de bord de l'administration.   [V10-CMS-BILINGUE] */
require __DIR__ . '/_inc.php';
Auth::requireAdmin(false, 'dash');

$counts = [
    'dash_pages'    => (int)DB::val('SELECT COUNT(*) FROM pages'),
    'dash_projects' => (int)DB::val('SELECT COUNT(*) FROM projects'),
    'dash_artists'  => (int)DB::val('SELECT COUNT(*) FROM artists'),
    'dash_events'   => (int)DB::val('SELECT COUNT(*) FROM events'),
    'dash_team'     => (int)DB::val('SELECT COUNT(*) FROM team_members'),
    'dash_images'   => (int)DB::val('SELECT COUNT(*) FROM images'),
];
$warnRecipients = Settings::emails('form_infos_to') === [] || Settings::emails('form_expenses_to') === [];
$upcoming = DB::all("SELECT * FROM events WHERE visible=1 AND COALESCE(date_end, date_sort) >= CURDATE() ORDER BY date_sort LIMIT 5");

/* [V36-FACTURES] Les factures reçues et pas encore payées. Le courriel de
   dépôt prévient déjà, mais un courriel se lit une fois et se perd : cette
   liste est ce qui reste quand on n'a pas répondu tout de suite. Elle
   disparaît d'elle-même dès qu'il n'y a plus rien à payer, et n'apparaît
   jamais tant que la base n'a pas été mise à jour. */
$factures = MemberDocs::facturesEnAttente();

admin_top(ta('nav_dash'), 'dash');
?>
<div class="page-head"><h1><?= e(ta('nav_dash')) ?></h1></div>

<?php if ($warnRecipients): ?>
<div class="flash warn"><?= e(ta('dash_warn')) ?>
  <a href="<?= e(admin_url('settings.php#forms')) ?>"><?= e(ta('dash_warn_link')) ?></a>.</div>
<?php endif; ?>

<div class="stats">
  <?php foreach ($counts as $key => $n): ?>
  <div class="stat"><strong><?= $n ?></strong><span><?= e(ta($key)) ?></span></div>
  <?php endforeach; ?>
</div>

<?php if ($factures): ?>
<section class="panel">
  <h2><?= e(ta('dash_inv_h', (string)count($factures))) ?></h2>
  <p class="hint"><?= e(ta('dash_inv_i')) ?></p>
  <table class="tbl"><tbody>
    <?php foreach ($factures as $fa): ?>
    <tr>
      <td><strong><?= e((string)($fa['personne'] ?: $fa['courriel'])) ?></strong><br>
        <span class="muted"><?= e((string)($fa['title'] ?: $fa['filename'])) ?><?php
          $fAsso = trim((string)($fa['assoc'] ?? ''));
          if ($fAsso !== '') echo ' · ' . e($fAsso);
        ?></span></td>
      <td class="muted" style="white-space:nowrap"><?= e(Dates::afficher((string)($fa['status_at'] ?? ''))) ?></td>
      <td style="text-align:right; white-space:nowrap">
        <a class="btn small" href="<?= e(admin_url('collaborator-edit.php?id=' . (int)$fa['collaborator_id'])) ?>"><?= e(ta('dash_inv_go')) ?></a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody></table>
</section>
<?php endif; ?>

<div class="cols">
  <section class="panel">
    <h2><?= e(ta('dash_shortcuts')) ?></h2>
    <p class="quick">
      <a class="btn small" href="<?= e(admin_url('list.php?e=project&new=1')) ?>"><?= e(ta('dash_new_project')) ?></a>
      <a class="btn small" href="<?= e(admin_url('list.php?e=artist&new=1')) ?>"><?= e(ta('dash_new_artist')) ?></a>
      <a class="btn small" href="<?= e(admin_url('list.php?e=event&new=1')) ?>"><?= e(ta('dash_new_event')) ?></a>
      <a class="btn small ghost" href="<?= e(admin_url('pages.php')) ?>"><?= e(ta('dash_manage_pages')) ?></a>
    </p>
    <h2><?= e(ta('dash_upcoming')) ?></h2>
    <?php if (!$upcoming): ?><p class="hint"><?= e(ta('dash_no_upcoming')) ?></p><?php endif; ?>
    <ul class="plain">
      <?php foreach ($upcoming as $ev): ?>
      <li><a href="<?= e(admin_url('edit.php?e=event&id=' . $ev['id'])) ?>">
        <strong><?= e(fa($ev, 'date_text') ?: ($ev['date_text_fr'] ?: $ev['date_text_en'])) ?></strong> — <?= e($ev['venue']) ?><?= $ev['city'] ? ', ' . e($ev['city']) : '' ?>
      </a></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <section class="panel">
    <h2><?= ta('dash_how') ?></h2>
    <ul class="help-list">
      <li><?= ta('dash_help_1') ?></li>
      <li><?= ta('dash_help_2') ?></li>
      <li><?= ta('dash_help_3') ?></li>
      <li><?= ta('dash_help_4') ?></li>
      <li><?= ta('dash_help_5') ?></li>
    </ul>
  </section>
</div>
<?php admin_bottom(); ?>

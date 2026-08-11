<?php
/**
 * Journal des accès : qui a téléchargé quel document, qui a ouvert l'espace
 * de qui, et quand.                                              [V39-JOURNAL]
 *
 * Lecture seule — rien ne s'écrit depuis cette page. Les 200 lignes les plus
 * récentes ; le journal garde 6 mois d'historique, purgés automatiquement.
 */
require __DIR__ . '/_inc.php';
Auth::requireAdmin();

$cid = isset($_GET['c']) ? (int)$_GET['c'] : null;
$collab = $cid ? DB::one('SELECT id, name FROM collaborators WHERE id = ?', [$cid]) : null;
if ($cid && !$collab) { http_response_code(404); exit; }

$lignes = AccessLog::recentes(200, $collab ? (int)$collab['id'] : null);

/** Le texte d'une action, dans la langue de l'administration. */
function al_action_txt(string $a): string
{
    return match ($a) {
        'login'    => ta('al_action_login'),
        'download' => ta('al_action_download'),
        'visite'   => ta('al_action_visite'),
        default    => $a,
    };
}

admin_top(ta('nav_access_log'), 'access_log');
?>
<div class="page-head"><h1><?= e(ta('nav_access_log')) ?></h1></div>

<div class="panel">
  <p class="hint"><?= e(ta('al_intro')) ?></p>
  <?php if ($collab): ?>
  <p class="hint"><strong><?= e(ta('al_filtered', $collab['name'])) ?></strong>
    — <a href="<?= e(admin_url('access-log.php')) ?>"><?= e(ta('al_clear_filter')) ?></a></p>
  <?php endif; ?>

  <?php if (!$lignes): ?>
  <p class="hint"><?= e(ta('al_none')) ?></p>
  <?php else: ?>
  <table class="tbl">
    <thead><tr>
      <th><?= e(ta('al_th_when')) ?></th>
      <th><?= e(ta('al_th_collab')) ?></th>
      <th><?= e(ta('al_th_action')) ?></th>
      <th><?= e(ta('al_th_detail')) ?></th>
      <th><?= e(ta('al_th_who')) ?></th>
      <th><?= e(ta('al_th_ip')) ?></th>
    </tr></thead>
    <tbody>
      <?php foreach ($lignes as $l): ?>
      <tr>
        <td><?= e(Dates::afficherHeure((string)$l['at'])) ?></td>
        <td><a href="<?= e(admin_url('access-log.php?c=' . (int)$l['collaborator_id'])) ?>"><?= e($l['collab_name']) ?></a></td>
        <td><span class="badge<?= $l['actor'] === 'admin' ? ' warn' : '' ?>"><?= e(al_action_txt((string)$l['action'])) ?></span></td>
        <td><?= e((string)$l['detail']) ?></td>
        <td><?= $l['actor'] === 'admin'
              ? e(ta('al_actor_admin', $l['admin_name'] ?: ($l['admin_email'] ?: '?')))
              : e(ta('al_actor_member')) ?></td>
        <td class="muted"><?= e((string)$l['ip']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php admin_bottom();

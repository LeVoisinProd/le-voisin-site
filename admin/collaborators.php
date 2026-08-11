<?php
/** Collaborateurs et documents de l'espace privé.   [V12-ESPACE] [V27-ACCES] */
require __DIR__ . '/_inc.php';
Auth::requireAdmin();

$errors = [];
$aConfirmer = [];     // les personnes à qui l'on s'apprête à écrire   [V28-INVIT]
$rapport    = null;   // le compte rendu du dernier envoi groupé

/* Le compte rendu est déposé en session juste avant une redirection, puis relu
 * ici. Sans cette redirection, actualiser la page après un envoi renverrait le
 * formulaire : toute l'équipe recevrait un second message, et les liens du
 * premier cesseraient de fonctionner. */
if (!empty($_SESSION['lv_invit_rapport'])) {
    $rapport = $_SESSION['lv_invit_rapport'];
    unset($_SESSION['lv_invit_rapport']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['lv_action'] ?? ''), ['confirmer', 'envoyer'], true)) {
    Auth::requireCsrf();
    $ids  = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
    $gens = $ids
        ? DB::all('SELECT * FROM collaborators WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
                . ' ORDER BY name, id', $ids)
        : [];
    // On vérifie qu'un envoi est possible AVANT de toucher aux liens : chaque
    // lien neuf annule le précédent, il serait fâcheux de les annuler tous pour
    // découvrir ensuite qu'aucun message ne peut partir.
    $obstacle = Invitations::obstacle();

    if (!$gens)          $errors[] = ta('inv_none_sel');
    elseif ($obstacle)   $errors[] = $obstacle;
    elseif ($_POST['lv_action'] === 'confirmer') {
        $aConfirmer = $gens;
    } else {
        $res = [];
        foreach ($gens as $g) $res[] = Invitations::envoyer($g);
        $_SESSION['lv_invit_rapport'] = $res;
        redirect('/admin/collaborators.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['lv_action'])) {
    Auth::requireCsrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    if ($name === '') $errors[] = ta('col_name_req');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = ta('col_bad_email');
    if (!$errors && DB::val('SELECT id FROM collaborators WHERE email = ?', [$email])) $errors[] = ta('col_email_exists');
    if (!$errors) {
        /* [V27-ACCES] On ne demande plus de mot de passe ici.
         *
         * Le formulaire en inscrivait un au hasard, refabriqué à chaque
         * affichage de la page : il fallait le noter avant d'enregistrer,
         * sans que rien ne le dise, et il changeait sous les yeux de qui
         * revenait sur la page. D'où l'impression, parfaitement fondée, que
         * « le mot de passe change tout seul ».
         *
         * Le compte naît donc sans mot de passe, et l'on prépare aussitôt le
         * lien que la personne ouvrira pour choisir le sien. Le bureau n'a
         * plus rien à inventer, à noter, ni à transmettre de secret. */
        $id = DB::insert('collaborators', [
            'name' => $name, 'email' => $email, 'lang' => 'fr',
            'pass_hash' => '', 'active' => 1,
        ]);
        $avecLien = true;
        try {
            MemberAuth::lienNouveau($id);
        } catch (Throwable $e) {
            // Les colonnes du lien n'existent qu'après « Mettre à jour la
            // base » : le compte est créé quand même, on le dit franchement.
            $avecLien = false;
        }
        flash(ta($avecLien ? 'col_created' : 'col_created_nolink'));
        redirect('/admin/collaborator-edit.php?id=' . $id);
    }
}

$list = DB::all('SELECT * FROM collaborators ORDER BY name, id');
$pending = DB::all(
    "SELECT d.*, c.name AS cname FROM member_documents d
     JOIN collaborators c ON c.id = d.collaborator_id
     WHERE d.needs_signature = 1 AND d.sign_status <> 'signed' ORDER BY d.created_at"
);

/**
 * Où en est l'accès de cette personne.   [V27-ACCES]
 *
 * Renvoie [classe de pastille, texte]. L'ordre des questions est celui du
 * bon sens : une connexion réussie prouve tout le reste, donc on la regarde
 * en premier. Un compte désactivé passe avant tout : le mot de passe peut
 * être parfait, l'entrée est fermée.
 */
function lv_acces(array $c): array
{
    if (empty($c['active']))                    return ['off',  ta('col_acc_off')];
    if (!empty($c['last_login']))               return ['ok',   ta('col_acc_ok', Dates::afficherHeure((string)$c['last_login']))];
    if (trim((string)($c['pass_hash'] ?? '')) !== '') return ['', ta('col_acc_ready')];
    if (!empty($c['reset_token']) && !empty($c['reset_expires'])
        && strtotime((string)$c['reset_expires']) > time())  return ['warn', ta('col_acc_link')];
    return ['warn', ta('col_acc_none')];
}

/** Le nom de la langue d'écriture d'une personne.   [V28-INVIT] */
function lv_langue_nom(?string $l): string
{
    return Invitations::langue($l) === 'en' ? 'English' : 'Français';
}

/* ---- Écran de confirmation ------------------------------------------------
 *
 * Un envoi groupé ne se rattrape pas : le message est parti, et le lien
 * précédent de chacun ne fonctionne plus. On montre donc d'abord la liste
 * exacte des destinataires, avec l'adresse et la langue de chacun, et rien
 * d'autre sur la page — pas de tableau à côté où l'on croirait pouvoir encore
 * cocher quelqu'un. */
if ($aConfirmer):
    admin_top(ta('nav_collab'), 'collab');
    ?>
<div class="page-head"><h1><?= e(ta('inv_conf_head')) ?></h1></div>
<div class="panel">
  <p class="hint"><?= e(ta('inv_conf_intro', count($aConfirmer))) ?></p>
  <table class="tbl">
    <thead><tr><th><?= e(ta('col_th_name')) ?></th><th><?= e(ta('col_th_email')) ?></th><th><?= e(ta('inv_th_lang')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($aConfirmer as $g): ?>
      <tr>
        <td><strong><?= e($g['name']) ?></strong></td>
        <td><?= e($g['email']) ?></td>
        <td><?= e(lv_langue_nom($g['lang'] ?? '')) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="hint"><?= e(ta('inv_conf_w1', MemberAuth::LIEN_JOURS)) ?></p>
  <p class="hint"><?= e(ta('inv_conf_w2')) ?></p>
  <form method="post" style="margin-top:18px;">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="lv_action" value="envoyer">
    <?php foreach ($aConfirmer as $g): ?>
    <input type="hidden" name="ids[]" value="<?= (int)$g['id'] ?>">
    <?php endforeach; ?>
    <button class="btn" type="submit"><?= e(ta('inv_conf_go', count($aConfirmer))) ?></button>
    <a class="btn ghost" href="<?= e(admin_url('collaborators.php')) ?>"><?= e(ta('inv_conf_cancel')) ?></a>
  </form>
</div>
<?php
    admin_bottom();
    exit;
endif;

admin_top(ta('nav_collab'), 'collab');
?>
<div class="page-head"><h1><?= e(ta('nav_collab')) ?></h1></div>

<?php if ($errors): ?><div class="flash err"><?php foreach ($errors as $er) echo e($er) . '<br>'; ?></div><?php endif; ?>

<?php /* ---- Compte rendu du dernier envoi groupé ----   [V28-INVIT]
         Ligne par ligne, avec la raison exacte des échecs : un envoi qui
         « n'a pas marché » sans plus de détail ne se répare pas. */ ?>
<?php if ($rapport):
    $nOk = count(array_filter($rapport, fn($r) => $r['ok']));
    $nKo = count($rapport) - $nOk; ?>
<div class="panel">
  <h2><?= e(ta('inv_rap_head')) ?></h2>
  <p class="hint"><?= e($nKo ? ta('inv_rap_mixed', $nOk, $nKo) : ta('inv_rap_all', $nOk)) ?></p>
  <table class="tbl">
    <tbody>
      <?php foreach ($rapport as $r): ?>
      <tr>
        <td><strong><?= e($r['nom']) ?></strong></td>
        <td><?= e($r['email']) ?></td>
        <td><span class="badge<?= $r['ok'] ? ' ok' : ' warn' ?>"><?= e($r['ok'] ? ta('inv_rap_sent') : ta('inv_rap_failed')) ?></span></td>
        <td><?= $r['ok'] ? '' : e($r['raison']) ?></td>
        <td style="text-align:right"><a class="btn small ghost" href="<?= e(admin_url('collaborator-edit.php?id=' . (int)$r['id'])) ?>"><?= e(ta('com_open')) ?></a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($nKo): ?><p class="hint"><?= e(ta('inv_rap_help')) ?></p><?php endif; ?>
</div>
<?php endif; ?>

<?php /* [V27-ACCES] Trois phrases avant tout le reste. Le système de mots de
         passe n'est pas compliqué, il est seulement contre-intuitif : on ne
         donne pas un mot de passe, on donne le droit d'en choisir un. Tant
         que cela n'est écrit nulle part, on le cherche là où il n'est pas. */ ?>
<div class="panel">
  <h2><?= e(ta('col_how_head')) ?></h2>
  <p class="hint"><?= e(ta('col_how_1')) ?></p>
  <p class="hint"><?= e(ta('col_how_2')) ?></p>
  <p class="hint"><?= e(ta('col_how_3')) ?></p>
</div>

<div class="panel">
  <h2><?= e(ta('col_add')) ?></h2>
  <p class="hint"><?= e(ta('col_add_help')) ?></p>
  <form method="post">
    <?= Auth::csrfField() ?>
    <div class="grid2">
      <?= field_wrap(ta('col_fullname'), '<input type="text" name="name" required>', '', true) ?>
      <?= field_wrap(ta('col_email'), '<input type="email" name="email" required>', ta('col_email_help'), true) ?>
    </div>
    <p><button class="btn" type="submit"><?= e(ta('col_create')) ?></button></p>
  </form>
</div>

<div class="panel">
  <h2><?= e(ta('col_accounts', count($list))) ?></h2>
  <?php if (!$list): ?><p class="hint"><?= e(ta('col_none')) ?></p><?php else: ?>
  <?php /* [V28-INVIT] Cocher, puis « Envoyer les accès » : chacun reçoit son
           propre lien, dans sa langue. Rien ne part de cet écran — le bouton
           mène d'abord à la liste des destinataires. */ ?>
  <form method="post" id="lv-envoi">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="lv_action" value="confirmer">
  <table class="tbl">
    <thead><tr>
      <th style="width:1%"><input type="checkbox" id="lv-tout" title="<?= e(ta('inv_pick_all')) ?>"></th>
      <th><?= e(ta('col_th_name')) ?></th><th><?= e(ta('col_th_email')) ?></th><th><?= e(ta('col_th_access')) ?></th><th><?= e(ta('col_th_docs')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php foreach ($list as $c):
          $nd = (int)DB::val('SELECT COUNT(*) FROM member_documents WHERE collaborator_id = ?', [$c['id']]);
          $ns = (int)DB::val("SELECT COUNT(*) FROM member_documents WHERE collaborator_id = ? AND needs_signature = 1 AND sign_status <> 'signed'", [$c['id']]);
          [$cl, $txt] = lv_acces($c); ?>
      <tr>
        <?php /* Un compte désactivé n'a pas de case : lui écrire enverrait un
                 lien qui ne peut pas ouvrir la porte. */ ?>
        <td><?php if (!empty($c['active'])): ?><input type="checkbox" class="lv-pick" name="ids[]" value="<?= (int)$c['id'] ?>"><?php endif; ?></td>
        <td><strong><?= e($c['name']) ?></strong></td>
        <td><?= e($c['email']) ?></td>
        <?php /* [V27-ACCES] La colonne « Actif » disait oui ou non ; elle ne
                 disait pas si la personne pouvait entrer. Celle-ci le dit. */ ?>
        <td><span class="badge acces<?= $cl !== '' ? ' ' . $cl : '' ?>"><?= e($txt) ?></span></td>
        <td><?= $nd ?><?php if ($ns): ?> · <span class="badge warn"><?= e(ta('col_to_sign_n', $ns)) ?></span><?php endif; ?></td>
        <td style="text-align:right"><a class="btn small ghost" href="<?= e(admin_url('collaborator-edit.php?id=' . $c['id'])) ?>"><?= e(ta('com_open')) ?></a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
    <p style="margin:16px 0 4px;"><button class="btn" type="submit"><?= e(ta('inv_send_btn')) ?></button></p>
    <p class="hint"><?= e(ta('inv_send_help')) ?></p>
  </form>
  <script>
  (function () {
    var tout = document.getElementById('lv-tout');
    if (!tout) return;
    tout.addEventListener('change', function () {
      var cases = document.querySelectorAll('#lv-envoi .lv-pick');
      for (var i = 0; i < cases.length; i++) cases[i].checked = tout.checked;
    });
  })();
  </script>
  <?php endif; ?>
</div>

<div class="panel">
  <h2><?= e(ta('col_signatures')) ?></h2>
  <?php if (!$pending): ?>
  <p class="hint"><?= e(ta('col_sign_none')) ?></p>
  <?php else: ?>
  <p class="hint"><?= e(ta('col_sign_hint')) ?></p>
  <table class="tbl">
    <thead><tr><th><?= e(ta('col_th_collab')) ?></th><th><?= e(ta('col_th_doc')) ?></th><th><?= e(ta('col_th_status')) ?></th><th></th></tr></thead>
    <tbody>
      <?php foreach ($pending as $d): ?>
      <tr>
        <td><?= e($d['cname']) ?></td>
        <td><?= e($d['title'] ?: $d['filename']) ?></td>
        <td><span class="badge warn"><?= e(ta('col_to_sign')) ?></span></td>
        <td style="text-align:right"><a class="btn small ghost" href="<?= e(admin_url('collaborator-edit.php?id=' . $d['collaborator_id'])) ?>"><?= e(ta('com_view')) ?></a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php admin_bottom();

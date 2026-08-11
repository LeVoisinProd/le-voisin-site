<?php
/** Comptes d'accès à l'administration.   [V10-CMS-BILINGUE] */
require __DIR__ . '/_inc.php';
Auth::requireAdmin();
$me = Auth::user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $email = mb_strtolower(trim((string)$_POST['email']));
        $pass = (string)$_POST['password'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) flash(ta('usr_bad_email'), 'err');
        elseif (mb_strlen($pass) < 10) flash(ta('usr_short_pass'), 'err');
        elseif (DB::val('SELECT id FROM users WHERE email = ?', [$email])) flash(ta('usr_exists'), 'err');
        else {
            DB::insert('users', ['email' => $email, 'name' => trim((string)$_POST['name']),
                'pass_hash' => password_hash($pass, PASSWORD_DEFAULT)]);
            flash(ta('usr_created'));
        }
    }

    if ($action === 'password') {
        $uid = (int)$_POST['id'];
        $pass = (string)$_POST['password'];
        if (mb_strlen($pass) < 10) flash(ta('usr_short_pass'), 'err');
        else {
            DB::update('users', ['pass_hash' => password_hash($pass, PASSWORD_DEFAULT)], 'id = ?', [$uid]);
            flash(ta('usr_pass_changed'));
        }
    }

    if ($action === 'delete') {
        $uid = (int)$_POST['id'];
        $total = (int)DB::val('SELECT COUNT(*) FROM users');
        if ($uid === (int)$me['id']) flash(ta('usr_self_delete'), 'err');
        elseif ($total <= 1) flash(ta('usr_last_one'), 'err');
        else { DB::delete('users', 'id = ?', [$uid]); flash(ta('usr_deleted')); }
    }
    redirect('/admin/users.php');
}

$users = DB::all('SELECT * FROM users ORDER BY id');
admin_top(ta('nav_users'), 'users');
?>
<div class="page-head"><h1><?= e(ta('usr_title')) ?></h1></div>

<div class="panel">
  <div class="rowlist">
    <?php foreach ($users as $u): ?>
    <div class="rowitem">
      <span class="row-main"><strong><?= e($u['name'] ?: '—') ?></strong> <em><?= e($u['email']) ?></em>
        <em class="muted"><?= $u['last_login'] ? e(ta('usr_last_login', date('d.m.Y H:i', strtotime($u['last_login'])))) : e(ta('usr_never')) ?></em></span>
      <form method="post" class="inline-form userpass">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <input type="password" name="password" placeholder="<?= e(ta('usr_new_pass')) ?>" autocomplete="new-password">
        <button class="btn small ghost" type="submit"><?= e(ta('usr_change')) ?></button>
      </form>
      <?php if ((int)$u['id'] !== (int)$me['id']): ?>
      <form method="post" class="inline-form js-confirm" data-confirm="<?= e(ta('usr_del_confirm', $u['email'])) ?>">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <button class="icon-btn danger" type="submit" title="<?= e(ta('com_delete')) ?>">×</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <h2><?= e(ta('usr_add')) ?></h2>
  <form method="post" class="grid2">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="create">
    <?= field_wrap(ta('usr_name'), '<input type="text" name="name">') ?>
    <?= field_wrap(ta('usr_email'), '<input type="email" name="email" required>', '', true) ?>
    <?= field_wrap(ta('usr_password'), '<input type="password" name="password" required minlength="10" autocomplete="new-password">', ta('usr_pass_help'), true) ?>
    <div class="f"><label class="f-label">&nbsp;</label><button class="btn" type="submit"><?= e(ta('com_create')) ?></button></div>
  </form>
</div>
<?php admin_bottom(); ?>

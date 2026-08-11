<?php
/** Espace collaborateur — connexion.   [V12-ESPACE] */
require __DIR__ . '/_inc.php';
if (MemberAuth::check()) redirect('/espace/');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    MemberAuth::requireCsrf();
    if (MemberAuth::throttled()) {
        $error = t('member_throttled');
    } elseif (MemberAuth::login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
        redirect('/espace/');
    } else {
        $error = t('member_bad_login');
    }
}

espace_top(t('member_area'), false);
?>
<div class="espace-login">
  <h1><?= e(t('member_area')) ?></h1>
  <p class="muted"><?= e(t('member_login_intro')) ?></p>
  <?php if ($error): ?><div class="form-errors" role="alert"><p><?= e($error) ?></p></div><?php endif; ?>
  <form method="post" class="form">
    <?= MemberAuth::csrfField() ?>
    <div class="field"><label for="email"><?= e(t('member_email')) ?></label>
      <input type="email" id="email" name="email" required autofocus autocomplete="username"></div>
    <div class="field"><label for="password"><?= e(t('member_password')) ?></label>
      <input type="password" id="password" name="password" required autocomplete="current-password"></div>
    <p><button class="btn big" type="submit"><?= e(t('member_login')) ?></button></p>
  </form>
  <p class="muted"><?= e(t('member_pw_forgot')) ?></p>
</div>
<?php espace_bottom();

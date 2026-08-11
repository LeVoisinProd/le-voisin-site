<?php
/** Connexion à l'administration.   [V11-LANGUE-CACHE] */
require __DIR__ . '/_inc.php';

if (Auth::check()) redirect('/admin/');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    if (Auth::throttled()) {
        $error = ta('log_throttled');
    } elseif (Auth::login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
        redirect('/admin/');
    } else {
        $error = ta('log_bad');
    }
}
?><!DOCTYPE html>
<html lang="<?= e(I18n::$admin) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(ta('log_title')) ?> — <?= e(ta('com_admin')) ?></title>
<link rel="stylesheet" href="<?= e(asset_url('/assets/css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('/admin/assets/admin.css')) ?>">
</head>
<body class="login-body">
<form class="login-box" method="post" action="<?= e(admin_url('login.php')) ?>">
  <p class="side-logo">LE&nbsp;VOISIN<span><?= e(ta('com_admin')) ?></span></p>
  <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
  <?= Auth::csrfField() ?>
  <div class="f"><label class="f-label"><?= e(ta('log_email')) ?></label>
    <input type="email" name="email" required autofocus autocomplete="username"></div>
  <div class="f"><label class="f-label"><?= e(ta('log_password')) ?></label>
    <input type="password" name="password" required autocomplete="current-password"></div>
  <button class="btn wide" type="submit"><?= e(ta('log_submit')) ?></button>
  <?= admin_lang_switch('login-lang') ?>
</form>
</body>
</html>

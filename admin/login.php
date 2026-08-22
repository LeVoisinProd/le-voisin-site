<?php
/** Connexion à l'administration.   [V11-LANGUE-CACHE] */
require __DIR__ . '/_inc.php';

/* ── APRÈS LA CONNEXION, CHACUN CHEZ SOI.  [22.08.2026] ─────────────────────
   Depuis que `/admin/` est réservé à la direction, y renvoyer tout le monde
   ferait atterrir un compte `production` sur une page de refus juste après avoir
   tapé son mot de passe. Il croirait que sa connexion a échoué. On l'emmène là
   où son travail se fait. */
function lv_apres_connexion(): string
{
    return Auth::role() === 'direction' ? '/admin/' : '/dashboard.php';
}

if (Auth::check()) redirect(lv_apres_connexion());

/* ── DEUX ÉTAPES QUAND LE DEUXIÈME FACTEUR EST POSÉ.  [22.08.2026] ──────────
   La première n'accorde rien: `Auth::login()` retient seulement qui vient de
   prouver son mot de passe, dans une clef de session qui n'ouvre aucune page.
   C'est ce formulaire-ci qui ouvre la session, et seulement contre un code.

   LE MESSAGE D'ERREUR EST LE MÊME dans les deux étapes. Dire « mot de passe
   juste, code faux » confirme à qui essaie qu'il tient la moitié — autant le
   lui écrire. */
$error = '';
$etape = Auth::attendCode() ? 'code' : 'mdp';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    if (Auth::throttled()) {
        $error = ta('log_throttled');
    } elseif (($_POST['code'] ?? '') !== '' || $etape === 'code') {
        if (Auth::loginCode((string)($_POST['code'] ?? ''))) {
            redirect(lv_apres_connexion());
        }
        $error = ta('log_bad');
        $etape = Auth::attendCode() ? 'code' : 'mdp';
    } elseif (Auth::login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
        if (Auth::attendCode()) { $etape = 'code'; }
        else { redirect(lv_apres_connexion()); }
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
  <?= admin_marque() ?>
  <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
  <?= Auth::csrfField() ?>
  <?php if ($etape === 'code'): ?>
    <?php /* `inputmode="numeric"` ouvre le pavé de chiffres sur un téléphone, et
         `autocomplete="one-time-code"` laisse le trousseau d'Apple proposer le
         code sans qu'on le recopie. */ ?>
    <p class="f-label" style="margin-bottom:14px">Code à six chiffres de votre application d’authentification.</p>
    <div class="f"><label class="f-label">Code</label>
      <input type="text" name="code" required autofocus inputmode="numeric" pattern="[0-9]*"
             maxlength="6" autocomplete="one-time-code"></div>
    <button class="btn wide" type="submit">Entrer</button>
  <?php else: ?>
    <div class="f"><label class="f-label"><?= e(ta('log_email')) ?></label>
      <input type="email" name="email" required autofocus autocomplete="username"></div>
    <div class="f"><label class="f-label"><?= e(ta('log_password')) ?></label>
      <input type="password" name="password" required autocomplete="current-password"></div>
    <button class="btn wide" type="submit"><?= e(ta('log_submit')) ?></button>
  <?php endif; ?>
  <?= admin_lang_switch('login-lang') ?>
</form>
</body>
</html>

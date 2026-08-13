<?php
/**
 * Espace collaborateur — choisir son mot de passe à partir d'un lien.
 * [V13-MOTDEPASSE]
 *
 * Cette page ne demande pas l'ancien mot de passe : elle n'est atteignable
 * qu'avec le jeton du lien, fabriqué depuis l'administration et valable
 * quelques jours. C'est la personne elle-même qui choisit son mot de passe ;
 * l'administration ne le voit à aucun moment.
 */
require __DIR__ . '/_inc.php';

$jeton = trim((string)($_POST['jeton'] ?? $_GET['jeton'] ?? ''));
$m     = MemberAuth::parJeton($jeton);

// Dès qu'on sait de qui il s'agit, la page parle sa langue.
if ($m && !empty($m['lang'])) I18n::setLang((string)$m['lang']);

$erreur = '';
$fait   = false;

if ($m && (int)$m['active'] === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    MemberAuth::requireCsrf();
    $p1 = (string)($_POST['password'] ?? '');
    $p2 = (string)($_POST['password2'] ?? '');
    if (mb_strlen($p1) < 12) $erreur = t('member_pw_short');
    elseif ($p1 !== $p2)     $erreur = t('member_pw_diff');
    else {
        MemberAuth::motDePasse((int)$m['id'], $p1);
        $fait = true;
    }
}

espace_top(t('member_pw_title'), false);
?>
<div class="espace-login">
  <h1><?= e(t('member_pw_title')) ?></h1>

<?php if ($fait): ?>

  <p><?= e(t('member_pw_done')) ?></p>
  <p><a class="btn big" href="<?= e(espace_url('login.php')) ?>"><?= e(t('member_login')) ?></a></p>

<?php elseif (!$m): ?>

  <p><?= e(t('member_pw_expired')) ?></p>
  <p class="muted"><?= e(t('member_pw_ask')) ?></p>

<?php elseif ((int)$m['active'] !== 1): ?>

  <p><?= e(t('member_pw_off')) ?></p>
  <p class="muted"><?= e(t('member_pw_ask')) ?></p>

<?php else: ?>

  <p class="muted"><?= e(t('member_pw_intro', $m['email'])) ?></p>
  <?php if ($erreur): ?><div class="form-errors" role="alert"><p><?= e($erreur) ?></p></div><?php endif; ?>
  <form method="post" class="form" autocomplete="off">
    <?= MemberAuth::csrfField() ?>
    <input type="hidden" name="jeton" value="<?= e($jeton) ?>">
    <div class="field"><label for="password"><?= e(t('member_pw_new')) ?></label>
      <input type="password" id="password" name="password" required minlength="8" autofocus autocomplete="new-password"></div>
    <div class="field"><label for="password2"><?= e(t('member_pw_again')) ?></label>
      <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password"></div>
    <p class="muted"><?= e(t('member_pw_help')) ?></p>
    <p><button class="btn big" type="submit"><?= e(t('member_pw_save')) ?></button></p>
  </form>

<?php endif; ?>
</div>
<?php espace_bottom();

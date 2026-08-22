<?php
/**
 * Mon compte: mon mot de passe et mon deuxième facteur.  [22.08.2026]
 *
 * IL NAÎT D'UN DÉFAUT QUE J'AI CRÉÉ LE MATIN MÊME. Le deuxième facteur
 * s'activait dans Paramètres, réservé à la direction; et depuis que `/admin/`
 * l'est aussi, un compte `production` ne pouvait NI poser son facteur NI changer
 * son mot de passe. On lui demandait de se protéger sans lui en donner le moyen.
 *
 * C'EST UN ÉCRAN DE PERSONNE, PAS DE RÉGLAGES. On n'y agit que sur soi. Les
 * rôles, les comptes des autres et les réglages de la maison restent dans
 * Paramètres, et c'est ce qui permet d'ouvrir celui-ci à tout le monde.
 *
 * PAS DE CODE QR, ET C'EST UN CHOIX. En fabriquer un demanderait une
 * bibliothèque, ou de charger une image chez un tiers — c'est-à-dire d'envoyer
 * le secret du deuxième facteur à quelqu'un d'autre. Toutes les applications
 * d'authentification savent recevoir une clef tapée; elle est écrite en groupes
 * de quatre pour qu'on ne se trompe pas.
 */
declare(strict_types=1);

$moi = (int)($_SESSION['lv_admin_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $moi > 0) {
    Auth::requireCsrf();
    dash_exige_ecriture('compte');

    $act = (string)($_POST['act'] ?? '');

    if ($act === 'mdp') {
        /* ON EXIGE LE MOT DE PASSE ACTUEL. Sans lui, une session laissée ouverte
           sur un poste suffirait à s'approprier le compte pour de bon. */
        $u  = DB::one('SELECT pass_hash FROM users WHERE id = ?', [$moi]);
        $ok = $u && password_verify((string)($_POST['actuel'] ?? ''), (string)$u['pass_hash']);
        $n1 = (string)($_POST['nouveau'] ?? '');
        $n2 = (string)($_POST['encore'] ?? '');

        if (!$ok) {
            dash_flash('Le mot de passe actuel ne correspond pas. Rien n’a changé.', 'err');
        } elseif (mb_strlen($n1) < 10) {
            dash_flash('Dix caractères au minimum.', 'err');
        } elseif ($n1 !== $n2) {
            dash_flash('Les deux saisies diffèrent. Rien n’a changé.', 'err');
        } else {
            DB::update('users', ['pass_hash' => password_hash($n1, PASSWORD_DEFAULT)], 'id = ?', [$moi]);
            dash_flash('Mot de passe changé.');
        }

    } elseif ($act === 'preparer') {
        /* On écrit un secret neuf mais on n'active rien: tant qu'un code n'a pas
           été prouvé, le compte doit pouvoir entrer sans. Sinon une application
           mal configurée enferme dehors. */
        DB::update('users', ['totp_secret' => Crypto::chiffrer(Totp::secret()), 'totp_actif' => 0],
                   'id = ?', [$moi]);
        dash_flash('Une clef a été préparée. Posez-la dans votre application, puis prouvez un code.');

    } elseif ($act === 'activer') {
        $u   = DB::one('SELECT totp_secret FROM users WHERE id = ?', [$moi]);
        $sec = Crypto::dechiffrer((string)($u['totp_secret'] ?? ''));
        $pas = $sec === '' ? null : Totp::verifier($sec, (string)($_POST['code'] ?? ''));
        if ($pas === null) {
            dash_flash('Ce code ne correspond pas. Rien n’a été activé.', 'err');
        } else {
            DB::update('users', ['totp_actif' => 1, 'totp_dernier_pas' => $pas], 'id = ?', [$moi]);
            dash_flash('Deuxième facteur actif. Il sera demandé à la prochaine connexion.');
        }

    } elseif ($act === 'retirer') {
        /* ON EXIGE UN CODE POUR RETIRER, quand le facteur est actif. Sans cela,
           une session volée suffirait à le désarmer et à rendre le mot de passe
           seul suffisant pour toujours. */
        $u   = DB::one('SELECT totp_secret, totp_actif, totp_dernier_pas FROM users WHERE id = ?', [$moi]);
        $sec = Crypto::dechiffrer((string)($u['totp_secret'] ?? ''));
        $pas = $sec === '' ? null
             : Totp::verifier($sec, (string)($_POST['code'] ?? ''),
                              $u['totp_dernier_pas'] !== null ? (int)$u['totp_dernier_pas'] : null);
        if ((int)($u['totp_actif'] ?? 0) === 1 && $pas === null) {
            dash_flash('Ce code ne correspond pas. Le deuxième facteur reste actif.', 'err');
        } else {
            DB::update('users', ['totp_secret' => null, 'totp_actif' => 0, 'totp_dernier_pas' => null],
                       'id = ?', [$moi]);
            dash_flash('Deuxième facteur retiré.');
        }
    }

    redirect('/dashboard.php?e=compte');
}

$u         = DB::one('SELECT email, name, role_dash, totp_secret, totp_actif, last_login
                        FROM users WHERE id = ?', [$moi]);
$monSecret = Crypto::dechiffrer((string)($u['totp_secret'] ?? ''));
$monActif  = (int)($u['totp_actif'] ?? 0) === 1;

$ROLES = ['direction' => 'Direction', 'production' => 'Production', 'lecture' => 'Lecture'];

dash_haut('compte', e((string)($u['email'] ?? '')));
?>
<?php dash_flash_html(); ?>
<div class="zone">

  <section class="ct">
    <h2>Mon deuxième facteur</h2>

    <?php if ($monActif): ?>
      <p class="ct-ok">Actif. Un code de votre application est demandé à chaque connexion.</p>
      <p class="aide">Si vous changez de téléphone, retirez-le d’abord ici, puis reposez-le sur le
         nouveau. Si vous l’avez perdu, seule la direction peut le retirer depuis le serveur.</p>
      <form method="post" class="ct-f">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="act" value="retirer">
        <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
               placeholder="Code actuel" required autocomplete="one-time-code">
        <button type="submit" class="ct-b">retirer</button>
      </form>

    <?php elseif ($monSecret !== ''): ?>
      <p class="aide">Ouvrez votre application d’authentification — Google Authenticator, Aegis,
         1Password, Bitwarden, le trousseau d’Apple — ajoutez un compte « saisir une clef », et
         recopiez celle-ci. Puis prouvez un code: tant qu’il n’est pas prouvé, rien n’est exigé
         à la connexion.</p>
      <p class="ct-s"><?= e(Totp::lisible($monSecret)) ?></p>
      <p class="aide">Compte: <strong><?= e((string)$u['email']) ?></strong> · Émetteur:
         <strong>Le Voisin</strong> · 6 chiffres, 30 secondes.</p>
      <form method="post" class="ct-f">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="act" value="activer">
        <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
               placeholder="Code à six chiffres" required autocomplete="one-time-code">
        <button type="submit" class="ct-b ct-oui">activer</button>
      </form>
      <form method="post" class="ct-f">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="act" value="retirer">
        <button type="submit" class="ct-b">annuler et effacer cette clef</button>
      </form>

    <?php else: ?>
      <p class="aide">Un mot de passe volé ouvre le dashboard tout seul. Un code à six chiffres,
         qui change toutes les trente secondes et ne vit que dans votre téléphone, change cela.</p>
      <form method="post" class="ct-f">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="act" value="preparer">
        <button type="submit" class="ct-b ct-oui">préparer une clef</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="ct">
    <h2>Mon mot de passe</h2>
    <p class="aide">Dix caractères au minimum. Une phrase entière vaut mieux qu’un mot compliqué:
       elle se retient et elle est plus longue.</p>
    <form method="post" class="ct-f ct-col">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="act" value="mdp">
      <input type="password" name="actuel" placeholder="Mot de passe actuel" required
             autocomplete="current-password">
      <input type="password" name="nouveau" placeholder="Nouveau" required minlength="10"
             autocomplete="new-password">
      <input type="password" name="encore" placeholder="Le nouveau, encore" required minlength="10"
             autocomplete="new-password">
      <button type="submit" class="ct-b">changer</button>
    </form>
  </section>

  <section class="ct">
    <h2>Ce compte</h2>
    <dl class="ct-d">
      <dt>Adresse</dt><dd><?= e((string)($u['email'] ?? '')) ?></dd>
      <dt>Nom</dt><dd><?= e((string)($u['name'] ?? '')) ?: '—' ?></dd>
      <dt>Rôle</dt><dd><?= e($ROLES[(string)($u['role_dash'] ?? '')] ?? (string)$u['role_dash']) ?></dd>
      <dt>Dernière entrée</dt>
      <dd><?= $u['last_login'] ? e(date('d.m.Y H:i', strtotime((string)$u['last_login']))) : '—' ?></dd>
    </dl>
    <p class="aide">Le rôle se change dans Paramètres, et seulement par la direction: personne ne
       se donne des droits à soi-même.</p>
  </section>

</div>

<style>
.ct{margin:0 0 22px;padding:16px 18px;border:1px solid var(--trait);border-radius:10px;
  background:var(--fond2);max-width:720px}
.ct h2{margin:0 0 8px;font-size:15px}
.ct .aide{margin:0 0 10px;font-size:12.5px;color:var(--doux);max-width:64ch}
.ct-ok{margin:0 0 8px;font-weight:600}
.ct-s{margin:12px 0;padding:12px 14px;background:var(--papier);border:1px solid var(--trait);
  border-radius:6px;font-family:ui-monospace,Menlo,monospace;font-size:16px;letter-spacing:.06em;
  word-break:break-all;-webkit-user-select:all;user-select:all}
.ct-f{display:flex;gap:8px;align-items:center;margin:8px 0 0;flex-wrap:wrap}
.ct-f.ct-col{flex-direction:column;align-items:flex-start;max-width:320px}
.ct-f input{width:auto;min-width:200px;padding:7px 10px;font:inherit;font-size:14px;
  border:1px solid var(--trait);border-radius:5px;background:var(--papier);color:var(--encre)}
.ct-f.ct-col input{width:100%;box-sizing:border-box}
button.ct-b{margin:0;padding:7px 15px;font:inherit;font-size:13.5px;font-weight:600;
  border:1px solid var(--trait);border-radius:5px;background:var(--papier);color:var(--doux);
  cursor:pointer}
button.ct-b:hover{color:var(--encre);border-color:var(--encre)}
button.ct-b.ct-oui{background:var(--jaune,#FFD24D);border-color:var(--jaune,#FFD24D);color:var(--encre)}
.ct-d{display:grid;grid-template-columns:140px 1fr;gap:6px 14px;margin:0 0 10px;font-size:13.5px}
.ct-d dt{color:var(--doux)}
.ct-d dd{margin:0}
</style>
<?php dash_bas(); ?>

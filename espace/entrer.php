<?php
/**
 * Espace collaborateur — la porte.   [V40-CLE] [13.08.2026]
 *
 * Il n'y a plus de mot de passe. La personne indique son adresse, reçoit une
 * clé par courriel, clique, et elle est chez elle. La preuve d'identité est la
 * boîte aux lettres, exactement comme l'était déjà le lien de réinitialisation
 * du modèle précédent : ce qui change n'est pas le niveau de protection, c'est
 * le nombre d'écrans et le nombre de demandes de dépannage.
 *
 * Trois entrées possibles sur cette même page :
 *
 *   1. AVEC UNE CLÉ, en cliquant depuis le courriel. On entre, et la clé meurt.
 *   2. AVEC LE SOUVENIR du navigateur, quand on est déjà venu dans le mois.
 *      La page reconnaît la personne, l'appelle par son nom, et un bouton
 *      suffit. Aucun courriel n'est envoyé : c'est ce qui rend le geste
 *      quotidien supportable.
 *   3. SANS RIEN : on écrit son adresse et l'on reçoit une clé.
 *
 * LA RÉPONSE EST TOUJOURS LA MÊME après une demande, que l'adresse ait un
 * espace ou non. Une page qui répondrait « inconnue » livrerait la liste des
 * personnes qui travaillent ici à quiconque prend le temps d'essayer des
 * adresses. Et l'essai est compté dans les deux cas, sinon la vitesse de la
 * réponse dirait ce que le texte refuse de dire.
 *
 * Fichier neuf, volontairement. Sur ce serveur le cache d'opcode empêche la
 * mise à jour d'index.php ; un fichier neuf, lui, compile toujours.
 */
require __DIR__ . '/_inc.php';

if (MemberAuth::check()) redirect('/espace/');

$etat = '';   // '' · envoye · expire · trop
$prec = '';   // l'adresse déjà saisie, pour ne pas la faire retaper

/* ---- 1. Une clé dans l'adresse -------------------------------------------
   L'annulation vient AVANT l'ouverture de session : si quoi que ce soit
   échouait ensuite, mieux vaut une clé morte qu'une clé qui traîne, valable,
   dans une boîte aux lettres. En demander une autre ne coûte rien. */
$jeton = trim((string)($_GET['jeton'] ?? ''));
if ($jeton !== '') {
    $m = MemberAuth::parJeton($jeton);
    if ($m && (int)($m['active'] ?? 0) === 1) {
        MemberAuth::lienAnnuler((int)$m['id']);
        if (MemberAuth::entrer((int)$m['id'])) redirect('/espace/');
    }
    $etat = 'expire';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    MemberAuth::requireCsrf();

    /* ---- 2. Le souvenir, un clic ---------------------------------------- */
    if ((string)($_POST['action'] ?? '') === 'reprendre') {
        $id = MemberAuth::souvenirLire();
        if ($id !== null && MemberAuth::entrer($id)) redirect('/espace/');
        /* Le souvenir ne vaut plus rien : compte désactivé, ou signature qui
           ne correspond plus. On l'efface pour que la page cesse de proposer
           un bouton qui ne mène nulle part. */
        MemberAuth::souvenirEffacer();
        $etat = 'expire';

    /* ---- 3. Une demande de clé ------------------------------------------ */
    } else {
        $prec = trim((string)($_POST['email'] ?? ''));
        if (MemberAuth::throttled('e:')) {
            $etat = 'trop';
        } else {
            MemberAuth::noter('e:', $prec);
            $c = Invitations::parAdresse($prec);
            if ($c) Invitations::cleEnvoyer($c);
            $etat = 'envoye';
            $prec = '';
        }
    }
}

/* Qui le navigateur croit reconnaître. Le compte est relu en base : un compte
   désactivé depuis la dernière visite ne doit pas voir son nom s'afficher. */
$connu = null;
if ($etat !== 'expire') {
    $sid = MemberAuth::souvenirLire();
    if ($sid !== null) {
        $connu = DB::one('SELECT id, name, email FROM collaborators WHERE id = ? AND active = 1', [$sid]) ?: null;
    }
}

espace_top(t('member_area'), false);
?>
<div class="espace-login">
  <h1><?= e(t('member_area')) ?></h1>

<?php if ($etat === 'envoye'): ?>
  <div class="form-ok" role="status"><p><?= e(t('esp_cle_envoyee')) ?></p></div>
  <p class="muted"><?= e(t('esp_cle_spam')) ?></p>

<?php else: ?>

  <?php if ($etat === 'expire'): ?>
  <div class="form-errors" role="alert"><p><?= e(t('esp_cle_morte')) ?></p></div>
  <?php elseif ($etat === 'trop'): ?>
  <div class="form-errors" role="alert"><p><?= e(t('member_throttled')) ?></p></div>
  <?php endif; ?>

  <?php if ($connu): ?>
  <?php /* Le navigateur se souvient : ni adresse à retaper, ni courriel à
           attendre. Le clic reste, lui, parce qu'un écran laissé ouvert dans
           un bureau ne doit pas rouvrir l'espace tout seul. */ ?>
  <p class="espace-connu"><?= e(t('esp_cle_bonjour', $connu['name'] ?: $connu['email'])) ?></p>
  <form method="post" class="form">
    <?= MemberAuth::csrfField() ?>
    <input type="hidden" name="action" value="reprendre">
    <p><button class="btn big" type="submit"><?= e(t('esp_cle_reprendre')) ?></button></p>
  </form>
  <details class="espace-autre">
    <summary><?= e(t('esp_cle_autre')) ?></summary>
  <?php endif; ?>

    <p class="muted"><?= e(t('esp_cle_intro')) ?></p>
    <form method="post" class="form">
      <?= MemberAuth::csrfField() ?>
      <div class="field">
        <label for="email"><?= e(t('member_email')) ?></label>
        <input type="email" id="email" name="email" required <?= $connu ? '' : 'autofocus' ?>
               autocomplete="email" value="<?= e($prec) ?>">
      </div>
      <p><button class="btn<?= $connu ? '' : ' big' ?>" type="submit"><?= e(t('esp_cle_demander')) ?></button></p>
    </form>

  <?php if ($connu): ?>
  </details>
  <?php endif; ?>

<?php endif; ?>
</div>
<?php espace_bottom();

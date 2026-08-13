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
 *   1. AVEC UNE CLÉ, en cliquant depuis le courriel. La page reconnaît la
 *      personne et lui demande de confirmer ; c'est ce clic-là qui entre et qui
 *      tue la clé. Voir la note plus bas : le GET ne consomme rien, parce que
 *      les antivirus de messagerie ouvrent les adresses avant leur destinataire.
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

   LE GET NE CONSOMME RIEN, et c'est la règle la plus importante de ce fichier.

   Outlook et Microsoft Defender, Proofpoint, Barracuda et plusieurs antivirus
   de messagerie OUVRENT les adresses contenues dans un message, à la livraison,
   pour les inspecter. Si la clé mourait au GET, elle serait morte avant que la
   personne ait vu le message : elle cliquerait, lirait « cette clé n'est plus
   valable », en redemanderait une depuis la même boîte surveillée, et
   recommencerait sans fin. Sans mot de passe de repli, c'est une porte fermée
   définitivement, et pour tout un domaine à la fois.

   Le GET reconnaît donc la personne et lui montre un bouton. C'est le POST qui
   entre et qui tue la clé : les robots ne font pas de POST. Le clic en plus est
   exactement ce qui protège.

   [13.08.2026] L'ancienne page ne souffrait pas de ce défaut — son GET
   affichait un formulaire — et c'est en la remplaçant que je l'ai introduit. */
$jeton = trim((string)($_GET['jeton'] ?? ''));
$porteur = null;   // la personne que la clé désigne, avant tout engagement
if ($jeton !== '') {
    $m = MemberAuth::parJeton($jeton);
    if ($m && (int)($m['active'] ?? 0) === 1) {
        $porteur = $m;
        /* La page parle la langue de la personne, et pas celle du dernier
           visiteur : la phrase qu'elle a le plus besoin de comprendre est
           justement celle qui lui annonce un ennui. */
        if (!empty($m['lang'])) I18n::setLang((string)$m['lang']);
    } else {
        $etat = 'expire';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    MemberAuth::requireCsrf();

    /* ---- 2a. La clé, confirmée par un clic ------------------------------ */
    if ((string)($_POST['action'] ?? '') === 'cle') {
        $m = MemberAuth::parJeton((string)($_POST['jeton'] ?? ''));
        if ($m && (int)($m['active'] ?? 0) === 1) {
            /* L'annulation vient avant l'ouverture de session : si quoi que ce
               soit échouait ensuite, mieux vaut une clé morte qu'une clé
               valable qui traîne dans une boîte aux lettres. */
            MemberAuth::lienAnnuler((int)$m['id']);
            if (MemberAuth::entrer((int)$m['id'])) redirect('/espace/');
        }
        $etat = 'expire';

    /* ---- 2b. Le souvenir, un clic --------------------------------------- */
    } elseif ((string)($_POST['action'] ?? '') === 'reprendre') {
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
            /* L'adresse n'est PAS enregistrée : la fiche d'une personne lit
               les lignes de login_attempts portant son adresse et les affiche
               comme « essais manqués ». Demander une clé et entrer sans encombre
               y ressemblait à un échec, sur l'écran même qui sert à diagnostiquer
               qui n'arrive pas à entrer. Le freinage, lui, compte par adresse IP
               et n'a pas besoin de savoir qui. */
            MemberAuth::noter('e:');
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

<?php if ($porteur): ?>
  <?php /* Une clé valable, pas encore dépensée : un nom et un bouton. Rien
           n'est écrit en base tant que ce bouton n'a pas été pressé. */ ?>
  <p class="espace-connu"><?= e(t('esp_cle_bonjour', $porteur['name'] ?: $porteur['email'])) ?></p>
  <form method="post" class="form">
    <?= MemberAuth::csrfField() ?>
    <input type="hidden" name="action" value="cle">
    <input type="hidden" name="jeton" value="<?= e($jeton) ?>">
    <p><button class="btn big" type="submit"><?= e(t('esp_cle_reprendre')) ?></button></p>
  </form>
  <p class="muted"><?= e(t('esp_cle_une_fois')) ?></p>

<?php elseif ($etat === 'envoye'): ?>
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

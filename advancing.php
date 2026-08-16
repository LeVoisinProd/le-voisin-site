<?php
/**
 * Le portail d'advancing, côté lieu. [16.08.2026]
 *
 * LA QUATRIÈME PORTE. Le site en avait trois: Auth pour le bureau, MemberAuth
 * pour les 77 collaborateur·rices, CatalogAuth pour les programmateur·rices.
 * Celle-ci est la quatrième, et c'est la plus ouverte des quatre: pas de
 * compte, pas de mot de passe, un lien qui suffit.
 *
 * POURQUOI CE CHOIX PLUTÔT QU'UN COMPTE. Le régisseur d'un théâtre n'ouvrira
 * pas un compte chez nous pour remplir six champs. Lui en imposer un, c'est
 * garantir qu'il répondra par e-mail comme avant, et l'écran ne servira à
 * personne. Le lien est donc la seule façon que cela existe.
 *
 * CE QUE LE LIEN NE DONNE PAS, et c'est ce qui rend le choix acceptable: il ne
 * vaut que pour l'advancing d'UNE date. Pas le prix de cession, pas le deal,
 * pas les contrats, pas les notes internes, pas les autres dates. Le pire cas
 * d'un lien qui fuit, c'est que quelqu'un lise la fiche technique d'un
 * spectacle et l'horaire d'un montage.
 *
 * UN FICHIER À LA RACINE, comme catalogue.php et dashboard.php: le cache
 * d'opcode du serveur garde index.php compilé et refuse de le relire — mesuré
 * le 12.08.2026 et remesuré depuis. Un fichier au nom neuf se compile à la
 * première requête.
 *
 * L'ADRESSE:  https://le-voisin.com/advancing.php?t=<jeton>
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

I18n::init();

$jeton = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$lien  = Advancing::parJeton($jeton);

/* Une seule et même réponse pour « jeton inconnu », « expiré » et « révoqué ».
   Distinguer apprendrait à qui essaie que la première moitié était bonne. */
if (!$lien) {
    http_response_code(404);
    $titre = 'Lien introuvable';
    $corps = '<p>Ce lien n\'est plus valable. Il a peut-être expiré, ou été remplacé
              par un plus récent.</p>
              <p>Écrivez à la personne qui vous l\'a envoyé: elle peut en ouvrir un
              nouveau en un clic.</p>';
    require __DIR__ . '/app/advancing_page.php';
    exit;
}

$bookingId = (int)$lien['booking_id'];
$b = DB::one('SELECT id, projet, artiste, venue, ville, pays, date_debut, date_texte, heure
              FROM booking WHERE id = ?', [$bookingId]);
if (!$b) { http_response_code(404); exit('Introuvable'); }

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Le jeton EST le justificatif: il est long, secret et lié à cette date.
       Une seconde vérification de session n'aurait rien à vérifier, puisqu'il
       n'y a pas de session. parJeton() a déjà refusé expiré et révoqué. */
    $n = Advancing::repondre($bookingId, (array)($_POST['r'] ?? []), (array)($_FILES['f'] ?? []));
    $message = $n > 0
        ? 'Merci, c\'est enregistré. Vous pouvez revenir sur ce lien pour compléter plus tard.'
        : 'Rien n\'a changé.';
}

Advancing::noterVisite($bookingId);
$champs = Advancing::champs($bookingId);

/* Les champs sont regroupés par section, dans l'ordre voulu par le bureau. */
$sections = [];
foreach ($champs as $c) $sections[(string)($c['section'] ?? '')][] = $c;

$lieu = trim((string)($b['venue'] ?? ''));
$quand = $b['date_texte'] ?: ($b['date_debut'] ? date('d.m.Y', strtotime((string)$b['date_debut'])) : '');
$titre = 'Advancing — ' . ($b['projet'] ?: 'spectacle');

ob_start();
?>
<p class="chapo">
  <strong><?= e((string)$b['projet']) ?></strong><?php if ($b['artiste']): ?> · <?= e((string)$b['artiste']) ?><?php endif; ?><br>
  <?= e($lieu) ?><?php if ($b['ville']): ?>, <?= e((string)$b['ville']) ?><?php endif; ?>
  <?php if ($quand): ?> · <?= e($quand) ?><?php endif; ?>
</p>

<p>Voici ce dont nous avons besoin pour préparer cette date. Répondez à ce qui vous
   concerne — tout n'est pas forcément de votre ressort — et revenez sur ce lien
   quand vous voulez: ce qui est enregistré reste.</p>

<?php if ($message): ?><div class="msg"><?= e($message) ?></div><?php endif; ?>

<?php if (!$champs): ?>
  <p class="vide">La liste n'est pas encore prête. Nous vous préviendrons.</p>
<?php else: ?>
<form method="post" action="advancing.php" enctype="multipart/form-data">
  <input type="hidden" name="t" value="<?= e($jeton) ?>">

  <?php foreach ($sections as $nom => $liste): ?>
    <?php if ($nom !== ''): ?><h2><?= e($nom) ?></h2><?php endif; ?>

    <?php foreach ($liste as $c): $cid = (int)$c['id']; $rep = (string)($c['reponse'] ?? ''); ?>
      <div class="ch <?= $c['etat'] === 'refuse' ? 'refait' : '' ?>">
        <label for="c<?= $cid ?>">
          <?= e((string)$c['libelle']) ?>
          <?php if ((int)$c['obligatoire'] === 1): ?><span class="ob" title="Nécessaire">·</span><?php endif; ?>
        </label>
        <?php if ($c['consigne']): ?><p class="cons"><?= e((string)$c['consigne']) ?></p><?php endif; ?>
        <?php if ($c['etat'] === 'refuse'): ?>
          <p class="cons refait">Ceci est à refaire. Merci de renvoyer une version corrigée.</p>
        <?php endif; ?>

        <?php if ($c['type'] === 'long'): ?>
          <textarea id="c<?= $cid ?>" name="r[<?= $cid ?>]" rows="4"><?= e($rep) ?></textarea>

        <?php elseif ($c['type'] === 'oui_non'): ?>
          <select id="c<?= $cid ?>" name="r[<?= $cid ?>]">
            <option value="">—</option>
            <option value="oui" <?= $rep === 'oui' ? 'selected' : '' ?>>oui</option>
            <option value="non" <?= $rep === 'non' ? 'selected' : '' ?>>non</option>
          </select>

        <?php elseif ($c['type'] === 'fichier'): ?>
          <?php if ($c['fichier']): ?>
            <p class="deja">Reçu : <strong><?= e((string)$c['fichier']) ?></strong>.
               En déposer un autre le remplace.</p>
          <?php endif; ?>
          <input type="file" id="c<?= $cid ?>" name="f[<?= $cid ?>]">
          <p class="cons">PDF, image, plan, tableur. 25 Mo au maximum.</p>

        <?php else: ?>
          <?php $t = ['nombre'=>'number','date'=>'date','heure'=>'time'][$c['type']] ?? 'text'; ?>
          <input type="<?= $t ?>" id="c<?= $cid ?>" name="r[<?= $cid ?>]" value="<?= e($rep) ?>">
        <?php endif; ?>

        <?php if ($c['etat'] === 'accepte'): ?>
          <p class="ok">Validé de notre côté.</p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <button type="submit">Enregistrer</button>
  <p class="pied">Vous pouvez enregistrer plusieurs fois. Rien ne se perd entre deux visites.</p>
</form>
<?php endif; ?>
<?php
$corps = (string)ob_get_clean();
require __DIR__ . '/app/advancing_page.php';

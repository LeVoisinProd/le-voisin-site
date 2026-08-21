<?php
/** Fiche d'un module.   [V10-CMS-BILINGUE] [V14-DUPLIQUER] */
require __DIR__ . '/_inc.php';
Auth::requireAdmin();

$entity = (string)($_GET['e'] ?? '');
$def = Content::def($entity);
$id = (int)($_GET['id'] ?? 0);
$row = Content::get($entity, $id);
if (!$row) { flash(ta('ed_notfound'), 'err'); redirect('/admin/list.php?e=' . $entity); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    $errors = Content::save($entity, $id, $_POST);
    if (!$errors) {
        // « Enregistrer et dupliquer » : la fiche en cours est d'abord
        // enregistrée, puis sa copie (hors ligne) est créée et ouverte.
        // Pratique pour saisir une tournée date après date.
        if (($_POST['after'] ?? '') === 'duplicate' && !empty($def['duplicable'])) {
            $newId = Content::duplicate($entity, $id);
            flash(dup_message($def));
            redirect('/admin/edit.php?e=' . $entity . '&id=' . $newId);
        }
        flash(ta('com_saved'));
        redirect('/admin/edit.php?e=' . $entity . '&id=' . $id);
    }
    $row = array_merge($row, $_POST); // réaffiche les valeurs saisies
}

$isNew = isset($_GET['new']);
$title = $isNew ? ta('ed_title_new', tc($def['label'])) : tc($def['label']);

admin_top($title, 'e:' . $entity);
?>
<?php
/* PRÉCÉDENT ET SUIVANT À CÔTÉ DU RETOUR. [Anna, 21.08.2026]
   Le garde-fou existe déjà: `form.js-dirty` déclenche le beforeunload du
   navigateur, donc quitter une saisie non enregistrée prévient tout seul.
   Rien à ajouter ici — et rien à ajouter non plus dans les autres modules,
   puisque cette fiche les sert tous. */
$vz = Content::voisins($entity, $id);
?>
<div class="page-head">
  <h1><?= e(tc($def['plural'])) ?> <span class="crumb">→ <?= e($isNew ? ta('ed_new') : ta('ed_modify')) ?></span></h1>
  <div class="actions">
    <?php if ($vz['prec'] !== null): ?>
      <a class="btn ghost small" href="<?= e(admin_url('edit.php?e=' . $entity . '&id=' . $vz['prec'])) ?>"><?= e(ta('ed_prev')) ?></a>
    <?php else: ?><span class="btn ghost small mort"><?= e(ta('ed_prev')) ?></span><?php endif; ?>
    <?php if ($vz['rang']): ?>
      <span class="rang"><?= e(ta('ed_rank', (string)$vz['rang'], (string)$vz['total'])) ?></span>
    <?php endif; ?>
    <?php if ($vz['suiv'] !== null): ?>
      <a class="btn ghost small" href="<?= e(admin_url('edit.php?e=' . $entity . '&id=' . $vz['suiv'])) ?>"><?= e(ta('ed_next')) ?></a>
    <?php else: ?><span class="btn ghost small mort"><?= e(ta('ed_next')) ?></span><?php endif; ?>
    <a class="btn ghost" href="<?= e(admin_url('list.php?e=' . $entity)) ?>"><?= e(ta('ed_back')) ?></a>
  </div>
</div>

<?php if ($errors): ?>
<div class="flash err"><?php foreach ($errors as $er) echo e($er) . '<br>'; ?></div>
<?php endif; ?>

<form method="post" class="editform js-dirty">
  <?= Auth::csrfField() ?>
  <div class="editgrid">
    <div class="editmain panel">
      <?php foreach ($def['fields'] as $name => $fdef):
          if (in_array($fdef['type'], ['toggle', 'seo'], true)) continue;
          echo render_field($name, $fdef, $row, $entity);
      endforeach; ?>
    </div>
    <div class="editside">
      <div class="panel">
        <?php foreach ($def['fields'] as $name => $fdef):
            if ($fdef['type'] !== 'toggle') continue;
            echo render_field($name, $fdef, $row, $entity);
        endforeach; ?>
        <?php /* [V30-VOIR-LA-PAGE] Le lien vers la page publique, juste au-dessus
                 des boutons d'enregistrement. Il s'ouvre dans un nouvel onglet :
                 on va voir le résultat sur le site sans quitter la fiche ni
                 perdre ce qu'on est en train d'écrire.

                 Il n'apparaît pas sur une fiche neuve, qui n'a pas encore
                 d'adresse, ni quand cette sorte de fiche ne s'affiche nulle
                 part sur le site — admin_public_url() renvoie alors une chaîne
                 vide plutôt qu'une adresse morte. */
        $voirUrl = $isNew ? '' : admin_public_url($entity, $row);
        $enLigne = !array_key_exists('visible', $def['fields']) || !empty($row['visible']); ?>
        <?php /* [V31-ESPACE-VOIR] Tout le bloc est enveloppé : « Voir la page »
                 mène ailleurs, « Enregistrer » agit ici, et les deux ne doivent
                 pas se toucher. L'espacement ne peut pas venir du bouton
                 lui-même — les lignes d'explication s'intercalent entre lui et
                 « Enregistrer » selon les cas, et une règle de voisinage
                 immédiat ne s'applique alors plus. C'est donc l'enveloppe, dont
                 la présence ne dépend d'aucun cas de figure, qui porte la
                 respiration. */ ?>
        <?php if ($voirUrl !== ''): ?>
        <div class="ed-voir">
          <a class="btn wide ghost" href="<?= e($voirUrl) ?>" target="_blank" rel="noopener"><?= e(ta('ed_view_page')) ?> <?= Ico::ext() ?></a>
          <?php if (!$enLigne): ?>
          <p class="hint"><?= e(ta('ed_view_page_hidden')) ?></p>
          <?php endif; ?>
          <p class="hint" id="viewHint" hidden><?= e(ta('ed_view_page_dirty')) ?></p>
        </div>
        <?php endif; ?>
        <button class="btn wide" type="submit"><?= e(ta('com_save')) ?></button>
        <?php if (!empty($def['duplicable']) && !$isNew): ?>
        <button class="btn wide ghost" type="submit" name="after" value="duplicate"><?= e(ta('ed_dup_save')) ?></button>
        <p class="hint"><?= e(ta('ed_dup_save_hint')) ?></p>
        <?php endif; ?>
      </div>
      <?php foreach ($def['fields'] as $name => $fdef):
          if ($fdef['type'] !== 'seo') continue;
          echo '<div class="panel">' . render_field($name, $fdef, $row, $entity) . '</div>';
      endforeach; ?>
    </div>
  </div>
</form>

<?php if ($entity === 'event' && !$isNew): ?>
<div class="panel" id="socialPanel" data-id="<?= (int)$id ?>">
  <h2><?= e(ta('ed_social')) ?></h2>
  <p class="hint"><?= e(ta('ed_social_hint')) ?></p>
  <p class="quick"><button type="button" class="btn small" id="socialGen"><?= e(ta('ed_social_gen')) ?></button></p>
  <div id="socialResult" hidden>
    <div class="social-grid">
      <img id="socialImg" src="" alt="<?= e(ta('ed_social_preview')) ?>">
      <div>
        <textarea id="socialCaption" rows="9"></textarea>
        <p class="quick" style="margin-top:10px">
          <button type="button" class="btn small ghost" id="socialCopy"><?= e(ta('ed_social_copy')) ?></button>
          <a class="btn small ghost" id="socialDl" href="#" download="publication-levoisin.jpg"><?= e(ta('ed_social_dl')) ?></a>
          <button type="button" class="btn small" id="socialPush"<?= trim(setting('social_webhook')) !== '' ? '' : ' hidden' ?>><?= e(ta('ed_social_push')) ?></button>
        </p>
        <p class="hint"<?= trim(setting('social_webhook')) !== '' ? ' hidden' : '' ?>>
          <?= e(ta('ed_social_webhook')) ?>
          <a href="<?= e(admin_url('settings.php#social')) ?>"><?= e(ta('ed_social_webhook_l')) ?></a>.</p>
        <p class="hint" id="socialEnHint" hidden><?= e(ta('ed_social_enhint')) ?></p>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php admin_bottom(['linkList' => admin_link_list()]); ?>

<?php /* Le Voisin — vue formulaire.   [V4-RENVOI]
        Nouveauté : le bouton « Envoyer un autre justificatif » après un envoi
        réussi, et le bandeau qui signale les coordonnées reprises. */ ?>
<section class="section">
  <div class="wrap">
    <h1><?= e(f($page, 'title')) ?></h1>
    <?php if (f($page, 'body')): ?><div class="rich form-intro"><?= f($page, 'body') ?></div><?php endif; ?>

    <?php if ($state['sent']): ?>
    <div class="form-success">
      <h2><?= e(t('form_success_title')) ?></h2>
      <p><?= e(t('form_success_text')) ?></p>
      <?php if (!empty($state['again'])): ?>
      <p class="form-again"><?= e(t('form_again_text')) ?></p>
      <p><a class="btn big" href="<?= e($state['url'] . '?suite=1') ?>"><?= e(t('form_again_btn')) ?></a></p>
      <?php endif; ?>
    </div>
    <?php else: ?>

    <?php // On réutilise .form-notice, déjà stylée : aucune feuille de style à
          // redéployer, et le bandeau reste lisible même si le CSS manque. ?>
    <?php if (!empty($state['resumed'])): ?>
    <p class="form-notice form-resumed"><?= e(t('form_resumed')) ?>
       <a href="<?= e($state['url'] . '?vider=1') ?>"><?= e(t('form_resumed_clear')) ?></a></p>
    <?php endif; ?>

    <?php if ($state['errors']): ?>
    <div class="form-errors" role="alert">
      <p><?= e($state['errors']['_'] ?? t('form_has_errors')) ?></p>
    </div>
    <?php endif; ?>

    <form class="form" method="post" action="<?= e(Pages::url($page)) ?>" enctype="multipart/form-data" novalidate>
      <?= Auth::csrfField() ?>
      <input type="hidden" name="_t" value="<?= time() ?>">
      <p class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>

      <p class="form-legend"><abbr>*</abbr> <?= e(t('form_required_legend')) ?></p>

      <?php
      $open = false;
      foreach ($def['fields'] as $fd):
          if ($fd['type'] === 'section'):
              if ($open) echo "</div>\n";
              echo '<h2 class="form-section">' . e(Forms::label($fd['label'])) . "</h2>\n";
              echo '<div class="form-grid">';
              $open = true;
              continue;
          endif;
          $key = $fd['key'];
          $label = Forms::label($fd['label']);
          $err = $state['errors'][$key] ?? '';
          $old = (string)($state['old'][$key] ?? '');
          $req = !empty($fd['required']);
          $wide = !empty($fd['wide']) || in_array($fd['type'], ['textarea', 'file'], true);
          $condAttr = '';
          if (!empty($fd['show_if'])) {
              $condAttr = ' data-show-if="' . e(json_encode([$fd['show_if'][0], array_values((array)$fd['show_if'][1])])) . '"';
          }
          if (!$open) { echo '<div class="form-grid">'; $open = true; }
      ?>
      <div class="field<?= $wide ? ' field--wide' : '' ?><?= $err ? ' has-error' : '' ?>"<?= $condAttr ?>>
        <label for="f_<?= e($key) ?>"><?= e($label) ?><?= $req ? ' <abbr title="' . e(t('form_required_mark')) . '">*</abbr>' : '' ?></label>
        <?php if (!empty($fd['help'])): ?><p class="field-help"><?= e(Forms::label($fd['help'])) ?></p><?php endif; ?>

        <?php switch ($fd['type']):
            case 'textarea': ?>
        <textarea id="f_<?= e($key) ?>" name="<?= e($key) ?>" rows="4"><?= e($old) ?></textarea>
        <?php /* [V17-CHOIX] La réponse déjà donnée est reconnue quelle que soit
                 la langue dans laquelle elle l'a été : changer de langue en
                 cours de route ne vide plus les menus déroulants. */ ?>
        <?php break; case 'select': ?>
        <select id="f_<?= e($key) ?>" name="<?= e($key) ?>">
          <option value=""><?= e(t('form_choose')) ?></option>
          <?= Forms::optionsHtml($fd, $old) ?>
        </select>
        <?php /* [14.08.2026] Le choix multiple, dessiné par Forms::casesHtml() —
                 la même fonction que la fiche de l'espace, pour que les deux
                 portes ne divergent pas au premier ajustement. */ ?>
        <?php break; case 'multi': ?>
        <span class="cases-multi" id="f_<?= e($key) ?>">
          <?= Forms::casesHtml($fd, $old, $key) ?>
        </span>
        <?php break; case 'yesno': ?>
        <span class="yesno" id="f_<?= e($key) ?>">
          <label><input type="radio" name="<?= e($key) ?>" value="yes"<?= $old === 'yes' ? ' checked' : '' ?>> <?= e(t('form_yes')) ?></label>
          <label><input type="radio" name="<?= e($key) ?>" value="no"<?= $old === 'no' ? ' checked' : '' ?>> <?= e(t('form_no')) ?></label>
        </span>
        <?php break; case 'file': ?>
        <input type="file" id="f_<?= e($key) ?>" name="<?= e($key) ?><?= !empty($fd['multiple']) ? '[]' : '' ?>"<?= !empty($fd['multiple']) ? ' multiple' : '' ?> accept="<?= e($fd['accept'] ?? '.pdf') ?>">
        <?php break; case 'number': ?>
        <input type="text" inputmode="decimal" id="f_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($old) ?>">
        <?php /* [V16-DATES] Jour d'abord, partout et pour tout le monde : un
                 champ de date natif s'affiche dans la langue du navigateur. */ ?>
        <?php break; case 'date': ?>
        <?= Dates::champ($key, $old, I18n::$lang, 'f_' . $key) ?>
        <?php break; default: ?>
        <input type="<?= e($fd['type'] === 'text' ? 'text' : $fd['type']) ?>" id="f_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($old) ?>">
        <?php break; endswitch; ?>

        <?php if ($err): ?><p class="field-error"><?= e($err) ?></p><?php endif; ?>
      </div>
      <?php endforeach; if ($open) echo "</div>\n"; ?>

      <?php if (!empty($def['notice'])): ?>
      <p class="form-notice"><?= e(Forms::label($def['notice'])) ?></p>
      <?php endif; ?>

      <p><button class="btn big" type="submit"><?= e(t('form_send')) ?></button></p>
    </form>
    <?php endif; ?>
  </div>
</section>
<?php /* [V16-DATES] L'aide à la frappe des dates voyage avec le formulaire, et
         non avec le gabarit du site : les formulaires sont le seul endroit du
         site public où l'on saisit quoi que ce soit, et il est inutile de
         charger ce petit script sur les pages qui ne font que se lire.
         Aujourd'hui le formulaire des dépenses ne demande aucune date ; le
         script ne trouve alors rien et ne fait rien. Il est ici pour le jour
         où un formulaire en demandera une, afin qu'on n'ait pas à y repenser.
         Le champ marche de toute façon sans lui : le script n'ajoute que les
         points au fil de la frappe. */ ?>
<?= Dates::script() ?>

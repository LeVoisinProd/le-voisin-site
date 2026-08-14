<?php
/**
 * Espace collaborateur — la fiche de renseignements.   [V12-ESPACE] [V35-FICHE-ONGLET]
 *
 * La fiche occupait une page à part, atteinte depuis un bouton. Cela faisait
 * un clic de trop pour la seule partie de l'espace où l'on a quelque chose à
 * faire, et une incohérence avec les deux autres onglets, qui montrent leur
 * contenu dès qu'on les ouvre. Elle est maintenant posée dans le premier
 * onglet, et le formulaire s'y enregistre.
 *
 * Le traitement et le rendu sont séparés, parce qu'ils n'ont pas lieu au même
 * moment : le premier doit s'exécuter avant la moindre ligne de HTML — il peut
 * décider de rediriger —, le second au milieu de la page. La page qui appelle
 * doit donc traiter d'abord, afficher ensuite.
 */

/**
 * Enregistre la fiche si le formulaire vient d'être envoyé.
 *
 * À appeler avant toute sortie : en cas de succès, la fonction redirige et ne
 * revient pas. Cette redirection n'est pas un ornement — sans elle, un
 * rafraîchissement après enregistrement renvoie le formulaire une deuxième
 * fois, avec la photo et les pièces d'identité qu'il porte.
 *
 * @return array{saved: bool, errors: array<int, string>, data: array<string, mixed>,
 *               bio: string, photoId: ?int, fields: array<int, array<string, mixed>>}
 */
function espace_infos_traiter(int $cid): array
{
    $def     = Forms::def('form_infos');
    $fields  = $def['fields'];
    $profile = MemberProfile::get($cid);
    $data    = $profile['data'];
    $bio     = $profile['bio'];
    $photoId = $profile['photo_image_id'];
    $errors  = [];

    /* L'enregistrement vient d'avoir lieu, et la page a été rechargée par la
       redirection : le message de confirmation est porté par l'adresse. */
    $saved = isset($_GET['enr']);

    /* [V37-FICHE-BUREAU] La fiche était éteinte pendant une visite de
       l'administration : rien ne s'y enregistrait, et le bandeau le promettait.
       Or le bureau ne détient le mot de passe de personne — « Voir son espace »
       est son seul chemin jusqu'à la fiche, et le panneau du CMS ne fait que la
       lire. Il ne pouvait donc écrire nulle part, au moment même où il vient de
       verser un tableau de soixante-dix-sept personnes à compléter. La fiche
       s'ouvre donc à l'écriture des deux côtés ; ce qui les distingue n'est plus
       le droit d'écrire mais la manière dont c'est rangé, et c'est
       MemberProfile::saveBureau() qui s'en charge. */
    $visite = MemberAuth::visite();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['fiche'])) {
        return ['saved' => $saved, 'errors' => [], 'data' => $data,
                'bio' => $bio, 'photoId' => $photoId, 'fields' => $fields];
    }

    MemberAuth::requireCsrf();
    $vals = [];
    foreach ($fields as $fd) {
        if (in_array($fd['type'], ['section', 'file'], true)) continue;
        /* [14.08.2026] Un choix multiple arrive en tableau et se range en un
           seul texte, les réponses jointes par un point médian espacé. Tout ce
           qui lit une fiche ensuite — la feuille imprimée, le CMS, le courriel —
           reçoit donc une chaîne comme pour n'importe quel autre champ, et n'a
           pas eu à changer d'une ligne. L'ancienne valeur est passée pour qu'une
           association retirée des réglages depuis ne soit pas effacée. */
        if ($fd['type'] === 'multi') {
            $vals[$fd['key']] = Forms::multiRanger($fd, $_POST[$fd['key']] ?? [], null,
                                                   (string)($data[$fd['key']] ?? ''));
            continue;
        }
        $v = trim((string)($_POST[$fd['key']] ?? ''));
        /* [V16-DATES] La date est écrite jour d'abord ; on la range à
           l'anglaise pour la base, qui est la seule à en avoir besoin. */
        if ($fd['type'] === 'date' && $v !== '') {
            $iso = Dates::versIso($v);
            if ($iso === null) $errors[] = Forms::label($fd['label']) . ' : ' . t('form_date_invalid');
            $v = $iso ?? $v;
        }
        $vals[$fd['key']] = $v;
    }
    if (($vals['email'] ?? '') !== '' && !filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) $errors[] = t('form_email_invalid');
    /* [V17-BIO] La courte bio tient maintenant 2000 signes. La limite est
       demandée à MemberProfile plutôt qu'écrite ici : elle doit être la même
       dans le compteur affiché, dans la troncature et dans la base. */
    $bio = mb_substr(trim((string)($_POST['bio'] ?? '')), 0, MemberProfile::bioMax());

    if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name']) && (int)($_FILES['photo']['error'] ?? 1) === 0) {
        try {
            /* [13.08.2026] upload() et non importFile(). La différence n'est pas
               cosmétique : importFile() ne vérifie ni le poids, ni l'erreur de
               transfert, ni que le fichier vienne bien d'un envoi. Avec
               upload_max_filesize à 300 Mo, une personne connectée remplissait
               le quota du site autant de fois qu'elle voulait, et le site
               cessait alors d'écrire, dépôt de documents compris.
               En prime, upload() redresse l'orientation EXIF : les photos
               prises avec un téléphone tenu de côté ne restent plus couchées. */
            $img = Img::upload($_FILES['photo'], 'collaborator', $cid, 'cover');
            if ($photoId) Img::delete($photoId);
            $photoId = (int)$img['id'];
        } catch (Throwable $e) { $errors[] = 'Photo : ' . $e->getMessage(); }
    }
    /* [13.08.2026] Les pièces d'identité comptent comme un dépôt : la personne
       en reçoit l'accusé au même titre qu'une facture. Un seul courriel pour
       les deux pièces, et l'envoi n'a lieu que si quelque chose est passé. */
    $deposes = [];
    foreach (['passport', 'residence_permit'] as $fk) {
        if (!empty($_FILES[$fk]['tmp_name']) && is_uploaded_file($_FILES[$fk]['tmp_name']) && (int)($_FILES[$fk]['error'] ?? 1) === 0) {
            try { $deposes[] = MemberDocs::upload($_FILES[$fk], $cid, 'identity', null, false); }
            catch (Throwable $e) { $errors[] = $e->getMessage(); }
        }
    }
    if ($deposes) {
        try { MemberNotify::depotConfirme(MemberAuth::member(), $deposes); }
        catch (Throwable $e) { /* l'avis manque, le dépôt reste */ }
    }
    /* [V16-DATES] Ce qui a été tapé reste affiché même si une seule ligne
       coince : sur une fiche de vingt-cinq questions, tout retaper à cause
       d'une date mal comprise serait décourageant. */
    $data = $vals + $data;

    if (!$errors) {
        /* Deux enregistrements pour deux bouches. Ce que la personne écrit est
           sa réponse et va dans « data ». Ce que le bureau écrit à sa place
           n'en est pas une : saveBureau() le range case par case, en pré-saisie
           là où la personne n'a pas encore répondu, par-dessus la réponse là où
           elle a répondu, et dans le compte pour les trois cases que le compte
           tient déjà. Il rend la liste des cases qu'il a dû refuser — le message
           est écrit ici, où l'on sait dans quelle langue parler au visiteur. */
        if ($visite) {
            foreach (MemberProfile::saveBureau($cid, $vals, $bio, $photoId) as $case) {
                $errors[] = espace_visite_t('member_visit_no_' . $case);
            }
        } else {
            /* [13.08.2026] Ce qui a changé se calcule AVANT d'écrire, en
               comparant à $profile, chargé en tête de fonction et jamais
               touché depuis. Et l'on compare à $profile['data'], la vue
               fusionnée déjà montrée à l'écran, et non à ce qui était
               enregistré : le formulaire renvoie aussi ce que le bureau avait
               pré-rempli, si bien que comparer à l'enregistré signalerait comme
               « modifié » tout le pré-remplissage, pour chaque personne, dès la
               première sauvegarde.

               La première saisie est un événement à part : c'est celui que le
               bureau attend, et il n'a pas de « ce qui a changé » à montrer. */
            $premiere = empty($profile['saisi']);
            $changes  = [];
            foreach ($fields as $fd) {
                if (in_array($fd['type'], ['section', 'file'], true)) continue;
                $k = $fd['key'];
                if (trim((string)($vals[$k] ?? '')) !== trim((string)($profile['data'][$k] ?? ''))) {
                    $changes[] = Forms::label($fd['label'], I18n::ADMIN_DEFAULT);
                }
            }
            /* Libellés propres, dans la langue du bureau : le libellé public
               de la bio porte un « %s » pour le nombre de signes, qui sortirait
               tel quel dans le message. */
            if ($bio !== $profile['bio'])                 $changes[] = ta('mn_fic_bio');
            if ((int)$photoId !== (int)$profile['photo_image_id']) $changes[] = ta('mn_fic_photo');

            MemberProfile::save($cid, $vals, $bio, $photoId);

            /* L'avis part après l'enregistrement, et son échec ne défait rien :
               la fiche est écrite, c'est le courriel qui manque. */
            if ($changes || $premiere) {
                try {
                    MemberNotify::ficheModifiee(MemberAuth::member() ?? [], $changes, $premiere);
                } catch (Throwable $e) { /* rien à dire ici */ }
            }
        }
    }

    if (!$errors) {
        /* « #partie-infos » est l'identifiant de la première partie, dans
           index.php : c'est ce qui ramène sur le bon onglet. Le banc vérifie
           que les deux se correspondent toujours. */
        redirect(espace_url() . '?enr=1#partie-infos');
    }

    return ['saved' => false, 'errors' => $errors, 'data' => $data,
            'bio' => $bio, 'photoId' => $photoId, 'fields' => $fields];
}

/**
 * La fiche, telle qu'elle s'affiche dans le premier onglet.
 *
 * Les intitulés de section sont des h3 et non des h2 : le titre de la partie,
 * juste au-dessus, est un h2. Trois niveaux, dans l'ordre — la partie, la
 * section, le champ.
 *
 * @param array{saved: bool, errors: array<int, string>, data: array<string, mixed>,
 *              bio: string, photoId: ?int, fields: array<int, array<string, mixed>>} $etat
 */
function espace_infos_form(int $cid, array $etat): string
{
    $fields  = $etat['fields'];
    $data    = $etat['data'];
    $photoId = $etat['photoId'];
    $photo   = $photoId ? Img::row((int)$photoId) : null;
    $idDocs  = array_filter(MemberDocs::forMember($cid), fn($d) => $d['category'] === 'identity');
    ob_start();
    ?>
  <?php if ($etat['saved']): ?><div class="form-success" role="status"><p>✓ <?= e(t('member_saved')) ?></p></div><?php endif; ?>
  <?php if ($etat['errors']): ?><div class="form-errors" role="alert"><?php foreach ($etat['errors'] as $er) echo '<p>' . e($er) . '</p>'; ?></div><?php endif; ?>

  <?php /* [V35-FICHE-ONGLET] Le formulaire s'envoie à l'accueil de l'espace, et
           l'ancre le ramène sur son onglet — y compris lorsqu'une date mal
           comprise renvoie la page avec ses erreurs : c'est précisément le
           moment où l'on ne veut pas avoir à rechercher où l'on était. */ ?>
  <form class="form espace-fiche" method="post" enctype="multipart/form-data" novalidate
        action="<?= e(espace_url()) ?>#partie-infos">
    <?= MemberAuth::csrfField() ?>
    <input type="hidden" name="fiche" value="1">
    <?php /* [V37-FICHE-BUREAU] Le « fieldset » portait l'attribut « disabled »,
             qui éteignait la fiche entière pendant une visite. Il reste, vide de
             tout attribut : il groupe les champs et leur donne un « min-width »
             nul que la grille attend. C'est le bandeau, et le mot au-dessus du
             bouton, qui disent maintenant chez qui l'on écrit — un formulaire
             gris disait bien qu'on ne pouvait rien y mettre, mais c'était
             justement ce qu'il fallait pouvoir faire. */
    $visite = MemberAuth::visite(); ?>
    <fieldset>

    <h3 class="form-section"><?= e(t('member_photo_bio')) ?></h3>
    <div class="form-grid">
      <div class="field">
        <label for="photo"><?= e(t('member_photo')) ?></label>
        <?php if ($photo): Img::ensure($photo, 'square'); ?><div class="member-photo-prev"><?= Img::tag($photo, 'square', ['alt' => '']) ?></div><?php endif; ?>
        <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
      </div>
      <?php /* [V17-BIO] Un seul nombre, celui de MemberProfile : le libellé, la
               limite de frappe et le compteur ne peuvent plus se contredire. */
      $bioMax = MemberProfile::bioMax(); ?>
      <div class="field field--wide">
        <label for="bio"><?= e(sprintf(t('member_bio'), $bioMax)) ?></label>
        <textarea id="bio" name="bio" rows="8" maxlength="<?= $bioMax ?>" class="js-count" data-max="<?= $bioMax ?>"><?= e($etat['bio']) ?></textarea>
        <p class="field-help"><span class="js-count-n">0</span>/<?= $bioMax ?></p>
      </div>
    </div>

    <?php
    $open = false;
    foreach ($fields as $fd):
        if ($fd['type'] === 'section'):
            if ($open) echo "</div>\n";
            echo '<h3 class="form-section">' . e(Forms::label($fd['label'])) . "</h3>\n<div class=\"form-grid\">";
            $open = true; continue;
        endif;
        if (!$open) { echo '<div class="form-grid">'; $open = true; }
        $key = $fd['key']; $label = Forms::label($fd['label']);
        $old = (string)($data[$key] ?? '');
        $wide = !empty($fd['wide']) || in_array($fd['type'], ['textarea', 'file'], true);
        $cond = !empty($fd['show_if']) ? ' data-show-if="' . e(json_encode([$fd['show_if'][0], array_values((array)$fd['show_if'][1])])) . '"' : '';
    ?>
    <div class="field<?= $wide ? ' field--wide' : '' ?>"<?= $cond ?>>
      <label for="f_<?= e($key) ?>"><?= e($label) ?></label>
      <?php if (!empty($fd['help'])): ?><p class="field-help"><?= e(Forms::label($fd['help'])) ?></p><?php endif; ?>
      <?php switch ($fd['type']):
        case 'textarea': ?><textarea id="f_<?= e($key) ?>" name="<?= e($key) ?>" rows="4"><?= e($old) ?></textarea>
        <?php /* [V17-CHOIX] La réponse est reconnue quelle que soit la langue dans
                 laquelle elle a été donnée : la fiche remplie en français s'ouvre
                 remplie en anglais, et l'inverse. */ ?>
        <?php break; case 'select': ?><select id="f_<?= e($key) ?>" name="<?= e($key) ?>"><option value=""><?= e(t('form_choose')) ?></option>
          <?= Forms::optionsHtml($fd, $old) ?></select>
        <?php break; case 'multi': ?><span class="cases-multi" id="f_<?= e($key) ?>">
          <?= Forms::casesHtml($fd, $old, $key) ?></span>
        <?php break; case 'yesno': ?><span class="yesno" id="f_<?= e($key) ?>">
          <label><input type="radio" name="<?= e($key) ?>" value="yes"<?= $old === 'yes' ? ' checked' : '' ?>> <?= e(t('form_yes')) ?></label>
          <label><input type="radio" name="<?= e($key) ?>" value="no"<?= $old === 'no' ? ' checked' : '' ?>> <?= e(t('form_no')) ?></label></span>
        <?php break; case 'file': ?><input type="file" id="f_<?= e($key) ?>" name="<?= e($key) ?>" accept="<?= e($fd['accept'] ?? '.pdf,.jpg,.jpeg,.png') ?>">
          <?php if ($idDocs): ?><p class="field-help"><?= e(t('member_id_on_file')) ?></p><?php endif; ?>
        <?php break; case 'number': ?><input type="text" inputmode="decimal" id="f_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($old) ?>">
        <?php /* [V16-DATES] Jour d'abord, quelle que soit la langue du navigateur :
                 pour une date de naissance, 07/04 et 04/07 ne pardonnent pas. */ ?>
        <?php break; case 'date': ?><?= Dates::champ($key, $old, I18n::$lang, 'f_' . $key) ?>
        <?php break; default: ?><input type="<?= e($fd['type'] === 'text' ? 'text' : $fd['type']) ?>" id="f_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($old) ?>">
      <?php break; endswitch; ?>
    </div>
    <?php endforeach; if ($open) echo "</div>\n"; ?>
    </fieldset>

    <?php /* [V37-BOUTONS] « btn » et non « btn big » : l'espace n'avait qu'un
             seul bouton de cette taille, sur ce seul onglet, et il faisait
             paraître les autres inégaux. Deux tailles suffisent, ici comme dans
             le CMS — l'ordinaire pour une action de page, la petite pour une
             action de ligne. */ ?>
    <?php if ($visite): ?>
    <div class="espace-fiche-envoi">
      <p class="form-notice"><?= e(espace_visite_t('member_visit_fiche')) ?></p>
      <p><button class="btn" type="submit"><?= e(espace_visite_t('member_visit_save')) ?></button></p>
    </div>
    <?php else: ?>
    <p class="espace-fiche-envoi"><button class="btn" type="submit"><?= e(t('member_save')) ?></button></p>
    <?php endif; ?>
  </form>
  <script>
  document.querySelectorAll('.js-count').forEach(function(t){var o=t.closest('.field').querySelector('.js-count-n');var u=function(){if(o)o.textContent=t.value.length;};t.addEventListener('input',u);u();});
  </script>
    <?php
    return (string)ob_get_clean();
}

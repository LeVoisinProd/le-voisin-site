<?php
/**
 * Espace collaborateur — déposer une facture, dire où en est un document.
 * [V36-FACTURES]
 *
 * Jusqu'ici l'espace était à sens unique : le bureau déposait, la personne
 * téléchargeait. Il y manquait le mouvement inverse, qui est pourtant celui
 * qu'on fait le plus souvent — envoyer sa facture — et il se faisait par
 * courriel, dans une pièce jointe nommée « scan.pdf », à retrouver plus tard
 * dans une boîte de réception.
 *
 * Deux gestes sont ajoutés, et un seul endroit les traite :
 *
 *   — déposer une facture, en haut du deuxième onglet, avec l'association
 *     concernée, le mois, et le projet s'il y en a un ;
 *   — poser le statut suivant sur un document : « Bien reçu » sur une fiche de
 *     salaire, « Reçue » sur une facture qu'on vient d'être payé.
 *
 * Le traitement et le rendu sont séparés comme dans _infos.php, et pour la
 * même raison : le premier peut rediriger, il doit donc s'exécuter avant la
 * moindre ligne de HTML.
 *
 * Rien ici ne fonctionne tant que la base n'a pas été mise à jour :
 * MemberDocs::colonneStatut() garde l'entrée, et l'espace reste exactement
 * celui d'hier. Installer les fichiers avant de mettre la base à jour ne
 * casse rien — cela ne fait rien.
 */

/* ---------------------------------------------------------------------------
   Le petit mot qui survit à la redirection.

   L'administration a flash(), mais elle l'a pour elle seule : la fonction est
   définie dans admin/_inc.php, que l'espace ne charge pas — et il n'y a aucune
   raison de charger la moitié de l'administration pour afficher une phrase.
   L'espace a donc la sienne, sous sa propre clef de session, ce qui évite au
   passage qu'un message destiné au bureau s'affiche chez quelqu'un d'autre.

   Le texte est traduit au moment où il est écrit, et non au moment où il est
   lu : entre les deux il n'y a qu'une redirection, la langue n'a pas le temps
   de changer, et stocker une phrase toute faite épargne de stocker une clef,
   ses paramètres, et la mécanique pour les rejouer.
   --------------------------------------------------------------------------- */

function espace_flash(string $type, string $texte): void
{
    $_SESSION['lv_espace_flash'] = ['type' => $type, 'texte' => $texte];
}

/** Le message en attente, s'il y en a un. Le lire l'efface. */
function espace_flash_lire(): ?array
{
    if (empty($_SESSION['lv_espace_flash'])) return null;
    $f = (array)$_SESSION['lv_espace_flash'];
    unset($_SESSION['lv_espace_flash']);
    return $f;
}

/** Le message en attente, rendu. Rien du tout s'il n'y en a pas. */
function espace_flash_html(): string
{
    $f = espace_flash_lire();
    if (!$f) return '';
    $ok = ($f['type'] ?? '') === 'ok';
    return '<div class="' . ($ok ? 'form-success' : 'form-errors') . '" role="'
         . ($ok ? 'status' : 'alert') . '"><p>' . ($ok ? '✓ ' : '')
         . e((string)($f['texte'] ?? '')) . '</p></div>';
}

/* ---------------------------------------------------------------------------
   Les mois qu'on peut facturer.

   Une liste de mois écrits en toutes lettres plutôt que deux menus — mois d'un
   côté, année de l'autre. Deux menus laissent choisir « Février 2019 », qui
   n'est pas un mois qu'on facture, et obligent à valider une combinaison. Un
   seul menu ne propose que des réponses justes, et la bonne est en tête : le
   mois en cours.

   On remonte dix-huit mois. Au-delà, ce n'est plus un retard, c'est une
   histoire ancienne — et elle se règle avec le bureau, pas avec un formulaire.
   --------------------------------------------------------------------------- */
function espace_periodes(int $n = 18): array
{
    $out    = [];
    $depart = new DateTimeImmutable('first day of this month');
    for ($i = 0; $i < $n; $i++) {
        $d = $depart->sub(new DateInterval('P' . $i . 'M'));
        $out[$d->format('Y-m')] = Dates::moisAn($d->format('Y-m'), I18n::$lang);
    }
    return $out;
}

/**
 * Le nom sous lequel la personne est connue, pour le nom du fichier.
 *
 * Celui de sa fiche d'abord — c'est elle qui l'a écrit, et il est complet —,
 * celui de son compte ensuite. La fiche peut ne pas être remplie, et un
 * fichier ne doit pas s'appeler « __Facture.pdf » pour autant.
 */
function espace_nom_personne(array $m): string
{
    $nom = '';
    try {
        $p   = MemberProfile::get((int)$m['id']);
        $nom = trim((string)($p['data']['full_name'] ?? ''));
    } catch (Throwable $e) {
        $nom = '';
    }
    if ($nom === '') $nom = trim((string)($m['name'] ?? ''));
    return $nom;
}

/* ---------------------------------------------------------------------------
   Le traitement.

   À appeler avant toute sortie. La fonction ne revient jamais quand elle a
   travaillé : elle redirige, ce qui vide le formulaire du navigateur — sans
   cela, un rafraîchissement après un dépôt déposerait la facture une seconde
   fois.

   Elle ne se déclenche que sur $_POST['doc'], jamais sur un POST quelconque :
   la fiche de renseignements poste sur la même adresse et se reconnaît, elle, à
   $_POST['fiche']. Les deux formulaires cohabitent sans se marcher dessus.
   --------------------------------------------------------------------------- */
function espace_docs_traiter(array $m): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['doc'])) return;
    if (!MemberDocs::colonneStatut()) return;

    $retour = espace_url() . '#partie-contrats';

    /* [V27-ACCES] Pendant une visite de l'administration, rien ne s'écrit. Le
       bandeau noir promet que rien n'est modifié ; la promesse se tient ici,
       par le refus d'écrire, et non par un bouton grisé — un bouton absent
       n'empêche personne de fabriquer un envoi à la main. */
    if (MemberAuth::visite()) {
        espace_flash('err', espace_visite_t('member_visit_ro'));
        redirect($retour);
    }

    MemberAuth::requireCsrf();
    $quoi = (string)$_POST['doc'];

    if ($quoi === 'depot')  espace_docs_depot($m, $retour);
    if ($quoi === 'statut') espace_docs_statut($m, $retour);
    redirect($retour);
}

/** Le dépôt d'une facture. Ne revient pas. */
function espace_docs_depot(array $m, string $retour): void
{
    $fichier = $_FILES['facture'] ?? null;
    if (!is_array($fichier) || (int)($fichier['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        espace_flash('err', tu('doc_f_nofile'));
        redirect($retour);
    }

    /* L'association : celle qui reçoit la facture, donc celle qui sera
       prévenue. On n'accepte que ce que les réglages proposent — un nom
       inventé rangerait le document sous une association qui n'existe pas, et
       l'avis de dépôt partirait à l'adresse générale sans qu'on sache pourquoi. */
    $choix = MemberDocs::assocChoix();
    $assoc = trim((string)($_POST['assoc'] ?? ''));
    if ($assoc !== '' && !array_key_exists($assoc, $choix)) $assoc = '';
    if ($assoc === '' && $choix) {
        espace_flash('err', tu('doc_f_noassoc'));
        redirect($retour);
    }

    /* Le mois : une des réponses proposées, pas une date libre. */
    $periodes = espace_periodes();
    $periode  = (string)($_POST['periode'] ?? '');
    if (!isset($periodes[$periode])) $periode = (string)array_key_first($periodes);
    [$annee, $mois] = array_map('intval', explode('-', $periode));

    /* Le projet, facultatif : une facture peut n'en concerner aucun. */
    $projet  = (int)($_POST['projet'] ?? 0);
    $projets = MemberDocs::projetChoix(I18n::$lang);
    $projet  = isset($projets[$projet]) ? $projet : 0;

    $ext = mb_strtolower(pathinfo((string)($fichier['name'] ?? ''), PATHINFO_EXTENSION));
    $nom = MemberDocs::nomFacture(espace_nom_personne($m), $annee, $mois, $ext);

    try {
        $doc = MemberDocs::upload($fichier, (int)$m['id'], 'invoice',
                                  $projet ?: null, false, $assoc, 'member', $nom);
    } catch (Throwable $e) {
        espace_flash('err', $e->getMessage());
        redirect($retour);
    }

    /* L'avis part après coup, et son échec ne défait rien : la facture est
       déposée, elle est visible des deux côtés, et c'est le courriel qui
       manque — pas le document. */
    try { MemberNotify::factureDeposee($m, $doc); } catch (Throwable $e) { /* rien à dire ici */ }

    espace_flash('ok', tu('doc_f_ok', (string)($doc['filename'] ?? '')));
    redirect($retour);
}

/** Le statut suivant, posé par la personne. Ne revient pas. */
function espace_docs_statut(array $m, string $retour): void
{
    $id  = (int)($_POST['id'] ?? 0);
    $doc = $id > 0 ? MemberDocs::row($id) : null;

    /* La propriété est vérifiée ici, où l'on sait de qui il s'agit :
       MemberDocs::statut() vérifie l'enchaînement, pas l'appartenance. */
    if (!$doc || (int)$doc['collaborator_id'] !== (int)$m['id']) {
        espace_flash('err', tu('doc_f_refus'));
        redirect($retour);
    }

    $vers = (string)($_POST['vers'] ?? '');
    if (!MemberDocs::statut($id, $vers, 'member')) {
        espace_flash('err', tu('doc_f_refus'));
        redirect($retour);
    }

    $frais = MemberDocs::row($id) ?: $doc;
    try { MemberNotify::receptionConfirmee($m, $frais); } catch (Throwable $e) { /* rien à dire ici */ }

    espace_flash('ok', tu(MemberDocs::parLaPersonne($doc) ? 'doc_f_recu' : 'doc_f_ack'));
    redirect($retour);
}

/* ---------------------------------------------------------------------------
   Le formulaire de dépôt.

   Il est en haut du deuxième onglet, et non derrière un bouton « Déposer un
   document » : c'est le seul geste que la personne vient faire ici, le cacher
   d'un clic reviendrait à cacher la raison de la page. Il est aussi en dehors
   du cas « aucun document » — quelqu'un qui n'a encore rien reçu est
   précisément quelqu'un qui a une première facture à envoyer.

   Trois questions et un fichier. Le nom du fichier n'est pas demandé : il est
   fabriqué à partir des réponses, selon la nomenclature du bureau, sigle de
   l'association compris. Sur soixante-dix-sept comptes, c'est la seule façon
   d'obtenir des noms qui se rangent.
   --------------------------------------------------------------------------- */
function espace_facture_form(array $m): string
{
    if (!MemberDocs::colonneStatut()) return '';

    $visite   = MemberAuth::visite();
    $assocs   = MemberDocs::assocChoix();
    $periodes = espace_periodes();
    $projets  = MemberDocs::projetChoix(I18n::$lang);
    $exts     = '.' . implode(',.', MemberDocs::EXTS);

    ob_start();
    ?>
  <details class="espace-depot"<?= $visite ? '' : ' open' ?>>
    <summary class="espace-depot-h"><?= e(t('member_depot_h')) ?></summary>
    <p class="espace-depot-i"><?= e(t('member_depot_i')) ?></p>
    <form class="form espace-depot-form" method="post" enctype="multipart/form-data"
          action="<?= e(espace_url()) ?>#partie-contrats">
      <?= MemberAuth::csrfField() ?>
      <input type="hidden" name="doc" value="depot">
      <fieldset<?= $visite ? ' disabled' : '' ?>>
      <div class="form-grid">
        <?php if ($assocs): ?>
        <div class="field">
          <label for="depot-assoc"><?= e(t('member_depot_assoc')) ?></label>
          <select id="depot-assoc" name="assoc" required>
            <option value=""><?= e(t('form_choose')) ?></option>
            <?php foreach ($assocs as $nom => $sigle): ?>
            <option value="<?= e($nom) ?>"><?= e($sigle !== '' ? $nom . ' — ' . $sigle : $nom) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="field">
          <label for="depot-periode"><?= e(t('member_depot_mois')) ?></label>
          <select id="depot-periode" name="periode">
            <?php foreach ($periodes as $clef => $libelle): ?>
            <option value="<?= e($clef) ?>"><?= e($libelle) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php /* [V36-FACTURES] Le projet est facultatif, et c'est écrit dans le
                 menu lui-même : « Aucun projet » est une réponse, pas une case
                 laissée vide. Une personne qui travaille sur trois spectacles
                 dans le mois émet trois factures, et c'est ici qu'elles se
                 distinguent. */ ?>
        <?php if ($projets): ?>
        <div class="field">
          <label for="depot-projet"><?= e(t('member_depot_projet')) ?></label>
          <select id="depot-projet" name="projet">
            <option value="0"><?= e(t('member_depot_sans_projet')) ?></option>
            <?php foreach ($projets as $pid => $titre): ?>
            <option value="<?= (int)$pid ?>"><?= e($titre) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="field field--wide">
          <label for="depot-fichier"><?= e(t('member_depot_fichier')) ?></label>
          <input type="file" id="depot-fichier" name="facture" accept="<?= e($exts) ?>" required>
          <p class="field-help"><?= e(t('member_depot_aide')) ?></p>
        </div>
      </div>
      </fieldset>
      <?php if ($visite): ?>
      <p class="form-notice"><?= e(espace_visite_t('member_visit_ro')) ?></p>
      <?php else: ?>
      <p><button class="btn" type="submit"><?= e(t('member_depot_go')) ?></button></p>
      <?php endif; ?>
    </form>
  </details>
    <?php
    return (string)ob_get_clean();
}

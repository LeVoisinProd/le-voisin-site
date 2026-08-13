<?php
/** Fiche d'un collaborateur : documents, signature, compte.   [V12-ESPACE] [V15-FICHE-PDF] */
require __DIR__ . '/_inc.php';
Auth::requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$c = DB::one('SELECT * FROM collaborators WHERE id = ?', [$id]);
if (!$c) { flash(ta('ce_notfound'), 'err'); redirect('/admin/collaborators.php'); }

// Téléchargement admin d'un document (vérifie qu'il appartient bien à ce collaborateur).
if (isset($_GET['dl'])) {
    $doc = MemberDocs::row((int)$_GET['dl']);
    if ($doc && (int)$doc['collaborator_id'] === $id && is_file($p = MemberDocs::filePath($doc, true))) {
        $name = $doc['signed_filename'] ?: ($doc['filename'] ?: ('document.' . $doc['ext']));
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . addslashes($name) . '"');
        header('Content-Length: ' . filesize($p));
        readfile($p); exit;
    }
    http_response_code(404); exit(ta('ce_dl_notfound'));
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $mobile = trim((string)($_POST['mobile'] ?? ''));
        $lang = in_array(($_POST['lang'] ?? 'fr'), ['fr', 'en'], true) ? (string)$_POST['lang'] : 'fr';
        $active = empty($_POST['active']) ? 0 : 1;
        if ($name === '') $errors[] = ta('ce_name_req');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = ta('ce_bad_email');
        if (!$errors && DB::val('SELECT id FROM collaborators WHERE email = ? AND id <> ?', [$email, $id])) $errors[] = ta('ce_email_used');
        if (!$errors) {
            DB::update('collaborators', compact('name', 'email', 'mobile', 'lang', 'active'), 'id = ?', [$id]);
            flash(ta('com_saved')); redirect('/admin/collaborator-edit.php?id=' . $id);
        }
    } elseif ($action === 'lien') {
        // Fabrique un lien à usage unique. Le mot de passe en cours n'est pas
        // touché : il reste valable tant que la personne n'en a pas choisi un autre.
        MemberAuth::lienNouveau($id);
        flash(ta('ce_link_made'));
        redirect('/admin/collaborator-edit.php?id=' . $id);
    } elseif ($action === 'lien_envoyer') {
        /* [12.08.2026] Envoyer le lien par e-mail, depuis la fiche d'une seule
           personne. L'envoi groupé existait depuis le chantier des invitations,
           mais il oblige à passer par la liste et à cocher — inutilement lourd
           pour une personne qu'on vient justement d'ouvrir.

           C'est le même Invitations::envoyer() que la liste : un seul chemin
           d'envoi, un seul texte, un seul journal. Il fabrique aussi le lien au
           passage, donc pas besoin d'en créer un avant.

           Un envoi remplace le lien précédent. Si la personne n'avait pas
           encore utilisé le sien, l'ancien cesse de fonctionner — c'est voulu,
           et le message de retour le dit. */
        $obstacle = Invitations::obstacle();
        if ($obstacle !== '') {
            flash($obstacle, 'err');
        } else {
            /* [13.08.2026] $c, et non $row, qui n'existe nulle part dans ce
               fichier. La ligne est là depuis le chantier des invitations et
               n'a donc JAMAIS fonctionné : passer une variable indéfinie à un
               paramètre typé array est une erreur fatale en PHP 8, page
               blanche, aucun message, aucun envoi. Personne ne l'avait vu
               parce que le seul symptôme est une page qui ne répond pas. */
            $r = Invitations::envoyer($c);
            if ($r['ok']) flash(ta('ce_link_sent') . ' ' . $r['email']);
            else          flash(($r['raison'] ?: ta('inv_err_send')) , 'err');
        }
        redirect('/admin/collaborator-edit.php?id=' . $id);
    } elseif ($action === 'lien_annuler') {
        MemberAuth::lienAnnuler($id);
        flash(ta('ce_link_cancelled'));
        redirect('/admin/collaborator-edit.php?id=' . $id);
    } elseif ($action === 'voir') {
        // [V27-ACCES] « Voir son espace ». On ouvre l'espace de la personne
        // sans rien savoir de son mot de passe et sans inscrire de connexion
        // à son nom : c'est un regard, pas une connexion. Le compte doit être
        // actif, sinon l'espace renverrait aussitôt vers sa page d'entrée.
        if (MemberAuth::visiteOuvrir($id)) redirect('/espace/');
        flash(ta('ce_visit_off'), 'err');
        redirect('/admin/collaborator-edit.php?id=' . $id);
    } elseif ($action === 'upload') {
        $cat  = (string)($_POST['category'] ?? 'other');
        /* [V33-ESPACE-3] Les deux menus sont lus sans se demander lequel a été
           montré : c'est MemberDocs::upload() qui écarte celui qui ne va pas
           avec la rubrique. Un formulaire vieux d'un onglet, un navigateur sans
           JavaScript, un envoi bricolé à la main : tous donnent le même
           résultat, parce que la règle n'est écrite qu'à un seul endroit. */
        $proj = (int)($_POST['project_id'] ?? 0) ?: null;
        $sign = !empty($_POST['needs_signature']);
        $asso = (string)($_POST['assoc'] ?? '');   // [V32-DOC-ASSO]
        $f = $_FILES['file'] ?? ['name' => []];
        $names = is_array($f['name'] ?? null) ? $f['name'] : [$f['name'] ?? ''];
        $ok = 0; $errs = []; $deposes = [];
        foreach ($names as $i => $nm) {
            $err = is_array($f['error']) ? ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) : $f['error'];
            if ((int)$err === UPLOAD_ERR_NO_FILE) continue;
            $one = [
                'name'     => is_array($f['name']) ? $f['name'][$i] : $f['name'],
                'type'     => is_array($f['type']) ? $f['type'][$i] : $f['type'],
                'tmp_name' => is_array($f['tmp_name']) ? $f['tmp_name'][$i] : $f['tmp_name'],
                'error'    => $err,
                'size'     => is_array($f['size']) ? $f['size'][$i] : $f['size'],
            ];
            try {
                $doc = MemberDocs::upload($one, $id, $cat, $proj, $sign, $asso);
                $ok++;
                $deposes[] = $doc;   // [V36-FACTURES] pour l'avis unique de fin de boucle
                // Envoi automatique à Skribble si le document doit être signé et que Skribble est configuré.
                if ($sign && ($doc['ext'] ?? '') === 'pdf' && Skribble::configured()) {
                    try {
                        $res = Skribble::send(MemberDocs::filePath($doc, false), $doc['title'] ?: $doc['filename'], $c['email'], $c['mobile'], (string)($c['lang'] ?? ''));
                        DB::update('member_documents', ['skribble_request_id' => $res['id'], 'skribble_signing_url' => $res['signing_url'], 'sign_status' => 'sent'], 'id = ?', [$doc['id']]);
                    } catch (Throwable $sx) { $errs[] = 'Skribble (' . $nm . ') : ' . $sx->getMessage(); }
                }
            }
            catch (Throwable $ex) { $errs[] = (string)$nm . ' : ' . $ex->getMessage(); }
        }
        /* [V36-FACTURES] Un seul avis pour tout l'envoi, une fois la boucle
           finie : déposer douze fiches de salaire d'un coup doit se lire comme
           une attention, pas comme une panne de serveur. Un courriel qui ne
           part pas ne remet rien en cause — les documents sont déposés, et
           c'est cela qui compte. */
        if ($deposes) { try { MemberNotify::documentsDeposes($c, $deposes); } catch (Throwable $ex) { } }
        if ($ok) flash($ok > 1 ? ta('ce_docs_added', $ok) : ta('ce_doc_added'));
        if ($errs) flash(implode(' — ', $errs), 'err');
        redirect('/admin/collaborator-edit.php?id=' . $id);
    } elseif ($action === 'doc_assoc') {
        // [V32-DOC-ASSO] Ranger un document déjà déposé sous une association —
        // ou l'en sortir, en choisissant la ligne vide. C'est ce qui permet de
        // reprendre l'existant sans le redéposer : le fichier est renommé sur
        // le disque au passage, il repart donc avec le bon sigle.
        $doc = MemberDocs::row((int)($_POST['doc'] ?? 0));
        if ($doc && (int)$doc['collaborator_id'] === $id) {
            MemberDocs::ranger((int)$doc['id'], (string)($_POST['assoc'] ?? ''));
            flash(ta('ce_doc_filed'));
        }
        redirect('/admin/collaborator-edit.php?id=' . $id);
    } elseif ($action === 'doc_project') {
        // [V33-ESPACE-3] Rattacher à un projet un document de production déjà
        // déposé — ou l'en détacher, avec la ligne vide. Le fichier n'est pas
        // renommé : le sigle en fin de nom désigne l'employeur, pas la
        // production, et rien dans la nomenclature du bureau ne prévoit le
        // projet.
        $doc = MemberDocs::row((int)($_POST['doc'] ?? 0));
        if ($doc && (int)$doc['collaborator_id'] === $id) {
            MemberDocs::rangerProjet((int)$doc['id'], (int)($_POST['project_id'] ?? 0) ?: null);
            flash(ta('ce_doc_projected'));
        }
        redirect('/admin/collaborator-edit.php?id=' . $id);
    } elseif ($action === 'doc_cat') {
        // [V33-ESPACE-3] Changer un document de rubrique — donc, au besoin, de
        // volet. C'est par là que passe la reprise de l'existant : un billet de
        // train rangé jadis dans « Autres documents » devient « Billets de
        // voyage » et rejoint les projets, sans être redéposé.
        $doc = MemberDocs::row((int)($_POST['doc'] ?? 0));
        if ($doc && (int)$doc['collaborator_id'] === $id) {
            MemberDocs::rangerCategorie((int)$doc['id'], (string)($_POST['category'] ?? ''));
            flash(ta('ce_doc_recat'));
        }
        redirect('/admin/collaborator-edit.php?id=' . $id);
    } elseif ($action === 'doc_statut') {
        /* [V36-FACTURES] Le bureau ne pose qu'un seul statut, « payée », et
           seulement sur une facture qu'on lui a envoyée. Ce n'est pas le
           bouton qui le décide : MemberDocs::statut() consulte la même règle
           que l'affichage et refuse tout le reste sans bruit — un statut
           sauté, un document qui appartient à quelqu'un d'autre, un envoi
           bricolé à la main. Ici on ne vérifie donc que la propriété, la
           seule chose que cette page sait et que le modèle ignore.

           L'avis part après coup, et son échec ne défait rien : la facture
           est payée, la personne l'apprendra en ouvrant son espace. */
        $doc = MemberDocs::row((int)($_POST['doc'] ?? 0));
        if ($doc && (int)$doc['collaborator_id'] === $id
            && MemberDocs::statut((int)$doc['id'], (string)($_POST['vers'] ?? ''), 'admin')) {
            try { MemberNotify::facturePayee($c, MemberDocs::row((int)$doc['id']) ?? $doc); }
            catch (Throwable $ex) { }
            flash(ta('ce_doc_paid'));
        }
        redirect('/admin/collaborator-edit.php?id=' . $id);
    } elseif ($action === 'doc_delete') {
        $doc = MemberDocs::row((int)($_POST['doc'] ?? 0));
        if ($doc && (int)$doc['collaborator_id'] === $id) MemberDocs::delete((int)$doc['id']);
        flash(ta('ce_doc_deleted')); redirect('/admin/collaborator-edit.php?id=' . $id);
    } elseif ($action === 'doc_signed') {
        $doc = MemberDocs::row((int)($_POST['doc'] ?? 0));
        if ($doc && (int)$doc['collaborator_id'] === $id) DB::update('member_documents', ['sign_status' => 'signed', 'signed_at' => date('Y-m-d H:i:s')], 'id = ?', [$doc['id']]);
        flash(ta('ce_marked_signed')); redirect('/admin/collaborator-edit.php?id=' . $id);
    } elseif ($action === 'sign_send') {
        // Envoi (ou renvoi) manuel d'un document à la signature Skribble.
        $doc = MemberDocs::row((int)($_POST['doc'] ?? 0));
        if (!$doc || (int)$doc['collaborator_id'] !== $id || $doc['ext'] !== 'pdf') {
            flash(ta('ce_doc_invalid'), 'err');
        } elseif (!Skribble::configured()) {
            flash(ta('ce_skribble_off'), 'err');
        } else {
            try {
                $res = Skribble::send(MemberDocs::filePath($doc, false), $doc['title'] ?: $doc['filename'], $c['email'], $c['mobile'], (string)($c['lang'] ?? ''));
                DB::update('member_documents', ['skribble_request_id' => $res['id'], 'skribble_signing_url' => $res['signing_url'], 'sign_status' => 'sent'], 'id = ?', [$doc['id']]);
                flash(ta('ce_sent_skribble'));
            } catch (Throwable $ex) { flash('Skribble : ' . $ex->getMessage(), 'err'); }
        }
        redirect('/admin/collaborator-edit.php?id=' . $id);
    } elseif ($action === 'sign_refresh') {
        // Vérifie l'état de signature auprès de Skribble.
        $doc = MemberDocs::row((int)($_POST['doc'] ?? 0));
        if ($doc && (int)$doc['collaborator_id'] === $id && trim((string)($doc['skribble_request_id'] ?? '')) !== '') {
            try {
                $st = strtoupper(Skribble::status($doc['skribble_request_id']));
                if ($st === 'SIGNED') {
                    DB::update('member_documents', ['sign_status' => 'signed', 'signed_at' => date('Y-m-d H:i:s')], 'id = ?', [$doc['id']]);
                    flash(ta('ce_signed_ok'));
                } else {
                    flash(ta('ce_skribble_status', $st ?: ta('ce_unknown')));
                }
            } catch (Throwable $ex) { flash('Skribble : ' . $ex->getMessage(), 'err'); }
        }
        redirect('/admin/collaborator-edit.php?id=' . $id);
    }
}

$c = DB::one('SELECT * FROM collaborators WHERE id = ?', [$id]);
$docs = MemberDocs::forMember($id);
// [V32-DOC-ASSO] Vide tant que la base n'a pas sa colonne, ou tant qu'aucune
// association n'est inscrite dans les réglages : les menus disparaissent alors
// d'eux-mêmes et la page redevient exactement celle d'avant.
$assocChoix = MemberDocs::colonneAssoc() ? MemberDocs::assocChoix() : [];

/* ---------------------------------------------------------------------------
   Les deux volets du dépôt.                                   [V33-ESPACE-3]

   $catsParVolet est construit à partir de MemberDocs::VOLETS, puis complété par
   toute rubrique réellement présente dans les documents de cette personne mais
   absente de la liste. Un document dont la rubrique aurait été renommée ou
   retirée un jour continue donc de s'afficher, au lieu de disparaître
   silencieusement d'une page qui prétend tout montrer. Il atterrit du côté
   contractuel, comme le dit MemberDocs::volet() pour une rubrique inconnue.
   --------------------------------------------------------------------------- */
$projChoix    = MemberDocs::projetChoix(I18n::$admin);
$voletTitres  = ['contrat' => ta('ce_volet_contrat'), 'projet' => ta('ce_volet_projet')];
$voletParCat  = [];
foreach (MemberDocs::CATEGORIES as $k => $_lbl) $voletParCat[$k] = MemberDocs::volet($k);
$catsParVolet = MemberDocs::VOLETS;
foreach ($docs as $d) {
    $cat = (string)$d['category'];
    if (!array_key_exists($cat, MemberDocs::CATEGORIES)
        && !in_array($cat, $catsParVolet[MemberDocs::volet($cat)], true)) {
        $catsParVolet[MemberDocs::volet($cat)][] = $cat;
    }
}

$profile = MemberProfile::get($id);
$infosFields = Forms::def('form_infos')['fields'];
$photo = $profile['photo_image_id'] ? Img::row($profile['photo_image_id']) : null;
$csrf = Auth::csrfField();

// L'état du compte, affiché à côté du mot de passe. Ces trois renseignements
// répondent aux questions qu'on se pose quand quelqu'un « n'arrive pas à se
// connecter » : y a-t-il un mot de passe, le compte est-il actif, et le blocage
// après plusieurs essais manqués est-il en train de jouer ?
$aMotDePasse = trim((string)($c['pass_hash'] ?? '')) !== '';
$echecs = (int)DB::val(
    'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND at > (NOW() - INTERVAL ' . MemberAuth::WINDOW_MIN . ' MINUTE)',
    [$c['email']]
);

// Le lien en cours, s'il y en a un et qu'il n'a pas expiré.
$lienFin  = !empty($c['reset_expires']) ? strtotime((string)$c['reset_expires']) : 0;
/* [13.08.2026] Une clé sans échéance est VIVANTE, et non morte.

   Cette ligne exigeait une date de fin. Depuis que la clé d'invitation
   n'expire plus, sa date est nulle, $lienFin vaut 0, et « 0 > maintenant » est
   faux : la fiche annonçait « Clé créée » et n'affichait jamais la clé. Anna
   l'a trouvé en une minute, en cliquant. Même faute que dans lv_acces() de la
   liste, corrigée là et pas ici : une condition écrite à deux endroits ne se
   corrige jamais qu'à un seul. */
$lienActif = !empty($c['reset_token']) && ($lienFin === 0 || $lienFin > time());
$lienUrl  = $lienActif ? MemberAuth::lienUrl((string)$c['reset_token']) : '';

admin_top(ta('ce_head') . ' — ' . $c['name'], 'collab');
?>
<div class="page-head">
  <h1><?= e(ta('ce_head')) ?> <span class="crumb">→ <?= e($c['name']) ?></span></h1>
  <div class="actions">
    <?php /* [V27-ACCES] « Voir son espace » : la seule façon de vérifier ce que
             la personne trouve en se connectant, puisque son mot de passe
             n'est lisible par personne. Nouvel onglet, comme l'impression. */ ?>
    <form method="post" target="_blank" class="inline-form"><?= $csrf ?><input type="hidden" name="action" value="voir">
      <button class="btn ghost" type="submit"><?= e(ta('ce_visit')) ?></button></form>
    <?php /* [V15-FICHE-PDF] Nouvel onglet : on ne perd pas la fiche en cours. */ ?>
    <a class="btn ghost" href="<?= e(admin_url('collaborator-print.php?id=' . $id)) ?>"
       target="_blank" rel="noopener"><?= e(ta('ce_print')) ?></a>
    <a class="btn ghost" href="<?= e(admin_url('collaborators.php')) ?>"><?= e(ta('ce_back')) ?></a>
  </div>
</div>
<?php if ($errors): ?><div class="flash err"><?php foreach ($errors as $er) echo e($er) . '<br>'; ?></div><?php endif; ?>

<div class="editgrid">
  <div class="editmain">
    <div class="panel">
      <h2><?= e(ta('ce_profile')) ?></h2>
      <?php /* [V30-FICHE-PRE] « saisi » et non « data » : la fiche propose
               désormais le nom et l'e-mail du compte dans les cases vides, pour
               que le collaborateur n'ait pas à les retaper. Ici, c'est ce qu'il
               a lui-même écrit qui compte — sans quoi toutes les fiches
               paraîtraient commencées alors qu'aucune ne l'est.

               [V31-FICHE-DEJA] La phrase reste donc affichée tant que la
               personne n'a rien enregistré, mais elle ne cache plus rien : ce
               qui lui est proposé d'avance — l'adresse, l'IBAN, ce que le
               bureau savait déjà — se lit dessous, en le sachant. */ ?>
      <?php if (!$profile['saisi']): ?>
      <p class="hint"><?= e(ta('ce_profile_empty')) ?></p>
      <?php endif; ?>
      <?php if ($profile['data'] || $profile['bio'] !== '' || $photo): ?>
      <?php if ($photo): Img::ensure($photo, 'square'); ?><div class="admin-photo"><?= Img::tag($photo, 'square', ['alt' => '']) ?></div><?php endif; ?>
      <?php if ($profile['bio'] !== ''): ?><p><strong><?= e(ta('ce_bio')) ?></strong><br><?= nl2br(e($profile['bio'])) ?></p><?php endif; ?>
      <table class="tbl"><tbody>
        <?php /* [V16-DATES] Les réponses passent par le même filtre que la fiche
                 imprimée : une date de naissance s'écrit 07.04.1990 et non
                 1990-04-07, « yes » s'écrit Oui, et une question posée sous
                 condition ne s'affiche que si la condition est remplie. */ ?>
        <?php foreach ($infosFields as $fd):
            if (in_array($fd['type'], ['section', 'file'], true)) continue;
            $v = trim((string)($profile['data'][$fd['key']] ?? ''));
            if ($v === '') continue;
            if (!empty($fd['show_if'])) {
                [$sk, $sv] = $fd['show_if'];
                if (!in_array(trim((string)($profile['data'][$sk] ?? '')), (array)$sv, true)) continue;
            }
            $v = MemberSheet::valeur($fd, $v, I18n::$admin);
        ?>
        <tr><td style="width:42%"><?= e(Forms::label($fd['label'], I18n::$admin)) ?></td><td><?= nl2br(e($v)) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2><?= e(ta('ce_documents')) ?></h2>
      <form method="post" enctype="multipart/form-data" class="mdoc-upload">
        <?= $csrf ?><input type="hidden" name="action" value="upload">
        <?php /* [V33-ESPACE-3] La rubrique commande le second menu : une pièce
                 contractuelle demande son association, un document de
                 production demande son projet. Le classement des rubriques en
                 deux familles est donc écrit dans le menu lui-même, en clair,
                 plutôt que rappelé dans une phrase que personne ne lit. */ ?>
        <div class="grid2">
          <div class="f"><label class="f-label"><?= e(ta('ce_category')) ?></label>
            <select name="category" id="lv-cat" data-volets="<?= e((string)json_encode($voletParCat)) ?>">
              <?php foreach (MemberDocs::VOLETS as $volet => $cats): ?>
              <optgroup label="<?= e($voletTitres[$volet] ?? $volet) ?>">
                <?php foreach ($cats as $k): if (!isset(MemberDocs::CATEGORIES[$k])) continue; ?><option value="<?= e($k) ?>"><?= e(tc(MemberDocs::CATEGORIES[$k])) ?></option><?php endforeach; ?>
              </optgroup>
              <?php endforeach; ?>
            </select></div>
          <?php if ($assocChoix): ?>
          <div class="f lv-volet" data-volet="contrat"><label class="f-label"><?= e(ta('ce_assoc')) ?></label>
            <select name="assoc"><option value="">—</option>
              <?php foreach ($assocChoix as $nom => $sig): ?><option value="<?= e($nom) ?>"><?= e($nom) ?><?= $sig !== '' ? ' (' . e($sig) . ')' : '' ?></option><?php endforeach; ?>
            </select>
            <p class="hint"><?= e(ta('ce_assoc_h')) ?></p></div>
          <?php endif; ?>
          <?php if ($projChoix): ?>
          <div class="f lv-volet" data-volet="projet"><label class="f-label"><?= e(ta('ce_project')) ?></label>
            <select name="project_id"><option value="">—</option>
              <?php foreach ($projChoix as $pid => $ptitre): ?><option value="<?= (int)$pid ?>"><?= e($ptitre) ?></option><?php endforeach; ?>
            </select>
            <p class="hint"><?= e(ta('ce_project_h')) ?></p></div>
          <?php endif; ?>
        </div>
        <script>
        /* Le menu de trop est masqué, pas supprimé : sans JavaScript les deux
           restent visibles et le dépôt marche quand même, car c'est le serveur
           qui écarte celui qui ne va pas avec la rubrique. Le navigateur ne
           fait ici qu'éviter une question inutile. */
        (function () {
          var sel = document.getElementById('lv-cat');
          if (!sel) return;
          var map = {};
          try { map = JSON.parse(sel.getAttribute('data-volets') || '{}'); } catch (e) { return; }
          var cases = sel.form ? sel.form.querySelectorAll('.lv-volet') : [];
          function montrer() {
            var v = map[sel.value] || 'contrat', i;
            for (i = 0; i < cases.length; i++) {
              cases[i].hidden = (cases[i].getAttribute('data-volet') !== v);
            }
          }
          sel.addEventListener('change', montrer);
          montrer();
        })();
        </script>
        <div class="f f-toggle"><label class="switch"><input type="checkbox" name="needs_signature" value="1"><span></span></label>
          <span class="f-label inline"><?= e(ta('ce_needs_sign')) ?></span></div>
        <label class="dropzone"><?= e(ta('ce_choose_files')) ?><input type="file" name="file[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple required></label>
        <p><button class="btn" type="submit"><?= e(ta('ce_add_doc')) ?></button></p>
      </form>

      <?php if (!$docs): ?>
      <p class="hint"><?= e(ta('ce_no_docs')) ?></p>
      <?php else: foreach ($catsParVolet as $volet => $catsV):
        $duVolet = array_filter($docs, fn($d) => in_array((string)$d['category'], $catsV, true));
        if (!$duVolet) continue; ?>
      <h3 class="mdoc-volet"><?= e($voletTitres[$volet] ?? $volet) ?></h3>
      <?php foreach ($catsV as $cat): $group = array_filter($duVolet, fn($d) => (string)$d['category'] === $cat); if (!$group) continue; ?>
      <h4 class="mdoc-cat"><?= e(isset(MemberDocs::CATEGORIES[$cat]) ? tc(MemberDocs::CATEGORIES[$cat]) : $cat) ?></h4>
      <table class="tbl">
        <tbody>
          <?php foreach ($group as $d):
            $needs   = (int)$d['needs_signature'] === 1;
            $st      = (string)($d['sign_status'] ?? '');
            $hasReq  = trim((string)($d['skribble_request_id'] ?? '')) !== '';
            $signUrl = trim((string)($d['skribble_signing_url'] ?? ''));
            $isPdf   = strtolower((string)$d['ext']) === 'pdf';
            /* [V36-FACTURES] Les deux questions du statut, posées au modèle et
               non devinées ici : qu'y a-t-il à lire, et qu'y a-t-il à poser ?
               Avant la mise à jour de la base, les deux répondent « rien » et
               la ligne est exactement celle d'hier. */
            $stClef = MemberDocs::statutClef($d);
            $stVers = MemberDocs::statutSuivant($d, 'admin');
            $stQuand = $stClef !== '' ? Dates::afficher((string)($d['status_at'] ?? '')) : '';
          ?>
          <tr>
            <td><strong><?= e($d['title'] ?: $d['filename']) ?></strong><br><span class="muted"><?= e($d['filename']) ?> · <?= e(Docs::human((int)$d['size'])) ?></span>
              <?php /* [V32-DOC-ASSO] Les menus s'enregistrent au changement :
                       ranger quarante documents ne doit pas demander
                       quatre-vingts clics. Sans JavaScript, le bouton OK
                       apparaît à la place.

                       [V33-ESPACE-3] Le premier menu déplace le document d'une
                       rubrique à l'autre, et le second le range là où sa
                       nouvelle rubrique l'attend : sous une association s'il est
                       contractuel, sous un projet s'il relève d'une production.
                       Un même document ne montre donc jamais les deux — c'est ce
                       qui rend le rangement lisible plutôt qu'à trous. */ ?>
              <div class="mdoc-range">
                <form method="post" class="inline-form mdoc-asso"><?= $csrf ?><input type="hidden" name="action" value="doc_cat"><input type="hidden" name="doc" value="<?= (int)$d['id'] ?>">
                  <select name="category" onchange="this.form.submit()">
                    <?php foreach (MemberDocs::VOLETS as $vv => $ccs): ?>
                    <optgroup label="<?= e($voletTitres[$vv] ?? $vv) ?>">
                      <?php foreach ($ccs as $ck): if (!isset(MemberDocs::CATEGORIES[$ck])) continue; ?><option value="<?= e($ck) ?>"<?= $ck === $cat ? ' selected' : '' ?>><?= e(tc(MemberDocs::CATEGORIES[$ck])) ?></option><?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                    <?php if (!isset(MemberDocs::CATEGORIES[$cat])): ?><option value="<?= e($cat) ?>" selected><?= e($cat) ?></option><?php endif; ?>
                  </select>
                  <noscript><button class="btn small ghost" type="submit">OK</button></noscript>
                </form>
                <?php if ($volet === 'projet'): ?>
                  <?php if ($projChoix): $pDoc = (int)($d['project_id'] ?? 0); ?>
                  <form method="post" class="inline-form mdoc-asso"><?= $csrf ?><input type="hidden" name="action" value="doc_project"><input type="hidden" name="doc" value="<?= (int)$d['id'] ?>">
                    <select name="project_id" onchange="this.form.submit()">
                      <option value="">—</option>
                      <?php foreach ($projChoix as $pid => $ptitre): ?><option value="<?= (int)$pid ?>"<?= (int)$pid === $pDoc ? ' selected' : '' ?>><?= e($ptitre) ?></option><?php endforeach; ?>
                    </select>
                    <noscript><button class="btn small ghost" type="submit">OK</button></noscript>
                  </form>
                  <?php endif; ?>
                <?php elseif ($assocChoix): $aDoc = trim((string)($d['assoc'] ?? '')); ?>
                <form method="post" class="inline-form mdoc-asso"><?= $csrf ?><input type="hidden" name="action" value="doc_assoc"><input type="hidden" name="doc" value="<?= (int)$d['id'] ?>">
                  <select name="assoc" onchange="this.form.submit()">
                    <option value="">—</option>
                    <?php foreach ($assocChoix as $nom => $sig): ?><option value="<?= e($nom) ?>"<?= $nom === $aDoc ? ' selected' : '' ?>><?= e($nom) ?><?= $sig !== '' ? ' (' . e($sig) . ')' : '' ?></option><?php endforeach; ?>
                    <?php if ($aDoc !== '' && !array_key_exists($aDoc, $assocChoix)): ?><option value="<?= e($aDoc) ?>" selected><?= e($aDoc) ?></option><?php endif; ?>
                  </select>
                  <noscript><button class="btn small ghost" type="submit">OK</button></noscript>
                </form>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <?php if ($needs): ?>
                <?php if ($st === 'signed'): ?><span class="badge ok"><?= e(ta('ce_signed')) ?></span>
                <?php elseif ($st === 'sent'): ?><span class="badge warn"><?= e(ta('ce_sent_waiting')) ?></span>
                <?php else: ?><span class="badge warn"><?= e(ta('col_to_sign')) ?></span><?php endif; ?>
              <?php endif; ?>
              <?php /* [V36-FACTURES] Le même libellé qu'au verso, dans l'espace
                       de la personne : ce que le bureau lit ici et ce qu'elle
                       lit chez elle doivent être le même mot, sinon on se
                       téléphone pour comparer. */ ?>
              <?php if ($stClef !== ''): ?><span class="mdoc-st mdoc-st-<?= e((string)$d['status']) ?>"><?= e(tu($stClef)) ?><?php if ($stQuand !== ''): ?> <span class="mdoc-st-d"><?= e($stQuand) ?></span><?php endif; ?></span><?php endif; ?>
            </td>
            <td style="text-align:right; white-space:nowrap">
              <?php if ($stVers !== ''): ?>
              <form method="post" style="display:inline"><?= $csrf ?><input type="hidden" name="action" value="doc_statut"><input type="hidden" name="doc" value="<?= (int)$d['id'] ?>"><input type="hidden" name="vers" value="<?= e($stVers) ?>"><button class="btn small" type="submit"><?= e(tu(MemberDocs::boutonClef($d, $stVers))) ?></button></form>
              <?php endif; ?>
              <a class="btn small ghost" href="<?= e(admin_url('collaborator-edit.php?id=' . $id . '&dl=' . (int)$d['id'])) ?>"><?= e(ta('com_download')) ?></a>
              <?php if ($needs && $st !== 'signed'): ?>
                <?php if (Skribble::configured() && $isPdf): ?>
                  <?php if (!$hasReq): ?>
                  <form method="post" style="display:inline"><?= $csrf ?><input type="hidden" name="action" value="sign_send"><input type="hidden" name="doc" value="<?= (int)$d['id'] ?>"><button class="btn small" type="submit"><?= e(ta('ce_send_sign')) ?></button></form>
                  <?php else: ?>
                  <form method="post" style="display:inline"><?= $csrf ?><input type="hidden" name="action" value="sign_refresh"><input type="hidden" name="doc" value="<?= (int)$d['id'] ?>"><button class="btn small" type="submit"><?= e(ta('ce_refresh_status')) ?></button></form>
                  <?php if ($signUrl !== ''): ?><a class="btn small ghost" href="<?= e($signUrl) ?>" target="_blank" rel="noopener"><?= e(ta('ce_sign_link')) ?></a><?php endif; ?>
                  <?php endif; ?>
                <?php endif; ?>
                <form method="post" style="display:inline"><?= $csrf ?><input type="hidden" name="action" value="doc_signed"><input type="hidden" name="doc" value="<?= (int)$d['id'] ?>"><button class="btn small ghost" type="submit"><?= e(ta('ce_mark_signed')) ?></button></form>
              <?php endif; ?>
              <form method="post" style="display:inline" onsubmit="return confirm('<?= e(addslashes(ta('ce_del_doc_confirm'))) ?>')"><?= $csrf ?><input type="hidden" name="action" value="doc_delete"><input type="hidden" name="doc" value="<?= (int)$d['id'] ?>"><button class="btn small ghost" type="submit">×</button></form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endforeach; endforeach; endif; ?>
    </div>
  </div>

  <div class="editside">
    <div class="panel">
      <h2><?= e(ta('ce_account')) ?></h2>
      <form method="post">
        <?= $csrf ?><input type="hidden" name="action" value="save">
        <?= field_wrap(ta('col_fullname'), '<input type="text" name="name" value="' . e($c['name']) . '" required>') ?>
        <?= field_wrap(ta('ce_email_login'), '<input type="email" name="email" value="' . e($c['email']) . '" required>') ?>
        <?= field_wrap(ta('ce_mobile'), '<input type="text" name="mobile" value="' . e($c['mobile']) . '" placeholder="+41…">') ?>
        <div class="f"><label class="f-label"><?= e(ta('ce_lang')) ?></label><select name="lang"><option value="fr"<?= ($c['lang'] ?? 'fr') === 'fr' ? ' selected' : '' ?>>Français</option><option value="en"<?= ($c['lang'] ?? '') === 'en' ? ' selected' : '' ?>>English</option></select></div>
        <div class="f f-toggle"><label class="switch"><input type="checkbox" name="active" value="1"<?= $c['active'] ? ' checked' : '' ?>><span></span></label><span class="f-label inline"><?= e(ta('ce_active')) ?></span></div>
        <button class="btn wide" type="submit"><?= e(ta('com_save')) ?></button>
      </form>
    </div>
    <div class="panel">
      <h2><?= e(ta('ce_password')) ?></h2>
      <p class="hint">
        <?= $aMotDePasse ? e(ta('ce_pass_set')) : '<strong>' . e(ta('ce_pass_none')) . '</strong>' ?>
        <?php if ($aMotDePasse && !empty($c['pass_set_at'])): ?>
          <?= e(ta('ce_pass_set_at', date('d.m.Y', strtotime((string)$c['pass_set_at'])))) ?>
        <?php endif; ?><br>
        <?= $c['last_login']
              ? e(ta('ce_last_login', date('d.m.Y H:i', strtotime((string)$c['last_login']))))
              : e(ta('ce_never_login')) ?>
        <?php if (!$c['active']): ?><br><strong><?= e(ta('ce_inactive_warn')) ?></strong><?php endif; ?>
        <?php if ($echecs): ?><br><?= e(ta('ce_attempts', (string)$echecs, (string)MemberAuth::WINDOW_MIN, (string)MemberAuth::MAX_ATTEMPTS)) ?><?php endif; ?>
      </p>
      <p class="hint"><?= e(ta('ce_pass_why')) ?></p>
      <?php /* [V27-ACCES] La question « est-ce que cette personne a bien accès ? »
               se pose ici, devant l'état du compte. La réponse est en haut de
               page : autant le dire, plutôt que de laisser chercher. */ ?>
      <p class="hint"><?= e(ta('ce_visit_help')) ?></p>

      <h3 class="mdoc-cat"><?= e(ta('ce_link_head')) ?></h3>
      <p class="hint"><?= e(ta('ce_link_help')) ?></p>
      <?php if ($lienActif): ?>
      <p class="hint"><?= e($lienFin === 0 ? ta('ce_link_sans_fin') : ta('ce_link_active', date('d.m.Y', $lienFin))) ?></p>
      <p><input type="text" id="lv-lien" value="<?= e($lienUrl) ?>" readonly spellcheck="false" onclick="this.select()"></p>
      <p>
        <button type="button" class="btn small" id="lv-copier"><?= e(ta('ce_link_copy')) ?></button>
        <span class="muted" id="lv-copie" hidden><?= e(ta('ce_link_copied')) ?></span>
      </p>
      <form method="post" style="display:inline"><?= $csrf ?><input type="hidden" name="action" value="lien_envoyer">
        <button class="btn small" type="submit"><?= e(ta('ce_link_send')) ?></button></form>
      <form method="post" style="display:inline"><?= $csrf ?><input type="hidden" name="action" value="lien">
        <button class="btn small ghost" type="submit"><?= e(ta('ce_link_remake')) ?></button></form>
      <form method="post" style="display:inline"><?= $csrf ?><input type="hidden" name="action" value="lien_annuler">
        <button class="btn small ghost" type="submit"><?= e(ta('ce_link_cancel')) ?></button></form>
      <?php else: ?>
      <p class="hint"><?= e(ta('ce_link_none')) ?></p>
      <form method="post"><?= $csrf ?><input type="hidden" name="action" value="lien_envoyer">
        <button class="btn wide" type="submit"><?= e(ta('ce_link_send')) ?></button></form>
      <?php /* Le second bouton fabrique le lien SANS l'envoyer : pour le coller
               dans un message écrit à la main, ou pour le donner de vive voix. */ ?>
      <form method="post"><?= $csrf ?><input type="hidden" name="action" value="lien">
        <button class="btn wide ghost" type="submit"><?= e(ta('ce_link_make')) ?></button></form>
      <?php endif; ?>
      <script>
      (function () {
        /* Copie du lien. Si le navigateur refuse le presse-papiers (site sans
           HTTPS, permission refusée), on sélectionne le texte : il ne reste
           plus qu'à faire Ctrl+C. */
        var cp = document.getElementById('lv-copier'), ln = document.getElementById('lv-lien'),
            ok = document.getElementById('lv-copie');
        if (cp && ln) {
          cp.addEventListener('click', function () {
            ln.focus(); ln.select();
            var fini = function () { if (ok) { ok.hidden = false; setTimeout(function () { ok.hidden = true; }, 2500); } };
            if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(ln.value).then(fini, function () {});
            } else { try { document.execCommand('copy'); fini(); } catch (e) {} }
          });
        }
      })();
      </script>
    </div>
  </div>
</div>
<?php admin_bottom();

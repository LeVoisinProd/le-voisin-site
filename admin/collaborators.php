<?php
/** Collaborateurs et documents de l'espace privé.   [V12-ESPACE] [V27-ACCES] */
require __DIR__ . '/_inc.php';
Auth::requireAdmin();

$errors = [];
$aConfirmer = [];     // les personnes à qui l'on s'apprête à écrire   [V28-INVIT]
$aSupprimer = [];     // celles que l'on s'apprête à supprimer          [V41-SUPPR]
$rapport    = null;   // le compte rendu du dernier envoi groupé

/* Le compte rendu est déposé en session juste avant une redirection, puis relu
 * ici. Sans cette redirection, actualiser la page après un envoi renverrait le
 * formulaire : toute l'équipe recevrait un second message, et les liens du
 * premier cesseraient de fonctionner. */
$restants = [];
if (!empty($_SESSION['lv_invit_rapport'])) {
    $rapport = $_SESSION['lv_invit_rapport'];
    unset($_SESSION['lv_invit_rapport']);
}
$essai = null;
if (!empty($_SESSION['lv_essai_rapport'])) {
    $essai = $_SESSION['lv_essai_rapport'];
    unset($_SESSION['lv_essai_rapport']);
}
if (!empty($_SESSION['lv_suppr_rapport'])) {
    $sr = $_SESSION['lv_suppr_rapport'];
    unset($_SESSION['lv_suppr_rapport']);
    flash(ta('col_del_done', (int)$sr['n']) . ((int)$sr['refus'] ? ' ' . ta('col_del_kept', (int)$sr['refus']) : ''));
}
if (!empty($_SESSION['lv_invit_restants'])) {
    $restants = (array)$_SESSION['lv_invit_restants'];
    unset($_SESSION['lv_invit_restants']);
}

/* [13.08.2026] Supprimer en lot, depuis les mêmes cases que l'envoi.

   Douze fiches créées par erreur de saisie, jamais servies : les ouvrir une à
   une pour les supprimer était le vrai coût. La règle, elle, ne bouge pas et
   n'est écrite qu'à un endroit, dans Collaborateurs::supprimer() : une fiche
   qui porte un document n'est pas supprimable, et l'écran de confirmation le
   montre AVANT, personne par personne, plutôt que de refuser après coup. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['lv_action'] ?? ''), ['suppr_confirmer', 'suppr'], true)) {
    Auth::requireCsrf();
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
    $gens = $ids
        ? DB::all('SELECT * FROM collaborators WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
                . ' ORDER BY name, id', $ids)
        : [];
    if (!$gens) {
        $errors[] = ta('inv_none_sel');
    } elseif ($_POST['lv_action'] === 'suppr_confirmer') {
        $aSupprimer = $gens;
    } else {
        $n = 0; $refus = 0;
        foreach ($gens as $g) {
            if (Collaborateurs::supprimer((int)$g['id'])) $n++; else $refus++;
        }
        $_SESSION['lv_suppr_rapport'] = ['n' => $n, 'refus' => $refus];
        redirect('/admin/collaborators.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['lv_action'] ?? ''), ['confirmer', 'envoyer'], true)) {
    Auth::requireCsrf();
    $ids  = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
    $gens = $ids
        ? DB::all('SELECT * FROM collaborators WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
                . ' ORDER BY name, id', $ids)
        : [];
    // On vérifie qu'un envoi est possible AVANT de toucher aux liens : chaque
    // lien neuf annule le précédent, il serait fâcheux de les annuler tous pour
    // découvrir ensuite qu'aucun message ne peut partir.
    $obstacle = Invitations::obstacle();

    if (!$gens)          $errors[] = ta('inv_none_sel');
    elseif ($obstacle)   $errors[] = $obstacle;
    elseif ($_POST['lv_action'] === 'confirmer') {
        $aConfirmer = $gens;
    } else {
        /* [13.08.2026] La boucle, avec de quoi survivre à une coupure.

           Trois précautions, et chacune répare un cas vu ou évité :

           — un BUDGET DE TEMPS. Chaque message ouvre sa propre connexion SMTP,
             avec TLS et authentification, soit une dizaine d'allers-retours par
             personne ; à soixante-dix-sept, on dépasse largement ce qu'un
             serveur web laisse durer à une requête. La boucle s'arrête donc
             d'elle-même plutôt que d'être tuée, et le compte rendu propose de
             reprendre avec celles qui restent.

           — set_time_limit(0) écarte la limite de PHP, mais PAS celle du
             serveur web, invisible d'ici. C'est bien pour cela que le budget
             existe : on ne fait pas confiance à la limite, on s'arrête avant.

           — le JOURNAL sur disque, écrit à chaque personne. Si malgré tout le
             processus est tué, c'est la seule trace qui reste. */
        @set_time_limit(0);
        @ignore_user_abort(true);
        $BUDGET = 180;
        $depart = time();

        $res = [];
        $restants = [];
        Invitations::journal('DÉBUT | ' . count($gens) . ' personne(s)');
        foreach ($gens as $g) {
            if (time() - $depart > $BUDGET) { $restants[] = (int)$g['id']; continue; }
            $r = Invitations::envoyer($g);
            $res[] = $r;
            Invitations::journal(sprintf('#%d | %s | %s | %s',
                (int)$g['id'], $r['nom'] ?: '?', $r['email'] ?: '?',
                $r['ok'] ? 'OK' : 'ÉCHEC : ' . ($r['raison'] ?: '?')));
        }
        $partis = count(array_filter($res, fn($r) => $r['ok']));
        Invitations::journal($restants
            ? sprintf('INTERROMPU | %d parti(s), %d en échec, %d non tenté(s)',
                      $partis, count($res) - $partis, count($restants))
            : sprintf('FIN | %d parti(s), %d en échec', $partis, count($res) - $partis));

        $_SESSION['lv_invit_rapport']  = $res;
        $_SESSION['lv_invit_restants'] = $restants;
        redirect('/admin/collaborators.php');
    }
}

/* [13.08.2026] Le texte de l'invitation s'écrit ici, et plus seulement dans
 * Réglages. La raison est d'usage : l'écran d'envoi est ici, avec la liste, la
 * confirmation et le bouton ; le texte qui va partir était le seul morceau
 * rangé ailleurs. On relit ce qu'on envoie au moment où on l'envoie.
 *
 * Ce sont les MÊMES quatre réglages qu'en Réglages, pas une copie : les deux
 * écrans écrivent aux mêmes clefs, donc ils ne peuvent pas diverger. L'ancien
 * bloc reste en place tant que celui-ci n'a pas fait ses preuves. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['lv_action'] ?? ''), ['texte', 'texte_essai'], true)) {
    Auth::requireCsrf();
    foreach (Invitations::CLES as $k) {
        if (array_key_exists($k, $_POST)) Settings::set($k, trim((string)$_POST[$k]));
    }
    /* [13.08.2026] L'essai part depuis ici, et plus seulement des Réglages.
       On relit ce qu'on vient d'écrire, dans sa propre boîte, sans qu'aucun
       collaborateur ni aucun vrai lien soit touché. Séparer le lieu où l'on
       écrit du lieu où l'on essaie, c'est écrire sans jamais relire. */
    /* [13.08.2026] L'envoi a son propre bouton. Il était accroché à
       « Enregistrer les réglages », ce que personne ne devine : on remplissait
       l'adresse et l'on cherchait un bouton d'envoi qui n'existait pas.
       Celui-ci enregistre PUIS envoie, dans cet ordre, sinon on relirait autre
       chose que ce qui vient d'être écrit. */
    if (($_POST['lv_action'] ?? '') === 'texte_essai' && trim((string)($_POST['lv_essai_to'] ?? '')) !== '') {
        $_SESSION['lv_essai_rapport'] = Invitations::essai(
            trim((string)$_POST['lv_essai_to']), (string)($_POST['lv_essai_lang'] ?? 'fr'));
    }
    redirect('/admin/collaborators.php?texte=1#texte-invitation');
}

/* [13.08.2026] Rétablir le texte d'origine.

   Le texte du code ne sert que TANT QUE les réglages sont vides, et les deux
   éditeurs ouvrent leurs champs déjà remplis avec le texte en vigueur. Il suffit
   donc d'avoir enregistré une fois, un jour, pour figer une copie dans la base
   et ne plus jamais voir les versions suivantes. C'est arrivé, et cela s'est vu
   le 13.08 : le message annonçait encore un mot de passe à choisir et sept jours
   de validité, longtemps après que ni l'un ni l'autre n'existent.

   Vider quatre champs à la main pour s'en sortir n'est pas une manoeuvre qu'on
   devine. Ce bouton le fait. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['lv_action'] ?? '') === 'texte_defaut') {
    Auth::requireCsrf();
    foreach (Invitations::CLES as $k) Settings::set($k, '');
    flash(ta('inv_txt_reset'));
    redirect('/admin/collaborators.php#texte-invitation');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['lv_action'])) {
    Auth::requireCsrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    if ($name === '') $errors[] = ta('col_name_req');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = ta('col_bad_email');
    if (!$errors && DB::val('SELECT id FROM collaborators WHERE email = ?', [$email])) $errors[] = ta('col_email_exists');
    if (!$errors) {
        /* [V27-ACCES] On ne demande plus de mot de passe ici.
         *
         * Le formulaire en inscrivait un au hasard, refabriqué à chaque
         * affichage de la page : il fallait le noter avant d'enregistrer,
         * sans que rien ne le dise, et il changeait sous les yeux de qui
         * revenait sur la page. D'où l'impression, parfaitement fondée, que
         * « le mot de passe change tout seul ».
         *
         * Le compte naît donc sans mot de passe, et l'on prépare aussitôt le
         * lien que la personne ouvrira pour choisir le sien. Le bureau n'a
         * plus rien à inventer, à noter, ni à transmettre de secret. */
        $id = DB::insert('collaborators', [
            'name' => $name, 'email' => $email, 'lang' => 'fr',
            'pass_hash' => '', 'active' => 1,
        ]);
        $avecLien = true;
        try {
            MemberAuth::lienNouveau($id);
        } catch (Throwable $e) {
            // Les colonnes du lien n'existent qu'après « Mettre à jour la
            // base » : le compte est créé quand même, on le dit franchement.
            $avecLien = false;
        }
        flash(ta($avecLien ? 'col_created' : 'col_created_nolink'));
        redirect('/admin/collaborator-edit.php?id=' . $id);
    }
}

$list = DB::all('SELECT * FROM collaborators ORDER BY name, id');
$pending = DB::all(
    "SELECT d.*, c.name AS cname FROM member_documents d
     JOIN collaborators c ON c.id = d.collaborator_id
     WHERE d.needs_signature = 1 AND d.sign_status <> 'signed' ORDER BY d.created_at"
);

/**
 * Où en est l'accès de cette personne.   [V27-ACCES]
 *
 * Renvoie [classe de pastille, texte]. L'ordre des questions est celui du
 * bon sens : une connexion réussie prouve tout le reste, donc on la regarde
 * en premier. Un compte désactivé passe avant tout : le mot de passe peut
 * être parfait, l'entrée est fermée.
 */
function lv_acces(array $c): array
{
    if (empty($c['active']))                    return ['off',  ta('col_acc_off')];
    if (!empty($c['last_login']))               return ['ok',   ta('col_acc_ok', Dates::afficherHeure((string)$c['last_login']))];
    /* [13.08.2026] Une clé sans échéance est une clé VIVANTE.

       Cette ligne exigeait une date de fin pour reconnaître un lien en cours.
       Depuis que l'invitation n'expire plus, sa date est nulle, et la colonne
       aurait affiché « aucun accès » à toutes les personnes qui viennent d'en
       recevoir une. La condition porte donc sur le jeton, et l'échéance ne
       disqualifie que si elle existe et qu'elle est passée. */
    if (!empty($c['reset_token'])
        && (empty($c['reset_expires']) || strtotime((string)$c['reset_expires']) > time())) {
        return ['warn', ta('col_acc_link')];
    }
    /* Le mot de passe ne vaut plus « compte prêt » depuis qu'il n'y en a plus,
       mais la colonne existe encore en base pour d'anciens comptes : on la lit
       après le jeton, et non avant, pour ne pas masquer une clé en cours. */
    if (trim((string)($c['pass_hash'] ?? '')) !== '') return ['', ta('col_acc_ready')];
    return ['warn', ta('col_acc_none')];
}

/** Le nom de la langue d'écriture d'une personne.   [V28-INVIT] */
function lv_langue_nom(?string $l): string
{
    return Invitations::langue($l) === 'en' ? 'English' : 'Français';
}

/* ---- Écran de confirmation ------------------------------------------------
 *
 * Un envoi groupé ne se rattrape pas : le message est parti, et le lien
 * précédent de chacun ne fonctionne plus. On montre donc d'abord la liste
 * exacte des destinataires, avec l'adresse et la langue de chacun, et rien
 * d'autre sur la page — pas de tableau à côté où l'on croirait pouvoir encore
 * cocher quelqu'un. */
if ($aSupprimer):
    admin_top(ta('nav_collab'), 'collab');
    $peut = []; $nePeutPas = [];
    foreach ($aSupprimer as $g) {
        $nd = Collaborateurs::documents((int)$g['id']);
        if ($nd > 0) $nePeutPas[] = [$g, $nd]; else $peut[] = $g;
    }
    ?>
<div class="page-head"><h1><?= e(ta('col_del_head')) ?></h1></div>
<div class="panel">
  <?php if ($peut): ?>
  <p class="hint"><?= e(ta('col_del_intro', count($peut))) ?></p>
  <table class="tbl"><tbody>
    <?php foreach ($peut as $g): ?>
    <tr><td><strong><?= e($g['name']) ?></strong></td><td><?= e($g['email']) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>

  <?php /* Celles qu'on ne peut pas supprimer sont montrées AVANT, avec la
           raison chiffrée. Refuser après coup, sans dire lesquelles, oblige à
           recommencer à l'aveugle. */ ?>
  <?php if ($nePeutPas): ?>
  <p class="hint"><?= e(ta('col_del_gardees', count($nePeutPas))) ?></p>
  <table class="tbl"><tbody>
    <?php foreach ($nePeutPas as [$g, $nd]): ?>
    <tr><td><strong><?= e($g['name']) ?></strong></td><td><?= e($g['email']) ?></td>
        <td><?= e(ta('col_del_ndocs', $nd)) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>

  <form method="post" style="margin-top:18px;">
    <?= Auth::csrfField() ?>
    <?php foreach ($peut as $g): ?><input type="hidden" name="ids[]" value="<?= (int)$g['id'] ?>"><?php endforeach; ?>
    <?php if ($peut): ?>
    <button class="btn ce-del" type="submit" name="lv_action" value="suppr"><?= e(ta('col_del_go', count($peut))) ?></button>
    <?php endif; ?>
    <a class="btn ghost" href="<?= e(admin_url('collaborators.php')) ?>"><?= e(ta('inv_conf_cancel')) ?></a>
  </form>
</div>
<?php
    admin_bottom();
    exit;
endif;

if ($aConfirmer):
    admin_top(ta('nav_collab'), 'collab');
    ?>
<div class="page-head"><h1><?= e(ta('inv_conf_head')) ?></h1></div>
<div class="panel">
  <p class="hint"><?= e(ta('inv_conf_intro', count($aConfirmer))) ?></p>
  <table class="tbl">
    <?php /* [13.08.2026] Deux colonnes de plus, pour que cet écran dise ce
             qu'il taisait. L'ÉTAT D'ACCÈS d'abord : la fonction existait et
             servait déjà dans la liste, mais pas ici, si bien qu'on renvoyait
             une invitation à quelqu'un déjà installé sans le voir. Et la
             LANGUE : tout ce qui n'est pas exactement « en » devient français
             en silence, donc une colonne vide se lit « Français » sans se
             distinguer d'un choix délibéré. On le dit. */ ?>
    <thead><tr><th><?= e(ta('col_th_name')) ?></th><th><?= e(ta('col_th_email')) ?></th><th><?= e(ta('inv_th_lang')) ?></th><th><?= e(ta('col_th_access')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($aConfirmer as $g): [$cls, $lib] = lv_acces($g); ?>
      <tr>
        <td><strong><?= e($g['name']) ?></strong></td>
        <td><?= e($g['email']) ?></td>
        <td><?= e(lv_langue_nom($g['lang'] ?? '')) ?><?php if (trim((string)($g['lang'] ?? '')) === ''): ?>
            <span class="hint"><?= e(ta('inv_lang_defaut')) ?></span><?php endif; ?></td>
        <td<?= $cls ? ' class="' . e($cls) . '"' : '' ?>><?= e($lib) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="hint"><?= e(ta('inv_conf_w1b')) ?></p>
  <form method="post" style="margin-top:18px;">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="lv_action" value="envoyer">
    <?php foreach ($aConfirmer as $g): ?>
    <input type="hidden" name="ids[]" value="<?= (int)$g['id'] ?>">
    <?php endforeach; ?>
    <button class="btn" type="submit"><?= e(ta('inv_conf_go', count($aConfirmer))) ?></button>
    <a class="btn ghost" href="<?= e(admin_url('collaborators.php')) ?>"><?= e(ta('inv_conf_cancel')) ?></a>
  </form>
</div>
<?php
    admin_bottom();
    exit;
endif;

admin_top(ta('nav_collab'), 'collab');
?>
<div class="page-head"><h1><?= e(ta('nav_collab')) ?></h1></div>

<?php if ($errors): ?><div class="flash err"><?php foreach ($errors as $er) echo e($er) . '<br>'; ?></div><?php endif; ?>

<?php if (!empty($_GET['texte'])): ?><div class="flash ok"><?= e(ta('st_saved')) ?></div><?php endif; ?>

<?php /* [13.08.2026] Reprendre l'envoi là où le budget de temps l'a arrêté.
         Sans ce bouton, il faudrait retrouver à la main qui n'a pas reçu,
         c'est-à-dire exactement ce que le compte rendu ne dit pas. */ ?>
<?php if ($restants): ?>
<div class="panel">
  <h2><?= e(ta('inv_rest_head', count($restants))) ?></h2>
  <p class="hint"><?= e(ta('inv_rest_h')) ?></p>
  <form method="post">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="lv_action" value="confirmer">
    <?php foreach ($restants as $rid): ?><input type="hidden" name="ids[]" value="<?= (int)$rid ?>"><?php endforeach; ?>
    <button class="btn" type="submit"><?= e(ta('inv_rest_go', count($restants))) ?></button>
  </form>
</div>
<?php endif; ?>

<?php /* Le journal des envois, replié. C'est la seule trace qui survit à une
         coupure, parce qu'il s'écrit personne par personne et non à la fin. */ ?>
<?php $jrn = Invitations::journalLire(); ?>
<?php if ($jrn !== ''): ?>
<details class="panel">
  <summary style="cursor:pointer;font-weight:600;"><?= e(ta('inv_jrn_head')) ?></summary>
  <p class="hint" style="margin-top:12px;"><?= e(ta('inv_jrn_h')) ?></p>
  <pre style="overflow-x:auto;font-size:12.5px;line-height:1.6;margin:0;"><?= e($jrn) ?></pre>
</details>
<?php endif; ?>

<?php /* ---- Le message d'invitation, replié ----
         Replié, parce qu'on l'ouvre une fois par saison et qu'un bloc de
         quatre champs déroulé pousserait la liste hors de l'écran. Les champs
         ne sont jamais vides : ils s'ouvrent sur le texte en vigueur, qu'on
         relit et qu'on retouche, plutôt que sur un carré blanc. */ ?>
<details class="panel" id="texte-invitation">
  <summary style="cursor:pointer;font-weight:600;"><?= e(ta('st_p_invite')) ?></summary>
  <p class="hint" style="margin-top:14px;"><?= ta('st_invite_h') ?></p>
  <p class="hint"><?= ta('st_invite_marks') ?></p>
  <p class="hint"><?= ta('st_invite_mise') ?></p>
  <form method="post" action="/admin/collaborators.php">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="lv_action" value="texte">
    <div class="grid2">
      <?= field_wrap(ta('st_f_inv_sub_fr'),
          '<input type="text" name="invite_subject_fr" value="' . e(trim((string)setting('invite_subject_fr', '')) ?: Invitations::sujetDefaut('fr')) . '">') ?>
      <?= field_wrap(ta('st_f_inv_sub_en'),
          '<input type="text" name="invite_subject_en" value="' . e(trim((string)setting('invite_subject_en', '')) ?: Invitations::sujetDefaut('en')) . '">') ?>
      <?= field_wrap(ta('st_f_inv_txt_fr'),
          '<textarea name="invite_body_fr" rows="13">' . e(trim((string)setting('invite_body_fr', '')) ?: Invitations::texteDefaut('fr')) . '</textarea>') ?>
      <?= field_wrap(ta('st_f_inv_txt_en'),
          '<textarea name="invite_body_en" rows="13">' . e(trim((string)setting('invite_body_en', '')) ?: Invitations::texteDefaut('en')) . '</textarea>') ?>
    </div>
    <p><button class="btn" type="submit" name="lv_action" value="texte"><?= e(ta('st_save')) ?></button></p>

    <hr style="border:0;border-top:1px solid #e4e4e0;margin:22px 0 18px;">
    <?php /* L'essai, juste sous les champs : on écrit, on enregistre, on se
             l'envoie, on le relit. Aucun collaborateur n'est touché et le lien
             de l'exemple ne mène nulle part. */ ?>
    <p class="hint"><?= e(ta('st_inv_test_h')) ?></p>
    <?php if ($essai): ?>
    <div class="flash <?= $essai['ok'] ? 'ok' : 'err' ?>"><?= e($essai['ok']
        ? ta('st_inv_test_ok', $essai['email'])
        : ta('st_inv_test_ko', $essai['raison'])) ?></div>
    <?php endif; ?>
    <div class="grid2">
      <?php /* L'adresse est PRÉ-REMPLIE et non suggérée en gris : un texte gris
               se lit comme une valeur, on appuie sur envoyer, et rien ne part. */ ?>
      <?= field_wrap(ta('st_f_inv_testto'),
          '<input type="email" name="lv_essai_to" value="'
          . e((string)(Auth::user()['email'] ?? setting('contact_email', ''))) . '">') ?>
      <div class="f"><label class="f-label"><?= e(ta('st_f_inv_testlang')) ?></label>
        <select name="lv_essai_lang"><option value="fr">Français</option><option value="en">English</option></select>
      </div>
    </div>
    <p><button class="btn" type="submit" name="lv_action" value="texte_essai"><?= e(ta('inv_essai_btn')) ?></button></p>
    <p class="hint"><?= e(ta('inv_essai_h')) ?></p>
  </form>

  <?php /* Hors du formulaire au-dessus : sinon ce bouton emporterait avec lui
           le contenu des quatre champs, qu'il est justement là pour effacer. */ ?>
  <form method="post" style="margin-top:14px;">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="lv_action" value="texte_defaut">
    <button class="btn small ghost" type="submit"><?= e(ta('inv_txt_reset_btn')) ?></button>
    <p class="hint"><?= e(ta('inv_txt_reset_h')) ?></p>
  </form>
</details>

<?php /* ---- Compte rendu du dernier envoi groupé ----   [V28-INVIT]
         Ligne par ligne, avec la raison exacte des échecs : un envoi qui
         « n'a pas marché » sans plus de détail ne se répare pas. */ ?>
<?php if ($rapport):
    $nOk = count(array_filter($rapport, fn($r) => $r['ok']));
    $nKo = count($rapport) - $nOk; ?>
<div class="panel">
  <h2><?= e(ta('inv_rap_head')) ?></h2>
  <p class="hint"><?= e($nKo ? ta('inv_rap_mixed', $nOk, $nKo) : ta('inv_rap_all', $nOk)) ?></p>
  <table class="tbl">
    <tbody>
      <?php foreach ($rapport as $r): ?>
      <tr>
        <td><strong><?= e($r['nom']) ?></strong></td>
        <td><?= e($r['email']) ?></td>
        <td><span class="badge<?= $r['ok'] ? ' ok' : ' warn' ?>"><?= e($r['ok'] ? ta('inv_rap_sent') : ta('inv_rap_failed')) ?></span></td>
        <td><?= $r['ok'] ? '' : e($r['raison']) ?></td>
        <td style="text-align:right"><a class="btn small ghost" href="<?= e(admin_url('collaborator-edit.php?id=' . (int)$r['id'])) ?>"><?= e(ta('com_open')) ?></a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($nKo): ?><p class="hint"><?= e(ta('inv_rap_help')) ?></p><?php endif; ?>
</div>
<?php endif; ?>

<?php /* [V27-ACCES] Trois phrases avant tout le reste. Le système de mots de
         passe n'est pas compliqué, il est seulement contre-intuitif : on ne
         donne pas un mot de passe, on donne le droit d'en choisir un. Tant
         que cela n'est écrit nulle part, on le cherche là où il n'est pas. */ ?>
<div class="panel">
  <h2><?= e(ta('col_how_head')) ?></h2>
  <p class="hint"><?= e(ta('col_how_1')) ?></p>
  <p class="hint"><?= e(ta('col_how_2')) ?></p>
  <p class="hint"><?= e(ta('col_how_3')) ?></p>
</div>

<div class="panel">
  <h2><?= e(ta('col_add')) ?></h2>
  <p class="hint"><?= e(ta('col_add_help')) ?></p>
  <form method="post">
    <?= Auth::csrfField() ?>
    <div class="grid2">
      <?= field_wrap(ta('col_fullname'), '<input type="text" name="name" required>', '', true) ?>
      <?= field_wrap(ta('col_email'), '<input type="email" name="email" required>', ta('col_email_help'), true) ?>
    </div>
    <p><button class="btn" type="submit"><?= e(ta('col_create')) ?></button></p>
  </form>
</div>

<div class="panel">
  <h2><?= e(ta('col_accounts', count($list))) ?></h2>
  <?php if (!$list): ?><p class="hint"><?= e(ta('col_none')) ?></p><?php else: ?>
  <?php /* [V28-INVIT] Cocher, puis « Envoyer les accès » : chacun reçoit son
           propre lien, dans sa langue. Rien ne part de cet écran — le bouton
           mène d'abord à la liste des destinataires. */ ?>
  <form method="post" id="lv-envoi">
    <?= Auth::csrfField() ?>
    <?php /* [13.08.2026] Un filet, et voici contre quoi. L'action vient du nom
             du bouton, pour que deux gestes partent de la même sélection. Mais
             un formulaire peut partir SANS bouton — la touche Entrée, une
             extension, un navigateur pressé — et l'action arriverait vide : la
             page tomberait alors dans la branche qui CRÉE un collaborateur, à
             deux pas de celle qui en supprime douze. Ce champ donne une valeur
             par défaut inoffensive, et les boutons la remplacent parce qu'ils
             viennent après : à nom égal, PHP garde le dernier. */ ?>
    <input type="hidden" name="lv_action" value="confirmer">
  <table class="tbl">
    <thead><tr>
      <th style="width:1%"><input type="checkbox" id="lv-tout" title="<?= e(ta('inv_pick_all')) ?>"></th>
      <th><?= e(ta('col_th_name')) ?></th><th><?= e(ta('col_th_email')) ?></th><th><?= e(ta('col_th_access')) ?></th><th><?= e(ta('col_th_docs')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php foreach ($list as $c):
          $nd = (int)DB::val('SELECT COUNT(*) FROM member_documents WHERE collaborator_id = ?', [$c['id']]);
          $ns = (int)DB::val("SELECT COUNT(*) FROM member_documents WHERE collaborator_id = ? AND needs_signature = 1 AND sign_status <> 'signed'", [$c['id']]);
          [$cl, $txt] = lv_acces($c); ?>
      <tr>
        <?php /* Un compte désactivé n'a pas de case : lui écrire enverrait un
                 lien qui ne peut pas ouvrir la porte. */ ?>
        <td><?php if (!empty($c['active'])): ?><input type="checkbox" class="lv-pick" name="ids[]" value="<?= (int)$c['id'] ?>"><?php endif; ?></td>
        <td><strong><?= e($c['name']) ?></strong></td>
        <td><?= e($c['email']) ?></td>
        <?php /* [V27-ACCES] La colonne « Actif » disait oui ou non ; elle ne
                 disait pas si la personne pouvait entrer. Celle-ci le dit. */ ?>
        <td><span class="badge acces<?= $cl !== '' ? ' ' . $cl : '' ?>"><?= e($txt) ?></span></td>
        <td><?= $nd ?><?php if ($ns): ?> · <span class="badge warn"><?= e(ta('col_to_sign_n', $ns)) ?></span><?php endif; ?></td>
        <td style="text-align:right"><a class="btn small ghost" href="<?= e(admin_url('collaborator-edit.php?id=' . $c['id'])) ?>"><?= e(ta('com_open')) ?></a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
    <?php /* [13.08.2026] Deux actions pour une même sélection. L'action n'est
             plus un champ caché mais le nom du bouton : celui sur lequel on
             appuie décide, et les deux gestes partent des mêmes cases. */ ?>
    <p style="margin:16px 0 4px;">
      <button class="btn" type="submit" name="lv_action" value="confirmer"><?= e(ta('inv_send_btn')) ?></button>
      <button class="btn ghost ce-del" type="submit" name="lv_action" value="suppr_confirmer" style="margin-left:10px;"><?= e(ta('col_del_btn')) ?></button>
    </p>
    <p class="hint"><?= e(ta('inv_send_help')) ?></p>
    <p class="hint"><?= e(ta('col_del_help')) ?></p>
  </form>
  <script>
  (function () {
    var tout = document.getElementById('lv-tout');
    if (!tout) return;
    tout.addEventListener('change', function () {
      var cases = document.querySelectorAll('#lv-envoi .lv-pick');
      for (var i = 0; i < cases.length; i++) cases[i].checked = tout.checked;
    });
  })();
  </script>
  <?php endif; ?>
</div>

<div class="panel">
  <h2><?= e(ta('col_signatures')) ?></h2>
  <?php if (!$pending): ?>
  <p class="hint"><?= e(ta('col_sign_none')) ?></p>
  <?php else: ?>
  <p class="hint"><?= e(ta('col_sign_hint')) ?></p>
  <table class="tbl">
    <thead><tr><th><?= e(ta('col_th_collab')) ?></th><th><?= e(ta('col_th_doc')) ?></th><th><?= e(ta('col_th_status')) ?></th><th></th></tr></thead>
    <tbody>
      <?php foreach ($pending as $d): ?>
      <tr>
        <td><?= e($d['cname']) ?></td>
        <td><?= e($d['title'] ?: $d['filename']) ?></td>
        <td><span class="badge warn"><?= e(ta('col_to_sign')) ?></span></td>
        <td style="text-align:right"><a class="btn small ghost" href="<?= e(admin_url('collaborator-edit.php?id=' . $d['collaborator_id'])) ?>"><?= e(ta('com_view')) ?></a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php admin_bottom();

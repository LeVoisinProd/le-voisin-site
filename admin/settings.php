<?php
/**
 * Réglages du site — [V10-CMS-BILINGUE] (30.07.2026).
 *
 * V10 : l'écran est bilingue. Tous les libellés, aides et messages du rapport
 * de contrôle passent par ta(), clefs « st_… » dans app/i18n/admin.fr.php et
 * admin.en.php. Le rapport n'essaie plus de reconnaître une phrase française
 * dans l'erreur SMTP : il lit Smtp::$code, un mot court qui ne change jamais
 * de langue.
 *
 * V9-MOTDEPASSE (29.07.2026) : le mot de passe d'envoi est débarrassé de ses
 * espaces de bord au moment de l'enregistrement. Un mot de passe collé depuis
 * un gestionnaire ou un courriel emporte presque toujours une espace ou un
 * retour à la ligne invisible ; le serveur le refusait alors avec un « 535 mot
 * de passe invalide » parfaitement incompréhensible, puisque le mot de passe
 * avait l'air juste. Le contrôle le signale désormais au lieu de le taire, et
 * quand le serveur refuse vraiment le compte, il énumère les trois causes qui
 * expliquent presque tous les refus chez Infomaniak.
 *
 * V7-ENVOI : le bouton « Enregistrer et tester l'envoi ».
 * Quand un formulaire ne part pas, la question n'est jamais « est-ce que ça
 * marche » — c'est « qu'est-ce qui bloque ». Le contrôle déroule donc toute la
 * chaîne, dans l'ordre, et s'arrête sur le premier maillon fautif en le
 * nommant : mot de passe refusé, port fermé, adresse de destination oubliée.
 * Il remplace le fichier de diagnostic qu'il fallait déposer puis effacer.
 *
 * V5-ADMIN : « Présentation pied de page (FR/EN) », les une ou deux phrases
 * affichées sous « LE VOISIN » dans la première colonne du pied de page noir.
 *
 * V3-SMTP : le panneau « Envoi des emails (SMTP) ». Le serveur d'Infomaniak
 * n'autorise pas l'envoi direct par PHP, il faut donc donner au site une
 * vraie boîte e-mail et son mot de passe.
 */
require __DIR__ . '/_inc.php';
Auth::requireAdmin();

$KEYS = [
    'site_name', 'contact_address', 'contact_phone', 'contact_email',
    'contact_hours_fr', 'contact_hours_en', 'instagram_url', 'newsletter_url',
    'footer_about_fr', 'footer_about_en', 'footer_note_fr', 'footer_note_en',
    'ga_id', 'cookies_mode', 'cookie_title_fr', 'cookie_title_en', 'cookie_text_fr', 'cookie_text_en',
    'form_infos_to', 'form_expenses_to', 'form_expenses_bexio', 'form_assoc_options',
    'yt_channel_id', 'mail_from', 'donate_url', 'linkedin_url', 'social_webhook',
    'pro_cms_url', 'pro_dashboard_url', 'pro_dashboard_label',
    'skribble_username', 'skribble_api_key', 'skribble_quality',
    'smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user',
];
// Le message d'invitation des collaborateurs, en français et en anglais. [V28-INVIT]
$KEYS = array_merge($KEYS, Invitations::CLES);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    foreach ($KEYS as $k) {
        if (array_key_exists($k, $_POST)) Settings::set($k, trim((string)$_POST[$k]));
    }
    // Mot de passe SMTP : le champ est toujours affiché vide, pour ne jamais
    // écrire le mot de passe dans le code de la page. Laisser vide conserve
    // celui déjà enregistré ; taper « - » l'efface.
    // On retire les espaces de bord : un mot de passe collé emporte souvent un
    // espace ou un retour à la ligne invisible, et le serveur le refuse alors
    // sans qu'on puisse deviner pourquoi.
    // On note aussi la date du changement.   [V21-MDP]
    // Sans elle, un second essai qui échoue ne dit pas si le mot de passe
    // essayé est celui qu'on vient de coller ou le précédent — et l'on cherche
    // alors du côté du serveur un défaut qui est dans le champ resté vide.
    $mdp = trim((string)($_POST['smtp_pass'] ?? ''));
    if ($mdp === '-')    { Settings::set('smtp_pass', ''); Settings::set('smtp_pass_at', ''); }
    elseif ($mdp !== '') { Settings::set('smtp_pass', $mdp); Settings::set('smtp_pass_at', date('Y-m-d H:i')); }
    $mdpSaisi = ($mdp !== '' && $mdp !== '-');
    $logo = (int)($_POST['logo_image_id'] ?? 0);
    Settings::set('logo_image_id', $logo > 0 ? (string)$logo : '');
    Settings::set('keep_submissions', empty($_POST['keep_submissions']) ? '0' : '1');
    if (!in_array(setting('cookies_mode'), ['simple', 'advanced', 'off'], true)) Settings::set('cookies_mode', 'advanced');
    flash(ta('st_saved'));

    // « Enregistrer et tester l'envoi » : on enregistre d'abord (le contrôle
    // doit porter sur ce qui vient d'être saisi, mot de passe compris), puis on
    // affiche le rapport sans quitter la page — sinon il faudrait retaper le
    // mot de passe pour réessayer.
    if (empty($_POST['lv_test']) && empty($_POST['lv_invit_test'])) redirect('/admin/settings.php');
    if (!empty($_POST['lv_test'])) {
        $rapport = lv_controle_envoi(trim((string)($_POST['lv_test_to'] ?? '')), $mdpSaisi);
    }
    // « M'envoyer un exemple » : le texte qui vient d'être enregistré, expédié
    // à soi-même. On le relit dans sa boîte, tel que le recevra l'équipe, sans
    // qu'aucun collaborateur ni aucun lien réel soit touché.   [V28-INVIT]
    if (!empty($_POST['lv_invit_test'])) {
        $rapportInvit = Invitations::essai(
            trim((string)($_POST['lv_invit_test_to'] ?? '')),
            (string)($_POST['lv_invit_test_lang'] ?? 'fr')
        );
    }
}

function s_in(string $key, string $type = 'text', string $ph = ''): string
{
    return '<input type="' . $type . '" name="' . e($key) . '" value="' . e(setting($key)) . '"' . ($ph ? ' placeholder="' . e($ph) . '"' : '') . '>';
}

/**
 * Le texte enregistré, ou celui d'origine s'il n'y en a pas.   [V28-INVIT]
 *
 * Un champ vide avec un texte par défaut invisible se retouche mal : on ne
 * sait pas ce qu'on modifie. Le champ s'ouvre donc sur le texte réellement
 * envoyé, et l'effacer complètement rétablit celui d'origine.
 */
function s_invit(string $cle, string $defaut): string
{
    $v = trim(setting($cle, ''));
    return $v !== '' ? $v : $defaut;
}

/** Une adresse manifestement laissée telle quelle depuis un exemple. */
function lv_adresse_exemple(string $mail): bool
{
    $d = strtolower(substr(strrchr($mail, '@') ?: '', 1));
    return in_array($d, ['example.com', 'example.org', 'example.net', 'exemple.com', 'test.com'], true);
}

/**
 * Contrôle de bout en bout de la chaîne d'envoi.   [V7-ENVOI]
 *
 * Un formulaire qui « ne part pas » peut échouer à cinq endroits différents,
 * et le visiteur ne voit qu'une phrase d'excuse identique dans tous les cas.
 * On déroule donc la chaîne dans l'ordre où elle casse en pratique :
 *
 *   1. y a-t-il un serveur d'envoi ?      (sans lui, rien n'est possible ici)
 *   2. le serveur accepte-t-il le compte ? (mot de passe, port, chiffrement)
 *   3. un vrai message arrive-t-il ?       (l'essai grandeur nature)
 *   4. où sont censés arriver les envois ? (adresses des formulaires)
 *   5. qu'a dit le serveur les dernières fois ? (le journal)
 *
 * Rien de secret n'est affiché : du mot de passe, on ne montre que le nombre
 * de caractères enregistrés — de quoi repérer une saisie vide ou tronquée sans
 * jamais le laisser lire à quelqu'un qui passerait derrière l'écran.
 */
function lv_controle_envoi(string $destination, bool $mdpSaisi = false): array
{
    $r = [];
    $bloc = static function (string $etat, string $titre, string $texte, string $detail = '') use (&$r): void {
        $r[] = ['etat' => $etat, 'titre' => $titre, 'texte' => $texte, 'detail' => $detail];
    };

    // ---- 1. La voie d'envoi -------------------------------------------
    $hote = trim((string)setting('smtp_host', ''));
    $user = trim((string)setting('smtp_user', ''));
    $pass = (string)setting('smtp_pass', '');

    if ($hote === '') {
        $bloc('ko', ta('st_d_route'),
            function_exists('mail') ? ta('st_d_nosmtp_mail') : ta('st_d_nosmtp_nomail'));
    } else {
        $bloc('ok', ta('st_d_route'), ta('st_d_route_ok', $hote,
            (setting('smtp_port', '587') ?: '587'), (setting('smtp_secure', 'tls') ?: 'tls')));

        // ---- 2. Le compte -------------------------------------------------
        if ($user === '') {
            $bloc('ko', ta('st_d_account'), ta('st_d_nouser'));
        } elseif ($pass === '') {
            $bloc('ko', ta('st_d_account'), ta('st_d_nopass', $user));
        } else {
            // Un mot de passe collé emporte souvent un espace invisible. Il est
            // désormais ignoré à l'envoi, mais on le signale : c'est le genre de
            // détail qui fait chercher pendant une heure du côté du serveur.
            $net   = trim($pass);
            $souci = ($net !== $pass);
            $notes = $souci ? ta('st_d_pass_ws') : '';

            // Lequel des deux vient d'être essayé ?   [V21-MDP]
            // Le champ s'affiche toujours vide, et le laisser vide conserve
            // l'ancien mot de passe. Après un premier refus, on relance donc le
            // test sans rien retaper : c'est l'ancien qui repart, le refus est
            // identique, et l'on en tire une conclusion fausse. Autant le dire.
            if ($mdpSaisi) {
                $notes .= ta('st_d_pass_new');
            } else {
                $quand  = trim((string)setting('smtp_pass_at', ''));
                $date   = $quand !== '' ? strtotime($quand) : false;
                $notes .= $date
                    ? ta('st_d_pass_old', date('d.m.Y H:i', $date))
                    : ta('st_d_pass_old_nodate');
            }

            // Un mot de passe d'appareil Infomaniak est fait de lettres et de
            // chiffres. Tout le reste trahit le collage plutôt que le serveur :
            // un espace au milieu, un caractère accentué venu d'un traitement
            // de texte, ou une entité ramassée en copiant depuis une page web.
            $formes = [];
            if (preg_match('/\s/u', $net))          $formes[] = ta('st_d_shape_space');
            if (preg_match('/[^\x20-\x7E]/', $net)) $formes[] = ta('st_d_shape_odd');
            if (preg_match('/&(?:amp|quot|lt|gt|apos|#0?39);/i', $net)) $formes[] = ta('st_d_shape_html');
            if ($formes) {
                $souci  = true;
                $notes .= ta('st_d_shape', implode(', ', $formes));
            }

            $bloc($souci ? 'attention' : 'ok', ta('st_d_account'),
                ta('st_d_pass_ok', $user, (string)mb_strlen($net)) . $notes);
        }

        $expediteur = trim((string)setting('mail_from', ''));
        if ($expediteur !== '' && $user !== '' && strcasecmp($expediteur, $user) !== 0) {
            $bloc('attention', ta('st_d_from'), ta('st_d_from_bad', $expediteur, $user));
        }

        // ---- 3. Connexion et authentification ------------------------------
        $verifOk = Smtp::verifie();
        $trace   = Smtp::$trace;

        // Quand le serveur refuse le compte, dire « échec » ne sert à rien : la
        // question suivante est toujours « oui, mais pourquoi ? ». On énumère
        // donc les trois causes qui expliquent presque tous les refus.
        // Smtp::$code ne change pas de langue, contrairement au message.
        $aide = '';
        if (!$verifOk && Smtp::$code === 'auth') {
            $aide = '<div class="lv-r-aide">'
                  . '<p>' . ta('st_help_1') . '</p>'
                  . '<p>' . ta('st_help_2', e($user)) . '</p>'
                  . '<p>' . ta('st_help_3') . '</p><ul>'
                  . '<li>' . ta('st_help_4') . '</li>'
                  . '<li>' . ta('st_help_5', e($user)) . '</li>'
                  . '</ul>'
                  . '<p class="lv-r-note">' . ta('st_help_6') . '</p>'
                  . '</div>';
        }

        $bloc($verifOk ? 'ok' : 'ko', ta('st_d_conn'),
            $verifOk ? ta('st_d_conn_ok') : ta('st_d_fail', Smtp::$erreur),
            $aide . ($trace ? '<details><summary>' . e(ta('st_d_dialog')) . '</summary><pre>' . e(implode("\n", $trace)) . '</pre></details>' : ''));

        // ---- 4. Un vrai message ---------------------------------------------
        if ($verifOk) {
            if ($destination === '' || !filter_var($destination, FILTER_VALIDATE_EMAIL)) {
                $destination = setting('contact_email', '') ?: $user;
            }
            $envoiOk = Mailer::send([$destination], ta('st_d_test_subj', setting('site_name', 'Le Voisin')),
                Mailer::wrap(ta('st_d_test_h'), ta('st_d_test_body')));
            $bloc($envoiOk ? 'ok' : 'ko', ta('st_d_test'),
                $envoiOk ? ta('st_d_test_ok', $destination) : ta('st_d_fail', Smtp::$erreur),
                $envoiOk ? '' : '<details><summary>' . e(ta('st_d_dialog')) . '</summary><pre>' . e(implode("\n", Smtp::$trace)) . '</pre></details>');
        }
    }

    // ---- 5. Où arrivent les formulaires ----------------------------------
    foreach ([
        'form_infos_to'    => ta('st_d_form_infos'),
        'form_expenses_to' => ta('st_d_form_exp'),
    ] as $cle => $titre) {
        $adr = Settings::emails($cle);
        if (!$adr) {
            $bloc('ko', $titre, ta('st_d_nodest'));
        } elseif ($faux = array_filter($adr, 'lv_adresse_exemple')) {
            $bloc('ko', $titre, ta('st_d_example', implode(', ', $faux)));
        } else {
            $bloc('ok', $titre, ta('st_d_dest', implode(', ', $adr)));
        }
    }

    // ---- Les boîtes comptables des associations ---------------------------
    $assocs = Settings::pairs('form_assoc_options');
    if (!$assocs) {
        $bloc('attention', ta('st_d_assoc'), ta('st_d_assoc_empty'));
    } else {
        // [V32-DOC-ASSO] Le sigle est affiché à côté de la boîte comptable, mais
        // il ne change pas le verdict : il est facultatif, et une association
        // sans sigle marche parfaitement — ses fichiers gardent leur nom.
        $sigles = Settings::sigles('form_assoc_options');
        $lignes = '';
        $sans = 0;
        $mauvaises = 0;
        foreach ($assocs as $nom => $mail) {
            if ($mail === '')                                    { $sans++;      $etat = '<em>' . e(ta('st_d_assoc_none')) . '</em>'; }
            elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL))   { $mauvaises++; $etat = '<strong>' . e(ta('st_d_assoc_bad')) . '</strong> ' . e($mail); }
            else                                                 { $etat = e($mail); }
            $sig = (string)($sigles[$nom] ?? '');
            $lignes .= '<tr><td style="padding:3px 14px 3px 0;">' . e($nom) . '</td><td style="padding:3px 14px 3px 0;">' . $etat . '</td>'
                     . '<td style="padding:3px 0;">' . ($sig === '' ? '<em style="opacity:.55">' . e(ta('st_d_assoc_nosig')) . '</em>' : e($sig)) . '</td></tr>';
        }
        $secours = Settings::emails('form_expenses_bexio');
        $manque  = $sans + $mauvaises;
        $bloc($mauvaises ? 'ko' : ($sans ? 'attention' : 'ok'), ta('st_d_boxes'),
            $manque === 0
              ? ta('st_d_boxes_ok', (string)count($assocs))
              : ta('st_d_boxes_ko', (string)$manque, (string)count($assocs))
                . ($secours ? ta('st_d_boxes_fb', implode(', ', $secours)) : ta('st_d_boxes_nofb')),
            '<table style="border-collapse:collapse;font-size:13px;margin-top:8px;">' . $lignes . '</table>');
    }

    // ---- 6. Le journal ----------------------------------------------------
    $fichier = LV_APP . '/logs/mail.log';
    if (is_file($fichier)) {
        $tout = preg_split('/\r\n|\r|\n/', trim((string)file_get_contents($fichier))) ?: [];
        $dernieres = array_slice($tout, -12);
        $bloc('info', ta('st_d_log12'), 'app/logs/mail.log',
            '<pre>' . e(implode("\n", $dernieres)) . '</pre>');
    } else {
        $bloc('info', ta('st_d_log'), ta('st_d_log_none'));
    }

    return $r;
}

admin_top(ta('st_title'), 'settings');
?>
<div class="page-head"><h1><?= e(ta('st_title')) ?></h1></div>

<?php if (!empty($rapport)): ?>
<div class="panel lv-rapport" id="rapport">
  <h2><?= e(ta('st_rep_title')) ?></h2>
  <?php foreach ($rapport as $b): ?>
  <div class="lv-r lv-r-<?= e($b['etat']) ?>">
    <p class="lv-r-t"><span class="lv-r-p"><?= ['ok' => '✓', 'ko' => '✗', 'attention' => '!', 'info' => 'i'][$b['etat']] ?? '·' ?></span><strong><?= e($b['titre']) ?></strong></p>
    <p class="lv-r-x"><?= e($b['texte']) ?></p>
    <?= $b['detail'] ?>
  </div>
  <?php endforeach; ?>
  <p class="hint"><?= ta('st_rep_note') ?></p>
</div>
<style>
.lv-rapport .lv-r { border-left: 3px solid #ccc; padding: 2px 0 2px 14px; margin: 0 0 16px; }
.lv-rapport .lv-r-ok { border-color: #2e7d32; }
.lv-rapport .lv-r-ko { border-color: #c62828; }
.lv-rapport .lv-r-attention { border-color: #ef6c00; }
.lv-rapport .lv-r-t { margin: 0 0 2px; }
.lv-rapport .lv-r-p { display: inline-block; width: 1.4em; font-weight: bold; }
.lv-rapport .lv-r-ok .lv-r-p { color: #2e7d32; }
.lv-rapport .lv-r-ko .lv-r-p { color: #c62828; }
.lv-rapport .lv-r-attention .lv-r-p { color: #ef6c00; }
.lv-rapport .lv-r-x { margin: 0; }
.lv-rapport pre { background: #f6f6f4; border: 1px solid #e4e4e0; padding: 10px 12px; overflow: auto; font-size: 12px; line-height: 1.45; margin: 8px 0 0; }
.lv-rapport summary { cursor: pointer; font-size: 13px; color: #555; margin-top: 6px; }
.lv-rapport .lv-r-aide { background: #fdf6f6; border: 1px solid #f0dcdc; border-radius: 6px; padding: 10px 14px; margin: 10px 0 0; font-size: 13.5px; }
.lv-rapport .lv-r-aide p { margin: 0 0 6px; }
.lv-rapport .lv-r-aide ul { margin: 0; padding-left: 18px; }
.lv-rapport .lv-r-aide li { margin: 0 0 5px; line-height: 1.5; }
.lv-rapport .lv-r-aide em { font-style: normal; font-weight: 600; background: #fff; border: 1px solid #eadada; border-radius: 3px; padding: 0 5px; }
.lv-rapport .lv-r-aide .lv-r-note { margin: 8px 0 0; color: #7a6a6a; font-size: 12.5px; }
</style>
<?php endif; ?>

<form method="post" class="js-dirty">
  <?= Auth::csrfField() ?>

  <div class="panel">
    <h2><?= ta('st_p_site') ?></h2>
    <?= render_field('logo_image_id', [
        'type' => 'image', 'zone' => 'content', 'label' => ta('st_f_logo'),
        'help' => ta('st_f_logo_h'),
    ], ['id' => 0, 'logo_image_id' => (int)setting('logo_image_id', '0')], 'site') ?>
    <div class="grid2">
      <?= field_wrap(ta('st_f_name'), s_in('site_name')) ?>
      <?= field_wrap(ta('st_f_email'), s_in('contact_email', 'email')) ?>
      <?= field_wrap(ta('st_f_phone'), s_in('contact_phone')) ?>
      <?= field_wrap(ta('st_f_addr'), '<textarea name="contact_address" rows="2">' . e(setting('contact_address')) . '</textarea>') ?>
      <?= field_wrap(ta('st_f_hours_fr'), s_in('contact_hours_fr')) ?>
      <?= field_wrap(ta('st_f_hours_en'), s_in('contact_hours_en')) ?>
      <?= field_wrap(ta('st_f_insta'), s_in('instagram_url')) ?>
      <?= field_wrap(ta('st_f_linkedin'), s_in('linkedin_url')) ?>
      <?= field_wrap(ta('st_f_news'), s_in('newsletter_url')) ?>
      <?= field_wrap(ta('st_f_donate'), s_in('donate_url'), ta('st_f_donate_h')) ?>
      <?= field_wrap(ta('st_f_about_fr'), '<textarea name="footer_about_fr" rows="3">' . e(setting('footer_about_fr')) . '</textarea>',
          ta('st_f_about_h')) ?>
      <?= field_wrap(ta('st_f_about_en'), '<textarea name="footer_about_en" rows="3">' . e(setting('footer_about_en')) . '</textarea>',
          ta('st_f_about_en_h')) ?>
      <?= field_wrap(ta('st_f_note_fr'), s_in('footer_note_fr')) ?>
      <?= field_wrap(ta('st_f_note_en'), s_in('footer_note_en')) ?>
    </div>
  </div>

  <div class="panel" id="partners">
    <h2><?= ta('st_p_partners') ?></h2>
    <p class="hint"><?= ta('st_partners_h') ?></p>
    <?= render_field('partners', ['type' => 'gallery', 'zone' => 'partners', 'label' => ta('st_f_logos')], ['id' => 0], 'site') ?>
  </div>

  <div class="panel" id="cookies">
    <h2><?= ta('st_p_cookies') ?></h2>
    <div class="grid2">
      <?= field_wrap(ta('st_f_ga'), s_in('ga_id', 'text', 'G-XXXXXXXXXX'), ta('st_f_ga_h')) ?>
      <div class="f"><label class="f-label"><?= e(ta('st_f_cbanner')) ?></label>
        <select name="cookies_mode">
          <option value="advanced"<?= setting('cookies_mode') === 'advanced' ? ' selected' : '' ?>><?= e(ta('st_o_advanced')) ?></option>
          <option value="simple"<?= setting('cookies_mode') === 'simple' ? ' selected' : '' ?>><?= e(ta('st_o_simple')) ?></option>
          <option value="off"<?= setting('cookies_mode') === 'off' ? ' selected' : '' ?>><?= e(ta('st_o_off')) ?></option>
        </select></div>
      <?= field_wrap(ta('st_f_ctitle_fr'), s_in('cookie_title_fr', 'text', ta('st_ph_default'))) ?>
      <?= field_wrap(ta('st_f_ctitle_en'), s_in('cookie_title_en', 'text', ta('st_ph_default'))) ?>
      <?= field_wrap(ta('st_f_ctext_fr'), '<textarea name="cookie_text_fr" rows="3" placeholder="' . e(ta('st_ph_default')) . '">' . e(setting('cookie_text_fr')) . '</textarea>') ?>
      <?= field_wrap(ta('st_f_ctext_en'), '<textarea name="cookie_text_en" rows="3" placeholder="' . e(ta('st_ph_default')) . '">' . e(setting('cookie_text_en')) . '</textarea>') ?>
    </div>
  </div>

  <div class="panel" id="forms">
    <h2><?= ta('st_p_forms') ?></h2>
    <div class="grid2">
      <?= field_wrap(ta('st_f_to_infos'), s_in('form_infos_to', 'text', 'email1@…, email2@…'), ta('st_f_to_h')) ?>
      <?= field_wrap(ta('st_f_to_exp'), s_in('form_expenses_to', 'text', 'email1@…, email2@…'), ta('st_f_to_h')) ?>
      <?= field_wrap(ta('st_f_bexio'), s_in('form_expenses_bexio', 'text', 'inbox@bexio…'), ta('st_f_bexio_h')) ?>
      <?= field_wrap(ta('st_f_from'), s_in('mail_from', 'text', 'no-reply@le-voisin.com'), ta('st_f_from_h')) ?>
      <?= field_wrap(ta('st_f_assoc'),
          '<textarea name="form_assoc_options" rows="12" spellcheck="false">' . e(setting('form_assoc_options')) . '</textarea>',
          ta('st_f_assoc_h')) ?>
      <div class="f f-toggle"><label class="switch"><input type="checkbox" name="keep_submissions" value="1"<?= setting('keep_submissions') === '1' ? ' checked' : '' ?>><span></span></label>
        <span class="f-label inline"><?= e(ta('st_f_keep')) ?></span></div>
    </div>
  </div>

  <div class="panel" id="smtp">
    <h2><?= ta('st_p_smtp') ?></h2>
    <p class="hint"><?= ta('st_smtp_h') ?></p>
    <div class="grid2">
      <?= field_wrap(ta('st_f_host'), s_in('smtp_host', 'text', 'mail.infomaniak.com'), ta('st_f_host_h')) ?>
      <?= field_wrap(ta('st_f_port'), s_in('smtp_port', 'text', '587'), ta('st_f_port_h')) ?>
      <div class="f"><label class="f-label"><?= e(ta('st_f_sec')) ?></label>
        <select name="smtp_secure">
          <?php $sec = strtolower(setting('smtp_secure', 'tls')); foreach (
              ['tls' => ta('st_o_tls'), 'ssl' => ta('st_o_ssl'), 'none' => ta('st_o_none')] as $k => $lbl): ?>
          <option value="<?= $k ?>"<?= $sec === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select></div>
      <?= field_wrap(ta('st_f_user'), s_in('smtp_user', 'text', 'no-reply@le-voisin.com'), ta('st_f_user_h')) ?>
      <?= field_wrap(ta('st_f_pass'),
          '<input type="password" name="smtp_pass" value="" autocomplete="new-password" placeholder="'
          . e(setting('smtp_pass', '') !== '' ? ta('st_ph_pass_set') : ta('st_ph_pass_new')) . '">',
          setting('smtp_pass', '') !== '' ? ta('st_f_pass_h_set') : ta('st_f_pass_h_new')) ?>
    </div>
    <hr style="border:0;border-top:1px solid #e4e4e0;margin:22px 0 18px;">
    <p class="hint"><?= ta('st_test_h') ?></p>
    <div class="grid2">
      <?= field_wrap(ta('st_f_testto'),
          '<input type="email" name="lv_test_to" value="" placeholder="' . e(setting('contact_email', '') ?: ta('st_ph_youraddr')) . '">',
          ta('st_f_testto_h')) ?>
      <div class="f" style="align-self:end;">
        <button type="submit" name="lv_test" value="1" class="btn"><?= e(ta('st_btn_test')) ?></button>
      </div>
    </div>
  </div>

  <?php /* [V28-INVIT] Le message que reçoit un collaborateur avec son lien
           d'accès. Les champs ne sont jamais vides : ils s'ouvrent sur le
           texte d'origine, qu'on relit et qu'on retouche, plutôt que sur un
           carré blanc devant lequel il faut tout réinventer. */ ?>
  <div class="panel" id="invite">
    <h2><?= ta('st_p_invite') ?></h2>
    <p class="hint"><?= ta('st_invite_h') ?></p>
    <p class="hint"><?= ta('st_invite_marks') ?></p>
    <?php if (!empty($rapportInvit)): ?>
    <div class="flash <?= $rapportInvit['ok'] ? 'ok' : 'err' ?>"><?= e($rapportInvit['ok']
        ? ta('st_inv_test_ok', $rapportInvit['email'])
        : ta('st_inv_test_ko', $rapportInvit['raison'])) ?></div>
    <?php endif; ?>
    <div class="grid2">
      <?= field_wrap(ta('st_f_inv_sub_fr'),
          '<input type="text" name="invite_subject_fr" value="' . e(s_invit('invite_subject_fr', Invitations::sujetDefaut('fr'))) . '">') ?>
      <?= field_wrap(ta('st_f_inv_sub_en'),
          '<input type="text" name="invite_subject_en" value="' . e(s_invit('invite_subject_en', Invitations::sujetDefaut('en'))) . '">') ?>
      <?= field_wrap(ta('st_f_inv_txt_fr'),
          '<textarea name="invite_body_fr" rows="13">' . e(s_invit('invite_body_fr', Invitations::texteDefaut('fr'))) . '</textarea>') ?>
      <?= field_wrap(ta('st_f_inv_txt_en'),
          '<textarea name="invite_body_en" rows="13">' . e(s_invit('invite_body_en', Invitations::texteDefaut('en'))) . '</textarea>') ?>
    </div>
    <hr style="border:0;border-top:1px solid #e4e4e0;margin:22px 0 18px;">
    <p class="hint"><?= ta('st_inv_test_h') ?></p>
    <div class="grid2">
      <?= field_wrap(ta('st_f_inv_testto'),
          '<input type="email" name="lv_invit_test_to" value="" placeholder="'
          . e((string)(Auth::user()['email'] ?? setting('contact_email', ''))) . '">') ?>
      <div class="f"><label class="f-label"><?= e(ta('st_f_inv_testlang')) ?></label>
        <select name="lv_invit_test_lang">
          <option value="fr">Français</option>
          <option value="en">English</option>
        </select></div>
      <div class="f" style="align-self:end;">
        <button type="submit" name="lv_invit_test" value="1" class="btn"><?= e(ta('st_btn_inv_test')) ?></button>
      </div>
    </div>
  </div>

  <div class="panel" id="social">
    <h2><?= ta('st_p_social') ?></h2>
    <div class="grid2">
      <?= field_wrap(ta('st_f_webhook'), s_in('social_webhook', 'text', 'https://hook.eu2.make.com/…'), ta('st_f_webhook_h')) ?>
    </div>
  </div>

  <div class="panel">
    <h2><?= ta('st_p_video') ?></h2>
    <div class="grid2">
      <?= field_wrap(ta('st_f_yt'), s_in('yt_channel_id', 'text', 'UC…'), ta('st_f_yt_h')) ?>
    </div>
  </div>

  <div class="panel" id="pro">
    <h2><?= ta('st_p_pro') ?></h2>
    <p class="hint"><?= ta('st_pro_h') ?></p>
    <div class="grid2">
      <?= field_wrap(ta('st_f_cms'), s_in('pro_cms_url', 'text', '/admin/'), ta('st_f_cms_h')) ?>
      <?= field_wrap(ta('st_f_dash_label'), s_in('pro_dashboard_label', 'text', 'Dashboard')) ?>
      <?= field_wrap(ta('st_f_dash'), s_in('pro_dashboard_url', 'text', 'https://…'), ta('st_f_dash_h')) ?>
    </div>
  </div>

  <div class="panel" id="skribble">
    <h2><?= ta('st_p_skribble') ?></h2>
    <p class="hint"><?= ta('st_skr_h') ?></p>
    <div class="grid2">
      <?= field_wrap(ta('st_f_skr_user'), s_in('skribble_username', 'text', ta('st_ph_skr_user'))) ?>
      <?= field_wrap(ta('st_f_skr_key'), s_in('skribble_api_key', 'text', ta('st_ph_skr_key')), ta('st_f_skr_key_h')) ?>
      <div class="f"><label class="f-label"><?= e(ta('st_f_skr_q')) ?></label>
        <select name="skribble_quality">
          <?php $q = strtoupper(setting('skribble_quality', 'SES')); foreach (['SES' => ta('st_o_ses'), 'AES' => ta('st_o_aes'), 'QES' => ta('st_o_qes')] as $k => $lbl): ?>
          <option value="<?= $k ?>"<?= $q === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select></div>
    </div>
  </div>

  <p><button class="btn" type="submit"><?= e(ta('st_save')) ?></button></p>
</form>
<?php admin_bottom(); ?>

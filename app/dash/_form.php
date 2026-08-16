<?php
/**
 * Les briques de formulaire du dashboard. [16.08.2026]
 *
 * Écrites une fois pour que le deuxième écran qui saisit quelque chose n'ait pas
 * à redéclarer un champ, un message d'erreur ou un bandeau de confirmation.
 *
 * DEUX RÈGLES QUI NE SE NÉGOCIENT PAS ICI, et qui viennent de défauts mesurés
 * dans cette maison:
 *
 * 1. UN FORMULAIRE QUI REFUSE REND CE QUI A ÉTÉ SAISI. Le 14.08.2026 on a
 *    découvert que la fiche de l'espace collaborateur marque vingt-deux champs
 *    « required » et n'en contrôle aucun. Le défaut inverse coûte aussi cher:
 *    un formulaire qui refuse en vidant tout fait recommencer, et au troisième
 *    essai les gens saisissent moins bien.
 *
 * 2. ON REDIRIGE APRÈS UN ENREGISTREMENT RÉUSSI. Sans cela, un rafraîchissement
 *    de la page renvoie le POST et crée un doublon. C'est le motif
 *    « poster, rediriger, obtenir », et il n'est pas décoratif.
 */
declare(strict_types=1);

/** Un message qui survit à la redirection. */
function dash_flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) { $_SESSION['dash_flash'] = ['msg' => $msg, 'type' => $type]; return null; }
    $f = $_SESSION['dash_flash'] ?? null;
    unset($_SESSION['dash_flash']);
    return $f;
}

function dash_flash_html(): void
{
    $f = dash_flash();
    if (!$f) return;
    printf('<div class="flash %s">%s</div>', e($f['type']), e($f['msg']));
}

/**
 * Un champ.
 *
 * `$err` porte les erreurs de la tentative précédente, `$val` ce qui avait été
 * saisi. Les deux ensemble font qu'un refus n'efface rien.
 */
function ch(string $nom, string $libelle, $val, array $err = [], array $opt = []): void
{
    $type  = $opt['type']  ?? 'text';
    $aide  = $opt['aide']  ?? '';
    $large = !empty($opt['large']);
    $e     = $err[$nom] ?? null;

    printf('<div class="ch%s%s">', $large ? ' large' : '', $e ? ' faux' : '');
    printf('<label for="f_%s">%s%s</label>', e($nom), e($libelle),
           !empty($opt['requis']) ? ' <span class="req">obligatoire</span>' : '');

    if ($type === 'textarea') {
        printf('<textarea id="f_%s" name="%s" rows="%d">%s</textarea>',
               e($nom), e($nom), (int)($opt['rows'] ?? 4), e((string)$val));
    } elseif ($type === 'select') {
        printf('<select id="f_%s" name="%s">', e($nom), e($nom));
        foreach ($opt['choix'] ?? [] as $k => $lib) {
            printf('<option value="%s"%s>%s</option>', e((string)$k),
                   (string)$val === (string)$k ? ' selected' : '', e($lib));
        }
        echo '</select>';
    } else {
        printf('<input type="%s" id="f_%s" name="%s" value="%s"%s%s>',
               e($type), e($nom), e($nom), e((string)$val),
               isset($opt['step']) ? ' step="' . e((string)$opt['step']) . '"' : '',
               isset($opt['placeholder']) ? ' placeholder="' . e($opt['placeholder']) . '"' : '');
    }

    if ($e)          printf('<p class="err">%s</p>', e($e));
    elseif ($aide)   printf('<p class="aide">%s</p>', e($aide));
    echo '</div>';
}

/** Le style, avec l'enveloppe, pour qu'un formulaire ne dépende d'aucun fichier. */
function dash_form_style(): void
{
    ?>
    <style>
    form.saisie { padding:22px 26px 40px; max-width:960px; }
    .grille { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:0 24px; }
    .ch { margin-bottom:16px; }
    .ch.large { grid-column:1 / -1; }
    .ch label { display:block; font-size:12.5px; color:var(--doux); margin-bottom:4px; }
    .ch .req { color:var(--orange); font-size:11px; }
    .ch input, .ch select, .ch textarea { width:100%; padding:8px 11px; font-size:14.5px;
        font-family:inherit; border:1px solid var(--trait); border-radius:4px;
        background:var(--papier); color:var(--encre); }
    .ch textarea { resize:vertical; line-height:1.5; }
    .ch.faux input, .ch.faux select, .ch.faux textarea { border-color:var(--orange); }
    .ch .err { margin:4px 0 0; font-size:12.5px; color:var(--orange); }
    .ch .aide { margin:4px 0 0; font-size:12px; color:var(--doux); }
    .titre-bloc { grid-column:1 / -1; margin:18px 0 10px; font-size:13px;
        text-transform:uppercase; letter-spacing:.05em; color:var(--doux);
        border-bottom:1px solid var(--trait); padding-bottom:5px; }
    .actions { display:flex; gap:12px; align-items:center; margin-top:24px;
        padding-top:18px; border-top:1px solid var(--trait); }
    .actions .sec2 { color:var(--doux); font-size:13.5px; text-decoration:none; }
    .actions .sup { margin-left:auto; color:var(--orange); font-size:13px; text-decoration:none; }
    .flash { margin:16px 26px 0; padding:11px 16px; font-size:13.5px;
        border-left:4px solid var(--jaune); background:var(--fond2); }
    .flash.err { border-left-color:var(--orange); }
    </style>
    <?php
}

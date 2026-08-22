<?php
/**
 * La fiche de production, ses neuf onglets. [16.08.2026]
 *
 * Reprise de `19_productions.js` du dashboard Apps Script. Mêmes onglets, même
 * ordre, mêmes champs — l'ordre est celui d'Anna et il suit la fabrication d'un
 * spectacle: ce qu'il est, ce qu'on demande pour le financer, quand il se fait,
 * comment on s'y rend, qui fait quoi, ce qu'il coûte, ce qu'il rapporte, à qui
 * appartient ce qu'on a écrit.
 *
 * OUVERTE PAR `?e=projets&p=<id du spectacle du CMS>`. La clef est celle de
 * `projects`, jamais celle de `projet_prod`: c'est le spectacle qui existe
 * d'abord, la production s'y accroche. `ProdFiche::ligne()` crée la ligne au
 * premier passage, donc ouvrir une fiche ne demande aucun geste préalable.
 *
 * TOUT S'ENREGISTRE PAR POST ET RECHARGE. Pas d'auto-save en JavaScript comme
 * dans le dashboard actuel: un champ qui part à chaque frappe multiplie les
 * écritures et fait diverger deux onglets ouverts sur la même fiche. Ici on
 * remplit, on enregistre, on voit ce qui est enregistré.
 *
 * Attend $p (la ligne de `projects`) et $onglet.
 */
declare(strict_types=1);

/** @var array $p */
/** @var string $onglet */

$pid   = (int)$p['id'];
$prod  = ProdFiche::ligne($pid);
$d     = ProdFiche::donnees($pid);
$ecrit = dash_droit('projets', dash_role()) === 'ecrit';

$ONGLETS = [
    'synthese'     => 'Synthèse',
    'dossier'      => 'Dossier',
    'planning'     => 'Planning',
    'logistique'   => 'Logistique',
    'technique'    => 'Fiche technique',
    'fdr'          => 'Feuille de route',
    'remuneration' => 'Rémunération',
    'budget'       => 'Budget',
    'devis'        => 'Devis',
    'droits'       => 'Droits d\'auteur',
];
if (!isset($ONGLETS[$onglet])) $onglet = 'synthese';

$titre = trim((string)($p['title_fr'] ?: $p['title_en'])) ?: 'Spectacle';
$lien  = fn(string $o): string => '/dashboard.php?e=projets&p=' . $pid . '&o=' . $o;

/** Un champ texte qui s'enregistre avec le formulaire qui l'entoure. */
/* `$form` EST CE QUI PERMET DE DÉPLACER UN BLOC SANS LE DÉTACHER. [22.08.2026]
   Un champ qui porte `form="fSyn"` appartient au formulaire `fSyn` même s'il est
   écrit ailleurs dans la page — c'est du HTML standard. C'est ainsi que les
   statistiques descendent sous l'équipe tout en restant sous le même bouton
   Enregistrer. Sans cela il en faudrait un second, et une page où deux boutons
   n'enregistrent pas la même chose est une page où l'on perd son travail. */
$champ = function (string $chemin, string $label, string $val, string $aide = '', int $lignes = 0, string $form = '') use ($ecrit): string {
    $id = 'c-' . str_replace('.', '-', $chemin);
    $h  = '<div class="ch"><label for="' . $id . '">' . e($label) . '</label>';
    if ($aide !== '') $h .= '<p class="aide">' . e($aide) . '</p>';
    $ro = $ecrit ? '' : ' readonly';
    $fa = $form !== '' ? ' form="' . e($form) . '"' : '';
    $h .= $lignes > 0
        ? '<textarea id="' . $id . '" name="v[' . e($chemin) . ']" rows="' . $lignes . '"' . $ro . $fa . '>' . e($val) . '</textarea>'
        : '<input type="text" id="' . $id . '" name="v[' . e($chemin) . ']" value="' . e($val) . '"' . $ro . $fa . '>';
    return $h . '</div>';
};

dash_haut('projets', '<a href="/dashboard.php?e=projets" class="ret">tous les spectacles</a> · <strong>' . e($titre) . '</strong>');
?>

<div class="onglets pf">
  <?php foreach ($ONGLETS as $k => $lib): ?>
    <a href="<?= e($lien($k)) ?>" class="<?= $onglet === $k ? 'ici' : '' ?>"><?= e($lib) ?></a>
  <?php endforeach; ?>
</div>

<?php /* ── LE BOUTON PDF, VISIBLE ─────────────────────────────────────────────
     [16.08.2026] Il était un lien discret au bout de la barre d'onglets, et
     Anna ne l'a pas trouvé: « ainda nao vejo a parte de imprimir em formato
     pdf ». Un lien gris de la taille d'un onglet, au bout d'une rangée de dix,
     n'existe pas — le dashboard qu'elle quitte avait un vrai bouton, et c'est
     ce qu'on cherche des yeux.

     « PDF » ET NON « Imprimer », parce que c'est le résultat voulu et non le
     geste. Le navigateur ouvre sa fenêtre d'impression et « Enregistrer au
     format PDF » y est le premier choix; l'infobulle le dit pour qui hésite.
     Le site n'a aucune bibliothèque PDF — vérifié le 16.08, ni FPDF, ni TCPDF,
     ni Dompdf — et le PDF du navigateur est un vrai PDF, sélectionnable et
     cherchable, pas une image. */ ?>
<div class="barre-doc">
  <a class="bt-pdf" href="<?= e($lien($onglet)) ?>&amp;imprimer=1" target="_blank" rel="noopener"
     title="Ouvre une page nue. Dans la fenêtre qui s'ouvre: Imprimer, puis « Enregistrer au format PDF »">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
      <path d="M6 14h12v8H6z"></path>
    </svg>
    PDF — <?= e($ONGLETS[$onglet]) ?>
  </a>
  <?php /* Les quatre qui sortent de la maison s'impriment aussi en anglais. La
       langue se choisit AVANT d'ouvrir: rouvrir la page pour changer de langue
       fait perdre le réglage d'impression déjà posé. */ ?>
  <?php if (in_array($onglet, ['dossier','fdr','technique','devis'], true)): ?>
    <a class="bt-pdf bt-pdf-en" href="<?= e($lien($onglet)) ?>&amp;imprimer=1&amp;lang=en"
       target="_blank" rel="noopener" title="Same document, English labels">PDF — English</a>
  <?php endif; ?>
</div>
<style>
/* JUSTIFIÉS À DROITE, comme partout ailleurs. [16.08.2026] Demandé par Anna
   pour tout le site: un bouton d'impression n'est pas le geste principal d'un
   écran, c'est ce qu'on fait à la fin. À gauche il se lit avant le contenu et
   attire le clic; à droite il attend qu'on ait fini. */
.barre-doc{display:flex;gap:9px;flex-wrap:wrap;justify-content:flex-end;margin:14px 0 -4px}
.bt-pdf{display:inline-flex;align-items:center;gap:7px;padding:8px 15px;
  border:1px solid var(--encre);border-radius:5px;background:var(--encre);color:var(--papier);
  text-decoration:none;font-size:13.5px;font-weight:600;white-space:nowrap}
.bt-pdf:hover{opacity:.86}
/* La version anglaise en contour et non en plein: c'est la même action, pas une
   action plus importante. Deux boutons pleins côte à côte se disputent l'œil. */
.bt-pdf-en{background:transparent;color:var(--encre)}
</style>

<div class="zone">
<?php /* ══════════════════════════ SYNTHÈSE ══════════════════════════ */ ?>
<?php if ($onglet === 'synthese'): ?>

  <form id="fSyn" method="post" action="<?= e($lien('synthese')) ?>">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="pf" value="champs">

    <div class="deux">
      <div class="bl">
        <h3>Informations de la création</h3>
        <p class="aide">Le titre, la durée et les textes publics viennent du CMS et se
           modifient dans l'administration du site: les recopier ici ferait deux vérités.</p>
        <dl class="info">
          <dt>Titre</dt><dd><?= e($titre) ?></dd>
          <?php if ($p['year_creation']): ?><dt>Création</dt><dd><?= e((string)$p['year_creation']) ?></dd><?php endif; ?>
          <?php if ($p['duration_min']): ?><dt>Durée</dt><dd><?= (int)$p['duration_min'] ?> min</dd><?php endif; ?>
          <?php if ($p['public_cible']): ?><dt>Public</dt><dd><?= e((string)$p['public_cible']) ?></dd><?php endif; ?>
          <dt>Phase</dt>
          <dd>
            <select name="prod[phase]" <?= $ecrit ? '' : 'disabled' ?>>
              <?php foreach (['dev'=>'Développement','creation'=>'Création','production'=>'Production',
                              'promo'=>'Promotion','tournee'=>'Tournée','cloture'=>'Clôture'] as $k => $v): ?>
                <option value="<?= $k ?>" <?= ($prod['phase'] ?? 'dev') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
          </dd>
          <dt>Responsable</dt>
          <dd><input type="text" name="prod[responsable]" value="<?= e((string)($prod['responsable'] ?? '')) ?>" <?= $ecrit ? '' : 'readonly' ?>></dd>
          <dt>Lieu de création</dt>
          <dd><input type="text" name="prod[lieu_creation]" value="<?= e((string)($prod['lieu_creation'] ?? '')) ?>" <?= $ecrit ? '' : 'readonly' ?>></dd>
          <dt>Porteur juridique</dt>
          <dd>
            <select name="prod[organisation_id]" <?= $ecrit ? '' : 'disabled' ?>>
              <option value="">(aucun)</option>
              <?php foreach (DB::all("SELECT id, nom FROM organisation WHERE supprime_le IS NULL
                                      ORDER BY genre, nom") as $o): ?>
                <option value="<?= (int)$o['id'] ?>" <?= (int)($prod['organisation_id'] ?? 0) === (int)$o['id'] ? 'selected' : '' ?>><?= e((string)$o['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </dd>
          <dt>Budget du projet</dt>
          <dd class="paire">
            <input type="text" name="prod[budget]" value="<?= $prod['budget'] !== null ? e((string)(0 + (float)$prod['budget'])) : '' ?>" <?= $ecrit ? '' : 'readonly' ?>>
            <select name="prod[devise]" <?= $ecrit ? '' : 'disabled' ?>>
              <?php foreach (['CHF','EUR'] as $dv): ?>
                <option <?= ($prod['devise'] ?? 'CHF') === $dv ? 'selected' : '' ?>><?= $dv ?></option>
              <?php endforeach; ?>
            </select>
          </dd>
        </dl>
        <p class="aide">Le budget du projet artistique, <strong>pas l'argent qui passe par
           Le Voisin</strong>. Le détail se saisit dans l'onglet Budget.</p>

        <div class="ch">
          <label for="c-notes-prod">Notes de production</label>
          <textarea id="c-notes-prod" name="prod[notes]" rows="4" <?= $ecrit ? '' : 'readonly' ?>><?= e((string)($prod['notes'] ?? '')) ?></textarea>
        </div>
      </div>

      <div class="bl">
        <?= $champ('resume', 'Résumé du spectacle', (string)$d['resume'],
                   'Le pitch, tel qu\'on l\'envoie. C\'est lui que le Dossier reprend.', 7) ?>
        <?= $champ('coproductions', 'Coproductions', (string)$d['coproductions'], '', 3) ?>
        <?= $champ('soutiens', 'Soutiens', (string)$d['soutiens'], '', 3) ?>
      </div>
    </div>

  </form>

  <?php /* L'ÉQUIPE AU MILIEU, LES STATISTIQUES À LA FIN.  [Anna, 22.08.2026]
       « dans la partie synthèse mettre les infos de statistique à la fin,
       monter équipe du projet ». Qui fait le spectacle se lit avant combien de
       fois il s'est joué.

       Le formulaire de la synthèse se ferme ici, au-dessus de l'équipe — qui
       est un formulaire à part et ne peut pas s'imbriquer. Les statistiques
       s'écrivent en dessous et lui appartiennent quand même, par leur
       `form="fSyn"`: voir la closure `$champ` en haut de ce fichier. */ ?>

  <h3 class="sep">Équipe du projet</h3>
  <?php require __DIR__ . '/_prod_equipe.php'; ?>

  <h3 class="sep">Statistiques</h3>
  <div class="quatre">
    <?= $champ('statistiques.representations', 'Représentations', (string)$d['statistiques']['representations'], '', 0, 'fSyn') ?>
    <?= $champ('statistiques.spectateurs', 'Spectateurs', (string)$d['statistiques']['spectateurs'], '', 0, 'fSyn') ?>
    <?= $champ('statistiques.recettes', 'Recettes', (string)$d['statistiques']['recettes'], '', 0, 'fSyn') ?>
    <?= $champ('statistiques.villes', 'Villes jouées', (string)$d['statistiques']['villes'], '', 0, 'fSyn') ?>
  </div>
  <?= $champ('statistiques.notes', 'Notes', (string)$d['statistiques']['notes'], '', 2, 'fSyn') ?>

  <?php if ($ecrit): ?><button type="submit" form="fSyn">Enregistrer</button><?php endif; ?>

<?php /* ══════════════════════════ DOSSIER ═══════════════════════════ */ ?>
<?php elseif ($onglet === 'dossier'): ?>

  <p class="aide top">Les textes du dossier de demande de fonds. Le résumé, les coproductions
     et les soutiens viennent de la Synthèse — inutile de les ressaisir ici.</p>

  <form method="post" action="<?= e($lien('dossier')) ?>">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="pf" value="champs">
    <?= $champ('dossier.lettre', 'Lettre de motivation', (string)$d['dossier']['lettre'],
               'Adressée au financeur: pourquoi ce projet, pourquoi maintenant, pourquoi vous.', 8) ?>
    <?= $champ('dossier.description', 'Description du projet', (string)$d['dossier']['description'],
               'Présentation détaillée. Le résumé court reste dans la Synthèse.', 8) ?>
    <?= $champ('dossier.intention', 'Note d\'intention', (string)$d['dossier']['intention'],
               'Le propos artistique, la démarche, l\'écriture.', 8) ?>

    <div class="ch">
      <label for="c-dossier-calendrier">Calendrier</label>
      <?php $auto = ProdFiche::calendrierDepuisPlanning($d); ?>
      <?php if ($auto !== ''): ?>
        <p class="aide">Les étapes du Planning, telles qu'elles y sont saisies:</p>
        <pre class="auto"><?= e($auto) ?></pre>
      <?php else: ?>
        <p class="aide">Aucune étape dans le Planning — rien à reprendre ici pour l'instant.</p>
      <?php endif; ?>
      <textarea id="c-dossier-calendrier" name="v[dossier.calendrier]" rows="5" <?= $ecrit ? '' : 'readonly' ?>><?= e((string)$d['dossier']['calendrier']) ?></textarea>
    </div>

    <?= $champ('dossier.publicCible', 'Public cible', (string)$d['dossier']['publicCible'],
               'Âges, publics spécifiques, médiation.', 3) ?>
    <?= $champ('dossier.benefice', 'Bénéfice pour la ville', (string)$d['dossier']['benefice'],
               'Retombées locales: rayonnement, emploi, publics, partenariats.', 5) ?>
    <?php if ($ecrit): ?><button type="submit">Enregistrer</button><?php endif; ?>
  </form>

<?php /* ══════════════════════════ PLANNING ══════════════════════════ */ ?>
<?php elseif ($onglet === 'planning'): ?>
  <?php require __DIR__ . '/_prod_planning.php'; ?>

<?php /* ═════════════════════════ LOGISTIQUE ═════════════════════════ */ ?>
<?php elseif ($onglet === 'logistique'): ?>
  <?php require __DIR__ . '/_prod_logistique.php'; ?>

<?php /* ══════════════════════ FICHE TECHNIQUE ═══════════════════════ */ ?>
<?php elseif ($onglet === 'technique'): ?>
  <?php require __DIR__ . '/_prod_technique.php'; ?>

<?php /* ════════════════════ FEUILLE DE ROUTE ════════════════════════ */ ?>
<?php elseif ($onglet === 'fdr'): ?>

  <p class="aide top">Rédigée depuis le Planning, l'Équipe et la Logistique, puis
     <strong>modifiable librement</strong>. La régénérer remplace le texte ci-dessous:
     ce qui a été écrit à la main est alors perdu, et c'est pour cela qu'on le demande.</p>

  <?php if ($ecrit): ?>
    <form method="post" action="<?= e($lien('fdr')) ?>" class="inline"
          onsubmit="return confirm('Régénérer la feuille de route ? Le texte actuel sera remplacé.')">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="pf" value="fdr_generer">
      <button type="submit">Générer depuis les onglets</button>
    </form>
  <?php endif; ?>

  <form method="post" action="<?= e($lien('fdr')) ?>">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="pf" value="champs">
    <div class="ch">
      <label for="c-fdr">Feuille de route</label>
      <textarea id="c-fdr" name="v[fdr.texte]" rows="24" class="mono" <?= $ecrit ? '' : 'readonly' ?>><?= e((string)$d['fdr']['texte']) ?></textarea>
    </div>
    <?php if ($ecrit): ?><button type="submit">Enregistrer</button><?php endif; ?>
  </form>

<?php /* ═══════════════════════ RÉMUNÉRATION ═════════════════════════ */ ?>
<?php elseif ($onglet === 'remuneration'): ?>
  <?php require __DIR__ . '/_prod_remuneration.php'; ?>

<?php /* ══════════════════════════ BUDGET ════════════════════════════ */ ?>
<?php elseif ($onglet === 'budget'): ?>
  <?php require __DIR__ . '/_prod_budget.php'; ?>

<?php /* ══════════════════════════ DEVIS ═════════════════════════════ */ ?>
<?php elseif ($onglet === 'devis'): ?>
  <?php require __DIR__ . '/_prod_devis.php'; ?>

<?php /* ═════════════════════ DROITS D'AUTEUR ════════════════════════ */ ?>
<?php elseif ($onglet === 'droits'): ?>
  <?php require __DIR__ . '/_prod_droits.php'; ?>

<?php endif; ?>
</div>

<style>
.onglets.pf{display:flex;gap:2px;padding:12px 26px 0;border-bottom:1px solid var(--trait);
  overflow-x:auto}
.onglets.pf a{padding:8px 14px;font-size:13.5px;text-decoration:none;white-space:nowrap;
  color:var(--doux);border-bottom:2px solid transparent}
.onglets.pf a.ici{color:var(--encre);font-weight:600;border-bottom-color:var(--jaune,#FFD24D)}
.onglets.pf a:hover{color:var(--encre)}
.zone{padding:22px 26px 40px}
.ret{color:var(--doux);text-decoration:none}
.ret:hover{color:var(--encre)}
.deux{display:grid;grid-template-columns:1fr 1fr;gap:26px;margin-bottom:22px}
.quatre{display:grid;grid-template-columns:repeat(4,1fr);gap:0 16px}
.bl h3,h3{font-size:15px;margin:0 0 10px}
h3.sep{margin-top:30px;padding-top:20px;border-top:1px solid var(--trait)}
.ch{margin-bottom:15px}
.ch label{display:block;font-size:11.5px;font-weight:600;text-transform:uppercase;
  letter-spacing:.08em;color:var(--doux);margin-bottom:4px}
.ch input,.ch textarea,.ch select,dd input,dd select{width:100%;padding:8px 10px;
  font:inherit;font-size:14px;border:1px solid var(--trait);border-radius:5px;
  background:var(--papier);color:var(--encre);box-sizing:border-box}
.ch textarea{resize:vertical;line-height:1.5}
.ch textarea.mono{font-family:ui-monospace,Menlo,monospace;font-size:13px}
.aide{font-size:12.5px;color:var(--doux);margin:0 0 6px;max-width:74ch}
.aide.top{margin-bottom:16px}
pre.auto{background:var(--fond2);border:1px solid var(--trait);border-radius:5px;
  padding:10px 12px;font-size:12.5px;margin:0 0 8px;white-space:pre-wrap;max-height:180px;
  overflow:auto;font-family:ui-monospace,Menlo,monospace}
dl.info{display:grid;grid-template-columns:130px 1fr;gap:8px 14px;margin:0;font-size:14px}
dl.info dt{color:var(--doux);font-size:12.5px;padding-top:8px}
dl.info dd{margin:0}
button[type=submit]{padding:9px 20px;font:inherit;font-size:14px;font-weight:600;border:0;
  border-radius:5px;background:var(--encre);color:var(--papier);cursor:pointer;margin-top:6px}
button[type=submit]:hover{opacity:.88}
form.inline{display:inline}
form.inline button{margin-bottom:14px}
@media (max-width:820px){.deux{grid-template-columns:1fr}.quatre{grid-template-columns:1fr 1fr}}
</style>

<?php dash_bas();

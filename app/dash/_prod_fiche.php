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
    /* UNE ÉTIQUETTE VIDE N'EN POSE PAS. Dans une carte, le titre est déjà dans
       l'en-tête: la répéter au-dessus du champ ferait deux fois le même mot. */
    $h  = '<div class="ch">';
    if ($label !== '') $h .= '<label for="' . $id . '">' . e($label) . '</label>';
    if ($aide !== '') $h .= '<p class="aide">' . e($aide) . '</p>';
    $ro = $ecrit ? '' : ' readonly';
    $fa = $form !== '' ? ' form="' . e($form) . '"' : '';
    $h .= $lignes > 0
        ? '<textarea id="' . $id . '" name="v[' . e($chemin) . ']" rows="' . $lignes . '"' . $ro . $fa . '>' . e($val) . '</textarea>'
        : '<input type="text" id="' . $id . '" name="v[' . e($chemin) . ']" value="' . e($val) . '"' . $ro . $fa . '>';
    return $h . '</div>';
};

/**
 * Une carte: un en-tête qui nomme, un corps qui contient.  [Anna, 22.08.2026]
 *
 * « refazer a mise en page da synthese baseada nas imagens » et « adaptar a
 * mise en page da parte dossier segundo as imagens ». Les deux modèles qu'elle
 * a envoyés dessinent la même chose: des blocs encadrés, deux par rangée, avec
 * un bandeau de titre et parfois un bouton à droite de ce bandeau.
 *
 * C'EST LA MÊME FONCTION POUR LES DEUX ONGLETS, et pour ceux qui suivront. Les
 * écrire chacun de son côté aurait produit deux cartes qui se ressemblent sans
 * être pareilles — c'est ainsi qu'un dashboard finit avec quatre gris de fond.
 */
$carte = function (string $titre, string $corps, string $action = ''): string {
    return '<section class="carte"><div class="carte-t"><h3>' . e($titre) . '</h3>'
         . ($action !== '' ? '<div class="carte-d">' . $action . '</div>' : '')
         . '</div><div class="carte-b">' . $corps . '</div></section>';
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

  <?php /* LA SYNTHÈSE EN CARTES.  [Anna, 22.08.2026]
       « refazer a mise en page da synthese baseada nas imagens ». Ses modèles
       montrent une carte large pour l'identité du projet, puis des cartes deux
       par rangée pour le reste.

       LE FORMULAIRE SE FERME TOUT DE SUITE ET LES CHAMPS LE NOMMENT. L'équipe
       est un formulaire à part et ne peut pas s'imbriquer dans un autre; les
       cartes doivent pourtant s'écrire dans l'ordre du modèle, l'équipe au
       milieu. Chaque champ porte donc `form="fSyn"` et part avec lui d'où qu'il
       soit dans la page — un seul bouton Enregistrer pour toute la synthèse.

       CE QUI N'EST PAS ICI ET QUI EST DANS SES IMAGES: discipline, statut, dates
       de début et de fin, ville, pays, lieu de présentation, image du projet,
       documents de diffusion, totaux du projet. Ce sont des champs de l'ancien
       dashboard qui n'existent pas dans ce modèle-ci. La mise en page se fait
       sans eux; les ajouter est une autre décision, et elle lui appartient. */ ?>
  <form id="fSyn" method="post" action="<?= e($lien('synthese')) ?>">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="pf" value="champs">
  </form>

  <?php ob_start(); ?>
    <div class="trois">
      <div class="ch"><label>Titre</label><p class="fixe"><?= e($titre) ?></p></div>
      <div class="ch"><label>Création</label>
        <p class="fixe"><?= $p['year_creation'] ? e((string)$p['year_creation']) : '—' ?></p></div>
      <div class="ch"><label>Durée</label>
        <p class="fixe"><?= $p['duration_min'] ? (int)$p['duration_min'] . ' min' : '—' ?></p></div>

      <div class="ch"><label for="c-phase">Phase</label>
        <select id="c-phase" name="prod[phase]" form="fSyn" <?= $ecrit ? '' : 'disabled' ?>>
          <?php foreach (['dev'=>'Développement','creation'=>'Création','production'=>'Production',
                          'promo'=>'Promotion','tournee'=>'Tournée','cloture'=>'Clôture'] as $k => $v): ?>
            <option value="<?= $k ?>" <?= ($prod['phase'] ?? 'dev') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ch"><label for="c-porteur">Association porteuse</label>
        <select id="c-porteur" name="prod[organisation_id]" form="fSyn" <?= $ecrit ? '' : 'disabled' ?>>
          <option value="">(aucune)</option>
          <?php foreach (DB::all("SELECT id, nom FROM organisation WHERE supprime_le IS NULL
                                  ORDER BY genre, nom") as $o): ?>
            <option value="<?= (int)$o['id'] ?>" <?= (int)($prod['organisation_id'] ?? 0) === (int)$o['id'] ? 'selected' : '' ?>><?= e((string)$o['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ch"><label for="c-resp">Responsable</label>
        <input type="text" id="c-resp" name="prod[responsable]" form="fSyn"
               value="<?= e((string)($prod['responsable'] ?? '')) ?>" <?= $ecrit ? '' : 'readonly' ?>></div>

      <div class="ch"><label for="c-lieuc">Lieu de création</label>
        <input type="text" id="c-lieuc" name="prod[lieu_creation]" form="fSyn"
               value="<?= e((string)($prod['lieu_creation'] ?? '')) ?>" <?= $ecrit ? '' : 'readonly' ?>></div>
      <?php /* LE CODE DU CMS SE TRADUIT.  [Anna, 22.08.2026] « na parte Public
           não tem uma case, tem um texto lá colocado direto "all", o que é
           isso? ». C'est la valeur brute de la liste fermée du CMS — `all` veut
           dire « Tout public ». Elle s'affichait telle quelle, ce qui ne veut
           rien dire pour qui lit. Même table que le site: `app/config/entities.php`. */ ?>
      <?php $PUBLICS = ['young' => 'Jeune public', 'all' => 'Tout public', 'adult' => 'Adultes']; ?>
      <div class="ch"><label>Public</label>
        <p class="fixe"><?= e($PUBLICS[(string)($p['public_cible'] ?? '')] ?? '—') ?></p></div>
      <div class="ch"><label for="c-budget">Budget du projet</label>
        <div class="paire">
          <input type="text" id="c-budget" name="prod[budget]" form="fSyn"
                 value="<?= $prod['budget'] !== null ? e((string)(0 + (float)$prod['budget'])) : '' ?>" <?= $ecrit ? '' : 'readonly' ?>>
          <select name="prod[devise]" form="fSyn" <?= $ecrit ? '' : 'disabled' ?>>
            <?php foreach (['CHF','EUR'] as $dv): ?>
              <option <?= ($prod['devise'] ?? 'CHF') === $dv ? 'selected' : '' ?>><?= $dv ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
    <p class="aide">Le titre, la durée et le public viennent du CMS et se modifient dans
       l'administration du site: les recopier ici ferait deux vérités. Le budget est celui du
       projet artistique, <strong>pas l'argent qui passe par Le Voisin</strong> — le détail se
       saisit dans l'onglet Budget.</p>
  <?php $corpsInfos = ob_get_clean(); ?>

  <div class="grille1">
    <?= $carte('Informations du projet', $corpsInfos,
               $ecrit ? '<button type="submit" form="fSyn" class="b-jaune">Enregistrer</button>' : '') ?>
  </div>

  <?php ob_start(); require __DIR__ . '/_prod_equipe.php'; $corpsEquipe = ob_get_clean(); ?>

  <div class="grille2">
    <?= $carte('Résumé du spectacle',
               $champ('resume', '', (string)$d['resume'],
                      'Le pitch, tel qu\'on l\'envoie. C\'est lui que le Dossier reprend.', 7, 'fSyn')) ?>
    <?= $carte('Équipe du projet', $corpsEquipe) ?>

    <?= $carte('Coproductions',
               $champ('coproductions', '', (string)$d['coproductions'], '', 5, 'fSyn')) ?>
    <?= $carte('Soutiens',
               $champ('soutiens', '', (string)$d['soutiens'], '', 5, 'fSyn')) ?>

    <?php ob_start(); ?>
      <div class="deux">
        <?= $champ('statistiques.representations', 'Représentations', (string)$d['statistiques']['representations'], '', 0, 'fSyn') ?>
        <?= $champ('statistiques.spectateurs', 'Spectateurs', (string)$d['statistiques']['spectateurs'], '', 0, 'fSyn') ?>
        <?= $champ('statistiques.recettes', 'Recettes', (string)$d['statistiques']['recettes'], '', 0, 'fSyn') ?>
        <?= $champ('statistiques.villes', 'Villes jouées', (string)$d['statistiques']['villes'], '', 0, 'fSyn') ?>
      </div>
      <?= $champ('statistiques.notes', 'Notes', (string)$d['statistiques']['notes'], '', 2, 'fSyn') ?>
    <?php $corpsStat = ob_get_clean(); ?>
    <?= $carte('Statistiques', $corpsStat) ?>

    <?= $carte('Notes de production',
               '<div class="ch"><textarea id="c-notes-prod" name="prod[notes]" rows="6" form="fSyn"'
               . ($ecrit ? '' : ' readonly') . '>' . e((string)($prod['notes'] ?? '')) . '</textarea></div>') ?>
  </div>

  <?php /* PAS DE SECOND « Enregistrer » EN BAS. Celui de l'en-tête de la carte
       d'identité enregistre déjà toute la synthèse, cartes du bas comprises.
       Deux boutons identiques pour un seul enregistrement, c'est exactement ce
       qu'Anna a fait retirer de l'onglet Budget le même jour. */ ?>

<?php /* ══════════════════════════ DOSSIER ═══════════════════════════ */ ?>
<?php elseif ($onglet === 'dossier'): ?>

  <p class="aide top">Les textes du dossier de demande de fonds. Le résumé, les coproductions
     et les soutiens viennent de la Synthèse — inutile de les ressaisir ici.</p>

  <?php /* SIX CARTES, DEUX PAR RANGÉE.  [Anna, 22.08.2026] C'était une colonne
       de six grands textes empilés, et il fallait dérouler pour savoir ce que le
       dossier contenait. Les cartes le disent d'un regard, et la moitié de la
       largeur suffit à chaque texte: aucun ne se lit ligne à ligne, on les
       remplit et on les relit. */ ?>
  <form id="fDos" method="post" action="<?= e($lien('dossier')) ?>">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="pf" value="champs">
  </form>

  <div class="grille2">
    <?= $carte('Lettre de motivation',
               $champ('dossier.lettre', '', (string)$d['dossier']['lettre'],
                      'Adressée au financeur: pourquoi ce projet, pourquoi maintenant, pourquoi vous.', 8, 'fDos')) ?>

    <?= $carte('Description du projet',
               $champ('dossier.description', '', (string)$d['dossier']['description'],
                      'Présentation détaillée. Le résumé court reste dans la Synthèse.', 8, 'fDos')) ?>

    <?= $carte('Note d\'intention',
               $champ('dossier.intention', '', (string)$d['dossier']['intention'],
                      'Le propos artistique, la démarche, l\'écriture.', 8, 'fDos')) ?>

    <?php /* LE CALENDRIER GARDE SON CORPS À LA MAIN: il montre d'abord ce que le
         Planning contient déjà, et le bouton de son en-tête y renvoie plutôt que
         de faire retaper les dates ici. */ ?>
    <?php ob_start(); $auto = ProdFiche::calendrierDepuisPlanning($d); ?>
      <?php if ($auto !== ''): ?>
        <p class="aide">Les étapes du Planning, telles qu'elles y sont saisies:</p>
        <pre class="auto"><?= e($auto) ?></pre>
      <?php else: ?>
        <p class="aide">Aucune étape dans le Planning — rien à reprendre ici pour l'instant.</p>
      <?php endif; ?>
      <div class="ch">
        <textarea id="c-dossier-calendrier" name="v[dossier.calendrier]" rows="5" form="fDos"
                  <?= $ecrit ? '' : 'readonly' ?>><?= e((string)$d['dossier']['calendrier']) ?></textarea>
      </div>
    <?php $corpsCal = ob_get_clean(); ?>
    <?= $carte('Calendrier', $corpsCal,
               '<a class="carte-b2" href="' . e($lien('planning')) . '">Modifier les dates</a>') ?>

    <?= $carte('Public cible',
               $champ('dossier.publicCible', '', (string)$d['dossier']['publicCible'],
                      'Âges, publics spécifiques, médiation.', 5, 'fDos')) ?>

    <?= $carte('Bénéfice pour la ville',
               $champ('dossier.benefice', '', (string)$d['dossier']['benefice'],
                      'Retombées locales: rayonnement, emploi, publics, partenariats.', 5, 'fDos')) ?>
  </div>

  <?php if ($ecrit): ?>
    <div class="barre-enr"><button type="submit" form="fDos">Enregistrer</button></div>
  <?php endif; ?>

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
/* ── LES CARTES ───────────────────────────────────────────────────────────
   [Anna, 22.08.2026] Deux par rangée, `auto-fit` et non deux colonnes figées:
   sous 760 px de large deux cartes donneraient des zones de texte de six mots,
   et elles passent l'une sous l'autre d'elles-mêmes.
   `align-items:start` empêche une carte courte de s'étirer à la hauteur de sa
   voisine — sans lui, « Public cible » prendrait la taille de la lettre de
   motivation et son cadre serait vide aux trois quarts. */
.grille2{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,470px),1fr));
  gap:18px;align-items:start;margin:4px 0 0}
/* Une seule carte sur toute la largeur: même gouttière que la grille à deux,
   pour que les bords s'alignent d'une rangée à l'autre. */
.grille1{margin:4px 0 18px}
.carte{border:1px solid var(--trait);border-radius:10px;background:var(--papier);
  overflow:hidden}
.carte-t{display:flex;align-items:center;gap:12px;padding:12px 16px;
  background:var(--fond2);border-bottom:1px solid var(--trait)}
.carte-t h3{margin:0;font-size:14.5px}
.carte-d{margin-left:auto}
.carte-b{padding:14px 16px}
.carte-b .ch:last-child{margin-bottom:0}
/* Une valeur qui vient du CMS se lit, ne se saisit pas: pas de cadre autour. */
.carte-b p.fixe{margin:0;padding:8px 0;font-size:14px}
.carte-b .aide{margin:10px 0 0}
/* Le bouton d'en-tête de la carte d'identité: c'est l'action de l'écran, donc
   plein et jaune comme dans le modèle, et non en contour comme un renvoi. */
.b-jaune{margin-top:0;padding:7px 16px;font-size:13px;
  background:var(--jaune,#FFD24D);color:var(--encre)}
/* Un bouton d'en-tête est un renvoi, pas l'action de la carte: en contour. */
.carte-b2{display:inline-block;padding:5px 12px;border:1px solid var(--trait);
  border-radius:5px;font-size:12.5px;text-decoration:none;color:var(--encre);
  background:var(--papier);white-space:nowrap}
.carte-b2:hover{background:var(--fond2)}
/* L'ENREGISTREMENT SE RANGE À DROITE, comme les autres barres d'action. */
.barre-enr{display:flex;justify-content:flex-end;margin-top:18px}
.barre-enr button[type=submit]{margin-top:0}
/* TROIS COLONNES, ET NON « autant qu'il en tient ».  [22.08.2026] Écrit d'abord
   en `auto-fit minmax(220px)`, il en produisait cinq sur un grand écran: le
   montant du budget et sa monnaie se retrouvaient dans une case de 220 px et la
   monnaie repassait sous le montant — le défaut qu'on venait de corriger. Trois
   est la proportion du modèle, et elle se tient. */
.trois{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0 20px}
@media (max-width:900px){.trois{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:620px){.trois{grid-template-columns:minmax(0,1fr)}}

/* LE MONTANT ET SA MONNAIE SUR UNE LIGNE.  [Anna, 22.08.2026]
   « a parte budget do projet, porque tem campos em duas linhas? deixa o valor e
   a moeda na mesma linha ».
   `.paire` ÉTAIT UNE CLASSE SANS RÈGLE — écrite dans le HTML, jamais définie
   ici. Les deux champs héritaient donc du `width:100%` de `dd input, dd select`
   et n'avaient pas d'autre choix que de s'empiler. Un montant et sa monnaie
   sont une seule donnée: ils se lisent d'un trait. */
/* La règle visait `dd.paire`, du temps où le budget vivait dans une liste de
   définitions. La carte l'a mis dans un `div`, et la règle a cessé de le voir:
   la monnaie est repassée sous le montant. On vise la classe, pas la balise. */
.paire{display:flex;gap:8px;align-items:center}
.paire input{flex:1 1 auto;min-width:0}
.paire select{width:auto;flex:0 0 auto;padding-right:8px}

/* L'ÉQUIPE NE PREND QUE LA PLACE QU'IL LUI FAUT.  [Anna, 22.08.2026]
   « la partie équipe de projet, c'est trop large, il faut que ce soit plus
   rapproché ». Trois colonnes courtes — un prénom, un nom, une fonction —
   étirées sur toute la largeur de l'écran: l'œil devait traverser un vide pour
   relier le nom à ce que la personne fait. On borne la largeur et on resserre
   les lignes; le tableau garde son défilement si un nom déborde. */
.eq{max-width:660px}
.eq table{width:auto;min-width:100%;font-size:13.5px}
.eq th,.eq td{padding:5px 12px 5px 0}
.eq th:first-child,.eq td:first-child{padding-left:0}
.eq td.d,.eq th.d{width:1%;padding-right:0}
.eq button.x{background:none;border:0;color:var(--doux);cursor:pointer;font-size:15px;
  line-height:1;padding:0 2px}
.eq button.x:hover{color:#c8452f}
.eq form.inline button{margin-bottom:0}

/* La ligne d'ajout se range sous le tableau et sur la même largeur, sinon elle
   redevient le bloc large qu'on vient de resserrer. */
form.ajl.eq-aj{display:flex;gap:7px;flex-wrap:wrap;align-items:center;
  max-width:660px;margin-top:12px}
form.ajl.eq-aj input{padding:6px 9px;font:inherit;font-size:13.5px;
  border:1px solid var(--trait);border-radius:5px;background:var(--papier);color:var(--encre)}
form.ajl.eq-aj button[type=submit]{margin-top:0;padding:7px 15px;font-size:13.5px}

@media (max-width:820px){.deux{grid-template-columns:1fr}.quatre{grid-template-columns:1fr 1fr}}
</style>

<?php dash_bas();

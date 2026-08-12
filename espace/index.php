<?php
/** Espace collaborateur — accueil : informations, contractualisation, projets.
 *  [V12-ESPACE] [V32-DOC-ASSO] [V33-ESPACE-3] [V34-ONGLETS] [V35-FICHE-ONGLET] */
require __DIR__ . '/_inc.php';
require __DIR__ . '/_infos.php';
require __DIR__ . '/_docs.php';
MemberAuth::requireMember();
$m = MemberAuth::member();

/* [V36-FACTURES] Le dépôt d'une facture et les changements de statut, avant
   tout le reste : comme la fiche, ils redirigent quand ils ont travaillé, et
   une redirection ne se décide plus une fois la page commencée. Les deux
   traitements se partagent la même adresse sans se gêner — celui-ci ne
   reconnaît que $_POST['doc'], celui de la fiche que $_POST['fiche']. */
espace_docs_traiter($m);

/* [V35-FICHE-ONGLET] La fiche s'enregistre ici même, avant la moindre ligne de
   HTML : en cas de succès elle redirige, et une redirection ne se décide plus
   une fois la page commencée. L'ordre compte aussi pour une autre raison — la
   fiche peut déposer un passeport, qui est un document ; il faut donc relire
   les documents après elle, sinon la pièce qu'on vient d'envoyer manquerait à
   l'appel jusqu'au prochain rafraîchissement. */
$infos = espace_infos_traiter((int)$m['id']);
$docs  = MemberDocs::forMember((int)$m['id']);

/* ---------------------------------------------------------------------------
   Trois parties, trois onglets.                    [V33-ESPACE-3] [V34-ONGLETS]

   La page ne montrait qu'une longue liste de documents. Or on n'y vient pas
   pour la même chose : tantôt pour tenir ses informations à jour, tantôt pour
   retrouver un contrat ou une fiche de salaire, tantôt pour attraper le billet
   de train d'une tournée qui part demain. Trois besoins, trois moments, trois
   parties — et un onglet pour chacune, de sorte qu'on ne fasse défiler que ce
   qu'on est venu chercher.

   Les onglets sont trois liens ordinaires vers trois sections de la page. Sans
   JavaScript ils y conduisent, tout simplement, et les trois parties restent
   lisibles à la suite ; avec, ils n'en montrent qu'une à la fois. Rien ne
   dépend du script pour être atteignable.

   La deuxième et la troisième ne se rangent pas de la même façon, parce qu'on
   ne les cherche pas de la même façon. Un contrat se cherche par employeur —
   c'est l'association qui le range. Un billet se cherche par production — c'est
   le projet qui le range. La catégorie du document décide de son volet, donc de
   son classement : rien à choisir en plus, rien à oublier.
   --------------------------------------------------------------------------- */
$parVolet = ['contrat' => [], 'paiement' => [], 'projet' => []];
foreach ($docs as $d) $parVolet[MemberDocs::volet((string)$d['category'])][] = $d;

/* Un seul niveau de titre sous l'onglet : l'employeur, ou la production.
   La rubrique du document — contrat, billet, reçu — est écrite sur sa propre
   ligne, en gris. Un titre par rubrique faisait une cascade de titres pour un
   ou deux fichiers chacun ; la rubrique est un renseignement sur le document,
   pas un rayonnage. Les documents restent malgré tout groupés par rubrique à
   l'intérieur d'un bloc, dans l'ordre où elles sont déclarées. */
$ordonner = static function (array $liste, string $volet): array {
    $ordre = array_flip(MemberDocs::catsDuVolet($volet));
    $rang  = static fn(array $d): int => $ordre[(string)$d['category']] ?? 99;
    usort($liste, static fn($a, $b) => $rang($a) <=> $rang($b));
    return $liste;
};

/* Partie 2 — par association.                                 [V32-DOC-ASSO]
   Les documents sans association passent d'abord, et sans titre au-dessus :
   pour qui n'a qu'un employeur, la page reste celle d'hier. Puis les
   associations dans l'ordre des réglages, et pour finir celles qui n'y sont
   plus : une association effacée ne doit pas emporter ses documents. */
$byAsso = [];
foreach ($parVolet['contrat'] as $d) $byAsso[trim((string)($d['assoc'] ?? ''))][] = $d;

$ordreAsso = [];
if (isset($byAsso[''])) $ordreAsso[] = '';
foreach (array_keys(MemberDocs::assocChoix()) as $nom) if (isset($byAsso[$nom])) $ordreAsso[] = $nom;
foreach (array_keys($byAsso) as $nom) if (!in_array($nom, $ordreAsso, true)) $ordreAsso[] = $nom;

/* Partie 3 — par projet.
   Les projets viennent dans l'ordre du site, et « sans projet » ferme la
   marche : un document de production non rattaché est un oubli de rangement,
   pas une rubrique — le mettre en tête donnerait à toute la partie l'air d'un
   fourre-tout. Un projet effacé du site garde malgré tout ses documents
   visibles, sous son numéro. */
$projTitres = MemberDocs::projetChoix(I18n::$lang);
$byProj = [];
foreach ($parVolet['projet'] as $d) $byProj[(int)($d['project_id'] ?? 0)][] = $d;

$ordreProj = [];
foreach (array_keys($projTitres) as $pid) if (isset($byProj[$pid])) $ordreProj[] = (int)$pid;
foreach (array_keys($byProj) as $pid) if ($pid !== 0 && !in_array((int)$pid, $ordreProj, true)) $ordreProj[] = (int)$pid;
if (isset($byProj[0])) $ordreProj[] = 0;

espace_top(t('member_area'));
?>
<?php /* [V16-BONJOUR] « Bonjour » et le nom ne sont pas de même nature : l'un
         est une politesse, l'autre est ce qu'on vient lire. Sur une seule
         ligne et à la même taille, la salutation prenait la moitié du titre et
         poussait le nom à la ligne suivante. On met donc « Bonjour » en petit
         au-dessus, et le nom seul en dessous, à la taille exacte des titres de
         page du site. */ ?>
<div class="espace-intro">
  <h1 class="espace-hello">
    <span class="espace-hello-mot"><?= e(t('member_hello')) ?></span>
    <span class="espace-hello-nom"><?= e($m['name'] ?: $m['email']) ?></span>
  </h1>
  <p class="muted"><?= e(t('member_intro')) ?></p>
</div>

<?php /* [V35-FICHE-ONGLET] Quand la fiche vient d'être enregistrée, ou qu'elle
         revient avec une erreur, c'est son onglet qu'il faut rouvrir. L'ancre
         de l'adresse le dit déjà ; on l'écrit malgré tout dans la page, car
         une redirection perd parfois son ancre en chemin — et retrouver le
         formulaire refermé après l'avoir envoyé donnerait à croire que rien
         n'a été enregistré. */
$depart = ($infos['saved'] || $infos['errors']) ? ' data-depart="partie-infos"' : ''; ?>
<div class="espace-onglets" id="espace-onglets"<?= $depart ?>>
  <?php /* [V34-ONGLETS] Les onglets empruntent aux « chips » du site : même
           forme, même contour d'un pixel, et le jaune pour dire où l'on est —
           c'est déjà la langue du site, il n'y en aura pas une deuxième à
           apprendre. Le compte à côté du nom évite d'ouvrir un onglet pour
           découvrir qu'il est vide.
           [V37-NUMEROS] Chaque onglet porte son numéro, dans la même pastille
           noire que le titre de la partie qu'il ouvre : l'espace se dit en
           trois temps, et il faut pouvoir les compter avant d'entrer, pas
           seulement une fois dedans. Le numéro est devant, en pastille ; le
           compte reste derrière, en chiffre pâle — deux renseignements
           différents qui n'auraient pas dû se ressembler. */ ?>
  <nav class="chips espace-tabs" aria-label="<?= e(t('member_area')) ?>">
    <a class="chip espace-tab" id="onglet-infos" href="#partie-infos" data-cible="partie-infos"><span class="espace-tab-num" aria-hidden="true">1</span><?= e(t('member_tab1')) ?></a>
    <a class="chip espace-tab" id="onglet-contrats" href="#partie-contrats" data-cible="partie-contrats"><span class="espace-tab-num" aria-hidden="true">2</span><?= e(t('member_tab2')) ?><?php if ($parVolet['contrat']): ?><span class="espace-tab-n"><?= count($parVolet['contrat']) ?></span><?php endif; ?></a>
    <a class="chip espace-tab" id="onglet-paiements" href="#partie-paiements" data-cible="partie-paiements"><span class="espace-tab-num" aria-hidden="true">3</span><?= e(t('member_tab_paie')) ?><?php if ($parVolet['paiement']): ?><span class="espace-tab-n"><?= count($parVolet['paiement']) ?></span><?php endif; ?></a>
    <a class="chip espace-tab" id="onglet-projets" href="#partie-projets" data-cible="partie-projets"><span class="espace-tab-num" aria-hidden="true">4</span><?= e(t('member_tab3')) ?><?php if ($parVolet['projet']): ?><span class="espace-tab-n"><?= count($parVolet['projet']) ?></span><?php endif; ?></a>
  </nav>

<section class="espace-part" id="partie-infos" aria-labelledby="onglet-infos">
  <h2 class="espace-part-h"><span class="espace-part-n">1</span><?= e(t('member_part1')) ?></h2>
  <p class="espace-part-i"><?= e(t('member_part1_i')) ?></p>
  <?php /* [V35-FICHE-ONGLET] La fiche est ici, dépliée. Elle occupait une page
           à part, derrière un bouton « Mes informations → » : c'était un clic
           de trop pour la seule partie de l'espace où l'on ait quelque chose à
           faire, et cela dérogeait aux deux autres onglets, qui montrent leur
           contenu dès qu'on les ouvre. Reste le seul bouton qui mène vraiment
           ailleurs, la version imprimable — et lui s'ouvre dans un nouvel
           onglet du navigateur, la fiche à demi remplie reste derrière.
           [V15-FICHE-PDF] */ ?>
  <div class="espace-nav">
    <a class="btn ghost" href="<?= e(espace_url('fiche.php')) ?>"
       target="_blank" rel="noopener"><?= e(t('member_print')) ?></a>
  </div>
  <?= espace_infos_form((int)$m['id'], $infos) ?>
</section>

<section class="espace-part" id="partie-contrats" aria-labelledby="onglet-contrats">
  <h2 class="espace-part-h"><span class="espace-part-n">2</span><?= e(t('member_part2')) ?></h2>
  <p class="espace-part-i"><?= e(t('member_part2_i')) ?></p>
  <?php if (!$parVolet['contrat']): ?>
  <div class="espace-empty"><p><?= e(t('member_no_contrat')) ?></p></div>
  <?php else: foreach ($ordreAsso as $asso): $sigle = $asso === '' ? '' : MemberDocs::sigle($asso); ?>
  <?php if ($asso !== ''): ?>
  <h3 class="espace-asso"><?= e($asso) ?><?php if ($sigle !== ''): ?><span class="espace-asso-sigle"><?= e($sigle) ?></span><?php endif; ?></h3>
  <?php endif; ?>
  <?= espace_liste_docs($ordonner($byAsso[$asso], 'contrat')) ?>
  <?php endforeach; endif; ?>
</section>

<?php /* [12.08.2026] Les paiements, dans leur propre partie.

         Le dépôt et le mot qui suit un envoi restent EN DEHORS du cas « aucun
         document » : quelqu'un dont l'espace est vide est précisément
         quelqu'un qui a une première facture à envoyer. Les enfermer dans la
         branche « sinon » les rendrait invisibles à ceux qui en ont le plus
         besoin. */ ?>
<section class="espace-part" id="partie-paiements" aria-labelledby="onglet-paiements">
  <h2 class="espace-part-h"><span class="espace-part-n">3</span><?= e(t('member_part_paie')) ?></h2>
  <p class="espace-part-i"><?= e(t('member_part_paie_i')) ?></p>
  <?= espace_flash_html() ?>
  <?= espace_facture_form($m) ?>
  <?php if (!$parVolet['paiement']): ?>
  <div class="espace-empty"><p><?= e(t('member_no_paiement')) ?></p></div>
  <?php else: ?>
  <?= espace_liste_docs($ordonner($parVolet['paiement'], 'paiement')) ?>
  <?php endif; ?>
</section>

<section class="espace-part" id="partie-projets" aria-labelledby="onglet-projets">
  <h2 class="espace-part-h"><span class="espace-part-n">4</span><?= e(t('member_part3')) ?></h2>
  <p class="espace-part-i"><?= e(t('member_part3_i')) ?></p>
  <?php if (!$parVolet['projet']): ?>
  <div class="espace-empty"><p><?= e(t('member_no_projet')) ?></p></div>
  <?php else: foreach ($ordreProj as $pid):
      $titre = $pid === 0 ? t('member_no_proj_h') : ($projTitres[$pid] ?? ('#' . $pid)); ?>
  <h3 class="espace-proj<?= $pid === 0 ? ' espace-proj-sans' : '' ?>"><?= e($titre) ?></h3>
  <?= espace_liste_docs($ordonner($byProj[$pid], 'projet')) ?>
  <?php endforeach; endif; ?>
</section>
</div>

<script>
/* [V34-ONGLETS] Les onglets, une fois le script en place.
   Tout ce qui suit est un supplément : la page est déjà complète et navigable
   sans lui. On ne touche donc à rien tant qu'on n'a pas retrouvé les trois
   sections — mieux vaut une page longue qu'une page dont deux tiers ont
   disparu. L'onglet ouvert s'écrit dans l'adresse : on revient où l'on était
   après un téléchargement ou un rafraîchissement. */
(function () {
  var box = document.getElementById('espace-onglets');
  if (!box || !window.history) return;
  var barre = box.querySelector('.espace-tabs');
  var tabs  = Array.prototype.slice.call(box.querySelectorAll('.espace-tab'));
  var parts = tabs.map(function (t) { return document.getElementById(t.getAttribute('data-cible')); });
  /* [12.08.2026] « au moins deux », et non « exactement trois ».

     Le compte était écrit en dur du temps où l'espace avait trois parties.
     En ajoutant la quatrième — les paiements — le script cessait de
     s'initialiser d'un coup : plus d'onglets, plus de classe js-onglets, donc
     plus rien de ce qui en dépend. Le repli sans JavaScript faisait son
     travail, la page restait complète et lisible, et c'est pour cela que la
     panne se lisait comme « le menu ne tient plus » plutôt que comme une
     erreur.

     La condition dit maintenant ce qu'elle veut vraiment dire : il faut de
     quoi faire des onglets. Une cinquième partie n'aura rien à changer ici. */
  if (!barre || tabs.length < 2 || parts.indexOf(null) !== -1) return;

  box.className += ' js-onglets';
  barre.setAttribute('role', 'tablist');
  tabs.forEach(function (t, i) {
    t.setAttribute('role', 'tab');
    t.setAttribute('aria-controls', parts[i].id);
    parts[i].setAttribute('role', 'tabpanel');
    parts[i].setAttribute('tabindex', '0');
  });

  function montrer(i, ecrire) {
    tabs.forEach(function (t, j) {
      var actif = i === j;
      t.className = actif ? 'chip espace-tab on' : 'chip espace-tab';
      t.setAttribute('aria-selected', actif ? 'true' : 'false');
      t.setAttribute('tabindex', actif ? '0' : '-1');
      parts[j].hidden = !actif;
    });
    if (ecrire) history.replaceState(null, '', '#' + parts[i].id);
  }

  /* L'onglet du départ : celui que la page réclame — après un enregistrement,
     c'est la fiche —, puis celui de l'adresse, qui l'emporte parce qu'il vient
     du visiteur lui-même. À défaut, le premier. */
  var depart = 0, vise = box.getAttribute('data-depart') || '';
  parts.forEach(function (p, i) { if (p.id === vise) depart = i; });
  parts.forEach(function (p, i) { if ('#' + p.id === location.hash) depart = i; });
  montrer(depart, false);

  tabs.forEach(function (t, i) {
    t.addEventListener('click', function (ev) { ev.preventDefault(); montrer(i, true); t.focus(); });
    t.addEventListener('keydown', function (ev) {
      var pas = (ev.key === 'ArrowRight' || ev.key === 'ArrowDown') ? 1
              : (ev.key === 'ArrowLeft'  || ev.key === 'ArrowUp')   ? -1 : 0;
      if (!pas) return;
      ev.preventDefault();
      var n = (i + pas + tabs.length) % tabs.length;
      montrer(n, true);
      tabs[n].focus();
    });
  });
})();
</script>
<?php espace_bottom();

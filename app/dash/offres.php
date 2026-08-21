<?php
/**
 * Écran Offres — le pipeline des demandes entrantes. [16.08.2026]
 *
 * Ce que le formulaire public (demande.php) dépose arrive ici. L'écran répond
 * à une seule question, et son ordre de tri est fait pour elle: QU'EST-CE QUI
 * ATTEND UNE RÉPONSE. Les nouvelles d'abord, les classées à la fin — un tri
 * par date seule enterrerait une demande de la semaine dernière sous cinq
 * refus d'hier.
 *
 * LA CONVERSION EST LE GESTE QUI COMPTE. « Une offre acceptée se convertit en
 * booking avec tous les détails repris », dit la spécification. Elle crée la
 * date en `option` et non en `confirmed`: accepter une demande n'est pas avoir
 * un contrat signé, et confondre les deux ferait compter comme acquises des
 * dates qui ne le sont pas — précisément le reproche fait au tableur d'avant.
 */
declare(strict_types=1);

$STATUTS = Offers::STATUTS;
$peutEcrire = dash_droit('offres', dash_role()) === 'ecrit';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    dash_exige_ecriture('offres');

    $act = (string)($_POST['act'] ?? '');
    $oid = (int)($_POST['offre'] ?? 0);

    if ($act === 'statut' && $oid > 0) {
        $cp = trim((string)($_POST['contre_prix'] ?? ''));
        Offers::statut($oid, (string)($_POST['st'] ?? ''),
                       $cp !== '' ? (float)str_replace(',', '.', $cp) : null,
                       isset($_POST['notes']) ? (string)$_POST['notes'] : null);
        dash_flash('Demande mise à jour.');

    /* `creer` a disparu le 17.08 avec le formulaire: la saisie passe par Iris.
       `Offers::creerAuBureau()` reste dans la bibliothèque — c'est elle qu'Iris
       appellera — mais plus aucun POST de cet écran n'y mène. */

    } elseif ($act === 'convertir' && $oid > 0) {
        $bid = Offers::convertir($oid);
        if ($bid > 0) {
            dash_flash('Convertie en date. Elle est en option, pas confirmée.');
            redirect('/dashboard.php?e=bookings&b=' . $bid);
        }
        dash_flash('Conversion impossible.', 'err');
    }
    redirect('/dashboard.php?e=offres');
}

$filtre  = (string)($_GET['f'] ?? '');
$offreId = (int)($_GET['o'] ?? 0);
$offres  = Offers::liste($filtre);

/* La fiche s'ouvre meme si le filtre en cours ne la contient pas: on arrive
   souvent par un lien, et repondre « introuvable » a cause d'un filtre pose
   ailleurs serait un piege. */
$une = $offreId > 0 ? Offers::une($offreId) : null;
if ($offreId > 0 && !$une) {
    dash_flash('Cette offre n\'existe pas.', 'err');
    redirect('/dashboard.php?e=offres');
}
$n      = Offers::compter();
$porteurs = Offers::porteurs();
$total  = array_sum($n);

/* Le sous-titre dit d'abord ce qui attend une réponse, parce que c'est la
   seule chose qui demande un geste aujourd'hui. Le total vient après. */
$sousTitre = $n['nouvelle'] > 0
    ? '<strong>' . $n['nouvelle'] . '</strong> à traiter · ' . $total . ' en cours'
    : $total . ' devis et demandes en cours';

dash_haut('offres', $sousTitre);
?>

<?php /* LA GOUTTIÈRE DE 26 PX, QUI MANQUAIT À TOUT L'ÉCRAN. [Anna, 21.08.2026]
     « il y a encore des choses collées au menu ». Cet écran n'enveloppait rien
     dans `.zone`: les onglets « toutes / en discussion » commençaient à zéro,
     donc sous la barre noire, et le tableau s'étalait jusqu'au bord droit en
     poussant la page à déborder. Quatre écrans étaient dans ce cas — offres,
     personnel, projets, calendrier — et c'est le même oubli à chaque fois. */ ?>
<div class="zone">

<?php if ($une): ?>
  <?php
  /* UNE FICHE PAR OFFRE, ET NON TOUT EMPILE SOUS LA LISTE. [Anna, 21.08.2026]
     « pourquoi il y a une liste et le descriptif des offres en dessous ? ça
     devrait ouvrir la page de chacune des offres avec son détail, pas tout
     mélangé comme ça ».

     L'ecran montrait le tableau PUIS les sept fiches completes a la suite,
     reliees par une ancre. Sept tiennent encore; a trente la page devient un
     mur, et l'ancre ne dit plus ou l'on est ni ou l'on retourne. La liste
     liste, la fiche detaille.

     Les fleches gardent le filtre en cours, comme dans les Evenements. */
  $ids  = array_map(fn($x) => (int)$x['id'], $offres);
  $i    = array_search($offreId, $ids, true);
  $ctx  = $filtre !== '' ? '&amp;f=' . rawurlencode($filtre) : '';
  $lien = fn($n) => '/dashboard.php?e=offres&amp;o=' . (int)$n . $ctx;
  $o    = $une;
  $oid  = $offreId;
  ?>
  <div class="fil-o">
    <a href="/dashboard.php?e=offres<?= $ctx ?>">← toutes les offres</a>
    <?php if ($i !== false): ?>
      <?php if (isset($ids[$i - 1])): ?>
        <a class="pas" href="<?= $lien($ids[$i - 1]) ?>">← précédente</a>
      <?php else: ?><span class="pas mort">← précédente</span><?php endif; ?>
      <span class="rang"><?= $i + 1 ?> / <?= count($ids) ?></span>
      <?php if (isset($ids[$i + 1])): ?>
        <a class="pas" href="<?= $lien($ids[$i + 1]) ?>">suivante →</a>
      <?php else: ?><span class="pas mort">suivante →</span><?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="offre st-<?= e($o['statut']) ?>" id="o-<?= $oid ?>">
    <div class="tete-o">
      <div>
        <span class="quand"><?= e(date('d.m.Y', strtotime((string)$o['cree_a']))) ?></span>
        <strong><?= e($o['projet'] ?: 'spectacle non précisé') ?></strong>
        <?php if ($o['venue']): ?> · <?= e((string)$o['venue']) ?><?php endif; ?>
        <?php if ($o['ville']): ?>, <?= e((string)$o['ville']) ?><?php endif; ?>
        <?php if ($o['pays']): ?> (<?= e((string)$o['pays']) ?>)<?php endif; ?>
      </div>
      <span class="et et-o<?= e($o['statut']) ?>"><?= e($STATUTS[$o['statut']]) ?></span>
    </div>

    <div class="corps-o">
      <dl>
        <?php if ($o['date_souhaitee'] || $o['date_texte']): ?>
          <dt>Quand</dt><dd>
            <?= $o['date_souhaitee'] ? e(date('d.m.Y', strtotime((string)$o['date_souhaitee']))) : '' ?>
            <?= $o['date_texte'] ? '<span class="sec">' . e((string)$o['date_texte']) . '</span>' : '' ?>
            <?php if ($o['representations']): ?> · <?= (int)$o['representations'] ?> représentation<?= (int)$o['representations'] > 1 ? 's' : '' ?><?php endif; ?>
          </dd>
        <?php endif; ?>

        <?php if ($o['budget'] !== null): ?>
          <dt>Budget annoncé</dt>
          <dd><strong><?= number_format((float)$o['budget'],2,',',' ') ?> <?= e($o['devise']) ?></strong>
            <?php if ($o['contre_prix'] !== null): ?>
              · contre-proposé <strong><?= number_format((float)$o['contre_prix'],2,',',' ') ?></strong>
            <?php endif; ?>
          </dd>
        <?php endif; ?>

        <dt>Qui</dt>
        <dd><?= e($o['contact_nom'] ?? '') ?><?php if ($o['contact_role']): ?>, <?= e((string)$o['contact_role']) ?><?php endif; ?>
          <?php if ($o['structure']): ?><br><?= e((string)$o['structure']) ?><?php endif; ?>
          <br><a href="mailto:<?= e((string)$o['contact_email']) ?>"><?= e((string)$o['contact_email']) ?></a>
          <?php if ($o['contact_tel']): ?> · <?= e((string)$o['contact_tel']) ?><?php endif; ?>
        </dd>

        <?php if ($o['message']): ?>
          <dt>Ce qu'ils écrivent</dt><dd class="msg-o"><?= nl2br(e((string)$o['message'])) ?></dd>
        <?php endif; ?>

        <?php if ($o['notes_internes']): ?>
          <dt>Notes</dt><dd class="ni"><?= nl2br(e((string)$o['notes_internes'])) ?></dd>
        <?php endif; ?>

        <?php if ($o['booking_id']): ?>
          <dt>Date née d'elle</dt>
          <dd><a href="/dashboard.php?e=bookings&amp;b=<?= (int)$o['booking_id'] ?>">voir la date</a></dd>
        <?php endif; ?>
      </dl>

      <?php if ($peutEcrire && !$o['booking_id']): ?>
        <form method="post" action="/dashboard.php?e=offres" class="ajl">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="act" value="statut">
          <input type="hidden" name="offre" value="<?= $oid ?>">
          <input type="text" name="contre_prix" placeholder="Contre-proposer" size="10"
                 value="<?= $o['contre_prix'] !== null ? e((string)$o['contre_prix']) : '' ?>">
          <input type="text" name="notes" placeholder="Note interne" size="24"
                 value="<?= e((string)($o['notes_internes'] ?? '')) ?>">
          <select name="st">
            <?php foreach ($STATUTS as $k => $lib): ?>
              <option value="<?= $k ?>" <?= $o['statut'] === $k ? 'selected' : '' ?>><?= e($lib) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit">enregistrer</button>
        </form>

        <form method="post" action="/dashboard.php?e=offres" class="conv"
              onsubmit="return confirm('Créer une date à partir de cette demande ?')">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="act" value="convertir">
          <input type="hidden" name="offre" value="<?= $oid ?>">
          <button type="submit">convertir en date</button>
          <span class="sec pt">Crée un booking en <strong>option</strong>, avec tout ce qui
            est dit ici recopié dans ses notes.</span>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div><!-- .zone -->
<?php dash_bas(); return; ?>
<?php endif; ?>


<div class="filtres">
  <a href="/dashboard.php?e=offres" class="<?= $filtre === '' ? 'ici' : '' ?>">toutes (<?= $total ?>)</a>
  <?php foreach ($STATUTS as $k => $lib): if (!$n[$k]) continue; ?>
    <a href="/dashboard.php?e=offres&amp;f=<?= $k ?>" class="<?= $filtre === $k ? 'ici' : '' ?>"><?= e($lib) ?> (<?= $n[$k] ?>)</a>
  <?php endforeach; ?>
</div>

<?php if (!$offres): ?>
  <p class="vide">Aucun devis ni demande<?= $filtre ? ' dans cet état' : '' ?> en cours.
     <?php if (!$filtre): ?><br><span class="sec">Deux portes alimentent cette page:
     le formulaire public <code>/demande.php</code> — le lien à mettre dans une
     signature d'e-mail ou un dossier de diffusion — et Iris, à qui l'on colle un
     courriel entier et qui en tire ce qu'elle reconnaît.</span><?php endif; ?></p>
<?php else: ?>

<?php /* ── LE TABLEAU ────────────────────────────────────────────────────
     [16.08.2026] Demandé par Anna: « fazer lista com colunas de assos, prix,
     ville pays ». Les fiches en dessous servent à AGIR sur une demande; le
     tableau sert à les VOIR TOUTES — combien, où, à quel prix, dans quel état.
     Ce sont deux gestes différents et un seul affichage ne fait bien ni l'un
     ni l'autre: la fiche est trop haute pour comparer dix lignes, le tableau
     trop étroit pour contre-proposer.

     La colonne Association n'est pas dans la table `offer`: elle se déduit du
     spectacle, dont le porteur vit dans `projet_prod`. La stocker en ferait
     une deuxième vérité, qui se tromperait le jour où une pièce change de
     porteur. */ ?>
<div class="tw"><table class="tofr">
  <thead><tr>
    <th>Reçue</th><th>Spectacle</th><th>Association</th><th>Lieu</th>
    <th>Ville, pays</th><th>Quand</th><th class="d">Prix</th><th>État</th>
  </tr></thead>
  <tbody>
  <?php foreach ($offres as $o): ?>
    <tr>
      <td class="sec"><?= e(date('d.m.y', strtotime((string)$o['cree_a']))) ?></td>
      <td><a href="/dashboard.php?e=offres&amp;o=<?= (int)$o['id'] ?><?=
        $filtre !== '' ? '&amp;f=' . rawurlencode($filtre) : '' ?>"><?=
        e($o['projet'] ?: '—') ?></a></td>
      <td class="sec"><?php $as = Offers::porteurDe((string)($o['projet'] ?? ''), $porteurs); ?>
        <?= $as !== '' ? e($as) : '<span class="sec">—</span>' ?></td>
      <td><?= e((string)($o['venue'] ?? '')) ?></td>
      <td class="sec"><?= e(trim(((string)$o['ville']) . (($o['ville'] && $o['pays']) ? ', ' : '') . (string)$o['pays'])) ?></td>
      <td class="sec"><?= $o['date_souhaitee']
            ? e(date('d.m.Y', strtotime((string)$o['date_souhaitee'])))
            : e((string)($o['date_texte'] ?? '')) ?>
        <?php if ($o['representations']): ?> · <?= (int)$o['representations'] ?>×<?php endif; ?></td>
      <td class="d"><?php if ($o['budget'] !== null): ?>
          <?= number_format((float)$o['budget'], 0, ',', ' ') ?> <?= e($o['devise']) ?>
          <?php if ($o['contre_prix'] !== null): ?><br><span class="cp">contre
            <?= number_format((float)$o['contre_prix'], 0, ',', ' ') ?></span><?php endif; ?>
        <?php else: ?><span class="sec">—</span><?php endif; ?></td>
      <td><span class="et et-o<?= e($o['statut']) ?>"><?= e($STATUTS[$o['statut']]) ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<?php endif; ?>

<?php /* ── PLUS DE SAISIE À LA MAIN ICI ───────────────────────────── [17.08.2026]
     Anna: « ajouter une offre, quem vai fazer isso é a Iris. eu vou copiar e
     colar tudo lá e o sistema faz a triagem de informações e pergunta o que
     falta. entao tem que tirar essa opção de lá ».

     Le formulaire d'ajout posé le 16.08 est donc retiré. Il partait d'une
     supposition juste — les vraies demandes arrivent par courriel, pas par le
     formulaire public — et d'une mauvaise réponse: quinze champs à recopier à
     la main depuis un courriel qu'on a déjà sous les yeux. On ne le fait pas
     deux fois.

     L'entrée passe par Iris: on colle le courriel entier, elle en tire ce
     qu'elle reconnaît et demande ce qui manque. Le geste devient un
     copier-coller, et ce qui manque est signalé au lieu d'être oublié.

     LA PAGE NE PERD RIEN EN ATTENDANT: `demande.php` continue d'alimenter le
     pipeline, et les devis envoyés y entrent aussi — un devis parti est une
     offre en cours, c'est la même chose vue de l'autre côté. */ ?>


<style>
.tofr td{vertical-align:top}
.tofr .cp{font-size:11.5px;color:var(--doux)}
.filtres{display:flex;gap:14px;flex-wrap:wrap;padding:0 0 18px;font-size:13.5px}
/* Le fil de la fiche. Les fleches gardent leur place aux extremites: un bouton
   qui disparait decale les autres sous le curseur. */
.fil-o{display:flex;gap:16px;align-items:baseline;margin:0 0 20px;font-size:13px}
.fil-o a{color:var(--doux);text-decoration:none}
.fil-o a:hover{color:var(--encre)}
.fil-o .pas{color:var(--encre);font-weight:600}
.fil-o .pas.mort{color:var(--doux);opacity:.35}
.fil-o .rang{color:var(--doux);font-variant-numeric:tabular-nums}
.filtres a{color:var(--doux);text-decoration:none}
.filtres a.ici{color:var(--encre);font-weight:600}
.offre{border:1px solid var(--trait);border-radius:6px;margin-bottom:14px;overflow:hidden}
.offre.st-nouvelle{border-left:3px solid var(--jaune,#FFD24D)}
.offre.st-acceptee{border-left:3px solid #7bb33a}
.offre.st-refusee,.offre.st-sans_suite{opacity:.62}
.tete-o{display:flex;justify-content:space-between;align-items:center;gap:12px;
  padding:11px 16px;background:var(--fond2);font-size:14.5px}
.quand{color:var(--doux);font-size:12.5px;margin-right:8px}
.corps-o{padding:14px 16px}
.corps-o dl{display:grid;grid-template-columns:130px 1fr;gap:5px 14px;margin:0 0 12px;font-size:14px}
.corps-o dt{color:var(--doux);font-size:12.5px;padding-top:2px}
.corps-o dd{margin:0}
.msg-o{white-space:normal;max-width:70ch}
.ni{color:#9a7a2a}
.conv{margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.conv button{padding:6px 15px;font-size:13px}
.et-onouvelle{border-color:#d9a800;font-weight:600}
.et-oacceptee{border-color:#7bb33a;font-weight:600}
.et{font-size:12px;padding:2px 7px;border-radius:3px;white-space:nowrap;border:1px solid var(--trait)}
@media (max-width:640px){.corps-o dl{grid-template-columns:1fr}.corps-o dt{padding-top:8px}}
</style>

</div><!-- .zone -->

<?php dash_bas();

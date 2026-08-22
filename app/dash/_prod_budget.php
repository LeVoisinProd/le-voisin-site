<?php
/**
 * Onglet Budget. [16.08.2026, refait le même jour]
 *
 * REFAIT SUR L'ÉCRAN D'ANNA. La première version mettait dépenses et recettes
 * dans une seule liste triée par « nature ». Ce n'est pas ainsi qu'on lit un
 * budget de production: un dossier de subvention se lit POSTE PAR POSTE, et
 * « Hébergement » n'est pas un poste — c'est une ligne dans les frais de
 * production. Regrouper de tête devant un jury n'est pas une option.
 *
 *   CHARGES à gauche, en quatre postes   PRODUITS à droite, par partenaire
 *   TOTAL CHARGES                        TOTAL PRODUITS
 *                    SOLDE DU PROJET
 *
 * LA MASSE SALARIALE EST CALCULÉE, PAS SAISIE, et l'écran le dit — « auto ».
 * Elle est la somme de l'onglet Rémunération. Une somme retapée diverge à la
 * première rémunération modifiée, et c'est le poste le plus gros du budget:
 * l'erreur s'y voit le moins et coûte le plus.
 *
 * UN PRODUIT PORTE UN PARTENAIRE. « Coproduction: 15 000 » ne dit pas qui
 * coproduit, et c'est la première question qu'on pose en lisant un budget.
 *
 * CE N'EST PAS LA COMPTABILITÉ. Les écritures réelles vivent dans Bexio et
 * Banana; ceci est le prévisionnel, celui qu'on met dans un dossier. Les
 * confondre ferait deux comptabilités dont aucune ne serait juste.
 */
declare(strict_types=1);
/** @var array $d */ /** @var bool $ecrit */ /** @var callable $lien */

/* ── À QUOI SE RAPPORTE UNE LIGNE.  [Anna, 22.08.2026] ──────────────────────
   « na parte para ajouter uma nova linha de frais, incluir o campo projet
   entier, ou as fases de cada etapa ».

   Une charge n'appartient pas toujours au projet entier: le décor se paie une
   fois, mais les défraiements se rattachent à la résidence de mars ou à la série
   de novembre. Sans ce champ, un budget de tournée est un tas.

   VIDE VEUT DIRE « PROJET ENTIER », et c'est ce qui permet de ne rien casser:
   toutes les lignes déjà saisies restent ce qu'elles sont sans qu'on les
   reprenne. */
$ETAPES_B = [];
foreach (($d['planning']['dates'] ?? []) as $etB) {
    $f = static fn($x) => ($t = strtotime((string)$x)) ? date('d.m.Y', $t) : (string)$x;
    $a = (string)($etB['debut'] ?? ''); $b = (string)($etB['fin'] ?? '');
    $lb = trim(implode(' · ', array_filter([
        ($a === '' && $b === '') ? '' : (($b === '' || $b === $a) ? $f($a) : $f($a) . ' – ' . $f($b)),
        ProdFiche::PHASES[$etB['phase'] ?? ''] ?? '',
        trim(((string)($etB['lieu'] ?? '')) . (($etB['ville'] ?? '') ? ', ' . $etB['ville'] : ''), ', '),
    ])));
    if ($lb !== '') $ETAPES_B[] = $lb;
}

$B  = ProdFiche::budgetParPoste($d);
$mt = static fn(float $v): string => number_format($v, 0, ',', ' ');
?>

<div class="bd-tete">
  <h3>Budget de production</h3>
  <?php /* IL Y AVAIT ICI UN SECOND « PDF — Budget ».  [Anna, 22.08.2026]
       « na parte budget tem dois botões de budget, deixar um só ».

       Celui-ci datait du 16.08, quand chaque onglet portait son propre bouton
       d'impression. La barre de documents posée depuis en haut de la fiche en
       donne déjà un, sur tous les onglets, et il ouvre EXACTEMENT la même
       adresse. Deux boutons identiques pour un seul document font douter qu'ils
       soient identiques — on les compare au lieu de cliquer. */ ?>
</div>

<div class="bd">
  <?php /* ── CHARGES ─────────────────────────────────────────────────────── */ ?>
  <div class="bd-col">
    <div class="bd-t">Charges</div>

    <?php foreach (ProdFiche::BUDGET_POSTES as $cle => $lib): $p = $B['postes'][$cle]; ?>
      <div class="bd-poste">
        <div class="bd-p-t"><?= e($lib) ?></div>

        <?php if ($cle === 'personnel'): ?>
          <div class="bd-l bd-auto">
            <span>Salaires équipe (rémunération TTC) <span class="auto">auto</span></span>
            <b><?= $mt($p['auto']) ?> CHF</b>
          </div>
          <?php if ($p['auto'] <= 0): ?>
            <p class="bd-vide">Rien dans l'onglet Rémunération. Ce poste se remplit là-bas et
               pas ici — c'est ce qui garantit que les deux disent la même chose.</p>
          <?php endif; ?>
        <?php endif; ?>

          <?php /* ── UN SEUL RANG PAR LIGNE, ET LE + AVEC LES AUTRES.  [Anna, 22.08.2026]
               « os botões de + têm que estar na mesma linha quando adicionamos
               uma charge ou produit, e está tudo deformado na mise en page ».

               ELLE A RAISON ET C'ÉTAIT PIRE QU'UN DÉFAUT D'ALIGNEMENT: le HTML
               lui-même était cassé. Deux insertions automatiques successives
               avaient laissé le formulaire d'ajout À L'INTÉRIEUR de celui qui
               corrige, et le bouton d'effacement flottait en marge négative et
               hauteur nulle pour retomber au bout de la ligne. Un navigateur
               démêle cela comme il peut, et ce qu'il peut n'est pas beau.

               Ce bloc est réécrit à la main, d'un tenant. La ligne est un `div`
               en flex sans retour à la ligne: chaque champ a le droit de maigrir
               jusqu'à zéro, donc rien ne pousse le bouton en dessous. Le
               formulaire qui corrige est déclaré vide et ses champs le nomment
               par `form="bdE-xxx"`; celui qui efface est écrit à sa place, au
               bout du rang. Plus rien à compenser. */ ?>
          <?php foreach ($p['lignes'] as $l): $lid = (string)($l['id'] ?? ''); $fb = 'bdE-' . $lid; ?>
            <?php if ($ecrit): ?>
              <div class="bd-l bd-ed">
                <form method="post" action="<?= e($lien('budget')) ?>" id="<?= e($fb) ?>" class="bd-f">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="pf" value="liste_modifier">
                  <input type="hidden" name="ou" value="budget">
                  <input type="hidden" name="ligne" value="<?= e($lid) ?>">
                </form>
                <input type="text" name="l[libelle]" form="<?= e($fb) ?>" class="bd-lib"
                       value="<?= e((string)($l['libelle'] ?? '')) ?>" placeholder="Libellé">
                <?php if ($ETAPES_B): ?>
                  <select name="l[etape]" form="<?= e($fb) ?>" class="bd-et">
                    <option value="">Projet entier</option>
                    <?php foreach ($ETAPES_B as $lb): ?>
                      <option value="<?= e($lb) ?>" <?= (string)($l['etape'] ?? '') === $lb ? 'selected' : '' ?>><?= e($lb) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php endif; ?>
                <input type="text" name="l[montant]" form="<?= e($fb) ?>" class="bd-mt"
                       value="<?= e((string)($l['montant'] ?? '')) ?>" inputmode="decimal">
                <select name="l[devise]" form="<?= e($fb) ?>" class="bd-dv">
                  <?php foreach (['CHF','EUR'] as $dv): ?>
                    <option <?= (string)($l['devise'] ?? 'CHF') === $dv ? 'selected' : '' ?>><?= $dv ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if ((string)($l['devise'] ?? 'CHF') === 'EUR'): ?>
                  <span class="bd-cv" title="Taux BCE du <?= e((string)($l['taux_jour'] ?? '?')) ?>">= <?= $mt((float)$l['_m']) ?> CHF</span>
                <?php endif; ?>
                <button type="submit" form="<?= e($fb) ?>" class="bd-ok" title="Enregistrer">✓</button>
                <form method="post" action="<?= e($lien('budget')) ?>" class="bd-x"
                      onsubmit="return confirm('Retirer cette ligne du budget ?')">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="pf" value="liste_retirer">
                  <input type="hidden" name="ou" value="budget">
                  <input type="hidden" name="ligne" value="<?= e($lid) ?>">
                  <button type="submit" class="x" title="Retirer">×</button>
                </form>
              </div>
            <?php else: ?>
              <div class="bd-l">
                <span><?= e((string)($l['libelle'] ?? '')) ?: '—' ?></span>
                <b><?= $mt((float)$l['_m']) ?> CHF</b>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>

          <?php if ($ecrit && $cle !== 'personnel'): ?>
            <?php /* La ligne d'ajout du poste, à la même largeur et au même rang:
                 on écrit là où on lit. Le poste est celui-ci, il ne se choisit
                 pas. « Frais de personnel » n'en a pas — il se remplit depuis la
                 Rémunération, et une saisie ici ferait un double compte. */ ?>
            <form method="post" action="<?= e($lien('budget')) ?>" class="bd-l bd-aj">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="pf" value="liste_ajouter">
              <input type="hidden" name="ou" value="budget">
              <input type="hidden" name="l[sens]" value="depense">
              <input type="hidden" name="l[poste]" value="<?= e($cle) ?>">
              <input type="text" name="l[libelle]" placeholder="Ajouter une ligne" required class="bd-lib">
              <?php if ($ETAPES_B): ?>
                <select name="l[etape]" class="bd-et" title="À quoi se rapporte cette ligne">
                  <option value="">Projet entier</option>
                  <?php foreach ($ETAPES_B as $lb): ?>
                    <option value="<?= e($lb) ?>"><?= e($lb) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
              <input type="text" name="l[montant]" placeholder="Montant" inputmode="decimal" class="bd-mt">
              <select name="l[devise]" class="bd-dv"><option>CHF</option><option>EUR</option></select>
              <button type="submit" class="bd-plus" title="Ajouter">+</button>
            </form>
          <?php endif; ?>

        <?php if (!$p['lignes'] && $cle !== 'personnel'): ?>
          <div class="bd-l bd-rien"><span>—</span><b>0 CHF</b></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <div class="bd-tot rouge">
      <span>Total charges</span><b><?= $mt($B['charges']) ?> CHF</b>
    </div>
  </div>

  <?php /* ── PRODUITS ────────────────────────────────────────────────────── */ ?>
  <div class="bd-col">
    <div class="bd-t">Produits</div>

    <?php if ($ecrit): ?>
      <?php /* La ligne d'ajout des produits: même rang, mêmes classes et même
           bouton que celles des charges. Elle est en tête de la colonne et non
           au pied, parce qu'un produit s'ajoute plus souvent qu'il ne se
           relit. */ ?>
      <form method="post" action="<?= e($lien('budget')) ?>" class="bd-l bd-aj">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="pf" value="liste_ajouter">
        <input type="hidden" name="ou" value="budget">
        <input type="hidden" name="l[sens]" value="recette">
        <input type="text" name="l[libelle]" placeholder="Partenaire" required class="bd-lib">
        <select name="l[nature]" class="bd-et">
          <?php foreach (ProdFiche::BUDGET_PRODUITS as $k => $v): ?>
            <option value="<?= e($k) ?>"><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="l[montant]" placeholder="Montant" inputmode="decimal" class="bd-mt">
        <select name="l[devise]" class="bd-dv"><option>CHF</option><option>EUR</option></select>
        <button type="submit" class="bd-plus" title="Ajouter">+</button>
      </form>
    <?php endif; ?>

    <?php foreach ($B['produits'] as $l): $lid = (string)($l['id'] ?? ''); $fb = 'bdP-' . $lid; ?>
      <?php if ($ecrit): ?>
        <div class="bd-l bd-ed">
          <form method="post" action="<?= e($lien('budget')) ?>" id="<?= e($fb) ?>" class="bd-f">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="pf" value="liste_modifier">
            <input type="hidden" name="ou" value="budget">
            <input type="hidden" name="ligne" value="<?= e($lid) ?>">
          </form>
          <input type="text" name="l[libelle]" form="<?= e($fb) ?>" class="bd-lib"
                 value="<?= e((string)($l['libelle'] ?? '')) ?>" placeholder="Partenaire">
          <select name="l[nature]" form="<?= e($fb) ?>" class="bd-et">
            <?php foreach (ProdFiche::BUDGET_PRODUITS as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= (string)($l['nature'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="l[montant]" form="<?= e($fb) ?>" class="bd-mt"
                 value="<?= e((string)($l['montant'] ?? '')) ?>" inputmode="decimal">
          <select name="l[devise]" form="<?= e($fb) ?>" class="bd-dv">
            <?php foreach (['CHF','EUR'] as $dv): ?>
              <option <?= (string)($l['devise'] ?? 'CHF') === $dv ? 'selected' : '' ?>><?= $dv ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ((string)($l['devise'] ?? 'CHF') === 'EUR'): ?>
            <span class="bd-cv" title="Taux BCE du <?= e((string)($l['taux_jour'] ?? '?')) ?>">= <?= $mt((float)$l['_m']) ?> CHF</span>
          <?php endif; ?>
          <button type="submit" form="<?= e($fb) ?>" class="bd-ok" title="Enregistrer">✓</button>
          <form method="post" action="<?= e($lien('budget')) ?>" class="bd-x"
                onsubmit="return confirm('Retirer ce produit ?')">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="pf" value="liste_retirer">
            <input type="hidden" name="ou" value="budget">
            <input type="hidden" name="ligne" value="<?= e($lid) ?>">
            <button type="submit" class="x" title="Retirer">×</button>
          </form>
        </div>
      <?php else: ?>
        <div class="bd-l">
          <span><?= e((string)($l['libelle'] ?? '')) ?: '—' ?>
            <span class="bd-nat"><?= e(ProdFiche::BUDGET_PRODUITS[(string)($l['nature'] ?? '')]
                                    ?? ProdFiche::BUDGET_RECETTE[(string)($l['nature'] ?? '')] ?? '') ?></span></span>
          <b><?= $mt((float)$l['_m']) ?> CHF</b>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <div class="bd-tot"><span>Total produits</span><b><?= $mt($B['recettes']) ?> CHF</b></div>

    <?php /* LE SOLDE EST MONTRÉ MÊME NÉGATIF, ET SURTOUT À CE MOMENT-LÀ. Un
         budget qui n'affiche que le total des charges laisse croire que le
         financement suit. */ ?>
    <div class="bd-solde <?= $B['solde'] < 0 ? 'moins' : 'plus' ?>">
      <span>Solde du projet</span><b><?= $mt($B['solde']) ?> CHF</b>
    </div>
    <?php if ($B['solde'] < 0): ?>
      <p class="bd-vide">Il manque <?= $mt(abs($B['solde'])) ?> CHF pour équilibrer.</p>
    <?php endif; ?>
  </div>
</div>

<?php /* LE FORMULAIRE D'AJOUT GLOBAL EST PARTI D'ICI.  [Anna, 22.08.2026]
     « colocar o botão de mais dentro do budget ao lado do montant, não embaixo ».

     Il vivait tout en bas, avec un menu pour choisir le poste. Deux gestes de
     trop: descendre jusqu'à lui, puis redire dans quel poste on écrit — alors
     qu'on venait de regarder ce poste-là. Chaque poste porte désormais sa propre
     ligne d'ajout, juste sous ses lignes, et le poste n'a plus à se choisir
     puisqu'il est celui sous lequel on écrit. */ ?>
<?php if ($ecrit): ?>
  <p class="aide">Le poste « Frais de personnel » se remplit depuis l'onglet Rémunération: une
     ligne de salaire ajoutée ici ferait un double compte avec la somme automatique.</p>
<?php endif; ?>

<style>
.bd-tete{display:flex;align-items:center;gap:16px;margin:0 0 16px}
.bd-tete h3{margin:0;font-size:16px}
/* ── UNE LIGNE DE BUDGET: UN SEUL RANG.  [Anna, 22.08.2026] ────────────────
   « os botões de + têm que estar na mesma linha, e está tudo deformado ».

   `flex-wrap:nowrap` ET DES CHAMPS QUI ONT LE DROIT DE MAIGRIR: chaque `min-width:0`
   permet au champ de rétrécir au lieu de pousser le bouton à la ligne suivante.
   C'est le contraire de la version d'avant, qui laissait tout passer à la ligne
   et compensait au jugé avec une marge négative — d'où le désordre.

   `display:contents` SUR LE FORMULAIRE VIDE: il ancre les champs sans occuper
   de place dans la rangée. Sans lui, il compterait comme un élément flex et
   ouvrirait un trou. */
.bd-l.bd-ed,form.bd-l.bd-aj{display:flex;flex-wrap:nowrap;align-items:center;gap:6px}
form.bd-f{display:contents}
form.bd-x{display:inline-flex;flex:0 0 auto;margin:0}
.bd-ed input,.bd-ed select,.bd-aj input,.bd-aj select{min-width:0;padding:3px 6px;
  font:inherit;font-size:13px;border:1px solid transparent;border-radius:4px;
  background:transparent;color:var(--encre);box-sizing:border-box}
.bd-aj input,.bd-aj select{border-color:var(--trait);background:var(--papier)}
.bd-ed input:hover,.bd-ed select:hover{border-color:var(--trait)}
.bd-ed input:focus,.bd-ed select:focus,.bd-aj input:focus,.bd-aj select:focus{
  border-color:var(--encre);background:var(--papier);outline:none}
.bd-l input.bd-lib{flex:1 1 80px}
.bd-l select.bd-et{flex:0 1 128px;font-size:11.5px;color:var(--doux)}
.bd-l input.bd-mt{flex:0 0 74px;text-align:right;font-variant-numeric:tabular-nums}
.bd-l select.bd-dv{flex:0 0 60px;font-size:12px}
.bd-cv{flex:0 0 auto;font-size:11px;color:var(--doux);white-space:nowrap}
.bd-ed button.bd-ok{flex:0 0 auto;background:none;border:0;padding:0 2px;color:var(--doux);
  cursor:pointer;font-size:13px;font-family:inherit;margin:0}
.bd-ed button.bd-ok:hover{color:var(--encre)}
form.bd-aj{margin-top:6px;opacity:.85}
form.bd-aj:hover,form.bd-aj:focus-within{opacity:1}
/* `.bd-aj button.bd-plus` et non `.bd-plus`: la règle générale de la fiche vise
   `button[type=submit]`, qui pèse autant et vient après. Même piège que le
   bouton de la Logistique il y a deux heures. */
.bd-aj button.bd-plus{flex:0 0 auto;padding:2px 10px;font-size:14px;line-height:1.35;font-weight:700;
  border:0;border-radius:5px;background:var(--jaune,#FFD24D);color:var(--encre);cursor:pointer;
  font-family:inherit;margin:0}

.bd{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:0 46px;align-items:start}
@media (max-width:900px){ .bd{grid-template-columns:minmax(0,1fr)} }
.bd-t{font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;
  color:var(--doux);padding-bottom:7px;border-bottom:1px solid var(--encre);margin-bottom:4px}
.bd-poste{margin:14px 0 0}
.bd-p-t{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--doux);
  padding:6px 0 4px;border-bottom:1px solid var(--trait)}
.bd-l{display:flex;align-items:baseline;gap:10px;padding:7px 0;border-bottom:1px solid var(--trait);
  font-size:14px}
.bd-l span{flex:1;min-width:0;overflow-wrap:anywhere}
/* `tabular-nums`: une colonne de montants se lit en comparant les rangs des
   chiffres. Sans cela « 1 200 » et « 980 » ne s'alignent pas. */
.bd-l b{font-variant-numeric:tabular-nums;white-space:nowrap}
.bd-l.bd-rien span,.bd-l.bd-rien b{color:var(--doux)}
.bd-auto b{font-weight:700}
.auto{margin-left:7px;padding:1px 7px;border:1px solid var(--trait);border-radius:9px;
  font-size:10.5px;color:var(--doux);text-transform:uppercase;letter-spacing:.06em}
.bd-nat{margin-left:7px;font-size:11.5px;color:var(--doux)}
.bd-vide{margin:6px 0 0;font-size:12.5px;color:var(--doux)}
.bd-tot{display:flex;align-items:baseline;gap:10px;margin-top:14px;padding-top:10px;
  border-top:2px solid var(--encre);font-size:14px;font-weight:700}
.bd-tot span{flex:1;text-transform:uppercase;letter-spacing:.06em;font-size:12px}
.bd-tot b{font-variant-numeric:tabular-nums}
.bd-tot.rouge b{color:#c8452f}
.bd-solde{display:flex;align-items:baseline;gap:10px;margin-top:14px;padding:13px 15px;
  background:var(--fond2);border-radius:6px;font-weight:700}
.bd-solde span{flex:1;text-transform:uppercase;letter-spacing:.06em;font-size:12px;color:var(--doux)}
.bd-solde b{font-size:16px;font-variant-numeric:tabular-nums}
.bd-solde.plus b{color:#2f7d4f} .bd-solde.moins b{color:#c8452f}
/* Les règles des anciens formulaires d'ajout — `.bd-add` et `.bd-add-p` — sont
   parties avec eux le 22.08.2026. Les deux lignes d'ajout portent maintenant
   `.bd-l.bd-aj`, comme les lignes qu'elles complètent, et c'est ce qui les met
   au même rang. */
.bd-l .inline{display:inline}
.bd-l .x{border:0;background:transparent;color:var(--doux);cursor:pointer;font-size:15px;padding:0 2px}
.bd-l .x:hover{color:#c8452f}
</style>

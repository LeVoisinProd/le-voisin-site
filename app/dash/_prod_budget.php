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

$B  = ProdFiche::budgetParPoste($d);
$mt = static fn(float $v): string => number_format($v, 0, ',', ' ');
?>

<div class="bd-tete">
  <h3>Budget de production</h3>
  <?php /* Le bouton d'impression à droite, comme partout ailleurs depuis le
       16.08: ce n'est pas le geste principal de l'écran. */ ?>
  <a class="bt-pdf" href="<?= e($lien('budget')) ?>&amp;imprimer=1" target="_blank" rel="noopener"
     title="Ouvre une page nue. Dans la fenêtre qui s'ouvre: Imprimer, puis « Enregistrer au format PDF »">PDF — Budget</a>
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

        <?php foreach ($p['lignes'] as $l): ?>
          <div class="bd-l">
            <span><?= e((string)($l['libelle'] ?? '')) ?: '—' ?></span>
            <b><?= $mt((float)$l['_m']) ?> CHF</b>
            <?php if ($ecrit): ?>
              <form method="post" action="<?= e($lien('budget')) ?>" class="inline"
                    onsubmit="return confirm('Retirer cette ligne du budget ?')">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="pf" value="liste_retirer">
                <input type="hidden" name="ou" value="budget">
                <input type="hidden" name="id" value="<?= e((string)($l['id'] ?? '')) ?>">
                <button type="submit" class="x" title="Retirer">×</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

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
      <form method="post" action="<?= e($lien('budget')) ?>" class="bd-add-p">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="pf" value="liste_ajouter">
        <input type="hidden" name="ou" value="budget">
        <input type="hidden" name="l[sens]" value="recette">
        <input type="text" name="l[libelle]" placeholder="Partenaire (nom)" required>
        <select name="l[nature]">
          <?php foreach (ProdFiche::BUDGET_PRODUITS as $k => $v): ?>
            <option value="<?= $k ?>"><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="l[montant]" placeholder="Montant" size="9" inputmode="decimal">
        <button type="submit" title="Ajouter">+</button>
      </form>
    <?php endif; ?>

    <?php foreach ($B['produits'] as $l): ?>
      <div class="bd-l">
        <span><?= e((string)($l['libelle'] ?? '')) ?: '—' ?>
          <span class="bd-nat"><?= e(ProdFiche::BUDGET_PRODUITS[(string)($l['nature'] ?? '')]
                                  ?? ProdFiche::BUDGET_RECETTE[(string)($l['nature'] ?? '')] ?? '') ?></span></span>
        <b><?= $mt((float)$l['_m']) ?> CHF</b>
        <?php if ($ecrit): ?>
          <form method="post" action="<?= e($lien('budget')) ?>" class="inline"
                onsubmit="return confirm('Retirer ce produit ?')">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="pf" value="liste_retirer">
            <input type="hidden" name="ou" value="budget">
            <input type="hidden" name="id" value="<?= e((string)($l['id'] ?? '')) ?>">
            <button type="submit" class="x" title="Retirer">×</button>
          </form>
        <?php endif; ?>
      </div>
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

<?php if ($ecrit): ?>
<form method="post" action="<?= e($lien('budget')) ?>" class="bd-add">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="pf" value="liste_ajouter">
  <input type="hidden" name="ou" value="budget">
  <input type="hidden" name="l[sens]" value="depense">
  <div class="bd-add-t">Ajouter une ligne de charge</div>
  <select name="l[poste]">
    <?php foreach (ProdFiche::BUDGET_POSTES as $k => $v): ?>
      <option value="<?= $k ?>"><?= e($v) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="text" name="l[libelle]" placeholder="Libellé — location décor, affiches…" required>
  <input type="text" name="l[montant]" placeholder="Montant" size="10" inputmode="decimal">
  <button type="submit">+ Ajouter</button>
</form>
<p class="aide">Le poste « Frais de personnel » se remplit depuis l'onglet Rémunération: une
   ligne de salaire ajoutée ici ferait un double compte avec la somme automatique.</p>
<?php endif; ?>

<style>
.bd-tete{display:flex;align-items:center;gap:16px;margin:0 0 16px}
.bd-tete h3{margin:0;font-size:16px}
.bd-tete .bt-pdf{margin-left:auto;display:inline-flex;align-items:center;gap:7px;padding:8px 15px;
  border:1px solid var(--encre);border-radius:5px;background:var(--encre);color:var(--papier);
  text-decoration:none;font-size:13.5px;font-weight:600;white-space:nowrap}
.bd-tete .bt-pdf:hover{opacity:.86}
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
.bd-add-p{display:flex;gap:6px;margin:10px 0 4px;flex-wrap:wrap}
.bd-add-p input[type=text]:first-of-type{flex:1;min-width:120px}
.bd-add{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:26px 0 8px;
  padding:13px 15px;background:var(--fond2);border-radius:6px}
.bd-add-t{width:100%;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--doux)}
.bd-add input[type=text]{flex:1;min-width:160px}
.bd-add input,.bd-add select,.bd-add-p input,.bd-add-p select{padding:7px 9px;font:inherit;
  font-size:13.5px;border:1px solid var(--trait);border-radius:5px;background:var(--papier);
  color:var(--encre)}
.bd-add button,.bd-add-p button{padding:7px 14px;font:inherit;font-size:13.5px;font-weight:600;
  border:1px solid var(--encre);border-radius:5px;background:var(--encre);color:var(--papier);
  cursor:pointer}
.bd-l .inline{display:inline}
.bd-l .x{border:0;background:transparent;color:var(--doux);cursor:pointer;font-size:15px;padding:0 2px}
.bd-l .x:hover{color:#c8452f}
</style>

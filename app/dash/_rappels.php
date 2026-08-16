<?php
/**
 * L'agenda des rappels. [16.08.2026]
 *
 * Anna: « separar agenda projets et agenda rappels (é o to do do voisin) ».
 * Le Calendrier montre les DATES — ce qui se joue, où, quand. Cet écran montre
 * ce qu'il faut FAIRE. Ce sont deux lectures différentes: on ouvre l'un pour
 * savoir où l'on sera en mars, l'autre pour savoir quoi faire ce matin.
 *
 * CINQ TRANCHES ET NON UN CALENDRIER. En retard, aujourd'hui, demain, cette
 * semaine, après. Un mois affiché en grille demande de chercher la date du jour
 * avant de pouvoir lire quoi que ce soit; une liste qui commence par le retard
 * se lit dans l'ordre où l'on agit.
 *
 * CE QUI VIENT D'AILLEURS NE SE COCHE PAS ICI. Une obligation administrative se
 * coche dans Administration, un encaissement dans la date. Le lien y mène en un
 * clic. Cocher ici ferait deux endroits pour le même geste, et le jour où ils
 * ne s'accordent plus, plus personne ne sait lequel dit vrai.
 */
declare(strict_types=1);
/** @var bool $rEcrit */

$fenetre = (int)($_GET['j'] ?? 30);
if (!in_array($fenetre, [7, 30, 90, 365], true)) $fenetre = 30;

$tranches = Rappels::parTranche($fenetre);
$total    = array_sum(array_map('count', $tranches));

$TITRES = ['retard' => 'En retard', 'aujourdhui' => "Aujourd'hui", 'demain' => 'Demain',
           'semaine' => 'Cette semaine', 'apres' => 'Plus tard'];

/** D'où vient la ligne, dit en un mot. */
$SOURCES = ['rappel' => 'rappel', 'administration' => 'administration', 'fonds' => 'subvention',
            'encaissement' => 'encaissement', 'pipeline' => 'pipeline'];
?>

<p class="ag-bascule"><a href="/dashboard.php?e=calendrier">Les dates</a>
  <strong>Les rappels</strong></p>
<style>
.ag-bascule{display:flex;gap:16px;align-items:center;margin:0 0 12px;font-size:13.5px}
.ag-bascule a{color:var(--doux);text-decoration:none}
.ag-bascule a:hover{color:var(--encre)}
</style>

<form class="filtres" method="get" action="/dashboard.php">
  <input type="hidden" name="e" value="calendrier">
  <input type="hidden" name="v" value="rappels">
  <select name="j" onchange="this.form.submit()">
    <?php foreach ([7 => 'les 7 prochains jours', 30 => 'les 30 prochains jours',
                    90 => 'les 3 prochains mois', 365 => "l'année"] as $k => $lib): ?>
      <option value="<?= $k ?>" <?= $fenetre === $k ? 'selected' : '' ?>><?= e($lib) ?></option>
    <?php endforeach; ?>
  </select>
  <noscript><button type="submit">Afficher</button></noscript>
</form>

<?php if ($rEcrit): ?>
<details class="rp-neuf" <?= $total === 0 ? 'open' : '' ?>>
  <summary>Écrire un rappel</summary>
  <form method="post" action="/dashboard.php?e=calendrier&amp;v=rappels">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="rp" value="creer">
    <div class="gr">
      <label>Quand
        <input type="datetime-local" name="quand" required
               value="<?= e(date('Y-m-d\TH:i', strtotime('tomorrow 09:00'))) ?>"></label>
      <label>Pour qui <input type="text" name="pour_qui" placeholder="Anna, Mirta, Alessandra…"></label>
    </div>
    <label class="pl">Quoi
      <input type="text" name="texte" required maxlength="500"
             placeholder="Rappeler le Théâtre de Saint-Quentin pour la saison 27"></label>
    <label class="pl">Contexte
      <textarea name="note" rows="2"
        placeholder="Ce qu'on voudra relire au moment où le rappel tombe"></textarea></label>
    <?php /* Le rattachement est facultatif: sans cela on inventerait un lien
         pour pouvoir écrire « appeler la banque ». */ ?>
    <label class="pl">Contact lié <span class="opt">facultatif</span>
      <input type="text" name="contact_q" list="rp-contacts" placeholder="Chercher un contact par son nom">
      <datalist id="rp-contacts">
        <?php foreach (DB::all("SELECT id, nom, prenom, nom_famille, structure FROM contact
                                 WHERE supprime_le IS NULL AND (prenom <> '' OR nom_famille <> '')
                                 ORDER BY nom_famille, prenom LIMIT 400") as $c):
          $n = trim(((string)$c['prenom']) . ' ' . ((string)$c['nom_famille']));
          if ($n === '') continue; ?>
          <option value="<?= e($n . ($c['structure'] ? ' — ' . $c['structure'] : '') . ' #' . $c['id']) ?>">
        <?php endforeach; ?>
      </datalist>
    </label>
    <div class="act"><button type="submit">Ajouter</button></div>
  </form>
</details>
<?php endif; ?>

<?php if ($total === 0): ?>
  <p class="vide">Rien dans cette fenêtre. Ni retard, ni échéance, ni rappel écrit.</p>
<?php endif; ?>

<?php foreach ($TITRES as $cle => $lib): $liste = $tranches[$cle]; if (!$liste) continue; ?>
  <section class="rp-bloc <?= $cle === 'retard' ? 'rouge' : '' ?>">
    <h3><?= e($lib) ?> <span class="n"><?= count($liste) ?></span></h3>
    <ul class="rp">
      <?php foreach ($liste as $x): ?>
        <li class="rp-l s-<?= e($x['source']) ?>">
          <div class="rp-q">
            <?= e(date('d.m', strtotime($x['quand']))) ?>
            <?php if (substr($x['quand'], 11, 5) !== '00:00'): ?>
              <span class="h"><?= e(substr($x['quand'], 11, 5)) ?></span>
            <?php endif; ?>
          </div>
          <div class="rp-c">
            <div class="rp-t"><?= e($x['texte']) ?></div>
            <?php if ($x['sous'] !== ''): ?><div class="rp-s"><?= e($x['sous']) ?></div><?php endif; ?>
            <?php if (($x['note'] ?? '') !== ''): ?><div class="rp-n"><?= nl2br(e($x['note'])) ?></div><?php endif; ?>
            <div class="rp-m">
              <span class="rp-src"><?= e($SOURCES[$x['source']] ?? $x['source']) ?></span>
              <?php if ($x['lien'] !== ''): ?>
                <a href="<?= e($x['lien']) ?>">ouvrir</a>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($rEcrit && $x['cochable']): ?>
            <div class="rp-a">
              <form method="post" action="/dashboard.php?e=calendrier&amp;v=rappels&amp;j=<?= $fenetre ?>">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="rp" value="fait">
                <input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
                <button type="submit" title="Marquer comme fait">fait</button>
              </form>
              <?php /* Reporter plutôt que cocher: un rappel qu'on coche pour
                   s'en débarrasser disparaît, et le geste qu'il portait avec
                   lui. « +7 jours » est le bouton honnête. */ ?>
              <form method="post" action="/dashboard.php?e=calendrier&amp;v=rappels&amp;j=<?= $fenetre ?>">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="rp" value="reporter">
                <input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
                <input type="hidden" name="jours" value="7">
                <button type="submit" class="doux" title="Repousser d'une semaine">+7 j</button>
              </form>
            </div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endforeach; ?>

<?php /* CE QUI EST CACHÉ SE DIT. Une liste qui tronque en silence se lit comme
     une liste complète, et c'est exactement ainsi qu'une échéance se perd. */ ?>
<?php if (Rappels::$ecartes): ?>
  <p class="rp-cache">Ne sont pas affichées:
    <?php $bouts = [];
      foreach (Rappels::$ecartes as $src => $n)
        $bouts[] = $n . ' ' . ($SOURCES[$src] ?? $src) . ($n > 1 ? 's' : '');
      echo e(implode(', ', $bouts)); ?>
    dont l'échéance est passée de plus de trois mois. Ce ne sont plus des
    échéances mais des lignes dont le statut n'a jamais été fermé — elles se
    règlent dans leur écran, pas ici.</p>
<?php endif; ?>

<p class="rp-pied">Cet agenda ne recopie rien. Les obligations administratives, les délais de
   subvention, les encaissements et les échéances du pipeline sont lus là où ils vivent —
   les cocher se fait dans leur écran, et ils disparaissent d'ici tout seuls.</p>

<style>
.rp-neuf{margin:0 0 20px;border:1px solid var(--trait);border-radius:6px;background:var(--fond2)}
.rp-neuf>summary{padding:10px 14px;cursor:pointer;font-weight:600;font-size:13.5px}
.rp-neuf[open]>summary{border-bottom:1px solid var(--trait)}
.rp-neuf form{padding:14px}
.rp-neuf .gr{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:11px}
.rp-neuf label{display:flex;flex-direction:column;gap:4px;font-size:11.5px;font-weight:700;
  text-transform:uppercase;letter-spacing:.08em;color:var(--doux)}
.rp-neuf label.pl{display:block;margin-bottom:11px}
.rp-neuf .opt{font-weight:400;text-transform:none;letter-spacing:0;opacity:.7}
.rp-neuf input,.rp-neuf textarea{padding:7px 9px;font:inherit;font-size:14px;font-weight:400;
  text-transform:none;letter-spacing:0;border:1px solid var(--trait);border-radius:5px;
  background:var(--papier);color:var(--encre);width:100%;box-sizing:border-box}
.rp-neuf .gr input{width:auto}
.rp-bloc{margin:0 0 22px}
.rp-bloc h3{font-size:12px;text-transform:uppercase;letter-spacing:.09em;color:var(--doux);
  margin:0 0 8px}
.rp-bloc.rouge h3{color:#c8452f}
.rp-bloc h3 .n{margin-left:7px;padding:1px 8px;border-radius:9px;background:var(--fond2);
  font-size:11px;letter-spacing:0}
.rp{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:1px}
.rp-l{display:flex;gap:14px;align-items:flex-start;padding:9px 11px;background:var(--fond2)}
.rp-bloc.rouge .rp-l{border-left:3px solid #c8452f}
/* La date en tête et en chiffres alignés: on balaie la colonne de gauche pour
   trouver « quand », pas le texte. */
.rp-q{flex:0 0 62px;font-size:13px;font-variant-numeric:tabular-nums;color:var(--doux);
  padding-top:1px}
.rp-q .h{display:block;font-size:11.5px}
.rp-c{flex:1;min-width:0}
.rp-t{font-size:14px;line-height:1.35;overflow-wrap:anywhere}
.rp-s{font-size:12.5px;color:var(--doux);margin-top:1px;overflow-wrap:anywhere}
.rp-n{font-size:12.5px;margin-top:4px;white-space:pre-wrap;color:var(--doux)}
.rp-m{margin-top:5px;display:flex;gap:12px;align-items:center;font-size:11.5px}
.rp-src{padding:1px 8px;border:1px solid var(--trait);border-radius:9px;color:var(--doux)}
.rp-l.s-rappel .rp-src{border-color:var(--encre);color:var(--encre)}
.rp-m a{color:var(--doux)}
.rp-a{display:flex;gap:6px;flex:none}
.rp-a button{padding:5px 11px;font:inherit;font-size:12px;font-weight:600;cursor:pointer;
  border:1px solid var(--encre);border-radius:5px;background:var(--encre);color:var(--papier)}
.rp-a button.doux{background:transparent;color:var(--doux);border-color:var(--trait)}
.rp-cache{margin:22px 0 0;padding:9px 13px;background:var(--fond2);font-size:12.5px;
  color:var(--doux);max-width:88ch}
.rp-pied{margin:14px 0 0;font-size:12.5px;color:var(--doux);max-width:88ch}
@media (max-width:640px){ .rp-l{flex-wrap:wrap} .rp-a{width:100%} }
</style>

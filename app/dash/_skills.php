<?php
/**
 * Les skills du Claude Code OS, sur le tableau de bord. [16.08.2026]
 *
 * Déplacé depuis Marketing à la demande d'Anna: « isso deveria estar no tableau
 * de bord ». Elles n'ont rien de marketing — `/devis`, `/fechar`, `/organizar`,
 * `/vigiar` servent toute la maison — et sur le tableau de bord elles sont sous
 * les yeux au moment où l'on se demande par quoi commencer.
 */
declare(strict_types=1);
?>

<?php /* ══ LES SKILLS DU CLAUDE CODE OS ══════════════════════════════════
     [16.08.2026]

     ELLES NE SE LANCENT PAS D'ICI, et c'est écrit à l'écran plutôt que
     découvert au premier clic qui ne fait rien. Elles tournent dans le Claude
     Code, sur le Mac, avec le dépôt de travail et le Drive montés. Un site
     public qui déclencherait des commandes sur une machine personnelle est une
     décision qui se prend à froid, pas en passant.

     CE QUE CET ÉCRAN FAIT: dire qu'elles existent. Treize skills, et personne
     d'autre qu'Anna ne sait lesquelles ni ce qu'elles font — une skill qu'on
     ignore n'est pas un outil, c'est du code mort.

     Ce que chacune LIT est affiché, et c'est l'information qui manque le plus:
     quand une skill rend un mauvais résultat, la cause est presque toujours un
     fichier de contexte faux, pas la skill. */
$skills = DB::all('SELECT * FROM skill ORDER BY famille DESC, ordre');
$sorties = DB::all('SELECT * FROM skill_sortie ORDER BY fait_le DESC LIMIT 30');
$parFam = [];
foreach ($skills as $sk) $parFam[$sk['famille']][] = $sk;
?>

<h3 class="sect-sk">Les skills du Claude Code OS</h3>
<div class="avis-sk">Elles ne se lancent pas depuis cette page: elles tournent dans le
  Claude&nbsp;Code, sur le Mac, avec le dépôt de travail et le Drive montés. Ici on voit
  <strong>ce qui existe</strong> et <strong>ce que chacune lit</strong> — quand une skill
  rend un mauvais résultat, la cause est presque toujours un fichier de contexte faux et
  non la skill.</div>

<?php foreach (['metier'=>'Métier', 'systeme'=>'Système'] as $f => $lib):
      if (empty($parFam[$f])) continue; ?>
  <div class="fam-t"><?= e($lib) ?></div>
  <div class="sk-g">
    <?php foreach ($parFam[$f] as $sk): ?>
      <div class="sk">
        <div class="sk-n">/<?= e((string)$sk['nom']) ?></div>
        <div class="sk-h"><?= e((string)$sk['titre']) ?></div>
        <p class="sk-r"><?= e((string)$sk['resume']) ?></p>
        <?php if ($sk['lit']): ?><p class="sk-io"><span>lit</span> <?= e((string)$sk['lit']) ?></p><?php endif; ?>
        <?php if ($sk['ecrit'] && $sk['ecrit'] !== '—'): ?><p class="sk-io"><span>écrit</span> <?= e((string)$sk['ecrit']) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<h3 class="sect-sk">Ce qu'elles ont produit</h3>
<?php if ($sorties): ?>
  <div class="tw"><table>
    <thead><tr><th>Quand</th><th>Skill</th><th>Ce que c'est</th><th>Où</th></tr></thead>
    <tbody>
    <?php foreach ($sorties as $o): ?>
      <tr>
        <td class="sec"><?= e(date('d.m.Y', strtotime((string)$o['fait_le']))) ?></td>
        <td><span class="sk-p">/<?= e((string)$o['skill_nom']) ?></span></td>
        <td><?= e((string)$o['titre']) ?>
          <?php if ($o['resume']): ?><br><span class="n"><?= e((string)$o['resume']) ?></span><?php endif; ?></td>
        <td class="sec"><?= e((string)($o['ou'] ?? '')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <p class="vide-sk">Rien d'enregistré. Le registre est prêt, les skills n'y écrivent pas
     encore: c'est le prochain pas, et il se fait du côté du Claude&nbsp;Code — chaque skill
     déclarant ce qu'elle vient de produire. Aujourd'hui ces sorties vivent dans
     <code>dados/</code> du dépôt et dans le Drive, et ce tableau ne les voit pas.</p>
<?php endif; ?>

<style>
.sect-sk{margin:30px 26px 8px;font-size:15px}
.avis-sk{margin:0 26px 18px;padding:11px 15px;background:var(--fond2);
  border-left:3px solid var(--jaune);font-size:13.5px;max-width:92ch}
.fam-t{margin:18px 26px 8px;font-size:11.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.09em;color:var(--doux)}
.sk-g{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
  gap:10px;padding:0 26px}
.sk{border:1px solid var(--trait);border-radius:7px;padding:13px 15px}
.sk-n{font-family:ui-monospace,Menlo,monospace;font-size:13px;font-weight:600;
  color:var(--encre)}
.sk-h{font-size:12.5px;color:var(--doux);margin-bottom:6px}
.sk-r{font-size:13px;margin:0 0 8px;line-height:1.45}
.sk-io{font-size:11.5px;color:var(--doux);margin:2px 0;line-height:1.4}
.sk-io span{display:inline-block;min-width:32px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;font-size:10px}
.sk-p{font-family:ui-monospace,Menlo,monospace;font-size:12.5px}
.vide-sk{margin:0 26px;color:var(--doux);font-size:13.5px;max-width:88ch}
</style>

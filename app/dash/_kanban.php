<?php
/**
 * Le tableau en colonnes, sur le tableau de bord. [16.08.2026]
 *
 * Anna: « queria uma visao em pipilene assim, para eu acompanhar o andamento
 * das coisas, onde eu possa puxar um evento de uma coluna para outra, escrever
 * dentro dizendo o que tem que ser feito, mudar a coluna de posicao ».
 *
 * GLISSER-DÉPOSER SANS AUCUNE BIBLIOTHÈQUE. L'API HTML `draggable` suffit et
 * elle est native depuis quinze ans; ajouter une dépendance pour cela ferait
 * un fichier de plus à installer, à mettre à jour, et à charger — et la
 * politique de sécurité du site interdit déjà les scripts venus d'ailleurs.
 *
 * LE TABLEAU RESTE UTILISABLE SANS JAVASCRIPT. Chaque carte porte un menu de
 * déplacement en `<select>` qui poste normalement, et chaque colonne son
 * formulaire d'ajout. Le glisser est un raccourci, jamais le seul chemin: le
 * jour où un navigateur se comporte mal, on ne perd pas l'écran.
 *
 * CHAQUE GESTE EST ENREGISTRÉ TOUT DE SUITE, sans bouton « enregistrer ». Un
 * tableau qu'on réorganise pendant deux minutes et qu'on oublie de valider est
 * un tableau qui ment à la prochaine ouverture.
 */
declare(strict_types=1);
/** @var bool $kbEcrit */

$colonnes = Kanban::colonnes();
$cartes   = Kanban::cartes();
$total    = array_sum(array_map('count', $cartes));

/** Ce qu'une carte montre en dessous de son titre, selon ce à quoi elle pointe. */
$sousTitre = static function (array $k): string {
    if ($k['contact_id']) {
        $p = trim(((string)$k['c_prenom']) . ' ' . ((string)$k['c_famille']));
        $s = trim((string)$k['c_struct']);
        $l = trim(((string)$k['c_ville']) . ((string)$k['c_pays'] ? ', ' . $k['c_pays'] : ''));
        return trim(implode(' · ', array_filter([$p ?: (string)$k['c_nom'], $s, $l])), ' ·');
    }
    if ($k['booking_id']) {
        return trim(implode(' · ', array_filter([
            (string)$k['b_projet'], (string)$k['b_venue'], (string)$k['b_ville'],
            $k['b_date'] ? date('d.m.Y', strtotime((string)$k['b_date'])) : '',
        ])), ' ·');
    }
    if ($k['offer_id'])   return trim(implode(' · ', array_filter([(string)$k['o_projet'], (string)$k['o_venue']])), ' ·');
    if ($k['project_id']) return (string)$k['p_titre'];
    return '';
};
?>

<section class="kb-zone">
  <div class="kb-tete">
    <h2>Pipeline <span class="n"><?= $total ?></span></h2>
    <p class="kb-aide">Glissez une carte d'une colonne à l'autre. Cliquez sur une carte pour
       écrire ce qu'il faut faire. Tout s'enregistre immédiatement.</p>
  </div>

  <div class="kb" id="kb">
    <?php foreach ($colonnes as $col): $cid = (int)$col['id']; $liste = $cartes[$cid] ?? []; ?>
      <div class="kb-col c-<?= e((string)($col['couleur'] ?: 'neutre')) ?>"
           data-col="<?= $cid ?>" <?= $kbEcrit ? 'draggable="true"' : '' ?>>

        <div class="kb-col-t">
          <span class="kb-poignee" title="Glisser pour déplacer la colonne">⠿</span>
          <strong><?= e((string)$col['titre']) ?></strong>
          <span class="kb-n"><?= count($liste) ?></span>
          <?php if ($kbEcrit): ?>
            <details class="kb-menu"><summary title="Modifier la colonne">⋯</summary>
              <form method="post" action="/dashboard.php?e=accueil">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="kb" value="col_maj">
                <input type="hidden" name="id" value="<?= $cid ?>">
                <input type="text" name="titre" value="<?= e((string)$col['titre']) ?>" maxlength="120">
                <select name="couleur">
                  <?php foreach (Kanban::COULEURS as $k => $lib): ?>
                    <option value="<?= $k ?>" <?= ($col['couleur'] ?: 'neutre') === $k ? 'selected' : '' ?>><?= e($lib) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit">Renommer</button>
              </form>
              <form method="post" action="/dashboard.php?e=accueil"
                    onsubmit="return confirm('Archiver cette colonne et ses <?= count($liste) ?> carte(s) ? Rien n\'est détruit.')">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="kb" value="col_archiver">
                <input type="hidden" name="id" value="<?= $cid ?>">
                <button type="submit" class="sup">Archiver la colonne</button>
              </form>
            </details>
          <?php endif; ?>
        </div>

        <div class="kb-cartes" data-zone="<?= $cid ?>">
          <?php foreach ($liste as $k): $kid = (int)$k['id']; $st = $sousTitre($k);
            $tard = $k['echeance'] && $k['echeance'] < date('Y-m-d'); ?>
            <article class="kb-carte<?= $tard ? ' tard' : '' ?>" data-carte="<?= $kid ?>"
                     <?= $kbEcrit ? 'draggable="true"' : '' ?>>
              <?php if ($k['contact_id'] && trim((string)$k['c_photo']) !== ''): ?>
                <img class="kb-ph" src="<?= e((string)$k['c_photo']) ?>" alt="">
              <?php endif; ?>
              <div class="kb-corps">
                <?php if (trim((string)$k['note']) !== ''): ?>
                  <p class="kb-note"><?= nl2br(e((string)$k['note'])) ?></p>
                <?php endif; ?>
                <div class="kb-titre"><?= e((string)$k['titre']) ?></div>
                <?php if ($st !== ''): ?><div class="kb-st"><?= e($st) ?></div><?php endif; ?>
                <?php if ($k['echeance']): ?>
                  <div class="kb-ech"><?= e(date('d.m.Y', strtotime((string)$k['echeance']))) ?><?=
                    $tard ? ' — dépassée' : '' ?></div>
                <?php endif; ?>
                <?php if ($k['contact_id']): ?>
                  <a class="kb-lien" href="/dashboard.php?e=contacts&amp;c=<?= (int)$k['contact_id'] ?>">voir la fiche</a>
                <?php elseif ($k['booking_id']): ?>
                  <a class="kb-lien" href="/dashboard.php?e=bookings&amp;b=<?= (int)$k['booking_id'] ?>">voir la date</a>
                <?php elseif ($k['project_id']): ?>
                  <a class="kb-lien" href="/dashboard.php?e=projets&amp;p=<?= (int)$k['project_id'] ?>">voir le spectacle</a>
                <?php endif; ?>
              </div>

              <?php if ($kbEcrit): ?>
                <details class="kb-ed"><summary title="Écrire ce qu'il faut faire">écrire</summary>
                  <form method="post" action="/dashboard.php?e=accueil">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="kb" value="carte_maj">
                    <input type="hidden" name="id" value="<?= $kid ?>">
                    <input type="text" name="titre" value="<?= e((string)$k['titre']) ?>" maxlength="190">
                    <textarea name="note" rows="3"
                      placeholder="Ce qu'il faut faire — « rappeler avant vendredi », « en vacances »…"><?= e((string)$k['note']) ?></textarea>
                    <label>Échéance <input type="date" name="echeance" value="<?= e((string)$k['echeance']) ?>"></label>
                    <?php /* Le menu de déplacement double le glisser: sans JavaScript,
                         c'est le seul chemin, et il reste le plus sûr sur écran tactile. */ ?>
                    <label>Colonne
                      <select name="colonne_id">
                        <?php foreach ($colonnes as $c2): ?>
                          <option value="<?= (int)$c2['id'] ?>" <?= (int)$c2['id'] === $cid ? 'selected' : '' ?>><?=
                            e((string)$c2['titre']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <div class="kb-ed-act">
                      <button type="submit">Enregistrer</button>
                      <button type="submit" name="kb" value="carte_archiver" class="sup"
                        onclick="return confirm('Retirer cette carte du tableau ? Elle est archivée, pas détruite.')">Retirer</button>
                    </div>
                  </form>
                </details>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>

        <?php if ($kbEcrit): ?>
          <form class="kb-add" method="post" action="/dashboard.php?e=accueil">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="kb" value="carte_creer">
            <input type="hidden" name="colonne_id" value="<?= $cid ?>">
            <input type="text" name="titre" placeholder="Ajouter une carte" maxlength="190"
                   list="kb-contacts" autocomplete="off">
            <button type="submit" title="Ajouter">+</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php if ($kbEcrit): ?>
      <div class="kb-col kb-neuve">
        <form method="post" action="/dashboard.php?e=accueil">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="kb" value="col_creer">
          <input type="text" name="titre" placeholder="Nouvelle colonne" maxlength="120">
          <button type="submit">+ colonne</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</section>

<style>
.kb-zone{margin:0 0 26px}
.kb-tete{display:flex;align-items:baseline;gap:14px;flex-wrap:wrap;margin-bottom:10px}
.kb-tete h2{margin:0;font-size:16px}
.kb-tete .n{margin-left:6px;padding:1px 9px;border-radius:10px;background:var(--fond2);
  font-size:12px;color:var(--doux);font-weight:600}
.kb-aide{margin:0;font-size:12.5px;color:var(--doux)}
/* Le tableau défile à l'horizontale et lui seul: le reste de la page ne bouge
   pas. `overscroll-behavior-x` empêche le geste de faire reculer le navigateur
   d'une page quand on arrive au bout — sur trackpad, c'est immédiat. */
.kb{display:flex;gap:12px;align-items:flex-start;overflow-x:auto;overscroll-behavior-x:contain;
  padding:2px 2px 12px}
.kb-col{flex:0 0 272px;background:var(--fond2);border-radius:8px;padding:9px;
  border-top:3px solid var(--trait)}
.kb-col.c-jaune{border-top-color:#FFD24D} .kb-col.c-orange{border-top-color:#FF7142}
.kb-col.c-vert{border-top-color:#3f9d63}  .kb-col.c-rouge{border-top-color:#c8452f}
.kb-col.kb-drag{opacity:.4}
.kb-col-t{display:flex;align-items:center;gap:7px;padding:2px 4px 9px;font-size:13.5px}
.kb-poignee{cursor:grab;color:var(--doux);font-size:13px;letter-spacing:-2px}
.kb-n{margin-left:auto;padding:0 7px;border-radius:9px;background:var(--papier);
  font-size:11.5px;color:var(--doux)}
.kb-menu>summary,.kb-ed>summary{cursor:pointer;color:var(--doux);font-size:11.5px;list-style:none}
.kb-menu>summary::-webkit-details-marker,.kb-ed>summary::-webkit-details-marker{display:none}
.kb-menu form,.kb-ed form{display:flex;flex-direction:column;gap:6px;margin:8px 0 0;
  padding:9px;background:var(--papier);border:1px solid var(--trait);border-radius:6px}
.kb-cartes{display:flex;flex-direction:column;gap:8px;min-height:34px}
/* La zone garde une hauteur même vide, sinon on ne peut rien déposer dans une
   colonne qui vient d'être vidée. */
.kb-cartes.kb-sur{outline:2px dashed var(--encre);outline-offset:3px;border-radius:6px}
.kb-carte{background:var(--papier);border:1px solid var(--trait);border-radius:6px;
  padding:9px 10px;font-size:13px;cursor:grab;display:flex;gap:9px}
.kb-carte.kb-drag{opacity:.4}
.kb-carte.tard{border-left:3px solid #c8452f}
.kb-ph{width:34px;height:34px;object-fit:cover;border-radius:5px;flex:none}
.kb-corps{min-width:0;flex:1}
/* La note passe AVANT le titre et en italique: c'est ce qu'on lit en balayant
   la colonne — « rappeler vite », « en vacances ». Le nom, on le connaît. */
.kb-note{margin:0 0 4px;font-size:12.5px;font-style:italic;color:#8a6d00;white-space:pre-wrap}
.kb-titre{font-weight:600;line-height:1.3;overflow-wrap:anywhere}
.kb-st{margin-top:2px;font-size:11.5px;color:var(--doux);overflow-wrap:anywhere}
.kb-ech{margin-top:4px;font-size:11.5px;color:var(--doux)}
.kb-carte.tard .kb-ech{color:#c8452f;font-weight:600}
.kb-lien{display:inline-block;margin-top:5px;font-size:11.5px;color:var(--doux)}
.kb-ed-act{display:flex;gap:8px}
.kb-ed .sup,.kb-menu .sup{background:transparent;border:1px solid var(--trait);color:#c8452f}
.kb-add{display:flex;gap:5px;margin-top:9px}
.kb-add input{flex:1;min-width:0;padding:6px 8px;font:inherit;font-size:12.5px;
  border:1px solid var(--trait);border-radius:5px;background:var(--papier)}
.kb-add button{padding:0 11px;font-size:15px;line-height:1;border:1px solid var(--trait);
  border-radius:5px;background:var(--papier);cursor:pointer}
.kb-neuve{background:transparent;border:1px dashed var(--trait);border-top-width:1px}
.kb-neuve form{display:flex;flex-direction:column;gap:6px}
.kb-neuve input{padding:6px 8px;font:inherit;font-size:12.5px;border:1px solid var(--trait);
  border-radius:5px;background:var(--papier)}
.kb-menu input,.kb-menu select,.kb-ed input,.kb-ed select,.kb-ed textarea{
  padding:6px 8px;font:inherit;font-size:12.5px;border:1px solid var(--trait);
  border-radius:5px;background:var(--papier);color:var(--encre);width:100%;box-sizing:border-box}
.kb-ed label{font-size:11px;color:var(--doux);text-transform:uppercase;letter-spacing:.06em}
.kb-menu button,.kb-ed button,.kb-neuve button{padding:6px 10px;font:inherit;font-size:12.5px;
  font-weight:600;border:1px solid var(--encre);border-radius:5px;background:var(--encre);
  color:var(--papier);cursor:pointer}
@media (max-width:640px){ .kb-col{flex-basis:84vw} }
</style>

<?php if ($kbEcrit): ?>
<script>
/* Glisser-déposer, sans bibliothèque.
   Chaque dépôt part immédiatement au serveur; si l'appel échoue on recharge,
   pour que l'écran ne montre jamais un ordre que la base ne connaît pas. */
(function () {
  var kb = document.getElementById('kb');
  if (!kb) return;
  var jeton = document.querySelector('input[name="_csrf"]');
  jeton = jeton ? jeton.value : '';
  var pris = null, typePris = null;

  function poster(donnees) {
    donnees.append('_csrf', jeton);
    return fetch('/dashboard.php?e=accueil&kbjson=1',
                 { method: 'POST', body: donnees, headers: { 'X-CSRF': jeton } })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
      .catch(function () { location.reload(); });
  }

  kb.addEventListener('dragstart', function (e) {
    var carte = e.target.closest ? e.target.closest('.kb-carte') : null;
    var col   = e.target.closest ? e.target.closest('.kb-col')   : null;
    /* Une carte est DANS une colonne: sans ce test, saisir une carte
       déplacerait aussi la colonne qui la contient. */
    if (carte) { pris = carte; typePris = 'carte'; }
    else if (col && !col.classList.contains('kb-neuve')) { pris = col; typePris = 'colonne'; }
    else return;
    pris.classList.add('kb-drag');
    e.dataTransfer.effectAllowed = 'move';
    try { e.dataTransfer.setData('text/plain', ''); } catch (x) {}
  });

  kb.addEventListener('dragend', function () {
    if (pris) pris.classList.remove('kb-drag');
    document.querySelectorAll('.kb-sur').forEach(function (z) { z.classList.remove('kb-sur'); });
    pris = null; typePris = null;
  });

  kb.addEventListener('dragover', function (e) {
    if (!pris) return;
    e.preventDefault();
    if (typePris !== 'carte') return;
    var zone = e.target.closest ? e.target.closest('.kb-cartes') : null;
    document.querySelectorAll('.kb-sur').forEach(function (z) { if (z !== zone) z.classList.remove('kb-sur'); });
    if (zone) zone.classList.add('kb-sur');
  });

  kb.addEventListener('drop', function (e) {
    if (!pris) return;
    e.preventDefault();

    if (typePris === 'carte') {
      var zone = e.target.closest ? e.target.closest('.kb-cartes') : null;
      if (!zone) return;
      /* Devant quelle carte on se pose: la première dont le milieu est
         sous le curseur. */
      var avant = 0;
      var voisines = Array.prototype.slice.call(zone.querySelectorAll('.kb-carte'));
      for (var i = 0; i < voisines.length; i++) {
        if (voisines[i] === pris) continue;
        var r = voisines[i].getBoundingClientRect();
        if (e.clientY < r.top + r.height / 2) { avant = voisines[i].dataset.carte; break; }
      }
      if (avant) zone.insertBefore(pris, zone.querySelector('[data-carte="' + avant + '"]'));
      else zone.appendChild(pris);

      var d = new FormData();
      d.append('kb', 'carte_deplacer');
      d.append('id', pris.dataset.carte);
      d.append('colonne_id', zone.dataset.zone);
      d.append('avant_id', avant || 0);
      poster(d).then(function () { majCompteurs(); });

    } else {
      var cible = e.target.closest ? e.target.closest('.kb-col') : null;
      if (!cible || cible === pris || cible.classList.contains('kb-neuve')) return;
      var r2 = cible.getBoundingClientRect();
      if (e.clientX < r2.left + r2.width / 2) kb.insertBefore(pris, cible);
      else kb.insertBefore(pris, cible.nextSibling);

      var d2 = new FormData();
      d2.append('kb', 'col_ordre');
      Array.prototype.forEach.call(kb.querySelectorAll('.kb-col[data-col]'), function (c) {
        d2.append('ids[]', c.dataset.col);
      });
      poster(d2);
    }
  });

  /* Les compteurs se recalculent à l'écran plutôt qu'en rechargeant la page:
     recharger après chaque glissement ferait perdre la position de défilement,
     et l'on réorganise un tableau en enchaînant les gestes. */
  function majCompteurs() {
    Array.prototype.forEach.call(kb.querySelectorAll('.kb-col[data-col]'), function (c) {
      var n = c.querySelectorAll('.kb-carte').length;
      var b = c.querySelector('.kb-n');
      if (b) b.textContent = n;
    });
  }
})();
</script>
<?php endif; ?>

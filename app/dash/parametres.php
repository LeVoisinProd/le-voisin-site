<?php
/**
 * Écran Paramètres et équipe. [16.08.2026]
 *
 * Anna: « Invitez toute votre équipe avec des rôles et permissions. »
 *
 * LE RÔLE EST LE POINT, ET IL VIENT D'UN DÉFAUT MESURÉ. Le dashboard actuel a
 * déjà une grille de permissions par module sur la fiche de chaque personne, et
 * RIEN NE LA LIT: ni showSection(), ni la couche de sauvegarde, ni le serveur.
 * Vérifié le 15.08.2026 en cherchant chaque lecture de `userRole` et de
 * `permissions`. C'est une étiquette. Tout le monde voit et modifie tout.
 *
 * Et c'est pire côté Google: la feuille s'ouvre entière ou pas du tout. Pour
 * qu'une personne voie une date de spectacle, il lui faut l'accès aux salaires,
 * aux IBAN et aux AVS de cent seize fiches. C'est écrit dans tarefas.md depuis
 * le 12.08 et cela attend précisément cette reprise.
 *
 * ICI LE RÔLE EST UNE COLONNE DE `users`, la table qui décide déjà qui entre.
 * Une grille qui vit à côté de l'authentification finit toujours par s'en
 * détacher; celle-ci ne le peut pas.
 *
 * TROIS RÔLES ET PAS DOUZE. Une grille fine que personne ne tient à jour protège
 * moins qu'un rôle grossier qu'on comprend.
 */
declare(strict_types=1);

$ROLES = [
  'direction'  => ['Direction',  'Tout, y compris l\'argent, les salaires et les paramètres.'],
  'production' => ['Production', 'Les dates, les projets, les contacts, l\'administration. Pas les montants ni les salaires.'],
  'lecture'    => ['Lecture',    'Regarder, sans rien modifier.'],
];

$moi = Auth::user();
$monRole = DB::one('SELECT role_dash FROM users WHERE id = ?', [$moi['id'] ?? 0])['role_dash'] ?? 'direction';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    /* Seule la direction change un rôle. Sans cette ligne, l'écran serait un
       formulaire d'auto-promotion. */
    if ($monRole !== 'direction') { http_response_code(403); exit('Interdit'); }

    $uid = (int)($_POST['id'] ?? 0);
    $r   = (string)($_POST['role'] ?? '');
    if ($uid && isset($ROLES[$r])) {
        /* On ne se retire pas à soi-même le dernier rôle de direction: le
           système se fermerait à clef de l'intérieur. */
        $autres = (int)DB::pdo()->query(
            "SELECT COUNT(*) FROM users WHERE role_dash = 'direction'")->fetchColumn();
        if ($uid === (int)($moi['id'] ?? 0) && $r !== 'direction' && $autres <= 1) {
            dash_flash('Impossible: vous êtes la seule direction. Nommez quelqu\'un d\'abord.', 'err');
        } else {
            DB::pdo()->prepare('UPDATE users SET role_dash = ? WHERE id = ?')->execute([$r, $uid]);
            dash_flash('Rôle enregistré.');
        }
    }
    redirect('/dashboard.php?e=parametres');
}

$gens = DB::all('SELECT id, email, name, role_dash, last_login FROM users ORDER BY name, email');

/* Ce que la maison sait d'elle-même, pour que ces nombres ne s'aillent pas
   chercher ailleurs. */
$compte = [
  'organisations' => (int)DB::pdo()->query("SELECT COUNT(*) FROM organisation WHERE supprime_le IS NULL")->fetchColumn(),
  'contacts'      => (int)DB::pdo()->query("SELECT COUNT(*) FROM contact WHERE supprime_le IS NULL")->fetchColumn(),
  'bookings'      => (int)DB::pdo()->query("SELECT COUNT(*) FROM booking WHERE supprime_le IS NULL")->fetchColumn(),
  'projets'       => (int)DB::pdo()->query("SELECT COUNT(*) FROM projects")->fetchColumn(),
];
$mig = DB::all('SELECT fichier, applique_a, duree_ms FROM schema_migration ORDER BY fichier');

dash_haut('parametres', count($gens) . ' personne' . (count($gens) > 1 ? 's' : ''));
?>
<?php dash_flash_html(); ?>
<div class="zone">

<h3 class="sect">L'équipe</h3>
<?php if ($monRole !== 'direction'): ?>
  <p class="sec">Votre rôle est « <?= e($ROLES[$monRole][0]) ?> »: vous voyez cette liste
     et ne pouvez pas la modifier.</p>
<?php endif; ?>
<div class="tw"><table>
  <thead><tr><th>Personne</th><th>Courriel</th><th>Dernière entrée</th><th>Rôle</th></tr></thead>
  <tbody>
  <?php foreach ($gens as $g): ?>
    <tr>
      <td><?= e($g['name'] ?: '(sans nom)') ?><?php
        if ((int)$g['id'] === (int)($moi['id'] ?? 0)): ?> <span class="vous">vous</span><?php endif; ?></td>
      <td class="sec"><?= e($g['email']) ?></td>
      <td class="sec"><?= e($g['last_login'] ? substr((string)$g['last_login'], 0, 10) : 'jamais') ?></td>
      <td>
        <?php if ($monRole === 'direction'): ?>
        <form method="post" action="/dashboard.php?e=parametres" class="inline">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
          <select name="role" onchange="this.form.submit()">
            <?php foreach ($ROLES as $k => [$lib, $ex]): ?>
              <option value="<?= $k ?>"<?= $g['role_dash']===$k?' selected':'' ?>><?= e($lib) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php else: ?><span class="sec"><?= e($ROLES[$g['role_dash']][0] ?? '') ?></span><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<div class="roles">
  <?php foreach ($ROLES as [$lib, $ex]): ?>
    <div><strong><?= e($lib) ?></strong> <?= e($ex) ?></div>
  <?php endforeach; ?>
</div>

<div class="avis alerte">
  <h2>Ce que le rôle ne fait pas encore</h2>
  <p><strong>Il est enregistré et il n'est pas encore appliqué écran par écran.</strong>
     Le dire est plus honnête que de laisser croire le contraire, et c'est exactement
     le défaut du dashboard actuel: une grille de permissions par module y existe sur
     chaque fiche et rien ne la lit.</p>
  <p>La différence est qu'ici le rôle vit dans la table qui décide déjà qui entre, et
     non dans une fiche à côté. Le brancher est une ligne par écran, pas une
     réarchitecture.</p>
</div>

<h3 class="sect">Ce que la base contient</h3>
<div class="chiffres">
  <?php foreach ($compte as $k => $v): ?>
    <div><span class="n"><?= number_format($v, 0, ',', ' ') ?></span><span class="l"><?= e($k) ?></span></div>
  <?php endforeach; ?>
</div>

<h3 class="sect">Les migrations appliquées <span class="n"><?= count($mig) ?></span></h3>
<p class="sec pt">Le schéma de cette base n'existait nulle part avant le 16.08.2026:
   pas de fichier, pas d'installeur, rien dans l'historique. Chaque changement est
   désormais un fichier numéroté, appliqué une fois, et suivi par git.</p>
<div class="tw"><table>
  <thead><tr><th>Fichier</th><th>Appliquée</th><th class="d">Durée</th></tr></thead>
  <tbody>
  <?php foreach ($mig as $m): ?>
    <tr><td><?= e($m['fichier']) ?></td>
        <td class="sec"><?= e(substr((string)$m['applique_a'], 0, 16)) ?></td>
        <td class="d sec"><?= (int)$m['duree_ms'] ?> ms</td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<h3 class="sect">Ailleurs</h3>
<div class="liens">
  <a href="/admin/">Administration du site</a>
  <a href="/admin/settings.php">Réglages du CMS</a>
  <a href="/admin/collaborators.php">Espace collaborateur</a>
  <a href="/catalogue.php" target="_blank" rel="noopener">Catalogue</a>
</div>
</div>

<style>
h3.sect{font-size:12.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--doux);
  margin:30px 0 8px;border-bottom:1px solid var(--trait);padding-bottom:5px}
h3.sect:first-child{margin-top:0}
h3.sect .n{float:right;font-weight:400}
.vous{font-size:10.5px;border:1px solid var(--trait);border-radius:3px;padding:0 5px;color:var(--doux)}
form.inline{display:inline}
table select{font-size:12.5px;padding:3px 7px;border:1px solid var(--trait);
  border-radius:3px;background:var(--papier);color:var(--encre)}
td.d,th.d{text-align:right}
.roles{margin-top:12px;max-width:80ch}
.roles div{font-size:13px;color:var(--doux);padding:3px 0}
.roles strong{color:var(--encre)}
.avis{margin:22px 0;padding:15px 19px;background:var(--fond2);
  border-left:4px solid var(--jaune);max-width:78ch}
.avis.alerte{border-left-color:var(--orange)}
.avis h2{font-size:14px;margin:0 0 8px}
.avis p{margin:0 0 8px;font-size:13.5px;color:var(--doux)}.avis p:last-child{margin:0}
.chiffres{display:flex;gap:34px;flex-wrap:wrap;padding:6px 0}
.chiffres .n{display:block;font-size:22px;font-weight:600}
.chiffres .l{font-size:12px;color:var(--doux)}
.liens{display:flex;gap:18px;flex-wrap:wrap;font-size:13.5px;padding-top:4px}
.pt{margin:0 0 10px;max-width:76ch}
</style>
<?php dash_bas(); ?>

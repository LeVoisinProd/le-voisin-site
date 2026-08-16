<?php
/**
 * Écran Documentation. [16.08.2026]
 *
 * La docuthèque: des liens vers le Drive, rangés par usage.
 *
 * LES FICHIERS NE VIENNENT PAS ICI, ET C'EST VOULU. Le mapa du dépôt de travail
 * le dit depuis le 07.08.2026: un document va au Google Drive, une règle va au
 * dépôt, une donnée va à la base. Copier les fichiers ferait deux endroits où
 * chercher, et le second serait toujours périmé.
 */
declare(strict_types=1);

$RUB = ['guides'=>'Guides et procédures','contrats'=>'Modèles de contrat',
        'proddiff'=>'Production et diffusion','fiches'=>'Fiches techniques',
        'compta'=>'Comptabilité','autre'=>'Autre'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    /* Le rôle décide aussi de l'écriture, et pas seulement de l'accès à
       l'écran: `production` lit les Finances sans les modifier. Le routeur
       ne peut pas le faire à notre place, lui ne voit pas les POST. */
    dash_exige_ecriture('documentation');
    $a = (string)($_POST['action'] ?? '');
    if ($a === 'ajouter') {
        $t = trim((string)($_POST['titre'] ?? ''));
        $u = trim((string)($_POST['url'] ?? ''));
        if ($t !== '' && $u !== '') {
            DB::pdo()->prepare('INSERT INTO document_lien (rubrique,titre,url,description,ordre)
                VALUES (?,?,?,?,(SELECT COALESCE(MAX(o.ordre),0)+10 FROM document_lien o))')
              ->execute([(string)($_POST['rubrique'] ?? 'autre'), $t, $u,
                         trim((string)($_POST['description'] ?? '')) ?: null]);
            dash_flash('Lien ajouté.');
        } else dash_flash('Il faut au moins un titre et une adresse.', 'err');
    }
    if ($a === 'supprimer') {
        DB::pdo()->prepare('UPDATE document_lien SET supprime_le = NOW() WHERE id = ?')
                 ->execute([(int)($_POST['id'] ?? 0)]);
        dash_flash('Lien retiré. Le fichier reste sur le Drive, évidemment.');
    }
    redirect('/dashboard.php?e=documentation');
}

$liens = DB::all('SELECT * FROM document_lien WHERE supprime_le IS NULL ORDER BY rubrique, ordre, id');
$par = [];
foreach ($liens as $l) $par[$l['rubrique']][] = $l;

dash_haut('documentation', count($liens) . ' lien' . (count($liens) > 1 ? 's' : ''));
?>
<?php dash_flash_html(); ?>
<div class="zone">
  <?php if (!$liens): ?>
    <div class="avis">
      <h2>Rien encore</h2>
      <p>Cette page range des liens vers le Drive par usage: les guides, les modèles
         de contrat, les fiches techniques. Les fichiers restent où ils sont, seule
         l'adresse vit ici.</p>
    </div>
  <?php endif; ?>

  <?php foreach ($RUB as $k => $lib): if (empty($par[$k])) continue; ?>
    <h3 class="sect"><?= e($lib) ?> <span class="n"><?= count($par[$k]) ?></span></h3>
    <?php foreach ($par[$k] as $l): ?>
      <div class="lien">
        <a href="<?= e($l['url']) ?>" target="_blank" rel="noopener"><?= e($l['titre']) ?></a>
        <?php if ($l['description']): ?><span class="sec"><?= e($l['description']) ?></span><?php endif; ?>
        <form method="post" action="/dashboard.php?e=documentation" class="inline"
              onsubmit="return confirm('Retirer ce lien de la liste ?')">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="supprimer">
          <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
          <button type="submit" class="x">×</button>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <h3 class="sect">Ajouter</h3>
  <form method="post" action="/dashboard.php?e=documentation" class="ajl">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="ajouter">
    <select name="rubrique"><?php foreach ($RUB as $k=>$v): ?>
      <option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
    <input type="text" name="titre" placeholder="Titre" required>
    <input type="text" name="url" placeholder="https://drive.google.com/..." required>
    <input type="text" name="description" placeholder="À quoi ça sert">
    <button type="submit">ajouter</button>
  </form>
</div>
<style>
h3.sect{font-size:12.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--doux);
  margin:26px 0 6px;border-bottom:1px solid var(--trait);padding-bottom:5px}
h3.sect:first-child{margin-top:0}
h3.sect .n{float:right;font-weight:400}
.lien{display:flex;gap:14px;align-items:baseline;padding:8px 0;
  border-bottom:1px solid var(--trait);max-width:900px}
.lien a{font-size:14px}
.lien .sec{color:var(--doux);font-size:12.5px}
.lien form{margin-left:auto}
button.x{background:none;color:var(--doux);padding:0 6px;font-size:16px;cursor:pointer}
form.inline{display:inline}
form.ajl{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px;max-width:900px}
form.ajl input,form.ajl select{padding:7px 10px;font-size:13.5px;font-family:inherit;
  border:1px solid var(--trait);border-radius:4px;background:var(--papier);color:var(--encre)}
form.ajl input[name=titre],form.ajl input[name=url]{flex:1;min-width:170px}
form.ajl input[name=description]{flex:1;min-width:150px}
.avis{padding:15px 19px;background:var(--fond2);border-left:4px solid var(--jaune);max-width:76ch}
.avis h2{font-size:14.5px;margin:0 0 8px}.avis p{margin:0;font-size:13.5px;color:var(--doux)}
</style>
<?php dash_bas(); ?>

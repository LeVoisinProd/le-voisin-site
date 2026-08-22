<?php
/**
 * Écran Documentation — la Docuthèque. [16.08.2026]
 *
 * Cinq rubriques, celles d'Anna: Guides & Charte, Contrats, Prod & Diff.,
 * Fiches de poste, Docs.
 *
 * LE FICHIER N'EST PAS ICI, C'EST UN LIEN VERS LE DRIVE. Un modèle de contrat
 * se modifie à plusieurs, se commente, garde son historique: c'est ce que le
 * Drive fait bien et qu'une colonne de fichier ferait mal. Ce que cet écran
 * ajoute, c'est de savoir QUELS modèles existent, dans quel état, et de les
 * retrouver sans demander à quelqu'un.
 *
 * LE STATUT « À COMPLÉTER » EST LA COLONNE QUI SERT LE PLUS. Sur les modèles
 * de contrats, huit sur huit le sont: un modèle incomplet qu'on croit prêt
 * part chez un·e artiste avec des blancs dedans.
 */
declare(strict_types=1);

const RUBRIQUES = [
    'guides'   => ['Guides & Charte', 'Guides internes, charte graphique et procédures de Le Voisin.'],
    'contrats' => ['Contrats', 'Modèles de contrats — CDDU, prestation de services, CDI, stage, CH et FR.'],
    'prod'     => ['Prod & Diff.', 'Templates de production, de diffusion et base de dossier projet.'],
    'postes'   => ['Fiches de poste', 'Modèles de fiches de poste par fonction.'],
    'docs'     => ['Docs', 'Le reste.'],
];
const STATUTS_D = ['pret'=>'prêt','a-completer'=>'à compléter','a-faire'=>'à faire','obsolete'=>'obsolète'];

$ecrit = dash_droit('documentation', dash_role()) === 'ecrit';
$rub   = (string)($_GET['r'] ?? 'guides');
if (!isset(RUBRIQUES[$rub])) $rub = 'guides';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    dash_exige_ecriture('documentation');
    $act = (string)($_POST['dt'] ?? '');
    $r   = isset(RUBRIQUES[$_POST['rubrique'] ?? '']) ? (string)$_POST['rubrique'] : $rub;

    if ($act === 'ajouter' && trim((string)($_POST['titre'] ?? '')) !== '') {
        $t = static fn(string $k, int $m = 400): ?string
            => trim((string)($_POST[$k] ?? '')) !== '' ? mb_substr(trim((string)$_POST[$k]), 0, $m) : null;
        DB::insert('docutheque', [
            'rubrique'    => $r,
            'titre'       => mb_substr(trim((string)$_POST['titre']), 0, 190),
            'description' => $t('description'),
            'url'         => $t('url', 600),
            'statut'      => isset(STATUTS_D[$_POST['statut'] ?? '']) ? $_POST['statut'] : 'pret',
            'ordre'       => (int)($_POST['ordre'] ?? 100) ?: 100,
        ]);
        dash_flash('Document ajouté.');

    } elseif ($act === 'statut') {
        /* Un clic fait tourner le statut. Quatre états, un bouton: chercher
           « à compléter » dans une liste déroulante pour un modèle qu'on vient
           de finir coûte plus que le geste ne vaut. */
        $suiv = ['pret'=>'a-completer','a-completer'=>'a-faire','a-faire'=>'obsolete','obsolete'=>'pret'];
        $l = DB::one('SELECT statut FROM docutheque WHERE id = ?', [(int)($_POST['ligne'] ?? 0)]);
        if ($l) DB::update('docutheque', ['statut' => $suiv[$l['statut']] ?? 'pret'],
                           'id = ?', [(int)$_POST['ligne']]);

    } elseif ($act === 'supprimer') {
        DB::pdo()->prepare('UPDATE docutheque SET supprime_le = NOW() WHERE id = ?')
                 ->execute([(int)($_POST['ligne'] ?? 0)]);
        dash_flash('Document retiré. Il reste en base.');
    }
    redirect('/dashboard.php?e=documentation&r=' . $r);
}

$docs = DB::all('SELECT * FROM docutheque WHERE supprime_le IS NULL AND rubrique = ?
                 ORDER BY ordre, titre', [$rub]);
$compte = [];
foreach (DB::all('SELECT rubrique, COUNT(*) n FROM docutheque WHERE supprime_le IS NULL
                  GROUP BY rubrique') as $c) $compte[$c['rubrique']] = (int)$c['n'];

$aCompleter = (int)DB::val("SELECT COUNT(*) FROM docutheque
                            WHERE supprime_le IS NULL AND statut IN ('a-completer','a-faire')");

dash_haut('documentation', $aCompleter > 0
    ? '<strong>' . $aCompleter . '</strong> document' . ($aCompleter > 1 ? 's' : '') . ' à compléter'
    : 'tout est prêt');
dash_flash_html();
?>

<div class="onglets">
  <?php foreach (RUBRIQUES as $k => [$lib, $_]): ?>
    <a href="/dashboard.php?e=documentation&amp;r=<?= $k ?>" class="<?= $rub === $k ? 'ici' : '' ?>"><?= e($lib) ?><?php
      if (!empty($compte[$k])): ?> <span class="n"><?= $compte[$k] ?></span><?php endif; ?></a>
  <?php endforeach; ?>
</div>

<div class="zone">
  <p class="intro"><?= e(RUBRIQUES[$rub][1]) ?></p>

  <?php if ($docs): ?>
    <ul class="ld">
    <?php foreach ($docs as $d): $did = (int)$d['id']; ?>
      <li class="s-<?= e($d['statut']) ?>">
        <div class="ld-t">
          <strong><?= e((string)$d['titre']) ?></strong>
          <?php if ($d['description']): ?><br><span class="n"><?= e((string)$d['description']) ?></span><?php endif; ?>
        </div>
        <div class="ld-a">
          <?php if ($d['url']): ?>
            <a href="<?= e((string)$d['url']) ?>" target="_blank" rel="noopener" class="ouvre">Ouvrir &nearr;</a>
          <?php else: ?>
            <span class="sansl">pas encore de lien</span>
          <?php endif; ?>
          <?php if ($ecrit): ?>
            <form method="post" action="/dashboard.php?e=documentation&amp;r=<?= $rub ?>" class="inline">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="dt" value="statut">
              <input type="hidden" name="rubrique" value="<?= $rub ?>">
              <input type="hidden" name="ligne" value="<?= $did ?>">
              <button type="submit" class="st st-<?= e($d['statut']) ?>"><?= e(STATUTS_D[$d['statut']]) ?></button>
            </form>
            <form method="post" action="/dashboard.php?e=documentation&amp;r=<?= $rub ?>" class="inline"
                  onsubmit="return confirm('Retirer ce document de la liste ?')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="dt" value="supprimer">
              <input type="hidden" name="rubrique" value="<?= $rub ?>">
              <input type="hidden" name="ligne" value="<?= $did ?>">
              <button type="submit" class="x">×</button>
            </form>
          <?php else: ?>
            <span class="st st-<?= e($d['statut']) ?>"><?= e(STATUTS_D[$d['statut']]) ?></span>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="vide">Rien dans cette rubrique.</p>
  <?php endif; ?>

  <?php if ($ecrit): ?>
  <form method="post" action="/dashboard.php?e=documentation&amp;r=<?= $rub ?>" class="ajl">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="dt" value="ajouter">
    <select name="rubrique">
      <?php foreach (RUBRIQUES as $k => [$lib, $_]): ?>
        <option value="<?= $k ?>" <?= $rub === $k ? 'selected' : '' ?>><?= e($lib) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="titre" placeholder="Titre du document" size="26" required>
    <input type="text" name="description" placeholder="À quoi ça sert" size="26">
    <input type="text" name="url" placeholder="https://drive.google.com/…" size="26">
    <select name="statut">
      <?php foreach (STATUTS_D as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?>
    </select>
    <input type="text" name="ordre" value="100" size="4" title="Ordre">
    <button type="submit">ajouter</button>
  </form>
  <p class="aide-d">Le fichier reste dans le Drive: un modèle se modifie à plusieurs et garde
     son historique, ce qu'une colonne de fichier ferait mal. Ce qu'on note ici, c'est qu'il
     existe, où il est, et s'il est prêt. Un clic sur l'étiquette fait tourner le statut.</p>
  <?php endif; ?>
</div>

<style>
.onglets{display:flex;gap:2px;padding:12px 26px 0;border-bottom:1px solid var(--trait);overflow-x:auto}
.onglets a{padding:9px 15px;font-size:13.5px;text-decoration:none;white-space:nowrap;
  color:var(--doux);border-bottom:2px solid transparent}
.onglets a.ici{color:var(--encre);font-weight:600;border-bottom-color:var(--jaune)}
.onglets a .n{font-size:11px;opacity:.7}
.intro{color:var(--doux);font-size:13.5px;margin:0 0 16px}
.ld{list-style:none;margin:0 0 20px;padding:0;display:flex;flex-direction:column;gap:8px}
.ld li{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:12px 16px;
  border:1px solid var(--trait);border-radius:7px}
.ld li.s-a-completer,.ld li.s-a-faire{border-left:3px solid var(--orange)}
.ld li.s-obsolete{opacity:.55}
.ld-t{flex:1;min-width:200px;font-size:14.5px}
.ld-t .n{font-size:12.5px;color:var(--doux)}
.ld-a{display:flex;align-items:center;gap:10px}
.ouvre{font-size:13px;color:#3a6fd0;text-decoration:none}
.ouvre:hover{text-decoration:underline}
.sansl{font-size:12.5px;color:var(--doux)}
.st{font-size:11.5px;padding:3px 10px;border-radius:11px;border:1px solid var(--trait);
  background:none;color:var(--doux);cursor:pointer;font-family:inherit}
button.st:hover{border-color:var(--encre);color:var(--encre)}
.st-pret{border-color:#7bb33a;color:#4d7a1e}
.st-a-completer,.st-a-faire{border-color:#e2653a;color:#c1441a;font-weight:600}
.aide-d{font-size:12.5px;color:var(--doux);margin:10px 0 0;max-width:84ch}
</style>

<?php dash_bas();

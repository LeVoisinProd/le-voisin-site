<?php
/**
 * Écran Projets. [16.08.2026]
 *
 * LE PARTAGE DU TRAVAIL, ET C'EST LA DÉCISION LA PLUS STRUCTURANTE DE L'ÉCRAN.
 *
 * Un spectacle se saisissait à trois endroits: `projects` du CMS, `lv-prods` et
 * `lv-fiches` du dashboard. Anna: « on ne veut pas travailler en double ».
 *
 * Cet écran n'ajoute donc PAS une quatrième fiche. Il lit et écrit `projects`,
 * la table du CMS, et lui ajoute par-dessus la couche `projet_prod` qui porte ce
 * que le CMS n'a pas: phase, responsable, budget, validation.
 *
 *   ce que le CMS porte      titre, textes, images, catégories, ce qui est publié
 *   ce que ce dashboard ajoute  phase, responsable, budget, porteur juridique
 *   ce qui est commun        la même ligne, le même identifiant
 *
 * Le sens de la dépendance qu'Anna décrit se met ainsi en place tout seul: la
 * fiche devient la source et le site la lit, sans qu'aucune donnée ne soit
 * recopiée. L'édition des textes et des images reste pour l'instant dans
 * l'administration du site, et le lien y renvoie: la déplacer ici est un autre
 * chantier, et le faire à moitié rouvrirait la double saisie.
 */
declare(strict_types=1);

$PHASES = ['dev'=>'développement','creation'=>'création','production'=>'production',
           'promo'=>'promotion','tournee'=>'tournée','cloture'=>'clôturé'];

$id = (int)($_GET['p'] ?? 0);

// ═══════════════════════════════════════════════════════════════════════════
// ENREGISTRER la couche production
// ═══════════════════════════════════════════════════════════════════════════

$CHAMPS = ['phase','responsable','valide_par','budget','devise','organisation_id',
           'lieu_creation','notes'];
$err = $saisi = [];

/* LES LIENS DE PRESSKIT. Traités avant le bloc ci-dessous, qui exige un projet
   ouvert ($id > 0): ceux-ci portent un identifiant de spectacle du CMS, pas de
   projet du dashboard, et se postent depuis la liste. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pk'] ?? '') !== '') {
    Auth::requireCsrf();
    dash_exige_ecriture('projets');

    $cms = (int)($_POST['projet_cms'] ?? 0);
    if ($cms > 0) {
        if ((string)$_POST['pk'] === 'ouvrir') {
            Presskit::ouvrir($cms, (string)($_POST['destinataire'] ?? ''));
            dash_flash('Lien ouvert. Il expire dans ' . Presskit::JOURS . ' jours, et tout ancien lien cesse de fonctionner.');
        } elseif ((string)$_POST['pk'] === 'revoquer') {
            Presskit::revoquer($cms);
            dash_flash('Lien révoqué.');
        }
    }
    redirect('/dashboard.php?e=projets');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    Auth::requireCsrf();
    /* Le rôle décide aussi de l'écriture, et pas seulement de l'accès à
       l'écran: `production` lit les Finances sans les modifier. Le routeur
       ne peut pas le faire à notre place, lui ne voit pas les POST. */
    dash_exige_ecriture('projets');
    foreach ($CHAMPS as $c) $saisi[$c] = trim((string)($_POST[$c] ?? ''));

    if (!isset($PHASES[$saisi['phase']])) $saisi['phase'] = 'dev';
    if ($saisi['devise'] === '') $saisi['devise'] = 'CHF';
    if ($saisi['budget'] !== '') {
        $saisi['budget'] = str_replace([',', ' ', "'"], ['.', '', ''], $saisi['budget']);
        if (!is_numeric($saisi['budget'])) $err['budget'] = 'Un montant, sans texte autour.';
    }

    if (!$err) {
        $v = array_map(fn($c) => $saisi[$c] === '' ? null : $saisi[$c], $CHAMPS);
        DB::pdo()->prepare(
            'INSERT INTO projet_prod (project_id,' . implode(',', $CHAMPS) . ')
             VALUES (?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE '
             . implode(',', array_map(fn($c) => "$c=VALUES($c)", $CHAMPS)))
          ->execute([$id, ...$v]);
        dash_flash('Projet enregistré.');
        redirect('/dashboard.php?e=projets&p=' . $id);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// LA FICHE
// ═══════════════════════════════════════════════════════════════════════════

if ($id > 0) {
    $p = DB::one(
        "SELECT pr.*, pp.phase, pp.responsable, pp.valide_par, pp.budget, pp.devise,
                pp.organisation_id, pp.lieu_creation, pp.notes AS notes_prod, pp.raci,
                o.nom AS organisation
           FROM projects pr
           LEFT JOIN projet_prod  pp ON pp.project_id = pr.id
           LEFT JOIN organisation o  ON o.id = pp.organisation_id
          WHERE pr.id = ?", [$id]);
    if (!$p) { dash_haut('projets'); echo '<p class="vide">Ce projet n\'existe pas.</p>'; dash_bas(); return; }

    $st = DB::pdo()->prepare("SELECT * FROM booking WHERE supprime_le IS NULL AND projet = ?
                              ORDER BY date_debut DESC");
    $st->execute([$p['title_fr'] ?: $p['title_en']]);
    $dates = $st->fetchAll();

    $mod = isset($_GET['mod']);
    $v = fn(string $c) => $saisi[$c] ?? ($p[$c] ?? '');
    $titre = $p['title_fr'] ?: $p['title_en'];

    dash_haut('projets', e($PHASES[$p['phase'] ?? 'dev'] ?? '') .
        ($p['year_creation'] ? ' · ' . (int)$p['year_creation'] : ''));
    if ($mod) dash_form_style();
    ?>
    <div class="fil"><a href="/dashboard.php?e=projets">← tous les projets</a>
      <?php if (!$mod): ?>
        <a class="mod" href="/dashboard.php?e=projets&amp;p=<?= $id ?>&amp;mod=1">modifier la production</a>
      <?php endif; ?></div>
    <?php dash_flash_html(); ?>
    <div class="zone">
      <h2 class="gros"><?= e($titre) ?></h2>

      <?php if ($mod): ?>
        <?php if ($err) echo '<div class="flash err">Rien n\'a été enregistré: '
                          . count($err) . ' champ(s) à corriger.</div>'; ?>
        <form class="saisie" method="post" action="/dashboard.php?e=projets&amp;p=<?= $id ?>&amp;mod=1">
          <?= Auth::csrfField() ?>
          <div class="grille">
            <?php
            ch('phase', 'Phase', $v('phase') ?: 'dev', $err, ['type'=>'select','choix'=>$PHASES]);
            ch('responsable', 'Responsable', $v('responsable'), $err, ['aide'=>'Qui le porte au bureau']);
            ch('valide_par', 'Validé par', $v('valide_par'), $err);
            ch('lieu_creation', 'Lieu de création', $v('lieu_creation'), $err);
            ch('budget', 'Budget du projet', $v('budget'), $err,
               ['aide'=>'Le budget du projet artistique, PAS l\'argent qui passe par Le Voisin']);
            ch('devise', 'Devise', $v('devise') ?: 'CHF', $err,
               ['type'=>'select','choix'=>['CHF'=>'CHF','EUR'=>'EUR']]);
            $orgs = ['' => '(aucune)'];
            foreach (DB::all("SELECT id, nom FROM organisation WHERE supprime_le IS NULL
                              ORDER BY genre, nom") as $o) $orgs[$o['id']] = $o['nom'];
            ch('organisation_id', 'Porteur juridique', $v('organisation_id'), $err,
               ['type'=>'select','choix'=>$orgs]);
            ch('notes', 'Notes de production', $v('notes_prod'), $err,
               ['type'=>'textarea','large'=>true]);
            ?>
          </div>
          <div class="actions">
            <button type="submit">Enregistrer</button>
            <a class="sec2" href="/dashboard.php?e=projets&amp;p=<?= $id ?>">annuler</a>
          </div>
        </form>
      <?php else: ?>
        <div class="deux">
          <div>
            <h3 class="sect">Production <span class="n">le dashboard</span></h3>
            <div class="fiche">
            <?php
            $l = function (string $k, $v, string $n = '') {
                if ($v === null || $v === '') return;
                printf('<div class="l"><span class="k">%s</span><span class="v">%s%s</span></div>',
                       e($k), e((string)$v), $n ? '<span class="n">'.e($n).'</span>' : '');
            };
            $l('Phase', $PHASES[$p['phase'] ?? ''] ?? '');
            $l('Responsable', $p['responsable']);
            $l('Validé par', $p['valide_par']);
            $l('Lieu de création', $p['lieu_creation']);
            if ($p['budget'] !== null)
                $l('Budget', number_format((float)$p['budget'], 0, ',', ' ') . ' ' . $p['devise'],
                   'du projet, pas de la maison');
            $l('Porteur juridique', $p['organisation']);
            if (!$p['phase']) echo '<p class="sec">Rien encore. Cette couche est vide tant qu\'on ne l\'a pas remplie.</p>';
            ?>
            </div>
          </div>
          <div>
            <h3 class="sect">Éditorial <span class="n">le site</span></h3>
            <div class="fiche">
            <?php
            $l('Titre FR', $p['title_fr']);
            $l('Titre EN', $p['title_en']);
            $l('Année de création', $p['year_creation']);
            $l('Durée', $p['duration_min'] ? $p['duration_min'] . ' min' : '');
            $l('Public', $p['public_cible']);
            $l('Statut', $p['status'] === 'current' ? 'en cours' : 'passé');
            $l('Publié', $p['visible'] ? 'oui' : 'non');
            $l('Au catalogue', $p['catalog_visible'] ? 'oui' : 'non');
            ?>
            </div>
            <p class="sec ren">Les textes, les images et les catégories se modifient
              dans <a href="/admin/edit.php?e=project&amp;id=<?= $id ?>">l'administration du site</a>.
              Les déplacer ici est un autre chantier: le faire à moitié rouvrirait
              la double saisie qu'on vient de fermer.</p>
          </div>
        </div>

        <h3 class="sect">Dates <span class="n"><?= count($dates) ?></span></h3>
        <?php if (!$dates): ?>
          <p class="sec">Aucune date rattachée. Le rapprochement se fait sur le titre exact.</p>
        <?php else: ?>
        <div class="tw"><table><tbody>
          <?php foreach ($dates as $d): ?>
          <tr>
            <td><a href="/dashboard.php?e=bookings&amp;b=<?= (int)$d['id'] ?>"><?=
              e($d['date_texte'] ?: (string)$d['date_debut']) ?></a></td>
            <td><?= e($d['venue'] ?? '') ?></td><td class="sec"><?= e($d['ville'] ?? '') ?></td>
            <td class="sec"><?= e($d['statut']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <style>
    .fil{padding:12px 26px 0;font-size:13px;display:flex;gap:16px}
    .fil a{color:var(--doux);text-decoration:none}
    .fil a.mod{margin-left:auto;color:var(--encre);font-weight:600}
    h2.gros{font-size:21px;margin:0 0 18px}
    h3.sect{font-size:12.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--doux);
      margin:0 0 8px;border-bottom:1px solid var(--trait);padding-bottom:5px}
    h3.sect .n{font-weight:400;text-transform:none;letter-spacing:0}
    .deux{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:0 40px}
    .deux h3.sect{margin-top:0}
    .fiche .l{display:flex;gap:12px;padding:7px 0;border-bottom:1px solid var(--trait)}
    .fiche .k{color:var(--doux);font-size:12.5px;min-width:130px}
    .fiche .v{font-size:14px}.fiche .n{color:var(--doux);font-size:12px;margin-left:8px}
    .sec{color:var(--doux);font-size:13px}
    .ren{margin-top:14px;max-width:46ch;line-height:1.5}
    .deux + h3.sect{margin-top:32px}
    </style>
    <?php dash_bas(); return;
}

// ═══════════════════════════════════════════════════════════════════════════
// LA LISTE
// ═══════════════════════════════════════════════════════════════════════════

$q     = trim((string)($_GET['q'] ?? ''));
$phase = trim((string)($_GET['ph'] ?? ''));
$etat  = trim((string)($_GET['st'] ?? ''));

$where = ['1=1']; $args = [];
if (isset($PHASES[$phase])) { $where[] = 'pp.phase = ?'; $args[] = $phase; }
if ($etat === 'current' || $etat === 'former') { $where[] = 'pr.status = ?'; $args[] = $etat; }
if ($q !== '') {
    $like = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
    $where[] = '(pr.title_fr LIKE ? OR pr.title_en LIKE ? OR pp.responsable LIKE ?)';
    array_push($args, $like, $like, $like);
}

$t0 = microtime(true);
$st = DB::pdo()->prepare(
    "SELECT pr.id, pr.title_fr, pr.title_en, pr.status, pr.visible, pr.year_creation,
            pr.duration_min, pp.phase, pp.responsable, pp.budget, pp.devise,
            o.nom AS organisation,
            (SELECT COUNT(*) FROM booking b
              WHERE b.supprime_le IS NULL AND b.projet = COALESCE(pr.title_fr, pr.title_en)) AS n_dates
       FROM projects pr
       LEFT JOIN projet_prod  pp ON pp.project_id = pr.id
       LEFT JOIN organisation o  ON o.id = pp.organisation_id
      WHERE " . implode(' AND ', $where) . "
      ORDER BY pr.status, pr.sort, pr.id");
$st->execute($args);
$lignes = $st->fetchAll();
$ms = (int)round((microtime(true) - $t0) * 1000);

$parPhase = DB::pdo()->query("SELECT phase, COUNT(*) n FROM projet_prod GROUP BY phase")
                     ->fetchAll(PDO::FETCH_KEY_PAIR);
$sansProd = (int)DB::pdo()->query("SELECT COUNT(*) FROM projects pr
    LEFT JOIN projet_prod pp ON pp.project_id = pr.id WHERE pp.project_id IS NULL")->fetchColumn();

dash_haut('projets', count($lignes) . ' projet' . (count($lignes)>1?'s':'') . ' · ' . $ms . ' ms');
?>
<form class="filtres" method="get" action="/dashboard.php">
  <input type="hidden" name="e" value="projets">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Titre, responsable">
  <select name="ph">
    <option value="">Toutes les phases</option>
    <?php foreach ($PHASES as $k => $v): ?>
      <option value="<?= $k ?>"<?= $phase === $k ? ' selected' : '' ?>><?=
        e($v) ?> (<?= $parPhase[$k] ?? 0 ?>)</option>
    <?php endforeach; ?>
  </select>
  <select name="st">
    <option value="">Tous</option>
    <option value="current"<?= $etat==='current'?' selected':'' ?>>en cours</option>
    <option value="former"<?= $etat==='former'?' selected':'' ?>>passés</option>
  </select>
  <button type="submit">Chercher</button>
  <?php if ($q!==''||$phase!==''||$etat!==''): ?>
    <a class="vider" href="/dashboard.php?e=projets">tout effacer</a><?php endif; ?>
</form>
<?php dash_flash_html(); ?>

<?php if ($sansProd): ?>
<div class="alerte"><strong><?= $sansProd ?> projets n'ont pas de couche production.</strong>
  Ils existent sur le site et personne n'a encore dit qui les porte, à quelle phase ils
  sont, ni quel budget. Ouvrir la fiche et cliquer sur « modifier la production » suffit.</div>
<?php endif; ?>

<div class="tw"><table>
  <thead><tr><th>Projet</th><th>Phase</th><th>Responsable</th><th>Porteur</th>
    <th class="d">Année</th><th class="d">Durée</th><th class="d">Budget</th><th class="d">Dates</th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $r): ?>
    <tr class="<?= $r['status']==='former' ? 'passe' : '' ?>">
      <td><a href="/dashboard.php?e=projets&amp;p=<?= (int)$r['id'] ?>"><?=
        e($r['title_fr'] ?: $r['title_en']) ?></a>
        <?php if (!$r['visible']): ?><span class="np">non publié</span><?php endif; ?></td>
      <td><?php if ($r['phase']): ?><span class="ph <?= e($r['phase']) ?>"><?=
        e($PHASES[$r['phase']]) ?></span><?php else: ?><span class="sec">—</span><?php endif; ?></td>
      <td class="sec"><?= e($r['responsable'] ?? '') ?></td>
      <td class="sec"><?= e($r['organisation'] ?? '') ?></td>
      <td class="d sec"><?= $r['year_creation'] ? (int)$r['year_creation'] : '' ?></td>
      <td class="d sec"><?= $r['duration_min'] ? (int)$r['duration_min'] . ' min' : '' ?></td>
      <td class="d"><?= $r['budget'] !== null
          ? number_format((float)$r['budget'], 0, ',', ' ') . ' ' . e($r['devise']) : '' ?></td>
      <td class="d"><?= $r['n_dates'] ? (int)$r['n_dates'] : '<span class="sec">0</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<?php /* ── LES PRESSKITS ────────────────────────────────────────────────────
       [16.08.2026]

       ILS NE PENDENT PAS AUX PROJETS CI-DESSUS, et il faut le dire plutôt que
       de le cacher. La table `projet` du dashboard porte la production — la
       phase, le budget, les dates. Le contenu qu'un presskit partage — intro,
       distribution, photos, fiches techniques — vit dans `projects`, la table
       du CMS, parce que c'est elle qui alimente le site public.

       Ce sont donc deux listes, et c'est exactement la duplication que la
       spécification veut supprimer: « nous allons revoir ce qui est ici est
       déjà dans le cms, on ne veut pas travailler en double. » Tant qu'elle
       n'est pas faite, mieux vaut deux listes honnêtes qu'une seule qui
       mentirait sur ce qu'elle montre. */ ?>

<h2 class="sect2">Presskits</h2>
<p class="sec expl">Le lien qu'on envoie à un programmateur intéressé: intro, photos et
   fiches techniques, sans lui demander d'ouvrir un compte ni de connaître le mot de passe
   du Catalogue. Il se révoque, contrairement à une adresse publique une fois partagée.
   <br>Ces spectacles sont ceux du <strong>site</strong>, pas les projets de production
   ci-dessus: le contenu d'un presskit vit dans le CMS.</p>

<div class="tw"><table>
  <thead><tr><th>Spectacle</th><th>Lien</th><th>Visites</th><th></th></tr></thead>
  <tbody>
  <?php foreach (Presskit::projets() as $s): $sid = (int)$s['id'];
        $actif = $s['jeton'] && !(int)$s['revoque']
                 && (!$s['expire_a'] || strtotime((string)$s['expire_a']) > time());
        $url = $actif ? rtrim((string)cfg('base_url',''), '/') . '/presskit.php?t=' . $s['jeton'] : ''; ?>
    <tr>
      <td><?= e((string)($s['title_fr'] ?: $s['title_en'])) ?></td>
      <td class="sec">
        <?php if ($actif): ?>
          <input type="text" class="url" value="<?= e($url) ?>" readonly onclick="this.select()"
                 aria-label="Lien du presskit">
          <?php if ($s['destinataire']): ?><br><span class="np">remis à <?= e((string)$s['destinataire']) ?></span><?php endif; ?>
        <?php elseif ($s['jeton']): ?>
          <span class="sec">révoqué ou expiré</span>
        <?php else: ?>
          <span class="sec">—</span>
        <?php endif; ?>
      </td>
      <td class="d sec"><?= $s['visites'] !== null ? (int)$s['visites'] : '' ?>
        <?php if ($s['dernier_acces']): ?><br><span class="np"><?= e(date('d.m.Y', strtotime((string)$s['dernier_acces']))) ?></span><?php endif; ?>
      </td>
      <td class="d">
        <?php if (dash_droit('projets', dash_role()) === 'ecrit'): ?>
          <form method="post" action="/dashboard.php?e=projets" class="inline">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="pk" value="ouvrir">
            <input type="hidden" name="projet_cms" value="<?= $sid ?>">
            <button type="submit" class="lien-b"><?= $actif ? 'renouveler' : 'ouvrir' ?></button>
          </form>
          <?php if ($actif): ?>
            <form method="post" action="/dashboard.php?e=projets" class="inline"
                  onsubmit="return confirm('Révoquer ce lien ?')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="pk" value="revoquer">
              <input type="hidden" name="projet_cms" value="<?= $sid ?>">
              <button type="submit" class="lien-b">révoquer</button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<style>
td.d,th.d{text-align:right;white-space:nowrap}
tr.passe{opacity:.55}
.sect2{margin:34px 26px 4px;font-size:16px}
.expl{margin:0 26px 12px;max-width:80ch;font-size:13.5px}
.url{width:100%;max-width:420px;padding:5px 8px;font-family:ui-monospace,Menlo,monospace;
  font-size:11.5px;border:1px solid var(--trait);border-radius:4px;
  background:var(--fond2);color:var(--encre)}
.lien-b{background:none;border:0;color:var(--doux);text-decoration:underline;
  cursor:pointer;font:inherit;font-size:12.5px;padding:2px 6px}
.lien-b:hover{color:var(--encre)}
.np{font-size:10.5px;border:1px solid var(--trait);border-radius:3px;padding:0 4px;
    margin-left:6px;color:var(--doux)}
.ph{font-size:11px;padding:2px 8px;border-radius:10px;border:1px solid var(--trait);white-space:nowrap}
.ph.tournee{background:#e7f6ea;border-color:#bfe3c8;color:#1c5c2e}
.ph.creation,.ph.production{background:#fff6d9;border-color:#f0dfa3;color:#6b5312}
.ph.cloture{background:var(--fond2);color:var(--doux)}
.alerte{margin:16px 26px 0;padding:11px 16px;background:var(--fond2);
  border-left:4px solid var(--orange);font-size:13.5px;max-width:82ch}
@media (prefers-color-scheme:dark){:root:not([data-theme=light]) .ph{
  background:transparent!important;color:inherit!important}}
</style>
<?php dash_bas(); ?>

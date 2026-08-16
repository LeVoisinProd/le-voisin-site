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

/* ══ LA FICHE DE PRODUCTION, ses neuf onglets. [16.08.2026] ══════════════
   Ouverte par ?p=<id du spectacle du CMS>. Elle est traitée avant tout le
   reste de cet écran, y compris les POST de la liste: elle a ses propres
   actions et ne partage rien avec eux. */
$pcms = (int)($_GET['p'] ?? 0);
if ($pcms > 0) {
    $p = DB::one('SELECT * FROM projects WHERE id = ?', [$pcms]);
    if (!$p) { dash_haut('projets'); echo '<p class="vide">Ce spectacle n\'existe pas.</p>'; dash_bas(); return; }

    $onglet = preg_replace('/[^a-z]/', '', strtolower((string)($_GET['o'] ?? 'synthese')));
    $retour = '/dashboard.php?e=projets&p=' . $pcms . '&o=' . $onglet;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pf'] ?? '') !== '') {
        Auth::requireCsrf();
        dash_exige_ecriture('projets');
        $act = (string)$_POST['pf'];

        if ($act === 'champs') {
            /* Les champs libres de la fiche, vérifiés un à un contre le modèle:
               une clef inconnue est refusée, parce qu'un JSON n'a pas de schéma
               pour s'en défendre tout seul. */
            $n = 0;
            foreach ((array)($_POST['v'] ?? []) as $chemin => $val) {
                if (ProdFiche::champ($pcms, (string)$chemin, (string)$val)) $n++;
            }
            /* Les colonnes typées de projet_prod ne passent pas par le JSON. */
            $pr = (array)($_POST['prod'] ?? []);
            $maj = [];
            if (isset($pr['phase']) && in_array($pr['phase'], ['dev','creation','production','promo','tournee','cloture'], true)) {
                $maj['phase'] = $pr['phase'];
            }
            foreach (['responsable','valide_par','lieu_creation'] as $c) {
                if (isset($pr[$c])) $maj[$c] = mb_substr(trim((string)$pr[$c]), 0, 190) ?: null;
            }
            if (isset($pr['notes'])) $maj['notes'] = trim((string)$pr['notes']) ?: null;
            if (isset($pr['devise']) && in_array($pr['devise'], ['CHF','EUR'], true)) $maj['devise'] = $pr['devise'];
            if (isset($pr['organisation_id'])) $maj['organisation_id'] = (int)$pr['organisation_id'] ?: null;
            if (isset($pr['budget'])) {
                /* « 12 000 », « 12'000 » et « 12,5 » arrivent tous les trois. Ce
                   qui ne se lit pas comme un nombre est ignoré plutôt qu'écrit à
                   zéro: un budget effacé par une virgule serait pire que rien. */
                $b = str_replace([',', ' ', "'", ' '], ['.', '', '', ''], trim((string)$pr['budget']));
                $maj['budget'] = $b === '' ? null : (is_numeric($b) ? (float)$b : null);
                if ($b !== '' && !is_numeric($b)) unset($maj['budget']);
            }
            if ($maj) { ProdFiche::ligne($pcms); DB::update('projet_prod', $maj, 'project_id = ?', [$pcms]); }
            dash_flash($n || $maj ? 'Enregistré.' : 'Rien à enregistrer.');

        } elseif ($act === 'liste_ajouter') {
            $l = array_map(fn($x) => mb_substr(trim((string)$x), 0, 500), (array)($_POST['l'] ?? []));
            ProdFiche::ajouter($pcms, (string)($_POST['ou'] ?? ''), $l);
            dash_flash('Ligne ajoutée.');

        } elseif ($act === 'liste_retirer') {
            ProdFiche::retirer($pcms, (string)($_POST['ou'] ?? ''), (string)($_POST['ligne'] ?? ''));
            dash_flash('Ligne retirée.');

        } elseif ($act === 'jour') {
            ProdFiche::jour($pcms, (string)($_POST['jour'] ?? ''));

        } elseif ($act === 'fdr_generer') {
            /* Elle remplace le texte, et la page prévient avant. Générer sans
               écraser donnerait deux feuilles de route et personne ne saurait
               laquelle est partie au lieu. */
            $dd = ProdFiche::donnees($pcms);
            $dd['fdr']['texte'] = ProdFiche::feuilleDeRoute($p, $dd);
            ProdFiche::ecrire($pcms, $dd);
            dash_flash('Feuille de route générée. Modifiez-la librement.');
        }
        redirect($retour);
    }

    /* Les deux vues imprimables: le dossier et la feuille de route. */
    if (($_GET['imprimer'] ?? '') === '1' && in_array($onglet, ['dossier','fdr'], true)) {
        require __DIR__ . '/_prod_imprimer.php';
        return;
    }

    require __DIR__ . '/_prod_fiche.php';
    return;
}

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


/* ══ L'ANCIENNE FICHE A ÉTÉ REMPLACÉE. [16.08.2026] ═════════════════════════
   Elle vivait ici et montrait la seule couche `projet_prod`: phase,
   responsable, validé par, lieu, budget, porteur juridique, notes. Ses champs
   sont tous repris dans l'onglet Synthèse de la nouvelle fiche, qui en ajoute
   huit autres. La garder aurait donné deux fiches pour le même spectacle,
   atteignables par la même adresse — et c'est la première qui aurait gagné.

   La liste des dates du spectacle qu'elle affichait est passée dans l'onglet
   Devis, qui répond à la même question avec les prix en plus. */


// ═══════════════════════════════════════════════════════════════════════════
// LA LISTE
// ═══════════════════════════════════════════════════════════════════════════

$q     = trim((string)($_GET['q'] ?? ''));
$phase = trim((string)($_GET['ph'] ?? ''));
/* LES SPECTACLES PASSÉS NE S'OUVRENT PLUS PAR DÉFAUT. Anna, 16.08.2026: « tem
   que tirar os inativos. ja foram, nao precisam estar ali ». Quatorze des
   trente-cinq sont en `status = 'former'`, et ils remplissaient plus du tiers
   d'une liste qu'on ouvre pour travailler sur les vingt et un autres.

   ILS NE SONT PAS SUPPRIMÉS POUR AUTANT, et « tous » reste à un clic: leurs
   fiches de production portent des budgets, des feuilles de route et des
   contrats qu'on va rechercher des années après. Cacher n'est pas effacer, et
   la différence se voit le jour d'un contrôle. */
$etat  = trim((string)($_GET['st'] ?? ''));
if ($etat === '' && !isset($_GET['st'])) $etat = 'current';

/* LE TYPE VIENT DU SITE, il n'est pas ressaisi ici. Anna, 16.08.2026: « vc pode
   puxar a classificacao de tipo de projeto do nosso site ». Les six catégories
   — Danse, Musique, Théâtre, Arts visuels, Performance, Marionnettes — vivent
   dans `categories` et se rattachent par `project_categories`, qui est une
   table de liaison: une pièce peut en porter deux, et « Danse · Marionnettes »
   est une information, pas une hésitation. Les recopier dans le dashboard
   ferait deux vérités qui divergeraient à la première correction faite côté
   site. */
$typeId = (int)($_GET['ty'] ?? 0);
$TYPES  = [];
foreach (DB::all("SELECT c.id, c.name_fr,
                    (SELECT COUNT(*) FROM project_categories pc WHERE pc.category_id = c.id) n
                  FROM categories c ORDER BY c.sort, c.name_fr") as $c)
    if ((int)$c['n'] > 0) $TYPES[(int)$c['id']] = ['nom' => (string)$c['name_fr'], 'n' => (int)$c['n']];

$where = ['1=1']; $args = [];
if (isset($PHASES[$phase])) { $where[] = 'pp.phase = ?'; $args[] = $phase; }
if ($etat === 'current' || $etat === 'former') { $where[] = 'pr.status = ?'; $args[] = $etat; }
if (isset($TYPES[$typeId])) {
    $where[] = 'EXISTS (SELECT 1 FROM project_categories pc WHERE pc.project_id = pr.id AND pc.category_id = ?)';
    $args[] = $typeId;
}
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
            (SELECT GROUP_CONCAT(c.name_fr ORDER BY c.sort SEPARATOR ' · ')
               FROM project_categories pc JOIN categories c ON c.id = pc.category_id
              WHERE pc.project_id = pr.id) AS types,
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
  <select name="ty">
    <option value="">Tous les types</option>
    <?php foreach ($TYPES as $id => $t): ?>
      <option value="<?= $id ?>"<?= $typeId === $id ? ' selected' : '' ?>><?=
        e($t['nom']) ?> (<?= $t['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <select name="st">
    <option value="tous"<?= $etat==='tous'?' selected':'' ?>>tous, y compris les passés</option>
    <option value="current"<?= $etat==='current'?' selected':'' ?>>en cours</option>
    <option value="former"<?= $etat==='former'?' selected':'' ?>>passés</option>
  </select>
  <button type="submit">Chercher</button>
  <?php if ($q!==''||$phase!==''||$etat!=='current'||$typeId): ?>
    <a class="vider" href="/dashboard.php?e=projets">tout effacer</a><?php endif; ?>
</form>
<?php dash_flash_html(); ?>

<?php if ($sansProd): ?>
<div class="alerte"><strong><?= $sansProd ?> projets n'ont pas de couche production.</strong>
  Ils existent sur le site et personne n'a encore dit qui les porte, à quelle phase ils
  sont, ni quel budget. Ouvrir la fiche et cliquer sur « modifier la production » suffit.</div>
<?php endif; ?>

<div class="tw"><table>
  <thead><tr><th>Projet</th><th>Type</th><th>Phase</th><th>Porteur</th>
    <th class="d">Année</th><th class="d">Durée</th><th class="d">Budget</th><th class="d">Dates</th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $r): ?>
    <tr class="<?= $r['status']==='former' ? 'passe' : '' ?>">
      <td><a href="/dashboard.php?e=projets&amp;p=<?= (int)$r['id'] ?>"><?=
        e($r['title_fr'] ?: $r['title_en']) ?></a>
        <?php if (!$r['visible']): ?><span class="np">non publié</span><?php endif; ?></td>
      <td class="sec"><?= $r['types'] ? e((string)$r['types']) : '<span class="sec">—</span>' ?></td>
      <td><?php if ($r['phase']): ?><span class="ph <?= e($r['phase']) ?>"><?=
        e($PHASES[$r['phase']]) ?></span><?php else: ?><span class="sec">—</span><?php endif; ?></td>
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

<h2 class="sect2">Les spectacles du site</h2>
<p class="sec expl">Cliquez un titre pour ouvrir sa <strong>fiche de production</strong> et ses
   neuf onglets: Synthèse, Dossier, Planning, Logistique, Feuille de route, Rémunération,
   Budget, Devis, Droits d'auteur.
   <br>La colonne <em>presskit</em> donne le lien qu'on envoie à un programmateur — intro,
   photos et fiches techniques, sans compte ni mot de passe du Catalogue. Il se révoque,
   contrairement à une adresse publique une fois partagée.
   <br>Ces spectacles sont ceux du <strong>site</strong>, pas les projets de production
   ci-dessus: leur contenu vit dans le CMS.</p>

<div class="tw"><table>
  <thead><tr><th>Spectacle</th><th>Lien de presskit</th><th>Visites</th><th></th></tr></thead>
  <tbody>
  <?php foreach (Presskit::projets() as $s): $sid = (int)$s['id'];
        $actif = $s['jeton'] && !(int)$s['revoque']
                 && (!$s['expire_a'] || strtotime((string)$s['expire_a']) > time());
        $url = $actif ? rtrim((string)cfg('base_url',''), '/') . '/presskit.php?t=' . $s['jeton'] : ''; ?>
    <tr>
      <td><a href="/dashboard.php?e=projets&amp;p=<?= $sid ?>"><strong><?= e((string)($s['title_fr'] ?: $s['title_en'])) ?></strong></a></td>
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
</style>
<?php dash_bas(); ?>

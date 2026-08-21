<?php
/**
 * Écran Personnel. [17.08.2026]
 *
 * 91 personnes, 72 engagements. Les données sont en production depuis le
 * 16.08 et n'avaient AUCUN écran: on pouvait les écrire par un script et pas
 * les lire, ce qui revient à ne pas les avoir. C'était le premier point du
 * plan du lendemain, pour cette raison — c'est le plus gros déblocage d'un
 * seul geste.
 *
 * SIX ONGLETS, dans l'ordre où le travail se fait:
 *
 *   Employé·e·s     qui ils sont. L'identité, l'adresse, l'AVS, l'IBAN
 *   Engagements     qui travaille sur quoi, quand, à quel tarif
 *   Salaires        ce que ça coûte, par association et par mois
 *   AGI             l'attestation de gain intermédiaire, sur le formulaire
 *                   officiel, prête à envoyer à la caisse
 *   Feuilles de temps  les heures, quand elles seront saisies
 *   Équipe & accès  qui entre dans ce dashboard et avec quel rôle
 *
 * CET ÉCRAN EST RÉSERVÉ À `direction`, et ce n'est pas une précaution de
 * principe: il affiche 42 numéros AVS et 40 IBAN. C'est la raison d'être de
 * toute la grille de permissions — Anna, 16.08.2026, sur `administration`:
 * « pour voir une date de spectacle il fallait tout voir, salaires, AVS et
 * IBAN compris, donc personne n'entrait ».
 *
 * L'AVS ET L'IBAN NE SORTENT JAMAIS EN LISTE. Ils vivent sur la fiche d'une
 * personne, une à la fois, derrière un geste. Une liste de 91 lignes portant
 * 91 IBAN se copie d'un glissement de souris et se retrouve dans un tableur
 * sur un bureau; une fiche à la fois, non.
 */
declare(strict_types=1);

require_once __DIR__ . '/_form.php';

$ONGLETS = [
    'emp'    => 'Employé·e·s',
    'eng'    => 'Engagements',
    'sal'    => 'Salaires',
    'agi'    => 'AGI',
    'temps'  => 'Feuilles de temps',
    'equipe' => 'Équipe & accès',
];
$onglet = isset($ONGLETS[$_GET['t'] ?? '']) ? (string)$_GET['t'] : 'emp';
$pid    = (int)($_GET['p'] ?? 0);

/** Les types d'engagement rencontrés, pour les filtres et la saisie. */
$TYPES = ['interne' => 'interne', 'cdd' => 'CDD', 'cdi' => 'CDI',
          'intermittent' => 'intermittent', 'mandat' => 'mandat', 'stage' => 'stage'];

// ═══════════════════════════════════════════════════════════════════════════
// ACTIONS
// ═══════════════════════════════════════════════════════════════════════════

$CH_EMP = ['prenom','nom','pronom','email','telephone','fonction','role_interne','couleur',
           'type_engagement','organisation_id','naissance','nationalite','permis',
           'rue','cp','ville','pays','paie_mensuelle','paie_horaire','devise','notes','actif'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    dash_exige_ecriture('personnel');
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'emp') {
        $id   = (int)($_POST['id'] ?? 0);
        $cols = [];
        foreach ($CH_EMP as $c) {
            if (!array_key_exists($c, $_POST)) continue;
            $v = trim((string)$_POST[$c]);
            /* Les colonnes numériques et les dates n'acceptent pas la chaîne
               vide: MariaDB en fait 0 et 0000-00-00, qui s'affichent ensuite
               comme une vraie valeur. Le vide doit rester le vide. */
            if (in_array($c, ['paie_mensuelle','paie_horaire','organisation_id','naissance'], true))
                $cols[$c] = ($v === '' ? null : $v);
            elseif ($c === 'actif') $cols[$c] = $v === '1' ? 1 : 0;
            else $cols[$c] = $v;
        }
        if (isset($_POST['actif']) === false && $id > 0) unset($cols['actif']);

        /* L'AVS ET L'IBAN PASSENT PAR LE CHIFFREMENT, jamais par $CH_EMP. Les
           mettre dans la liste générale les écrirait en clair, et c'est
           exactement le trou que `chiffrer_fiches.php` a bouché le 16.08 —
           36 valeurs en clair, dont 24 AVS et 20 IBAN.

           UN CHAMP LAISSÉ VIDE NE VIDE RIEN. Le formulaire n'affiche pas la
           valeur existante en clair; si on ne saisit rien, c'est qu'on ne
           voulait pas y toucher, pas qu'on voulait l'effacer. */
        foreach (['avs', 'iban'] as $c) {
            $v = trim((string)($_POST[$c] ?? ''));
            if ($v !== '') $cols[$c] = Crypto::chiffrer($v);
        }

        if (trim((string)($cols['prenom'] ?? '')) === '' && trim((string)($cols['nom'] ?? '')) === '') {
            dash_flash('Il faut au moins un prénom ou un nom.', 'err');
        } elseif ($id > 0) {
            DB::update('rh_employe', $cols, 'id = ?', [$id]);
            dash_flash('Fiche enregistrée.');
        } else {
            $cols['devise'] ??= 'CHF';
            $cols['actif']  ??= 1;
            DB::insert('rh_employe', $cols);
            $id = (int)DB::val("SELECT id FROM rh_employe ORDER BY id DESC LIMIT 1");
            dash_flash('Fiche créée.');
        }
        header('Location: ' . url('/dashboard.php?e=personnel&p=' . $id));
        exit;
    }

    if ($act === 'eng') {
        $id   = (int)($_POST['id'] ?? 0);
        $cols = [];
        foreach (['employe_id','projet','organisation_id','debut','fin','mois','jours','heures',
                  'paie_mensuelle','paie_horaire','statut','notes'] as $c) {
            if (!array_key_exists($c, $_POST)) continue;
            $v = trim((string)$_POST[$c]);
            $cols[$c] = ($v === '' ? null : $v);
        }
        /* Le nom est recopié sur l'engagement. C'est une duplication assumée:
           les 72 engagements repris du dashboard portent un nom et un `empId`
           qui ne mène nulle part — 2 sur 72 retombaient sur quelqu'un. Le nom,
           lui, est juste sur les 72. Tant que le lien n'est pas refait à la
           main, c'est le nom qui dit de qui il s'agit. */
        if (!empty($cols['employe_id'])) {
            $e = DB::one("SELECT prenom, nom FROM rh_employe WHERE id = ?", [(int)$cols['employe_id']]);
            if ($e) $cols['employe_nom'] = trim($e['prenom'] . ' ' . $e['nom']);
        }
        if ($id > 0) { DB::update('rh_engagement', $cols, 'id = ?', [$id]); dash_flash('Engagement enregistré.'); }
        else         { DB::insert('rh_engagement', $cols);                  dash_flash('Engagement créé.'); }
        header('Location: ' . url('/dashboard.php?e=personnel&t=eng'));
        exit;
    }

    if ($act === 'supprimer') {
        /* Suppression logique, comme les contacts: une fiche de personnel
           porte des engagements, des salaires et des déclarations. La faire
           disparaître pour de bon rendrait illisibles des décomptes déjà
           déposés. */
        DB::run("UPDATE rh_employe SET supprime_le = NOW() WHERE id = ?", [(int)($_POST['id'] ?? 0)]);
        dash_flash('Fiche retirée des listes. Elle reste en base.');
        header('Location: ' . url('/dashboard.php?e=personnel'));
        exit;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// LA FICHE D'UNE PERSONNE
// ═══════════════════════════════════════════════════════════════════════════

if ($pid > 0 || ($_GET['neuf'] ?? '') !== '') {
    require __DIR__ . '/_personnel_fiche.php';
    return;
}

// ═══════════════════════════════════════════════════════════════════════════
// CE QUE TOUS LES ONGLETS PARTAGENT
// ═══════════════════════════════════════════════════════════════════════════

$orgs = DB::all("SELECT id, nom FROM organisation
                  WHERE supprime_le IS NULL AND genre = 'association' ORDER BY nom");
$nomOrg = [];
foreach ($orgs as $o) $nomOrg[(int)$o['id']] = (string)$o['nom'];

dash_haut('personnel');
?>
<nav class="onglets">
  <?php foreach ($ONGLETS as $k => $lib): ?>
    <a href="<?= e(url('/dashboard.php?e=personnel&t=' . $k)) ?>"
       class="<?= $onglet === $k ? 'ici' : '' ?>"><?= e($lib) ?></a>
  <?php endforeach; ?>
</nav>
<?php dash_flash_html(); ?>
<?php require __DIR__ . '/_filtre_colonnes.php'; ?>

<?php /* LA GOUTTIÈRE DE 26 PX, QUI MANQUAIT. [Anna, 21.08.2026] « il y a encore
     des choses collées au menu ». Quatre écrans n'enveloppaient rien dans
     `.zone` — offres, personnel, projets, calendrier — et c'était le même
     oubli: le contenu commençait à zéro, donc sous la barre noire, et les
     tableaux poussaient la page à déborder vers la droite. */ ?>
<div class="zone">


<?php
// ═══════════════════════════════════════════════════════════════════════════
// ONGLET EMPLOYÉ·E·S
// ═══════════════════════════════════════════════════════════════════════════
if ($onglet === 'emp'):

    $q    = trim((string)($_GET['q'] ?? ''));
    $org  = (int)($_GET['org'] ?? 0);
    $type = trim((string)($_GET['type'] ?? ''));
    $vue  = (string)($_GET['vue'] ?? 'actifs');

    $w = ['supprime_le IS NULL']; $a = [];
    if ($q !== '') {
        $w[] = "(CONCAT_WS(' ', prenom, nom, email, fonction, role_interne, ville) LIKE ?)";
        $a[] = '%' . $q . '%';
    }
    if ($org)          { $w[] = 'organisation_id = ?'; $a[] = $org; }
    if ($type !== '')  { $w[] = 'type_engagement = ?'; $a[] = $type; }
    if ($vue === 'actifs')   $w[] = 'actif = 1';
    if ($vue === 'inactifs') $w[] = 'actif = 0';

    $sql = implode(' AND ', $w);
    $gens = DB::all("SELECT * FROM rh_employe WHERE $sql ORDER BY nom, prenom", $a);

    $lesTypes = DB::all("SELECT type_engagement t, COUNT(*) n FROM rh_employe
                          WHERE supprime_le IS NULL AND type_engagement <> ''
                          GROUP BY t ORDER BY n DESC");
    $total  = (int)DB::val("SELECT COUNT(*) FROM rh_employe WHERE supprime_le IS NULL");
    $nbEng  = [];
    foreach (DB::all("SELECT employe_id, COUNT(*) n FROM rh_engagement
                       WHERE supprime_le IS NULL AND employe_id IS NOT NULL GROUP BY employe_id") as $r)
        $nbEng[(int)$r['employe_id']] = (int)$r['n'];
?>
<?php /* LES FILTRES QUE LE MENU DE COLONNE REMPLACE SONT PARTIS.
     [Anna, 21.08.2026] « este tipo de filtro acaba de colocar os outros
     filtros em desuso, pode tirar ». Association, type et recherche libre:
     ces colonnes ont leur menu, avec le compte de chaque valeur et le tri.

     LA VUE RESTE, PARCE QU'ELLE N'EST PAS UN FILTRE: par défaut cet écran ne
     montre que les personnes actives, et le menu de colonne ne voit que ce
     qui est rendu. La retirer ne masquerait pas les inactives, elle les
     rendrait inatteignables. */ ?>
<form class="filtres" method="get" action="<?= e(url('/dashboard.php')) ?>">
  <input type="hidden" name="e" value="personnel">
  <input type="hidden" name="t" value="<?= e($onglet) ?>">
  <select name="vue">
      <option value="actifs"<?=   $vue === 'actifs'   ? ' selected' : '' ?>>Actives et actifs</option>
      <option value="inactifs"<?= $vue === 'inactifs' ? ' selected' : '' ?>>Inactives et inactifs</option>
      <option value="tous"<?=     $vue === 'tous'     ? ' selected' : '' ?>>Tout le monde</option>
    </select>
  <button type="submit">Voir</button>
  <a class="neuf" href="<?= e(url('/dashboard.php?e=personnel&neuf=1')) ?>">+ nouvelle personne</a>
</form>

<p class="cpt"><?= count($gens) ?> sur <?= $total ?></p>

<?php if (!$gens): ?>
  <p class="vide">Personne ne correspond.</p>
<?php else: ?>
<div class="tw">
<table data-filtres>
  <thead><tr>
    <th>Nom</th><th>Fonction</th><th>Association</th>
    <th>Type</th>
    <th class="n">Engagements</th><th>Contact</th>
  </tr></thead>
  <tbody>
  <?php foreach ($gens as $g):
      $nom = trim($g['prenom'] . ' ' . $g['nom']); ?>
    <tr<?= $g['actif'] ? '' : ' class="off"' ?>>
      <td>
        <?php if ($g['couleur']): ?><span class="pastille"
             style="background:<?= e((string)$g['couleur']) ?>"></span><?php endif; ?>
        <a href="<?= e(url('/dashboard.php?e=personnel&p=' . (int)$g['id'])) ?>"><?= e($nom) ?></a>
        <?php if ($g['pronom']): ?><span class="sec">(<?= e((string)$g['pronom']) ?>)</span><?php endif; ?>
      </td>
      <td><?= e((string)($g['role_interne'] ?: $g['fonction'])) ?></td>
      <td><?= e($nomOrg[(int)$g['organisation_id']] ?? (string)$g['asso_ref']) ?></td>
      <td><?= e((string)$g['type_engagement']) ?></td>
      <td class="n"><?= ($n = $nbEng[(int)$g['id']] ?? 0) ? $n : '<span class="tiret">—</span>' ?></td>
      <td class="sec"><?= e((string)$g['email']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php
// ═══════════════════════════════════════════════════════════════════════════
// ONGLET ENGAGEMENTS
// ═══════════════════════════════════════════════════════════════════════════
elseif ($onglet === 'eng'):

    $engs = DB::all("SELECT e.*, r.prenom, r.nom
                       FROM rh_engagement e
                       LEFT JOIN rh_employe r ON r.id = e.employe_id
                      WHERE e.supprime_le IS NULL
                      ORDER BY e.debut DESC, e.employe_nom");
    $sansLien = count(array_filter($engs, fn($x) => !$x['employe_id']));
?>
<?php if ($sansLien): ?>
<div class="note">
  <p><strong><?= $sansLien ?> engagement(s) sur <?= count($engs) ?> ne pointent vers aucune
     fiche de personne.</strong> Le dashboard d'origine porte une numérotation antérieure:
     sur les 72 repris, 2 identifiants retombaient sur quelqu'un. Le nom, lui, est juste
     sur les 72 — c'est lui qui s'affiche en attendant que le lien soit refait.</p>
</div>
<?php endif; ?>

<?php if (!$engs): ?>
  <p class="vide">Aucun engagement.</p>
<?php else: ?>
<div class="tw">
<table data-filtres>
  <thead><tr>
    <th>Personne</th><th>Projet</th><th>Association</th>
    <th>Début</th><th>Fin</th><th class="n">Jours</th>
    <th class="n">Heures</th>
    <th class="n">Mensuel</th><th class="n">Horaire</th>
    <th>État</th>
  </tr></thead>
  <tbody>
  <?php foreach ($engs as $g): ?>
    <tr>
      <td><?php if ($g['employe_id']): ?>
            <a href="<?= e(url('/dashboard.php?e=personnel&p=' . (int)$g['employe_id'])) ?>"><?=
              e(trim($g['prenom'] . ' ' . $g['nom'])) ?></a>
          <?php else: ?>
            <?= e((string)$g['employe_nom']) ?> <span class="sec" title="sans fiche">·</span>
          <?php endif; ?></td>
      <td><?= e((string)$g['projet']) ?></td>
      <td><?= e($nomOrg[(int)$g['organisation_id']] ?? (string)$g['asso_ref']) ?></td>
      <td><?= e((string)$g['debut']) ?></td>
      <td><?= e((string)$g['fin']) ?></td>
      <td class="n"><?= $g['jours']  !== null ? e((string)$g['jours'])  : '<span class="tiret">—</span>' ?></td>
      <td class="n"><?= $g['heures'] !== null ? e((string)$g['heures']) : '<span class="tiret">—</span>' ?></td>
      <td class="n"><?= $g['paie_mensuelle'] !== null ? number_format((float)$g['paie_mensuelle'], 0, ',', "'") : '<span class="tiret">—</span>' ?></td>
      <td class="n"><?= $g['paie_horaire']   !== null ? number_format((float)$g['paie_horaire'],   2, ',', "'") : '<span class="tiret">—</span>' ?></td>
      <td><?= e((string)$g['statut']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php
// ═══════════════════════════════════════════════════════════════════════════
// ONGLET SALAIRES
// ═══════════════════════════════════════════════════════════════════════════
elseif ($onglet === 'sal'):

    /* CE QUI EST COMPTÉ EST DIT, et c'est la seule façon de rendre un total
       lisible. Un « coût mensuel » calculé sur des fiches dont la moitié n'a
       pas de tarif ressemble à un chiffre mesuré et n'en est pas un. */
    $parOrg = DB::all(
        "SELECT organisation_id, COUNT(*) n,
                SUM(paie_mensuelle IS NOT NULL) nm, SUM(paie_mensuelle) m,
                SUM(paie_horaire   IS NOT NULL) nh, AVG(paie_horaire)   h
           FROM rh_employe
          WHERE supprime_le IS NULL AND actif = 1
          GROUP BY organisation_id ORDER BY m DESC");
    $sansTarif = (int)DB::val("SELECT COUNT(*) FROM rh_employe
                                WHERE supprime_le IS NULL AND actif = 1
                                  AND paie_mensuelle IS NULL AND paie_horaire IS NULL");
    $actifs = (int)DB::val("SELECT COUNT(*) FROM rh_employe WHERE supprime_le IS NULL AND actif = 1");
?>
<div class="note">
  <p>Les montants sont ceux <strong>portés sur la fiche de chaque personne</strong>, pas ceux
     d'un décompte de salaire. Ils servent à chiffrer une date avant de la vendre, pas à payer.</p>
  <?php if ($sansTarif): ?>
  <p><strong><?= $sansTarif ?> personne(s) sur <?= $actifs ?> n'ont ni tarif mensuel ni tarif
     horaire.</strong> Les colonnes ci-dessous ne les comptent pas: un total qui les compterait
     pour zéro dirait que le personnel coûte moins qu'il ne coûte.</p>
  <?php endif; ?>
</div>

<div class="tw">
<table data-filtres>
  <thead><tr>
    <th>Association</th><th class="n">Personnes</th>
    <th class="n">Avec tarif mensuel</th><th class="n">Total mensuel</th>
    <th class="n">Avec tarif horaire</th><th class="n">Horaire moyen</th>
  </tr></thead>
  <tbody>
  <?php $tm = 0; foreach ($parOrg as $r): $tm += (float)$r['m']; ?>
    <tr>
      <td><?= e($nomOrg[(int)$r['organisation_id']] ?? '—') ?></td>
      <td class="n"><?= (int)$r['n'] ?></td>
      <td class="n"><?= (int)$r['nm'] ?></td>
      <td class="n"><?= $r['m'] !== null ? number_format((float)$r['m'], 0, ',', "'") : '<span class="tiret">—</span>' ?></td>
      <td class="n"><?= (int)$r['nh'] ?></td>
      <td class="n"><?= $r['h'] !== null ? number_format((float)$r['h'], 2, ',', "'") : '<span class="tiret">—</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr>
    <th>Total</th><th class="n"><?= $actifs ?></th><th></th>
    <th class="n"><?= number_format($tm, 0, ',', "'") ?></th><th></th><th></th>
  </tr></tfoot>
</table>
</div>

<?php
// ═══════════════════════════════════════════════════════════════════════════
// ONGLET AGI
// ═══════════════════════════════════════════════════════════════════════════
elseif ($onglet === 'agi'):
    require __DIR__ . '/_personnel_agi.php';

// ═══════════════════════════════════════════════════════════════════════════
// ONGLET FEUILLES DE TEMPS
// ═══════════════════════════════════════════════════════════════════════════
elseif ($onglet === 'temps'):
    $avecHeures = DB::all("SELECT e.*, r.prenom, r.nom FROM rh_engagement e
                             LEFT JOIN rh_employe r ON r.id = e.employe_id
                            WHERE e.supprime_le IS NULL AND e.heures IS NOT NULL
                            ORDER BY e.debut DESC");
?>
<div class="note">
  <p>Les heures se saisissent aujourd'hui <strong>sur l'engagement</strong>, en un total.
     Une feuille de temps jour par jour n'existe pas encore, et c'est elle que l'AGI
     réclame: la grille des 31 jours du formulaire se remplit à la main.</p>
  <p>Ce qui existe déjà et qu'il ne faudra pas refaire: les 31 cases de la grille sont
     <strong>nommées et vérifiées</strong> dans <code>agi-champs.json</code>. Le jour où
     les heures seront saisies ici, l'onglet AGI les y écrira sans autre travail.</p>
</div>

<?php if (!$avecHeures): ?>
  <p class="vide">Aucun engagement ne porte d'heures.</p>
<?php else: ?>
<div class="tw">
<table data-filtres>
  <thead><tr><th>Personne</th><th>Projet</th><th>Période</th>
    <th class="n">Heures</th><th class="n">Jours</th></tr></thead>
  <tbody>
  <?php foreach ($avecHeures as $g): ?>
    <tr>
      <td><?= e(trim($g['prenom'] . ' ' . $g['nom']) ?: (string)$g['employe_nom']) ?></td>
      <td><?= e((string)$g['projet']) ?></td>
      <td><?= e(trim((string)$g['debut'] . ' → ' . (string)$g['fin'], ' →')) ?></td>
      <td class="n"><?= e((string)$g['heures']) ?></td>
      <td class="n"><?= $g['jours'] !== null ? e((string)$g['jours']) : '<span class="tiret">—</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php
// ═══════════════════════════════════════════════════════════════════════════
// ONGLET ÉQUIPE & ACCÈS
// ═══════════════════════════════════════════════════════════════════════════
else:
    $bureau  = DB::all("SELECT * FROM rh_employe
                         WHERE supprime_le IS NULL AND role_interne <> '' ORDER BY id");
    $comptes = DB::all("SELECT id, email, name, role_dash, last_login FROM users ORDER BY email");
    $parMail = [];
    foreach ($comptes as $c) $parMail[mb_strtolower((string)$c['email'])] = $c;
?>
<div class="note">
  <p>Deux choses distinctes sur le même écran, parce qu'on les confond tout le temps.
     <strong>L'équipe</strong>, c'est qui travaille au bureau. <strong>Les accès</strong>,
     c'est qui peut ouvrir ce dashboard. Une personne peut être l'une sans l'autre:
     la comptable n'a pas besoin d'un compte, un compte de lecture peut appartenir
     à quelqu'un d'extérieur.</p>
</div>

<h3>L'équipe</h3>
<div class="tw">
<table data-filtres>
  <thead><tr><th>Nom</th><th>Rôle</th><th>Courriel</th><th>Téléphone</th>
    <th>Compte</th></tr></thead>
  <tbody>
  <?php foreach ($bureau as $b):
      $c = $parMail[mb_strtolower((string)$b['email'])] ?? null; ?>
    <tr>
      <td><span class="pastille" style="background:<?= e((string)$b['couleur'] ?: '#ccc') ?>"></span>
          <a href="<?= e(url('/dashboard.php?e=personnel&p=' . (int)$b['id'])) ?>"><?=
            e(trim($b['prenom'] . ' ' . $b['nom'])) ?></a></td>
      <td><?= e((string)$b['role_interne']) ?></td>
      <td class="sec"><?= e((string)$b['email']) ?></td>
      <td class="sec"><?= e((string)$b['telephone']) ?></td>
      <td><?= $c ? e((string)$c['role_dash'])
                 : '<span class="tiret">aucun</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<h3>Les comptes du dashboard</h3>
<div class="tw">
<table data-filtres>
  <thead><tr><th>Courriel</th><th>Nom</th><th>Rôle</th><th>Dernière entrée</th></tr></thead>
  <tbody>
  <?php foreach ($comptes as $c): ?>
    <tr>
      <td><?= e((string)$c['email']) ?></td>
      <td><?= e((string)$c['name']) ?></td>
      <td><?= e((string)$c['role_dash']) ?></td>
      <td class="sec"><?= e((string)$c['last_login']) ?: '<span class="tiret">jamais</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="sec">Les rôles se donnent depuis <a href="<?= e(url('/dashboard.php?e=parametres')) ?>">Paramètres
   et équipe</a>, où l'on peut aussi créer un compte. Ils ne se modifient pas ici, pour
   qu'il n'y ait qu'un seul endroit qui les décide.</p>

<?php endif; ?>

<style>
.onglets{display:flex;gap:2px;padding:12px 26px 0;border-bottom:1px solid var(--trait);flex-wrap:wrap}
.onglets a{padding:8px 15px;font-size:13.5px;text-decoration:none;
  border-bottom:3px solid transparent;color:var(--doux)}
.onglets a.ici{color:var(--encre);border-bottom-color:var(--jaune);font-weight:600}
.filtres.deux-lignes{display:block}
.fl-haut{display:flex;align-items:center;gap:10px;margin-bottom:9px}
.fl-haut input[type=search]{flex:1 1 auto;min-width:0}
.fl-haut button{white-space:nowrap}
.neuf{margin-left:0;padding:8px 16px;background:var(--jaune);color:#0d0d0d;
  border-radius:4px;text-decoration:none;font-size:13.5px;font-weight:600;white-space:nowrap}
.fl-bas{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.cpt{font-size:13px;color:var(--doux);margin:0 0 10px}
tr.off td{opacity:.55}
.pastille{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:6px;
  vertical-align:middle}
.tiret{color:var(--doux);opacity:.5}
td.n,th.n{text-align:right;font-variant-numeric:tabular-nums}
.note{border-left:3px solid var(--jaune);padding:2px 0 2px 14px;margin:0 26px 18px;
  max-width:760px;font-size:13.5px}
.note p{margin:.5em 0}
h3{margin:26px 26px 10px;font-size:15px}
</style>
</div><!-- .zone -->

<?php dash_bas(); ?>

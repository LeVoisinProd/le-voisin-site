<?php
/**
 * Écran Administration. [16.08.2026]
 *
 * La conformité suisse et française. C'EST LA SEULE CAPACITÉ QU'AUCUN DES
 * DIX-SEPT LOGICIELS DU MÉTIER NE COUVRE, vérifié au benchmark du 15.08.2026:
 * l'impôt à la source canton par canton, l'AVS employeur, les attestations A1 et
 * le calendrier d'un bureau qui administre treize associations n'ont d'équivalent
 * nulle part. C'est donc l'écran qui justifie de construire plutôt que d'acheter.
 *
 * Et c'est celui qu'Alessandra ouvre tous les mois, avec quatre heures par
 * semaine devant elle. Il est donc fait pour être parcouru vite: ce qui reste à
 * faire d'abord, le fait à la fin, un clic pour cocher.
 *
 * DEUX ONGLETS.
 *   Le mois   la liste des obligations, générée depuis le modèle
 *   A1        les attestations, dérivées des dates hors de Suisse
 */
declare(strict_types=1);

$ETATS  = ['a_faire'=>'à faire','en_cours'=>'en cours','fait'=>'fait','sans_objet'=>'sans objet'];
$ETATS_A1 = ['a_demander'=>'à demander','demande'=>'demandée','recu'=>'reçue','sans_objet'=>'sans objet'];
$CATS   = ['declarations'=>'déclarations','rh'=>'ressources humaines','compta'=>'comptabilité',
           'juridique'=>'juridique','autre'=>'autre'];

/** Le délai légal d'une demande A1. Quatre semaines, et ce n'est pas indicatif. */
const A1_DELAI = 28;

$onglet  = ($_GET['t'] ?? '') === 'a1' ? 'a1' : 'mois';
$periode = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['m'] ?? '')) ? $_GET['m'] : date('Y-m');

// ═══════════════════════════════════════════════════════════════════════════
// ACTIONS
// ═══════════════════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'generer') {
        /* IDEMPOTENT PAR LA CLEF UNIQUE (modele, periode, organisation). Relancer
           la génération d'un mois déjà généré n'ajoute rien et n'efface rien: ce
           qui est déjà coché le reste. C'est ce qui permet de la relancer après
           avoir ajouté une association, sans peur. */
        $orgs = DB::all("SELECT id, pays FROM organisation
                          WHERE genre = 'association' AND supprime_le IS NULL AND statut = 'actif'");
        $mods = DB::all("SELECT * FROM admin_modele WHERE actif = 1");
        $st = DB::pdo()->prepare(
            'INSERT IGNORE INTO admin_tache (modele_id, organisation_id, periode, territoire, echeance)
             VALUES (?,?,?,?,?)');
        $n = 0;
        foreach ($orgs as $o) {
            $paysOrg = strtoupper(substr((string)($o['pays'] ?? 'CH'), 0, 2));
            foreach ($mods as $m) {
                /* Une obligation française ne concerne pas une association
                   suisse, et l'inverse. Générer les deux pour tout le monde
                   ferait une liste que personne ne lit. */
                $t = (string)$m['territoire'];
                $estFr = $t === 'FR';
                $orgFr = in_array($paysOrg, ['FR'], true);
                if ($estFr !== $orgFr && $t !== '') continue;

                $ech = $m['jour_echeance']
                     ? sprintf('%s-%02d', $periode, min(28, (int)$m['jour_echeance'])) : null;
                $st->execute([$m['id'], $o['id'], $periode, $m['territoire'], $ech]);
                $n += $st->rowCount();
            }
        }
        dash_flash($n > 0 ? "$n obligation(s) ajoutée(s) pour $periode."
                          : "Rien à ajouter: le mois était déjà généré.");
        redirect('/dashboard.php?e=administration&m=' . $periode);
    }

    if ($act === 'etat') {
        $tid = (int)($_POST['id'] ?? 0);
        $e   = (string)($_POST['etat'] ?? '');
        if ($tid && isset($ETATS[$e])) {
            DB::pdo()->prepare('UPDATE admin_tache SET etat = ?, fait_le = ?, fait_par = ?
                                 WHERE id = ?')
              ->execute([$e, $e === 'fait' ? date('Y-m-d H:i:s') : null,
                         $e === 'fait' ? (Auth::user()['name'] ?? null) : null, $tid]);
        }
        redirect('/dashboard.php?e=administration&m=' . $periode);
    }

    if ($act === 'a1_etat') {
        $aid = (int)($_POST['id'] ?? 0);
        $e   = (string)($_POST['etat'] ?? '');
        if ($aid && isset($ETATS_A1[$e])) {
            DB::pdo()->prepare('UPDATE a1_demande SET etat = ?, demande_le = ?, recu_le = ? WHERE id = ?')
              ->execute([$e, $e === 'demande' ? date('Y-m-d') : null,
                         $e === 'recu' ? date('Y-m-d') : null, $aid]);
        }
        redirect('/dashboard.php?e=administration&t=a1');
    }

    if ($act === 'a1_creer') {
        $bid = (int)($_POST['booking_id'] ?? 0);
        $qui = trim((string)($_POST['personne'] ?? ''));
        if ($bid && $qui !== '') {
            DB::pdo()->prepare('INSERT IGNORE INTO a1_demande (booking_id, personne) VALUES (?,?)')
                     ->execute([$bid, $qui]);
            dash_flash('Demande A1 ajoutée.');
        }
        redirect('/dashboard.php?e=administration&t=a1');
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// ONGLET A1
// ═══════════════════════════════════════════════════════════════════════════

if ($onglet === 'a1') {
    /* Les dates hors de Suisse à venir. `pays` est enfin une colonne, donc cette
       dérivation ne demande plus rien à personne. */
    $dates = DB::all(
        "SELECT b.*, DATEDIFF(b.date_debut, CURDATE()) AS jours
           FROM booking b
          WHERE b.supprime_le IS NULL AND b.date_debut >= CURDATE()
            AND b.pays IS NOT NULL AND b.pays NOT IN ('CH','Suisse','SUISSE')
            AND b.statut IN ('confirmed','option')
          ORDER BY b.date_debut");

    $parBooking = [];
    foreach (DB::all("SELECT * FROM a1_demande ORDER BY personne") as $a) {
        $parBooking[(int)$a['booking_id']][] = $a;
    }

    $urgentes = 0;
    foreach ($dates as $d) {
        $faites = array_filter($parBooking[(int)$d['id']] ?? [],
                               fn($a) => in_array($a['etat'], ['recu','sans_objet'], true));
        if ((int)$d['jours'] <= A1_DELAI && count($faites) === 0) $urgentes++;
    }

    dash_haut('administration', count($dates) . ' date(s) hors de Suisse à venir');
    ?>
    <div class="onglets">
      <a href="/dashboard.php?e=administration">Le mois</a>
      <a href="/dashboard.php?e=administration&amp;t=a1" class="ici">Attestations A1</a>
    </div>
    <?php dash_flash_html(); ?>

    <div class="avis">
      <h2>Obligation légale, pas formalité</h2>
      <p>Détacher quelqu'un dans l'Union sans attestation A1 expose à un contrôle sur
         place et à une amende. <strong>La demande prend quatre semaines</strong>, donc une
         date à moins de <?= A1_DELAI ?> jours sans A1 est déjà en retard.</p>
      <?php if ($urgentes): ?>
        <p><strong><?= $urgentes ?> date(s) sont dans cette situation.</strong></p>
      <?php endif; ?>
    </div>

    <?php if (!$dates): ?>
      <p class="vide">Aucune date hors de Suisse à venir.</p>
    <?php else: ?>
    <div class="zone">
    <?php foreach ($dates as $d):
        $qui = $parBooking[(int)$d['id']] ?? [];
        $ok  = count(array_filter($qui, fn($a) => in_array($a['etat'], ['recu','sans_objet'], true)));
        $urg = (int)$d['jours'] <= A1_DELAI && $ok === 0; ?>
      <div class="dbloc <?= $urg ? 'urg' : '' ?>">
        <div class="dtete">
          <a href="/dashboard.php?e=bookings&amp;b=<?= (int)$d['id'] ?>"><strong><?=
            e($d['date_texte'] ?: (string)$d['date_debut']) ?></strong></a>
          <span class="sec"><?= e($d['venue'] ?? '') ?>, <?= e($d['ville'] ?? '') ?>
            · <?= e($d['pays']) ?></span>
          <span class="jrs"><?= (int)$d['jours'] ?> jours<?php
            if ($urg): ?> · délai dépassé<?php endif; ?></span>
        </div>
        <?php if ($qui): ?>
        <table class="mini"><tbody>
          <?php foreach ($qui as $a): ?>
          <tr>
            <td><?= e($a['personne']) ?></td>
            <td class="d">
              <form method="post" action="/dashboard.php?e=administration&amp;t=a1" class="inline">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="a1_etat">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <select name="etat" onchange="this.form.submit()">
                  <?php foreach ($ETATS_A1 as $k => $v): ?>
                    <option value="<?= $k ?>"<?= $a['etat'] === $k ? ' selected' : '' ?>><?= e($v) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody></table>
        <?php else: ?>
          <p class="sec pas">Personne n'est encore inscrit sur cette date.</p>
        <?php endif; ?>
        <form method="post" action="/dashboard.php?e=administration&amp;t=a1" class="ajout">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="a1_creer">
          <input type="hidden" name="booking_id" value="<?= (int)$d['id'] ?>">
          <input type="text" name="personne" placeholder="Nom de la personne détachée">
          <button type="submit">ajouter</button>
        </form>
      </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <style>
    .onglets{display:flex;gap:2px;padding:12px 26px 0;border-bottom:1px solid var(--trait)}
    .onglets a{padding:8px 15px;font-size:13.5px;text-decoration:none;
      border-bottom:3px solid transparent;color:var(--doux)}
    .onglets a.ici{color:var(--encre);border-bottom-color:var(--jaune);font-weight:600}
    .dbloc{border:1px solid var(--trait);border-radius:5px;padding:12px 16px;margin-bottom:14px;max-width:820px}
    .dbloc.urg{border-left:4px solid var(--orange)}
    .dtete{display:flex;gap:14px;align-items:baseline;flex-wrap:wrap;margin-bottom:8px}
    .dtete a{text-decoration:none}
    .jrs{margin-left:auto;font-size:12.5px;color:var(--doux)}
    .dbloc.urg .jrs{color:var(--orange);font-weight:600}
    table.mini{font-size:13.5px;margin:0 0 8px}
    table.mini td{padding:4px 0;border-bottom:1px solid var(--trait)}
    table.mini td.d{text-align:right;width:170px}
    form.inline{display:inline}
    form.ajout{display:flex;gap:8px;margin-top:6px}
    form.ajout input{flex:1;padding:6px 10px;font-size:13.5px;font-family:inherit;
      border:1px solid var(--trait);border-radius:4px;background:var(--papier);color:var(--encre)}
    form.ajout button{padding:6px 14px;font-size:13px}
    .pas{margin:4px 0 8px}
    </style>
    <?php dash_bas(); return;
}

// ═══════════════════════════════════════════════════════════════════════════
// ONGLET LE MOIS
// ═══════════════════════════════════════════════════════════════════════════

$taches = DB::all(
    "SELECT t.*, m.libelle AS m_libelle, m.categorie, m.jour_echeance, o.nom AS org
       FROM admin_tache t
       LEFT JOIN admin_modele m ON m.id = t.modele_id
       LEFT JOIN organisation o ON o.id = t.organisation_id
      WHERE t.periode = ?
      ORDER BY m.categorie, m.ordre, o.nom", [$periode]);

$parCat = [];
foreach ($taches as $t) $parCat[$t['categorie'] ?? 'autre'][] = $t;

$reste = count(array_filter($taches, fn($t) => $t['etat'] === 'a_faire'));
$fait  = count(array_filter($taches, fn($t) => $t['etat'] === 'fait'));

$mois = DB::all("SELECT periode, COUNT(*) n, SUM(etat='fait') f FROM admin_tache
                  GROUP BY periode ORDER BY periode DESC LIMIT 18");

dash_haut('administration', $taches
    ? count($taches) . ' obligations · ' . $fait . ' faites · ' . $reste . ' à faire'
    : 'aucune obligation générée pour ce mois');
?>
<div class="onglets">
  <a href="/dashboard.php?e=administration" class="ici">Le mois</a>
  <a href="/dashboard.php?e=administration&amp;t=a1">Attestations A1</a>
</div>

<form class="filtres" method="get" action="/dashboard.php">
  <input type="hidden" name="e" value="administration">
  <select name="m" onchange="this.form.submit()">
    <?php
    $vus = array_column($mois, 'periode');
    if (!in_array($periode, $vus, true)) array_unshift($mois, ['periode'=>$periode,'n'=>0,'f'=>0]);
    foreach ($mois as $x): ?>
      <option value="<?= e($x['periode']) ?>"<?= $periode === $x['periode'] ? ' selected' : '' ?>>
        <?= e($x['periode']) ?><?= $x['n'] ? ' · ' . $x['f'] . '/' . $x['n'] : ' · vide' ?></option>
    <?php endforeach; ?>
  </select>

</form>
<?php dash_flash_html(); ?>

<?php if (!$taches): ?>
  <div class="avis">
    <h2>Ce mois n'est pas encore généré</h2>
    <p>Les obligations viennent d'un modèle de seize lignes: impôt à la source par
       canton, AVS, DSN française, contrats, fiches de salaire, classement comptable.
       Elles sont créées pour chaque association active, et seulement celles qui la
       concernent: une obligation française ne s'ajoute pas à une association suisse.</p>
    <form method="post" action="/dashboard.php?e=administration&amp;m=<?= e($periode) ?>">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="generer">
      <button type="submit">Générer <?= e($periode) ?></button>
    </form>
  </div>
<?php else: ?>
  <div class="zone">
  <?php foreach ($parCat as $cat => $rows): ?>
    <h3 class="sect"><?= e($CATS[$cat] ?? $cat) ?>
      <span class="n"><?= count(array_filter($rows, fn($r) => $r['etat']==='fait')) ?>/<?= count($rows) ?></span></h3>
    <div class="tw"><table>
      <tbody>
      <?php foreach ($rows as $t):
          $enRetard = $t['echeance'] && $t['etat'] === 'a_faire' && $t['echeance'] < date('Y-m-d'); ?>
        <tr class="<?= $t['etat'] === 'fait' ? 'ok' : ($enRetard ? 'tard' : '') ?>">
          <td class="terr"><?php if ($t['territoire']): ?><span class="tg"><?= e($t['territoire']) ?></span><?php endif; ?></td>
          <td><?= e($t['m_libelle'] ?: $t['libelle'] ?: '') ?></td>
          <td class="sec"><?= e($t['org'] ?? '') ?></td>
          <td class="sec d"><?php if ($t['echeance']): ?><?= e(substr((string)$t['echeance'], 8, 2)) ?><?php endif; ?></td>
          <td class="d">
            <form method="post" action="/dashboard.php?e=administration&amp;m=<?= e($periode) ?>" class="inline">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="action" value="etat">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <select name="etat" onchange="this.form.submit()">
                <?php foreach ($ETATS as $k => $v): ?>
                  <option value="<?= $k ?>"<?= $t['etat'] === $k ? ' selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endforeach; ?>

  <form method="post" action="/dashboard.php?e=administration&amp;m=<?= e($periode) ?>" class="regen">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="generer">
    <button type="submit">Compléter ce mois</button>
    <span class="sec">Ajoute ce qui manque, ne touche pas à ce qui est déjà coché.</span>
  </form>
  </div>
<?php endif; ?>

<style>
.onglets{display:flex;gap:2px;padding:12px 26px 0;border-bottom:1px solid var(--trait)}
.onglets a{padding:8px 15px;font-size:13.5px;text-decoration:none;
  border-bottom:3px solid transparent;color:var(--doux)}
.onglets a.ici{color:var(--encre);border-bottom-color:var(--jaune);font-weight:600}
h3.sect{font-size:12.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--doux);
  margin:26px 0 6px;border-bottom:1px solid var(--trait);padding-bottom:5px}
h3.sect .n{float:right;font-weight:400;text-transform:none}
td.terr{width:44px}
.tg{font-size:10.5px;border:1px solid var(--trait);border-radius:3px;padding:1px 5px;color:var(--doux)}
td.d{text-align:right}
td.d select{font-size:12.5px;padding:3px 6px}
tr.ok td{opacity:.5}
tr.tard td:nth-child(2){color:var(--orange);font-weight:600}
form.inline{display:inline}
.regen{margin-top:30px;padding-top:16px;border-top:1px solid var(--trait);
  display:flex;gap:14px;align-items:center;flex-wrap:wrap}
.avis form{margin-top:12px}
</style>
<?php dash_bas(); ?>

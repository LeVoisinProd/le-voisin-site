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
    /* Le rôle décide aussi de l'écriture, et pas seulement de l'accès à
       l'écran: `production` lit les Finances sans les modifier. Le routeur
       ne peut pas le faire à notre place, lui ne voit pas les POST. */
    dash_exige_ecriture('administration');
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'generer') {
        /* IDEMPOTENT PAR LA CLEF UNIQUE (modele, periode, organisation). Relancer
           la génération d'un mois déjà généré n'ajoute rien et n'efface rien: ce
           qui est déjà coché le reste. C'est ce qui permet de la relancer après
           avoir ajouté une association, sans peur. */
        /* `gestion = 'diffusion'` EST EXCLU, et ce n'est pas un détail. Improvável
           Produções et Tainá E I O U sont des associations dont le Le Voisin vend
           les spectacles sans les administrer — Anna, 16.08.2026. Leur générer
           seize obligations par mois fabriquerait trente-deux lignes « à faire »
           que personne ne peut faire, parce qu'elles ne nous reviennent pas. Et
           une liste dont un cinquième est faux cesse d'être lue: la panne serait
           celle d'Alessandra, pas celle du logiciel. */
        $orgs = DB::all("SELECT id, pays, canton, canton_fiscal FROM organisation
                          WHERE genre = 'association' AND supprime_le IS NULL
                            AND statut = 'actif' AND gestion = 'complete'");
        $mods = DB::all("SELECT * FROM admin_modele WHERE actif = 1");
        $st = DB::pdo()->prepare(
            'INSERT IGNORE INTO admin_tache (modele_id, organisation_id, periode, territoire, echeance)
             VALUES (?,?,?,?,?)');
        $n = 0;
        /* `pays` est écrit tantôt « CH » tantôt « Suisse », tantôt « FR » tantôt
           « France », selon la source qui a rempli la fiche. On normalise ici
           plutôt que de nettoyer la colonne: la reprise depuis le dashboard doit
           rester sans perte, et deux graphies ne sont pas une erreur de saisie. */
        $pays = static function (?string $p): string {
            $p = strtoupper(trim((string)$p));
            if ($p === '' ) return 'CH';
            if (str_starts_with($p, 'FR')) return 'FR';
            if (str_starts_with($p, 'CH') || str_starts_with($p, 'SUISSE')) return 'CH';
            return substr($p, 0, 2);
        };

        $sansCanton = [];
        foreach ($orgs as $o) {
            $paysOrg   = $pays($o['pays'] ?? null);
            /* Le canton fiscal l'emporte quand il est renseigné: c'est celui où
               l'association déclare, et il ne coïncide pas toujours avec son
               siège. À défaut, le canton du siège. */
            $cantonOrg = strtoupper(trim((string)($o['canton_fiscal'] ?: $o['canton'] ?: '')));

            foreach ($mods as $m) {
                $t = strtoupper(trim((string)$m['territoire']));

                /* LE TERRITOIRE SE LIT À TROIS NIVEAUX, et les confondre était un
                   vrai défaut: la version précédente ne séparait que la France de
                   la Suisse, si bien qu'une association bernoise recevait CINQ
                   impôts à la source — Genève, Vaud, Berne, Valais et Zurich. On
                   ne remarque pas l'erreur en lisant le code; on la remarque le
                   mois où quelqu'un cherche pourquoi rien ne correspond. */
                if ($t === '') {
                    // sans territoire: tout le monde
                } elseif ($t === 'FR' || $t === 'CH') {
                    if ($paysOrg !== $t) continue;                 // le pays
                } else {
                    if ($paysOrg !== 'CH' || $cantonOrg !== $t) continue;   // le canton
                }

                $ech = $m['jour_echeance']
                     ? sprintf('%s-%02d', $periode, min(28, (int)$m['jour_echeance'])) : null;
                $st->execute([$m['id'], $o['id'], $periode, $m['territoire'], $ech]);
                $n += $st->rowCount();
            }

            /* UNE ASSOCIATION SUISSE SANS AUCUN MODÈLE CANTONAL EST UN TROU, PAS
               UNE ABSENCE D'OBLIGATION. CRILE est au Tessin et DieselReclame dans
               le Jura; ces deux cantons prélèvent l'impôt à la source comme les
               autres, seulement aucun modèle ne les couvre. Se taire ici ferait
               croire qu'il n'y a rien à déclarer. */
            if ($paysOrg === 'CH' && $cantonOrg !== ''
                && !array_filter($mods, fn($m) => strtoupper(trim((string)$m['territoire'])) === $cantonOrg)) {
                $sansCanton[$cantonOrg] = true;
            }
        }

        $avis = $n > 0 ? "$n obligation(s) ajoutée(s) pour $periode."
                       : "Rien à ajouter: le mois était déjà généré.";
        if ($sansCanton) {
            $avis .= ' Aucun modèle pour ' . implode(', ', array_keys($sansCanton))
                   . ' — les obligations cantonales de ces cantons ne sont donc pas suivies ici.';
        }
        dash_flash($avis);
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

/* Le rôle décide aussi de l'affichage: en lecture seule les cases se lisent
   mais ne se cliquent pas, plutôt que de proposer un bouton qui répondrait 403. */
$peutEcrire = dash_droit('administration', dash_role()) === 'ecrit';

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
  <?php /* ── UNE GRILLE, PAS UNE LISTE ───────────────────────────── [17.08.2026]
       Anna: « essa parte le mois com a lista nao ajuda a nada, ela é enorme e
       nao é legivel ». Elle avait raison et le compte le dit: 94 lignes
       empilées pour 13 associations et 8 obligations. Une liste de 94 lignes
       ne répond pas à la question qu'on se pose en ouvrant cet écran, qui
       n'est jamais « quelle est la 47e ligne » mais « QUI n'a pas encore fait
       QUOI ». Cette question-là est un croisement, et un croisement se lit
       dans un tableau à deux entrées, pas dans une colonne.

       LES CASES SE CLIQUENT, ET C'EST DÉJÀ SON VOCABULAIRE: la fiche
       d'association dit « cliquez sur une case pour changer le statut ». Un
       clic fait tourner l'état — à faire, en cours, fait, sans objet — au lieu
       d'ouvrir un menu déroulant, ce qui faisait trois gestes par ligne et 282
       gestes pour un mois.

       UNE CASE VIDE N'EST PAS UN OUBLI: elle veut dire que cette obligation ne
       concerne pas cette association. Une association bernoise n'a pas
       d'impôt à la source genevois, et le tiret le dit mieux qu'une absence. */ ?>

  <?php
  /* Les colonnes: les modèles réellement présents ce mois-ci, dans l'ordre du
     formulaire. On ne liste pas les seize modèles: ceux que personne n'a ce
     mois feraient des colonnes entièrement vides. */
  $cols = [];
  $cell = [];
  $orgs = [];
  foreach ($taches as $t) {
      $mid = (int)$t['modele_id'];
      $oid = (int)$t['organisation_id'];
      $cols[$mid] ??= ['libelle' => (string)($t['m_libelle'] ?: $t['libelle']),
                       'terr' => (string)$t['territoire'], 'cat' => (string)$t['categorie'],
                       'jour' => $t['jour_echeance']];
      $orgs[$oid] ??= (string)($t['org'] ?? '—');
      $cell[$oid][$mid] = $t;
  }
  asort($orgs, SORT_NATURAL | SORT_FLAG_CASE);

  /* Le suivant dans le cycle. « sans objet » revient à « à faire »: on doit
     pouvoir se déjuger d'un clic, comme on s'est jugé d'un clic. */
  $SUIVANT = ['a_faire'=>'en_cours', 'en_cours'=>'fait', 'fait'=>'sans_objet', 'sans_objet'=>'a_faire'];
  /* « À faire » porte un signe et non le vide. Une case vide et une case sans
     objet se ressemblent trop de loin, et c'est précisément la distinction qui
     compte: l'une attend un geste, l'autre non. Le cercle ouvert se ferme en
     coche, ce qui rend le cycle lisible sans la légende. */
  $SIGNE   = ['a_faire'=>'○', 'en_cours'=>'···', 'fait'=>'✓', 'sans_objet'=>'—'];
  $auj     = date('Y-m-d');
  ?>

  <div class="tw grille-adm">
  <table class="mat">
    <thead>
      <tr>
        <th class="coin">Association</th>
        <?php foreach ($cols as $mid => $c): ?>
          <th class="mc" title="<?= e($c['libelle']) ?><?= $c['jour'] ? ' — le ' . (int)$c['jour'] : '' ?>">
            <span class="lib"><?= e($c['libelle']) ?></span>
            <?php if ($c['terr']): ?><span class="tg"><?= e($c['terr']) ?></span><?php endif; ?>
            <?php if ($c['jour']): ?><span class="jr">le <?= (int)$c['jour'] ?></span><?php endif; ?>
          </th>
        <?php endforeach; ?>
        <th class="tot">reste</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($orgs as $oid => $nom): $reste_o = 0; ?>
      <tr>
        <th class="org"><a href="/dashboard.php?e=associations&amp;o=<?= (int)$oid ?>&amp;mod=1"><?= e($nom) ?></a></th>
        <?php foreach ($cols as $mid => $c):
            $t = $cell[$oid][$mid] ?? null;
            if (!$t): ?>
              <td class="c vide" title="ne concerne pas cette association">·</td>
            <?php continue; endif;
            $etat = (string)$t['etat'];
            if ($etat === 'a_faire') $reste_o++;
            $tard = $t['echeance'] && $etat === 'a_faire' && $t['echeance'] < $auj; ?>
          <td class="c e-<?= e($etat) ?><?= $tard ? ' tard' : '' ?>">
            <?php if ($peutEcrire): ?>
            <form method="post" action="/dashboard.php?e=administration&amp;m=<?= e($periode) ?>">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="action" value="etat">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <input type="hidden" name="etat" value="<?= $SUIVANT[$etat] ?>">
              <button type="submit" title="<?= e($ETATS[$etat] ?? $etat) ?><?= $tard ? ' — délai passé' : '' ?>"><?=
                $SIGNE[$etat] ?></button>
            </form>
            <?php else: ?><span title="<?= e($ETATS[$etat] ?? $etat) ?>"><?= $SIGNE[$etat] ?></span><?php endif; ?>
          </td>
        <?php endforeach; ?>
        <td class="tot<?= $reste_o ? '' : ' zero' ?>"><?= $reste_o ?: '✓' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <th class="org">reste</th>
        <?php foreach ($cols as $mid => $c):
            $n = 0;
            foreach ($orgs as $oid => $_) if ((($cell[$oid][$mid] ?? null)['etat'] ?? '') === 'a_faire') $n++; ?>
          <td class="tot<?= $n ? '' : ' zero' ?>"><?= $n ?: '✓' ?></td>
        <?php endforeach; ?>
        <td class="tot"><?= $reste ?></td>
      </tr>
    </tfoot>
  </table>
  </div>

  <p class="leg"><span class="k e-a_faire">○</span> à faire ·
     <span class="k e-en_cours">···</span> en cours ·
     <span class="k e-fait">✓</span> fait ·
     <span class="k e-sans_objet">—</span> sans objet ·
     <span class="k tard">○</span> délai passé ·
     <span class="k vide">·</span> ne concerne pas cette association.
     Un clic fait tourner l'état. Le nom de l'association ouvre sa fiche, où vivent
     les déclarations trimestrielles et les comptes cantonaux.</p>

  <div class="zone">
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
/* La matrice. Les colonnes sont étroites exprès: on y lit un signe, pas un
   texte, et c'est ce qui permet de tenir treize associations et huit
   obligations dans un écran. L'en-tête porte le nom en entier, tourné. */
.grille-adm{padding:0 26px}
table.mat{width:auto;border-collapse:separate;border-spacing:0}
table.mat th.coin{text-align:left;vertical-align:bottom;min-width:190px}
table.mat th.mc{width:62px;padding:6px 3px;vertical-align:bottom;background:var(--fond2);
  border-bottom:1px solid var(--trait)}
table.mat th.mc .lib{display:block;font-size:10px;line-height:1.25;font-weight:600;
  text-transform:none;letter-spacing:0;color:var(--encre);
  overflow-wrap:anywhere;hyphens:auto}
table.mat th.mc .tg{display:inline-block;margin-top:3px;font-size:9.5px;border:1px solid var(--trait);
  border-radius:3px;padding:0 4px;color:var(--doux);background:var(--papier)}
table.mat th.mc .jr{display:block;font-size:9.5px;color:var(--doux);font-weight:400}
table.mat th.org{text-align:left;font-weight:600;font-size:13px;white-space:nowrap;
  padding:5px 12px 5px 0;background:var(--papier);text-transform:none;letter-spacing:0}
table.mat th.org a{text-decoration:none}
table.mat th.org a:hover{text-decoration:underline}
table.mat td.c{padding:2px;text-align:center;border:1px solid var(--trait)}
table.mat td.c form{margin:0}
table.mat td.c button,table.mat td.c span{display:block;width:100%;min-height:26px;border:0;
  background:none;font-family:inherit;font-size:13px;cursor:pointer;color:inherit;padding:0}
table.mat td.c span{cursor:default}
table.mat td.c.e-fait{background:#e8f6ec;color:#1c6b32}
table.mat td.c.e-en_cours{background:#fff6d9}
table.mat td.c.e-sans_objet{background:var(--fond2);color:var(--doux)}
table.mat td.c.tard{background:var(--orange);color:#fff}
table.mat td.c.vide{border-color:transparent;color:var(--trait);cursor:default}
table.mat td.tot,table.mat th.tot{text-align:center;font-size:12px;color:var(--doux);
  font-variant-numeric:tabular-nums;padding:4px 8px}
table.mat td.tot.zero{color:#1c6b32}
table.mat tfoot td.tot{border-top:2px solid var(--trait);font-weight:600}
.leg{padding:12px 26px 0;font-size:12.5px;color:var(--doux);max-width:900px}
.leg .k{display:inline-block;min-width:20px;text-align:center;border:1px solid var(--trait);
  border-radius:3px;margin-right:2px}
.leg .k.e-fait{background:#e8f6ec;color:#1c6b32}
.leg .k.e-en_cours{background:#fff6d9}
.leg .k.e-sans_objet{background:var(--fond2)}
.leg .k.tard{background:var(--orange)}
.leg .k.vide{border-color:transparent}
.regen{margin-top:30px;padding-top:16px;border-top:1px solid var(--trait);
  display:flex;gap:14px;align-items:center;flex-wrap:wrap}
.avis form{margin-top:12px}
</style>
<?php dash_bas(); ?>

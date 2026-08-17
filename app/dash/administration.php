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

    /* ── QUI PART, SANS LE REDEMANDER ─────────────────────────── [17.08.2026]
       Anna: « na parte A1 tb fazer uma tabela por date de jeu, com nome da
       pessoa, asso, e o nome da pessoa détachée vai aparecer automaticamente
       assim que as infos do evento forem preenchidas ».

       LA SOURCE EST L'ÉQUIPE DU DEVIS, et c'est la bonne: c'est là qu'on
       écrit QUI VOYAGE — la distinction existe déjà, `suit_jeu` sépare ceux
       qui partent de l'administration qui reste. La distribution complète ne
       conviendrait pas: le Bestiarium en compte dix, dont le dramaturge et
       l'œil extérieur, qui ne montent dans aucun train.

       Le rapprochement passe par le titre du spectacle, comme partout
       ailleurs sur cet écran. Une date dont le titre ne correspond à aucune
       fiche ne propose personne, et le dit. */
    $equipeProj = [];   // titre normalisé => [['nom'=>…, 'role'=>…], …]
    $assoProj   = [];   // titre normalisé => nom de l'association
    $clef = static fn(string $x): string => mb_strtolower(trim($x));

    foreach (DB::all(
        "SELECT p.title_fr, p.title_en, pp.donnees, o.nom AS asso
           FROM projet_prod pp
           JOIN projects p ON p.id = pp.project_id
      LEFT JOIN organisation o ON o.id = pp.organisation_id") as $r) {
        $d = json_decode((string)$r['donnees'], true) ?: [];
        $gens = [];
        foreach ((array)($d['devis']['equipe'] ?? []) as $x) {
            /* L'administration ne part pas: `suit_jeu = 0` la désigne. */
            if ((string)($x['suit_jeu'] ?? '1') === '0') continue;
            $gens[] = ['nom' => trim((string)($x['nom'] ?? '')),
                       'role' => trim((string)($x['role'] ?? ''))];
        }
        foreach ([$r['title_fr'], $r['title_en']] as $t) {
            $t = trim((string)$t);
            if ($t === '') continue;
            if ($gens) $equipeProj[$clef($t)] ??= $gens;
            if ($r['asso']) $assoProj[$clef($t)] ??= (string)$r['asso'];
        }
    }

    $urgentes = 0;
    foreach ($dates as $d) {
        $faites = array_filter($parBooking[(int)$d['id']] ?? [],
                               fn($a) => in_array($a['etat'], ['recu','sans_objet'], true));
        if ((int)$d['jours'] <= A1_DELAI && count($faites) === 0) $urgentes++;
    }

    $peutEcrire = dash_droit('administration', dash_role()) === 'ecrit';

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
    <div class="tw"><table class="a1">
      <thead><tr>
        <th>Date</th><th class="d">J−</th><th>Lieu</th><th>Pays</th>
        <th>Spectacle</th><th>Association</th><th>Personne détachée</th><th>A1</th>
      </tr></thead>
      <tbody>
      <?php foreach ($dates as $d):
          $k     = $clef((string)$d['projet']);
          $qui   = $parBooking[(int)$d['id']] ?? [];
          $prop  = $equipeProj[$k] ?? [];
          $asso  = $assoProj[$k] ?? '';
          $urg   = (int)$d['jours'] <= A1_DELAI;
          /* Ce qui est déjà demandé passe devant; ce que l'équipe du devis
             propose et qu'on n'a pas encore demandé vient après, en gris. */
          $dejaNoms = array_map(fn($a) => mb_strtolower(trim((string)$a['personne'])), $qui);
          $reste = array_values(array_filter($prop,
              fn($x) => $x['nom'] !== '' && !in_array(mb_strtolower($x['nom']), $dejaNoms, true)));
          $lignes = max(1, count($qui) + count($reste));
          $i = 0;
      ?>
        <?php for ($n = 0; $n < $lignes; $n++):
            $a = $qui[$n] ?? null;
            $r = $a ? null : ($reste[$n - count($qui)] ?? null); ?>
        <tr class="<?= $urg && !$a ? 'urg' : '' ?><?= $n === 0 ? ' debut' : ' suite' ?>">
          <?php if ($n === 0): ?>
            <td rowspan="<?= $lignes ?>"><a href="/dashboard.php?e=bookings&amp;b=<?= (int)$d['id'] ?>"><?=
              e($d['date_texte'] ?: (string)$d['date_debut']) ?></a></td>
            <td rowspan="<?= $lignes ?>" class="d nb<?= $urg ? ' rouge' : '' ?>"><?= (int)$d['jours'] ?></td>
            <td rowspan="<?= $lignes ?>"><?= e((string)$d['venue']) ?>
              <span class="sec"><?= e((string)$d['ville']) ?></span></td>
            <td rowspan="<?= $lignes ?>"><?= e((string)$d['pays']) ?></td>
            <td rowspan="<?= $lignes ?>" class="sec"><?= e((string)$d['projet']) ?></td>
            <td rowspan="<?= $lignes ?>" class="sec"><?= $asso !== '' ? e($asso)
                : '<span class="manq">fiche de production sans association</span>' ?></td>
          <?php endif; ?>

          <td class="pers">
            <?php if ($a): ?>
              <?= e((string)$a['personne']) ?>
            <?php elseif ($r): ?>
              <span class="prop"><?= e($r['nom']) ?></span>
              <?php if ($r['role'] !== ''): ?><span class="sec"><?= e($r['role']) ?></span><?php endif; ?>
            <?php elseif (!$prop): ?>
              <span class="manq">personne n'est encore inscrit·e au devis de ce spectacle</span>
            <?php else: ?>
              <span class="manq">les lignes du devis n'ont pas de nom</span>
            <?php endif; ?>
          </td>

          <td class="etat">
            <?php if ($a): ?>
              <form method="post" action="/dashboard.php?e=administration&amp;t=a1" class="inline">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="a1_etat">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <select name="etat" onchange="this.form.submit()"<?= $peutEcrire ? '' : ' disabled' ?>>
                  <?php foreach ($ETATS_A1 as $kk => $vv): ?>
                    <option value="<?= $kk ?>"<?= $a['etat'] === $kk ? ' selected' : '' ?>><?= e($vv) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            <?php elseif ($r && $peutEcrire): ?>
              <form method="post" action="/dashboard.php?e=administration&amp;t=a1" class="inline">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="a1_creer">
                <input type="hidden" name="booking_id" value="<?= (int)$d['id'] ?>">
                <input type="hidden" name="personne" value="<?= e($r['nom']) ?>">
                <button type="submit" class="lien">inscrire</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endfor; ?>

        <?php if ($peutEcrire): ?>
        <tr class="ajout-l">
          <td colspan="6"></td>
          <td colspan="2">
            <form method="post" action="/dashboard.php?e=administration&amp;t=a1" class="ajout">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="action" value="a1_creer">
              <input type="hidden" name="booking_id" value="<?= (int)$d['id'] ?>">
              <input type="text" name="personne" placeholder="quelqu'un d'autre part sur cette date">
              <button type="submit">ajouter</button>
            </form>
          </td>
        </tr>
        <?php endif; ?>
      <?php endforeach; ?>
      </tbody>
    </table></div>

    <p class="leg">Les noms en gris viennent de l'<strong>équipe du devis</strong> du
       spectacle — ceux dont les jours suivent le jeu, donc ceux qui voyagent.
       « Inscrire » ouvre la demande. L'administration n'y figure pas: elle ne part pas.</p>
    <?php endif; ?>

    <style>
    .onglets{display:flex;gap:2px;padding:12px 26px 0;border-bottom:1px solid var(--trait)}
    .onglets a{padding:8px 15px;font-size:13.5px;text-decoration:none;
      border-bottom:3px solid transparent;color:var(--doux)}
    .onglets a.ici{color:var(--encre);border-bottom-color:var(--jaune);font-weight:600}
    table.a1 td{vertical-align:top}
    table.a1 tr.suite td{border-top:0}
    table.a1 tr.debut td{border-top:1px solid var(--trait)}
    table.a1 td.d{text-align:right}
    table.a1 td.rouge{color:var(--orange);font-weight:700}
    table.a1 td.pers .prop{color:var(--doux)}
    table.a1 td.pers .sec{margin-left:7px;font-size:12px}
    table.a1 .manq{color:var(--doux);font-size:12.5px;font-style:italic}
    table.a1 tr.urg td.pers{background:#fff4f0}
    table.a1 tr.ajout-l td{border-top:0;padding-top:0;padding-bottom:10px}
    table.a1 button.lien{background:none;border:0;color:var(--encre);text-decoration:underline;
      cursor:pointer;font-family:inherit;font-size:13px;padding:0}
    table.a1 select{font-size:12.5px;padding:3px 6px}
    .leg{padding:12px 26px 0;font-size:12.5px;color:var(--doux);max-width:860px}
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

  /* ── LES COLONNES SE GROUPENT PAR TERRITOIRE ─────────────────── [17.08.2026]
     Anna: « faltou marcar preparer les contrats du mois de le voisin fr ».
     Il ne manquait rien — la ligne du Voisin FR porte bien sa préparation de
     contrats, vérifié en base. Elle avait lu la colonne d'à côté.

     ET C'EST MA FAUTE, PAS LA SIENNE: quatre intitulés apparaissent DEUX FOIS
     dans ce tableau, une version suisse et une française — « Préparer les
     contrats du mois », « Fiches de salaire préparées », « Classement
     comptable du mois » — et seule une étiquette de neuf pixels les
     distinguait. Deux colonnes qui portent le même nom à trois centimètres
     l'une de l'autre ne se distinguent pas, elles se confondent.

     Une bande de territoire au-dessus les sépare. L'ordre suit celle-ci
     plutôt que la catégorie: on cherche « ce que doit une association
     française », pas « toutes les déclarations du mois toutes zones
     confondues ». */
  $NOM_TERR = ['CH'=>'Suisse — toutes', 'FR'=>'France', 'GE'=>'Genève', 'VD'=>'Vaud',
               'BE'=>'Berne', 'VS'=>'Valais', 'ZH'=>'Zurich', 'TI'=>'Tessin', 'JU'=>'Jura',
               ''=>'Toutes'];
  /* Les cantons d'abord — ils ne concernent qu'une association ou deux et se
     lisent vite — puis la Suisse entière, puis la France. */
  $rangTerr = static function (string $t): int {
      if ($t === '') return 3;
      if ($t === 'CH') return 1;
      if ($t === 'FR') return 2;
      return 0;                      // un canton
  };
  uasort($cols, static function ($a, $b) use ($rangTerr) {
      $ra = $rangTerr($a['terr']); $rb = $rangTerr($b['terr']);
      if ($ra !== $rb) return $ra <=> $rb;
      if ($a['terr'] !== $b['terr']) return strcmp($a['terr'], $b['terr']);
      return strcmp((string)$a['cat'] . $a['libelle'], (string)$b['cat'] . $b['libelle']);
  });
  /* Combien de colonnes par bande, dans l'ordre où elles sortent. */
  $bandes = [];
  foreach ($cols as $c) {
      $t = (string)$c['terr'];
      if ($bandes && array_key_last($bandes) === $t) { $bandes[$t]++; continue; }
      $bandes[$t] = ($bandes[$t] ?? 0) + 1;
  }

  /* Le suivant dans le cycle. « sans objet » revient à « à faire »: on doit
     pouvoir se déjuger d'un clic, comme on s'est jugé d'un clic. */
  $SUIVANT = ['a_faire'=>'en_cours', 'en_cours'=>'fait', 'fait'=>'sans_objet', 'sans_objet'=>'a_faire'];

  /* ── LE RETARD NE SE SIGNALE PAS AVANT LA MISE EN SERVICE ──── [17.08.2026]
     Anna: « nao precisa deixar nada como atrasado, ainda nao estamos com isso
     no ar ». Les obligations d'août ont été générées le 16 avec leurs jours
     d'échéance — 1, 5, 10, 15 — et le 17 la moitié du tableau passait en
     orange. Personne n'était en retard: personne ne s'en servait encore.

     UN SIGNAL QUI CRIE AVANT D'AVOIR RAISON NE SERA PLUS CRU QUAND IL AURA
     RAISON. C'est le même mécanisme que les trois P1 « en retard » du 10.08
     qui étaient déjà faites, et qui ont coûté une matinée.

     Le mécanisme reste, il attend une date. `admin_service_depuis` est vide
     tant qu'on n'a pas commencé; le jour où le suivi devient réel, on y pose
     la date et le retard se remet à parler — pour les échéances postérieures
     à elle, jamais pour ce qui la précède. */
  $enService = trim(Settings::get('admin_service_depuis'));
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
      <tr class="bandes">
        <th class="coin"></th>
        <?php foreach ($bandes as $t => $n): ?>
          <th class="bande t-<?= e($t ?: 'x') ?>" colspan="<?= (int)$n ?>"><?= e($NOM_TERR[$t] ?? $t) ?></th>
        <?php endforeach; ?>
        <th></th>
      </tr>
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
            $tard = $enService !== '' && $t['echeance'] && $etat === 'a_faire'
                    && $t['echeance'] < $auj && $t['echeance'] >= $enService; ?>
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
     <?php if ($enService !== ''): ?><span class="k tard">○</span> délai passé · <?php endif; ?>
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
/* Le coin haut-gauche croise les deux collants: il doit passer devant les
   deux, sinon un nom d'association lui glisse dessous en défilant. */
table.mat th.coin{text-align:left;vertical-align:bottom;min-width:190px;
  position:sticky;left:0;z-index:12;background:var(--fond2)}
table.mat th.mc{width:62px;padding:6px 3px;vertical-align:bottom;background:var(--fond2);
  border-bottom:1px solid var(--trait);z-index:10}
/* La bande de territoire. Elle porte un fond et un trait à gauche: sans l'un
   des deux, deux bandes voisines se lisent comme une seule. */
table.mat tr.bandes th.bande{font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;
  color:var(--doux);text-align:left;padding:3px 6px;background:var(--papier);
  border-left:2px solid var(--trait);border-bottom:1px solid var(--trait);white-space:nowrap}
table.mat tr.bandes th.coin{background:var(--papier);border:0}
table.mat th.mc .lib{display:block;font-size:10px;line-height:1.25;font-weight:600;
  text-transform:none;letter-spacing:0;color:var(--encre);
  overflow-wrap:anywhere;hyphens:auto}
table.mat th.mc .tg{display:inline-block;margin-top:3px;font-size:9.5px;border:1px solid var(--trait);
  border-radius:3px;padding:0 4px;color:var(--doux);background:var(--papier)}
table.mat th.mc .jr{display:block;font-size:9.5px;color:var(--doux);font-weight:400}
/* LES EN-TÊTES DE LIGNE NE COLLENT PAS. [17.08.2026]
   `_layout.php` pose `th{position:sticky;top:0}` pour que les noms de colonnes
   tiennent en haut d'un long tableau. Dans une matrice, les noms
   d'ASSOCIATION sont aussi des `th` — ils héritaient donc du collant vertical
   et les treize venaient s'empiler en haut, par-dessus l'en-tête des colonnes. C'est ce qu'Anna a vu: « a mise en page esta meio truncada »,
   CRILE et DieselReclame flottant au-dessus du tableau.

   Ils collent à GAUCHE à la place, ce qu'une matrice large demande vraiment: on
   fait défiler treize colonnes et l'on veut garder le nom de la ligne sous les
   yeux. `top:auto` annule l'héritage vertical sans toucher à la règle générale,
   qui reste juste pour tous les autres tableaux. */
table.mat th.org{text-align:left;font-weight:600;font-size:13px;white-space:nowrap;
  padding:5px 12px 5px 0;background:var(--papier);text-transform:none;letter-spacing:0;
  position:sticky;left:0;top:auto;z-index:6}
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
table.mat tfoot th.org{border-top:2px solid var(--trait)}
/* La matrice ne doit jamais pousser le corps de page de côté: c'est `.tw` qui
   défile, pas la fenêtre — sinon la barre de titre, collée en haut, part avec
   elle et se coupe à droite. */
.grille-adm{max-width:100%}
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

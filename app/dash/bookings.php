<?php
/**
 * Écran Bookings. [16.08.2026]
 *
 * Une date jouée, ou en cours de l'être. C'est l'objet central du dashboard, et
 * il n'existait nulle part comme donnée: `events` du CMS portait une chaîne
 * d'affichage sans cachet ni statut, `lv-tour` portait 35 lignes codées en dur
 * et EN LECTURE SEULE, sans aucun formulaire pour en créer une.
 *
 * DEUX VUES DANS UN SEUL FICHIER, choisies par ?b=<id>: la liste, et la fiche
 * avec ses cinq onglets. Elles partagent trop pour vivre séparées, et le
 * fichier reste lisible tant qu'il n'y a que ces deux-là.
 *
 * LES ONGLETS DE LA FICHE sont déclarés et vides pour l'instant. C'est le même
 * parti que le menu: montrer la carte plutôt que de la cacher. Chacun dit ce
 * qu'il portera et ce qui lui manque encore comme table.
 */
declare(strict_types=1);

const PAR_PAGE = 60;

/** Les onglets de la fiche, dans l'ordre demandé par Anna. */
const ONGLETS = [
    'apercu'    => 'Aperçu',
    'deal'      => 'Deal',
    'factures'  => 'Factures',
    'contrats'  => 'Contrats',
    'advancing' => 'Advancing',
    'voyage'    => 'Voyage',
];

$id = (int)($_GET['b'] ?? 0);

// ═══════════════════════════════════════════════════════════════════════════
// ENREGISTRER  (avant tout affichage: on redirige, on ne rend rien)
// ═══════════════════════════════════════════════════════════════════════════

$CHAMPS = ['projet','artiste','venue','venue_url','ville','pays','date_debut','date_fin',
           'date_texte','heure','prix_cession','prix_vente','devise','client','statut',
           'representations','notes_artiste','notes_internes'];
$STATUTS = ['pending' => 'en attente', 'option' => 'option',
            'confirmed' => 'confirmé', 'canceled' => 'annulé'];

$err = [];
$saisi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    /* Le rôle décide aussi de l'écriture, et pas seulement de l'accès à
       l'écran: `production` lit les Finances sans les modifier. Le routeur
       ne peut pas le faire à notre place, lui ne voit pas les POST. */
    dash_exige_ecriture('bookings');

    /* Les lignes de deal se saisissent depuis l'onglet Deal et n'ont rien à voir
       avec le formulaire du booking: on les traite ici et on repart. */
    $actD = (string)($_POST['deal'] ?? '');
    if ($actD !== '' && $id > 0) {
        if ($actD === 'ajouter') {
            $q  = (float)str_replace(',', '.', (string)($_POST['quantite'] ?? '1')) ?: 1;
            $pu = trim((string)($_POST['prix_unitaire'] ?? ''));
            $pu = $pu === '' ? null : (float)str_replace([',', ' ', "'"], ['.', '', ''], $pu);
            $mt = trim((string)($_POST['montant'] ?? ''));
            $mt = $mt === '' ? ($pu !== null ? round($q * $pu, 2) : null)
                             : (float)str_replace([',', ' ', "'"], ['.', '', ''], $mt);
            DB::pdo()->prepare(
              'INSERT INTO deal_item (booking_id,type,libelle,charge,quantite,prix_unitaire,montant,devise,ordre)
               VALUES (?,?,?,?,?,?,?,?,(SELECT COALESCE(MAX(o.ordre),0)+10 FROM deal_item o WHERE o.booking_id=?))')
              ->execute([$id,
                (string)($_POST['type'] ?? 'autre'), trim((string)($_POST['libelle'] ?? '')) ?: null,
                (string)($_POST['charge'] ?? 'incluse'), $q, $pu, $mt,
                (string)($_POST['devise'] ?? 'CHF'), $id]);
            dash_flash('Ligne ajoutée.');
        }
        if ($actD === 'supprimer') {
            DB::pdo()->prepare('DELETE FROM deal_item WHERE id = ? AND booking_id = ?')
                     ->execute([(int)($_POST['ligne'] ?? 0), $id]);
            dash_flash('Ligne supprimée.');
        }
        redirect('/dashboard.php?e=bookings&b=' . $id . '&o=deal');
    }

    foreach ($CHAMPS as $c) $saisi[$c] = trim((string)($_POST[$c] ?? ''));

    /* SUPPRESSION LOGIQUE, jamais un DELETE. Une date effacée par erreur se
       retrouve, et c'est déjà la règle du dashboard actuel. */
    if (($_POST['action'] ?? '') === 'supprimer' && $id > 0) {
        DB::pdo()->prepare('UPDATE booking SET supprime_le = NOW() WHERE id = ?')->execute([$id]);
        dash_flash('Booking supprimé. Il reste en base et peut être rétabli.');
        redirect('/dashboard.php?e=bookings');
    }

    // Ce qui est vraiment obligatoire, et rien de plus: sans lieu ni date, la
    // ligne ne veut rien dire et ne peut pas être retrouvée.
    if ($saisi['venue'] === '')      $err['venue'] = 'Le lieu est nécessaire pour retrouver la date.';
    if ($saisi['date_debut'] === '') $err['date_debut'] = 'Sans date, la ligne ne peut ni se trier ni se compter.';

    foreach (['date_debut', 'date_fin'] as $d) {
        if ($saisi[$d] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $saisi[$d])) {
            $err[$d] = 'Format attendu: AAAA-MM-JJ.';
        }
    }
    if ($saisi['date_debut'] !== '' && $saisi['date_fin'] !== ''
        && !isset($err['date_debut']) && !isset($err['date_fin'])
        && $saisi['date_fin'] < $saisi['date_debut']) {
        $err['date_fin'] = 'La fin est avant le début.';
    }
    foreach (['prix_cession', 'prix_vente'] as $p) {
        if ($saisi[$p] === '') continue;
        $saisi[$p] = str_replace([',', ' ', "\u{202f}"], ['.', '', ''], $saisi[$p]);
        if (!is_numeric($saisi[$p])) $err[$p] = 'Un montant, sans texte autour.';
    }
    if (!isset($STATUTS[$saisi['statut']])) $saisi['statut'] = 'pending';
    if ($saisi['devise'] === '') $saisi['devise'] = 'CHF';
    $saisi['representations'] = max(1, (int)($saisi['representations'] ?: 1));

    if (!$err) {
        $vals = [];
        foreach ($CHAMPS as $c) $vals[] = $saisi[$c] === '' ? null : $saisi[$c];
        if ($id > 0) {
            $set = implode(',', array_map(fn($c) => "$c=?", $CHAMPS));
            $vals[] = $id;
            DB::pdo()->prepare("UPDATE booking SET $set WHERE id = ?")->execute($vals);
            dash_flash('Booking enregistré.');
        } else {
            $q = implode(',', array_fill(0, count($CHAMPS), '?'));
            DB::pdo()->prepare('INSERT INTO booking (' . implode(',', $CHAMPS) . ") VALUES ($q)")
                     ->execute($vals);
            $id = (int)DB::pdo()->lastInsertId();
            dash_flash('Booking créé.');
        }
        /* Rediriger après un enregistrement réussi: sans cela, un
           rafraîchissement renvoie le POST et crée un doublon. */
        redirect('/dashboard.php?e=bookings&b=' . $id);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// LE FORMULAIRE
// ═══════════════════════════════════════════════════════════════════════════

if (isset($_GET['mod']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = $id > 0 ? DB::one('SELECT * FROM booking WHERE id = ? AND supprime_le IS NULL', [$id]) : [];
    if ($id > 0 && !$b) { dash_haut('bookings'); echo '<p class="vide">Ce booking n\'existe pas.</p>'; dash_bas(); return; }

    // Ce qui a été saisi prime sur ce qui est en base: un refus ne doit rien effacer.
    $v = fn(string $c) => $saisi[$c] ?? ($b[$c] ?? '');

    dash_haut('bookings', $id > 0 ? 'modifier' : 'nouveau');
    dash_form_style();
    if ($err) echo '<div class="flash err">Rien n\'a été enregistré: '
                 . count($err) . ' champ(s) à corriger. Ce que vous aviez saisi est conservé.</div>';
    ?>
    <div class="fil"><a href="/dashboard.php?e=bookings<?= $id > 0 ? '&amp;b=' . $id : '' ?>">← retour</a></div>
    <form class="saisie" method="post"
          action="/dashboard.php?e=bookings<?= $id > 0 ? '&amp;b=' . $id : '' ?>&amp;mod=1">
      <?= Auth::csrfField() ?>
      <div class="grille">
        <div class="titre-bloc">Quoi, qui, où</div>
        <?php
        ch('projet',  'Projet',  $v('projet'),  $err);
        ch('artiste', 'Artiste', $v('artiste'), $err);
        ch('venue',   'Lieu',    $v('venue'),   $err, ['requis' => true]);
        ch('ville',   'Ville',   $v('ville'),   $err);
        ch('pays',    'Pays',    $v('pays'),    $err);
        ch('client',  'Client',  $v('client'),  $err, ['aide' => 'Qui paie, si ce n\'est pas le lieu']);
        ch('venue_url', 'Site du lieu', $v('venue_url'), $err, ['large' => true]);

        echo '<div class="titre-bloc">Quand</div>';
        ch('date_debut', 'Début', $v('date_debut'), $err, ['type' => 'date', 'requis' => true]);
        ch('date_fin',   'Fin',   $v('date_fin'),   $err, ['type' => 'date',
            'aide' => 'Seulement si la série tient sur plusieurs jours']);
        ch('heure',      'Heure', substr((string)$v('heure'), 0, 5), $err, ['type' => 'time']);
        ch('representations', 'Représentations', $v('representations') ?: 1, $err,
           ['type' => 'number', 'aide' => 'Deux le même jour valent 1,5 jour de salaire, pas 2']);
        ch('date_texte', 'Date affichée', $v('date_texte'), $err, ['large' => true,
            'aide' => 'Ce que lit le public. « du 8 au 13 février » ne se dérive pas de deux dates',
            'placeholder' => '12, 13, 14 décembre 2026']);

        echo '<div class="titre-bloc">Combien</div>';
        ch('prix_cession', 'Prix de cession', $v('prix_cession'), $err,
           ['aide' => 'Ce que le lieu paie']);
        ch('prix_vente',   'Prix de vente',   $v('prix_vente'), $err,
           ['aide' => 'Ce qui est annoncé ou négocié']);
        ch('devise', 'Devise', $v('devise') ?: 'CHF', $err,
           ['type' => 'select', 'choix' => ['CHF' => 'CHF', 'EUR' => 'EUR']]);
        ch('statut', 'Statut', $v('statut') ?: 'pending', $err,
           ['type' => 'select', 'choix' => $STATUTS]);

        echo '<div class="titre-bloc">Notes</div>';
        ch('notes_artiste',  'Notes artiste',  $v('notes_artiste'),  $err,
           ['type' => 'textarea', 'large' => true, 'aide' => 'L\'artiste les voit']);
        ch('notes_internes', 'Notes internes', $v('notes_internes'), $err,
           ['type' => 'textarea', 'large' => true, 'aide' => 'L\'équipe seulement, jamais partagées']);
        ?>
      </div>
      <div class="actions">
        <button type="submit"><?= $id > 0 ? 'Enregistrer' : 'Créer' ?></button>
        <a class="sec2" href="/dashboard.php?e=bookings<?= $id > 0 ? '&amp;b=' . $id : '' ?>">annuler</a>
        <?php if ($id > 0): ?>
          <a class="sup" href="#"
             onclick="if(confirm('Supprimer ce booking ? Il restera en base et pourra être rétabli.')){
                        var f=document.createElement('form');f.method='post';
                        f.action='/dashboard.php?e=bookings&b=<?= $id ?>&mod=1';
                        f.innerHTML='<?= addslashes(Auth::csrfField()) ?><input name=action value=supprimer>';
                        document.body.appendChild(f);f.submit();}return false;">supprimer</a>
        <?php endif; ?>
      </div>
    </form>
    <style>.fil { padding:12px 26px 0; font-size:13px; }
           .fil a { color:var(--doux); text-decoration:none; }</style>
    <?php
    dash_bas();
    return;
}

// ═══════════════════════════════════════════════════════════════════════════
// LA FICHE
// ═══════════════════════════════════════════════════════════════════════════

if ($id > 0) {
    $b = DB::one('SELECT * FROM booking WHERE id = ? AND supprime_le IS NULL', [$id]);
    if (!$b) { dash_haut('bookings'); echo '<p class="vide">Ce booking n\'existe pas.</p>'; dash_bas(); return; }

    $ong = (string)($_GET['o'] ?? 'apercu');
    if (!isset(ONGLETS[$ong])) $ong = 'apercu';

    $titre = trim(($b['projet'] ?? '') . ' · ' . ($b['venue'] ?? ''));
    dash_haut('bookings', e($b['date_texte'] ?: (string)$b['date_debut']) . ' · ' . e($b['ville'] ?? ''));
    ?>
    <div class="fil"><a href="/dashboard.php?e=bookings">← tous les bookings</a>
      <a class="mod" href="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;mod=1">modifier</a></div>
    <?php dash_flash_html(); ?>

    <div class="onglets">
      <?php foreach (ONGLETS as $c => $lib): ?>
        <a href="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=<?= $c ?>"
           class="<?= $c === $ong ? 'ici' : '' ?>"><?= e($lib) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="zone">
    <?php if ($ong === 'apercu'): ?>
      <h2 class="gros"><?= e($titre) ?></h2>
      <div class="fiche">
        <?php
        $st = ['option' => 'option', 'confirmed' => 'confirmé', 'canceled' => 'annulé', 'pending' => 'en attente'];
        $l = function (string $k, $v, string $note = '') {
            if ($v === null || $v === '') return;
            printf('<div class="l"><span class="k">%s</span><span class="v">%s%s</span></div>',
                   e($k), e((string)$v), $note ? '<span class="n">' . e($note) . '</span>' : '');
        };
        $l('Statut', $st[$b['statut']] ?? $b['statut']);
        $l('Date', $b['date_texte'] ?: $b['date_debut']);
        if ($b['heure']) $l('Heure', substr((string)$b['heure'], 0, 5));
        $l('Représentations', $b['representations']);
        $l('Projet', $b['projet']);
        $l('Artiste', $b['artiste']);
        $l('Lieu', $b['venue']);
        $l('Ville', trim(($b['ville'] ?? '') . ' ' . ($b['pays'] ? '· ' . $b['pays'] : '')));
        $l('Client', $b['client']);
        if ($b['prix_cession'] !== null)
            $l('Prix de cession', number_format((float)$b['prix_cession'], 2, ',', ' ') . ' ' . $b['devise']);
        if ($b['prix_vente'] !== null)
            $l('Prix de vente', number_format((float)$b['prix_vente'], 2, ',', ' ') . ' ' . $b['devise']);
        $l('Provenance', $b['source'] . ' · ' . $b['source_ref'],
           $b['source'] === 'events' ? 'agenda du site' : 'lv-tour du dashboard');
        ?>
      </div>

      <?php /* Les deux natures de notes, et la distinction est le point:
               l'une part avec l'artiste, l'autre jamais. Une seule colonne
               obligerait à se relire avant chaque partage. */ ?>
      <div class="notes">
        <div class="bloc">
          <h3>Notes artiste <span class="n">visibles par l'artiste</span></h3>
          <p><?= $b['notes_artiste'] ? nl2br(e($b['notes_artiste'])) : '<span class="n">rien</span>' ?></p>
        </div>
        <div class="bloc int">
          <h3>Notes internes <span class="n">l'équipe seulement</span></h3>
          <p><?= $b['notes_internes'] ? nl2br(e($b['notes_internes'])) : '<span class="n">rien</span>' ?></p>
        </div>
      </div>

    <?php elseif ($ong === 'deal'): ?>
      <?php
      $lignes = DB::all('SELECT * FROM deal_item WHERE booking_id = ? ORDER BY ordre, id', [$id]);
      $TY = ['cachet'=>'cachet','frais_booking'=>'frais de booking','voyage'=>'voyage',
             'hebergement'=>'hébergement','per_diem'=>'per diem','droits'=>'droits',
             'materiel'=>'matériel','catering'=>'catering','marge'=>'marge','autre'=>'autre'];
      $CG = ['incluse'=>'incluse dans la cession','lieu'=>'à la charge du lieu','nous'=>'à notre charge'];
      $tot = ['incluse'=>0.0, 'lieu'=>0.0, 'nous'=>0.0];
      foreach ($lignes as $l) $tot[$l['charge']] += (float)$l['montant'];
      ?>
      <?php if ($lignes): ?>
      <div class="tw"><table>
        <thead><tr><th>Nature</th><th>Libellé</th><th>Charge</th><th class="d">Qté</th>
          <th class="d">Prix</th><th class="d">Montant</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lignes as $l): ?>
          <tr class="c-<?= e($l['charge']) ?>">
            <td><?= e($TY[$l['type']] ?? $l['type']) ?></td>
            <td class="sec"><?= e($l['libelle'] ?? '') ?></td>
            <td class="sec"><?= e($CG[$l['charge']]) ?></td>
            <td class="d sec"><?= rtrim(rtrim(number_format((float)$l['quantite'],2,',',' '),'0'),',') ?></td>
            <td class="d sec"><?= $l['prix_unitaire'] !== null
                ? number_format((float)$l['prix_unitaire'],2,',',' ') : '' ?></td>
            <td class="d"><?= $l['montant'] !== null
                ? number_format((float)$l['montant'],2,',',' ') . ' ' . e($l['devise']) : '' ?></td>
            <td class="d">
              <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=deal" class="inline"
                    onsubmit="return confirm('Supprimer cette ligne ?')">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="deal" value="supprimer">
                <input type="hidden" name="ligne" value="<?= (int)$l['id'] ?>">
                <button type="submit" class="x">×</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <?php foreach ($CG as $k => $lib): if (!$tot[$k]) continue; ?>
          <tr class="tot c-<?= $k ?>"><td colspan="5"><?= e(ucfirst($lib)) ?></td>
            <td class="d"><strong><?= number_format($tot[$k],2,',',' ') ?></strong></td><td></td></tr>
          <?php endforeach; ?>
        </tfoot>
      </table></div>

      <?php /* Le rapprochement avec le prix annoncé. Un écart n'est pas une
               erreur en soi: il dit seulement que les deux ne se sont pas encore
               parlé, et c'est exactement ce qu'on veut voir. */ ?>
      <?php if ($b['prix_cession'] !== null): ?>
        <?php $ecart = (float)$b['prix_cession'] - $tot['incluse']; ?>
        <div class="rap <?= abs($ecart) > 0.5 ? 'ecart' : 'ok' ?>">
          Prix de cession annoncé <strong><?= number_format((float)$b['prix_cession'],2,',',' ') ?>
          <?= e($b['devise']) ?></strong>, somme des lignes incluses
          <strong><?= number_format($tot['incluse'],2,',',' ') ?></strong>.
          <?php if (abs($ecart) > 0.5): ?>
            Écart de <strong><?= number_format($ecart,2,',',' ') ?></strong>.
            Ce n'est pas forcément une erreur: les deux ne se sont pas encore parlé.
          <?php else: ?>Les deux concordent.<?php endif; ?>
        </div>
      <?php endif; ?>
      <?php else: ?>
        <p class="sec">Aucune ligne. Le prix de ce booking n'est pour l'instant qu'un
           nombre: ajouter les lignes dit ce qu'il contient.</p>
      <?php endif; ?>

      <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=deal" class="ajl">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="deal" value="ajouter">
        <select name="type"><?php foreach ($TY as $k=>$v): ?>
          <option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
        <input type="text" name="libelle" placeholder="Libellé">
        <select name="charge"><?php foreach ($CG as $k=>$v): ?>
          <option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
        <input type="text" name="quantite" value="1" size="3" title="Quantité">
        <input type="text" name="prix_unitaire" placeholder="Prix unitaire" size="10">
        <input type="text" name="montant" placeholder="ou montant" size="10">
        <select name="devise"><option>CHF</option><option>EUR</option></select>
        <button type="submit">ajouter</button>
      </form>
      <p class="sec pt">Laisser le montant vide le calcule depuis la quantité et le prix.
         Le remplir directement permet un forfait négocié, sans inventer un prix unitaire.</p>

      <style>
      td.d,th.d{text-align:right;white-space:nowrap}
      tr.c-lieu td,tr.c-nous td{opacity:.72}
      tr.tot td{border-top:2px solid var(--encre);background:var(--fond2)}
      tr.tot.c-lieu td,tr.tot.c-nous td{border-top-width:1px;background:transparent}
      button.x{background:none;color:var(--doux);padding:0 6px;font-size:16px;cursor:pointer}
      form.inline{display:inline}
      form.ajl{display:flex;gap:7px;flex-wrap:wrap;align-items:center;margin-top:18px;
        padding-top:16px;border-top:1px solid var(--trait)}
      form.ajl input,form.ajl select{padding:6px 9px;font-size:13.5px;font-family:inherit;
        border:1px solid var(--trait);border-radius:4px;background:var(--papier);color:var(--encre)}
      form.ajl input[name=libelle]{flex:1;min-width:140px}
      form.ajl button{padding:6px 15px;font-size:13px}
      .rap{margin-top:16px;padding:11px 15px;background:var(--fond2);font-size:13.5px;
        border-left:4px solid var(--jaune);max-width:76ch}
      .rap.ecart{border-left-color:var(--orange)}
      .pt{margin-top:8px;font-size:12.5px;max-width:70ch}
      </style>

    <?php else: ?>
      <?php
      /* Chaque onglet dit ce qu'il portera ET ce qui lui manque comme table.
         Un « bientôt » ne apprend rien; ceci apprend où en est la reprise. */
      $quoi = [
        'factures'  => ['Générer et télécharger les factures de ce booking.',
                        'Demande la table `invoice` et la liaison bexio par API. Le client bexio actuel vit dans Apps Script: le porter en PHP est chiffré entre 12 h et 20 h pour le seul OAuth2.'],
        'contrats'  => ['Contrats, avec signature en ligne.',
                        'Le site sait déjà signer: `app/lib/Skribble.php` fonctionne et l\'espace collaborateur s\'en sert. Il manque la table `contract` et le lien vers ce booking.'],
        'advancing' => ['Fiches techniques, accueil et logistique du show.',
                        'C\'est la mécanique la plus intéressante d\'artistu: un formulaire construit champ par champ, envoyé au lieu, avec un état par champ (demandé, reçu, accepté) et un portail où le lieu répond. Rien d\'équivalent n\'existe ici.'],
        'voyage'    => ['Vols, transferts, hôtels.',
                        'Demande une table `logistique` rattachée au booking. Aujourd\'hui ces informations sont des catégories de documents dans l\'espace collaborateur: des fichiers, pas des données.'],
      ][$ong];
      ?>
      <div class="avis">
        <h2><?= e(ONGLETS[$ong]) ?></h2>
        <p><?= e($quoi[0]) ?></p>
        <p><?= e($quoi[1]) ?></p>
      </div>
    <?php endif; ?>
    </div>

    <style>
    .fil { padding:12px 26px 0; font-size:13px; display:flex; gap:16px; }
    .fil a { color:var(--doux); text-decoration:none; }
    .fil a.mod { margin-left:auto; color:var(--encre); font-weight:600; }
    .onglets { display:flex; gap:2px; padding:12px 26px 0; border-bottom:1px solid var(--trait);
               overflow-x:auto; }
    .onglets a { padding:8px 15px; font-size:13.5px; text-decoration:none; white-space:nowrap;
               border-bottom:3px solid transparent; color:var(--doux); }
    .onglets a.ici { color:var(--encre); border-bottom-color:var(--jaune); font-weight:600; }
    h2.gros { font-size:20px; margin:0 0 18px; }
    .fiche { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
             gap:0 32px; max-width:900px; }
    .fiche .l { display:flex; gap:12px; padding:7px 0; border-bottom:1px solid var(--trait); }
    .fiche .k { color:var(--doux); font-size:12.5px; min-width:120px; }
    .fiche .v { font-size:14px; }
    .fiche .n, .notes .n { color:var(--doux); font-size:12px; font-weight:400; margin-left:8px; }
    .notes { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
             gap:18px; margin-top:26px; max-width:900px; }
    .notes .bloc { padding:14px 18px; background:var(--fond2); border-left:4px solid var(--jaune); }
    .notes .bloc.int { border-left-color:var(--orange); }
    .notes h3 { font-size:13.5px; margin:0 0 8px; }
    .notes p { margin:0; font-size:14px; }
    </style>
    <?php
    dash_bas();
    return;
}

// ═══════════════════════════════════════════════════════════════════════════
// LA LISTE
// ═══════════════════════════════════════════════════════════════════════════

$q      = trim((string)($_GET['q'] ?? ''));
$statut = trim((string)($_GET['s'] ?? ''));
$annee  = trim((string)($_GET['a'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));

$where = ['supprime_le IS NULL'];
$args  = [];
if ($statut !== '' && isset(['option'=>1,'confirmed'=>1,'canceled'=>1,'pending'=>1][$statut])) {
    $where[] = 'statut = ?'; $args[] = $statut;
}
if ($annee !== '' && ctype_digit($annee)) { $where[] = 'YEAR(date_debut) = ?'; $args[] = (int)$annee; }
if ($q !== '') {
    /* Peu de lignes ici, quatre-vingt-six aujourd'hui et quelques centaines à
       terme: un LIKE suffit et évite d'ajouter un index FULLTEXT qu'il faudrait
       entretenir pour rien. */
    $like = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
    $where[] = '(venue LIKE ? OR projet LIKE ? OR artiste LIKE ? OR ville LIKE ? OR client LIKE ?)';
    array_push($args, $like, $like, $like, $like, $like);
}
$sqlWhere = implode(' AND ', $where);

$t0 = microtime(true);
$st = DB::pdo()->prepare("SELECT COUNT(*) FROM booking WHERE $sqlWhere");
$st->execute($args);
$total  = (int)$st->fetchColumn();
$pages  = max(1, (int)ceil($total / PAR_PAGE));
$page   = min($page, $pages);

$st = DB::pdo()->prepare("SELECT * FROM booking WHERE $sqlWhere
                          ORDER BY date_debut DESC, id DESC
                          LIMIT " . PAR_PAGE . " OFFSET " . (($page - 1) * PAR_PAGE));
$st->execute($args);
$lignes = $st->fetchAll();
$ms = (int)round((microtime(true) - $t0) * 1000);

$annees = DB::pdo()->query("SELECT YEAR(date_debut) a, COUNT(*) n FROM booking
                             WHERE supprime_le IS NULL AND date_debut IS NOT NULL
                             GROUP BY a ORDER BY a DESC")->fetchAll();
$parStatut = DB::pdo()->query("SELECT statut, COUNT(*) n FROM booking
                                WHERE supprime_le IS NULL GROUP BY statut")->fetchAll(PDO::FETCH_KEY_PAIR);

/* Les dates présentes dans les DEUX sources. C'est la double saisie qu'Anna
   décrit: aujourd'hui la même date s'écrit à la main dans le CMS et dans le
   dashboard. On la compte et on la montre, sans fusionner: choisir laquelle
   gagne demande de lire les deux. */
$doublons = (int)DB::pdo()->query(
    "SELECT COUNT(*) FROM (SELECT date_debut, ville FROM booking
       WHERE supprime_le IS NULL AND date_debut IS NOT NULL
       GROUP BY date_debut, ville HAVING COUNT(DISTINCT source) > 1) x")->fetchColumn();

$lien = function (array $chg) use ($q, $statut, $annee, $page): string {
    $p = array_merge(['e'=>'bookings','q'=>$q,'s'=>$statut,'a'=>$annee,'page'=>$page], $chg);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 1);
    return '/dashboard.php?' . http_build_query($p);
};

$ETIQ = ['option'=>'option','confirmed'=>'confirmé','canceled'=>'annulé','pending'=>'en attente'];

dash_haut('bookings', number_format($total,0,',',' ') . ' booking' . ($total>1?'s':'') . ' · ' . $ms . ' ms');
?>

<form class="filtres" method="get" action="/dashboard.php">
  <input type="hidden" name="e" value="bookings">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Lieu, projet, artiste, ville, client">
  <select name="s">
    <option value="">Tous les statuts</option>
    <?php foreach ($ETIQ as $k => $v): ?>
      <option value="<?= $k ?>"<?= $statut === $k ? ' selected' : '' ?>><?= e($v) ?> (<?= $parStatut[$k] ?? 0 ?>)</option>
    <?php endforeach; ?>
  </select>
  <select name="a">
    <option value="">Toutes les années</option>
    <?php foreach ($annees as $x): ?>
      <option value="<?= $x['a'] ?>"<?= $annee === (string)$x['a'] ? ' selected' : '' ?>><?= $x['a'] ?> (<?= $x['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Chercher</button>
  <?php if ($q !== '' || $statut !== '' || $annee !== ''): ?>
    <a class="vider" href="/dashboard.php?e=bookings">tout effacer</a>
  <?php endif; ?>
  <a class="neuf" href="/dashboard.php?e=bookings&amp;mod=1">+ nouveau booking</a>
</form>
<?php dash_flash_html(); ?>

<?php if ($doublons > 0): ?>
<div class="alerte">
  <strong><?= $doublons ?> dates existent dans les deux sources</strong>, l'agenda du site et
  lv-tour du dashboard. C'est la double saisie: la même date écrite à la main des deux côtés.
  Elles sont laissées telles quelles, parce que choisir laquelle gagne demande de les lire.
</div>
<?php endif; ?>

<?php if (!$lignes): ?>
  <p class="vide">Aucun booking ne correspond.</p>
<?php else: ?>
<div class="tw">
<table>
  <thead><tr>
    <th>Date</th><th>Projet</th><th>Artiste</th><th>Lieu</th><th>Ville</th>
    <th>Statut</th><th class="d">Cession</th><th class="d">Vente</th><th>Client</th>
  </tr></thead>
  <tbody>
  <?php foreach ($lignes as $r): ?>
    <tr>
      <td><a href="/dashboard.php?e=bookings&amp;b=<?= (int)$r['id'] ?>"><?=
        e($r['date_texte'] ?: (string)$r['date_debut']) ?></a>
        <?php if ($r['heure']): ?><div class="sec"><?= substr((string)$r['heure'],0,5) ?></div><?php endif; ?></td>
      <td><?= e($r['projet'] ?? '') ?></td>
      <td class="sec"><?= e($r['artiste'] ?? '') ?></td>
      <td><?= e($r['venue'] ?? '') ?></td>
      <td><?= e($r['ville'] ?? '') ?><?php if ($r['pays']): ?>
        <div class="sec"><?= e($r['pays']) ?></div><?php endif; ?></td>
      <td><span class="et <?= e($r['statut']) ?>"><?= e($ETIQ[$r['statut']] ?? $r['statut']) ?></span></td>
      <td class="d"><?= $r['prix_cession'] !== null
            ? number_format((float)$r['prix_cession'],0,',',' ') . ' ' . e($r['devise']) : '' ?></td>
      <td class="d"><?= $r['prix_vente'] !== null
            ? number_format((float)$r['prix_vente'],0,',',' ') . ' ' . e($r['devise']) : '' ?></td>
      <td class="sec"><?= e($r['client'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<nav class="pages">
  <?php if ($page > 1): ?><a href="<?= e($lien(['page'=>$page-1])) ?>">précédent</a><?php endif; ?>
  <span class="mut">page <?= $page ?> sur <?= $pages ?></span>
  <?php if ($page < $pages): ?><a href="<?= e($lien(['page'=>$page+1])) ?>">suivant</a><?php endif; ?>
</nav>
<?php endif; ?>

<style>
td.d, th.d { text-align:right; white-space:nowrap; }
.et { font-size:11.5px; padding:2px 8px; border-radius:10px; border:1px solid var(--trait);
      white-space:nowrap; }
.et.confirmed { background:#e7f6ea; border-color:#bfe3c8; color:#1c5c2e; }
.et.option    { background:#fff6d9; border-color:#f0dfa3; color:#6b5312; }
.et.pending   { background:var(--fond2); }
.et.canceled  { background:#fbe9e7; border-color:#f0c3bb; color:#7a2b1e; }
@media (prefers-color-scheme: dark) { :root:not([data-theme=light]) .et.confirmed,
  :root:not([data-theme=light]) .et.option, :root:not([data-theme=light]) .et.canceled {
  background:transparent; color:inherit; } }
.neuf { margin-left:auto; padding:8px 16px; background:var(--jaune); color:#0d0d0d;
        border-radius:4px; text-decoration:none; font-size:13.5px; font-weight:600; }
.alerte { margin:16px 26px 0; padding:12px 16px; background:var(--fond2);
          border-left:4px solid var(--orange); font-size:13.5px; max-width:80ch; }
</style>

<?php dash_bas(); ?>

<?php
/**
 * La fiche d'une personne. [17.08.2026]
 *
 * Inclus par `personnel.php` quand ?p=<id> ou ?neuf=1. Lecture et saisie sur
 * le même écran: une fiche de personnel se remplit par petits morceaux, au fil
 * de ce qui arrive — le permis en mars, l'IBAN en avril — et un aller-retour
 * vers un formulaire séparé pour changer une ligne se fait une fois puis plus.
 *
 * L'AVS ET L'IBAN NE SONT PAS RÉAFFICHÉS EN CLAIR DANS LE FORMULAIRE, et c'est
 * délibéré. Deux raisons, la seconde plus importante que la première:
 *
 *   1. Une page ouverte sur un bureau ne doit pas porter 40 IBAN à la vue.
 *   2. Un champ pré-rempli avec la valeur déchiffrée serait RENVOYÉ à chaque
 *      enregistrement, donc rechiffré à chaque fois. Ça marche, mais ça veut
 *      dire que la valeur fait l'aller-retour par le navigateur à chaque
 *      correction d'une virgule dans les notes. Elle reste où elle est.
 *
 * On voit donc s'ils sont renseignés, et on les révèle un par un, à la
 * demande. Saisir écrase; laisser vide ne touche à rien.
 */
declare(strict_types=1);

$neuf = $pid === 0;
$p    = $neuf ? [] : DB::one("SELECT * FROM rh_employe WHERE id = ? AND supprime_le IS NULL", [$pid]);

if (!$neuf && !$p) {
    dash_haut('personnel');
    echo '<p class="vide">Cette fiche n\'existe pas.</p>';
    dash_bas();
    return;
}

/* Révéler une valeur chiffrée: un aller simple, une valeur à la fois, et
   seulement sur demande explicite. Le paramètre est dans l'URL donc il ne
   survit pas à un rechargement de la page. */
$revele = (string)($_GET['voir'] ?? '');
$clair  = ['avs' => null, 'iban' => null];
/* `array_key_exists` ET NON `isset`: les deux clefs valent null au départ, et
   `isset(null)` est faux. Avec `isset`, « montrer » ne montrait jamais rien et
   la page se rechargeait à l'identique — une panne muette, celle qui coûte le
   plus cher à trouver parce qu'elle ressemble à un lien mort. */
if (!$neuf && array_key_exists($revele, $clair) && trim((string)$p[$revele]) !== '') {
    $clair[$revele] = Crypto::dechiffrer((string)$p[$revele]) ?: '(illisible avec la clef actuelle)';
}

$orgsF = ['' => '—'];
foreach ($orgs ?? DB::all("SELECT id, nom FROM organisation
                            WHERE supprime_le IS NULL AND genre = 'association' ORDER BY nom") as $o)
    $orgsF[(int)$o['id']] = (string)$o['nom'];

$engs = $neuf ? [] : DB::all(
    "SELECT * FROM rh_engagement WHERE supprime_le IS NULL AND employe_id = ?
      ORDER BY debut DESC", [$pid]);

$nom = trim(($p['prenom'] ?? '') . ' ' . ($p['nom'] ?? ''));
dash_haut('personnel', $neuf ? 'nouvelle personne' : e($nom));
dash_form_style();
dash_flash_html();
?>

<form class="saisie" method="post" action="<?= e(url('/dashboard.php?e=personnel')) ?>">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="action" value="emp">
  <input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>">

  <div class="grille">
    <h2 class="titre-bloc">Identité</h2>
    <?php
    ch('prenom',   'Prénom',            $p['prenom']   ?? '');
    ch('nom',      'Nom',               $p['nom']      ?? '');
    ch('pronom',   'Pronom',            $p['pronom']   ?? '', [], ['aide' => 'elle, il, iel']);
    ch('naissance','Date de naissance', $p['naissance'] ?? '', [], ['type' => 'date']);
    ch('nationalite','Nationalité',     $p['nationalite'] ?? '');
    ch('permis',   'Permis de séjour',  $p['permis']   ?? '', [], ['aide' => 'B, C, G, L — vide si suisse ou UE résident']);
    ?>

    <h2 class="titre-bloc">Contact</h2>
    <?php
    ch('email',     'Courriel',   $p['email']     ?? '', [], ['type' => 'email']);
    ch('telephone', 'Téléphone',  $p['telephone'] ?? '');
    ch('rue',       'Rue',        $p['rue']       ?? '', [], ['large' => true]);
    ch('cp',        'Code postal',$p['cp']        ?? '');
    ch('ville',     'Ville',      $p['ville']     ?? '');
    ch('pays',      'Pays',       $p['pays']      ?? '');
    ?>

    <h2 class="titre-bloc">Emploi</h2>
    <?php
    ch('organisation_id', 'Association', $p['organisation_id'] ?? '', [],
       ['type' => 'select', 'choix' => $orgsF]);
    ch('fonction',     'Fonction',   $p['fonction'] ?? '', [],
       ['aide' => 'ce qu\'elle fait sur un spectacle: guitare, régie lumière']);
    ch('role_interne', 'Rôle au bureau', $p['role_interne'] ?? '', [],
       ['aide' => 'seulement pour l\'équipe interne: direction, comptabilité']);
    ch('type_engagement', 'Type d\'engagement', $p['type_engagement'] ?? '', [],
       ['aide' => 'interne, CDD, intermittent, mandat']);
    ch('paie_mensuelle', 'Tarif mensuel', $p['paie_mensuelle'] ?? '', [],
       ['type' => 'number', 'step' => '0.01']);
    ch('paie_horaire',   'Tarif horaire', $p['paie_horaire']   ?? '', [],
       ['type' => 'number', 'step' => '0.01']);
    ch('devise', 'Devise', $p['devise'] ?? 'CHF', [],
       ['type' => 'select', 'choix' => ['CHF' => 'CHF', 'EUR' => 'EUR']]);
    ch('couleur', 'Couleur de repérage', $p['couleur'] ?? '', [],
       ['type' => 'color', 'aide' => 'pour suivre sa ligne sur un planning']);
    ch('actif', 'Dans les listes', (string)($p['actif'] ?? 1), [],
       ['type' => 'select', 'choix' => [1 => 'oui, active ou actif', 0 => 'non, ancien·ne']]);
    ?>

    <h2 class="titre-bloc">Ce qui est chiffré</h2>
    <div class="ch large chiffre">
      <?php foreach (['avs' => 'Numéro AVS', 'iban' => 'IBAN'] as $c => $lib):
          $a = trim((string)($p[$c] ?? '')) !== ''; ?>
      <div class="lc">
        <label for="f_<?= $c ?>"><?= e($lib) ?></label>
        <div class="etat">
          <?php if ($clair[$c] !== null): ?>
            <code class="revele"><?= e((string)$clair[$c]) ?></code>
          <?php elseif ($a): ?>
            <span class="ok">renseigné, chiffré</span>
            <a href="<?= e(url('/dashboard.php?e=personnel&p=' . (int)$p['id'] . '&voir=' . $c)) ?>">montrer</a>
          <?php else: ?>
            <span class="rien">vide</span>
          <?php endif; ?>
        </div>
        <input type="text" id="f_<?= $c ?>" name="<?= $c ?>" value=""
               placeholder="<?= $a ? 'saisir pour remplacer — vide ne change rien' : 'saisir' ?>"
               autocomplete="off">
      </div>
      <?php endforeach; ?>
      <p class="aide">Ces deux valeurs sont chiffrées en base par <code>Crypto</code>, et la clef
         vient du <code>config.php</code> du serveur. Un champ laissé vide ne les efface pas.</p>
    </div>

    <?php ch('notes', 'Notes', $p['notes'] ?? '', [], ['type' => 'textarea', 'large' => true, 'rows' => 5]); ?>
  </div>

  <div class="actions">
    <a class="sec2" href="<?= e(url('/dashboard.php?e=personnel')) ?>">retour à la liste</a>
    <button type="submit">Enregistrer</button>
  </div>
</form>

<?php if (!$neuf): ?>
  <?php /* La suppression est un formulaire à part: dans le même que la saisie,
       un bouton « supprimer » à côté d'« enregistrer » se clique par erreur, et
       les deux enverraient le même POST. */ ?>
  <form method="post" action="<?= e(url('/dashboard.php?e=personnel')) ?>" class="sup-form"
        onsubmit="return confirm('Retirer cette fiche des listes ? Elle reste en base et ses engagements aussi.')">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="supprimer">
    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    <button type="submit" class="sup">retirer des listes</button>
  </form>

  <h3 class="eng-t">Ses engagements<?= $engs ? ' (' . count($engs) . ')' : '' ?></h3>
  <?php if (!$engs): ?>
    <p class="vide2">Aucun engagement ne pointe vers cette fiche. Les 72 engagements repris
       du dashboard portent une numérotation antérieure et ne se rattachent pas tout seuls;
       ils sont visibles dans l'onglet <a href="<?= e(url('/dashboard.php?e=personnel&t=eng')) ?>">Engagements</a>.</p>
  <?php else: ?>
  <div class="tw">
  <table>
    <thead><tr><th>Projet</th><th>Début</th><th>Fin</th>
      <th class="n">Jours</th><th class="n">Heures</th><th class="n">Mensuel</th><th>État</th></tr></thead>
    <tbody>
    <?php foreach ($engs as $g): ?>
      <tr>
        <td><?= e((string)$g['projet']) ?></td>
        <td><?= e((string)$g['debut']) ?></td>
        <td><?= e((string)$g['fin']) ?></td>
        <td class="n"><?= $g['jours']  !== null ? e((string)$g['jours'])  : '<span class="tiret">—</span>' ?></td>
        <td class="n"><?= $g['heures'] !== null ? e((string)$g['heures']) : '<span class="tiret">—</span>' ?></td>
        <td class="n"><?= $g['paie_mensuelle'] !== null
              ? number_format((float)$g['paie_mensuelle'], 0, ',', "'") : '<span class="tiret">—</span>' ?></td>
        <td><?= e((string)$g['statut']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
<?php endif; ?>

<style>
/* Les deux valeurs chiffrées, côte à côte, avec leur état au-dessus du champ:
   on doit voir d'un coup d'œil si c'est renseigné SANS avoir à le révéler. */
.chiffre{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:0 24px}
.chiffre .lc{margin-bottom:8px}
.chiffre .etat{font-size:12.5px;margin-bottom:5px;display:flex;gap:10px;align-items:baseline}
.chiffre .ok{color:var(--doux)}
.chiffre .rien{color:var(--doux);opacity:.6}
.chiffre code.revele{font-size:14px;background:var(--fond2);padding:2px 7px;border-radius:3px;
  user-select:all}
.chiffre>.aide{grid-column:1/-1;margin:2px 0 0;font-size:12px;color:var(--doux)}
.sup-form{padding:0 26px 10px;max-width:960px;display:flex;justify-content:flex-end}
button.sup{background:none;border:0;color:var(--orange);font-size:13px;font-family:inherit;
  cursor:pointer;padding:4px 0;text-decoration:underline}
.eng-t{margin:22px 26px 10px;font-size:15px}
.vide2{margin:0 26px 20px;font-size:13.5px;color:var(--doux);max-width:760px}
td.n,th.n{text-align:right;font-variant-numeric:tabular-nums}
.tiret{color:var(--doux);opacity:.5}
</style>
<?php dash_bas(); ?>

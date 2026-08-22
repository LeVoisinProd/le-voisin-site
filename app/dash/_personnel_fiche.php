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
/* L'état se lit dans le titre de la fiche, pas seulement dans un champ: on doit
   savoir qu'on regarde quelqu'un du passé avant de lui écrire. */
dash_haut('personnel', $neuf ? 'nouvelle personne'
    : e($nom) . (!$neuf && (int)($p['actif'] ?? 1) === 0
        ? ' <span class="anc">ancienne collaboration</span>' : ''));

/* PRÉCÉDENT ET SUIVANT, COMME AILLEURS. [Anna, 21.08.2026] « na parte
   personnel colocar tb os botões de próximo e anterior ». Quatre-vingt-onze
   fiches à relire une par une, c'est quatre-vingt-dix retours par la liste.

   L'ORDRE EST CELUI DE LA LISTE — `nom, prenom` — et la VUE est reprise dans
   l'adresse: partir des inactifs et se retrouver chez les actifs vaudrait à
   peine mieux que rien. On lit la colonne des identifiants entière plutôt que
   « le premier après celui-ci »: deux personnes du même nom de famille
   existent, et une comparaison sur une clef qui se répète saute des fiches.

   Les flèches sont ici, au-dessus du formulaire, et le garde-fou est celui du
   navigateur: `beforeunload` prévient si un champ a bougé. */
$vueF = (string)($_GET['vue'] ?? 'actifs');
$wF = ['supprime_le IS NULL'];
if ($vueF === 'actifs')   $wF[] = 'actif = 1';
if ($vueF === 'inactifs') $wF[] = 'actif = 0';
$idsF = $neuf ? [] : array_map('intval', DB::pdo()
    ->query('SELECT id FROM rh_employe WHERE ' . implode(' AND ', $wF) . ' ORDER BY nom, prenom')
    ->fetchAll(PDO::FETCH_COLUMN));
$iF = $neuf ? false : array_search($pid, $idsF, true);
$ctxF = $vueF !== 'actifs' ? '&amp;vue=' . rawurlencode($vueF) : '';
$lienF = fn($n) => url('/dashboard.php?e=personnel&p=' . (int)$n) . $ctxF;
?>
<?php if (!$neuf): ?>
<div class="fil-p">
  <a href="<?= e(url('/dashboard.php?e=personnel') . $ctxF) ?>">← toutes les personnes</a>
  <?php if ($iF !== false): ?>
    <?php if (isset($idsF[$iF - 1])): ?>
      <a class="pas" href="<?= $lienF($idsF[$iF - 1]) ?>">← précédent</a>
    <?php else: ?><span class="pas mort">← précédent</span><?php endif; ?>
    <span class="rang"><?= $iF + 1 ?> / <?= count($idsF) ?></span>
    <?php if (isset($idsF[$iF + 1])): ?>
      <a class="pas" href="<?= $lienF($idsF[$iF + 1]) ?>">suivant →</a>
    <?php else: ?><span class="pas mort">suivant →</span><?php endif; ?>
  <?php endif; ?>
</div>
<style>
.fil-p{display:flex;gap:16px;align-items:baseline;padding:12px 26px 0;font-size:13px}
.fil-p a{color:var(--doux);text-decoration:none}
.fil-p a:hover{color:var(--encre)}
.fil-p .pas{color:var(--encre);font-weight:600}
.fil-p .pas.mort{color:var(--doux);opacity:.35}
.fil-p .rang{color:var(--doux);font-variant-numeric:tabular-nums}
.anc{font-size:11px;padding:2px 8px;border-radius:10px;border:1px solid var(--trait);
  color:var(--doux);margin-left:8px;white-space:nowrap;font-weight:400}
</style>
<?php endif; ?>
<?php
dash_form_style();
dash_flash_html();
?>

<form class="saisie" method="post" enctype="multipart/form-data"
      action="<?= e(url('/dashboard.php?e=personnel')) ?>">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="action" value="emp">
  <input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>">

  <div class="grille">
    <h2 class="titre-bloc">Identité</h2>
    <?php
    /* ── ACTIVE OU ANCIENNE: EN TÊTE, ET DIT EN CLAIR.  [Anna, 22.08.2026] ──
       « na página de cada pessoa ainda não tem o botão para dizer se a pessoa é
       ativa ou inativa, tem muita gente que é do passado ».

       LE CHAMP EXISTAIT, ET C'EST PIRE QUE S'IL AVAIT MANQUÉ. Il s'appelait
       « Dans les listes » et vivait au bas du bloc Emploi, entre la devise et la
       couleur de repérage. Personne ne va chercher là si quelqu'un travaille
       encore ici, et le résultat se mesure: sur les quatre-vingt-onze fiches,
       QUATRE-VINGT-ONZE sont actives et aucune ne porte le passé.

       Il monte donc en tête, sous le nom, et prend le mot qu'on emploie. Une
       question qui se pose à l'ouverture de la fiche ne se range pas en bas. */
    ch('actif', 'Collaboration', (string)($p['actif'] ?? 1), [],
       ['type' => 'select', 'choix' => [1 => 'en cours', 0 => 'terminée — ancienne collaboration'],
        'aide' => 'Une collaboration terminée sort des listes de choix, mais la fiche reste: '
                . 'les contrats, les salaires et les déclarations d\'une personne partie se relisent.']);
    ch('prenom',   'Prénom',            $p['prenom']   ?? '');
    ch('nom',      'Nom',               $p['nom']      ?? '');
    /* DEUX NOMS POUR DEUX USAGES, ET ILS NE SE REMPLACENT PAS.  [Anna, 22.08.2026]
       Un contrat, une fiche de salaire ou une déclaration AVS au nom de scène est
       nul; un dossier de diffusion au nom d'état civil ne désigne pas l'artiste
       que le programmateur connaît. Le dossier prend celui-ci, la logistique
       prend le nom officiel. */
    ch('nom_artistique', 'Nom artistique ou d\'usage', $p['nom_artistique'] ?? '', [],
       ['aide' => 'Laissé vide, c\'est le nom officiel qui sert partout.']);
    ch('pronom',   'Pronom',            $p['pronom']   ?? '', [], ['aide' => 'elle, il, iel']);
    ch('naissance','Date de naissance', $p['naissance'] ?? '', [], ['type' => 'date']);
    ch('nationalite','Nationalité',     $p['nationalite'] ?? '');
    ch('permis',   'Permis de séjour',  $p['permis']   ?? '', [], ['aide' => 'B, C, G, L — vide si suisse ou UE résident']);
    /* ── LA BIO ET LE PORTRAIT.  [Anna, 22.08.2026] ──────────────────────────
       « colocar o item foto + bio ». Ils sont de la personne et non du
       spectacle — sa réponse à la question posée — donc écrits une fois ici et
       repris par tous les dossiers où elle entre. Une bio corrigée se corrige
       partout du même geste. */
    ch('bio', 'Biographie', $p['bio'] ?? '', [],
       ['type' => 'textarea', 'rows' => 6, 'large' => true,
        'aide' => 'Reprise telle quelle dans les dossiers. Du texte, pas de mise en forme.']);
    ?>

    <div class="ch large">
      <label for="f_photo">Portrait</label>
      <p class="aide">JPEG, PNG ou WebP, 8 Mo au maximum. Il n'est pas servi par une adresse
         publique: seul le dashboard le montre, et le dossier l'emporte à l'intérieur du PDF.</p>
      <div class="ph-l">
        <?php if (!empty($p['photo']) && (int)($p['id'] ?? 0) > 0): ?>
          <img class="ph-v" alt="" src="<?= e(url('/dashboard.php?e=personnel&photo=' . (int)$p['id'])) ?>">
          <label class="ph-s"><input type="checkbox" name="photo_sup" value="1"> retirer</label>
        <?php else: ?>
          <span class="ph-r">aucun portrait</span>
        <?php endif; ?>
        <input type="file" id="f_photo" name="photo" accept="image/jpeg,image/png,image/webp">
      </div>
    </div>
    <style>
    /* Les règles du portrait: écrites ici, dans le seul écran qui le montre. */
    .ph-l{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
    .ph-v{width:76px;height:76px;object-fit:cover;border-radius:8px;
      border:1px solid var(--trait);background:var(--fond2)}
    .ph-r{font-size:13px;color:var(--doux)}
    .ph-s{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;color:var(--doux)}
    .ph-s input{width:auto;margin:0}
    .ph-l input[type=file]{font-size:12.5px}
    </style>

    <h2 class="titre-bloc">Contact</h2>
    <?php
    ch('email',     'Courriel',   $p['email']     ?? '', [], ['type' => 'email']);
    ch('telephone', 'Téléphone',  $p['telephone'] ?? '');
    ch('rue',       'Rue',        $p['rue']       ?? '', [], ['large' => true]);
    /* Le numéro à part: il ne se trie ni ne se compare dans la même case que la
       rue, et les formulaires suisses et français ne l'attendent pas du même
       côté. Les adresses déjà saisies ne sont pas découpées — un découpage sur
       l'espace casserait « Chemin du 23-Août ». */
    ch('numero',    'N°',         $p['numero']    ?? '');
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
    /* « Dans les listes » a déménagé en tête de la fiche, sous le nom, et
       s'appelle maintenant ce qu'il est. Voir le bloc Identité. */
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

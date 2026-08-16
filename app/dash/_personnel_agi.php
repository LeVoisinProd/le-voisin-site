<?php
/**
 * L'attestation de gain intermédiaire, sur le formulaire officiel. [17.08.2026]
 *
 * Inclus par `personnel.php`, onglet AGI. Une personne au chômage qui travaille
 * quelques jours pour nous doit faire remplir ce formulaire PAR L'EMPLOYEUR, et
 * sans lui la caisse ne verse rien. Il revient tous les mois, pour les mêmes
 * personnes, avec les mêmes réponses aux treize questions — ce qui en fait
 * exactement le genre de papier qu'une machine doit préparer.
 *
 * LE PDF EST REMPLI DANS LE NAVIGATEUR, par pdf-lib, comme la déclaration SSA.
 * Le serveur n'a ni pdftk ni qpdf ni extension PHP qui sache écrire un
 * AcroForm, vérifié le 16.08.2026. La bibliothèque est servie depuis ce site:
 * la CSP refuse les CDN, et c'est très bien ainsi.
 *
 * LES DEUX ESPACES DE NOMS DU FORMULAIRE SONT LE PIÈGE, et il est silencieux.
 * « 1.x » est le questionnaire, « 2.x » la grille des trente-et-un jours. Il
 * existe un `1.54` — la durée hebdomadaire de la question 3 — ET un `2.54` —
 * le jour 13. Écrire dans le mauvais ne produit aucune erreur: on s'en aperçoit
 * quand la caisse renvoie le formulaire, un mois plus tard. Les noms de
 * `agi-champs.json` sont donc PLEINEMENT QUALIFIÉS, tous les trente-sept
 * vérifiés un à un contre les champs réels du fichier.
 *
 * CE QUE LA MACHINE NE PEUT PAS SAVOIR EST DEMANDÉ, PAS DEVINÉ. Les heures
 * jour par jour n'existent nulle part chez nous — les engagements portent un
 * total — donc la grille se saisit ici. Inventer une répartition régulière sur
 * un document qui sert à calculer une indemnité serait une déclaration fausse
 * signée par le bureau.
 */
declare(strict_types=1);

$gens = DB::all("SELECT id, prenom, nom, fonction, organisation_id, avs, paie_mensuelle, paie_horaire
                   FROM rh_employe WHERE supprime_le IS NULL AND actif = 1
                  ORDER BY nom, prenom");
$sel  = (int)($_GET['qui'] ?? 0);
$moi  = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['m'] ?? '')) ? (string)$_GET['m'] : date('Y-m');

$q = $sel ? DB::one("SELECT * FROM rh_employe WHERE id = ? AND supprime_le IS NULL", [$sel]) : null;
$o = $q && $q['organisation_id']
   ? DB::one("SELECT * FROM organisation WHERE id = ?", [(int)$q['organisation_id']]) : null;

/* L'AVS n'est déchiffré que pour la personne choisie, et il part dans le PDF
   sans jamais s'afficher: le formulaire le montre en pointillé. Un numéro AVS
   sur un écran ouvert au bureau est exactement ce que le chiffrement du 16.08
   servait à éviter. */
$avs = $q ? Crypto::dechiffrer((string)$q['avs']) : '';

/* La grille du mois: on donne les trente-et-un jours avec leur nom, pour que
   la saisie ne se décale pas d'une case — c'est la faute classique, et elle ne
   se voit pas sur le PDF fini. */
$prem  = new DateTimeImmutable($moi . '-01');
$nbJ   = (int)$prem->format('t');
$JOURS = ['Lu','Ma','Me','Je','Ve','Sa','Di'];

/* Ce qui est connu de l'engagement du mois, s'il y en a un. */
$eng = $sel ? DB::one(
    "SELECT * FROM rh_engagement
      WHERE supprime_le IS NULL AND employe_id = ?
        AND (mois = ? OR (debut <= LAST_DAY(?) AND (fin IS NULL OR fin >= ?)))
      ORDER BY debut DESC LIMIT 1",
    [$sel, $moi, $moi . '-01', $moi . '-01']) : null;

$champs = @json_decode((string)@file_get_contents(dirname(__DIR__, 2) . '/assets/formulaires/agi-champs.json'), true);
$pdfOk  = is_file(dirname(__DIR__, 2) . '/assets/formulaires/agi-attestation.pdf') && is_array($champs);
?>

<div class="note">
  <p>Le formulaire officiel de l'assurance chômage, rempli avec ce que la base sait déjà.
     <strong>Il se télécharge rempli, il ne s'envoie pas d'ici</strong> — il faut encore le
     signer et le transmettre à la caisse.</p>
  <?php if (!$pdfOk): ?>
  <p class="manque"><strong>Le formulaire vierge n'est pas sur ce serveur.</strong>
     Il doit être installé à <code>assets/formulaires/agi-attestation.pdf</code>,
     avec sa carte de champs <code>agi-champs.json</code>.</p>
  <?php endif; ?>
</div>

<form class="agi" method="get" action="<?= e(url('/dashboard.php')) ?>">
  <input type="hidden" name="e" value="personnel">
  <input type="hidden" name="t" value="agi">
  <label>Pour qui
    <select name="qui" onchange="this.form.submit()">
      <option value="">— choisir une personne —</option>
      <?php foreach ($gens as $g): ?>
        <option value="<?= (int)$g['id'] ?>"<?= $sel === (int)$g['id'] ? ' selected' : '' ?>><?=
          e(trim($g['nom'] . ', ' . $g['prenom'])) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Mois
    <input type="month" name="m" value="<?= e($moi) ?>" onchange="this.form.submit()">
  </label>
  <noscript><button type="submit">Afficher</button></noscript>
</form>

<?php if (!$q): ?>
  <p class="vide">Choisir une personne pour préparer son attestation.</p>
<?php else: ?>

<form class="saisie agi-form" onsubmit="return false">
  <div class="grille">

    <h2 class="titre-bloc">Ce que la base sait déjà</h2>
    <div class="ch"><label>Personne</label>
      <p class="lu"><?= e(trim($q['prenom'] . ' ' . $q['nom'])) ?></p></div>
    <div class="ch"><label>Numéro AVS</label>
      <p class="lu"><?= $avs !== ''
        ? '<span class="masq">' . e(substr($avs, 0, 3)) . '·····' . e(substr($avs, -2)) . '</span>
           <span class="sec">chiffré, il part dans le PDF sans s\'afficher</span>'
        : '<span class="manq">absent de la fiche — le formulaire partira sans</span>' ?></p></div>
    <div class="ch"><label>Employeur</label>
      <p class="lu"><?= e((string)($o['nom_legal'] ?: $o['nom'] ?? '—')) ?></p></div>
    <div class="ch"><label>IDE · REE</label>
      <p class="lu"><?= e((string)($o['ide'] ?? '')) ?: '<span class="manq">IDE absent</span>' ?>
         · <?= e((string)($o['ree'] ?? '')) ?: '<span class="manq">REE absent</span>' ?></p></div>

    <h2 class="titre-bloc">L'activité et la période</h2>
    <?php
    ch('activite',   'Activité exercée', $q['fonction'] ?: ($eng['projet'] ?? ''), [],
       ['aide' => 'ce qui est écrit sur le contrat']);
    ch('q3_heures',  'Durée hebdomadaire convenue', $eng['heures'] ?? '', [],
       ['type' => 'number', 'step' => '0.25', 'aide' => 'en heures']);
    ch('q4_normale', 'Durée normale dans l\'entreprise', '', [],
       ['type' => 'number', 'step' => '0.25', 'aide' => 'en heures par semaine']);
    ch('q8_mensuel', 'Salaire mensuel brut', $eng['paie_mensuelle'] ?? $q['paie_mensuelle'] ?? '', [],
       ['type' => 'number', 'step' => '0.01']);
    ch('q9_base',    'Salaire horaire brut', $eng['paie_horaire'] ?? $q['paie_horaire'] ?? '', [],
       ['type' => 'number', 'step' => '0.01']);
    ch('q9_heures',  'Heures du mois', $eng['heures'] ?? '', [],
       ['type' => 'number', 'step' => '0.25', 'aide' => 'total, pour la question 9']);
    ?>

    <h2 class="titre-bloc">Qui signe</h2>
    <?php
    $moiUser = Auth::user();
    ch('contact_nom',    'Nom',       '', [], ['aide' => 'la personne du bureau que la caisse rappellera']);
    ch('contact_prenom', 'Prénom',    '');
    ch('contact_tel',    'Téléphone', (string)($o['telephone'] ?? ''));
    ch('contact_email',  'Courriel',  (string)($moiUser['email'] ?? ''), [], ['type' => 'email']);
    ch('lieu',           'Fait à',    (string)($o['ville'] ?? ''));
    ch('date',           'Le',        date('Y-m-d'), [], ['type' => 'date']);
    ?>

    <h2 class="titre-bloc">Les jours travaillés — <?= e($prem->format('F Y')) ?></h2>
    <div class="ch large">
      <p class="aide">Les heures de chaque jour. <strong>Elles ne sont nulle part dans la base:</strong>
         les engagements portent un total, pas une répartition. Inventer une répartition
         régulière sur un document qui calcule une indemnité serait une déclaration fausse.
         Les cases laissées vides restent vides sur le formulaire.</p>
      <div class="jours">
        <?php for ($i = 1; $i <= 31; $i++):
            $d = $i <= $nbJ ? $prem->modify('+' . ($i - 1) . ' day') : null;
            $we = $d && (int)$d->format('N') >= 6; ?>
          <label class="j<?= $we ? ' we' : '' ?><?= $d ? '' : ' hors' ?>">
            <span><?= $i ?><?= $d ? ' ' . $JOURS[(int)$d->format('N') - 1] : '' ?></span>
            <input type="number" step="0.25" min="0" data-jour="<?= $i - 1 ?>"
                   <?= $d ? '' : 'disabled' ?>>
          </label>
        <?php endfor; ?>
      </div>
      <p class="aide total">Total saisi : <strong id="tot">0</strong> h</p>
    </div>
  </div>

  <div class="actions">
    <button type="button" id="agiGen"<?= $pdfOk ? '' : ' disabled' ?>>Télécharger l'attestation remplie</button>
    <span class="sec" id="agiMsg"></span>
  </div>
</form>

<?php if ($pdfOk): ?>
<script src="<?= e(url('/assets/js/pdf-lib.min.js')) ?>"></script>
<script>
(function () {
  var C = <?= json_encode($champs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var D = <?= json_encode([
      'nom'     => (string)$q['nom'],
      'prenom'  => (string)$q['prenom'],
      'avs'     => $avs,
      'emp_nom' => (string)($o['nom_legal'] ?: $o['nom'] ?? ''),
      'ide'     => (string)($o['ide'] ?? ''),
      'ree'     => (string)($o['ree'] ?? ''),
      'mois'    => $moi,
      'fichier' => 'agi-' . preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(trim($q['prenom'] . '-' . $q['nom']))) . '-' . $moi,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var URL_PDF = <?= json_encode(url('/assets/formulaires/agi-attestation.pdf')) ?>;

  var tot = document.getElementById('tot');
  function recalc() {
    var s = 0;
    document.querySelectorAll('.jours input').forEach(function (i) { s += parseFloat(i.value) || 0; });
    tot.textContent = String(Math.round(s * 100) / 100);
  }
  document.querySelectorAll('.jours input').forEach(function (i) { i.addEventListener('input', recalc); });

  function v(n) { var x = document.getElementById('f_' + n); return x ? x.value : ''; }

  /* L'AVS SE DÉCOUPE EN TROIS CASES sur le formulaire, et le découpage est
     celui du numéro suisse: 756 . 1234 5678 . 97. La première porte le
     préfixe pays, la dernière la clef de contrôle, celle du milieu le reste.
     Un numéro d'une autre forme est mis entier dans la première case plutôt
     que découpé au hasard — mieux vaut une case trop pleine qu'un numéro faux
     réparti sur trois. */
  function avs3(a) {
    var d = String(a || '').replace(/\D/g, '');
    if (d.length !== 13) return [String(a || ''), '', ''];
    return [d.slice(0, 3), d.slice(3, 11), d.slice(11)];
  }

  document.getElementById('agiGen').addEventListener('click', async function () {
    var msg = document.getElementById('agiMsg');
    msg.textContent = 'préparation…';
    try {
      var res = await fetch(URL_PDF);
      if (!res.ok) throw new Error('formulaire introuvable (' + res.status + ')');
      var doc = await PDFLib.PDFDocument.load(await res.arrayBuffer());
      var f = doc.getForm();

      var manques = [];
      function T(nom, val) {
        if (val === '' || val === null || val === undefined) return;
        try { f.getTextField(nom).setText(String(val)); }
        catch (e) { manques.push(nom); }
      }

      T(C.identite.nom, D.nom);
      T(C.identite.prenom, D.prenom);
      T(C.identite.activite, v('activite'));
      var a = avs3(D.avs);
      C.identite.avs.forEach(function (n, i) { T(n, a[i]); });

      T(C.employeur.nom, D.emp_nom);
      T(C.employeur.ide, D.ide);
      T(C.employeur.ree, D.ree);
      T(C.employeur.contact_nom, v('contact_nom'));
      T(C.employeur.contact_prenom, v('contact_prenom'));
      T(C.employeur.tel, v('contact_tel'));
      T(C.employeur.email, v('contact_email'));

      T(C.q3_heures,  v('q3_heures'));
      T(C.q4_normale, v('q4_normale'));
      T(C.q8_mensuel, v('q8_mensuel'));
      T(C.q9_base,    v('q9_base'));
      T(C.q9_heures,  v('q9_heures'));
      T(C.lieu,       v('lieu'));
      T(C.date,       v('date'));

      /* La grille: `C.jours` est dans l'ordre des jours du mois, pas dans
         l'ordre des noms de champ. C'est tout l'intérêt de la carte —
         l'ordre visuel du formulaire ne suit pas la numérotation. */
      document.querySelectorAll('.jours input').forEach(function (i) {
        var n = C.jours[parseInt(i.dataset.jour, 10)];
        if (n && i.value !== '') T(n, i.value);
      });

      var octets = await doc.save();
      var b = new Blob([octets], { type: 'application/pdf' });
      var u = URL.createObjectURL(b);
      var lien = document.createElement('a');
      lien.href = u; lien.download = D.fichier + '.pdf';
      document.body.appendChild(lien); lien.click(); lien.remove();
      setTimeout(function () { URL.revokeObjectURL(u); }, 4000);

      /* Un champ que le PDF ne connaît pas est DIT. Le formulaire officiel
         change de version, et une carte qui vieillit remplirait moins de
         cases sans rien signaler — on s'en apercevrait à la case vide sur le
         papier signé. */
      msg.textContent = manques.length
        ? 'téléchargé — ' + manques.length + ' champ(s) inconnus du formulaire: ' + manques.join(', ')
        : 'téléchargé.';
    } catch (err) {
      msg.textContent = 'échec : ' + err.message;
    }
  });
})();
</script>
<?php endif; ?>

<style>
form.agi{display:flex;gap:18px;align-items:flex-end;padding:18px 26px 4px;flex-wrap:wrap}
form.agi label{display:flex;flex-direction:column;gap:4px;font-size:12.5px;color:var(--doux)}
form.agi select,form.agi input{padding:8px 11px;font-size:14px;font-family:inherit;
  border:1px solid var(--trait);border-radius:4px;background:var(--papier);color:var(--encre)}
.agi-form .lu{margin:0;font-size:14.5px;padding:8px 0}
.agi-form .masq{font-family:ui-monospace,monospace}
.agi-form .manq{color:var(--orange);font-size:13px}
.jours{display:grid;grid-template-columns:repeat(auto-fill,minmax(84px,1fr));gap:7px;margin:10px 0}
.jours .j{display:flex;flex-direction:column;gap:3px}
.jours .j span{font-size:11.5px;color:var(--doux)}
.jours .j.we span{color:var(--orange);opacity:.8}
.jours .j.hors{opacity:.3}
.jours input{width:100%;padding:6px 8px;font-size:13.5px;font-family:inherit;
  border:1px solid var(--trait);border-radius:4px;background:var(--papier);color:var(--encre);
  text-align:right;font-variant-numeric:tabular-nums}
.aide.total{margin-top:8px;font-size:13px}
.note .manque{color:var(--orange)}
</style>
<?php endif; ?>

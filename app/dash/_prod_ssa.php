<?php
/**
 * La déclaration d'œuvre SSA, et le formulaire officiel rempli. [16.08.2026]
 *
 * Anna: « o pdf da ssa nao é um pdf normal, ele é feito em cima do formulario
 * da ssa (…) pronto para ser enviado para declarar a obra ». La version
 * imprimable écrite le matin même ne servait donc pas: la SSA veut SON
 * formulaire, le M23F0525, avec ses cases.
 *
 * LE REMPLISSAGE SE FAIT DANS LE NAVIGATEUR, et ce n'est pas un choix
 * esthétique. Le serveur ne sait pas remplir un AcroForm: ni pdftk, ni qpdf, ni
 * bibliothèque PHP capable de le faire — vérifié le 16.08. Le navigateur, lui,
 * en est capable avec pdf-lib, qui est servie depuis ce site et non depuis un
 * CDN: la politique de sécurité refuse les scripts venus d'ailleurs, et une
 * dépendance externe est de toute façon une panne qui attend son heure.
 *
 * CE QUI N'EST PAS REDEMANDÉ ICI. Le titre, le producteur, la date de première
 * représentation et le partage des droits viennent du projet et de l'onglet
 * Droits. Les redemander ferait deux vérités: celle qu'on déclare et celle
 * qu'on tient. Les champs de cet écran sont exactement ceux que le formulaire
 * demande EN PLUS.
 *
 * LES 85 CHAMPS DU PDF SONT MAPPÉS, y compris les onze lignes du tableau des
 * droits (`Fonction4Row1` … `Row11`) et les quinze codes de genre (`a` =
 * Théâtre, `g` = Marionnettes, `o` = Autre). La description est découpée sur
 * cinq lignes de 95 caractères, comme le fait le formulaire.
 */
declare(strict_types=1);
/** @var array $d */ /** @var array $p */ /** @var int $pid */
/** @var bool $ecrit */ /** @var callable $lien */

$ssa   = $d['droits']['ssa'] ?? [];
$titre = trim((string)($p['title_fr'] ?: $p['title_en']));

/* Le porteur juridique: c'est lui le producteur au sens de la SSA. */
$prod0 = ProdFiche::ligne($pid);
$porteur = $prod0['organisation_id']
    ? (string)(DB::val('SELECT nom FROM organisation WHERE id = ?', [(int)$prod0['organisation_id']]) ?: '')
    : '';

/* La première représentation: la plus ancienne date de ce spectacle. */
$date1 = (string)(DB::val(
    "SELECT MIN(date_debut) FROM booking
      WHERE supprime_le IS NULL AND projet = ? AND date_debut IS NOT NULL", [$titre]) ?: '');

$auteurs = array_slice($d['droits']['auteurs'] ?? [], 0, 11);
$total   = ProdFiche::droitsTotal($d);

/** La valeur saisie, ou vide. */
$v = static fn(string $k): string => (string)($ssa[$k] ?? '');
?>

<div class="ssa-tete">
  <h3>Déclaration d'œuvre SSA</h3>
  <?php if ($ecrit): ?>
    <button type="button" id="ssaGen" class="bt-pdf">Générer le PDF SSA</button>
  <?php endif; ?>
</div>

<p class="aide">Le titre, le producteur, la 1ère représentation et le partage des droits
   (les auteurs de l'onglet, <strong>onze au maximum</strong>) sont repris automatiquement.
   Complétez le reste, puis générez le formulaire officiel <strong>M23F0525</strong>,
   prêt à signer.</p>

<?php if (abs($total - 100.0) >= 0.01): ?>
  <p class="ssa-av"><strong>Le partage fait <?= rtrim(rtrim(number_format($total, 2, ',', ' '), '0'), ',') ?> %
     et non 100 %.</strong> Le formulaire se génère quand même — mais une déclaration qui ne
     fait pas exactement 100 % est refusée par la SSA, et on l'apprend des mois plus tard,
     quand les droits devaient tomber.</p>
<?php endif; ?>

<form method="post" action="<?= e($lien('droits')) ?>">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="pf" value="champs">

  <div class="ssa-g">
    <?php foreach (ProdFiche::SSA_CHAMPS as $k => [$lib, $type, $aide]):
      $id = 'ssa-' . $k; $val = $v($k); ?>
      <div class="ch<?= $type === 'zone' ? ' pl' : '' ?>">
        <label for="<?= $id ?>"><?= e($lib) ?></label>
        <?php if ($aide !== ''): ?><p class="aide"><?= e($aide) ?></p><?php endif; ?>

        <?php if ($type === 'genres'): ?>
          <select id="<?= $id ?>" name="v[droits.ssa.<?= $k ?>]" <?= $ecrit ? '' : 'disabled' ?>>
            <?php foreach (ProdFiche::SSA_GENRES as $gk => $gl): ?>
              <option value="<?= $gk ?>" <?= ($val ?: 'a') === $gk ? 'selected' : '' ?>><?= e($gl) ?></option>
            <?php endforeach; ?>
          </select>

        <?php elseif ($type === 'oui_non'): ?>
          <select id="<?= $id ?>" name="v[droits.ssa.<?= $k ?>]" <?= $ecrit ? '' : 'disabled' ?>>
            <?php foreach (['' => '—', 'Oui' => 'Oui', 'Non' => 'Non'] as $ok => $ol): ?>
              <option value="<?= e($ok) ?>" <?= $val === $ok ? 'selected' : '' ?>><?= e($ol) ?></option>
            <?php endforeach; ?>
          </select>

        <?php elseif ($type === 'orig'): ?>
          <select id="<?= $id ?>" name="v[droits.ssa.<?= $k ?>]" <?= $ecrit ? '' : 'disabled' ?>>
            <option value="originale" <?= $val !== 'adaptee' ? 'selected' : '' ?>>Originale</option>
            <option value="adaptee"   <?= $val === 'adaptee' ? 'selected' : '' ?>>Adaptée / traduite</option>
          </select>

        <?php elseif ($type === 'date'): ?>
          <input type="date" id="<?= $id ?>" name="v[droits.ssa.<?= $k ?>]"
                 value="<?= e($val ?: $date1) ?>" <?= $ecrit ? '' : 'readonly' ?>>

        <?php elseif ($type === 'zone'): ?>
          <textarea id="<?= $id ?>" name="v[droits.ssa.<?= $k ?>]" rows="4"
            <?= $ecrit ? '' : 'readonly' ?>><?= e($val) ?></textarea>

        <?php else: ?>
          <input type="text" id="<?= $id ?>" name="v[droits.ssa.<?= $k ?>]"
                 value="<?= e($k === 'producteur' && $val === '' ? $porteur : $val) ?>"
                 <?= $ecrit ? '' : 'readonly' ?>>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($ecrit): ?><div class="act"><button type="submit">Enregistrer</button></div><?php endif; ?>
</form>

<p class="aide">Abréviations des fonctions dans le tableau des droits :
   <strong>A</strong> auteur·e du texte · <strong>C</strong> compositeur·trice ·
   <strong>CH</strong> chorégraphe · <strong>MES</strong> metteur·euse en scène ·
   <strong>AT</strong> traducteur·trice · <strong>AA</strong> adaptateur·trice ·
   <strong>ALI</strong> livret · <strong>AAR</strong> argument · <strong>E</strong> édition.</p>

<?php if ($ecrit): ?>
<script src="<?= e(url('/assets/js/pdf-lib.min.js')) ?>"></script>
<script>
/* Le remplissage du formulaire officiel, dans le navigateur.
   Chaque champ est posé par son nom exact tel qu'il existe dans le PDF; un nom
   absent est ignoré sans bruit plutôt que de faire échouer toute la génération
   — un formulaire à 90 % rempli s'achève à la main, un formulaire qui ne sort
   pas ne s'achève pas du tout. */
(function () {
  var b = document.getElementById('ssaGen');
  if (!b) return;

  var D = <?= json_encode([
      'titre'      => $titre,
      'producteur' => $v('producteur') !== '' ? $v('producteur') : $porteur,
      'date1'      => $v('date1') !== '' ? $v('date1') : $date1,
      'duree'      => $v('duree') !== '' ? $v('duree') : (string)($p['duration_min'] ?? ''),
      'resume'     => (string)($d['resume'] ?? ''),
      'ville'      => '',
      'auteurs'    => array_map(fn($a) => [
          'role'    => (string)($a['role'] ?? ''),
          'nom'     => (string)($a['nom'] ?? ''),
          'societe' => (string)($a['societe'] ?? ''),
          'part'    => (string)($a['part'] ?? ''),
      ], $auteurs),
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

  function champ(id) { var e = document.getElementById('ssa-' + id); return e ? e.value.trim() : ''; }

  function frDate(iso) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) return iso || '';
    var p = iso.split('-'); return p[2] + '.' + p[1] + '.' + p[0];
  }

  b.addEventListener('click', async function () {
    b.disabled = true; var libelle = b.textContent; b.textContent = 'Génération…';
    try {
      var res = await fetch('<?= e(url('/assets/formulaires/ssa-declaration-oeuvre.pdf')) ?>');
      if (!res.ok) throw new Error('formulaire introuvable (' + res.status + ')');
      var doc = await PDFLib.PDFDocument.load(await res.arrayBuffer());
      var f = doc.getForm();

      function T(nom, val) { try { f.getTextField(nom).setText(String(val || '')); } catch (e) {} }
      function R(nom, opt) {
        if (!opt) return;
        try {
          var g = f.getRadioGroup(nom), os = g.getOptions();
          var m = os.find(function (o) { return o === opt; })
               || os.find(function (o) { return o.toLowerCase().indexOf(String(opt).toLowerCase()) > -1; });
          if (m) g.select(m);
        } catch (e) {}
      }

      T('Titre', D.titre);
      T('Sous-titre', champ('soustitre'));
      T('Autre titre', champ('autreTitre'));
      T('Langue/s', champ('langue'));
      T('minutage', champ('duree') || D.duree);

      var g = champ('genre') || 'a';
      if (g === 'o') T('Autre', champ('genreAutre'));
      R("Genre d'oeuvre", g);

      R("L'oeuvre est-elle accompagnée de ou comporte-t-elle une musique ?", champ('musique'));
      T('min_2', champ('dureeMusOrig'));
      T('min_3', champ('dureeMusProt'));
      T('min_4', champ('dureeMusDP'));

      R("L'oeuvre/la musique a-t-elle été éditée ?", champ('editee'));
      T('Edition lieu et année', champ('editionLieu'));
      R('Une diffusion radio/TV/web/VOD est-elle prévue ?', champ('diffusion'));
      R('La présente oeuvre est', champ('originale') === 'adaptee' ? 'adapt' : 'originale');

      T('Producteur', champ('producteur') || D.producteur);
      T('Date de la 1ère représentation', frDate(champ('date1') || D.date1));
      T('Lieu de la 1ère représentation', champ('lieu1'));

      /* La description sur cinq lignes de 95 caractères: c'est la place que le
         formulaire laisse. Couper au mot, jamais au milieu d'un mot. */
      var desc = champ('description') || D.resume || '';
      var lignes = [], cur = '';
      desc.split(/\s+/).forEach(function (w) {
        if ((cur + ' ' + w).trim().length > 95) { lignes.push(cur.trim()); cur = w; }
        else cur = (cur + ' ' + w);
      });
      if (cur.trim()) lignes.push(cur.trim());
      T('Description de lœuvre', lignes[0] || '');
      for (var i = 1; i <= 4; i++) T('Description_' + i, lignes[i] || '');

      T('Remarques', champ('remarques'));

      /* Lieu et date de signature, aux trois endroits où le formulaire les
         demande. Le lieu vient de la 1ère représentation, à défaut vide. */
      var lieuDate = [ (champ('lieu1') || '').split(',')[0].trim(),
                       frDate(champ('date1') || D.date1) ].filter(Boolean).join(', ');
      if (lieuDate) { T('Lieu date et signatures manuscrites_1', lieuDate);
                      T('Lieu date et signatures manuscrites_2', lieuDate);
                      T('Lieu date et signatures manuscrites_3', lieuDate); }

      var NOMF = 'Nom  Pseudonyme Indiquer cas échéant le pseudonyme utilisé pour lœuvreet patronyme entre parenthèsesRow';
      D.auteurs.forEach(function (a, i) {
        var n = i + 1;
        T('Fonction4Row' + n, a.role || '');
        T(NOMF + n, a.nom || '');
        T('Row' + n, a.part ? String(a.part) : '');
      });

      var out = await doc.save();
      var url = URL.createObjectURL(new Blob([out], { type: 'application/pdf' }));
      var a = document.createElement('a');
      a.href = url;
      a.download = 'declaration-ssa-' + (D.titre || 'oeuvre').toLowerCase()
                     .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') + '.pdf';
      document.body.appendChild(a); a.click(); document.body.removeChild(a);
      setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
      b.textContent = 'PDF généré — relisez avant d\'envoyer';
    } catch (err) {
      b.textContent = 'Échec : ' + err.message;
      b.style.background = '#c8452f';
    } finally {
      setTimeout(function () {
        b.disabled = false; b.textContent = libelle; b.style.background = '';
      }, 5000);
    }
  });
})();
</script>
<?php endif; ?>

<style>
.ssa-tete{display:flex;align-items:center;gap:16px;margin:26px 0 10px;padding-top:20px;
  border-top:1px solid var(--trait)}
.ssa-tete h3{margin:0;font-size:16px}
.ssa-tete .bt-pdf{margin-left:auto;padding:9px 17px;border:1px solid var(--encre);border-radius:5px;
  background:var(--jaune);color:#0d0d0d;font:inherit;font-size:13.5px;font-weight:600;
  cursor:pointer;white-space:nowrap}
.ssa-tete .bt-pdf:disabled{opacity:.65;cursor:default}
.ssa-av{margin:0 0 14px;padding:9px 13px;border-left:3px solid #d9a800;background:#fdf7e3;
  color:#4a3d00;font-size:13px;max-width:88ch}
.ssa-g{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:0 26px}
.ssa-g .ch.pl{grid-column:1/-1}
</style>

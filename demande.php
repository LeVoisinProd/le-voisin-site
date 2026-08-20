<?php
/**
 * Le formulaire public de demande de booking. [16.08.2026]
 *
 * Anna: « Les demandes entrantes ne restent plus dans votre boîte de
 * réception. » Aujourd'hui une demande de programmateur arrive par e-mail, se
 * répond par e-mail, et n'existe nulle part ailleurs: on ne peut ni compter
 * combien il en arrive, ni voir lesquelles sont restées sans réponse, ni dire
 * quelle proportion devient une date. Ces trois chiffres sont exactement ceux
 * qu'un dossier de subvention demande.
 *
 * PAS DE CAPTCHA, ET C'EST UN CHOIX. La personne qu'on veut ici est un
 * programmateur pressé; un CAPTCHA le fait renoncer et il écrit un e-mail, ce
 * qui ramène au point de départ. Les trois garde-fous sont dans Offers.php et
 * ne se voient pas: un champ piège, un temps minimum de remplissage signé, et
 * un plafond par adresse.
 *
 * UN FICHIER À LA RACINE, pour la même raison que catalogue.php, dashboard.php
 * et advancing.php: le cache d'opcode garde index.php compilé et refuse de le
 * relire, mesuré le 12.08.2026.
 *
 * L'ADRESSE:  https://le-voisin.com/demande.php
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

I18n::init();

/* [19.08.2026] La langue, exactement comme catalogue.php : cette page n'a pas
   d'adresse par langue, donc elle se choisit par paramètre et se garde en
   session. La clef de session est CELLE DU CATALOGUE, volontairement : on
   arrive ici depuis une fiche du Catalogue, et changer de langue en passant le
   lien serait le même défaut que d'avoir deux mises en page.

   Avant cette date le formulaire était écrit en français dans le fichier. Tant
   que la page n'avait pas de menu cela ne se voyait pas ; depuis qu'elle porte
   l'en-tête du site, un visiteur anglophone lisait un menu anglais au-dessus
   d'un formulaire français. */
session_boot();
$lg = strtolower(trim((string)($_GET['lang'] ?? '')));
if (in_array($lg, I18n::$langs, true)) $_SESSION['lv_cat_lang'] = $lg;
$lg = (string)($_SESSION['lv_cat_lang'] ?? '');
I18n::setLang(in_array($lg, I18n::$langs, true) ? $lg : I18n::browserLang());

$message = '';
$erreur  = false;
$envoye  = false;
$v       = [];   // ce qui a été saisi, pour ne pas le perdre en cas d'erreur

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $r  = Offers::creer($_POST, $ip);
    $message = $r['message'];
    $erreur  = !$r['ok'];
    $envoye  = $r['ok'];
    if ($erreur) $v = $_POST;
}

$spectacles = Offers::spectacles();

/* LE SPECTACLE PEUT ARRIVER DÉJÀ CHOISI. [16.08.2026] Le lien posé sous la
   vidéo d'une fiche de spectacle passe son titre en `?projet=`, pour que la
   personne qui vient de regarder le teaser n'ait pas à le retaper — et surtout
   pour qu'elle l'écrive comme nous l'écrivons, sinon le rapprochement avec la
   pièce échoue côté bureau.

   ON NE FAIT CONFIANCE À RIEN DE CE QUI VIENT DE L'URL: la valeur n'est retenue
   que si elle correspond exactement à un spectacle réel. Un paramètre libre
   recopié dans le champ serait un texte choisi par le visiteur affiché tel quel
   dans notre formulaire. */
if (!$envoye && ($_GET['projet'] ?? '') !== '' && !isset($v['projet'])) {
    $demande = trim((string)$_GET['projet']);
    foreach ($spectacles as $sp) if (strcasecmp((string)$sp, $demande) === 0) { $v['projet'] = (string)$sp; break; }
}

$titre = t('dem_titre');

/* [19.08.2026] Cette page passe désormais par layout.php, l'enveloppe du site,
   et non plus par app/page_publique.php. Anna : « colocar a mesma mise en page
   da pagina catalogue nesta pagina ». Le formulaire est le prolongement d'une
   fiche du Catalogue (c'est de là qu'on y arrive), et deux mises en page
   différentes de part et d'autre d'un lien donnent l'impression d'avoir changé
   de site.

   `module` vaut « catalog » pour une seule raison : c'est ce qui pose le
   noindex dans layout.php, que l'ancienne enveloppe posait aussi. Le
   comportement d'indexation ne change donc pas.

   app/page_publique.php reste en place et sert toujours le portail
   d'advancing : il n'est pas touché. */
$page = [
    'id' => 0, 'module' => 'catalog', 'template' => 'standard',
    'title_fr' => $titre, 'title_en' => $titre,
    'body_fr' => '', 'body_en' => '',
    'slug_fr' => 'demande.php', 'slug_en' => 'demande.php',
];

ob_start();
?>
<section class="section demande">
  <div class="wrap narrow">
    <h1><?= e($titre) ?></h1>

<?php if ($envoye): ?>
  <div class="msg"><?= e($message) ?></div>
  <p><?= e(t('dem_merci')) ?></p>

<?php else: ?>

  <p class="chapo"><?= e(t('dem_chapo')) ?></p>

  <?php if ($message): ?><div class="msg <?= $erreur ? 'err' : '' ?>"><?= e($message) ?></div><?php endif; ?>

  <form method="post" action="demande.php">
    <input type="hidden" name="_t" value="<?= e(Offers::jetonTemps()) ?>">

    <?php /* Le champ piège. Caché à l'œil ET aux lecteurs d'écran — un robot
             remplit tout ce qu'il trouve, une personne ne doit jamais le voir
             ni l'entendre. */ ?>
    <div class="piege" aria-hidden="true">
      <label for="site_web"><?= e(t('dem_piege')) ?></label>
      <input type="text" id="site_web" name="site_web" tabindex="-1" autocomplete="off">
    </div>

    <h2><?= e(t('dem_s_spectacle')) ?></h2>

    <div class="ch">
      <label for="projet"><?= e(t('dem_projet')) ?></label>
      <input type="text" id="projet" name="projet" list="l-spectacles"
             value="<?= e((string)($v['projet'] ?? '')) ?>"
             placeholder="<?= e(t('dem_projet_ph')) ?>">
      <?php if ($spectacles): ?>
        <datalist id="l-spectacles">
          <?php foreach ($spectacles as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?>
        </datalist>
        <p class="cons"><?= e(t('dem_projet_cons')) ?></p>
      <?php endif; ?>
    </div>

    <div class="ch">
      <label for="date_souhaitee"><?= e(t('dem_date')) ?></label>
      <input type="date" id="date_souhaitee" name="date_souhaitee"
             value="<?= e((string)($v['date_souhaitee'] ?? '')) ?>">
    </div>

    <div class="ch">
      <label for="date_texte"><?= e(t('dem_periode')) ?></label>
      <input type="text" id="date_texte" name="date_texte"
             value="<?= e((string)($v['date_texte'] ?? '')) ?>"
             placeholder="<?= e(t('dem_periode_ph')) ?>">
      <p class="cons"><?= e(t('dem_periode_cons')) ?></p>
    </div>

    <div class="ch deux">
      <div>
        <label for="representations"><?= e(t('dem_repr')) ?></label>
        <input type="number" id="representations" name="representations" min="1" max="99"
               value="<?= e((string)($v['representations'] ?? '')) ?>">
      </div>
      <div>
        <label for="budget"><?= e(t('dem_budget')) ?></label>
        <div class="avec">
          <input type="text" id="budget" name="budget" value="<?= e((string)($v['budget'] ?? '')) ?>">
          <select name="devise" aria-label="Devise">
            <option value="EUR" <?= ($v['devise'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR</option>
            <option value="CHF" <?= ($v['devise'] ?? '') === 'CHF' ? 'selected' : '' ?>>CHF</option>
          </select>
        </div>
        <p class="cons"><?= e(t('dem_budget_cons')) ?></p>
      </div>
    </div>

    <h2><?= e(t('dem_s_lieu')) ?></h2>

    <div class="ch">
      <label for="venue"><?= e(t('dem_lieu')) ?></label>
      <input type="text" id="venue" name="venue" value="<?= e((string)($v['venue'] ?? '')) ?>">
    </div>

    <div class="ch deux">
      <div>
        <label for="ville"><?= e(t('dem_ville')) ?></label>
        <input type="text" id="ville" name="ville" value="<?= e((string)($v['ville'] ?? '')) ?>">
      </div>
      <div>
        <label for="pays"><?= e(t('dem_pays')) ?></label>
        <input type="text" id="pays" name="pays" value="<?= e((string)($v['pays'] ?? '')) ?>">
      </div>
    </div>

    <div class="ch">
      <label for="venue_url"><?= e(t('dem_site')) ?></label>
      <input type="text" id="venue_url" name="venue_url" value="<?= e((string)($v['venue_url'] ?? '')) ?>">
    </div>

    <h2><?= e(t('dem_s_vous')) ?></h2>

    <div class="ch deux">
      <div>
        <label for="contact_nom"><?= e(t('dem_nom')) ?> <span class="ob">·</span></label>
        <input type="text" id="contact_nom" name="contact_nom" required
               value="<?= e((string)($v['contact_nom'] ?? '')) ?>">
      </div>
      <div>
        <label for="contact_role"><?= e(t('dem_fonction')) ?></label>
        <input type="text" id="contact_role" name="contact_role"
               value="<?= e((string)($v['contact_role'] ?? '')) ?>">
      </div>
    </div>

    <div class="ch">
      <label for="structure"><?= e(t('dem_structure')) ?></label>
      <input type="text" id="structure" name="structure" value="<?= e((string)($v['structure'] ?? '')) ?>">
    </div>

    <div class="ch deux">
      <div>
        <label for="contact_email"><?= e(t('dem_email')) ?> <span class="ob">·</span></label>
        <input type="email" id="contact_email" name="contact_email" required
               value="<?= e((string)($v['contact_email'] ?? '')) ?>">
      </div>
      <div>
        <label for="contact_tel"><?= e(t('dem_tel')) ?></label>
        <input type="text" id="contact_tel" name="contact_tel"
               value="<?= e((string)($v['contact_tel'] ?? '')) ?>">
      </div>
    </div>

    <div class="ch">
      <label for="message"><?= e(t('dem_message')) ?></label>
      <textarea id="message" name="message" rows="5"><?= e((string)($v['message'] ?? '')) ?></textarea>
      <p class="cons"><?= e(t('dem_message_cons')) ?></p>
    </div>

    <button type="submit"><?= e(t('dem_envoyer')) ?></button>
    <p class="pied"><?= e(t('dem_pied_a')) ?> <span class="ob">·</span> <?= e(t('dem_pied_b')) ?></p>
  </form>
<?php endif; ?>

<style>
/* Les règles de forme venaient de app/page_publique.php, qui avait sa propre
   feuille. En passant sous layout.php elles sont reprises ici, exprimées avec
   les variables du site : la police, le noir et le gris sont ceux du thème,
   plus ceux de l'ancienne enveloppe. */
.demande .ch{padding:15px 0;border-bottom:var(--trait) solid var(--line)}
.demande label{display:block;font-weight:600;font-size:15.5px;margin-bottom:5px}
.demande .ob{color:var(--error);font-weight:700}
.demande .cons{font-size:13.5px;color:var(--grey);margin:0 0 8px}
.demande .chapo{font-size:17px;line-height:1.45;padding-bottom:16px;
  border-bottom:var(--trait) solid var(--line);margin-bottom:20px}
.demande .msg{background:var(--wash);border-left:3px solid var(--ink);
  padding:12px 15px;margin:0 0 22px}
.demande .msg.err{border-left-color:var(--error)}
.demande .pied{margin-top:14px;font-size:13px;color:var(--grey)}
.demande input[type=text],.demande input[type=number],.demande input[type=date],
.demande input[type=time],.demande textarea,.demande select{
  width:100%;padding:9px 11px;font:inherit;font-size:15px;
  border:var(--trait) solid var(--line);border-radius:5px;
  background:var(--paper);color:var(--ink)}
.demande textarea{resize:vertical}
.demande input:focus,.demande textarea:focus,.demande select:focus{
  outline:var(--trait-fort) solid var(--ink);outline-offset:1px;border-color:transparent}
.demande button{margin-top:26px;padding:11px 26px;font:inherit;font-weight:600;
  font-size:15px;border:0;border-radius:5px;background:var(--ink);
  color:var(--paper);cursor:pointer}
.demande button:hover{opacity:.88}
.demande button:focus-visible{outline:var(--trait-fort) solid var(--ink);outline-offset:2px}
.demande h2{font-size:15px;text-transform:uppercase;letter-spacing:.1em;
  color:var(--grey);margin:34px 0 4px;font-weight:600}

.piege{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.ch.deux{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.ch.deux .ch{padding:0;border:0}
.avec{display:flex;gap:6px}
.avec input{flex:1}
.avec select{width:auto}
@media (max-width:520px){.ch.deux{grid-template-columns:1fr}}
</style>

  </div>
</section>
<?php
require_once LV_APP . '/views/partials/helpers.php';
$content = (string)ob_get_clean();
$meta = [
    'title' => $titre . ' — ' . setting('site_name', 'Le Voisin'),
    'desc'  => '', 'url' => '', 'og' => '', 'alt' => [],
];
include LV_APP . '/views/layout.php';

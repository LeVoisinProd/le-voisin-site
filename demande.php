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
$titre = 'Demande de booking';

ob_start();
?>

<?php if ($envoye): ?>
  <div class="msg"><?= e($message) ?></div>
  <p>Nous lisons chaque demande. Si votre projet de date avance de votre côté
     entre-temps, écrivez-nous: cela nous aide à donner la priorité.</p>

<?php else: ?>

  <p class="chapo">Vous souhaitez programmer un de nos spectacles. Ce formulaire
     remplace l'e-mail: il nous arrive au même endroit que toutes les autres
     demandes, ce qui veut dire qu'il ne se perd pas.</p>

  <?php if ($message): ?><div class="msg <?= $erreur ? 'err' : '' ?>"><?= e($message) ?></div><?php endif; ?>

  <form method="post" action="demande.php">
    <input type="hidden" name="_t" value="<?= e(Offers::jetonTemps()) ?>">

    <?php /* Le champ piège. Caché à l'œil ET aux lecteurs d'écran — un robot
             remplit tout ce qu'il trouve, une personne ne doit jamais le voir
             ni l'entendre. */ ?>
    <div class="piege" aria-hidden="true">
      <label for="site_web">Ne rien écrire ici</label>
      <input type="text" id="site_web" name="site_web" tabindex="-1" autocomplete="off">
    </div>

    <h2>Le spectacle</h2>

    <div class="ch">
      <label for="projet">Quel spectacle vous intéresse</label>
      <input type="text" id="projet" name="projet" list="l-spectacles"
             value="<?= e((string)($v['projet'] ?? '')) ?>"
             placeholder="Le titre, ou ce dont vous vous souvenez">
      <?php if ($spectacles): ?>
        <datalist id="l-spectacles">
          <?php foreach ($spectacles as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?>
        </datalist>
        <p class="cons">La liste propose nos spectacles, mais vous pouvez écrire autre chose.</p>
      <?php endif; ?>
    </div>

    <div class="ch">
      <label for="date_souhaitee">Une date, si vous en avez une</label>
      <input type="date" id="date_souhaitee" name="date_souhaitee"
             value="<?= e((string)($v['date_souhaitee'] ?? '')) ?>">
    </div>

    <div class="ch">
      <label for="date_texte">Ou la période, dans vos mots</label>
      <input type="text" id="date_texte" name="date_texte"
             value="<?= e((string)($v['date_texte'] ?? '')) ?>"
             placeholder="par exemple : une semaine en mars 2027, plutôt en début de mois">
      <p class="cons">Mieux vaut une période honnête qu'une date inventée.</p>
    </div>

    <div class="ch deux">
      <div>
        <label for="representations">Combien de représentations</label>
        <input type="number" id="representations" name="representations" min="1" max="99"
               value="<?= e((string)($v['representations'] ?? '')) ?>">
      </div>
      <div>
        <label for="budget">Le budget dont vous disposez</label>
        <div class="avec">
          <input type="text" id="budget" name="budget" value="<?= e((string)($v['budget'] ?? '')) ?>">
          <select name="devise" aria-label="Devise">
            <option value="EUR" <?= ($v['devise'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR</option>
            <option value="CHF" <?= ($v['devise'] ?? '') === 'CHF' ? 'selected' : '' ?>>CHF</option>
          </select>
        </div>
        <p class="cons">Même approximatif. Cela nous évite de vous proposer hors budget.</p>
      </div>
    </div>

    <h2>Le lieu</h2>

    <div class="ch">
      <label for="venue">Le lieu</label>
      <input type="text" id="venue" name="venue" value="<?= e((string)($v['venue'] ?? '')) ?>">
    </div>

    <div class="ch deux">
      <div>
        <label for="ville">Ville</label>
        <input type="text" id="ville" name="ville" value="<?= e((string)($v['ville'] ?? '')) ?>">
      </div>
      <div>
        <label for="pays">Pays</label>
        <input type="text" id="pays" name="pays" value="<?= e((string)($v['pays'] ?? '')) ?>">
      </div>
    </div>

    <div class="ch">
      <label for="venue_url">Site du lieu</label>
      <input type="text" id="venue_url" name="venue_url" value="<?= e((string)($v['venue_url'] ?? '')) ?>">
    </div>

    <h2>Vous</h2>

    <div class="ch deux">
      <div>
        <label for="contact_nom">Votre nom <span class="ob">·</span></label>
        <input type="text" id="contact_nom" name="contact_nom" required
               value="<?= e((string)($v['contact_nom'] ?? '')) ?>">
      </div>
      <div>
        <label for="contact_role">Votre fonction</label>
        <input type="text" id="contact_role" name="contact_role"
               value="<?= e((string)($v['contact_role'] ?? '')) ?>">
      </div>
    </div>

    <div class="ch">
      <label for="structure">La structure</label>
      <input type="text" id="structure" name="structure" value="<?= e((string)($v['structure'] ?? '')) ?>">
    </div>

    <div class="ch deux">
      <div>
        <label for="contact_email">E-mail <span class="ob">·</span></label>
        <input type="email" id="contact_email" name="contact_email" required
               value="<?= e((string)($v['contact_email'] ?? '')) ?>">
      </div>
      <div>
        <label for="contact_tel">Téléphone</label>
        <input type="text" id="contact_tel" name="contact_tel"
               value="<?= e((string)($v['contact_tel'] ?? '')) ?>">
      </div>
    </div>

    <div class="ch">
      <label for="message">Ce que vous voulez nous dire</label>
      <textarea id="message" name="message" rows="5"><?= e((string)($v['message'] ?? '')) ?></textarea>
      <p class="cons">Le contexte du festival ou de la saison, les contraintes de plateau,
         ce qui vous a fait penser à ce spectacle.</p>
    </div>

    <button type="submit">Envoyer la demande</button>
    <p class="pied">Les deux champs marqués <span class="ob">·</span> sont nécessaires:
       sans eux nous ne saurions pas à qui répondre.</p>
  </form>
<?php endif; ?>

<style>
.piege{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.ch.deux{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.ch.deux .ch{padding:0;border:0}
.avec{display:flex;gap:6px}
.avec input{flex:1}
.avec select{width:auto}
.msg.err{border-left-color:#e2653a}
@media (max-width:520px){.ch.deux{grid-template-columns:1fr}}
</style>
<?php
$corps = (string)ob_get_clean();
require __DIR__ . '/app/page_publique.php';

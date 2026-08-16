<?php
/**
 * Écran Offres — le pipeline des demandes entrantes. [16.08.2026]
 *
 * Ce que le formulaire public (demande.php) dépose arrive ici. L'écran répond
 * à une seule question, et son ordre de tri est fait pour elle: QU'EST-CE QUI
 * ATTEND UNE RÉPONSE. Les nouvelles d'abord, les classées à la fin — un tri
 * par date seule enterrerait une demande de la semaine dernière sous cinq
 * refus d'hier.
 *
 * LA CONVERSION EST LE GESTE QUI COMPTE. « Une offre acceptée se convertit en
 * booking avec tous les détails repris », dit la spécification. Elle crée la
 * date en `option` et non en `confirmed`: accepter une demande n'est pas avoir
 * un contrat signé, et confondre les deux ferait compter comme acquises des
 * dates qui ne le sont pas — précisément le reproche fait au tableur d'avant.
 */
declare(strict_types=1);

$STATUTS = Offers::STATUTS;
$peutEcrire = dash_droit('offres', dash_role()) === 'ecrit';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    dash_exige_ecriture('offres');

    $act = (string)($_POST['act'] ?? '');
    $oid = (int)($_POST['offre'] ?? 0);

    if ($act === 'statut' && $oid > 0) {
        $cp = trim((string)($_POST['contre_prix'] ?? ''));
        Offers::statut($oid, (string)($_POST['st'] ?? ''),
                       $cp !== '' ? (float)str_replace(',', '.', $cp) : null,
                       isset($_POST['notes']) ? (string)$_POST['notes'] : null);
        dash_flash('Demande mise à jour.');

    } elseif ($act === 'convertir' && $oid > 0) {
        $bid = Offers::convertir($oid);
        if ($bid > 0) {
            dash_flash('Convertie en date. Elle est en option, pas confirmée.');
            redirect('/dashboard.php?e=bookings&b=' . $bid);
        }
        dash_flash('Conversion impossible.', 'err');
    }
    redirect('/dashboard.php?e=offres');
}

$filtre = (string)($_GET['f'] ?? '');
$offres = Offers::liste($filtre);
$n      = Offers::compter();
$total  = array_sum($n);

$sousTitre = $n['nouvelle'] > 0
    ? '<strong>' . $n['nouvelle'] . '</strong> nouvelle' . ($n['nouvelle'] > 1 ? 's' : '') . ' à traiter'
    : 'rien de nouveau';

dash_haut('offres', $sousTitre);
?>

<div class="filtres">
  <a href="/dashboard.php?e=offres" class="<?= $filtre === '' ? 'ici' : '' ?>">toutes (<?= $total ?>)</a>
  <?php foreach ($STATUTS as $k => $lib): if (!$n[$k]) continue; ?>
    <a href="/dashboard.php?e=offres&amp;f=<?= $k ?>" class="<?= $filtre === $k ? 'ici' : '' ?>"><?= e($lib) ?> (<?= $n[$k] ?>)</a>
  <?php endforeach; ?>
</div>

<?php if (!$offres): ?>
  <p class="vide">Aucune demande<?= $filtre ? ' dans cet état' : '' ?>.
     <?php if (!$filtre): ?><br><span class="sec">Le formulaire est à l'adresse
     <code>/demande.php</code>: c'est le lien à mettre dans une signature d'e-mail,
     sur le site, ou dans un dossier de diffusion.</span><?php endif; ?></p>
<?php else: ?>

<?php foreach ($offres as $o): $oid = (int)$o['id']; ?>
  <div class="offre st-<?= e($o['statut']) ?>">
    <div class="tete-o">
      <div>
        <span class="quand"><?= e(date('d.m.Y', strtotime((string)$o['cree_a']))) ?></span>
        <strong><?= e($o['projet'] ?: 'spectacle non précisé') ?></strong>
        <?php if ($o['venue']): ?> · <?= e((string)$o['venue']) ?><?php endif; ?>
        <?php if ($o['ville']): ?>, <?= e((string)$o['ville']) ?><?php endif; ?>
        <?php if ($o['pays']): ?> (<?= e((string)$o['pays']) ?>)<?php endif; ?>
      </div>
      <span class="et et-o<?= e($o['statut']) ?>"><?= e($STATUTS[$o['statut']]) ?></span>
    </div>

    <div class="corps-o">
      <dl>
        <?php if ($o['date_souhaitee'] || $o['date_texte']): ?>
          <dt>Quand</dt><dd>
            <?= $o['date_souhaitee'] ? e(date('d.m.Y', strtotime((string)$o['date_souhaitee']))) : '' ?>
            <?= $o['date_texte'] ? '<span class="sec">' . e((string)$o['date_texte']) . '</span>' : '' ?>
            <?php if ($o['representations']): ?> · <?= (int)$o['representations'] ?> représentation<?= (int)$o['representations'] > 1 ? 's' : '' ?><?php endif; ?>
          </dd>
        <?php endif; ?>

        <?php if ($o['budget'] !== null): ?>
          <dt>Budget annoncé</dt>
          <dd><strong><?= number_format((float)$o['budget'],2,',',' ') ?> <?= e($o['devise']) ?></strong>
            <?php if ($o['contre_prix'] !== null): ?>
              · contre-proposé <strong><?= number_format((float)$o['contre_prix'],2,',',' ') ?></strong>
            <?php endif; ?>
          </dd>
        <?php endif; ?>

        <dt>Qui</dt>
        <dd><?= e($o['contact_nom'] ?? '') ?><?php if ($o['contact_role']): ?>, <?= e((string)$o['contact_role']) ?><?php endif; ?>
          <?php if ($o['structure']): ?><br><?= e((string)$o['structure']) ?><?php endif; ?>
          <br><a href="mailto:<?= e((string)$o['contact_email']) ?>"><?= e((string)$o['contact_email']) ?></a>
          <?php if ($o['contact_tel']): ?> · <?= e((string)$o['contact_tel']) ?><?php endif; ?>
        </dd>

        <?php if ($o['message']): ?>
          <dt>Ce qu'ils écrivent</dt><dd class="msg-o"><?= nl2br(e((string)$o['message'])) ?></dd>
        <?php endif; ?>

        <?php if ($o['notes_internes']): ?>
          <dt>Notes</dt><dd class="ni"><?= nl2br(e((string)$o['notes_internes'])) ?></dd>
        <?php endif; ?>

        <?php if ($o['booking_id']): ?>
          <dt>Date née d'elle</dt>
          <dd><a href="/dashboard.php?e=bookings&amp;b=<?= (int)$o['booking_id'] ?>">voir la date</a></dd>
        <?php endif; ?>
      </dl>

      <?php if ($peutEcrire && !$o['booking_id']): ?>
        <form method="post" action="/dashboard.php?e=offres" class="ajl">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="act" value="statut">
          <input type="hidden" name="offre" value="<?= $oid ?>">
          <input type="text" name="contre_prix" placeholder="Contre-proposer" size="10"
                 value="<?= $o['contre_prix'] !== null ? e((string)$o['contre_prix']) : '' ?>">
          <input type="text" name="notes" placeholder="Note interne" size="24"
                 value="<?= e((string)($o['notes_internes'] ?? '')) ?>">
          <select name="st">
            <?php foreach ($STATUTS as $k => $lib): ?>
              <option value="<?= $k ?>" <?= $o['statut'] === $k ? 'selected' : '' ?>><?= e($lib) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit">enregistrer</button>
        </form>

        <form method="post" action="/dashboard.php?e=offres" class="conv"
              onsubmit="return confirm('Créer une date à partir de cette demande ?')">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="act" value="convertir">
          <input type="hidden" name="offre" value="<?= $oid ?>">
          <button type="submit">convertir en date</button>
          <span class="sec pt">Crée un booking en <strong>option</strong>, avec tout ce qui
            est dit ici recopié dans ses notes.</span>
        </form>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php endif; ?>

<style>
.filtres{display:flex;gap:14px;flex-wrap:wrap;padding:0 0 18px;font-size:13.5px}
.filtres a{color:var(--doux);text-decoration:none}
.filtres a.ici{color:var(--encre);font-weight:600}
.offre{border:1px solid var(--trait);border-radius:6px;margin-bottom:14px;overflow:hidden}
.offre.st-nouvelle{border-left:3px solid var(--jaune,#FFD24D)}
.offre.st-acceptee{border-left:3px solid #7bb33a}
.offre.st-refusee,.offre.st-sans_suite{opacity:.62}
.tete-o{display:flex;justify-content:space-between;align-items:center;gap:12px;
  padding:11px 16px;background:var(--fond2);font-size:14.5px}
.quand{color:var(--doux);font-size:12.5px;margin-right:8px}
.corps-o{padding:14px 16px}
.corps-o dl{display:grid;grid-template-columns:130px 1fr;gap:5px 14px;margin:0 0 12px;font-size:14px}
.corps-o dt{color:var(--doux);font-size:12.5px;padding-top:2px}
.corps-o dd{margin:0}
.msg-o{white-space:normal;max-width:70ch}
.ni{color:#9a7a2a}
.conv{margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.conv button{padding:6px 15px;font-size:13px}
.et-onouvelle{border-color:#d9a800;font-weight:600}
.et-oacceptee{border-color:#7bb33a;font-weight:600}
.et{font-size:12px;padding:2px 7px;border-radius:3px;white-space:nowrap;border:1px solid var(--trait)}
@media (max-width:640px){.corps-o dl{grid-template-columns:1fr}.corps-o dt{padding-top:8px}}
</style>

<?php dash_bas();

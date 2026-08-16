<?php
/**
 * L'écran servi quand le rôle ne donne pas accès. [16.08.2026]
 *
 * POURQUOI IL DIT LE RÔLE ET À QUI S'ADRESSER. Un « 403 Interdit » nu laisse
 * la personne devant un mur sans savoir si elle s'est trompée d'adresse, si
 * son compte est cassé, ou si c'est normal. Elle écrit alors à Anna pour
 * demander, ce qui coûte deux messages et une journée.
 *
 * Ici la page dit trois choses: ce que vous êtes, ce que cet écran demande, et
 * que c'est un réglage et non une panne. La demande d'accès devient une phrase
 * au lieu d'une enquête.
 *
 * CE QU'ELLE NE DIT PAS: ce que l'écran contient. Le nom de l'écran suffit,
 * son contenu ne regarde pas quelqu'un qui n'y a pas droit.
 */
declare(strict_types=1);

$monRole = dash_role();

$dits = [
    'direction'  => 'direction',
    'production' => 'production',
    'lecture'    => 'lecture seule',
];

/* Quels rôles ouvrent cet écran: cela dit à la personne ce qu'il faut
   demander, sans qu'elle ait à deviner. */
$ouvrent = [];
foreach (DASH_ROLES as $r) {
    if (dash_droit($clef, $r) !== '') $ouvrent[] = $dits[$r] ?? $r;
}

dash_haut($clef);
?>
<div class="avis">
  <h2>Cet écran n'est pas ouvert à votre rôle</h2>

  <p>Vous êtes connecté·e en <strong><?= e($dits[$monRole] ?? $monRole) ?></strong>.
     <?= e(dash_libelle($clef)) ?> demande
     <?= $ouvrent ? '<strong>' . e(implode(' ou ', $ouvrent)) . '</strong>' : 'un autre rôle' ?>.</p>

  <p>Ce n'est pas une panne: les écrans qui portent les salaires, les numéros
     AVS et les IBAN ne s'ouvrent qu'aux rôles qui en ont l'usage. C'est ce qui
     permet d'inviter quelqu'un sur le calendrier sans lui ouvrir la paie.</p>

  <p>S'il vous le faut pour travailler, demandez-le à la direction : le réglage
     se fait en un clic dans <em>Paramètres et équipe</em>.</p>
</div>
<?php
dash_bas();

<?php
/**
 * Espace collaborateur — ma fiche personnelle, version imprimable.   [V15-FICHE-PDF]
 *
 * Aucun identifiant n'est accepté dans l'adresse : cette page ne sait rendre
 * que la fiche de la personne connectée. C'est la seule garantie sérieuse
 * qu'un collaborateur ne puisse pas lire la fiche d'un autre en changeant un
 * chiffre dans l'URL.
 */
require __DIR__ . '/_inc.php';
MemberAuth::requireMember();

$m = MemberAuth::member();

header('Cache-Control: private, no-store, max-age=0');
header('Content-Type: text/html; charset=utf-8');

echo MemberSheet::page(
    $m,
    I18n::$lang,
    espace_url() . '#partie-infos',
    t('member_print_back')
);

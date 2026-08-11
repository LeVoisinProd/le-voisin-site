<?php
/**
 * Espace collaborateur — l'ancienne adresse de la fiche.   [V12-ESPACE] [V35-FICHE-ONGLET]
 *
 * La fiche de renseignements n'est plus une page à part : elle est dépliée
 * dans le premier onglet de l'accueil. L'adresse, elle, a circulé — dans des
 * courriels d'invitation, dans des marque-pages, dans la mémoire de ceux qui
 * la tapent. Elle continue donc de mener à la fiche ; simplement, elle y mène
 * par une redirection.
 *
 * Le fichier ne peut pas être supprimé : l'installateur remplace et ajoute des
 * fichiers, il n'en efface pas. Autant qu'il serve.
 */
require __DIR__ . '/_inc.php';
MemberAuth::requireMember();
redirect(espace_url() . '#partie-infos');

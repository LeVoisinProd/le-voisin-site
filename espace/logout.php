<?php
/** Espace collaborateur — déconnexion.   [V12-ESPACE] [V27-ACCES] */
require __DIR__ . '/_inc.php';

/* [V27-ACCES] Pendant une visite depuis l'administration, ce bouton ne
   déconnecte personne : il referme la visite et ramène à la fiche du
   collaborateur. La session d'administration n'a jamais été quittée. */
if (MemberAuth::visite()) {
    $__id = (int)($_SESSION['lv_member_id'] ?? 0);
    MemberAuth::visiteFermer();
    redirect('/admin/collaborator-edit.php?id=' . $__id);
}

MemberAuth::logout();
redirect('/espace/login.php');

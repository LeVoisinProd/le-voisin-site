<?php
/**
 * Fiche personnelle d'un collaborateur, version imprimable.   [V15-FICHE-PDF]
 *
 * Page volontairement nue : ni menu, ni barre latérale. Ce qui s'affiche est
 * exactement ce qui sortira sur le papier — ou dans le PDF, en choisissant
 * « Enregistrer au format PDF » comme destination dans la fenêtre d'impression.
 */
require __DIR__ . '/_inc.php';
Auth::requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$c  = DB::one('SELECT * FROM collaborators WHERE id = ?', [$id]);
if (!$c) { flash(ta('ce_notfound'), 'err'); redirect('/admin/collaborators.php'); }

// Une fiche contient un numéro AVS et un IBAN : elle n'a rien à faire dans le
// cache d'un navigateur partagé, ni dans celui d'un intermédiaire.
header('Cache-Control: private, no-store, max-age=0');
header('Content-Type: text/html; charset=utf-8');

echo MemberSheet::page(
    $c,
    I18n::$admin,
    admin_url('collaborator-edit.php?id=' . $id),
    ta('ce_print_back')
);

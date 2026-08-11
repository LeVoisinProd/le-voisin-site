<?php
require dirname(__DIR__) . '/_inc.php';
Auth::requireAdmin(true);
Auth::requireCsrf(true);

/* [V31-PRESSE] La liste d'où vient le document. Elle arrive du navigateur,
   donc on ne la croit pas sur parole : seules les deux valeurs connues
   passent, tout le reste retombe sur « doc ». Sans cette liste blanche, une
   requête bricolée pourrait ranger une ligne dans une zone inventée, où
   plus aucun écran n'irait jamais la chercher — ni pour l'afficher, ni pour
   l'effacer. */
function lv_zone_doc($valeur): string
{
    $z = trim((string)$valeur);
    return in_array($z, ['doc', 'press'], true) ? $z : Docs::ZONE_DEFAUT;
}

if (!empty($_FILES['file'])) { // upload
    $owner = explode(':', (string)($_POST['owner'] ?? ''));
    $type = $owner[0] ?? ''; $oid = (int)($owner[1] ?? 0);
    $allowed = array_merge(['page'], array_keys(Content::entities()));
    if (!in_array($type, $allowed, true) || $oid <= 0) json_out(['error' => 'owner'], 400);
    try {
        $doc = Docs::upload($_FILES['file'], $type, $oid, lv_zone_doc($_POST['zone'] ?? ''));
    } catch (Throwable $ex) {
        json_out(['error' => $ex->getMessage()], 422);
    }
    json_out(['ok' => true, 'html' => doc_item_html($doc)]);
}

$in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
$action = (string)($in['action'] ?? '');
$id = (int)($in['id'] ?? 0);

switch ($action) {
    /* [V31-DOC-LIEN] Ajouter un document qui vit ailleurs. Même contrôle de
       propriétaire que pour un fichier déposé : la ligne se range dans la
       même liste, elle doit s'attacher à la même fiche. */
    case 'link':
        $owner = explode(':', (string)($in['owner'] ?? ''));
        $type = $owner[0] ?? ''; $oid = (int)($owner[1] ?? 0);
        if (!in_array($type, array_merge(['page'], array_keys(Content::entities())), true) || $oid <= 0) {
            json_out(['error' => 'owner'], 400);
        }
        try {
            $doc = Docs::addLink((string)($in['url'] ?? ''), $type, $oid, lv_zone_doc($in['zone'] ?? ''));
        } catch (Throwable $ex) {
            json_out(['error' => $ex->getMessage()], 422);
        }
        json_out(['ok' => true, 'html' => doc_item_html($doc)]);

    /* Corriger un lien mal collé sans perdre les titres déjà saisis. On ne
       transforme jamais un fichier déposé en lien : la ligne resterait avec
       un fichier orphelin sur le disque. */
    case 'url':
        $doc = Docs::row($id);
        if (!$doc) json_out(['error' => tu('sys_doc_nf')], 404);
        if (!Docs::estLien($doc)) json_out(['error' => tu('sys_doc_url')], 422);
        $url = trim((string)($in['value'] ?? ''));
        if ($url === '' || !preg_match('#^https?://#i', $url)
            || !filter_var($url, FILTER_VALIDATE_URL) || mb_strlen($url) > 1000) {
            json_out(['error' => tu('sys_doc_url')], 422);
        }
        DB::update('documents', ['url' => $url], 'id = ?', [$id]);
        json_out(['ok' => true]);

    case 'delete':
        Docs::delete($id);
        json_out(['ok' => true]);

    case 'title':
        $lang = in_array($in['lang'] ?? '', I18n::$langs, true) ? $in['lang'] : I18n::$default;
        DB::update('documents', ['title_' . $lang => mb_substr((string)($in['value'] ?? ''), 0, 250)], 'id = ?', [$id]);
        json_out(['ok' => true]);
}
json_out(['error' => 'action'], 400);

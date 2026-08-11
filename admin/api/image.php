<?php
/** Actions sur les images de l'administration (suppression, recadrage).   [V8-CADRAGE] */
require dirname(__DIR__) . '/_inc.php';
Auth::requireAdmin(true);
Auth::requireCsrf(true);

$in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
$action = (string)($in['action'] ?? '');
$id = (int)($in['id'] ?? 0);

switch ($action) {
    case 'delete':
        Img::delete($id);
        json_out(['ok' => true]);

    case 'alt':
        $lang = in_array($in['lang'] ?? '', I18n::$langs, true) ? $in['lang'] : I18n::$default;
        DB::update('images', ['alt_' . $lang => mb_substr((string)($in['value'] ?? ''), 0, 250)], 'id = ?', [$id]);
        json_out(['ok' => true]);

    case 'info': // pour la fenêtre de recadrage
        $img = Img::row($id);
        if (!$img) json_out(['error' => 'notfound'], 404);
        $fmts = Img::conf()['crop_ui'][$img['zone']] ?? [];
        if (!$fmts) $fmts = array_keys(Img::formats());
        json_out([
            'id'     => $id,
            'orig'   => upload_url('i/' . $id . '/orig.' . $img['ext']),
            'width'  => (int)$img['width'],
            'height' => (int)$img['height'],
            'crops'  => json_decode($img['crops'] ?? '', true) ?: new stdClass(),
            'formats'=> array_values($fmts),
        ]);

    /* Enregistrement groupé : tous les cadrages réglés dans la fenêtre
       partent ensemble.   [V31-RECADRAGE]
       L'ancienne action « crop », un format à la fois, reste juste en dessous :
       une page laissée ouverte avant la mise à jour continue de fonctionner. */
    case 'crops':
        $img = Img::row($id);
        if (!$img) json_out(['error' => 'notfound'], 404);
        $lot = $in['crops'] ?? null;
        if (!is_array($lot) || !$lot)  json_out(['error' => 'empty'], 400);
        if (count($lot) > 20)          json_out(['error' => 'trop'], 400);
        $faits = Img::recropLot($img, $lot);
        if (!$faits) json_out(['error' => 'fmt'], 400);
        $img = Img::row($id);
        Img::ensure($img, 'thumb');
        json_out(['ok' => true, 'faits' => $faits, 'n' => count($faits),
                  'thumb' => Img::fileUrl($img, 'thumb', 'jpg')]);

    case 'crop':
        $img = Img::row($id);
        if (!$img) json_out(['error' => 'notfound'], 404);
        $fmt = (string)($in['fmt'] ?? '');
        if (!isset(Img::formats()[$fmt])) json_out(['error' => 'fmt'], 400);
        Img::recrop($img, $fmt, [(int)$in['x'], (int)$in['y'], (int)$in['w'], (int)$in['h']]);
        $img = Img::row($id);
        Img::ensure($img, 'thumb');
        // fileUrl() date déjà l'adresse d'après le fichier : inutile d'y
        // ajouter un second « ? », qui donnait une adresse bancale.
        json_out(['ok' => true, 'thumb' => Img::fileUrl($img, 'thumb', 'jpg')]);
}
json_out(['error' => 'action'], 400);

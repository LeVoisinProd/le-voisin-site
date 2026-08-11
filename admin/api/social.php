<?php
/**
 * Réseaux sociaux : génère l'image de publication (1080×1350) et le texte
 * pour une date de tournée, et peut les envoyer à un webhook (Make, Zapier…)
 * qui publie sur Instagram / LinkedIn.
 * [V10-CMS-BILINGUE] — messages d'erreur traduits.
 */
require dirname(__DIR__) . '/_inc.php';
Auth::requireAdmin(true);
Auth::requireCsrf(true);

$in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
$action = (string)($in['action'] ?? '');
$id = (int)($in['id'] ?? 0);

$ev = DB::one(
    'SELECT e.*, a.name AS artist_name, a.slug_en AS artist_slug, a.cover_image_id AS artist_cover,
            p.title_en AS project_title_en, p.title_fr AS project_title_fr,
            p.slug_en AS project_slug, p.cover_image_id AS project_cover
     FROM events e
     LEFT JOIN artists a ON a.id = e.artist_id
     LEFT JOIN projects p ON p.id = e.project_id
     WHERE e.id = ?', [$id]
);
if (!$ev) json_out(['error' => tu('sys_event_nf')], 404);

/** Image source : celle de l'événement, sinon projet, sinon artiste. */
function social_source_image(array $ev): ?array
{
    foreach ([$ev['image_id'] ?? null, $ev['project_cover'] ?? null, $ev['artist_cover'] ?? null] as $imgId) {
        if ($imgId && ($img = Img::row((int)$imgId))) return $img;
    }
    return null;
}

function social_caption(array $ev, string $lang): string
{
    $isFr = $lang === 'fr';
    $date = $isFr ? ($ev['date_text_fr'] ?: $ev['date_text_en']) : ($ev['date_text_en'] ?: $ev['date_text_fr']);
    $project = $isFr ? ($ev['project_title_fr'] ?: $ev['project_title_en']) : ($ev['project_title_en'] ?: $ev['project_title_fr']);
    $who = trim((string)($ev['artist_name'] ?? ''));
    $title = trim($who . ($who && $project ? ' — ' : '') . (string)$project);
    $place = trim((string)$ev['venue']) . ($ev['city'] !== '' ? ', ' . $ev['city'] : '');

    $agenda = Pages::moduleP('agenda');
    $link = $ev['venue_url'] !== '' ? $ev['venue_url'] : ($agenda ? Pages::url($agenda, $lang) : url('/' . $lang));

    $tags = ['#LeVoisin', '#OnTour'];
    if ($who) $tags[] = '#' . preg_replace('/[^A-Za-z0-9]/', '', ucwords($who));
    if ($project) $tags[] = '#' . preg_replace('/[^A-Za-z0-9]/', '', ucwords((string)$project));

    $lines = [];
    $lines[] = '🗓 ' . $date;
    if ($title) $lines[] = $title;
    if ($place) $lines[] = '📍 ' . $place;
    $lines[] = '';
    $lines[] = ($isFr ? 'Billets & infos ↳ ' : 'Tickets & info ↳ ') . $link;
    $lines[] = '';
    $lines[] = implode(' ', $tags);
    return implode("\n", $lines);
}

/** Compose l'image 1080×1350 : photo + bandeau texte + logo. */
function social_image(array $ev): string
{
    $W = 1080; $H = 1350; $imgH = 900;
    $fontB = LV_ROOT . '/assets/fonts/ttf/SpaceGrotesk-Bold.ttf';
    $fontM = LV_ROOT . '/assets/fonts/ttf/SpaceGrotesk-Medium.ttf';

    $canvas = imagecreatetruecolor($W, $H);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    $black = imagecolorallocate($canvas, 0, 0, 0);
    $grey  = imagecolorallocate($canvas, 102, 102, 99);
    $yellow = imagecolorallocate($canvas, 255, 204, 0);
    imagefill($canvas, 0, 0, $white);

    // --- Photo (recadrée pour remplir 1080×900) ---
    $src = social_source_image($ev);
    if ($src) {
        $path = Img::dir((int)$src['id']) . '/orig.' . $src['ext'];
        $im = match ($src['ext']) {
            'jpg' => @imagecreatefromjpeg($path), 'png' => @imagecreatefrompng($path), 'webp' => @imagecreatefromwebp($path), default => false,
        };
        if ($im) {
            $sw = imagesx($im); $sh = imagesy($im);
            $ratio = $W / $imgH;
            if ($sw / $sh > $ratio) { $ch = $sh; $cw = (int)($sh * $ratio); $cx = (int)(($sw - $cw) / 2); $cy = 0; }
            else { $cw = $sw; $ch = (int)($sw / $ratio); $cx = 0; $cy = (int)(($sh - $ch) / 2); }
            imagecopyresampled($canvas, $im, 0, 0, $cx, $cy, $W, $imgH, $cw, $ch);
            imagedestroy($im);
        }
    } else {
        imagefilledrectangle($canvas, 0, 0, $W, $imgH, $black);
    }

    // --- Texte ---
    $pad = 64;
    $maxW = $W - 2 * $pad;
    $fit = function (string $text, string $font, int $size, int $min = 22) use ($maxW) {
        while ($size > $min) {
            $b = imagettfbbox($size, 0, $font, $text);
            if (($b[2] - $b[0]) <= $maxW) break;
            $size -= 2;
        }
        return $size;
    };

    $isFr = true;
    $date = mb_strtoupper($ev['date_text_fr'] ?: $ev['date_text_en']);
    $who = trim((string)($ev['artist_name'] ?? ''));
    $project = $ev['project_title_fr'] ?: $ev['project_title_en'];
    $place = trim((string)$ev['venue']) . ($ev['city'] !== '' ? ' — ' . $ev['city'] : '');

    $y = $imgH + 78;
    // Date sur fond jaune
    $ds = $fit($date, $fontB, 30);
    $b = imagettfbbox($ds, 0, $fontB, $date);
    $tw = $b[2] - $b[0];
    imagefilledrectangle($canvas, $pad - 14, $y - $ds - 14, $pad + $tw + 14, $y + 14, $yellow);
    imagettftext($canvas, $ds, 0, $pad, $y, $black, $fontB, $date);
    $y += 84;

    if ($who !== '') {
        $s = $fit($who, $fontB, 56);
        imagettftext($canvas, $s, 0, $pad, $y, $black, $fontB, $who);
        $y += (int)($s * 1.15) + 6;
    }
    if ($project) {
        $s = $fit((string)$project, $fontM, 42);
        imagettftext($canvas, $s, 0, $pad, $y, $black, $fontM, (string)$project);
        $y += (int)($s * 1.2) + 8;
    }
    if ($place !== '') {
        $s = $fit($place, $fontM, 30);
        imagettftext($canvas, $s, 0, $pad, $y, $grey, $fontM, $place);
    }

    // --- Logo + site en bas ---
    $logoPath = LV_ROOT . '/assets/img/logo-levoisin.png';
    if (is_file($logoPath) && ($logo = @imagecreatefrompng($logoPath))) {
        $lh = 52; $lw = (int)(imagesx($logo) * $lh / imagesy($logo));
        imagecopyresampled($canvas, $logo, $pad, $H - $pad - $lh, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
        imagedestroy($logo);
    }
    $siteTxt = preg_replace('~^https?://~', '', rtrim(cfg('base_url', 'le-voisin.com'), '/'));
    if (preg_match('~^(\\d+\\.|localhost|127\\.)~', $siteTxt)) $siteTxt = 'le-voisin.com';
    $b = imagettfbbox(24, 0, $fontM, $siteTxt);
    imagettftext($canvas, 24, 0, $W - $pad - ($b[2] - $b[0]), $H - $pad - 14, $grey, $fontM, $siteTxt);

    $dir = LV_UPLOADS . '/social';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $file = $dir . '/event-' . $ev['id'] . '.jpg';
    imagejpeg($canvas, $file, 88);
    imagedestroy($canvas);
    return upload_url('social/event-' . $ev['id'] . '.jpg') . '?t=' . time();
}

switch ($action) {
    case 'generate':
        $imgUrl = social_image($ev);
        json_out([
            'ok' => true,
            'image' => $imgUrl,
            'caption_fr' => social_caption($ev, 'fr'),
            'caption_en' => social_caption($ev, 'en'),
        ]);

    case 'push':
        $webhook = trim(setting('social_webhook', ''));
        if ($webhook === '' || !filter_var($webhook, FILTER_VALIDATE_URL)) {
            json_out(['error' => tu('sys_no_webhook')], 422);
        }
        $imgUrl = social_image($ev);
        $payload = json_encode([
            'type'       => 'event',
            'event_id'   => (int)$ev['id'],
            'image_url'  => preg_replace('/\?t=\d+$/', '', $imgUrl),
            'caption'    => (string)($in['caption'] ?? social_caption($ev, 'fr')),
            'caption_en' => social_caption($ev, 'en'),
            'date_text'  => $ev['date_text_fr'] ?: $ev['date_text_en'],
            'artist'     => $ev['artist_name'],
            'project'    => $ev['project_title_fr'] ?: $ev['project_title_en'],
            'venue'      => $ev['venue'],
            'city'       => $ev['city'],
            'venue_url'  => $ev['venue_url'],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($webhook);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false || $code >= 400) {
            json_out(['error' => tu('sys_webhook_err', (string)($err ?: $code))], 502);
        }
        json_out(['ok' => true, 'status' => $code]);
}
json_out(['error' => 'action'], 400);

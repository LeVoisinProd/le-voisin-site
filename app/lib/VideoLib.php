<?php
/**
 * Vidéos YouTube / Vimeo / Dailymotion : analyse d'URL, oEmbed, flux de chaîne.
 * [V10-CMS-BILINGUE] — messages de téléversement traduits (clefs « sys_vid_… »).
 */
class VideoLib
{
    /** Détecte le fournisseur et l'identifiant depuis une URL collée. */
    public static function parse(string $url): ?array
    {
        $url = trim($url);
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~', $url, $m)) {
            return ['provider' => 'youtube', 'vid' => $m[1]];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return ['provider' => 'vimeo', 'vid' => $m[1]];
        }
        if (preg_match('~(?:dailymotion\.com/video/|dai\.ly/)([A-Za-z0-9]+)~', $url, $m)) {
            return ['provider' => 'dailymotion', 'vid' => $m[1]];
        }
        return null;
    }

    private static function httpGet(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_USERAGENT      => 'LeVoisinCMS/1.0',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code < 400) ? $body : null;
    }

    /** Titre + vignette via oEmbed (sans clé d'API). */
    public static function oembed(string $provider, string $vid, string $url): array
    {
        $watch = self::watchUrl($provider, $vid);
        $endpoint = match ($provider) {
            'youtube'     => 'https://www.youtube.com/oembed?format=json&url=' . urlencode($watch),
            'vimeo'       => 'https://vimeo.com/api/oembed.json?url=' . urlencode($watch),
            'dailymotion' => 'https://www.dailymotion.com/services/oembed?format=json&url=' . urlencode($watch),
            default       => null,
        };
        $title = ''; $thumb = '';
        if ($endpoint && ($body = self::httpGet($endpoint))) {
            $data = json_decode($body, true) ?: [];
            $title = (string)($data['title'] ?? '');
            $thumb = (string)($data['thumbnail_url'] ?? '');
        }
        if ($thumb === '' && $provider === 'youtube') {
            $thumb = 'https://i.ytimg.com/vi/' . $vid . '/hqdefault.jpg';
        }
        return ['title' => $title, 'thumb' => $thumb];
    }

    public static function watchUrl(string $provider, string $vid): string
    {
        return match ($provider) {
            'youtube'     => 'https://www.youtube.com/watch?v=' . $vid,
            'vimeo'       => 'https://vimeo.com/' . $vid,
            'dailymotion' => 'https://www.dailymotion.com/video/' . $vid,
            default       => '',
        };
    }

    /** URL d'intégration (versions respectueuses de la vie privée). */
    public static function embedUrl(string $provider, string $vid): string
    {
        return match ($provider) {
            'youtube'     => 'https://www.youtube-nocookie.com/embed/' . $vid . '?rel=0',
            'vimeo'       => 'https://player.vimeo.com/video/' . $vid . '?dnt=1',
            'dailymotion' => 'https://www.dailymotion.com/embed/video/' . $vid,
            default       => '',
        };
    }

    /** Dernières vidéos d'une chaîne YouTube (flux RSS public, sans clé d'API). */
    public static function youtubeFeed(string $channelId): array
    {
        $channelId = trim($channelId);
        if ($channelId === '') return [];
        $xml = self::httpGet('https://www.youtube.com/feeds/videos.xml?channel_id=' . urlencode($channelId));
        if (!$xml) return [];
        $out = [];
        try {
            $feed = new SimpleXMLElement($xml);
            foreach ($feed->entry as $entry) {
                $yt = $entry->children('http://www.youtube.com/xml/schemas/2015');
                $media = $entry->children('http://search.yahoo.com/mrss/');
                $vid = (string)$yt->videoId;
                if ($vid === '') continue;
                $thumb = '';
                if ($media->group && $media->group->thumbnail) {
                    $thumb = (string)$media->group->thumbnail->attributes()['url'];
                }
                $out[] = [
                    'provider' => 'youtube',
                    'vid'      => $vid,
                    'title'    => (string)$entry->title,
                    'thumb'    => $thumb ?: ('https://i.ytimg.com/vi/' . $vid . '/hqdefault.jpg'),
                ];
            }
        } catch (Throwable $e) {
            return [];
        }
        return $out;
    }

    public static function forOwner(string $ownerType, int $ownerId): array
    {
        return DB::all(
            'SELECT * FROM videos WHERE owner_type=? AND owner_id=? ORDER BY sort, id',
            [$ownerType, $ownerId]
        );
    }

    public const MAX_SIZE = 300 * 1024 * 1024; // 300 Mo

    private const EXT_MAP = ['mp4' => 'mp4', 'm4v' => 'mp4', 'webm' => 'webm', 'mov' => 'mp4', 'ogv' => 'ogv'];

    /** Enregistre une vidéo téléversée (auto-hébergée). Retourne la ligne créée. */
    public static function storeUpload(array $file, string $ownerType, int $ownerId): array
    {
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ($err) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => tu('sys_vid_srv'),
                UPLOAD_ERR_PARTIAL  => tu('sys_vid_partial'),
                UPLOAD_ERR_NO_FILE  => tu('sys_no_file'),
                default             => tu('sys_vid_err'),
            });
        }
        if (($file['size'] ?? 0) > self::MAX_SIZE) {
            throw new RuntimeException(tu('sys_vid_big'));
        }
        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!isset(self::EXT_MAP[$ext])) {
            throw new RuntimeException(tu('sys_vid_formats'));
        }
        $mime = function_exists('mime_content_type') ? (@mime_content_type($file['tmp_name']) ?: '') : '';
        if ($mime !== '' && !str_starts_with($mime, 'video/')) {
            throw new RuntimeException(tu('sys_vid_invalid'));
        }
        $storeExt = self::EXT_MAP[$ext];

        $sort = 1 + (int)DB::val('SELECT COALESCE(MAX(sort),0) FROM videos WHERE owner_type=? AND owner_id=?', [$ownerType, $ownerId]);
        $id = DB::insert('videos', [
            'owner_type' => $ownerType, 'owner_id' => $ownerId,
            'provider' => 'file', 'vid' => '', 'url' => '',
            'title' => mb_substr(pathinfo((string)($file['name'] ?? 'video'), PATHINFO_FILENAME), 0, 250),
            'thumb' => '', 'sort' => $sort,
        ]);
        $dir = LV_UPLOADS . '/v/' . $id;
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            DB::delete('videos', 'id = ?', [$id]);
            throw new RuntimeException(tu('sys_vid_dir'));
        }
        $dest = $dir . '/video.' . $storeExt;
        if (!move_uploaded_file($file['tmp_name'], $dest) && !rename($file['tmp_name'], $dest)) {
            DB::delete('videos', 'id = ?', [$id]);
            throw new RuntimeException(tu('sys_vid_save'));
        }
        $url = upload_url('v/' . $id . '/video.' . $storeExt);
        DB::update('videos', ['vid' => $storeExt, 'url' => $url], 'id = ?', [$id]);
        return DB::one('SELECT * FROM videos WHERE id = ?', [$id]);
    }

    /** Supprime une vidéo (ligne + fichier auto-hébergé le cas échéant). */
    public static function remove(int $id): void
    {
        $dir = LV_UPLOADS . '/v/' . $id;
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
            @rmdir($dir);
        }
        DB::delete('videos', 'id = ?', [$id]);
    }
}

<?php
/**
 * Gestion des images : upload, formats prédéfinis, recadrage, WebP.
 * Original conservé dans uploads/i/{id}/orig.{ext},
 * déclinaisons générées en {format}.webp + {format}.jpg.
 *
 * [V10-CMS-BILINGUE] — messages d'erreur traduits (clefs « sys_img_… »).
 *
 * Version V8-CADRAGE (21.07.2026) : l'adresse d'une image porte désormais la
 * date du fichier (…/card.jpg?v=1753...).
 *
 * Pourquoi : le site demande aux navigateurs de garder les images en mémoire
 * pendant un mois — c'est ce qui rend les pages rapides. Mais quand on
 * recadrait une photo, le fichier changeait sans que son adresse change : le
 * navigateur continuait donc d'afficher, un mois durant, la version d'avant.
 * Le recadrage semblait sans effet alors qu'il avait bien eu lieu.
 *
 * En ajoutant la date de la dernière modification à l'adresse, un recadrage
 * crée une adresse neuve : la nouvelle image s'affiche tout de suite, et
 * toutes les autres restent en mémoire comme avant.
 */
class Img
{
    public const MAX_SIZE = 20 * 1024 * 1024; // 20 Mo

    private static ?array $conf = null;

    public static function conf(): array
    {
        return self::$conf ??= require LV_APP . '/config/formats.php';
    }

    public static function formats(): array
    {
        return self::conf()['formats'];
    }

    public static function zoneFormats(string $zone): array
    {
        return self::conf()['zones'][$zone] ?? ['thumb'];
    }

    public static function dir(int $id): string
    {
        return LV_UPLOADS . '/i/' . $id;
    }

    public static function row(int $id): ?array
    {
        return DB::one('SELECT * FROM images WHERE id = ?', [$id]);
    }

    /** Upload d'un fichier image ($_FILES['x']). Retourne la ligne créée. */
    public static function upload(array $file, string $ownerType, int $ownerId, string $zone): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(tu('sys_upload_err'));
        }
        if ($file['size'] > self::MAX_SIZE) {
            throw new RuntimeException(tu('sys_img_big'));
        }
        $info = @getimagesize($file['tmp_name']);
        if (!$info) throw new RuntimeException(tu('sys_img_invalid'));
        $ext = match ($info[2]) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => null,
        };
        if ($ext === null) throw new RuntimeException(tu('sys_img_formats'));

        $sort = 1 + (int)DB::val(
            'SELECT COALESCE(MAX(sort),0) FROM images WHERE owner_type=? AND owner_id=? AND zone=?',
            [$ownerType, $ownerId, $zone]
        );
        $id = DB::insert('images', [
            'owner_type' => $ownerType, 'owner_id' => $ownerId, 'zone' => $zone,
            'ext' => $ext, 'width' => $info[0], 'height' => $info[1], 'sort' => $sort,
        ]);
        $dir = self::dir($id);
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new RuntimeException(tu('sys_dir_err'));
        }
        $dest = $dir . '/orig.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dest) && !rename($file['tmp_name'], $dest)) {
            throw new RuntimeException(tu('sys_save_err'));
        }
        self::normalizeOrientation($dest, $ext, $id);
        // [V31-VISAGES] Les visages se cherchent après le redressement EXIF —
        // une photo de téléphone couchée sur le flanc n'en montrerait aucun —
        // et avant la fabrication des déclinaisons, qui en ont besoin.
        self::analyserVisages((array)self::row($id), $zone);
        $row = self::row($id);
        foreach (self::zoneFormats($zone) as $fmt) self::generate($row, $fmt);
        return self::row($id);
    }

    /** Importe une image déjà présente sur le disque (installateur, couvertures PDF...). */
    public static function importFile(string $path, string $ownerType, int $ownerId, string $zone): array
    {
        $info = @getimagesize($path);
        if (!$info) throw new RuntimeException(tu('sys_img_bad', basename($path)));
        $ext = match ($info[2]) {
            IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp',
            default => throw new RuntimeException(tu('sys_img_unsup')),
        };
        $sort = 1 + (int)DB::val(
            'SELECT COALESCE(MAX(sort),0) FROM images WHERE owner_type=? AND owner_id=? AND zone=?',
            [$ownerType, $ownerId, $zone]
        );
        $id = DB::insert('images', [
            'owner_type' => $ownerType, 'owner_id' => $ownerId, 'zone' => $zone,
            'ext' => $ext, 'width' => $info[0], 'height' => $info[1], 'sort' => $sort,
        ]);
        $dir = self::dir($id);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        copy($path, $dir . '/orig.' . $ext);
        self::analyserVisages((array)self::row($id), $zone);   // [V31-VISAGES]
        $row = self::row($id);
        foreach (self::zoneFormats($zone) as $fmt) self::generate($row, $fmt);
        return self::row($id);
    }

    /** Corrige l'orientation EXIF des JPEG (photos de téléphone). */
    private static function normalizeOrientation(string $path, string $ext, int $id): void
    {
        if ($ext !== 'jpg' || !function_exists('exif_read_data')) return;
        $exif = @exif_read_data($path);
        $o = (int)($exif['Orientation'] ?? 1);
        if ($o <= 1) return;
        $im = @imagecreatefromjpeg($path);
        if (!$im) return;
        $im = match ($o) {
            3 => imagerotate($im, 180, 0),
            6 => imagerotate($im, -90, 0),
            8 => imagerotate($im, 90, 0),
            default => $im,
        };
        imagejpeg($im, $path, 92);
        DB::update('images', ['width' => imagesx($im), 'height' => imagesy($im)], 'id = ?', [$id]);
        imagedestroy($im);
    }

    private static function load(string $path, string $ext)
    {
        return match ($ext) {
            'jpg'  => @imagecreatefromjpeg($path),
            'png'  => @imagecreatefrompng($path),
            'webp' => @imagecreatefromwebp($path),
            default => false,
        };
    }

    /**
     * Cherche les visages et retient vers quoi cadrer.  [V31-VISAGES]
     *
     * Se fait une fois, à l'envoi de l'image, et jamais plus : le résultat est
     * inscrit dans la colonne focus. Les six déclinaisons d'une photo s'en
     * servent ensuite sans rien recalculer.
     *
     * La colonne distingue trois états, et cette distinction est le cœur du
     * dispositif. Vide, l'image n'a pas encore été regardée. Un tiret, elle
     * l'a été et n'a pas de visage — un décor, une affiche, une vue de salle.
     * Deux nombres, on a trouvé où regarder. Sans le tiret, chaque passage de
     * mise à jour rechercherait indéfiniment des visages dans les photos qui
     * n'en contiennent pas.
     */
    public static function analyserVisages(array $img, ?string $zone = null): ?array
    {
        if (!class_exists('Faces') || !Faces::disponible()) return null;
        if (!$img) return null;

        // Une zone dont aucun format ne recadre — le corps d'un article, où
        // l'image est seulement mise à l'échelle — n'a que faire d'un point de
        // visée. On ne lui fait pas dépenser une seconde de calcul.
        if ($zone !== null && !self::zoneRecadre($zone)) return null;

        $orig = self::dir((int)$img['id']) . '/orig.' . $img['ext'];
        if (!is_file($orig)) return null;

        $pf = Faces::pointFocal($orig, (int)$img['width'], (int)$img['height']);
        $val = $pf['n'] > 0
            ? number_format($pf['x'], 4, '.', '') . ',' . number_format($pf['y'], 4, '.', '')
            : '-';
        DB::update('images', ['focus' => $val], 'id = ?', [(int)$img['id']]);

        return $pf['n'] > 0 ? ['x' => $pf['x'], 'y' => $pf['y']] : null;
    }

    /** Cette zone fabrique-t-elle au moins une image recadrée ? */
    public static function zoneRecadre(string $zone): bool
    {
        $formats = self::formats();
        foreach (self::zoneFormats($zone) as $f) {
            if (($formats[$f][2] ?? '') === 'crop') return true;
        }
        return false;
    }

    /** Le point à viser pour cette image, ou null s'il n'y en a pas. */
    public static function focus(array $img): ?array
    {
        $f = trim((string)($img['focus'] ?? ''));
        if ($f === '' || $f === '-') return null;
        $p = explode(',', $f);
        if (count($p) !== 2) return null;
        $x = (float)$p[0]; $y = (float)$p[1];
        if ($x < 0 || $x > 1 || $y < 0 || $y > 1) return null;
        return ['x' => $x, 'y' => $y];
    }

    /**
     * La fenêtre du recadrage automatique.
     *
     * Sans visage, c'est le centre — exactement comme avant, au pixel près.
     * Rien ne bouge sur les photos où il n'y a personne, et rien ne bouge non
     * plus si la reconnaissance est indisponible sur le serveur.
     *
     * Avec un visage, la fenêtre coulisse pour l'attraper. Horizontalement on
     * se centre dessus. Verticalement, on ne le centre pas : on place la ligne
     * des yeux au premier tiers, un peu au-dessus du milieu. Centrer un visage
     * dans un bandeau met le regard au milieu de l'image et laisse autant de
     * vide au-dessus de la tête qu'en dessous du menton — c'est l'erreur de
     * cadrage la plus visible qui soit. L'œil attend de l'air devant le regard
     * et non derrière.
     *
     * Un seul des deux axes a du jeu — celui où la photo déborde du format ; la
     * limitation aux bords s'en charge d'elle-même, sans qu'on ait à savoir
     * lequel des deux c'est.
     */
    private const LIGNE_YEUX = 0.40;

    private static function fenetreAuto(int $W, int $H, float $ratio, ?array $foyer): array
    {
        if ($W / $H > $ratio) { $sh = $H; $sw = (int)round($H * $ratio); }
        else                  { $sw = $W; $sh = (int)round($W / $ratio); }

        if (!$foyer) return [(int)(($W - $sw) / 2), (int)(($H - $sh) / 2), $sw, $sh];

        $sx = (int)round($foyer['x'] * $W - $sw / 2);
        $sy = (int)round($foyer['y'] * $H - $sh * self::LIGNE_YEUX);
        return [max(0, min($W - $sw, $sx)), max(0, min($H - $sh, $sy)), $sw, $sh];
    }

    /**
     * Génère une déclinaison. $rect = [x, y, w, h] dans l'original
     * (recadrage manuel), sinon recadrage automatique — sur les visages si on
     * en connaît, au centre sinon.
     */
    public static function generate(array $img, string $fmt, ?array $rect = null): bool
    {
        $formats = self::formats();
        if (!isset($formats[$fmt])) return false;
        [$tw, $th, $mode] = $formats[$fmt];

        $orig = self::dir((int)$img['id']) . '/orig.' . $img['ext'];
        if (!is_file($orig)) return false;
        $src = self::load($orig, $img['ext']);
        if (!$src) return false;
        $W = imagesx($src); $H = imagesy($src);

        // Recadrage mémorisé pour ce format ?
        if ($rect === null && !empty($img['crops'])) {
            $crops = json_decode($img['crops'], true) ?: [];
            if (isset($crops[$fmt])) $rect = $crops[$fmt];
        }

        if ($mode === 'fit') {
            $scale = min($tw / $W, $th / $H, 1);
            $ow = max(1, (int)round($W * $scale));
            $oh = max(1, (int)round($H * $scale));
            $sx = 0; $sy = 0; $sw = $W; $sh = $H;
        } else { // crop
            $ratio = $tw / $th;
            if ($rect) {
                $sx = max(0, (int)$rect[0]); $sy = max(0, (int)$rect[1]);
                $sw = min($W - $sx, (int)$rect[2]); $sh = min($H - $sy, (int)$rect[3]);
                if ($sw < 10 || $sh < 10) { $rect = null; }
            }
            if (!$rect) {
                // [V31-VISAGES] Le recadrage manuel prime toujours : on n'arrive
                // ici que si personne n'a réglé le cadre de ce format à la main.
                [$sx, $sy, $sw, $sh] = self::fenetreAuto($W, $H, $ratio, self::focus($img));
            }
            $ow = min($tw, $sw); // pas d'agrandissement au-delà de l'original
            $oh = (int)round($ow * $th / $tw);
            if ($oh > $sh) { $oh = $sh; $ow = (int)round($oh * $tw / $th); }
            $ow = max(1, $ow); $oh = max(1, $oh);
        }

        $dst = imagecreatetruecolor($ow, $oh);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $ow, $oh, $sw, $sh);

        $dir = self::dir((int)$img['id']);
        $okWebp = function_exists('imagewebp') ? imagewebp($dst, $dir . '/' . $fmt . '.webp', 82) : false;
        $okJpg  = imagejpeg($dst, $dir . '/' . $fmt . '.jpg', 85);
        imagedestroy($dst); imagedestroy($src);
        return $okJpg || $okWebp;
    }

    /**
     * Mémorise plusieurs recadrages d'un coup, puis refabrique les images.
     * [V31-RECADRAGE]
     *
     * Une même photo sert à plusieurs endroits — la vignette de grille, le
     * bandeau, le carré, le partage — et chacun a ses proportions. On règle
     * donc le cadre format par format, mais on n'enregistre qu'une fois, à la
     * fin : c'est pour cela que cette méthode prend un lot et non un format.
     *
     * L'ordre compte, et il n'est pas indifférent. Le choix (les rectangles)
     * est écrit dans la base AVANT que la moindre image soit fabriquée. Si le
     * serveur s'arrête en route — une grande photo, six formats, l'hébergement
     * coupe au bout de trente secondes —, le travail de cadrage n'est pas
     * perdu : il est déjà inscrit. Les fichiers manquants, eux, se refont tout
     * seuls à la première visite de la page.
     *
     * Les anciens fichiers sont effacés avant d'être refaits, pour la même
     * raison : un fichier périmé qui reste en place ne serait jamais
     * reconstruit, puisque ensure() ne regarde que s'il existe.
     *
     * @param  array $lot  format => [x, y, w, h] dans les pixels de l'original
     * @return array       les formats effectivement refabriqués
     */
    public static function recropLot(array $img, array $lot): array
    {
        $formats = self::formats();
        $crops   = json_decode($img['crops'] ?? '', true) ?: [];
        $retenus = [];

        foreach ($lot as $fmt => $rect) {
            $fmt = (string)$fmt;
            if (!isset($formats[$fmt]))                 continue;
            if (!is_array($rect) || count($rect) < 4)   continue;
            $r = [(int)$rect[0], (int)$rect[1], (int)$rect[2], (int)$rect[3]];
            // Un cadre de quelques pixels n'est pas un cadrage, c'est un
            // faux mouvement : on le laisse de côté plutôt que de l'inscrire.
            if ($r[0] < 0 || $r[1] < 0 || $r[2] < 10 || $r[3] < 10) continue;
            $crops[$fmt]   = $r;
            $retenus[$fmt] = $r;
        }
        if (!$retenus) return [];

        DB::update('images', ['crops' => json_encode($crops)], 'id = ?', [$img['id']]);
        $img['crops'] = json_encode($crops);

        $dossier = self::dir((int)$img['id']);
        $faits   = [];
        foreach ($retenus as $fmt => $r) {
            @unlink($dossier . '/' . $fmt . '.jpg');
            @unlink($dossier . '/' . $fmt . '.webp');
            if (self::generate($img, $fmt, $r)) $faits[] = $fmt;
        }
        return $faits;
    }

    /** Mémorise un recadrage manuel et régénère le format. */
    public static function recrop(array $img, string $fmt, array $rect): void
    {
        self::recropLot($img, [$fmt => $rect]);
    }

    /** Vérifie que la déclinaison existe (génération paresseuse). */
    public static function ensure(array $img, string $fmt): void
    {
        if (!is_file(self::dir((int)$img['id']) . '/' . $fmt . '.jpg')) {
            self::generate($img, $fmt);
        }
    }

    /**
     * Adresse publique d'une déclinaison, datée du fichier lui-même.
     *
     * Le « ?v=… » n'est pas une coquetterie : sans lui, un recadrage reste
     * invisible pendant un mois dans le navigateur de la personne qui vient de
     * le faire (voir l'explication en tête de classe).
     */
    public static function fileUrl(array $img, string $fmt, string $ext = 'jpg'): string
    {
        $url = upload_url('i/' . $img['id'] . '/' . $fmt . '.' . $ext);
        $t   = @filemtime(self::dir((int)$img['id']) . '/' . $fmt . '.' . $ext);
        return $t ? $url . '?v=' . $t : $url;
    }

    /** Balise <picture> WebP + JPEG avec alt bilingue. */
    public static function tag(?array $img, string $fmt, array $attrs = []): string
    {
        if (!$img) return '';
        self::ensure($img, $fmt);
        $webp = self::fileUrl($img, $fmt, 'webp');
        $jpg  = self::fileUrl($img, $fmt, 'jpg');
        $alt  = $attrs['alt'] ?? I18n::f($img, 'alt');
        unset($attrs['alt']);
        $extra = '';
        foreach ($attrs as $k => $v) $extra .= ' ' . $k . '="' . e($v) . '"';
        $hasWebp = is_file(self::dir((int)$img['id']) . '/' . $fmt . '.webp');
        $html = '<picture>';
        if ($hasWebp) $html .= '<source srcset="' . e($webp) . '" type="image/webp">';
        $html .= '<img src="' . e($jpg) . '" alt="' . e($alt) . '" loading="lazy"' . $extra . '>';
        return $html . '</picture>';
    }

    /** Galerie d'un contenu. */
    public static function gallery(string $ownerType, int $ownerId, string $zone = 'gallery'): array
    {
        return DB::all(
            'SELECT * FROM images WHERE owner_type=? AND owner_id=? AND zone=? ORDER BY sort, id',
            [$ownerType, $ownerId, $zone]
        );
    }

    /**
     * Copie une image au profit d'un autre contenu : la ligne ET les fichiers.
     * Retourne l'id de la nouvelle image, ou null si l'originale n'existe plus.
     *
     * [V14-DUPLIQUER] Pourquoi copier les fichiers plutôt que réutiliser la
     * même image : une image appartient à un contenu (owner_type / owner_id).
     * Si la copie se contentait de pointer vers l'image de l'original, alors
     * supprimer l'original effacerait la photo — et delete() ci-dessous
     * remettrait à zéro l'image de la copie, silencieusement. La copie doit
     * donc être autonome dès le premier instant.
     */
    public static function duplicate(int $id, string $ownerType, int $ownerId): ?int
    {
        $src = self::row($id);
        if (!$src) return null;

        $zone = (string)$src['zone'];
        $sort = 1 + (int)DB::val(
            'SELECT COALESCE(MAX(sort),0) FROM images WHERE owner_type=? AND owner_id=? AND zone=?',
            [$ownerType, $ownerId, $zone]
        );
        $newId = (int)DB::insert('images', [
            'owner_type' => $ownerType,
            'owner_id'   => $ownerId,
            'zone'       => $zone,
            'ext'        => $src['ext'],
            'width'      => (int)$src['width'],
            'height'     => (int)$src['height'],
            'alt_en'     => (string)$src['alt_en'],
            'alt_fr'     => (string)$src['alt_fr'],
            'sort'       => $sort,
            'crops'      => $src['crops'],   // le recadrage choisi est conservé
        ]);

        $from = self::dir($id);
        $to   = self::dir($newId);
        if (is_dir($from)) {
            if (!is_dir($to)) @mkdir($to, 0775, true);
            if (is_dir($to)) {
                foreach (glob($from . '/*') ?: [] as $f) {
                    if (is_file($f)) @copy($f, $to . '/' . basename($f));
                }
            }
        }
        return $newId;
    }

    public static function delete(int $id): void
    {
        $dir = self::dir($id);
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
            @rmdir($dir);
        }
        DB::delete('images', 'id = ?', [$id]);
        // Détache les couvertures qui pointaient vers cette image
        foreach (['pages' => 'og_image_id', 'projects' => 'cover_image_id', 'artists' => 'cover_image_id',
                  'events' => 'image_id', 'team_members' => 'image_id', 'documents' => 'cover_image_id'] as $table => $col) {
            DB::run("UPDATE `$table` SET `$col` = NULL WHERE `$col` = ?", [$id]);
        }
        foreach (['projects', 'artists'] as $table) {
            DB::run("UPDATE `$table` SET og_image_id = NULL WHERE og_image_id = ?", [$id]);
        }
    }
}

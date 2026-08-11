<?php
/**
 * Module Documents : upload, listing, couverture extraite du PDF si possible.
 * [V10-CMS-BILINGUE] — messages de téléversement traduits (clefs « sys_… »).
 */
class Docs
{
    public const MAX_SIZE = 25 * 1024 * 1024; // 25 Mo
    public const EXTS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip'];

    public static function dir(int $id): string
    {
        return LV_UPLOADS . '/d/' . $id;
    }

    public static function row(int $id): ?array
    {
        return DB::one('SELECT * FROM documents WHERE id = ?', [$id]);
    }

    /* ------------------------------------------------------------------
       [V31-PRESSE] Deux listes de documents sur une même fiche.

       Une fiche projet portait jusqu'ici une seule liste : « Documents ».
       Elle mélangeait ce qu'on donne à télécharger (fiche technique,
       dossier de production, rider) et ce qu'on donne à lire (un article
       paru dans un journal). Ce sont deux gestes différents pour deux
       publics différents — le programmateur télécharge, le curieux lit —
       et une seule liste les obligeait à se partager la même place.

       La « zone » sépare les deux sans rien dupliquer : même table, même
       machinerie, même façon d'ajouter un fichier ou un lien. Seule la
       colonne change de valeur — « doc » pour la liste des documents,
       « press » pour la revue de presse. Les lignes déjà là sont toutes
       des documents : elles reçoivent « doc » et ne bougent pas.

       Zone laissée à null : on prend tout. C'est ce qu'il faut pour
       supprimer une fiche — il ne doit rien rester derrière, quelle que
       soit la liste d'où ça venait.
       ------------------------------------------------------------------ */
    public const ZONE_DEFAUT = 'doc';

    public static function forOwner(string $ownerType, int $ownerId, ?string $zone = null): array
    {
        if ($zone === null) {
            return DB::all('SELECT * FROM documents WHERE owner_type=? AND owner_id=? ORDER BY sort, id', [$ownerType, $ownerId]);
        }
        return DB::all('SELECT * FROM documents WHERE owner_type=? AND owner_id=? AND zone=? ORDER BY sort, id',
                       [$ownerType, $ownerId, $zone]);
    }

    /** Le rang suivant, compté dans la seule liste concernée. */
    private static function rangSuivant(string $ownerType, int $ownerId, string $zone): int
    {
        return 1 + (int)DB::val('SELECT COALESCE(MAX(sort),0) FROM documents WHERE owner_type=? AND owner_id=? AND zone=?',
                                [$ownerType, $ownerId, $zone]);
    }

    public static function upload(array $file, string $ownerType, int $ownerId, string $zone = self::ZONE_DEFAUT): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(tu('sys_upload_err'));
        }
        if ($file['size'] > self::MAX_SIZE) throw new RuntimeException(tu('sys_doc_big'));
        $name = (string)$file['name'];
        $ext = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTS, true)) {
            throw new RuntimeException(tu('sys_doc_formats', strtoupper(implode(', ', self::EXTS))));
        }
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: ('document.' . $ext);
        $sort = self::rangSuivant($ownerType, $ownerId, $zone);
        $title = trim(preg_replace('/[-_]+/', ' ', pathinfo($name, PATHINFO_FILENAME)));
        $id = DB::insert('documents', [
            'owner_type' => $ownerType, 'owner_id' => $ownerId, 'zone' => $zone,
            'title_en' => $title, 'title_fr' => '',
            'filename' => $clean, 'ext' => $ext, 'size' => (int)$file['size'], 'sort' => $sort,
        ]);
        $dir = self::dir($id);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $dest = $dir . '/' . $clean;
        if (!move_uploaded_file($file['tmp_name'], $dest) && !rename($file['tmp_name'], $dest)) {
            throw new RuntimeException(tu('sys_save_err'));
        }
        if ($ext === 'pdf') self::extractCover($id, $dest);
        return self::row($id);
    }

    /** Couverture depuis la première page du PDF (nécessite Imagick sur le serveur). */
    public static function extractCover(int $id, string $pdfPath): void
    {
        if (!class_exists('Imagick')) return;
        try {
            $im = new Imagick();
            $im->setResolution(150, 150);
            $im->readImage($pdfPath . '[0]');
            $im->setImageBackgroundColor('#ffffff');
            $im = $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $im->setImageFormat('jpeg');
            $tmp = self::dir($id) . '/cover-src.jpg';
            $im->writeImage($tmp);
            $im->clear();
            $imgRow = Img::importFile($tmp, 'doc', $id, 'doccover');
            @unlink($tmp);
            DB::update('documents', ['cover_image_id' => $imgRow['id']], 'id = ?', [$id]);
        } catch (Throwable $e) {
            // pas bloquant : une couverture peut être ajoutée manuellement
        }
    }

    /* ------------------------------------------------------------------
       [V31-DOC-LIEN] Le document qui n'est pas ici.

       Jusqu'ici, mettre un document en téléchargement voulait dire le
       téléverser : le fichier entrait dans le site et y restait. C'est le
       bon geste pour un dossier de presse de deux mégaoctets, ce n'en est
       pas un pour une captation, un teaser, un dossier complet avec ses
       photos en pleine définition — l'hébergement a une taille, et
       recharger cinquante mégaoctets à chaque correction n'a pas de sens
       quand le fichier vit déjà dans un Drive, un Dropbox, un WeTransfer.

       Un document peut donc désormais être un simple lien. Il s'ajoute à
       côté des autres, se titre de la même façon, se range dans le même
       ordre, s'affiche dans la même liste : pour le visiteur c'est une
       ligne de plus, et il ne voit pas la différence — sinon qu'il quitte
       le site, ce que la petite flèche lui dit.

       Les deux ne se mélangent jamais sur une même ligne : ou bien
       « filename » est rempli et le fichier est ici, ou bien « url » l'est
       et il est ailleurs.
       ------------------------------------------------------------------ */
    public static function estLien(array $doc): bool
    {
        return trim((string)($doc['url'] ?? '')) !== '';
    }

    /** Le nom du site qui héberge le fichier, sans « www. » — pour l'afficher. */
    public static function hote(array $doc): string
    {
        $h = (string)parse_url((string)($doc['url'] ?? ''), PHP_URL_HOST);
        return $h !== '' ? preg_replace('/^www\./i', '', $h) : '';
    }

    /**
     * Ajoute un document qui vit ailleurs. On ne vérifie pas que le lien
     * répond : le serveur d'Infomaniak ne sort pas toujours, et un lien
     * momentanément injoignable reste un lien valable.
     */
    public static function addLink(string $url, string $ownerType, int $ownerId, string $zone = self::ZONE_DEFAUT): array
    {
        $url = trim($url);
        if ($url === '') throw new RuntimeException(tu('sys_doc_url'));
        if (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException(tu('sys_doc_url'));
        }
        if (mb_strlen($url) > 1000) throw new RuntimeException(tu('sys_doc_url'));

        /* Le format se lit dans l'adresse quand elle finit par un nom de
           fichier — « …/dossier-presse.pdf ». Sinon on n'invente rien : la
           liste affichera le nom du site à la place du format. */
        $chemin = (string)parse_url($url, PHP_URL_PATH);
        $ext = mb_strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTS, true)) $ext = '';

        /* Un titre de départ, mais seulement quand l'adresse en contient un
           vrai : « …/dossier-presse.pdf » donne « dossier presse ». Un lien
           Drive finit par « /view », un WeTransfer par une suite de lettres —
           en faire un titre serait pire que pas de titre du tout. Dans ce cas
           on laisse les deux titres vides : la liste affiche le nom du site en
           attendant qu'on écrive le vrai. */
        $titre = '';
        if ($ext !== '') {
            $base = trim((string)pathinfo($chemin, PATHINFO_FILENAME));
            if ($base !== '') $titre = trim((string)preg_replace('/[-_]+/', ' ', rawurldecode($base)));
        }

        $sort = self::rangSuivant($ownerType, $ownerId, $zone);
        $id = DB::insert('documents', [
            'owner_type' => $ownerType, 'owner_id' => $ownerId, 'zone' => $zone,
            /* Comme pour un fichier déposé : le titre deviné va en anglais,
               le français reste à écrire. */
            'title_en' => mb_substr($titre, 0, 250), 'title_fr' => '',
            'filename' => '', 'url' => $url, 'ext' => $ext, 'size' => 0, 'sort' => $sort,
        ]);
        return self::row($id);
    }

    public static function fileUrl(array $doc): string
    {
        if (self::estLien($doc)) return (string)$doc['url'];
        return upload_url('d/' . $doc['id'] . '/' . $doc['filename']);
    }

    /**
     * Ce qu'on écrit à droite du titre : le format et le poids pour un
     * fichier d'ici, le format et le site pour un lien — le poids d'un
     * fichier qui n'est pas chez nous, on ne le connaît pas.
     */
    public static function meta(array $doc): string
    {
        if (self::estLien($doc)) {
            $bouts = array_filter([strtoupper((string)$doc['ext']), self::hote($doc)]);
            return implode(' · ', $bouts);
        }
        return strtoupper((string)$doc['ext']) . ' · ' . self::human((int)$doc['size']);
    }

    public static function human(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' Mo';
        if ($bytes >= 1024) return round($bytes / 1024) . ' Ko';
        return $bytes . ' o';
    }

    public static function delete(int $id): void
    {
        $doc = self::row($id);
        if (!$doc) return;
        if (!empty($doc['cover_image_id'])) Img::delete((int)$doc['cover_image_id']);
        $dir = self::dir($id);
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
            @rmdir($dir);
        }
        DB::delete('documents', 'id = ?', [$id]);
    }
}

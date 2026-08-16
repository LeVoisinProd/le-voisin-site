<?php
/**
 * Les fichiers attachés à une date. [16.08.2026]
 *
 * Anna: « Glisser-déposer des fiches techniques, itinéraires, contrats sur un
 * booking. » Aujourd'hui ces pièces vivent dans des fils d'e-mails.
 *
 * LA LISTE D'EXTENSIONS EST FERMÉE, comme pour l'advancing, et la même
 * remarque vaut: uploads/private n'est pas servi par Apache, donc un .php
 * déposé là ne s'exécuterait pas — mais on ne fait pas reposer la sécurité
 * d'un dossier sur une seule serrure. Deux verrous indépendants valent mieux
 * qu'un verrou dont on est sûr.
 *
 * LE PARTAGE EST UNE COLONNE ET NON UN DOSSIER. Mettre les fichiers « artiste »
 * dans un répertoire séparé paraîtrait plus net et serait pire: on déplacerait
 * un fichier pour changer son partage, et un déplacement qui échoue à mi-chemin
 * laisse une pièce visible qui ne devrait pas l'être.
 */
declare(strict_types=1);

class BookingFiles
{
    /** Le plafond par fichier. Au-delà, c'est une vidéo, et elle va ailleurs. */
    public const MAX = 25 * 1024 * 1024;

    private const EXT = ['pdf','png','jpg','jpeg','gif','webp','svg','heic',
                         'doc','docx','xls','xlsx','ppt','pptx','csv','txt','rtf',
                         'dwg','dxf','zip','ics'];

    public static function dossier(int $bookingId): string
    {
        $d = dirname(__DIR__, 2) . '/uploads/private/b/' . $bookingId;
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }

    public static function liste(int $bookingId): array
    {
        return DB::all('SELECT * FROM booking_file WHERE booking_id = ? ORDER BY cree_a DESC, id DESC',
                       [$bookingId]);
    }

    public static function un(int $id): ?array
    {
        return DB::one('SELECT * FROM booking_file WHERE id = ?', [$id]);
    }

    public static function chemin(array $f): string
    {
        return self::dossier((int)$f['booking_id']) . '/' . $f['fichier'];
    }

    /**
     * Dépose un fichier.
     *
     * @throws RuntimeException avec un message écrit pour être lu par la
     *         personne: « ce type de fichier n'est pas accepté » vaut mieux
     *         qu'un « erreur » qui oblige à ouvrir un journal.
     */
    public static function deposer(int $bookingId, array $f, string $partage = 'interne',
                                   string $par = ''): int
    {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            /* UPLOAD_ERR_INI_SIZE veut dire que PHP a coupé avant nous: le
               dire, sinon la personne croit que le dépôt a marché. */
            throw new RuntimeException(
                (int)($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE
                    ? 'Le fichier dépasse ce que le serveur accepte.'
                    : 'Le dépôt a échoué.');
        }
        if (!is_uploaded_file((string)$f['tmp_name'])) throw new RuntimeException('Fichier inattendu.');
        if ((int)($f['size'] ?? 0) > self::MAX) {
            throw new RuntimeException('25 Mo au maximum. Au-delà, passez par un lien.');
        }

        $original = (string)($f['name'] ?? 'fichier');
        $nom = self::nomSur($original);
        if ($nom === '') {
            throw new RuntimeException('Ce type de fichier n\'est pas accepté ici.');
        }

        $dir = self::dossier($bookingId);
        /* Deux fichiers du même nom sur une même date arrivent vraiment —
           « plan.pdf » deux fois. On suffixe plutôt que d'écraser: écraser
           ferait disparaître une pièce sans que personne ne le voie. */
        $final = $nom;
        $i = 2;
        while (is_file($dir . '/' . $final)) {
            $b = (string)pathinfo($nom, PATHINFO_FILENAME);
            $e = (string)pathinfo($nom, PATHINFO_EXTENSION);
            $final = $b . '_' . $i++ . '.' . $e;
        }

        if (!move_uploaded_file((string)$f['tmp_name'], $dir . '/' . $final)) {
            throw new RuntimeException('Impossible d\'enregistrer le fichier.');
        }

        return DB::insert('booking_file', [
            'booking_id' => $bookingId,
            'titre'      => mb_substr($original, 0, 190),
            'fichier'    => $final,
            'taille'     => (int)($f['size'] ?? 0),
            'partage'    => $partage === 'artiste' ? 'artiste' : 'interne',
            'depose_par' => trim($par) !== '' ? mb_substr(trim($par), 0, 190) : null,
        ]);
    }

    public static function partage(int $id, int $bookingId, string $partage): void
    {
        DB::update('booking_file', ['partage' => $partage === 'artiste' ? 'artiste' : 'interne'],
                   'id = ? AND booking_id = ?', [$id, $bookingId]);
    }

    public static function supprimer(int $id, int $bookingId): void
    {
        $f = self::un($id);
        if (!$f || (int)$f['booking_id'] !== $bookingId) return;
        @unlink(self::chemin($f));
        DB::delete('booking_file', 'id = ? AND booking_id = ?', [$id, $bookingId]);
    }

    public static function poids(int $o): string
    {
        if ($o < 1024) return $o . ' o';
        if ($o < 1048576) return round($o / 1024) . ' Ko';
        return round($o / 1048576, 1) . ' Mo';
    }

    private static function nomSur(string $nom): string
    {
        $nom = basename($nom);
        $ext = strtolower((string)pathinfo($nom, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXT, true)) return '';
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)pathinfo($nom, PATHINFO_FILENAME)) ?: 'fichier';
        return mb_substr($base, 0, 150) . '.' . $ext;
    }
}

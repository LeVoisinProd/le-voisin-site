<?php
/**
 * Les pièces d'une offre — le devis, d'abord.  [Anna, 21.08.2026]
 *
 * « eu preciso baixar o pdf desde esta página ».
 *
 * CE QUI MANQUAIT N'ÉTAIT PAS UN BOUTON, C'ÉTAIT LE FICHIER. L'écran Offres
 * suit des demandes; le devis, lui, est produit ailleurs — par `gerar_devis.js`
 * — et vit sur le Drive. L'offre n'en gardait que le NOM, dans une note:
 * « Devis envoyé le 07.08. Fichier: …_Devis_V1.html — sur le Drive ». Elle
 * disait donc qu'un devis existait sans permettre de l'ouvrir.
 *
 * MÊME RANGEMENT QUE LES PIÈCES D'UNE DATE: `uploads/private`, qu'Apache ne
 * sert pas. Un devis porte un prix négocié et le nom d'un programmateur; il ne
 * s'attrape pas en devinant une adresse.
 *
 * ON SUFFIXE, ON N'ÉCRASE PAS. Deux « devis.pdf » sur la même offre arrivent
 * vraiment — la première version et la corrigée. Écraser ferait disparaître une
 * pièce sans que personne le voie, et c'est précisément l'historique d'une
 * négociation qu'on veut garder.
 *
 * CE FICHIER RESSEMBLE À `BookingFiles`, ET C'EST ASSUMÉ. Généraliser les deux
 * aurait voulu dire toucher au dépôt des dates, qui marche et qui est en
 * service. Quarante lignes en double coûtent moins cher qu'une abstraction
 * posée sur du code qu'on n'a pas de raison d'ouvrir aujourd'hui.
 */
declare(strict_types=1);

final class OfferFiles
{
    public const MAX = 25 * 1024 * 1024;

    /** Ce qu'un devis peut être. Pas de `.html`: on veut la pièce, pas sa source. */
    private const EXT = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx', 'odt'];

    public static function dossier(int $offerId): string
    {
        $d = dirname(__DIR__, 2) . '/uploads/private/o/' . $offerId;
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }

    /** @return array<int,array> */
    public static function liste(int $offerId): array
    {
        try {
            return DB::all('SELECT * FROM offer_file WHERE offer_id = ? ORDER BY cree_a DESC, id DESC',
                           [$offerId]);
        } catch (Throwable $e) { return []; }
    }

    public static function un(int $id): ?array
    {
        try { return DB::one('SELECT * FROM offer_file WHERE id = ?', [$id]); }
        catch (Throwable $e) { return null; }
    }

    public static function chemin(array $f): string
    {
        return self::dossier((int)$f['offer_id']) . '/' . $f['fichier'];
    }

    /**
     * Un nom sûr, ou '' si l'extension n'est pas acceptée.
     *
     * ON NE FAIT PAS CONFIANCE AU NOM D'ORIGINE. Il vient du poste de qui
     * dépose et peut contenir n'importe quoi — des barres obliques, des points
     * qui remontent d'un dossier, un `.php` déguisé.
     */
    private static function nomSur(string $original): string
    {
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXT, true)) return '';

        $base = pathinfo($original, PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) ?: 'fichier';
        return mb_substr(trim($base, '._-'), 0, 120) . '.' . $ext;
    }

    /** @throws RuntimeException avec un message écrit pour être lu */
    public static function deposer(int $offerId, array $f, string $par = ''): int
    {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                (int)($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE
                    ? 'Le fichier dépasse ce que le serveur accepte.'
                    : 'Le dépôt a échoué.');
        }
        if (!is_uploaded_file((string)$f['tmp_name'])) throw new RuntimeException('Fichier inattendu.');
        if ((int)($f['size'] ?? 0) > self::MAX) {
            throw new RuntimeException('25 Mo au maximum.');
        }

        $original = (string)($f['name'] ?? 'fichier');
        $nom = self::nomSur($original);
        if ($nom === '') {
            throw new RuntimeException('Format accepté: PDF, image, Word ou ODT.');
        }

        $dir   = self::dossier($offerId);
        $final = $nom;
        $i = 2;
        while (is_file($dir . '/' . $final)) {
            $final = pathinfo($nom, PATHINFO_FILENAME) . '-' . $i . '.' . pathinfo($nom, PATHINFO_EXTENSION);
            $i++;
        }

        if (!move_uploaded_file((string)$f['tmp_name'], $dir . '/' . $final)) {
            throw new RuntimeException('Le fichier n’a pas pu être enregistré.');
        }
        @chmod($dir . '/' . $final, 0644);

        DB::insert('offer_file', [
            'offer_id'   => $offerId,
            'titre'      => mb_substr($original, 0, 190),
            'fichier'    => $final,
            'taille'     => (int)$f['size'],
            'depose_par' => mb_substr($par, 0, 190) ?: null,
            'cree_a'     => date('Y-m-d H:i:s'),
        ]);
        return (int)DB::pdo()->lastInsertId();
    }

    public static function supprimer(int $id, int $offerId): void
    {
        $f = self::un($id);
        if (!$f || (int)$f['offer_id'] !== $offerId) return;
        $p = self::chemin($f);
        if (is_file($p)) @unlink($p);
        DB::run('DELETE FROM offer_file WHERE id = ?', [$id]);
    }

    public static function poids(int $o): string
    {
        return $o >= 1048576
            ? number_format($o / 1048576, 1, ',', ' ') . ' Mo'
            : number_format(max(1, (int)round($o / 1024)), 0, ',', ' ') . ' Ko';
    }
}

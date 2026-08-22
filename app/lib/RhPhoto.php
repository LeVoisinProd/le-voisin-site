<?php
/**
 * Le portrait d'une personne du Personnel.  [Anna, 22.08.2026]
 *
 * « colocar o item foto + bio ».
 *
 * HORS DU WEB, ET SERVI PAR LE DASHBOARD. Décidé par Anna à la question posée:
 * le fichier vit dans `uploads/private/rh/<id>/`, qu'Apache ne sert pas, et
 * c'est `personnel.php` qui le rend à qui a une session. Un portrait est une
 * donnée personnelle — il ne s'attrape pas en devinant une adresse, et un lien
 * d'image circule bien plus loin qu'on ne l'imagine.
 *
 * UNE SEULE PHOTO PAR PERSONNE, et la nouvelle remplace l'ancienne. C'est le
 * contraire du dépôt de pièces d'une date ou d'une offre, où l'on suffixe pour
 * ne rien perdre: là on garde un historique de négociation, ici on veut le
 * portrait à jour. Deux portraits de la même personne, ce sont deux dossiers qui
 * ne se ressemblent pas.
 *
 * ON NE FAIT PAS CONFIANCE À L'EXTENSION, ON REGARDE L'IMAGE. `getimagesize()`
 * lit les premiers octets et dit ce que le fichier est vraiment. Un `.jpg` qui
 * n'est pas une image n'a rien à faire dans un dossier qui part à un financeur,
 * et le nom d'origine vient d'un poste qu'on ne contrôle pas.
 */
declare(strict_types=1);

final class RhPhoto
{
    /** 8 Mo: un portrait sorti d'un téléphone en fait 3 ou 4. */
    public const MAX = 8 * 1024 * 1024;

    private const TYPES = [
        IMAGETYPE_JPEG => ['jpg',  'image/jpeg'],
        IMAGETYPE_PNG  => ['png',  'image/png'],
        IMAGETYPE_WEBP => ['webp', 'image/webp'],
    ];

    public static function dossier(int $empId): string
    {
        $d = dirname(__DIR__, 2) . '/uploads/private/rh/' . $empId;
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }

    public static function chemin(int $empId, string $fichier): string
    {
        /* `basename` par précaution: la valeur vient de la base, mais une base
           se répare parfois à la main, et « ../../config.php » est vite écrit. */
        return self::dossier($empId) . '/' . basename($fichier);
    }

    /** Le type MIME d'après l'extension enregistrée, pour l'en-tête de réponse. */
    public static function mime(string $fichier): string
    {
        $ext = strtolower(pathinfo($fichier, PATHINFO_EXTENSION));
        foreach (self::TYPES as [$e, $m]) if ($e === $ext) return $m;
        return 'application/octet-stream';
    }

    /**
     * Dépose le portrait et renvoie le nom du fichier écrit.
     *
     * @throws RuntimeException avec un message écrit pour être lu
     */
    public static function deposer(int $empId, array $f, ?string $ancien = null): string
    {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                (int)($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE
                    ? 'Le fichier dépasse ce que le serveur accepte.'
                    : 'Le dépôt a échoué.');
        }
        if (!is_uploaded_file((string)$f['tmp_name'])) throw new RuntimeException('Fichier inattendu.');
        if ((int)($f['size'] ?? 0) > self::MAX)        throw new RuntimeException('8 Mo au maximum.');

        $info = @getimagesize((string)$f['tmp_name']);
        if (!$info || !isset(self::TYPES[$info[2]])) {
            throw new RuntimeException('Une image JPEG, PNG ou WebP.');
        }
        [$ext] = self::TYPES[$info[2]];

        /* Le nom ne vient pas du poste qui dépose: `portrait.jpg`, et rien
           d'autre. Un nom d'origine n'apporte ici aucune information — il n'y a
           qu'une photo par personne — et c'est une entrée de moins à surveiller. */
        $nom = 'portrait.' . $ext;
        $dir = self::dossier($empId);

        if (!move_uploaded_file((string)$f['tmp_name'], $dir . '/' . $nom)) {
            throw new RuntimeException('L’image n’a pas pu être enregistrée.');
        }
        @chmod($dir . '/' . $nom, 0644);

        /* L'ancienne part si elle portait une autre extension — sinon elle
           resterait à côté sans que rien ne la désigne. */
        if ($ancien !== null && $ancien !== '' && basename($ancien) !== $nom) {
            $vieux = $dir . '/' . basename($ancien);
            if (is_file($vieux)) @unlink($vieux);
        }
        return $nom;
    }

    public static function supprimer(int $empId, ?string $fichier): void
    {
        if ($fichier === null || $fichier === '') return;
        $p = self::chemin($empId, $fichier);
        if (is_file($p)) @unlink($p);
    }

    /**
     * L'image en `data:` URI, pour un document qui doit tenir tout seul.
     *
     * LE DOSSIER IMPRIMÉ EN A BESOIN. Le navigateur qui fabrique le PDF suit
     * bien une adresse du dashboard — il a la session — mais le HTML enregistré
     * ou envoyé à quelqu'un d'autre montrerait des cadres vides. Une image
     * embarquée voyage avec le document.
     */
    public static function dataUri(int $empId, ?string $fichier): string
    {
        if ($fichier === null || $fichier === '') return '';
        $p = self::chemin($empId, $fichier);
        if (!is_file($p) || filesize($p) > self::MAX) return '';
        $b = @file_get_contents($p);
        return $b === false ? '' : 'data:' . self::mime($fichier) . ';base64,' . base64_encode($b);
    }
}

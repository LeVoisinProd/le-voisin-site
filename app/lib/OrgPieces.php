<?php
/**
 * Les pièces annuelles d'une association. [18.08.2026]
 *
 * Un document par (association, type, année): l'attestation d'affiliation LPP
 * pour commencer, d'autres ensuite — elles ont toutes la même forme.
 *
 * LES FICHIERS VIVENT DANS `uploads/private`, JAMAIS DANS `uploads/`. Une
 * attestation d'affiliation porte le numéro de contrat de prévoyance et le nom
 * de la caisse; servie par Apache, elle serait lisible de qui devine son
 * adresse. On réutilise donc le même dossier fermé que les fiches de salaire,
 * avec son `.htaccess` de refus, et un point d'entrée PHP qui vérifie le rôle
 * avant de rendre un octet.
 *
 * REMPLACER PLUTÔT QU'EMPILER. La clef unique (association, type, année) fait
 * qu'un second dépôt pour la même année écrase le premier — et l'ancien fichier
 * est effacé du disque dans le même geste. On ne corrige pas une attestation:
 * on en reçoit une meilleure, et deux versions de la même année laisseraient
 * quelqu'un choisir la mauvaise devant un contrôle.
 */
declare(strict_types=1);

final class OrgPieces
{
    /** Les pièces connues. Le libellé est celui qu'Anna a dicté. */
    public const TYPES = [
        'lpp_affiliation' => [
            'fr' => 'Attestation d’affiliation à une institution de prévoyance du deuxième pilier (LPP)',
            'en' => 'Certificate of affiliation to a second-pillar pension institution (LPP)',
        ],
    ];

    private static ?bool $table = null;

    /** La table peut manquer si les fichiers arrivent avant la migration. */
    public static function dispo(): bool
    {
        if (self::$table === null) {
            try { self::$table = (bool)DB::one("SHOW TABLES LIKE 'organisation_piece'"); }
            catch (\Throwable $e) { self::$table = false; }
        }
        return self::$table;
    }

    private static function racine(): string
    {
        $root = LV_UPLOADS . '/private';
        if (!is_dir($root)) mkdir($root, 0775, true);
        $ht = $root . '/.htaccess';
        if (!is_file($ht)) {
            file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
        }
        $dir = $root . '/org';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        return $dir;
    }

    public static function chemin(array $p): string
    {
        return self::racine() . '/' . (int)$p['organisation_id'] . '/' . $p['fichier'];
    }

    /** Les pièces d'une association, la plus récente d'abord. */
    public static function liste(int $orgId, ?string $type = null): array
    {
        if (!self::dispo() || $orgId <= 0) return [];
        $sql = 'SELECT * FROM organisation_piece WHERE organisation_id = ?';
        $a = [$orgId];
        if ($type !== null) { $sql .= ' AND type = ?'; $a[] = $type; }
        return DB::all($sql . ' ORDER BY annee DESC, type', $a);
    }

    public static function une(int $id): ?array
    {
        if (!self::dispo() || $id <= 0) return null;
        return DB::one('SELECT * FROM organisation_piece WHERE id = ?', [$id]) ?: null;
    }

    /**
     * Dépose une pièce. Rend le message d'erreur, ou '' si tout s'est bien passé.
     *
     * ON REFUSE PLUTÔT QUE DE DEVINER: une extension inattendue, un fichier vide
     * ou une année absurde s'arrêtent ici. Une attestation mal déposée qu'on
     * croit déposée est pire que pas d'attestation — on ne la redemande jamais.
     */
    public static function deposer(int $orgId, string $type, int $annee, array $file,
                                   string $note = '', string $par = ''): string
    {
        if (!self::dispo())                       return 'La table des pièces manque: lancer php db/migrer.php.';
        if (!isset(self::TYPES[$type]))           return 'Type de pièce inconnu.';
        if ($orgId <= 0)                          return 'Association inconnue.';
        if ($annee < 2000 || $annee > 2100)       return 'Année invalide.';
        if ((int)($file['error'] ?? 1) !== UPLOAD_ERR_OK) return 'Le fichier n’est pas arrivé.';
        if ((int)($file['size'] ?? 0) <= 0)       return 'Le fichier est vide.';
        if ((int)$file['size'] > 25 * 1024 * 1024) return 'Le fichier dépasse 25 Mo.';

        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true))
            return 'Format accepté: PDF, JPG ou PNG.';

        $org = DB::one('SELECT nom FROM organisation WHERE id = ?', [$orgId]);
        if (!$org) return 'Association inconnue.';

        /* Le nom porte l'association, l'objet et l'année: on le reconnaît hors
           du site, dans une pièce jointe ou sur un bureau. */
        $sigle = preg_replace('/[^A-Za-z0-9]+/', '', (string)$org['nom']) ?: 'ASSO';
        $nom   = sprintf('%d_%s_%s.%s', $annee, mb_substr($sigle, 0, 24), $type, $ext);

        $dir = self::racine() . '/' . $orgId;
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        /* L'ancienne de la même année part AVANT d'écrire la nouvelle: sinon un
           changement d'extension laisserait deux fichiers et la ligne n'en
           désignerait qu'un. */
        $avant = DB::one('SELECT * FROM organisation_piece WHERE organisation_id = ? AND type = ? AND annee = ?',
                         [$orgId, $type, $annee]);
        if ($avant) {
            $vieux = $dir . '/' . $avant['fichier'];
            if (is_file($vieux) && basename($vieux) !== $nom) @unlink($vieux);
        }

        if (!move_uploaded_file((string)$file['tmp_name'], $dir . '/' . $nom))
            return 'Le fichier n’a pas pu être enregistré.';
        @chmod($dir . '/' . $nom, 0644);

        $l = ['organisation_id' => $orgId, 'type' => $type, 'annee' => $annee,
              'fichier' => $nom, 'ext' => $ext, 'taille' => (int)$file['size'],
              'note' => mb_substr(trim($note), 0, 300) ?: null,
              'depose_par' => mb_substr($par, 0, 96) ?: null];
        if ($avant) DB::update('organisation_piece', $l, 'id = ?', [(int)$avant['id']]);
        else        DB::insert('organisation_piece', $l);
        return '';
    }

    public static function retirer(int $id): void
    {
        $p = self::une($id);
        if (!$p) return;
        $f = self::chemin($p);
        if (is_file($f)) @unlink($f);
        DB::delete('organisation_piece', 'id = ?', [$id]);
    }

    /** L'année qu'on propose par défaut: celle en cours. */
    public static function anneeDefaut(): int { return (int)date('Y'); }
}

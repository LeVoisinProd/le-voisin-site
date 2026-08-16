<?php
/**
 * Le presskit d'un spectacle: un lien, et ce qu'il ouvre. [16.08.2026]
 *
 * IL NE STOCKE AUCUN CONTENU. Tout est déjà dans le CMS: `projects` porte le
 * titre, l'intro, la distribution et les infos; `images` la couverture et la
 * galerie; `documents` les fiches techniques. Recopier créerait une deuxième
 * vérité qui divergerait au premier changement — le défaut même que la
 * spécification reproche à l'existant, où la même pièce est saisie trois fois.
 *
 * Cette classe ne fait donc que deux choses: gérer le droit d'entrer, et
 * rassembler ce qui existe déjà.
 *
 * LE JETON PLUTÔT QU'UNE PAGE PUBLIQUE, et la raison est datée. Le 11.08.2026
 * les fiches techniques sont passées derrière le mot de passe du Catalogue. Le
 * 13.08 on a découvert qu'elles restaient joignables par leur adresse directe,
 * servies par Apache sans jamais passer par PHP: « le mot de passe protégeait
 * la page, pas les fichiers. » Une page presskit publique referait ce trou par
 * la porte d'à côté. Un jeton se révoque; une adresse partagée, non.
 *
 * ET LES DOCUMENTS PASSENT PAR LE MÊME PORTIER, document.php, à qui l'on
 * apprend ce jeton — plutôt que d'ouvrir un second chemin vers les mêmes
 * fichiers. Deux portes vers une pièce, c'est deux serrures à se rappeler.
 */
declare(strict_types=1);

class Presskit
{
    public const JOURS = 365;

    /* ── Le lien ────────────────────────────────────────────────────────── */

    public static function lien(int $projectId): ?array
    {
        return DB::one('SELECT * FROM presskit_link WHERE project_id = ?', [$projectId]);
    }

    public static function ouvrir(int $projectId, string $destinataire = ''): array
    {
        $jeton = bin2hex(random_bytes(32));
        DB::delete('presskit_link', 'project_id = ?', [$projectId]);
        DB::insert('presskit_link', [
            'project_id'   => $projectId,
            'jeton'        => $jeton,
            'destinataire' => trim($destinataire) !== '' ? mb_substr(trim($destinataire), 0, 190) : null,
            'expire_a'     => date('Y-m-d H:i:s', time() + self::JOURS * 86400),
        ]);
        return self::lien($projectId) ?? [];
    }

    public static function revoquer(int $projectId): void
    {
        DB::update('presskit_link', ['revoque' => 1], 'project_id = ?', [$projectId]);
    }

    /**
     * Le lien derrière un jeton, ou null.
     *
     * Une seule réponse pour inconnu, expiré et révoqué: distinguer
     * apprendrait à qui essaie que la première moitié était bonne.
     */
    public static function parJeton(string $jeton): ?array
    {
        $jeton = trim($jeton);
        if (!preg_match('/^[0-9a-f]{64}$/', $jeton)) return null;

        $l = DB::one('SELECT * FROM presskit_link WHERE jeton = ?', [$jeton]);
        if (!$l) return null;
        if ((int)$l['revoque'] === 1) return null;
        if ($l['expire_a'] !== null && strtotime((string)$l['expire_a']) < time()) return null;
        return $l;
    }

    /**
     * Ce jeton ouvre-t-il ce projet précis.
     *
     * Appelée par document.php: un jeton valable pour un spectacle ne doit pas
     * ouvrir la fiche technique d'un autre.
     */
    public static function ouvre(string $jeton, int $projectId): bool
    {
        $l = self::parJeton($jeton);
        return $l !== null && (int)$l['project_id'] === $projectId;
    }

    public static function noterVisite(int $projectId): void
    {
        DB::run('UPDATE presskit_link SET visites = visites + 1, dernier_acces = NOW()
                 WHERE project_id = ?', [$projectId]);
    }

    /* ── Ce que le lien ouvre ───────────────────────────────────────────── */

    public static function projet(int $projectId): ?array
    {
        return DB::one('SELECT * FROM projects WHERE id = ?', [$projectId]);
    }

    /** La couverture, puis la galerie. */
    public static function images(int $projectId): array
    {
        return DB::all("SELECT * FROM images
                        WHERE owner_type = 'project' AND owner_id = ?
                          AND zone IN ('cover','gallery')
                        ORDER BY FIELD(zone,'cover','gallery'), sort, id", [$projectId]);
    }

    /**
     * Les documents du projet.
     *
     * Les deux zones: `doc` porte les fiches techniques — celles qui sont
     * réservées et que le presskit sert justement à partager — et `press` le
     * dossier de presse. Un presskit sans fiche technique n'aurait pas de
     * raison d'être.
     */
    public static function documents(int $projectId): array
    {
        return DB::all("SELECT * FROM documents
                        WHERE owner_type = 'project' AND owner_id = ?
                          AND zone IN ('doc','press')
                        ORDER BY FIELD(zone,'press','doc'), sort, id", [$projectId]);
    }

    /** Les spectacles visibles, pour l'écran qui distribue les liens. */
    public static function projets(): array
    {
        return DB::all("SELECT p.id, p.title_fr, p.title_en, p.visible,
                               l.jeton, l.revoque, l.expire_a, l.visites, l.dernier_acces, l.destinataire
                        FROM projects p
                        LEFT JOIN presskit_link l ON l.project_id = p.id
                        WHERE p.visible = 1
                        ORDER BY p.sort, p.title_fr");
    }
}

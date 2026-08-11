<?php
/**
 * Journal des accès aux données des collaborateurs.               [V39-JOURNAL]
 *
 * Qui a téléchargé quel document, qui a ouvert l'espace de qui — et depuis
 * quand. Trois principes :
 *
 *   — une panne du journal n'interrompt jamais la page qui l'appelle : rater
 *     une ligne de journal est sans conséquence, rater un téléchargement de
 *     fiche de salaire en a une ;
 *   — conservé 6 mois (art. 8 LPD — traçabilité), purgé tout seul au fil des
 *     écritures : pas de tâche planifiée à poser, pas de bouton à penser à
 *     cliquer ;
 *   — jamais de mot de passe ni de contenu de document dans une ligne, juste
 *     qui, quoi, quand.
 */
class AccessLog
{
    public const RETENTION_JOURS = 183; // ~6 mois

    private static function ip(): string
    {
        return substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 60);
    }

    /**
     * Inscrit une ligne.
     *
     * @param string   $acteur   'member' (la personne elle-même) ou 'admin'
     *                           (le bureau, pendant une visite).
     * @param int|null $acteurId L'identifiant admin (table users), quand
     *                           $acteur vaut 'admin'.
     * @param string   $action   'login' | 'download' | 'visite'.
     */
    public static function ecrire(int $collaboratorId, string $acteur, ?int $acteurId, string $action, string $detail = ''): void
    {
        try {
            DB::insert('access_log', [
                'collaborator_id' => $collaboratorId,
                'actor'           => $acteur,
                'actor_id'        => $acteurId,
                'action'          => $action,
                'detail'          => mb_substr($detail, 0, 255),
                'ip'              => self::ip(),
            ]);
            // Purge légère et aléatoire : un journal de ce volume n'a pas
            // besoin d'un DELETE à chaque écriture pour rester à jour.
            if (random_int(1, 50) === 1) {
                DB::run('DELETE FROM access_log WHERE at < (NOW() - INTERVAL ' . self::RETENTION_JOURS . ' DAY)');
            }
        } catch (\Throwable $e) {
            // Table absente (mise à jour de base pas encore faite) ou panne
            // passagère : le geste qui a déclenché ce journal reste valide,
            // on ne casse jamais la page pour une ligne d'historique.
        }
    }

    /**
     * Les lignes les plus récentes, pour la page d'administration.
     * $collaboratorId permet de ne voir que l'historique d'une personne.
     */
    public static function recentes(int $limite = 200, ?int $collaboratorId = null): array
    {
        $ou = $collaboratorId !== null ? 'WHERE a.collaborator_id = ?' : '';
        $params = $collaboratorId !== null ? [$collaboratorId] : [];
        return DB::all(
            'SELECT a.*, c.name AS collab_name, u.name AS admin_name, u.email AS admin_email
             FROM access_log a
             JOIN collaborators c ON c.id = a.collaborator_id
             LEFT JOIN users u ON u.id = a.actor_id
             ' . $ou . '
             ORDER BY a.at DESC, a.id DESC
             LIMIT ' . (int)$limite,
            $params
        );
    }
}

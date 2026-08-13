<?php
/**
 * Supprimer une fiche de collaborateur.        [V41-SUPPR] [13.08.2026]
 *
 * Écrit ici, et pas dans l'écran qui l'appelle, parce que deux écrans le
 * demandent : la fiche d'une personne et la liste, en lot. Aujourd'hui même,
 * deux conditions écrites à deux endroits ont été corrigées à un seul et le
 * défaut a survécu au correctif. La règle de suppression est trop chère pour
 * qu'on recommence : elle vit dans un fichier, et les deux écrans l'appellent.
 *
 * LA RÈGLE TIENT EN UNE PHRASE : on ne supprime que ce qui ne porte aucun
 * document. Contrats, fiches de salaire et factures se conservent dix ans ;
 * effacer la fiche emporterait le dossier avec elle. Ce qui reste supprimable
 * est donc exactement ce qu'on veut vraiment supprimer — un doublon, un essai,
 * une fiche créée et jamais servie.
 *
 * Pour quelqu'un qui part et qui a travaillé, le geste n'est pas ici : c'est
 * « Actif » décoché sur sa fiche. L'accès se ferme à l'instant, member() ne
 * chargeant que les comptes actifs, et les documents restent où la
 * comptabilité les cherchera.
 */
class Collaborateurs
{
    /** Combien de documents portent cette personne. Zéro veut dire supprimable. */
    public static function documents(int $id): int
    {
        return (int)DB::val('SELECT COUNT(*) FROM member_documents WHERE collaborator_id = ?', [$id]);
    }

    /**
     * Supprime la fiche, sa fiche personnelle, sa photo et son journal d'accès.
     *
     * Rend false sans rien toucher si un document est attaché. Ce contrôle est
     * refait ICI et pas seulement à l'écran : un bouton absent de la page n'a
     * jamais empêché personne de fabriquer un envoi à la main.
     *
     * La photo passe par Img::delete(), qui retire aussi les fichiers du
     * disque ; une suppression en base seule les laisserait derrière elle,
     * invisibles et occupant la place pour toujours.
     */
    public static function supprimer(int $id): bool
    {
        if ($id <= 0 || self::documents($id) > 0) return false;

        foreach (DB::all('SELECT id FROM images WHERE owner_type = ? AND owner_id = ?', ['collaborator', $id]) as $im) {
            try { Img::delete((int)$im['id']); } catch (Throwable $e) { /* le fichier manquait déjà */ }
        }
        DB::delete('member_profiles', 'collaborator_id = ?', [$id]);
        DB::delete('access_log', 'collaborator_id = ?', [$id]);
        DB::delete('collaborators', 'id = ?', [$id]);
        return true;
    }
}

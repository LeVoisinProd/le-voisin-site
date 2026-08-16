<?php
/**
 * Le tableau en colonnes: lecture et écritures. [16.08.2026]
 *
 * Toutes les écritures passent ici plutôt que dans l'écran, parce qu'elles
 * arrivent par deux chemins — le formulaire classique et les appels `fetch` du
 * glisser-déposer — et que deux chemins vers la même table finissent toujours
 * par diverger sur une règle.
 *
 * L'ORDRE EST ESPACÉ DE 10. Déposer entre deux cartes prend la moyenne des deux
 * voisines, ce qui évite de renuméroter la colonne entière à chaque geste. Quand
 * la place manque — deux voisines à 40 et 41 — on renumérote CETTE colonne
 * seulement, et seulement à ce moment-là.
 */
declare(strict_types=1);

final class Kanban
{
    public const COULEURS = ['neutre' => 'Neutre', 'jaune' => 'Jaune', 'orange' => 'Orange',
                             'vert' => 'Vert', 'rouge' => 'Rouge'];

    /* ── Lire ───────────────────────────────────────────────────────────── */

    public static function colonnes(): array
    {
        return DB::all('SELECT * FROM kanban_colonne WHERE archive_le IS NULL ORDER BY ordre, id');
    }

    /**
     * Les cartes, par colonne, avec ce qu'il faut pour les afficher.
     *
     * UNE SEULE REQUÊTE POUR TOUT LE TABLEAU. Une par colonne ferait dix
     * requêtes, et une par carte pour aller chercher le contact en ferait
     * deux cents — le tableau est la page qu'on ouvre le plus souvent.
     */
    public static function cartes(): array
    {
        $r = DB::all(
            "SELECT k.*,
                    c.nom AS c_nom, c.prenom AS c_prenom, c.nom_famille AS c_famille,
                    c.structure AS c_struct, c.email1 AS c_mail, c.ville_struct AS c_ville,
                    c.pays_struct AS c_pays, c.photo AS c_photo,
                    b.venue AS b_venue, b.ville AS b_ville, b.date_debut AS b_date, b.projet AS b_projet,
                    o.projet AS o_projet, o.venue AS o_venue, o.statut AS o_statut,
                    p.title_fr AS p_titre
               FROM kanban_carte k
               LEFT JOIN contact  c ON c.id = k.contact_id AND c.supprime_le IS NULL
               LEFT JOIN booking  b ON b.id = k.booking_id AND b.supprime_le IS NULL
               LEFT JOIN offer    o ON o.id = k.offer_id
               LEFT JOIN projects p ON p.id = k.project_id
              WHERE k.archive_le IS NULL
              ORDER BY k.colonne_id, k.ordre, k.id");

        $par = [];
        foreach ($r as $x) $par[(int)$x['colonne_id']][] = $x;
        return $par;
    }

    /* ── Les colonnes ───────────────────────────────────────────────────── */

    public static function colonneCreer(string $titre, string $couleur = 'neutre'): int
    {
        $titre = trim($titre);
        if ($titre === '') return 0;
        $max = (int)DB::val('SELECT COALESCE(MAX(ordre), 0) FROM kanban_colonne');
        return DB::insert('kanban_colonne', [
            'titre'   => mb_substr($titre, 0, 120),
            'ordre'   => $max + 10,
            'couleur' => isset(self::COULEURS[$couleur]) ? $couleur : 'neutre',
        ]);
    }

    public static function colonneRenommer(int $id, string $titre, string $couleur): void
    {
        $titre = trim($titre);
        if ($id <= 0 || $titre === '') return;
        DB::run('UPDATE kanban_colonne SET titre = ?, couleur = ? WHERE id = ?',
                [mb_substr($titre, 0, 120), isset(self::COULEURS[$couleur]) ? $couleur : 'neutre', $id]);
    }

    /**
     * Archive une colonne ET ses cartes.
     *
     * Les cartes ne sont PAS remises ailleurs: une carte orpheline réapparaîtrait
     * dans la première colonne venue, sans que personne ait demandé qu'elle y
     * aille. Archivées avec leur colonne, elles reviennent toutes ensemble si
     * l'on rouvre la colonne à la main en base.
     */
    public static function colonneArchiver(int $id): void
    {
        if ($id <= 0) return;
        DB::run('UPDATE kanban_carte   SET archive_le = NOW() WHERE colonne_id = ? AND archive_le IS NULL', [$id]);
        DB::run('UPDATE kanban_colonne SET archive_le = NOW() WHERE id = ?', [$id]);
    }

    /** Réordonne les colonnes d'après la suite d'identifiants reçue. */
    public static function colonnesOrdre(array $ids): void
    {
        $n = 10;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            DB::run('UPDATE kanban_colonne SET ordre = ? WHERE id = ?', [$n, $id]);
            $n += 10;
        }
    }

    /* ── Les cartes ─────────────────────────────────────────────────────── */

    public static function carteCreer(int $colonneId, string $titre, array $liens = []): int
    {
        $titre = trim($titre);
        if ($colonneId <= 0 || $titre === '') return 0;
        if (!DB::one('SELECT id FROM kanban_colonne WHERE id = ? AND archive_le IS NULL', [$colonneId])) return 0;

        $max = (int)DB::val('SELECT COALESCE(MAX(ordre), 0) FROM kanban_carte WHERE colonne_id = ?', [$colonneId]);
        return DB::insert('kanban_carte', [
            'colonne_id' => $colonneId,
            'ordre'      => $max + 10,
            'titre'      => mb_substr($titre, 0, 190),
            'contact_id' => ($liens['contact_id'] ?? 0) ?: null,
            'booking_id' => ($liens['booking_id'] ?? 0) ?: null,
            'offer_id'   => ($liens['offer_id']   ?? 0) ?: null,
            'project_id' => ($liens['project_id'] ?? 0) ?: null,
        ]);
    }

    public static function carteEcrire(int $id, string $titre, ?string $note, ?string $echeance): void
    {
        if ($id <= 0) return;
        $titre = trim($titre);
        $maj = [];
        if ($titre !== '') $maj['titre'] = mb_substr($titre, 0, 190);
        if ($note !== null) $maj['note'] = trim($note) !== '' ? $note : null;
        if ($echeance !== null) {
            $maj['echeance'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $echeance) ? $echeance : null;
        }
        if (!$maj) return;
        $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($maj)));
        DB::run("UPDATE kanban_carte SET $sets WHERE id = ?", [...array_values($maj), $id]);
    }

    public static function carteArchiver(int $id): void
    {
        if ($id > 0) DB::run('UPDATE kanban_carte SET archive_le = NOW() WHERE id = ?', [$id]);
    }

    /**
     * Déplace une carte dans une colonne, à la position voulue.
     *
     * `$avantId` est la carte devant laquelle se poser, ou 0 pour la fin.
     * On calcule un rang ENTRE les voisines plutôt que de tout renuméroter:
     * c'est ce qui permet à deux personnes de glisser des cartes en même temps
     * sans que l'une défasse l'ordre de l'autre.
     */
    public static function carteDeplacer(int $id, int $colonneId, int $avantId = 0): bool
    {
        if ($id <= 0 || $colonneId <= 0) return false;
        if (!DB::one('SELECT id FROM kanban_colonne WHERE id = ? AND archive_le IS NULL', [$colonneId])) return false;
        if (!DB::one('SELECT id FROM kanban_carte   WHERE id = ? AND archive_le IS NULL', [$id])) return false;

        if ($avantId > 0) {
            $ap = DB::one('SELECT ordre FROM kanban_carte WHERE id = ? AND colonne_id = ? AND archive_le IS NULL',
                          [$avantId, $colonneId]);
            if (!$ap) $avantId = 0;
        }

        if ($avantId === 0) {
            $ordre = (int)DB::val('SELECT COALESCE(MAX(ordre), 0) FROM kanban_carte
                                    WHERE colonne_id = ? AND archive_le IS NULL AND id <> ?',
                                  [$colonneId, $id]) + 10;
        } else {
            $apres = (int)$ap['ordre'];
            $avant = DB::val('SELECT COALESCE(MAX(ordre), ?) FROM kanban_carte
                               WHERE colonne_id = ? AND archive_le IS NULL AND id <> ? AND ordre < ?',
                             [$apres - 20, $colonneId, $id, $apres]);
            $ordre = (int)floor(((int)$avant + $apres) / 2);

            /* Plus de place entre les deux: on renumérote CETTE colonne, une
               fois, puis on recommence. Sans cela deux cartes finiraient au même
               rang et l'ordre deviendrait celui de l'identifiant, au hasard. */
            if ($ordre <= (int)$avant || $ordre >= $apres) {
                $n = 10;
                foreach (DB::all('SELECT id FROM kanban_carte WHERE colonne_id = ? AND archive_le IS NULL
                                   ORDER BY ordre, id', [$colonneId]) as $c) {
                    DB::run('UPDATE kanban_carte SET ordre = ? WHERE id = ?', [$n, (int)$c['id']]);
                    $n += 10;
                }
                return self::carteDeplacer($id, $colonneId, $avantId);
            }
        }

        DB::run('UPDATE kanban_carte SET colonne_id = ?, ordre = ? WHERE id = ?', [$colonneId, $ordre, $id]);
        return true;
    }
}

<?php
/**
 * L'agenda des rappels: ce qui est écrit, plus ce qui a une échéance ailleurs.
 * [16.08.2026]
 *
 * LE PRINCIPE, ET C'EST TOUT LE FICHIER. Une seule liste, alimentée par six
 * sources qui gardent chacune leur vérité chez elle:
 *
 *   rappel         ce qu'on a écrit à la main
 *   admin_tache    les obligations du mois — 188 en ont une
 *   demande_fonds  le délai de dépôt, et la date de réponse annoncée
 *   booking        les dates jouées et non encaissées
 *   kanban_carte   l'échéance posée sur une carte du pipeline
 *
 * RIEN N'EST RECOPIÉ. Cocher une obligation dans l'écran Administration la fait
 * disparaître d'ici au rechargement suivant, sans qu'aucun code ne s'en occupe.
 * Une table de rappels qui dupliquerait ces échéances serait fausse dès la
 * première fois qu'on coche ailleurs — et deux vérités pour la même échéance
 * font une échéance qu'on ignore des deux côtés.
 *
 * CE QUI ENTRE ET CE QUI RESTE DEHORS. Les 188 obligations couvrent deux mois
 * entiers; les verser toutes ferait une liste que personne ne lit. On prend ce
 * qui est en retard, plus ce qui tombe dans la fenêtre demandée. Le seuil est
 * un argument et non une constante cachée: l'écran l'expose.
 */
declare(strict_types=1);

final class Rappels
{
    /* ── Ce qu'on écrit ─────────────────────────────────────────────────── */

    public static function creer(array $p, string $par = ''): int
    {
        $texte = trim((string)($p['texte'] ?? ''));
        $quand = trim((string)($p['quand'] ?? ''));
        if ($texte === '') return 0;

        /* Une date seule vaut minuit. Refuser « 2026-09-01 » parce qu'il manque
           l'heure ferait perdre la saisie pour une précision qu'on n'a pas. */
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $quand)) $quand .= ' 00:00:00';
        elseif (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $quand)) $quand = str_replace('T', ' ', $quand) . ':00';
        else return 0;

        return DB::insert('rappel', [
            'quand'           => substr($quand, 0, 19),
            'texte'           => mb_substr($texte, 0, 500),
            'note'            => trim((string)($p['note'] ?? '')) ?: null,
            'pour_qui'        => mb_substr(trim((string)($p['pour_qui'] ?? '')), 0, 96) ?: null,
            'contact_id'      => (int)($p['contact_id'] ?? 0) ?: null,
            'booking_id'      => (int)($p['booking_id'] ?? 0) ?: null,
            'offer_id'        => (int)($p['offer_id'] ?? 0) ?: null,
            'project_id'      => (int)($p['project_id'] ?? 0) ?: null,
            'organisation_id' => (int)($p['organisation_id'] ?? 0) ?: null,
            'cree_par'        => mb_substr($par, 0, 96) ?: null,
        ]);
    }

    public static function fait(int $id, string $par, bool $fait = true): void
    {
        if ($id <= 0) return;
        DB::run('UPDATE rappel SET fait_le = ?, fait_par = ? WHERE id = ?',
                [$fait ? date('Y-m-d H:i:s') : null, $fait ? mb_substr($par, 0, 96) : null, $id]);
    }

    public static function archiver(int $id): void
    {
        if ($id > 0) DB::run('UPDATE rappel SET archive_le = NOW() WHERE id = ?', [$id]);
    }

    /** Reporte un rappel de N jours, en gardant l'heure. */
    public static function reporter(int $id, int $jours): void
    {
        if ($id <= 0 || $jours === 0) return;
        DB::run('UPDATE rappel SET quand = DATE_ADD(quand, INTERVAL ? DAY), fait_le = NULL
                  WHERE id = ?', [$jours, $id]);
    }

    /* ── La liste unique ────────────────────────────────────────────────── */

    /**
     * Tout ce qui attend, dans une seule suite triée par date.
     *
     * @param int $jours fenêtre vers l'avant. Le retard entre toujours.
     * @return array<int,array{quand:string,texte:string,source:string,lien:string,
     *                         sous:string,id:int,cochable:bool}>
     */
    /**
     * Jusqu'où l'on remonte dans le retard, pour les échéances DÉRIVÉES.
     *
     * MESURÉ AVANT DE CHOISIR: sans limite, la liste sortait 199 lignes en
     * retard, dont l'écrasante majorité des délais de subvention de 2024 —
     * des demandes dont le statut n'a jamais été fermé. Un agenda qui ouvre sur
     * deux cents lignes mortes n'est pas consulté deux fois, et les trois
     * lignes vivantes qu'il contenait sont perdues avec le reste.
     *
     * Quatre-vingt-dix jours: au-delà, une date de dépôt est passée pour de
     * bon et la ligne dit surtout que quelqu'un doit clore la demande. On
     * compte ce qu'on cache et on l'écrit à l'écran — cacher en silence serait
     * pire que la liste illisible.
     */
    private const RETARD_MAX = 90;

    /** Ce que le dernier appel à `agenda()` a écarté, par source. */
    public static array $ecartes = [];

    public static function agenda(int $jours = 30): array
    {
        $auj    = date('Y-m-d');
        $limite = date('Y-m-d', strtotime("+$jours days"));
        /* Le plancher ne s'applique QU'aux sources dérivées. Un rappel écrit à
           la main reste affiché quel que soit son âge: on l'a écrit, il est à
           nous, et le faire disparaître serait le perdre. Un encaissement non
           reçu non plus — de l'argent dû ne se périme pas. */
        $plancher = date('Y-m-d', strtotime('-' . self::RETARD_MAX . ' days'));
        self::$ecartes = [];
        $out = [];

        /* 1. Les rappels écrits. */
        foreach (DB::all(
            "SELECT r.*, c.nom AS c_nom, c.prenom AS c_prenom, c.nom_famille AS c_fam,
                    b.venue AS b_venue, b.projet AS b_projet, p.title_fr AS p_titre,
                    o.nom AS o_nom
               FROM rappel r
               LEFT JOIN contact      c ON c.id = r.contact_id AND c.supprime_le IS NULL
               LEFT JOIN booking      b ON b.id = r.booking_id AND b.supprime_le IS NULL
               LEFT JOIN projects     p ON p.id = r.project_id
               LEFT JOIN organisation o ON o.id = r.organisation_id
              WHERE r.archive_le IS NULL AND r.fait_le IS NULL
                AND DATE(r.quand) <= ?
              ORDER BY r.quand", [$limite]) as $r) {

            $sous = trim((string)($r['pour_qui'] ?? ''));
            $lien = '';
            if ($r['contact_id']) {
                $n = trim(((string)$r['c_prenom']) . ' ' . ((string)$r['c_fam'])) ?: (string)$r['c_nom'];
                $sous = trim($sous . ($sous ? ' · ' : '') . $n);
                $lien = '/dashboard.php?e=contacts&c=' . (int)$r['contact_id'];
            } elseif ($r['booking_id']) {
                $sous = trim($sous . ($sous ? ' · ' : '') . trim((string)$r['b_projet'] . ' ' . (string)$r['b_venue']));
                $lien = '/dashboard.php?e=bookings&b=' . (int)$r['booking_id'];
            } elseif ($r['project_id']) {
                $sous = trim($sous . ($sous ? ' · ' : '') . (string)$r['p_titre']);
                $lien = '/dashboard.php?e=projets&p=' . (int)$r['project_id'];
            } elseif ($r['organisation_id']) {
                $sous = trim($sous . ($sous ? ' · ' : '') . (string)$r['o_nom']);
                $lien = '/dashboard.php?e=associations&o=' . (int)$r['organisation_id'];
            }

            $out[] = ['quand' => (string)$r['quand'], 'texte' => (string)$r['texte'],
                      'source' => 'rappel', 'lien' => $lien, 'sous' => $sous,
                      'id' => (int)$r['id'], 'cochable' => true,
                      'note' => (string)($r['note'] ?? '')];
        }

        /* 2. Les obligations administratives. Seulement le retard et la fenêtre:
              188 lignes versées d'un coup feraient une liste illisible. */
        foreach (DB::all(
            "SELECT t.id, t.echeance, t.periode, m.libelle, o.nom
               FROM admin_tache t
               JOIN admin_modele m ON m.id = t.modele_id
               LEFT JOIN organisation o ON o.id = t.organisation_id
              WHERE t.etat IN ('a_faire','en_cours') AND t.echeance IS NOT NULL
                AND t.echeance <= ? AND t.echeance >= ?
              ORDER BY t.echeance LIMIT 120", [$limite, $plancher]) as $t) {
            $out[] = ['quand' => (string)$t['echeance'] . ' 00:00:00',
                      'texte' => (string)$t['libelle'],
                      'source' => 'administration',
                      'lien' => '/dashboard.php?e=administration&m=' . e((string)$t['periode']),
                      'sous' => (string)($t['nom'] ?? ''), 'id' => (int)$t['id'],
                      'cochable' => false, 'note' => ''];
        }

        $n = (int)DB::val("SELECT COUNT(*) FROM admin_tache
                            WHERE etat IN ('a_faire','en_cours') AND echeance IS NOT NULL
                              AND echeance < ?", [$plancher]);
        if ($n) self::$ecartes['administration'] = $n;

        /* 3. Les demandes de fonds: le délai de dépôt, puis la réponse annoncée. */
        foreach (DB::all(
            "SELECT id, inst, proj, asso, delai, reponse, statut FROM demande_fonds
              WHERE supprime_le IS NULL AND statut NOT IN ('accorde','refuse','decompte')
                AND ((delai IS NOT NULL AND delai <= ?) OR (reponse IS NOT NULL AND reponse <= ?))
              ORDER BY COALESCE(delai, reponse) LIMIT 200", [$limite, $limite]) as $g) {
            foreach ([['delai', 'Déposer la demande'], ['reponse', 'Réponse attendue']] as [$col, $quoi]) {
                if (!$g[$col] || $g[$col] > $limite) continue;
                if ($g[$col] < $plancher) {
                    self::$ecartes['fonds'] = (self::$ecartes['fonds'] ?? 0) + 1;
                    continue;
                }
                $out[] = ['quand' => (string)$g[$col] . ' 00:00:00',
                          'texte' => $quoi . ' — ' . (string)$g['inst'],
                          'source' => 'fonds',
                          'lien' => '/dashboard.php?e=finances&v=fonds',
                          'sous' => trim((string)$g['asso'] . ' · ' . (string)$g['proj'], ' ·'),
                          'id' => (int)$g['id'], 'cochable' => false, 'note' => ''];
            }
        }

        /* 4. Les dates jouées et non encaissées. Leur « échéance » est la date
              de la représentation: passée, l'argent aurait dû rentrer. */
        foreach (DB::all(
            "SELECT id, projet, venue, ville, date_debut, prix_cession, devise
               FROM booking
              WHERE supprime_le IS NULL AND encaissement = 'attendu'
                AND date_debut IS NOT NULL AND date_debut < ?
              ORDER BY date_debut LIMIT 60", [$auj]) as $b) {
            $out[] = ['quand' => (string)$b['date_debut'] . ' 00:00:00',
                      'texte' => 'Encaissement attendu' . ((float)$b['prix_cession'] > 0
                          ? ' — ' . number_format((float)$b['prix_cession'], 0, ',', ' ') . ' ' . $b['devise'] : ''),
                      'source' => 'encaissement',
                      'lien' => '/dashboard.php?e=bookings&b=' . (int)$b['id'],
                      'sous' => trim((string)$b['projet'] . ' · ' . (string)$b['venue'] . ' · ' . (string)$b['ville'], ' ·'),
                      'id' => (int)$b['id'], 'cochable' => false, 'note' => ''];
        }

        /* 5. Les cartes du pipeline qui portent une échéance. */
        foreach (DB::all(
            "SELECT k.id, k.titre, k.note, k.echeance, c.titre AS col
               FROM kanban_carte k JOIN kanban_colonne c ON c.id = k.colonne_id
              WHERE k.archive_le IS NULL AND c.archive_le IS NULL
                AND k.echeance IS NOT NULL AND k.echeance <= ?
              ORDER BY k.echeance LIMIT 60", [$limite]) as $k) {
            $out[] = ['quand' => (string)$k['echeance'] . ' 00:00:00',
                      'texte' => trim((string)$k['note']) !== '' ? (string)$k['note'] : (string)$k['titre'],
                      'source' => 'pipeline',
                      'lien' => '/dashboard.php?e=accueil',
                      'sous' => (string)$k['titre'] . ' · ' . (string)$k['col'],
                      'id' => (int)$k['id'], 'cochable' => false, 'note' => ''];
        }

        usort($out, fn($a, $b) => strcmp($a['quand'], $b['quand']));
        return $out;
    }

    /**
     * La même liste, rangée en tranches lisibles.
     *
     * Les tranches sont celles d'un bureau, pas d'un calendrier: ce qui est en
     * retard passe devant tout, et « plus tard » n'a pas besoin d'être détaillé.
     */
    public static function parTranche(int $jours = 30): array
    {
        $auj    = date('Y-m-d');
        $demain = date('Y-m-d', strtotime('+1 day'));
        $sem    = date('Y-m-d', strtotime('+7 days'));

        $t = ['retard' => [], 'aujourdhui' => [], 'demain' => [], 'semaine' => [], 'apres' => []];
        foreach (self::agenda($jours) as $x) {
            $j = substr($x['quand'], 0, 10);
            if     ($j <  $auj)    $t['retard'][]     = $x;
            elseif ($j === $auj)   $t['aujourdhui'][] = $x;
            elseif ($j === $demain) $t['demain'][]    = $x;
            elseif ($j <= $sem)    $t['semaine'][]    = $x;
            else                   $t['apres'][]      = $x;
        }
        return $t;
    }

    /** Combien de choses sont en retard. Sert au bandeau du tableau de bord. */
    public static function enRetard(): int
    {
        $t = self::parTranche(0);
        return count($t['retard']);
    }
}

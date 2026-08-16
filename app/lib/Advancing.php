<?php
/**
 * L'advancing d'un booking, des deux côtés du mur. [16.08.2026]
 *
 * D'UN CÔTÉ le bureau, qui construit la liste de ce qu'il demande et valide ce
 * qui revient. DE L'AUTRE le lieu, qui ouvre un lien et répond. Les deux
 * passent par ce fichier, et c'est voulu: la règle qui dit ce que le lieu a le
 * droit de voir et de changer doit exister à UN endroit, sinon le portail et
 * l'écran finiront par ne plus dire la même chose.
 *
 * CE QUE LE LIEU NE PEUT JAMAIS FAIRE, et qui est vérifié ici plutôt que dans
 * le formulaire:
 *
 *   - créer, supprimer ou renommer un champ. Il répond, il ne décide pas de
 *     la liste
 *   - lire `note_interne`. C'est la colonne qui porte « le régisseur est
 *     difficile » et « prévoir large, ils sont toujours en retard »
 *   - passer un champ à « accepté ». Recevoir n'est pas valider, et si le lieu
 *     pouvait valider lui-même, la validation ne voudrait plus rien dire
 *
 * LE JETON. 64 caractères hexadécimaux tirés de random_bytes: deviner n'est
 * pas une menace réaliste. Il expire, il se révoque, il ne vaut que pour une
 * date, et chaque visite est comptée — un lien qu'on croyait remis à une
 * personne et qui est visité trente fois se voit.
 */
declare(strict_types=1);

class Advancing
{
    /** Durée de vie par défaut d'un lien, en jours. */
    public const JOURS = 120;

    public const TYPES = [
        'texte'   => 'texte court',
        'long'    => 'texte long',
        'nombre'  => 'nombre',
        'date'    => 'date',
        'heure'   => 'heure',
        'oui_non' => 'oui / non',
        'fichier' => 'fichier',
    ];

    public const ETATS = [
        'demande' => 'demandé',
        'recu'    => 'reçu',
        'accepte' => 'accepté',
        'refuse'  => 'à refaire',
    ];

    /* ── Les champs ─────────────────────────────────────────────────────── */

    public static function champs(int $bookingId): array
    {
        return DB::all('SELECT * FROM advancing_field WHERE booking_id = ?
                        ORDER BY ordre, id', [$bookingId]);
    }

    public static function champ(int $id): ?array
    {
        return DB::one('SELECT * FROM advancing_field WHERE id = ?', [$id]);
    }

    public static function ajouter(int $bookingId, array $d): int
    {
        $type = (string)($d['type'] ?? 'texte');
        if (!isset(self::TYPES[$type])) $type = 'texte';

        return DB::insert('advancing_field', [
            'booking_id'  => $bookingId,
            'section'     => trim((string)($d['section'] ?? '')) ?: null,
            'libelle'     => mb_substr(trim((string)($d['libelle'] ?? '')), 0, 190),
            'type'        => $type,
            'obligatoire' => !empty($d['obligatoire']) ? 1 : 0,
            'ordre'       => (int)($d['ordre'] ?? 100),
            'consigne'    => trim((string)($d['consigne'] ?? '')) ?: null,
        ]);
    }

    /** Le bureau change l'état. Le lieu ne passe jamais par ici. */
    public static function etat(int $champId, int $bookingId, string $etat): void
    {
        if (!isset(self::ETATS[$etat])) return;
        DB::update('advancing_field', ['etat' => $etat],
                   'id = ? AND booking_id = ?', [$champId, $bookingId]);
    }

    public static function supprimer(int $champId, int $bookingId): void
    {
        $c = self::champ($champId);
        if ($c && (int)$c['booking_id'] === $bookingId && $c['fichier']) {
            @unlink(self::dossier($champId) . '/' . $c['fichier']);
            @rmdir(self::dossier($champId));
        }
        DB::delete('advancing_field', 'id = ? AND booking_id = ?', [$champId, $bookingId]);
    }

    /** Combien manque-t-il encore, et sur combien. */
    public static function avancement(int $bookingId): array
    {
        $c = self::champs($bookingId);
        $n = ['total' => count($c), 'demande'=>0, 'recu'=>0, 'accepte'=>0, 'refuse'=>0,
              'manquants_obligatoires' => 0];
        foreach ($c as $x) {
            $n[$x['etat']]++;
            if ((int)$x['obligatoire'] === 1 && in_array($x['etat'], ['demande','refuse'], true)) {
                $n['manquants_obligatoires']++;
            }
        }
        return $n;
    }

    /* ── Les fichiers déposés par le lieu ───────────────────────────────── */

    public static function dossier(int $champId): string
    {
        $d = dirname(__DIR__, 2) . '/uploads/private/adv/' . $champId;
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }

    /* ── Le lien remis au lieu ──────────────────────────────────────────── */

    public static function lien(int $bookingId): ?array
    {
        return DB::one('SELECT * FROM advancing_link WHERE booking_id = ?', [$bookingId]);
    }

    /** Crée ou remplace le lien. Remplacer invalide l'ancien, et c'est le but. */
    public static function ouvrirLien(int $bookingId, string $destinataire = ''): array
    {
        $jeton = bin2hex(random_bytes(32));
        $exp   = date('Y-m-d H:i:s', time() + self::JOURS * 86400);

        DB::delete('advancing_link', 'booking_id = ?', [$bookingId]);
        DB::insert('advancing_link', [
            'booking_id'   => $bookingId,
            'jeton'        => $jeton,
            'destinataire' => trim($destinataire) !== '' ? mb_substr(trim($destinataire), 0, 190) : null,
            'expire_a'     => $exp,
        ]);
        return self::lien($bookingId) ?? [];
    }

    public static function revoquer(int $bookingId): void
    {
        DB::update('advancing_link', ['revoque' => 1], 'booking_id = ?', [$bookingId]);
    }

    /**
     * Le booking derrière un jeton, ou null.
     *
     * Toutes les raisons de refuser rendent null, sans dire laquelle: un
     * portail qui distingue « jeton inconnu » de « jeton expiré » apprend à qui
     * essaie que la première moitié était bonne.
     */
    public static function parJeton(string $jeton): ?array
    {
        $jeton = trim($jeton);
        if (!preg_match('/^[0-9a-f]{64}$/', $jeton)) return null;

        $l = DB::one('SELECT * FROM advancing_link WHERE jeton = ?', [$jeton]);
        if (!$l) return null;
        if ((int)$l['revoque'] === 1) return null;
        if ($l['expire_a'] !== null && strtotime((string)$l['expire_a']) < time()) return null;

        return $l;
    }

    public static function noterVisite(int $bookingId): void
    {
        DB::run('UPDATE advancing_link SET visites = visites + 1, dernier_acces = NOW()
                 WHERE booking_id = ?', [$bookingId]);
    }

    /**
     * Enregistre ce que le lieu a répondu.
     *
     * N'écrit QUE `reponse`, `fichier`, `etat` et `repondu_a`, et seulement
     * pour des champs de ce booking. Le libellé, le type, l'ordre, la consigne
     * et la note interne ne sont pas touchables depuis le portail, même si
     * quelqu'un fabrique le POST à la main.
     *
     * L'état ne monte jamais au-delà de « reçu »: valider est un geste du
     * bureau. Un champ déjà « accepté » qu'on modifie redescend à « reçu »,
     * parce que ce qui a été validé n'est plus ce qui est là.
     *
     * @return int le nombre de champs modifiés
     */
    public static function repondre(int $bookingId, array $reponses, array $fichiers = []): int
    {
        $champs = self::champs($bookingId);
        $parId  = [];
        foreach ($champs as $c) $parId[(int)$c['id']] = $c;

        $n = 0;
        foreach ($reponses as $cid => $val) {
            $cid = (int)$cid;
            if (!isset($parId[$cid])) continue;            // pas un champ de ce booking
            $c = $parId[$cid];
            if ($c['type'] === 'fichier') continue;        // traité plus bas

            $val = is_string($val) ? trim($val) : '';
            if ($val === '' && (string)($c['reponse'] ?? '') === '') continue;

            DB::update('advancing_field', [
                'reponse'   => mb_substr($val, 0, 20000),
                'etat'      => $val === '' ? 'demande' : 'recu',
                'repondu_a' => $val === '' ? null : date('Y-m-d H:i:s'),
            ], 'id = ? AND booking_id = ?', [$cid, $bookingId]);
            $n++;
        }

        foreach ($fichiers as $cid => $f) {
            $cid = (int)$cid;
            if (!isset($parId[$cid]) || $parId[$cid]['type'] !== 'fichier') continue;
            if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            if (!is_uploaded_file((string)$f['tmp_name'])) continue;
            if ((int)($f['size'] ?? 0) > 25 * 1024 * 1024) continue;   // 25 Mo

            $nom = self::nomSur((string)($f['name'] ?? 'fichier'));
            if ($nom === '') continue;

            $dir = self::dossier($cid);
            foreach (glob($dir . '/*') ?: [] as $vieux) @unlink($vieux);
            if (!move_uploaded_file((string)$f['tmp_name'], $dir . '/' . $nom)) continue;

            DB::update('advancing_field', [
                'fichier'   => $nom,
                'etat'      => 'recu',
                'repondu_a' => date('Y-m-d H:i:s'),
            ], 'id = ? AND booking_id = ?', [$cid, $bookingId]);
            $n++;
        }
        return $n;
    }

    /**
     * Un nom de fichier sûr.
     *
     * La liste est fermée plutôt qu'ouverte: on accepte ce dont l'advancing a
     * besoin — plans, fiches, images, feuilles — et rien d'autre. Un `.php`
     * déposé dans uploads/ ne serait pas servi, le dossier est refusé par
     * Apache, mais on ne fait pas reposer cela sur une seule serrure.
     */
    private static function nomSur(string $nom): string
    {
        $nom = basename($nom);
        $ext = strtolower((string)pathinfo($nom, PATHINFO_EXTENSION));
        $ok  = ['pdf','png','jpg','jpeg','gif','webp','svg','dwg','dxf',
                'doc','docx','xls','xlsx','csv','txt','zip'];
        if (!in_array($ext, $ok, true)) return '';

        $base = (string)pathinfo($nom, PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) ?: 'fichier';
        return mb_substr($base, 0, 150) . '.' . $ext;
    }
}

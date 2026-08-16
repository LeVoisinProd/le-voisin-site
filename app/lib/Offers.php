<?php
/**
 * Les demandes de booking entrantes. [16.08.2026]
 *
 * Deux appelants: le formulaire public (demande.php) qui crée, et l'écran du
 * bureau (app/dash/offres.php) qui trie et convertit. La règle de ce qu'une
 * demande venue du dehors a le droit d'écrire vit ici, à un seul endroit.
 *
 * LE FORMULAIRE EST PUBLIC, DONC C'EST UNE CIBLE. Trois garde-fous, et aucun
 * n'est un CAPTCHA — un CAPTCHA fait fuir un programmateur pressé, qui est
 * précisément la personne qu'on veut:
 *
 *   1. UN CHAMP PIÈGE, invisible et vide. Les robots remplissent tout ce
 *      qu'ils trouvent. Rempli, on répond « merci » et on n'enregistre rien:
 *      dire « refusé » apprendrait au robot à réessayer autrement.
 *   2. LE TEMPS DE REMPLISSAGE. L'horodatage part signé avec le secret du
 *      site; moins de quatre secondes, c'est une machine. Signé, parce qu'un
 *      champ caché non signé se recopie à la valeur qu'on veut.
 *   3. UN PLAFOND PAR ADRESSE, six par heure. Assez pour qu'un théâtre envoie
 *      plusieurs demandes le même jour, trop peu pour une vague.
 *
 * CE QUE LE FORMULAIRE NE PEUT PAS ÉCRIRE: `statut`, `contre_prix`,
 * `notes_internes`, `booking_id`. Ils ne figurent pas dans creer(), donc même
 * un POST fabriqué ne les atteint pas.
 */
declare(strict_types=1);

class Offers
{
    public const STATUTS = [
        'nouvelle'        => 'nouvelle',
        'en_discussion'   => 'en discussion',
        'contre_proposee' => 'contre-proposée',
        'acceptee'        => 'acceptée',
        'refusee'         => 'refusée',
        'sans_suite'      => 'sans suite',
    ];

    /** Délai minimum de remplissage, en secondes. */
    private const DELAI_MIN = 4;

    /** Plafond par adresse IP et par heure. */
    private const PLAFOND = 6;

    /* ── Le jeton de temps du formulaire ────────────────────────────────── */

    public static function jetonTemps(): string
    {
        $t = (string)time();
        return $t . '.' . hash_hmac('sha256', $t, (string)cfg('secret', ''));
    }

    private static function jetonTempsOk(string $jeton): bool
    {
        $p = explode('.', $jeton, 2);
        if (count($p) !== 2) return false;
        $t = (int)$p[0];
        if (!hash_equals(hash_hmac('sha256', (string)$t, (string)cfg('secret', '')), $p[1])) return false;
        $age = time() - $t;
        /* Trop vite, c'est un robot. Trop vieux — plus de six heures — c'est
           une page laissée ouverte la veille, et le jeton doit expirer. */
        return $age >= self::DELAI_MIN && $age <= 6 * 3600;
    }

    private static function trop(string $ip): bool
    {
        if ($ip === '') return false;
        $n = (int)DB::val('SELECT COUNT(*) FROM offer WHERE ip = ? AND cree_a > (NOW() - INTERVAL 1 HOUR)', [$ip]);
        return $n >= self::PLAFOND;
    }

    /* ── Créer, depuis le formulaire public ─────────────────────────────── */

    /**
     * @return array{ok:bool, message:string, id:int}
     *
     * Rend toujours un message montrable. Le seul cas où l'on ment est le
     * champ piège: on répond « merci » sans rien écrire.
     */
    public static function creer(array $p, string $ip): array
    {
        $merci = ['ok' => true, 'message' => '', 'id' => 0];

        // 1. le champ piège
        if (trim((string)($p['site_web'] ?? '')) !== '') return $merci;

        // 2. le temps de remplissage
        if (!self::jetonTempsOk((string)($p['_t'] ?? ''))) {
            return ['ok' => false, 'id' => 0, 'message' =>
                'Le formulaire a expiré ou a été envoyé trop vite. Rechargez la page et réessayez.'];
        }

        // 3. le plafond par adresse
        if (self::trop($ip)) {
            return ['ok' => false, 'id' => 0, 'message' =>
                'Trop de demandes depuis cette connexion. Réessayez dans une heure, ou écrivez-nous directement.'];
        }

        $email = trim((string)($p['contact_email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'id' => 0, 'message' =>
                'Il nous faut une adresse e-mail valide pour vous répondre.'];
        }
        if (trim((string)($p['contact_nom'] ?? '')) === '') {
            return ['ok' => false, 'id' => 0, 'message' => 'Merci d\'indiquer votre nom.'];
        }

        $c = static fn(string $k, int $max = 190): ?string
            => trim((string)($p[$k] ?? '')) !== '' ? mb_substr(trim((string)$p[$k]), 0, $max) : null;

        $date = trim((string)($p['date_souhaitee'] ?? ''));
        $bud  = trim((string)($p['budget'] ?? ''));

        $id = DB::insert('offer', [
            'projet'          => $c('projet'),
            'venue'           => $c('venue'),
            'venue_url'       => $c('venue_url', 400),
            'ville'           => $c('ville', 96),
            'pays'            => $c('pays', 64),
            'date_souhaitee'  => $date !== '' ? date('Y-m-d', strtotime($date)) : null,
            'date_texte'      => $c('date_texte'),
            'representations' => (int)($p['representations'] ?? 0) ?: null,
            'budget'          => $bud !== '' ? (float)str_replace(',', '.', $bud) : null,
            'devise'          => in_array($p['devise'] ?? '', ['CHF','EUR'], true) ? $p['devise'] : 'EUR',
            'contact_nom'     => $c('contact_nom'),
            'contact_role'    => $c('contact_role', 120),
            'contact_email'   => mb_substr($email, 0, 190),
            'contact_tel'     => $c('contact_tel', 40),
            'structure'       => $c('structure'),
            'message'         => mb_substr(trim((string)($p['message'] ?? '')), 0, 8000) ?: null,
            'ip'              => mb_substr($ip, 0, 45),
        ]);

        return ['ok' => true, 'id' => $id, 'message' =>
            'Votre demande est arrivée. Nous revenons vers vous — en général sous une semaine.'];
    }

    /* ── Côté bureau ────────────────────────────────────────────────────── */

    /**
     * Une demande saisie au bureau. [16.08.2026]
     *
     * POURQUOI ELLE EXISTE. Le pipeline ne se remplissait que par le formulaire
     * public, et les demandes réelles n'y passent pas: elles arrivent par
     * courriel, au téléphone, dans un couloir de festival. Anna, le 16.08.2026:
     * « todas as offres de bestiarium deveriam estar ali ». Sans cette porte,
     * l'écran reste vide en permanence tout en prétendant être le pipeline —
     * c'est-à-dire qu'il ment, et qu'on retourne dans la boîte de réception.
     *
     * ELLE NE PASSE AUCUN DES TROIS FILTRES ANTI-ROBOT, et c'est correct: le
     * piège, le délai minimal et le plafond par adresse protègent d'un
     * formulaire ouvert au public. Ici la personne est authentifiée et l'écran
     * a déjà exigé le droit d'écriture. Les rejouer ferait échouer une saisie
     * légitime parce qu'elle a été tapée trop vite.
     *
     * Le seul contrôle gardé est le nom: une demande sans personne à qui
     * répondre n'est pas une demande. L'adresse, elle, devient facultative —
     * on connaît des programmateurs qu'on n'appelle qu'au téléphone.
     */
    public static function creerAuBureau(array $p): int
    {
        $nom = trim((string)($p['contact_nom'] ?? ''));
        if ($nom === '') return 0;

        $c = static fn(string $k, int $max = 190): ?string
            => trim((string)($p[$k] ?? '')) !== '' ? mb_substr(trim((string)$p[$k]), 0, $max) : null;

        $date  = trim((string)($p['date_souhaitee'] ?? ''));
        $bud   = trim((string)($p['budget'] ?? ''));
        $email = trim((string)($p['contact_email'] ?? ''));

        return DB::insert('offer', [
            'projet'          => $c('projet'),
            'venue'           => $c('venue'),
            'venue_url'       => $c('venue_url', 400),
            'ville'           => $c('ville', 96),
            'pays'            => $c('pays', 64),
            'date_souhaitee'  => $date !== '' && strtotime($date) ? date('Y-m-d', strtotime($date)) : null,
            'date_texte'      => $c('date_texte'),
            'representations' => (int)($p['representations'] ?? 0) ?: null,
            'budget'          => $bud !== '' ? (float)str_replace(',', '.', $bud) : null,
            'devise'          => in_array($p['devise'] ?? '', ['CHF','EUR'], true) ? $p['devise'] : 'CHF',
            'contact_nom'     => mb_substr($nom, 0, 190),
            'contact_role'    => $c('contact_role', 120),
            'contact_email'   => $email !== '' ? mb_substr($email, 0, 190) : null,
            'contact_tel'     => $c('contact_tel', 40),
            'structure'       => $c('structure'),
            'message'         => mb_substr(trim((string)($p['message'] ?? '')), 0, 8000) ?: null,
            'notes_internes'  => $c('notes_internes', 1000),
            /* `ip` dit d'où vient la demande. « bureau » plutôt qu'une adresse:
               la distinction sert le jour où l'on se demande si une ligne a été
               remplie par le lieu lui-même ou recopiée par nous. */
            'ip'              => 'bureau',
        ]);
    }

    /**
     * Le porteur de chaque spectacle, par titre normalisé.
     *
     * L'offre ne porte que le titre en texte — c'est ce que le lieu écrit dans
     * le formulaire, et lui demander de choisir dans une liste fermée ferait
     * abandonner. On rapproche donc ici, à la lecture, plutôt que d'imposer un
     * identifiant à la saisie: le rapprochement qui échoue laisse simplement la
     * colonne vide, là où une clef étrangère aurait refusé la demande.
     */
    public static function porteurs(): array
    {
        $n = static function (string $s): string {
            $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
            return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($s)) ?? '');
        };
        $m = [];
        foreach (DB::all("SELECT p.title_fr, p.title_en, o.nom
                            FROM projects p
                            JOIN projet_prod pp ON pp.project_id = p.id
                            JOIN organisation o ON o.id = pp.organisation_id
                           WHERE o.supprime_le IS NULL") as $r) {
            foreach ([$r['title_fr'], $r['title_en']] as $t) {
                $k = $n((string)$t);
                if ($k !== '') $m[$k] ??= (string)$r['nom'];
            }
        }
        return $m;
    }

    /** Le porteur d'un titre libre, ou une chaîne vide. */
    public static function porteurDe(string $titre, array $carte): string
    {
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $titre) ?: $titre;
        $k = trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($s)) ?? '');
        if ($k === '') return '';
        if (isset($carte[$k])) return $carte[$k];
        foreach ($carte as $cle => $nom) {
            if ($cle !== '' && (str_contains($k, $cle) || str_contains($cle, $k))) return $nom;
        }
        return '';
    }

    public static function liste(string $statut = ''): array
    {
        if ($statut !== '' && isset(self::STATUTS[$statut])) {
            return DB::all('SELECT * FROM offer WHERE statut = ? ORDER BY cree_a DESC', [$statut]);
        }
        /* Sans filtre, l'ordre suit ce qui demande une décision: les nouvelles
           d'abord, les classées à la fin. Une liste par date seule enterrerait
           une demande de la semaine dernière sous cinq refus d'hier. */
        return DB::all("SELECT * FROM offer ORDER BY
            FIELD(statut,'nouvelle','contre_proposee','en_discussion','acceptee','refusee','sans_suite'),
            cree_a DESC");
    }

    public static function une(int $id): ?array
    {
        return DB::one('SELECT * FROM offer WHERE id = ?', [$id]);
    }

    public static function compter(): array
    {
        $n = array_fill_keys(array_keys(self::STATUTS), 0);
        foreach (DB::all('SELECT statut, COUNT(*) n FROM offer GROUP BY statut') as $r) {
            $n[$r['statut']] = (int)$r['n'];
        }
        return $n;
    }

    public static function statut(int $id, string $statut, ?float $contrePrix = null, ?string $notes = null): void
    {
        if (!isset(self::STATUTS[$statut])) return;
        $maj = ['statut' => $statut, 'traite_a' => date('Y-m-d H:i:s')];
        if ($contrePrix !== null) $maj['contre_prix'] = $contrePrix;
        if ($notes !== null)      $maj['notes_internes'] = mb_substr($notes, 0, 1000);
        DB::update('offer', $maj, 'id = ?', [$id]);
    }

    /**
     * Convertit une offre en booking, une seule fois.
     *
     * LE PRIX RETENU EST LA CONTRE-PROPOSITION si elle existe, sinon le budget
     * annoncé. C'est le seul choix qui ne perd pas d'information: si nous avons
     * contre-proposé, c'est notre chiffre qui a servi de base, pas le leur.
     *
     * @return int l'id du booking, ou 0 si déjà converti
     */
    public static function convertir(int $id): int
    {
        $o = self::une($id);
        if (!$o) return 0;
        if ((int)($o['booking_id'] ?? 0) > 0) return (int)$o['booking_id'];   // déjà fait

        $prix = $o['contre_prix'] !== null ? (float)$o['contre_prix']
              : ($o['budget'] !== null ? (float)$o['budget'] : null);

        $bid = DB::insert('booking', [
            'source'          => 'offre',
            'source_ref'      => (string)$id,
            'projet'          => $o['projet'],
            'venue'           => $o['venue'],
            'venue_url'       => $o['venue_url'],
            'ville'           => $o['ville'],
            'pays'            => $o['pays'],
            'date_debut'      => $o['date_souhaitee'],
            'date_texte'      => $o['date_texte'],
            'prix_cession'    => $prix,
            'devise'          => $o['devise'],
            'client'          => $o['structure'] ?: $o['venue'],
            /* `representations` est NOT NULL avec 1 par défaut, et le
               formulaire public laisse le champ facultatif. Passer null faisait
               échouer la conversion — trouvé par le test, pas en production.
               Une date est au minimum une représentation. */
            'representations' => (int)($o['representations'] ?? 0) ?: 1,
            /* « option » et non « confirmed »: une offre acceptée par nous
               n'est pas une date signée. La confirmer se fait sur le booking,
               quand le contrat revient. */
            'statut'          => 'option',
            'notes_internes'  => self::resume($o),
        ]);

        DB::update('offer', ['booking_id' => $bid, 'statut' => 'acceptee',
                             'traite_a' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        return $bid;
    }

    /** Ce que la demande disait, recopié dans le booking pour ne pas le perdre. */
    private static function resume(array $o): string
    {
        $l = ['Née de la demande #' . $o['id'] . ' du ' . date('d.m.Y', strtotime((string)$o['cree_a'])) . '.'];
        if ($o['contact_nom'])   $l[] = 'Contact : ' . $o['contact_nom']
                                      . ($o['contact_role'] ? ' (' . $o['contact_role'] . ')' : '')
                                      . ($o['contact_email'] ? ' — ' . $o['contact_email'] : '');
        if ($o['budget'] !== null) $l[] = 'Budget annoncé : ' . $o['budget'] . ' ' . $o['devise'] . '.';
        if ($o['contre_prix'] !== null) $l[] = 'Contre-proposition : ' . $o['contre_prix'] . ' ' . $o['devise'] . '.';
        if ($o['message'])       $l[] = "\nCe qu'ils écrivaient :\n" . $o['message'];
        if ($o['notes_internes']) $l[] = "\nNotes : " . $o['notes_internes'];
        return implode("\n", $l);
    }

    /** Les titres des spectacles, pour guider le formulaire sans l'enfermer. */
    public static function spectacles(): array
    {
        try {
            $r = DB::all("SELECT title_fr, title_en FROM projects
                          WHERE visible = 1 ORDER BY sort, title_fr");
        } catch (Throwable $e) { return []; }

        $out = [];
        foreach ($r as $x) {
            $t = trim((string)($x['title_fr'] ?: $x['title_en']));
            if ($t !== '') $out[$t] = $t;
        }
        return array_values($out);
    }
}

<?php
/** Fiche personnelle d'un collaborateur : infos, bio, photo de travail.   [V12-ESPACE] */
class MemberProfile
{
    /**
     * Longueur de la courte bio.   [V17-BIO]
     *
     * 500 signes ne laissaient pas la place d'un parcours : on s'arrêtait au
     * milieu d'une phrase. 2000 tiennent une vraie présentation, tout en
     * restant une « courte bio » — au-delà, ce n'est plus une notice mais un
     * dossier, et cela ne rentre plus sur la fiche imprimée.
     */
    public const BIO_MAX = 2000;

    /**
     * Ce que la base accepte réellement aujourd'hui.
     *
     * La colonne est élargie par « Mettre à jour la base ». Tant que ce clic
     * n'a pas eu lieu, elle est restée à son ancienne taille : écrire 2000
     * signes dedans ferait perdre la fin du texte sans prévenir personne. On
     * s'aligne donc sur la plus petite des deux valeurs — le formulaire
     * annonce et applique cette limite-là —, et la limite passe d'elle-même à
     * 2000 dès que la base est à jour. Personne ne perd une ligne, quel que
     * soit l'ordre des opérations.
     */
    private static ?int $bioMax = null;

    public static function bioMax(): int
    {
        if (self::$bioMax === null) {
            $place = self::BIO_MAX;
            try {
                $col = DB::one("SHOW COLUMNS FROM `member_profiles` LIKE 'bio'");
                if ($col && preg_match('/\((\d+)\)/', (string)($col['Type'] ?? ''), $m)) {
                    $place = (int)$m[1];
                }
            } catch (\Throwable $e) { /* base muette : on garde la limite prévue */ }
            self::$bioMax = max(1, min(self::BIO_MAX, $place));
        }
        return self::$bioMax;
    }

    /* ---------------------------------------------------------------------
       Ce que le compte sait déjà de la personne.               [V30-FICHE-PRE]

       Une fiche de vingt-cinq questions qui s'ouvre entièrement vide décourage
       avant la première ligne — et les trois premières questions, justement,
       ont déjà leur réponse : le nom et l'adresse e-mail ont servi à créer le
       compte, le téléphone est dans la fiche du CMS quand il y a été mis.

       On les recopie donc dans les cases correspondantes. Trois règles :

         — on ne remplit qu'une case VIDE. Ce que la personne a écrit ne bouge
           jamais, même si cela diffère du compte : quelqu'un peut très bien
           préférer donner une autre adresse de contact que son identifiant.

         — rien n'est écrit dans la base à ce moment-là. La case est proposée
           remplie ; elle ne devient une réponse enregistrée qu'au moment où
           la personne enregistre sa fiche. Si son nom change dans le CMS
           avant cela, la fiche suit.

         — c'est une proposition, pas une serrure. Vider une case et enregistrer
           enregistre bien du vide ; la fiche rouverte reproposera simplement la
           valeur du compte, comme au premier jour. Une case qu'on veut vraiment
           différente se remplit autrement, elle ne se laisse pas vide.
       --------------------------------------------------------------------- */

    /** Case de la fiche => colonne du compte qui sait déjà y répondre. */
    public const PRE_REMPLI = [
        'full_name' => 'name',
        'email'     => 'email',
        'phone'     => 'mobile',
    ];

    /* ---------------------------------------------------------------------
       Ce que le bureau sait déjà de la personne.             [V31-FICHE-DEJA]

       Le compte ne connaît que trois choses. Le bureau, lui, en connaît vingt :
       adresse, date de naissance, nationalité, numéro AVS, IBAN — c'est écrit
       depuis des années dans le tableur de l'administration. Redemander tout
       cela à soixante-dix-sept personnes, c'est soixante-dix-sept fiches qui
       n'arriveront jamais, et autant d'occasions de recopier un IBAN de
       travers.

       Ces réponses-là sont donc déposées d'avance, dans une colonne à part :
       « prefill ». Elle se comporte exactement comme les trois cases du
       compte, une case de plus dans la même mécanique, et les trois règles
       ci-dessus valent mot pour mot : seule une case VIDE est proposée
       remplie, rien n'est écrit dans la base tant que la personne n'a pas
       enregistré, et tout se corrige en enregistrant par-dessus.

       Deux raisons de garder cela à part de « data », plutôt que d'écrire
       directement dans les réponses :

         — « data » est la parole de la personne. Y verser le tableur du bureau
           ferait croire à soixante-dix-sept fiches remplies, alors qu'aucune
           n'a encore été relue par celle qu'elle concerne. L'administration
           doit continuer de voir qui a répondu et qui n'a pas répondu.

         — un tableur se corrige. Tant que personne n'a enregistré sa fiche,
           réimporter une liste rectifiée profite à tout le monde d'un coup ;
           si les valeurs avaient été versées dans les réponses, il faudrait
           les rattraper une par une.

       Le compte passe avant la liste : si un nom est corrigé dans le CMS, la
       fiche suit le CMS, comme promis plus haut. La liste ne parle que des
       cases dont le compte ne sait rien.
       --------------------------------------------------------------------- */

    /** La colonne « prefill » existe-t-elle déjà ? (avant « Mettre à jour la base », non.) */
    private static ?bool $colPrefill = null;

    private static function colonnePrefill(): bool
    {
        if (self::$colPrefill === null) {
            try {
                self::$colPrefill = (bool)DB::one("SHOW COLUMNS FROM `member_profiles` LIKE 'prefill'");
            } catch (\Throwable $e) {
                self::$colPrefill = false;
            }
        }
        return self::$colPrefill;
    }

    /**
     * Dépose (ou remplace) les réponses connues d'avance pour une personne.
     * [V31-FICHE-DEJA]
     *
     * Un tableau vide efface la pré-saisie sans toucher à quoi que ce soit
     * d'autre : la fiche revient à ce qu'elle serait sans la liste.
     */
    public static function preremplir(int $collaboratorId, array $connu): void
    {
        if (!self::colonnePrefill()) return;

        $connu = array_filter($connu, static fn($v) => trim((string)$v) !== '');
        $json  = $connu ? json_encode($connu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        // [V38-CHIFFRE] Adresse, naissance, AVS, IBAN : chiffrés au repos.
        $json  = $json !== null ? Crypto::chiffrer($json) : null;

        if (DB::val('SELECT collaborator_id FROM member_profiles WHERE collaborator_id = ?', [$collaboratorId])) {
            DB::update('member_profiles', ['prefill' => $json], 'collaborator_id = ?', [$collaboratorId]);
        } else {
            DB::insert('member_profiles', ['collaborator_id' => $collaboratorId, 'prefill' => $json]);
        }
    }

    public static function get(int $collaboratorId): array
    {
        $r = DB::one('SELECT * FROM member_profiles WHERE collaborator_id = ?', [$collaboratorId]);
        // [V38-CHIFFRE] dechiffrer() rend aussi telles quelles les fiches
        // écrites avant le chiffrement (texte clair, sans préfixe) : rien à
        // migrer à la main, la prochaine sauvegarde les chiffre d'elle-même.
        $saisi = $r ? (json_decode(Crypto::dechiffrer($r['data'] ?? null), true) ?: []) : [];
        $prefillClair = $r && $r['prefill'] !== null ? Crypto::dechiffrer($r['prefill']) : null;
        return [
            'data' => self::prerempli($collaboratorId, $saisi, $prefillClair),
            /* [V30-FICHE-PRE] Ce que la personne a réellement écrit, sans les
               réponses recopiées du compte. L'administration s'en sert pour
               distinguer « fiche jamais ouverte » de « fiche commencée » : la
               pré-saisie est une commodité offerte au collaborateur, elle ne
               doit pas faire croire à un travail qu'il n'a pas encore fait. */
            'saisi' => $saisi,
            'bio' => $r['bio'] ?? '',
            'photo_image_id' => $r && $r['photo_image_id'] ? (int)$r['photo_image_id'] : null,
        ];
    }

    /**
     * Complète les cases restées vides avec ce qu'on sait déjà.
     * [V30-FICHE-PRE] [V31-FICHE-DEJA]
     *
     * D'abord le compte, ensuite la liste du bureau : l'ordre fait la règle.
     * Un nom corrigé dans le CMS l'emporte donc sur un nom hérité du tableur,
     * et la liste ne sert que là où le compte n'a rien à dire.
     */
    private static function prerempli(int $collaboratorId, array $data, ?string $prefill = null): array
    {
        $vide = static fn(array $d, string $case) => trim((string)($d[$case] ?? '')) === '';

        $manque = [];
        foreach (self::PRE_REMPLI as $case => $col) {
            if ($vide($data, $case)) $manque[$case] = $col;
        }

        if ($manque) {
            try {
                $c = DB::one('SELECT `name`, `email`, `mobile` FROM `collaborators` WHERE `id` = ?', [$collaboratorId]);
            } catch (\Throwable $e) { $c = null; }
            if ($c) {
                foreach ($manque as $case => $col) {
                    $v = trim((string)($c[$col] ?? ''));
                    if ($v !== '') $data[$case] = $v;
                }
            }
        }

        if ($prefill === null || $prefill === '') return $data;
        $connu = json_decode($prefill, true);
        if (!is_array($connu)) return $data;

        foreach ($connu as $case => $v) {
            if (!is_string($case) || is_array($v)) continue;
            $v = trim((string)$v);
            if ($v !== '' && $vide($data, $case)) $data[$case] = $v;
        }
        return $data;
    }

    /**
     * Enregistre une fiche remplie depuis le bureau.        [V37-FICHE-BUREAU]
     *
     * Anna ne détient le mot de passe de personne : « Voir son espace » est
     * son seul chemin vers la fiche, et la fiche y était éteinte. Elle ne
     * pouvait donc écrire nulle part, au moment précis où elle vient de verser
     * le tableau de soixante-dix-sept personnes. On rouvre l'écriture, mais on
     * ne peut pas la laisser tomber en vrac dans « data » : deux choses
     * doivent survivre au geste — la parole de la personne, et le signal qui
     * dit à l'administration qui a répondu et qui n'a pas répondu.
     *
     * D'où le partage, case par case, selon ce que la personne avait déjà
     * répondu — et non selon ce que le bureau vient de taper :
     *
     *   — case restée sans réponse : la valeur rejoint « prefill », là où sont
     *     déjà les renseignements du tableur. Elle est proposée à la personne,
     *     qui reste libre de la corriger, et la fiche continue de compter pour
     *     non commencée. Le bureau n'a pas parlé à sa place.
     *
     *   — case déjà répondue : la valeur va dans « data », par-dessus la
     *     réponse. C'est le seul endroit qui puisse l'emporter — déposée
     *     ailleurs, la correction du bureau serait avalée sans un mot, une
     *     pré-saisie ne remplissant qu'une case vide. Le signal ne bouge pas
     *     pour autant : une réponse corrigée reste une réponse.
     *
     *   — case sans réponse, mais que le compte remplit déjà (le nom, l'adresse
     *     e-mail, le téléphone) : la valeur va au compte. C'est le seul endroit
     *     qui puisse l'emporter, puisque prerempli() sert le compte avant la
     *     pré-saisie ; rangée en pré-saisie, la correction serait exacte et
     *     invisible, ce qui est la pire des deux façons de se tromper. Corriger
     *     un nom depuis la fiche revient donc à le corriger dans le CMS —
     *     c'est le même renseignement, au même endroit.
     *
     * Une case déposée que le bureau vide s'en va ; une réponse que le bureau
     * vide redevient une case vide, que la pré-saisie reproposera — c'est déjà
     * ce qui arrive quand la personne elle-même efface une case. Une case du
     * compte que le bureau vide ne vide pas le compte : on n'efface pas le nom
     * de quelqu'un par une case laissée blanche.
     *
     * La bio et la photo n'entrent dans aucun de ces comptes : elles ne servent
     * pas à distinguer une fiche commencée d'une fiche vierge, et sont
     * enregistrées telles quelles.
     *
     * @return array<int, string> Les cases refusées, par leur nom. Vide si tout
     *                            est passé. Seule l'adresse e-mail peut l'être :
     *                            elle sert à se connecter et ne peut pas être
     *                            celle de quelqu'un d'autre.
     */
    public static function saveBureau(int $collaboratorId, array $data, string $bio, ?int $photoId): array
    {
        $refus = [];
        $r     = DB::one('SELECT * FROM member_profiles WHERE collaborator_id = ?', [$collaboratorId]);
        // [V38-CHIFFRE]
        $saisi = $r ? (json_decode(Crypto::dechiffrer($r['data'] ?? null), true) ?: []) : [];
        $connu = [];
        if (self::colonnePrefill()) {
            $d = json_decode(Crypto::dechiffrer($r['prefill'] ?? null), true);
            if (is_array($d)) $connu = $d;
        }

        try { $compte = DB::one('SELECT `name`, `email`, `mobile` FROM `collaborators` WHERE `id` = ?', [$collaboratorId]); }
        catch (\Throwable $e) { $compte = null; }
        $majCompte = [];

        foreach ($data as $case => $v) {
            if (!is_string($case) || is_array($v)) continue;
            $v = trim((string)$v);

            if (trim((string)($saisi[$case] ?? '')) !== '') { $saisi[$case] = $v; continue; }

            $col = self::PRE_REMPLI[$case] ?? null;
            if ($col !== null && $compte !== null && trim((string)($compte[$col] ?? '')) !== '') {
                if ($v !== '' && $v !== trim((string)$compte[$col])) $majCompte[$col] = $v;
                continue;
            }

            if ($v !== '') $connu[$case] = $v;
            else           unset($connu[$case]);
        }

        /* L'adresse e-mail est aussi l'identifiant de connexion : deux personnes
           ne peuvent pas partager la même. On vérifie avant d'écrire, pour que
           le refus soit un message et non une erreur de base de données. */
        if (isset($majCompte['email'])) {
            $pris = DB::val('SELECT `id` FROM `collaborators` WHERE `email` = ? AND `id` <> ?',
                            [$majCompte['email'], $collaboratorId]);
            if ($pris) { unset($majCompte['email']); $refus[] = 'email'; }
        }

        self::save($collaboratorId, $saisi, $bio, $photoId);
        self::preremplir($collaboratorId, $connu);
        if ($majCompte) DB::update('collaborators', $majCompte, 'id = ?', [$collaboratorId]);

        return $refus;
    }

    public static function save(int $collaboratorId, array $data, string $bio, ?int $photoId): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // [V38-CHIFFRE] IBAN, AVS, adresse, naissance : chiffrés au repos.
        $row = ['data' => Crypto::chiffrer((string)$json), 'bio' => mb_substr($bio, 0, self::bioMax()), 'photo_image_id' => $photoId ?: null];
        if (DB::val('SELECT collaborator_id FROM member_profiles WHERE collaborator_id = ?', [$collaboratorId])) {
            DB::update('member_profiles', $row, 'collaborator_id = ?', [$collaboratorId]);
        } else {
            DB::insert('member_profiles', array_merge(['collaborator_id' => $collaboratorId], $row));
        }
    }
}

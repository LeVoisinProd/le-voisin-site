<?php
/**
 * Traitement des formulaires publics : validation, envoi email(s), copie BEXIO.
 *
 * Version V18-DIRECTION (01.08.2026) :
 *  - source « assoc_artists » : chaque option nomme désormais l'association
 *    ET sa direction artistique, « Encontro / Louis Matute », au lieu de les
 *    proposer comme deux choix sans rapport l'un avec l'autre.
 *  - correspond() : une réponse enregistrée sous l'ancienne forme — le nom de
 *    l'association seule, ou celui de la personne seule — reste reconnue.
 *
 * Version V17-CHOIX (01.08.2026) :
 *  - optionsHtml() : un menu déroulant retrouve la réponse déjà donnée, même
 *    si elle l'a été dans l'autre langue. Une fiche remplie en français
 *    s'ouvre remplie en anglais, et réciproquement.
 *  - source « assoc_artists » : les associations et les directions
 *    artistiques d'aujourd'hui, et elles seules.
 *
 * Version V7-ENVOI (21.07.2026) :
 *  - chaque association reçoit ses propres justificatifs, dans sa propre boîte
 *    de dépôt comptable. L'adresse est lue dans le réglage « Associations »,
 *    écrit en lignes « Nom | adresse » — la syntaxe de l'ancien formulaire.
 *  - le menu déroulant n'affiche que le nom : l'adresse ne figure jamais dans
 *    la page publique.
 *  - quand une association n'a pas d'adresse, le journal le dit et l'envoi
 *    part quand même à l'adresse de secours : rien ne se perd en silence.
 *
 * Version V4-RENVOI (29.07.2026) :
 *  - remember() / recall() / forget() : les champs qui ne changent pas d'un
 *    justificatif au suivant sont gardés en session pour le bouton
 *    « Envoyer un autre justificatif ». Une dépense = un envoi, sans corvée.
 *
 * Version V2-FORMULAIRES (28.07.2026) :
 *  - plus jamais de page blanche : toute erreur est attrapée et journalisée ;
 *  - plus de dépendance à fileinfo ni à la classe Docs pour les pièces jointes ;
 *  - refus explicite si aucune adresse de destination n'est configurée ;
 *  - captures d'écran PNG acceptées comme justificatifs.
 */
class Forms
{
    public const FILE_MAX = 5 * 1024 * 1024; // 5 Mo

    private static ?array $defs = null;

    public static function def(string $form): ?array
    {
        self::$defs ??= require LV_APP . '/config/forms.php';
        return self::$defs[$form] ?? null;
    }

    public static function label(array $node, ?string $lang = null): string
    {
        $lang ??= I18n::$lang;
        return $node[$lang] ?? $node[I18n::$default] ?? '';
    }

    // -----------------------------------------------------------------
    // « Envoyer un autre justificatif »
    //
    // Une dépense = un envoi : c'est la seule façon d'avoir un montant, une
    // devise et une catégorie qui décrivent vraiment le document joint. Mais
    // retaper son IBAN et son adresse à chaque quittance est décourageant, et
    // c'est précisément ce qui pousse les gens à tout entasser dans un seul
    // PDF. On garde donc en session ce qui ne change pas d'un envoi à l'autre.
    //
    // Prudence : ces valeurs contiennent un IBAN. Elles restent sur le
    // serveur, rattachées à la seule session du navigateur, et expirent au
    // bout de deux heures. La personne peut aussi les effacer d'un clic.
    // -----------------------------------------------------------------

    private const REPEAT_TTL = 7200;   // deux heures

    /**
     * Ouvre la session si elle ne l'est pas déjà.
     *
     * Indispensable : sur une page publique, la session n'est démarrée que par
     * Auth::csrf(), c'est-à-dire au moment où le formulaire est dessiné. Or on
     * a besoin de lire la session AVANT, dans le contrôleur, pour savoir s'il
     * faut proposer « Envoyer un autre justificatif ». Sans cet appel, la
     * mémoire paraîtrait toujours vide et le bouton ne s'afficherait jamais.
     */
    private static function session(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) return true;
        if (headers_sent()) return false;   // trop tard pour poser le cookie
        session_boot();
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /** Après un envoi réussi : mémoriser les champs réutilisables. */
    public static function remember(string $form, array $post): void
    {
        $champs = self::def($form)['repeat'] ?? [];
        if (!$champs || !self::session()) return;

        $garde = [];
        foreach ($champs as $c) {
            $v = trim((string)($post[$c] ?? ''));
            if ($v !== '') $garde[$c] = $v;
        }
        if ($garde) $_SESSION['lv_form_repeat'][$form] = ['t' => time(), 'v' => $garde];
    }

    /** Les champs mémorisés, ou [] s'il n'y en a pas / s'ils ont expiré. */
    public static function recall(string $form): array
    {
        if (!self::session()) return [];
        $e = $_SESSION['lv_form_repeat'][$form] ?? null;
        if (!is_array($e) || !isset($e['t'], $e['v'])) return [];
        if (time() - (int)$e['t'] > self::REPEAT_TTL) { self::forget($form); return []; }
        return (array)$e['v'];
    }

    public static function forget(string $form): void
    {
        if (!self::session()) return;
        unset($_SESSION['lv_form_repeat'][$form]);
    }

    /** Options d'un champ select (statiques ou dynamiques). */
    public static function options(array $field): array
    {
        if (($field['source'] ?? '') === 'artists') {
            $out = [];
            foreach (DB::all('SELECT name FROM artists WHERE visible = 1 ORDER BY sort, id') as $a) {
                $out[] = ['en' => $a['name'], 'fr' => $a['name']];
            }
            $out[] = ['en' => 'Other', 'fr' => 'Autre'];
            return $out;
        }
        // Les associations sont écrites « Nom | adresse comptable ». Seul le nom
        // est proposé dans le menu déroulant : l'adresse de dépôt n'a rien à
        // faire dans une page publique, et c'est le nom seul qui doit revenir
        // dans l'email et dans le contrôle de validité.
        if (($field['source'] ?? '') === 'assoc') {
            $out = [];
            foreach (array_keys(Settings::pairs('form_assoc_options')) as $nom) {
                $out[] = ['en' => $nom, 'fr' => $nom];
            }
            return $out;
        }

        // « Pour travailler avec » : une association et sa direction
        // artistique forment UNE seule option, « Encontro / Louis Matute ».
        //
        // La version précédente proposait les associations, puis les artistes,
        // dans la même liste : on lisait « Encontro » et « Louis Matute » comme
        // deux choix sans rapport, et rien ne disait que c'était la même
        // équipe. Le lien vit maintenant dans les réglages, en troisième
        // colonne de la liste des associations, à côté de la boîte de dépôt.
        // Il se corrige donc là où on corrige déjà le reste, sans toucher au
        // code, et la liste reste dans l'ordre voulu.
        //
        // Une association sans direction inscrite garde son nom seul : la
        // colonne est facultative, une ligne incomplète n'est jamais perdue.
        // C'est aussi pour cela que la liste des artistes n'alimente plus ce
        // menu : elle mélangeait les artistes que le bureau représente et les
        // personnes qui portent une association, deux choses différentes.
        //   [V18-DIRECTION]
        if (($field['source'] ?? '') === 'assoc_artists') {
            $out = [];
            $vus = [];
            foreach (Settings::trios('form_assoc_options') as $nom => $bouts) {
                $nom       = trim((string)$nom);
                $direction = trim($bouts['direction']);
                if ($nom === '') continue;
                $libelle = $direction === '' ? $nom : $nom . ' / ' . $direction;
                $cle     = mb_strtolower($libelle);
                if (isset($vus[$cle])) continue;          // pas deux fois la même ligne
                $vus[$cle] = true;
                $out[] = ['en' => $libelle, 'fr' => $libelle];
            }
            $out[] = ['en' => 'Other', 'fr' => 'Autre'];
            return $out;
        }

        return $field['options'] ?? [];
    }

    /**
     * Cette réponse enregistrée désigne-t-elle cette option ?
     *
     * Le cas simple est l'égalité avec l'un des libellés de l'option, quelle
     * que soit la langue : c'est ainsi qu'une fiche remplie en français
     * s'ouvre remplie en anglais.
     *
     * Le cas moins simple est celui d'un libellé qui a changé de forme. « Pour
     * travailler avec » proposait « Encontro », puis « Louis Matute », et
     * propose aujourd'hui « Encontro / Louis Matute ». Les fiches déjà
     * remplies portent l'ancienne réponse ; les faire correspondre à la
     * nouvelle option évite de les rouvrir à moitié vides. On compare donc
     * aussi la réponse à chaque moitié du libellé, de part et d'autre de la
     * barre oblique.
     *
     * La comparaison reste stricte à l'intérieur d'une moitié : « Encontro »
     * retrouve « Encontro / Louis Matute », mais une réponse approchante ne
     * retrouve rien. On préfère afficher une réponse inconnue telle quelle
     * plutôt que de la ranger dans la mauvaise case.   [V18-DIRECTION]
     */
    public static function correspond(array $opt, string $reponse): bool
    {
        $reponse = trim($reponse);
        if ($reponse === '') return false;

        foreach ($opt as $libelle) {
            $libelle = trim((string)$libelle);
            if ($libelle === '') continue;
            if ($libelle === $reponse) return true;
            if (strpos($libelle, '/') === false) continue;
            foreach (explode('/', $libelle) as $moitie) {
                if (trim($moitie) === $reponse) return true;
            }
        }
        return false;
    }

    /**
     * Le contenu d'un menu déroulant, la réponse déjà donnée étant cochée.
     *
     * Un menu déroulant enregistre le libellé tel qu'il était affiché, donc
     * dans la langue de la personne au moment où elle a répondu. Comparer
     * bêtement la réponse au libellé de la langue courante — ce que faisait
     * l'ancien code — donnait un menu vide dès qu'on changeait de langue : la
     * fiche paraissait à moitié remplie alors que tout y était. On cherche
     * donc la réponse dans TOUTES les langues de chaque option, exactement
     * comme le fait la fiche imprimée, et on affiche l'option correspondante
     * dans la langue demandée. Répondre une fois suffit désormais pour les
     * deux langues.
     *
     * Deux précautions :
     *  - la première option qui correspond gagne, pour qu'un même choix ne
     *    puisse jamais être coché deux fois ;
     *  - une réponse enregistrée qui ne figure plus dans la liste — une
     *    association renommée, un artiste passé en ancienne collaboration —
     *    est ajoutée en fin de liste et reste cochée. Sans cela, ouvrir sa
     *    fiche puis l'enregistrer effacerait en silence une réponse que
     *    personne n'a voulu changer.
     *
     * @param string $reponse ce qui est enregistré (vide si rien)
     */
    public static function optionsHtml(array $field, string $reponse, ?string $lang = null): string
    {
        $lang ??= I18n::$lang;
        $reponse = trim($reponse);
        $html = '';
        $trouve = false;

        foreach (self::options($field) as $opt) {
            $libelle = self::label($opt, $lang);
            if ($libelle === '') continue;
            $coche = !$trouve && self::correspond($opt, $reponse);
            if ($coche) $trouve = true;
            $html .= '<option value="' . e($libelle) . '"' . ($coche ? ' selected' : '') . '>'
                   . e($libelle) . "</option>\n";
        }

        if ($reponse !== '' && !$trouve) {
            $html .= '<option value="' . e($reponse) . '" selected>' . e($reponse) . "</option>\n";
        }
        return $html;
    }

    /**
     * Où doit partir la copie comptable, et ce qu'il faut en dire au journal.
     *
     * La règle est celle de l'ancien formulaire : chaque association reçoit
     * ses propres justificatifs, dans sa propre boîte de dépôt. L'adresse
     * générale de secours ne sert que si l'association choisie n'en a pas
     * encore — plutôt que de laisser l'envoi se perdre pendant qu'on complète
     * la liste.
     *
     * Chaque cas de repli est écrit dans le journal en nommant l'association :
     * une adresse oubliée dans les réglages doit se voir, pas se deviner.
     *
     * @return array{0: string[], 1: string} [destinataires, ligne de journal]
     */
    private static function accounting(array $def, array $values): array
    {
        $secours = Settings::emails($def['bexio_key']);
        $repli   = $secours
            ? 'copie envoyée à l\'adresse comptable de secours.'
            : 'aucune copie comptable n\'a pu être envoyée.';

        $nom = trim((string)($values[$def['assoc_key'] ?? 'association'] ?? ''));
        if ($nom === '') return [$secours, ''];

        $liste = Settings::pairs('form_assoc_options');

        if (!array_key_exists($nom, $liste)) {
            return [$secours, "L'association « $nom » ne figure pas dans la liste des réglages — $repli"];
        }

        $adresse = $liste[$nom];

        if ($adresse === '') {
            return [$secours, "Aucune adresse comptable pour « $nom » "
                . "(Administration > Réglages > Formulaires) — $repli"];
        }
        if (!filter_var($adresse, FILTER_VALIDATE_EMAIL)) {
            return [$secours, "L'adresse comptable de « $nom » est mal écrite : « $adresse » "
                . "(Administration > Réglages > Formulaires) — $repli"];
        }

        return [[$adresse], ''];
    }

    private static function condMet(array $field, array $post): bool
    {
        if (empty($field['show_if'])) return true;
        [$dep, $values] = $field['show_if'];
        return in_array((string)($post[$dep] ?? ''), (array)$values, true);
    }

    /** Normalise $_FILES[key] (simple ou multiple []) en liste de fichiers présents. */
    private static function normalizeFiles($f): array
    {
        if (!is_array($f) || !isset($f['name'])) return [];
        $out = [];
        if (is_array($f['name'])) {                    // champ multiple : name[], tmp_name[]…
            foreach ($f['name'] as $i => $n) {
                if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                $out[] = ['name' => $n, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
            }
        } elseif (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $out[] = $f;
        }
        return $out;
    }

    /** Nettoie un nom de personne pour en faire un nom de fichier : « Jean Testeur » → « Jean_Testeur ». */
    private static function cleanName(string $s): string
    {
        $s = trim(preg_replace('/\s+/', '_', trim($s)));
        $s = preg_replace('/[^\p{L}\p{N}_\-]+/u', '', $s);
        return mb_substr($s, 0, 80);
    }

    /** Un segment de nomenclature (montant, devise, catégorie…) : sans espaces, sûr pour un nom de fichier. */
    private static function filePart(string $s): string
    {
        $s = preg_replace('/\s+/', '', trim($s));               // « Achats nourriture » → « Achatsnourriture »
        $s = preg_replace('/[^\p{L}\p{N}.,€£$-]+/u', '', $s);   // garde lettres, chiffres, . , et symboles monétaires
        return mb_substr($s, 0, 40);
    }

    /**
     * Écrit une ligne dans app/logs/mail.log.
     *
     * Le dossier des journaux est créé au besoin : sur un site fraîchement
     * installé il peut manquer, et sans lui aucune trace n'est conservée —
     * exactement au moment où on en a le plus besoin.
     */
    private static function journal(string $ligne): void
    {
        $dir = LV_APP . '/logs';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/mail.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $ligne . "\n", FILE_APPEND);
    }

    /** Taille lisible, sans dépendre d'une autre classe du CMS. */
    private static function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' Mo';
        if ($bytes >= 1024) return round($bytes / 1024) . ' Ko';
        return $bytes . ' o';
    }

    /**
     * Type réel d'un fichier, quelle que soit la configuration du serveur.
     *
     * mime_content_type() n'existe que si l'extension « fileinfo » est active.
     * Quand elle manque, l'ancien code provoquait une erreur fatale — donc une
     * page blanche au moment de l'envoi. On essaie ici les deux méthodes, et si
     * aucune n'est disponible on renvoie null : le contrôle de l'extension du
     * fichier, lui, s'applique toujours.
     */
    private static function detectMime(string $path): ?string
    {
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($path);
            if (is_string($m) && $m !== '') return $m;
        }
        if (function_exists('finfo_open')) {
            $fi = @finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $m = @finfo_file($fi, $path);
                @finfo_close($fi);
                if (is_string($m) && $m !== '') return $m;
            }
        }
        return null;
    }

    /**
     * Traite un envoi. Retourne ['ok' => bool, 'errors' => [champ => message]].
     *
     * Filet de sécurité : quoi qu'il arrive en dessous, le visiteur ne doit
     * jamais tomber sur une page blanche ou une erreur 500 après avoir rempli
     * un long formulaire. On attrape donc toute erreur, on l'écrit en clair
     * dans le journal avec le fichier et la ligne fautifs, et on affiche un
     * message lisible.
     */
    public static function handle(string $form, array $post, array $files): array
    {
        try {
            return self::process($form, $post, $files);
        } catch (\Throwable $e) {
            self::journal("ERREUR sur $form : " . $e->getMessage()
                . ' — ' . basename($e->getFile()) . ' ligne ' . $e->getLine());
            return ['ok' => false, 'errors' => ['_' => t('form_send_failed')]];
        }
    }

    private static function process(string $form, array $post, array $files): array
    {
        $def = self::def($form);
        if (!$def) return ['ok' => false, 'errors' => ['_' => 'Formulaire inconnu.']];

        /* ==================================================================
           ANTI-SPAM. Trois barrières, et la troisième est nouvelle.
                                                            [13.08.2026]
           Ces formulaires sont ouverts à tous et font envoyer du courrier par
           le site. Jusqu'à aujourd'hui ce courrier partait de talkto@ ; depuis
           ce matin il part de la boîte Google d'Anna, avec son SPF et sa
           signature. Ce qui n'était qu'une nuisance est devenu sa réputation
           d'expéditrice et son compte : un robot qui trouve ce formulaire ne
           salit plus une adresse de service, il salit celle par laquelle
           partent les fiches de salaire et les dossiers de subvention.
           ================================================================== */

        // 1. Le champ piège : rempli, c'est un robot. On répond « merci » et
        //    l'on n'envoie rien — il ne doit pas apprendre qu'il a été vu.
        if (trim((string)($post['website'] ?? '')) !== '') return ['ok' => true, 'errors' => []];

        // 2. Le délai minimal, désormais OBLIGATOIRE. Il ne s'appliquait que
        //    « si le champ est présent » : ne pas l'envoyer suffisait à le
        //    sauter, ce qui est exactement ce que fait un script.
        $t = (int)($post['_t'] ?? 0);
        if ($t <= 0 || time() - $t < 3) return ['ok' => false, 'errors' => ['_' => t('form_error_generic')]];

        if (!Auth::csrfOk()) return ['ok' => false, 'errors' => ['_' => t('form_error_generic')]];

        /* 3. Le nombre. Rien ne limitait combien de fois une même adresse IP
              pouvait faire partir un message : le compteur existait dans la
              maison, branché sur la connexion de l'administration, et pas ici.
              Il compte sous « f: », à part des autres guichets, pour qu'un
              robot sur les formulaires ne ferme pas la porte de l'espace.
              Le compte est fait AVANT le travail, et l'essai est noté même
              lorsqu'il est refusé : sinon réessayer serait gratuit. */
        if (MemberAuth::throttled('f:')) {
            return ['ok' => false, 'errors' => ['_' => t('form_error_trop')]];
        }
        MemberAuth::noter('f:');

        $errors = [];
        $values = [];
        $attachments = [];

        foreach ($def['fields'] as $field) {
            $key = $field['key'];
            $type = $field['type'];
            if ($type === 'section') continue;
            $required = !empty($field['required']) && self::condMet($field, $post);

            if ($type === 'file') {
                $items = self::normalizeFiles($files[$key] ?? null);
                if (!$items) {
                    if ($required) $errors[$key] = t('form_required');
                    continue;
                }
                // Le champ n'accepte qu'un seul fichier : on le dit franchement
                // plutôt que de garder le premier en silence. Sinon la personne
                // repart convaincue d'avoir tout transmis, alors que le reste a
                // été jeté sans que personne ne s'en aperçoive.
                if (empty($field['multiple']) && count($items) > 1) {
                    $errors[$key] = t('form_file_single');
                    continue;
                }
                $allowed = array_map(fn($x) => ltrim(trim($x), '.'), explode(',', $field['accept'] ?? '.pdf'));
                $names = [];
                $i = 0;
                foreach ($items as $f) {
                    $i++;
                    if ($f['error'] !== UPLOAD_ERR_OK) { $errors[$key] = t('form_file_error'); break; }
                    if ($f['size'] > self::FILE_MAX) { $errors[$key] = t('form_file_too_big'); break; }
                    $ext = mb_strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed, true)) { $errors[$key] = t('form_file_type'); break; }
                    $mime = self::detectMime((string)$f['tmp_name']);
                    if ($mime !== null && !str_starts_with($mime, 'image/') && $mime !== 'application/pdf') {
                        $errors[$key] = t('form_file_type'); break;
                    }

                    // Nom du fichier joint (renommage automatique éventuel)
                    if (!empty($field['rename']['template'])) {
                        // Nomenclature construite depuis plusieurs champs (ex. factures)
                        $parts = [];
                        foreach ($field['rename']['template'] as $fk) {
                            $p = self::filePart((string)($post[$fk] ?? ''));
                            if ($p !== '') $parts[] = $p;
                        }
                        $base = implode('_', $parts) ?: 'justificatif';
                        $name = $base . (count($items) > 1 ? '_' . $i : '') . '.' . $ext;
                    } elseif (!empty($field['rename']['from'])) {
                        // Renommage depuis un seul champ + suffixe (ex. passeport)
                        $base = self::cleanName((string)($post[$field['rename']['from']] ?? '')) ?: 'document';
                        $suffix = $field['rename']['suffix'] ?? '';
                        $name = $base . ($suffix ? '_' . $suffix : '') . (count($items) > 1 ? '_' . $i : '') . '.' . $ext;
                    } else {
                        $name = preg_replace('/[^A-Za-z0-9._\- ]+/', '-', pathinfo((string)$f['name'], PATHINFO_FILENAME)) . '.' . $ext;
                    }
                    $attachments[] = ['path' => $f['tmp_name'], 'name' => $name];
                    $names[] = $name . ' (' . self::humanSize((int)$f['size']) . ')';
                }
                $values[$key] = implode("\n", $names);
                continue;
            }

            $v = trim((string)($post[$key] ?? ''));
            if ($required && $v === '') { $errors[$key] = t('form_required'); continue; }
            if ($v === '') { $values[$key] = ''; continue; }

            switch ($type) {
                case 'email':
                    if (!filter_var($v, FILTER_VALIDATE_EMAIL)) $errors[$key] = t('form_email_invalid');
                    break;
                case 'number':
                    $v = str_replace([',', ' '], ['.', ''], $v);
                    if (!is_numeric($v)) $errors[$key] = t('form_number_invalid');
                    break;
                /* [V16-DATES] La date est saisie jour d'abord (31.07.2026) ; on
                   la range à l'anglaise pour la base et les envois, mais on la
                   réaffichera toujours jour d'abord. */
                case 'date':
                    $iso = Dates::versIso($v);
                    if ($iso === null) $errors[$key] = t('form_date_invalid');
                    else $v = $iso;
                    break;
                case 'select':
                    $opts = array_merge(...array_map(fn($o) => array_values($o), self::options($field))) ?: [];
                    if (!in_array($v, $opts, true)) $errors[$key] = t('form_error_generic');
                    break;
                case 'yesno':
                    if (!in_array($v, ['yes', 'no'], true)) $errors[$key] = t('form_error_generic');
                    break;
            }
            $values[$key] = mb_substr($v, 0, 4000);
        }

        if ($errors) return ['ok' => false, 'errors' => $errors];

        // ---- Email principal ----
        $rows = '';
        foreach ($def['fields'] as $field) {
            if ($field['type'] === 'section') {
                $rows .= '<tr><td colspan="2" style="padding:14px 8px 4px;font-weight:bold;text-transform:uppercase;font-size:12px;letter-spacing:.08em;border-bottom:1px solid #ddd;">'
                       . e(self::label($field['label'], 'fr')) . '</td></tr>';
                continue;
            }
            $key = $field['key'];
            $val = $values[$key] ?? '';
            if ($val === '') continue;
            if ($field['type'] === 'yesno') $val = $val === 'yes' ? 'Oui' : 'Non';
            // [V16-DATES] Le courriel se lit comme la fiche : le jour d'abord.
            if ($field['type'] === 'date') $val = Dates::afficher($val) ?: $val;
            $rows .= '<tr><td style="padding:6px 8px;color:#555;vertical-align:top;width:45%;">' . e(self::label($field['label'], 'fr'))
                   . '</td><td style="padding:6px 8px;font-weight:600;">' . nl2br(e($val)) . '</td></tr>';
        }
        $rows .= '<tr><td style="padding:6px 8px;color:#555;">Langue du site</td><td style="padding:6px 8px;">' . e(mb_strtoupper(I18n::$lang)) . '</td></tr>';

        $title = self::label($def['name'], 'fr');
        $subject = '[' . setting('site_name', 'Le Voisin') . '] ' . $def['subject'];
        if (!empty($values['full_name'])) $subject .= ' — ' . $values['full_name'];
        if ($form === 'form_expenses' && !empty($values['amount'])) {
            $subject .= ' — ' . $values['amount'] . ' ' . ($values['currency'] ?? '');
        }
        $html = Mailer::wrap($title, '<table style="width:100%;border-collapse:collapse;font-size:14px;">' . $rows . '</table>');

        $to = Settings::emails($def['to_key']);

        // Aucune adresse de destination enregistrée dans les réglages du site.
        //
        // Il ne faut surtout pas répondre « envoi réussi » : la personne croirait
        // ses justificatifs transmis, la comptabilité ne recevrait jamais rien, et
        // personne ne s'en apercevrait — ni l'expéditeur, ni le bureau. On refuse
        // donc l'envoi, on l'écrit dans le journal, et on affiche un message qui
        // dit clairement que le problème vient du site et pas du formulaire rempli.
        if (!$to) {
            self::journal("REFUS : aucun destinataire configuré pour $form "
                . '(Administration > Réglages > Formulaires)');
            return ['ok' => false, 'errors' => ['_' => t('form_no_recipient')]];
        }

        $ok = Mailer::send($to, $subject, $html, $attachments, $values['email'] ?? null);

        // ---- Copie comptable : dans la boîte de l'association concernée ----
        //
        // Une association = une boîte de dépôt. Le justificatif doit arriver
        // directement dans celle de l'association choisie ; sans cela il
        // faudrait, chaque mois, retrier à la main un tas commun.
        if (!empty($def['bexio_key'])) {
            [$compta, $note] = self::accounting($def, $values);
            if ($note !== '') self::journal("$form : $note");
            if ($compta) {
                Mailer::send($compta, $subject, $html, $attachments, $values['email'] ?? null);
            }
        }

        // ---- Copie de confirmation à la personne ----
        if (!empty($def['confirm']) && !empty($values['email']) && filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $isFr = I18n::$lang === 'fr';
            $intro = $isFr
                ? '<p>Bonjour,</p><p>Nous avons bien reçu votre envoi « ' . e($title) . ' ». En voici une copie pour vos archives. Notre équipe revient vers vous si nécessaire.</p>'
                : '<p>Hello,</p><p>We have received your submission "' . e($title) . '". Here is a copy for your records. Our team will get back to you if needed.</p>';
            $confHtml = Mailer::wrap($isFr ? 'Confirmation de votre envoi' : 'Submission confirmation',
                $intro . '<table style="width:100%;border-collapse:collapse;font-size:14px;">' . $rows . '</table>');
            $confSubject = ($isFr ? 'Confirmation — ' : 'Confirmation — ') . $title . ' — ' . setting('site_name', 'Le Voisin');
            Mailer::send([$values['email']], $confSubject, $confHtml, $attachments, setting('contact_email', '') ?: null);
        }

        // ---- Copie de sécurité optionnelle ----
        //
        // Cette copie en base est un confort, pas l'objet du formulaire. Si la
        // table « submissions » manque ou que la base refuse l'écriture, ce
        // serait absurde d'annoncer un échec à quelqu'un dont les documents
        // sont déjà partis par email — il renverrait tout une deuxième fois.
        // On note donc le problème dans le journal et on continue.
        if (setting('keep_submissions', '0') === '1') {
            try {
                DB::insert('submissions', ['form' => $form, 'data' => json_encode($values, JSON_UNESCAPED_UNICODE)]);
            } catch (\Throwable $e) {
                self::journal("Copie en base impossible pour $form (emails bien partis) : " . $e->getMessage());
            }
        }

        return ['ok' => $ok, 'errors' => $ok ? [] : ['_' => t('form_send_failed')]];
    }
}

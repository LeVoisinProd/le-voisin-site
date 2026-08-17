<?php
/**
 * Documents privés des collaborateurs (contrats, fiches de salaire, logistique).
 * Stockés HORS accès public direct : dossier uploads/private protégé par .htaccess,
 * servis uniquement via un script qui vérifie l'identité et la propriété.
 * [V10-CMS-BILINGUE] — messages de téléversement traduits (clefs « sys_… »).
 */
class MemberDocs
{
    public const MAX_SIZE = 25 * 1024 * 1024;
    public const EXTS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    public const CATEGORIES = [
        // Volet « contractualisation »
        'contract'   => ['fr' => 'Contrats', 'en' => 'Contracts'],
        'payslip'    => ['fr' => 'Fiches de salaire', 'en' => 'Payslips'],
        /* [13.08.2026] L'attestation de gain intermédiaire arrivait sous
           « Autres documents », faute d'avoir sa ligne. C'est une pièce que la
           personne présente au chômage pour être indemnisée du manque à gagner :
           elle est réclamée à date, elle se cherche par mois, et la retrouver
           parmi les « autres » demandait d'ouvrir les PDF un par un.

           Elle se range à côté de la fiche de salaire, et pour la même raison :
           c'est l'employeur qui l'établit et la dépose, la personne ne fait que
           la télécharger. Le sigle reste dans le libellé — c'est sous ce nom-là
           qu'on la demande, jamais sous le nom complet. */
        'agi'        => ['fr' => 'Attestations de gain intermédiaire (AGI)',
                         'en' => 'Intermediate earnings certificates (AGI)'],
        /* [14.08.2026] L'attestation d'indépendant·e, sa ligne à elle, pour la
           raison qui a donné la sienne à l'AGI la veille : c'est une pièce qu'on
           vient chercher pour elle-même. Elle vaut une année civile, elle est
           réclamée à chaque exercice, et le bureau doit pouvoir répondre à « qui
           ne l'a pas encore envoyée » sans ouvrir soixante-dix-sept dossiers.

           Elle diffère de l'AGI sur un point qui compte : celle-ci, c'est LA
           PERSONNE qui la dépose, depuis sa fiche, en choisissant son statut. */
        'attestation'=> ['fr' => 'Attestations d\'indépendant·e',
                         'en' => 'Self-employed certificates'],
        'invoice'    => ['fr' => 'Factures', 'en' => 'Invoices'],        // [V36-FACTURES]
        /* [13.08.2026] Une facture et un justificatif de dépense ne sont pas la
           même chose, et tout arrivait sous « Factures ». Une facture, on la
           doit à quelqu'un ; un justificatif, on le rembourse. La comptabilité
           les traite différemment, et le bureau devait deviner en ouvrant le
           PDF. La personne qui dépose, elle, sait laquelle c'est. */
        'expense'    => ['fr' => 'Justificatifs de dépenses', 'en' => 'Expense receipts'],
        'identity'   => ['fr' => 'Pièces d\'identité', 'en' => 'Identity documents'],
        'other'      => ['fr' => 'Autres documents', 'en' => 'Other documents'],
        // Volet « projets »                                    [V33-ESPACE-3]
        'roadmap'    => ['fr' => 'Feuilles de route', 'en' => 'Roadmaps'],
        'travel'     => ['fr' => 'Billets de voyage', 'en' => 'Travel tickets'],
        'hotel'      => ['fr' => 'Réservations d\'hôtel', 'en' => 'Hotel bookings'],
        'perdiem'    => ['fr' => 'Reçus de per diem', 'en' => 'Per diem receipts'],
        'logistics'  => ['fr' => 'Documents logistiques', 'en' => 'Logistics documents'],
        'prod_other' => ['fr' => 'Autres documents de production', 'en' => 'Other production documents'],
    ];

    /* ---------------------------------------------------------------------
       Les deux volets de l'espace.                            [V33-ESPACE-3]

       Un document contractuel et une réservation d'hôtel ne se cherchent pas
       de la même façon. Le premier se cherche par employeur — « mon contrat
       chez Le Voisin CH » — et c'est l'association qui le range. La seconde se
       cherche par production — « mon voyage pour Dolce Vita » — et c'est le
       projet qui la range. Vouloir un seul classement pour les deux, c'est
       forcément en trahir un.

       La catégorie décide donc du volet, et le volet décide du reste : quel
       menu est proposé au dépôt, et sous quel titre le document apparaît dans
       l'espace. Il n'y a rien à choisir en plus, donc rien à oublier, et
       aucun état bancal du genre « rangé par projet mais sans projet ».

       Les catégories d'origine gardent leur nom court — contract, payslip,
       identity, other, logistics — pour que les documents déjà déposés
       restent exactement où ils sont. « logistics » rejoint les projets :
       c'est là qu'on ira chercher un ordre de mission.
       --------------------------------------------------------------------- */
    /* [12.08.2026] La facture quitte le volet contractuel pour le sien.

       Les deux volets mélangeaient deux sens opposés. Un contrat, une fiche de
       salaire, une pièce d'identité : le bureau les dépose, la personne les
       télécharge. Une facture : la personne la dépose, et elle en suit l'état.
       Rangées ensemble, il fallait lire tout le bloc d'un employeur pour
       retrouver la facture qu'on venait d'envoyer — et le circuit d'états
       « envoyée → payée → bien reçue » n'a de sens que pour elle.

       « perdiem » reste au volet projet : un reçu de per diem se rattache à
       une production, et c'est là qu'on le cherche. */
    public const VOLETS = [
        'contrat'  => ['contract', 'payslip', 'agi', 'attestation', 'identity', 'other'],
        'paiement' => ['invoice', 'expense'],
        'projet'   => ['roadmap', 'travel', 'hotel', 'perdiem', 'logistics', 'prod_other'],
    ];

    /* ---------------------------------------------------------------------
       L'exception de la facture.                              [V36-FACTURES]

       Un document contractuel n'a pas de projet : il appartient à un
       employeur, pas à une production. La facture est le seul cas où les deux
       sont vrais à la fois — on facture une association POUR une production,
       et quelqu'un qui travaille sur trois spectacles dans le même mois émet
       trois factures qu'il faut pouvoir distinguer.

       Le projet reste facultatif. Il est demandé « si la facture concerne un
       projet », et une facture qui n'en concerne aucun n'a rien à choisir.
       --------------------------------------------------------------------- */
    /* [13.08.2026] « expense » y entre : quelqu'un en tournée choisit son projet
       dans le formulaire de dépôt, et sans cette ligne ce choix était jeté en
       chemin. C'est exactement ce que la note du dépôt cherchait à éviter pour
       les factures. */
    public const AVEC_PROJET = ['invoice', 'expense'];

    /** Le volet d'une catégorie. Une catégorie inconnue reste contractuelle. */
    public static function volet(string $cat): string
    {
        foreach (self::VOLETS as $volet => $cats) {
            if (in_array($cat, $cats, true)) return $volet;
        }
        return 'contrat';
    }

    /** Les catégories d'un volet, dans l'ordre d'affichage. */
    /**
     * L'ancre de l'onglet qui montre ce volet.
     *
     * [13.08.2026] Écrite ici et plus dans `espace_ancre()`, parce que les
     * courriels de MemberNotify en ont besoin eux aussi et n'ont pas accès aux
     * fonctions de l'espace. Deux tables de correspondance auraient divergé.
     *
     * Elle corrige au passage une faute qui datait de la séparation en quatre
     * onglets : `paiement` produisait `#partie-paiement`, au singulier, alors
     * que la section s'appelle `partie-paiements`. Déposer une facture
     * renvoyait donc vers une ancre inexistante, et l'onglet ne s'ouvrait pas.
     */
    public static function ancre(string $volet): string
    {
        return '#partie-' . match ($volet) {
            'contrat'  => 'contrats',
            'paiement' => 'paiements',
            'projet'   => 'projets',
            default    => 'infos',
        };
    }

    public static function catsDuVolet(string $volet): array
    {
        return self::VOLETS[$volet] ?? [];
    }

    /**
     * Les projets qu'on peut CHOISIR : numéro => titre. Les projets actuels
     * seulement.
     *
     * [13.08.2026] Cette liste rendait tous les projets, anciens compris, et le
     * menu était devenu illisible : on déposait un billet de train en cherchant
     * une production en cours au milieu de tournées finies depuis des années.
     *
     * La note d'origine justifiait le contraire — « une tournée terminée garde
     * ses billets et ses reçus, la personne doit pouvoir les retrouver l'année
     * suivante ». Elle confondait deux choses. RETROUVER un document déjà rangé
     * sous un ancien projet ne demande rien à ce menu : le rangement est déjà
     * enregistré, et il continue de s'afficher. Ce menu ne sert qu'à CHOISIR,
     * et on ne classe pas un document neuf sous une tournée finie.
     *
     * D'où le second argument : il force un projet à figurer dans la liste même
     * s'il n'est plus actuel. Il sert au menu qui range un document DÉJÀ déposé,
     * pour que son rattachement d'hier reste visible et sélectionné. Sans lui,
     * ouvrir la fiche et toucher à autre chose détacherait le document de son
     * projet sans que personne ne le voie — c'est le même filet que celui du
     * menu « association », juste au-dessus.
     *
     * @param int|null $inclure projet à garder dans la liste même s'il est ancien
     */
    public static function projetChoix(?string $lang = null, ?int $inclure = null): array
    {
        $sql  = "SELECT id, title_en, title_fr FROM projects WHERE status = 'current'";
        $args = [];
        if ($inclure) { $sql .= ' OR id = ?'; $args[] = $inclure; }

        return self::titrer(DB::all($sql . ' ORDER BY sort, title_en', $args), $lang);
    }

    /**
     * TOUS les projets, anciens compris : numéro => titre.
     *
     * Sert à retrouver le nom d'un projet déjà rattaché à un document, sans
     * pour autant le proposer au choix. Lue une fois par page plutôt qu'une
     * fois par document : une fiche en porte plusieurs dizaines.
     */
    public static function projetTitres(?string $lang = null): array
    {
        return self::titrer(DB::all('SELECT id, title_en, title_fr FROM projects ORDER BY sort, title_en'), $lang);
    }

    /** Le titre d'un projet dans la langue demandée, avec repli sur l'autre. */
    private static function titrer(array $lignes, ?string $lang): array
    {
        $lang = $lang === 'en' ? 'en' : 'fr';
        $out  = [];
        foreach ($lignes as $p) {
            $t = trim((string)($lang === 'en' ? $p['title_en'] : $p['title_fr']));
            if ($t === '') $t = trim((string)($p['title_fr'] ?: $p['title_en']));
            $out[(int)$p['id']] = $t !== '' ? $t : ('#' . (int)$p['id']);
        }
        return $out;
    }

    /**
     * Rattache un document déjà déposé à un projet — ou l'en détache.
     *
     * Le fichier n'est pas renommé, contrairement au rangement par
     * association : la nomenclature du bureau met le sigle de l'employeur au
     * bout du nom, elle ne prévoit rien pour les projets. Inventer un suffixe
     * ici reviendrait à réécrire une convention qu'on ne nous a pas demandé
     * de changer.
     */
    public static function rangerProjet(int $id, ?int $projectId): void
    {
        if (!self::row($id)) return;
        if ($projectId !== null && !DB::val('SELECT id FROM projects WHERE id = ?', [$projectId])) return;
        DB::update('member_documents', ['project_id' => $projectId], 'id = ?', [$id]);
    }

    /**
     * Change un document de catégorie — donc, au besoin, de volet.
     *
     * C'est ce qui permet de reprendre l'existant : un billet de train déposé
     * jadis dans « Autres documents » devient « Billets de voyage » et passe
     * du côté des projets. Ni le fichier ni l'association ne bougent : on ne
     * renomme rien dans le dos de quelqu'un, et si le document repasse du côté
     * contractuel il retrouve son rangement intact.
     */
    public static function rangerCategorie(int $id, string $cat): void
    {
        if (!array_key_exists($cat, self::CATEGORIES)) return;
        if (!self::row($id)) return;
        DB::update('member_documents', ['category' => $cat], 'id = ?', [$id]);
    }

    /* ---------------------------------------------------------------------
       L'association à laquelle un document appartient.        [V32-DOC-ASSO]

       Une même personne travaille pour plusieurs associations, et ses
       contrats comme ses fiches de salaire s'empilaient dans les mêmes
       rubriques sans qu'on puisse dire lesquels venaient de laquelle. La même
       information est donc dite à deux endroits, parce qu'elle sert deux fois :

         — dans l'espace de la personne, les documents sont rangés par
           association, avec les rubriques habituelles à l'intérieur : on voit
           d'un coup d'œil ce qui vient de qui ;

         — dans le NOM du fichier, le sigle de l'association vient à la fin —
           2026_07_NOM_Contrat_LVCH.pdf. Un document téléchargé quitte le site
           et va vivre dans un dossier, une pièce jointe, une sauvegarde :
           l'affichage ne le suit pas, le nom si.

       Le sigle se place à la FIN. Le début du nom appartient à celui qui
       dépose — la date, la personne, la nature de la pièce —, et une
       nomenclature établie ne se réécrit pas sous les pieds de qui s'en sert.
       Le sigle ne s'insère pas dans le nom : il s'y ajoute.

       L'association est enregistrée sous son NOM, pas sous un numéro : les
       associations vivent dans un réglage en texte libre, elles n'ont pas
       d'identifiant. C'est déjà ainsi que la fiche personnelle enregistre
       « Pour travailler avec ».
       --------------------------------------------------------------------- */

    /** La colonne « assoc » existe-t-elle déjà ? (avant « Mettre à jour la base », non.) */
    private static ?bool $colAssoc = null;

    /**
     * Tant que la base n'a pas été mise à jour, tout se comporte comme avant :
     * pas de menu Association dans l'administration, pas de séparation dans
     * l'espace, aucun nom de fichier modifié. Installer les fichiers avant de
     * mettre la base à jour ne casse donc rien — cela ne fait rien.
     */
    /* [17.08.2026] Même garde que pour `assoc`, et pour la même raison: les
       fichiers peuvent être installés avant que la migration soit passée, et un
       dépôt de facture ne doit pas tomber en panne entre les deux. */
    private static ?bool $colProjLibre = null;
    public static function colonneProjetLibre(): bool
    {
        if (self::$colProjLibre === null) {
            try {
                self::$colProjLibre = (bool)DB::one("SHOW COLUMNS FROM `member_documents` LIKE 'projet_libre'");
            } catch (\Throwable $e) {
                self::$colProjLibre = false;
            }
        }
        return self::$colProjLibre;
    }

    public static function colonneAssoc(): bool
    {
        if (self::$colAssoc === null) {
            try {
                self::$colAssoc = (bool)DB::one("SHOW COLUMNS FROM `member_documents` LIKE 'assoc'");
            } catch (\Throwable $e) {
                self::$colAssoc = false;
            }
        }
        return self::$colAssoc;
    }

    /** Les associations qui ont un sigle, dans l'ordre des réglages : nom => sigle. */
    public static function assocs(): array
    {
        return Settings::sigles('form_assoc_options');
    }

    /**
     * Toutes les associations proposables, nom => sigle (le sigle peut manquer).
     *
     * C'est la liste des menus déroulants, et elle est plus large que celle des
     * sigles : une association sans sigle doit rester choisissable, sinon ses
     * documents ne pourraient pas être rangés sous son nom dans l'espace. Le
     * sigle ne décide que du nom de fichier, jamais du classement.
     *
     * @return array<string, string>
     */
    public static function assocChoix(): array
    {
        $out = [];
        foreach (Settings::trios('form_assoc_options') as $nom => $bouts) {
            $out[$nom] = self::sigleNet((string)$bouts['sigle']);
        }
        return $out;
    }

    /** Un sigle réduit à ce qui peut vivre dans un nom de fichier. */
    private static function sigleNet(string $sigle): string
    {
        return (string)preg_replace('/[^A-Za-z0-9-]+/', '', trim($sigle));
    }

    /**
     * Le sigle d'une association telle qu'elle est écrite dans les réglages.
     *
     * Une association inconnue, ou connue mais sans sigle, ne donne rien : le
     * document est bien rangé sous son nom, son fichier garde simplement le
     * nom qu'on lui a donné. Un sigle oublié dans les réglages ne fait donc
     * perdre aucun document — il ne fait perdre que le suffixe.
     */
    public static function sigle(string $assoc): string
    {
        $assoc = trim($assoc);
        if ($assoc === '') return '';
        return self::sigleNet((string)(self::assocs()[$assoc] ?? ''));
    }

    /**
     * Place le sigle de l'association à la fin du nom du fichier.
     *
     *   « 2026_07_NOM_Contrat.pdf » + Le Voisin CH
     *   → « 2026_07_NOM_Contrat_LVCH.pdf »
     *
     * Un sigle déjà présent à la fin est d'abord retiré, à condition qu'il
     * figure dans les réglages. C'est ce qui permet de changer un document
     * d'association sans empiler les suffixes, et de ne rien ajouter à un
     * fichier que le bureau avait déjà nommé correctement à la main.
     *
     * Un suffixe qui n'est pas un sigle connu — _v2, _signe, _final — n'est
     * jamais touché : il appartient à celui qui a nommé le fichier, et on ne
     * devine pas à sa place.
     */
    public static function nomAvecSigle(string $nom, string $assoc): string
    {
        $ext  = pathinfo($nom, PATHINFO_EXTENSION);
        $base = pathinfo($nom, PATHINFO_FILENAME);

        $connus = [];
        foreach (self::assocs() as $s) {
            $s = self::sigleNet($s);
            if ($s !== '') $connus[] = $s;
        }

        // Les sigles accumulés en fin de nom sont retirés un par un. La borne
        // n'est pas une précaution contre un cas prévu : c'est l'assurance
        // qu'un réglage bizarre ne fera jamais tourner cette boucle sans fin.
        $encore = true;
        $garde  = 0;
        while ($encore && $garde++ < 5) {
            $encore = false;
            foreach ($connus as $s) {
                $court = (string)preg_replace('/[._-]' . preg_quote($s, '/') . '$/i', '', $base);
                if ($court !== $base) { $base = $court; $encore = true; break; }
            }
        }

        $sigle = self::sigle($assoc);
        if ($sigle !== '') $base .= '_' . $sigle;
        if ($base === '')  $base = 'document';

        return $ext === '' ? $base : $base . '.' . $ext;
    }

    /**
     * Range un document déjà déposé sous une association — et renomme son
     * fichier en conséquence.
     *
     * Le renommage est fait AVANT l'écriture en base, et la base n'enregistre
     * le nouveau nom que si le disque a bien suivi. Un renommage refusé
     * laisse donc un document parfaitement fonctionnel, rangé sous la bonne
     * association, avec son ancien nom de fichier : c'est une gêne, pas une
     * perte. L'inverse — la base qui annonce un fichier que le disque n'a
     * pas — rendrait le document impossible à télécharger.
     */
    public static function ranger(int $id, string $assoc): void
    {
        if (!self::colonneAssoc()) return;
        $doc = self::row($id);
        if (!$doc) return;

        $assoc = trim($assoc);
        if ($assoc !== '' && !array_key_exists($assoc, Settings::trios('form_assoc_options'))) return;

        $maj = ['assoc' => $assoc];
        $dir = self::privateRoot() . '/m/' . $id;

        foreach (['filename', 'signed_filename'] as $col) {
            $ancien = trim((string)($doc[$col] ?? ''));
            if ($ancien === '') continue;
            $neuf = self::nomAvecSigle($ancien, $assoc);
            if ($neuf === $ancien) continue;
            if (!is_file($dir . '/' . $ancien)) continue;
            if (!@rename($dir . '/' . $ancien, $dir . '/' . $neuf)) continue;
            $maj[$col] = $neuf;
        }

        DB::update('member_documents', $maj, 'id = ?', [$id]);
    }

    /* =====================================================================
       Le statut d'un document, et qui a le droit de le poser. [V36-FACTURES]

       Trois mots suffisent pour les deux sens de circulation, parce que ce
       n'est pas le mot qui change de sens, c'est le document :

         — la personne dépose sa facture : elle est ENVOYÉE ;
         — le bureau la règle : elle est PAYÉE ;
         — la personne voit l'argent arriver : elle est REÇUE.

         — le bureau dépose une fiche de salaire, un contrat : la personne dit
           qu'elle l'a bien reçue. C'est le même « received » en base, et ce
           n'est pas un raccourci : dans les deux cas, il veut dire « c'est
           arrivé chez moi ». Seul le libellé diffère à l'écran, parce qu'on
           n'accuse pas réception d'un virement comme d'un PDF.

       Qui peut poser quoi est décidé ICI, en un seul endroit, et jamais dans
       le formulaire : un bouton absent de la page n'empêche personne de
       fabriquer un envoi à la main. La règle tient en une phrase — chacun ne
       confirme que ce qu'il constate lui-même. Le bureau seul sait qu'il a
       payé ; la personne seule sait qu'elle a reçu.
       ===================================================================== */

    /** La colonne « status » existe-t-elle déjà ? (avant « Mettre à jour la base », non.) */
    private static ?bool $colStatut = null;

    /**
     * Comme pour l'association : tant que la base n'a pas été mise à jour, il
     * n'y a ni statut, ni bouton, ni dépôt de facture — le reste du site
     * fonctionne exactement comme avant. Installer les fichiers avant de
     * mettre la base à jour ne casse rien.
     */
    public static function colonneStatut(): bool
    {
        if (self::$colStatut === null) {
            try {
                self::$colStatut = (bool)DB::one("SHOW COLUMNS FROM `member_documents` LIKE 'status'");
            } catch (\Throwable $e) {
                self::$colStatut = false;
            }
        }
        return self::$colStatut;
    }

    /** Le document a-t-il été déposé par la personne elle-même ? */
    public static function parLaPersonne(array $doc): bool
    {
        return (string)($doc['uploaded_by'] ?? 'admin') === 'member';
    }

    /**
     * Le statut que ce côté-ci peut poser maintenant — ou '' s'il n'a rien à
     * poser. C'est la seule autorité : l'affichage la consulte pour savoir
     * s'il dessine un bouton, et l'enregistrement la consulte pour savoir s'il
     * écrit.
     *
     * @param string $par 'member' (la personne) ou 'admin' (le bureau)
     */
    public static function statutSuivant(array $doc, string $par): string
    {
        if (!self::colonneStatut()) return '';
        $etat = (string)($doc['status'] ?? '');

        if (self::parLaPersonne($doc)) {
            // Une facture suit la chaîne, dans l'ordre, chacun son tour.
            if ($par === 'admin')  return $etat === 'sent' ? 'paid' : '';
            if ($par === 'member') return $etat === 'paid' ? 'received' : '';
            return '';
        }
        // Un document déposé par le bureau : la personne en accuse réception,
        // une fois. Le bureau n'accuse pas réception de son propre envoi.
        if ($par === 'member') return $etat === 'received' ? '' : 'received';
        return '';
    }

    /**
     * Pose le statut, si et seulement si ce côté-ci avait le droit de le poser.
     *
     * Toute autre demande est refusée sans bruit : un identifiant inventé, un
     * statut sauté, un document qui appartient à quelqu'un d'autre. La
     * propriété est vérifiée par l'appelant, qui seul sait de qui il s'agit ;
     * ici on vérifie l'enchaînement.
     */
    public static function statut(int $id, string $vers, string $par): bool
    {
        if (!self::colonneStatut() || $vers === '') return false;
        $doc = self::row($id);
        if (!$doc) return false;
        if (self::statutSuivant($doc, $par) !== $vers) return false;
        DB::update('member_documents',
            ['status' => $vers, 'status_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        return true;
    }

    /**
     * La clef de traduction du statut affiché, ou '' si rien n'est à dire.
     *
     * Le même « received » se lit « Reçue » sur une facture — l'argent est
     * arrivé — et « Bien reçu » sur une fiche de salaire — le document est
     * arrivé. C'est ici que les deux se séparent, une fois pour toutes.
     */
    public static function statutClef(array $doc): string
    {
        $etat = (string)($doc['status'] ?? '');
        if ($etat === '' || !self::colonneStatut()) return '';
        if (!self::parLaPersonne($doc)) return $etat === 'received' ? 'doc_st_ack' : '';
        return in_array($etat, ['sent', 'paid', 'received'], true) ? 'doc_st_' . $etat : '';
    }

    /** La clef de traduction du bouton qui pose le statut suivant. */
    public static function boutonClef(array $doc, string $vers): string
    {
        if ($vers === 'paid')     return 'doc_do_paid';
        if ($vers !== 'received') return '';
        return self::parLaPersonne($doc) ? 'doc_do_received' : 'doc_do_ack';
    }

    /**
     * Les factures envoyées et pas encore payées, la plus ancienne d'abord.
     *
     * L'ordre n'est pas un détail : une facture qui attend depuis six semaines
     * doit se lire avant celle d'avant-hier, sinon la liste ne sert qu'à
     * mesurer le retard sans aider à le rattraper. Le nom de la personne vient
     * avec la ligne — c'est chez elle qu'on va cliquer.
     *
     * Rien avant la mise à jour de la base : la colonne n'existe pas, la
     * requête tomberait, et le tableau de bord est la première page qu'on voit
     * en se connectant.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function facturesEnAttente(int $max = 25): array
    {
        if (!self::colonneStatut()) return [];
        try {
            return DB::all(
                'SELECT d.*, c.name AS personne, c.email AS courriel
                   FROM member_documents d
                   JOIN collaborators c ON c.id = d.collaborator_id
                  WHERE d.status = ? AND d.uploaded_by = ?
                  ORDER BY d.status_at, d.id
                  LIMIT ' . max(1, min(200, $max)),
                ['sent', 'member']
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Le nom d'une facture déposée par la personne : 2026_07_NOM_Facture.pdf
     *
     * Le sigle de l'association est ajouté ensuite, à la fin, par la même
     * mécanique que pour les dépôts du bureau — il n'y a qu'une nomenclature.
     *
     * Le nom est fabriqué par le site et non demandé à la personne : sur
     * soixante-dix-sept comptes, un fichier déposé s'appelle « scan.pdf »,
     * « IMG_4471.jpg » ou « facture (2).pdf ». Le bureau, lui, reçoit chaque
     * mois des pièces qu'il doit pouvoir ranger sans les ouvrir.
     */
    /**
     * Le nom d'un dépôt de la personne.
     *
     * [13.08.2026] Le mot final suit la catégorie. Le fichier s'appelait
     * « _Facture » même pour un justificatif de frais, ce qui obligeait le
     * bureau à ouvrir le PDF pour savoir ce qu'il tenait : précisément le
     * problème que la distinction facture / justificatif venait supprimer.
     */
    /**
     * Le nom d'un dépôt fait par la personne depuis son espace.
     *
     * [13.08.2026] Même nomenclature que le formulaire public, par le même
     * constructeur : un justificatif doit se lire pareil d'où qu'il vienne, et
     * le bureau les range dans les mêmes dossiers. Voir app/lib/NomFichier.php
     * pour l'ordre et la raison de chaque morceau.
     *
     * Le montant est demandé au dépôt depuis ce jour-là. Sans lui, il fallait
     * ouvrir le PDF pour savoir ce que valait une facture arrivée par l'espace.
     */
    public static function nomDepot(string $personne, string $montant, string $devise,
                                    string $assoc, string $categorie, string $projet, string $ext): string
    {
        return NomFichier::construire([
            $montant,
            $devise,
            mb_strtoupper($assoc),
            $categorie === 'expense' ? 'Frais' : 'Facture',
            $projet,
            $personne,
        ], $ext, $categorie === 'expense' ? 'Frais' : 'Facture');
    }


    /**
     * Un nom que la personne n'a pas déjà employé.
     *
     * Deux factures pour la même association le même mois — cela arrive, et ce
     * n'est pas une erreur. Comme c'est le site qui a choisi le nom, c'est à
     * lui de régler la collision qu'il vient de créer : -2, -3. Les noms
     * choisis à la main, eux, ne sont jamais retouchés.
     */
    private static function nomUnique(int $cid, string $nom): string
    {
        $ext  = pathinfo($nom, PATHINFO_EXTENSION);
        $base = pathinfo($nom, PATHINFO_FILENAME);
        $pris = [];
        foreach (DB::all('SELECT filename FROM member_documents WHERE collaborator_id = ?', [$cid]) as $r) {
            $pris[mb_strtolower((string)$r['filename'])] = true;
        }
        $essai = $nom;
        for ($n = 2; isset($pris[mb_strtolower($essai)]) && $n < 100; $n++) {
            $essai = $base . '-' . $n . ($ext === '' ? '' : '.' . $ext);
        }
        return $essai;
    }

    /**
     * Une pièce de cette rubrique a-t-elle été déposée dans l'année en cours ?
     *                                                          [14.08.2026]
     * L'ANNÉE CIVILE, et non « une fois dans la vie ». L'attestation
     * d'indépendant·e vaut pour un exercice : celle de l'an dernier ne prouve
     * rien cette année. La question se repose donc toute seule au 1er janvier,
     * ce qui est exactement ce qu'on veut d'un rappel annuel — personne n'a à
     * penser à le rallumer.
     */
    public static function docDeLAnnee(int $cid, string $categorie): bool
    {
        if ($cid <= 0 || !array_key_exists($categorie, self::CATEGORIES)) return false;
        return (int)DB::val(
            'SELECT COUNT(*) FROM member_documents
              WHERE collaborator_id = ? AND category = ? AND YEAR(created_at) = ?',
            [$cid, $categorie, (int)date('Y')]) > 0;
    }

    /** Racine privée, protégée contre l'accès direct par le web. */
    private static function privateRoot(): string
    {
        $root = LV_UPLOADS . '/private';
        if (!is_dir($root)) mkdir($root, 0775, true);
        $ht = $root . '/.htaccess';
        if (!is_file($ht)) {
            // Bloque tout accès direct (Apache 2.2 et 2.4).
            file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
        }
        return $root;
    }

    public static function dir(int $docId): string
    {
        $dir = self::privateRoot() . '/m/' . $docId;
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        return $dir;
    }

    public static function row(int $id): ?array
    {
        return DB::one('SELECT * FROM member_documents WHERE id = ?', [$id]);
    }

    public static function forMember(int $collaboratorId): array
    {
        return DB::all(
            'SELECT * FROM member_documents WHERE collaborator_id = ? ORDER BY category, sort, id',
            [$collaboratorId]
        );
    }

    /** Chemin sur le disque du fichier (version signée si disponible, sinon original). */
    public static function filePath(array $doc, bool $preferSigned = true): string
    {
        $dir = self::privateRoot() . '/m/' . (int)$doc['id'];
        if ($preferSigned && $doc['signed_filename'] !== '') return $dir . '/' . $doc['signed_filename'];
        return $dir . '/' . $doc['filename'];
    }

    /**
     * Dépose un document.
     *
     * @param string $par        'admin' (le bureau) ou 'member' (la personne).
     * @param string $nomImpose  Nom de fichier choisi par le site plutôt que
     *                           par le déposant ; le sigle lui est ajouté
     *                           ensuite, comme aux autres.
     */
    public static function upload(array $file, int $collaboratorId, string $category, ?int $projectId, bool $needsSignature, string $assoc = '', string $par = 'admin', string $nomImpose = '', string $projetLibre = ''): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(tu('sys_upload_err'));
        }
        if ($file['size'] > self::MAX_SIZE) throw new RuntimeException(tu('sys_doc_big'));
        $ext = mb_strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTS, true)) {
            throw new RuntimeException(tu('sys_doc_formats', strtoupper(implode(', ', self::EXTS))));
        }
        if (!array_key_exists($category, self::CATEGORIES)) $category = 'other';
        if ($needsSignature && $ext !== 'pdf') throw new RuntimeException(tu('sys_pdf_only'));

        $assoc = self::colonneAssoc() ? trim($assoc) : '';
        if ($assoc !== '' && !array_key_exists($assoc, Settings::trios('form_assoc_options'))) $assoc = '';

        /* [V33-ESPACE-3] C'est la catégorie qui commande : un document de
           production n'a pas d'association, un document contractuel n'a pas de
           projet. La règle est appliquée ici, une fois, plutôt que dans chaque
           formulaire — un envoi bricolé à la main ne peut pas la contourner. */
        /* [V36-FACTURES] Une seule exception, et elle est nommée : la facture
           appartient à une association ET peut concerner une production. Sans
           cette ligne, le projet choisi par la personne serait effacé en
           silence — le formulaire l'aurait demandé pour rien. */
        if (self::volet($category) === 'projet')                  $assoc = '';
        elseif (!in_array($category, self::AVEC_PROJET, true))    $projectId = null;

        $source = $nomImpose !== '' ? $nomImpose : (string)$file['name'];
        $clean  = preg_replace('/[^A-Za-z0-9._-]+/', '-', $source) ?: ('document.' . $ext);
        $clean  = self::nomAvecSigle($clean, $assoc);
        /* Le site n'a le droit de renuméroter que les noms qu'il a lui-même
           choisis : une pièce nommée à la main garde le nom qu'on lui a donné. */
        if ($nomImpose !== '') $clean = self::nomUnique($collaboratorId, $clean);

        /* [V32-DOC-ASSO] Le titre affiché se passe du sigle : dans l'espace,
           il est déjà écrit en tête de section, et le répéter sur chaque ligne
           n'apprend rien. On le retire donc du titre — y compris quand c'est
           le bureau qui l'avait tapé lui-même dans le nom du fichier. */
        $title = trim(preg_replace('/[-_]+/', ' ',
            pathinfo(self::nomAvecSigle($source, ''), PATHINFO_FILENAME)));

        $sort = 1 + (int)DB::val('SELECT COALESCE(MAX(sort),0) FROM member_documents WHERE collaborator_id = ? AND category = ?', [$collaboratorId, $category]);
        $ligne = [
            'collaborator_id' => $collaboratorId,
            'category' => $category,
            'project_id' => $projectId ?: null,
            'title' => mb_substr($title, 0, 250),
            'filename' => $clean,
            'ext' => $ext,
            'size' => (int)$file['size'],
            'needs_signature' => $needsSignature ? 1 : 0,
            'sign_status' => $needsSignature ? 'to_sign' : 'none',
            'sort' => $sort,
        ];
        /* [V32-DOC-ASSO] La colonne n'est nommée que si elle existe : les
           fichiers peuvent être installés avant la mise à jour de la base, et
           un dépôt de document ne doit pas tomber en panne entre les deux. */
        if (self::colonneAssoc()) $ligne['assoc'] = mb_substr($assoc, 0, 120);
        if (self::colonneProjetLibre() && trim($projetLibre) !== '')
            $ligne['projet_libre'] = mb_substr(trim($projetLibre), 0, 190);
        /* [V36-FACTURES] Une facture déposée par la personne est ENVOYÉE dès
           qu'elle arrive : le dépôt EST l'envoi, il n'y a pas de bouton à
           cliquer ensuite pour dire ce qu'on vient de faire. Tout le reste
           part sans statut — un contrat n'attend rien tant qu'on ne lui a
           rien demandé. */
        if (self::colonneStatut()) {
            /* [13.08.2026] « expense » suit exactement le même circuit que « invoice » :
           déposée par la personne, elle part en « envoyée », le bureau la marque
           payée, la personne confirme. Sans cette ligne, un justificatif de frais
           n'avait pas d'état du tout : aucun bouton d'un côté, aucun suivi de
           l'autre, et il n'apparaissait sur aucune liste d'attente. */
        $depart = ($par === 'member' && in_array($category, ['invoice', 'expense'], true)) ? 'sent' : '';
            $ligne['uploaded_by'] = $par === 'member' ? 'member' : 'admin';
            $ligne['status']      = $depart;
            $ligne['status_at']   = $depart !== '' ? date('Y-m-d H:i:s') : null;
        }
        $id = DB::insert('member_documents', $ligne);
        $dir = self::dir($id);
        $dest = $dir . '/' . $clean;
        if (!move_uploaded_file($file['tmp_name'], $dest) && !rename($file['tmp_name'], $dest)) {
            DB::delete('member_documents', 'id = ?', [$id]);
            throw new RuntimeException(tu('sys_save_err'));
        }
        return self::row($id);
    }

    public static function delete(int $id): void
    {
        $doc = self::row($id);
        if (!$doc) return;
        $dir = self::privateRoot() . '/m/' . $id;
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
            @rmdir($dir);
        }
        DB::delete('member_documents', 'id = ?', [$id]);
    }

    public static function catLabel(string $cat, string $lang = 'fr'): string
    {
        return self::CATEGORIES[$cat][$lang] ?? self::CATEGORIES['other'][$lang];
    }
}

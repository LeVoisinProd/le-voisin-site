<?php
/**
 * Le message qui porte le lien d'accès.   [V28-INVIT]
 *
 * Jusqu'ici, le lien à usage unique existait mais ne partait nulle part : il
 * s'affichait dans l'administration, à charge pour le bureau de le copier et
 * de rédiger un courriel à la main, une personne après l'autre. Pour une
 * équipe entière cela fait autant d'occasions de coller le lien de quelqu'un
 * d'autre — et le lien de quelqu'un d'autre ouvre son espace, pas le vôtre.
 *
 * Ce fichier réunit les trois pièces qui existaient déjà chacune de leur
 * côté : le lien (MemberAuth), l'envoi (Mailer) et la langue de la personne
 * (colonne « lang » du collaborateur). Le texte, lui, n'est pas dans le code :
 * il se modifie dans Administration > Réglages, en français et en anglais,
 * avec deux marqueurs, {nom} et {lien}, remplacés au moment de l'envoi. Les
 * formes anglaises {name} et {link} sont acceptées aussi : écrire {name} dans
 * la version anglaise est le geste naturel, et rien ne signalerait l'erreur —
 * le message partirait avec « {name} » écrit en toutes lettres.
 *
 * Les textes ci-dessous ne servent que tant que rien n'a été saisi dans les
 * réglages : le site sait donc envoyer une invitation correcte dès le premier
 * jour, sans réglage préalable.
 */
class Invitations
{
    /** Les réglages que la page Réglages enregistre pour nous. */
    public const CLES = ['invite_subject_fr', 'invite_subject_en', 'invite_body_fr', 'invite_body_en'];

    /** La langue d'une personne, ramenée à celles que l'on sait écrire. */
    public static function langue(?string $lang): string
    {
        return strtolower(trim((string)$lang)) === 'en' ? 'en' : 'fr';
    }

    public static function sujetDefaut(string $lang): string
    {
        // Le nom du site passe devant plutôt qu'après une préposition : « de Le
        // Voisin » ne se dit pas, et le sujet doit rester juste quel que soit
        // le nom saisi dans les réglages.
        $site = setting('site_name', 'Le Voisin');
        return self::langue($lang) === 'en'
            ? $site . ' — your access to the private area'
            : $site . ' — votre accès à l’espace personnel';
    }

    /**
     * Le message d'origine.
     *
     * [12.08.2026] Il disait seulement « votre espace est prêt, choisissez un
     * mot de passe ». C'est vrai et c'est insuffisant : la personne arrive
     * devant quatre onglets sans savoir lequel la concerne, ni qu'elle devra
     * revenir confirmer la réception d'un paiement. Elle repart, et l'espace
     * ne sert à rien.
     *
     * Le message explique donc la procédure, en quatre lignes et dans l'ordre
     * où elle la vivra. Il reste modifiable dans les réglages : ce texte n'est
     * que le repli, celui qui part quand personne n'a rien écrit.
     */
    public static function texteDefaut(string $lang): string
    {
        $j = MemberAuth::LIEN_JOURS;
        if (self::langue($lang) === 'en') {
            return "Hello {nom},\n\n"
                 . "Your personal space on our website is ready. You will find there everything "
                 . "that concerns you, and you can send us your invoices from it.\n\n"
                 . "Open the link below and choose your own password:\n\n"
                 . "{lien}\n\n"
                 . "The link works only once and stays valid for $j days. After that, just ask us "
                 . "for a new one.\n\n"
                 . "WHAT YOU WILL FIND THERE\n\n"
                 . "1. Your details — to fill in once, so we can draw up your contracts and pay you.\n"
                 . "2. Contracts and payslips — we file them there, you download them whenever you need.\n"
                 . "3. Payments and reimbursements — you upload your invoices and receipts, and you follow "
                 . "where they stand: sent, then paid by us, then you confirm you received the money.\n"
                 . "4. Your projects — tour sheets, travel and accommodation, filed by production.\n\n"
                 . "The third one asks something of you: once we mark an invoice as paid, please come back "
                 . "and confirm. Without that we cannot tell what has actually reached your account.\n\n"
                 . "See you soon";
        }
        return "Bonjour {nom},\n\n"
             . "Votre espace personnel sur notre site est prêt. Vous y trouverez tout ce qui vous "
             . "concerne, et vous pourrez nous envoyer vos factures depuis là.\n\n"
             . "Ouvrez le lien ci-dessous et choisissez vous-même votre mot de passe :\n\n"
             . "{lien}\n\n"
             . "Le lien ne sert qu'une fois et reste valable $j jours. Passé ce délai, demandez-nous-en "
             . "simplement un autre.\n\n"
             . "CE QUE VOUS Y TROUVEREZ\n\n"
             . "1. Vos informations — à remplir une fois, pour que nous puissions établir vos contrats "
             . "et vous payer.\n"
             . "2. Contrats et fiches de salaire — nous les y déposons, vous les téléchargez quand vous "
             . "en avez besoin.\n"
             . "3. Paiements et remboursements — vous y déposez vos factures et justificatifs, et vous "
             . "suivez où ils en sont : envoyé, puis payé par nous, puis vous confirmez avoir bien reçu "
             . "l'argent.\n"
             . "4. Vos projets — feuilles de route, voyages et hébergements, classés par production.\n\n"
             . "Le troisième point vous demande quelque chose : une fois que nous avons marqué une facture "
             . "comme payée, revenez confirmer. Sans cela nous ne savons pas ce qui vous est réellement "
             . "parvenu.\n\n"
             . "À bientôt";
    }

    /** Le sujet enregistré, ou celui d'origine si le champ est resté vide. */
    public static function sujet(string $lang): string
    {
        $lang = self::langue($lang);
        $v = trim(setting('invite_subject_' . $lang, ''));
        return $v !== '' ? $v : self::sujetDefaut($lang);
    }

    /** Le corps enregistré, ou celui d'origine si le champ est resté vide. */
    public static function texte(string $lang): string
    {
        $lang = self::langue($lang);
        $v = trim(setting('invite_body_' . $lang, ''));
        return $v !== '' ? $v : self::texteDefaut($lang);
    }

    /**
     * Remplacement des marqueurs dans du texte simple (le sujet).
     *
     * strtr() plutôt que str_replace() : strtr ne relit pas ce qu'il vient
     * d'écrire. Un nom qui contiendrait par accident « {lien} » ne verrait
     * donc pas son propre lien inséré à cet endroit.
     */
    public static function rendreTexte(string $modele, string $nom, string $lien): string
    {
        return strtr($modele, [
            '{nom}' => $nom, '{name}' => $nom,
            '{lien}' => $lien, '{link}' => $lien,
        ]);
    }

    /**
     * Le même texte en HTML, pour le corps du message.
     *
     * On échappe d'abord le modèle entier — un texte saisi dans les réglages
     * est du texte, pas du HTML, et une esperluette ne doit pas casser le
     * message — puis on remplace les marqueurs par des morceaux déjà propres.
     * Les accolades traversent l'échappement sans changer, l'ordre est donc sûr.
     *
     * Le lien s'écrit en toutes lettres plutôt qu'en « cliquez ici » : le
     * message est aussi lu en texte brut par certains logiciels, et l'adresse
     * doit rester copiable à la main.
     */
    public static function rendreHtml(string $modele, string $nom, string $lien): string
    {
        $ancre = '<a href="' . e($lien) . '" style="color:#111;word-break:break-all;">' . e($lien) . '</a>';
        $corps = strtr(nl2br(e($modele)), [
            '{nom}' => e($nom), '{name}' => e($nom),
            '{lien}' => $ancre, '{link}' => $ancre,
        ]);
        return '<div style="font-size:15px;line-height:1.65;">' . $corps . '</div>';
    }

    /**
     * Ce qui empêcherait tout envoi, dit avant d'y toucher.
     *
     * Sans ce contrôle, la page fabriquerait des liens neufs pour toute
     * l'équipe — ce qui annule les précédents — avant de découvrir qu'aucun
     * message ne peut partir. Renvoie '' quand la voie est libre.
     */
    public static function obstacle(): string
    {
        if (trim((string)setting('smtp_host', '')) === '' && !function_exists('mail')) {
            return ta('inv_err_nosmtp');
        }
        return '';
    }

    /**
     * Un lien neuf pour cette personne, et le message qui va avec.
     *
     * Renvoie le compte rendu de l'envoi : de quoi écrire à l'écran qui a reçu
     * quoi, et pourquoi le reste n'est pas parti.
     */
    public static function envoyer(array $c): array
    {
        $id    = (int)($c['id'] ?? 0);
        $nom   = trim((string)($c['name'] ?? ''));
        $email = trim((string)($c['email'] ?? ''));
        $lang  = self::langue((string)($c['lang'] ?? ''));

        $r = ['id' => $id, 'nom' => $nom, 'email' => $email, 'lang' => $lang,
              'ok' => false, 'raison' => '', 'lien' => false];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $r['raison'] = ta('inv_err_email'); return $r; }
        if (empty($c['active']))                        { $r['raison'] = ta('inv_err_off');   return $r; }

        try {
            $jeton = MemberAuth::lienNouveau($id);
        } catch (Throwable $ex) {
            // Les colonnes du lien n'existent qu'après « Mettre à jour la base ».
            $r['raison'] = ta('inv_err_link');
            return $r;
        }
        $r['lien'] = true;

        $lien  = MemberAuth::lienUrl($jeton);
        $sujet = self::rendreTexte(self::sujet($lang), $nom, $lien);
        $html  = Mailer::wrap($sujet, self::rendreHtml(self::texte($lang), $nom, $lien));

        if (Mailer::send([$email], $sujet, $html)) { $r['ok'] = true; return $r; }

        // Le lien existe désormais mais n'est arrivé nulle part. On ne l'efface
        // pas : il reste copiable à la main depuis la fiche de la personne,
        // c'est le seul dépannage possible tant que l'envoi ne passe pas.
        $r['raison'] = trim(Smtp::$erreur) !== '' ? trim(Smtp::$erreur) : ta('inv_err_send');
        return $r;
    }

    /**
     * Le même message envoyé à soi-même, pour le relire avant de l'expédier.
     *
     * Aucun collaborateur n'est touché et aucun vrai lien n'est fabriqué :
     * l'adresse montrée a la bonne forme mais ne mène à rien, et le sujet est
     * préfixé pour qu'on ne confonde pas cet essai avec un envoi réel.
     */
    public static function essai(string $email, string $lang): array
    {
        $lang  = self::langue($lang);
        $email = trim($email);
        $r = ['ok' => false, 'email' => $email, 'lang' => $lang, 'raison' => ''];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $r['raison'] = ta('inv_err_email'); return $r; }

        $nom   = ta('inv_demo_name');
        $lien  = MemberAuth::lienUrl(str_repeat('0', 64));
        $sujet = ta('inv_test_prefix') . ' ' . self::rendreTexte(self::sujet($lang), $nom, $lien);
        $html  = Mailer::wrap($sujet, self::rendreHtml(self::texte($lang), $nom, $lien));

        if (Mailer::send([$email], $sujet, $html)) { $r['ok'] = true; return $r; }
        $r['raison'] = trim(Smtp::$erreur) !== '' ? trim(Smtp::$erreur) : ta('inv_err_send');
        return $r;
    }
}

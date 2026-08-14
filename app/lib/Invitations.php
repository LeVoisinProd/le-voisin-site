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
            ? 'What\'s new at ' . $site . ': your personal space, and the site\'s new look'
            : 'Nouveautés de ' . $site . ' : ton espace personnel, et le nouveau visage du site';
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
        if (self::langue($lang) === 'en') {
            return <<<'TXT'
Hello {nom},

We are pleased to introduce the new look of the Le Voisin website. It comes with a simple intention: to work better with performing arts professionals, and to make our administrative exchanges clearer and more efficient.

The first new feature concerns you directly. You now have a personal space, where you will find everything that connects you to the associations we manage, and from which you send us your invoices and receive your contractual documents.

{bouton:Request my entry key}

~ No password to choose or remember.
~ Your space is tied to this email address, and to this one only.

The button takes you to the entry page. You enter this address, the one where you are reading this message, and you receive your key straight away. One click on the key and you are home. Any other address will not recognise you. It works the same way at every visit, and between visits your browser keeps you recognised for a month.

WHAT YOU WILL FIND THERE

The collaborator space has four tabs.

1. My information
Available at any time. You keep your contact details, your short biography and your profile photo up to date there: they are what we reuse in grant applications, and an up to date biography makes a better application. If you move house or change bank account, you can update your details directly in your profile. It can be edited at any time. We receive a notice of the changes straight away.

2. Contracts and payroll
Contracts, payslips and AGIs. We deposit them there, you download them when you need them, and you confirm you received them with the "I have received it" button. Contracts are signed directly from the space, with one click on "Sign": nothing left to print, scan or send back.

3. Payments and reimbursements
You deposit your invoices and expense receipts there, and you follow where they stand. For every document you deposit, you receive a confirmation email: it is your proof, with the what and the when.

4. Projects
Roadmaps, travel, accommodation and logistics, sorted by production. Every available document about the tours is in the same place.

THE MOST IMPORTANT POINT

An invoice goes through three states, and each step is confirmed by the person who alone can observe it.

> You deposit your invoice: it is sent.
> We settle it: we mark it paid.
> The money reaches your account: you click "I have received the payment".

That last gesture is what confirms to us that everything went through.

/ Please note: payments go out in the last week of each month. A transfer to an account outside Switzerland takes about two more working days to be credited.

WHY THIS EVOLUTION

The feedback we have been receiving for months all says much the same thing, and I wanted to answer it seriously: a clear administrative framework, one that lets the artistic work move forward more smoothly. Invoices followed, contracts within reach and payments traced, that is time given back to your main work.

We are a very small team, and by improving our tools we defend as best we can the projects of our associate artists, with partners and funders.

We have tried to make this new experience as smooth as possible. But if despite our efforts you have a question, or any trouble connecting, just reply to this message. Together we can improve our practices. Thank you in advance for your collaboration.

See you soon,
Anna
TXT;
        }
        return <<<'TXT'
Bonjour {nom},

Nous sommes heureuses de te présenter le nouveau visage du site de Le Voisin. Il accompagne une intention simple : mieux travailler avec les professionnel·les du spectacle, et rendre nos échanges administratifs plus clairs et plus efficaces.

La première nouveauté te concerne directement. Tu as désormais un espace personnel, où tu trouveras tout ce qui te lie aux associations que nous gérons, et depuis lequel tu nous envoies tes factures et reçois tes documents contractuels.

{bouton:Demander ma clé d'entrée}

~ Aucun mot de passe à choisir ni à retenir.
~ Ton espace est lié à cette adresse mail, et à elle seule.

Le bouton t'amène sur la page d'entrée. Tu y indiques cette adresse, celle à laquelle tu lis ce message, et tu reçois aussitôt ta clé. Un clic sur la clé et tu es chez toi. Une autre adresse ne te reconnaîtra pas. C'est la même chose à chaque visite, et entre deux visites ton navigateur te garde reconnu·e pendant un mois.

CE QUE TU Y TROUVERAS

L'Espace collaborateur·rice tient en quatre onglets.

1. Mes informations
Accessible à tout moment. Tu y tiens à jour tes coordonnées, ta courte biographie et ta photo de profil : ce sont elles que nous reprenons dans les dossiers de subvention, et une biographie à jour fait un meilleur dossier. Si tu déménages ou changes de compte bancaire, tu peux actualiser tes infos directement dans ton profil. Il est modifiable à tout moment. Nous recevons directement un avertissement des changements.

2. Contractualisation
Contrats, fiches de salaire et AGIs. Nous les y déposons, tu les télécharges quand tu en as besoin, et tu confirmes leur bonne réception avec le bouton « J'ai bien reçu ». Les contrats se signent directement depuis l'espace, d'un clic sur « Signer » : plus rien à imprimer, ni à scanner, ni à nous renvoyer.

3. Paiements & remboursements
Tu y déposes tes factures et tes justificatifs de frais, et tu suis où ils en sont. À chaque document déposé, tu reçois un courriel de confirmation : il te sert de preuve, avec le quoi et le quand.

4. Projets
Feuilles de route, voyages, hébergements et logistique, classés par production. Tout document accessible concernant les tournées se trouve au même endroit.

LE POINT LE PLUS IMPORTANT

Une facture passe par trois états, et chaque étape est confirmée par la personne qui est seule à pouvoir la constater.

> Tu déposes ta facture : elle est envoyée.
> Nous la réglons : nous la marquons payée.
> L'argent arrive sur ton compte : tu cliques sur « J'ai reçu le paiement ».

Ce dernier geste permet de nous confirmer que tout s'est bien passé.

/ À noter : les paiements partent la dernière semaine de chaque mois. Un virement vers un compte hors de Suisse met environ deux jours ouvrables de plus à être crédité.

POURQUOI CETTE ÉVOLUTION

Les retours que nous recevons depuis des mois disent tous un peu la même chose, et j'ai voulu y répondre sérieusement : un cadre administratif clair, qui laisse le travail artistique avancer avec plus de fluidité. Des factures suivies, des contrats accessibles et des paiements tracés, c'est du temps rendu à ton travail principal.

Nous sommes une toute petite équipe, et en faisant progresser nos outils nous défendons du mieux que nous pouvons les projets de nos artistes associé·es, auprès des partenaires et des financeurs.

Nous avons essayé de rendre cette nouvelle expérience la plus fluide possible. Mais si malgré nos efforts tu as une question, ou une difficulté pour te connecter, réponds simplement à ce message. Ensemble nous pouvons améliorer nos pratiques. Nous te remercions dès maintenant pour ta collaboration.

À bientôt,
Anna
TXT;
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
    /* ==================================================================
       LA MISE EN PAGE DU MESSAGE            [13.08.2026]

       Le texte reste du texte simple, écrit et relu dans le CMS par quelqu'un
       qui n'écrit pas de HTML. Mais un texte simple passé à nl2br() donne un
       mur de lignes, et ce message-ci a des titres, quatre rubriques numérotées
       et un encadré : il devenait illisible au moment précis où il part à
       soixante-dix-sept personnes.

       D'où une marque minuscule, six règles, qu'on retient en une fois :

         UNE LIGNE TOUT EN MAJUSCULES  ....  titre de section, souligné de jaune
         1. Quelque chose               ....  rubrique numérotée, pastille jaune
         > ligne                        ....  encadré (lignes consécutives groupées)
         / ligne                        ....  paragraphe en italique
         ~ ligne                        ....  petit texte gris, centré
         {bouton} ou {bouton:Libellé}   ....  le bouton jaune

       Tout le reste est un paragraphe. Rien n'est obligatoire : un texte écrit
       sans aucune de ces marques sort exactement comme avant, en paragraphes.
       C'était la condition pour ne pas casser un texte déjà enregistré.
       ================================================================== */

    /** Un titre de section : petites capitales et un trait jaune dessous. */
    private static function titre(string $t): string
    {
        return '<p style="margin:26px 0 4px;font-size:12px;font-weight:700;letter-spacing:.14em;'
             . 'text-transform:uppercase;color:#111;">' . e($t) . '</p>'
             . '<div style="width:42px;height:3px;background:#f0c63f;margin:0 0 18px;"></div>';
    }

    /**
     * Une rubrique numérotée : pastille jaune à gauche, texte à droite.
     *
     * En tableau et non en flexbox : la moitié des logiciels de messagerie ne
     * connaissent pas flexbox, et la pastille se retrouverait au-dessus du
     * texte. La couleur est écrite dans chaque cellule, parce que plusieurs
     * clients remettent la leur à l'intérieur d'un tableau — ce qui donne un
     * texte jaune pâle sur blanc, illisible, constaté le 13.08.
     */
    private static function rubrique(string $n, string $titre, string $corps): string
    {
        $cel = 'vertical-align:top;color:#111;font-size:15px;line-height:1.7;padding:0 0 18px;';
        return '<table role="presentation" cellpadding="0" cellspacing="0"'
             . ' style="width:100%;border-collapse:collapse;color:#111;"><tr>'
             . '<td style="width:34px;' . $cel . '">'
             . '<span style="display:inline-block;width:24px;height:24px;line-height:24px;'
             . 'text-align:center;background:#f0c63f;color:#111;border-radius:99px;'
             . 'font-weight:700;font-size:13px;">' . e($n) . '</span></td>'
             . '<td style="' . $cel . '"><b>' . e($titre) . '</b><br>' . $corps . '</td>'
             . '</tr></table>';
    }

    public static function rendreHtml(string $modele, string $nom, string $lien, string $lang = ''): string
    {
        $ancre = '<a href="' . e($lien) . '" style="color:#111;word-break:break-all;">' . e($lien) . '</a>';

        /* Les marqueurs se remplacent AVANT l'échappement pour {lien}, qui
           produit du HTML, et APRÈS pour {nom}, qui n'en produit pas. D'où le
           passage par un jeton improbable : sans lui, e() échapperait l'ancre. */
        $modele = strtr($modele, ['{name}' => '{nom}', '{link}' => '{lien}']);

        $out = '';
        foreach (preg_split("/\n[ \t]*\n/", str_replace("\r\n", "\n", trim($modele))) as $bloc) {
            $bloc  = trim($bloc, "\n");
            if (trim($bloc) === '') continue;
            $lignes = explode("\n", $bloc);
            $prem   = trim($lignes[0]);

            /* Le bouton, seul dans son bloc. */
            if (preg_match('/^\{bouton(?::(.+))?\}$/u', $prem, $m)) {
                /* [13.08.2026] Le libellé dans la langue de QUI REÇOIT, et non
                   dans celle du CMS. Une personne dont la fiche est en anglais
                   recevait un texte anglais et un bouton en français. */
                $out .= Mailer::bouton($lien, trim($m[1] ?? '') !== '' ? trim($m[1]) : self::m('inv_bouton', $lang));
                continue;
            }
            /* Un titre : une seule ligne, sans une seule minuscule, et courte. */
            if (count($lignes) === 1 && mb_strlen($prem) <= 60 && $prem !== ''
                && !preg_match('/\p{Ll}/u', $prem) && preg_match('/\p{Lu}/u', $prem)) {
                $out .= self::titre($prem);
                continue;
            }
            /* Un encadré : toutes les lignes commencent par « > ». */
            if (!array_filter($lignes, fn($l) => !preg_match('/^\s*>/', $l))) {
                $in = '';
                foreach ($lignes as $i => $l) {
                    $in .= '<p style="margin:0 0 ' . ($i === count($lignes) - 1 ? '0' : '8px') . ';color:#111;">'
                         . self::inline(preg_replace('/^\s*>\s?/', '', $l), $nom, $ancre) . '</p>';
                }
                $out .= '<div style="border:1px solid #e5e5e0;border-left:3px solid #f0c63f;'
                      . 'padding:16px 20px;margin:0 0 18px;color:#111;">' . $in . '</div>';
                continue;
            }
            /* Italique, et petit texte gris centré. */
            if (preg_match('/^\s*\//', $prem) || preg_match('/^\s*~/', $prem)) {
                $petit = preg_match('/^\s*~/', $prem);
                $txt   = self::inline(preg_replace("/^\s*[\/~]\s?/m", '', $bloc), $nom, $ancre);
                $out  .= $petit
                    ? '<p style="margin:0 0 26px;text-align:center;font-size:13px;line-height:1.6;color:#777;">' . nl2br($txt) . '</p>'
                    : '<p style="margin:0 0 26px;font-style:italic;color:#111;">' . nl2br($txt) . '</p>';
                continue;
            }
            /* Une rubrique numérotée : « 1. Titre » puis le texte dessous. */
            if (preg_match('/^(\d+)\.\s+(.+)$/u', $prem, $m)) {
                $reste = self::inline(implode("\n", array_slice($lignes, 1)), $nom, $ancre);
                $out  .= self::rubrique($m[1], $m[2], nl2br($reste));
                continue;
            }
            $out .= '<p style="margin:0 0 16px;color:#111;">'
                  . nl2br(self::inline($bloc, $nom, $ancre)) . '</p>';
        }
        return '<div style="font-size:15px;line-height:1.7;color:#111;">' . $out . '</div>';
    }

    /** Échappe un morceau de texte et y replace {nom} et {lien}. */
    private static function inline(string $t, string $nom, string $ancre): string
    {
        return strtr(e($t), ['{nom}' => e($nom), '{lien}' => $ancre]);
    }

    /* ==================================================================
       LA CLÉ D'ENTRÉE, celle que la personne se donne à elle-même.
                                                        [13.08.2026]
       À ne pas confondre avec l'invitation, plus haut. L'invitation est
       longue, éditable dans le CMS, envoyée par le bureau, et sa clé n'expire
       pas. Celle-ci est courte, écrite dans le code, demandée par la personne
       depuis la page de l'espace, et vaut trente minutes : elle est demandée
       et utilisée dans la même minute, une durée courte ne gêne personne et
       referme la fenêtre pour une boîte aux lettres lue par quelqu'un d'autre.

       Elle n'est pas éditable dans les réglages, et c'est voulu : un message
       de service que personne ne relit vaut mieux figé qu'à moitié réécrit.
       ================================================================== */

    public const CLE_MINUTES = 30;

    /**
     * Le journal des envois, écrit ligne par ligne PENDANT la boucle.
     *
     * Le compte rendu à l'écran n'est déposé en session qu'après la boucle
     * entière : une coupure à la trentième personne perd donc le résultat des
     * vingt-neuf premières, et le bureau se retrouve sans savoir qui a reçu
     * quoi. Ce fichier-ci est écrit au fur et à mesure et survit à tout, y
     * compris à un processus tué par le serveur, cas où aucune fonction de fin
     * ne s'exécute.
     *
     * Une ligne FIN manquante est le signal d'une coupure, et la dernière
     * ligne « #id » dit exactement où reprendre.
     *
     * Modelé sur Mailer::log(), et déposé dans le même dossier, fermé au web
     * par app/.htaccess.
     */
    public static function journal(string $ligne): void
    {
        $dir = LV_APP . '/logs';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/invitations.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $ligne . "\n", FILE_APPEND);
    }

    /** Les dernières lignes du journal, pour les montrer dans l'administration. */
    public static function journalLire(int $lignes = 40): string
    {
        $f = LV_APP . '/logs/invitations.log';
        if (!is_file($f)) return '';
        $t = @file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        return implode("\n", array_slice($t, -$lignes));
    }

    /** Un libellé dans la langue de la personne, sans toucher à celle de la page. */
    private static function m(string $cle, string $lang, ...$args): string
    {
        $s = I18n::ta($cle, self::langue($lang));
        return $args ? vsprintf($s, $args) : $s;
    }

    /**
     * Le message qui porte une clé d'entrée. Vrai si le courriel est parti.
     *
     * Rend faux sans rien dire de plus pour une adresse invalide ou un compte
     * désactivé : c'est l'appelant qui décide quoi montrer, et il montre la
     * même chose dans tous les cas, pour ne pas révéler qui a un compte.
     */
    public static function cleEnvoyer(array $c): bool
    {
        $email = trim((string)($c['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($c['active'])) return false;

        /* lienPour et non lienNouveau : cette page est publique, et n'importe
           qui peut y écrire l'adresse d'un autre. Réutiliser la clé vivante fait
           que demander une clé ne détruit jamais celle de quelqu'un. */
        try { $jeton = MemberAuth::lienPour((int)$c['id'], self::CLE_MINUTES); }
        catch (Throwable $ex) { return false; }

        $lang = self::langue((string)($c['lang'] ?? ''));
        $lien = MemberAuth::lienUrl($jeton);
        $nom  = trim((string)($c['name'] ?? ''));
        $nom  = $nom !== '' ? explode(' ', $nom)[0] : '';

        $sujet = self::m('cle_sujet', $lang);
        $corps = '<div style="font-size:15px;line-height:1.7;color:#111;">'
               . '<p style="margin:0 0 16px;">'
               . e($nom !== '' ? self::m('cle_bonjour', $lang, $nom) : self::m('cle_bonjour_sans', $lang))
               . '</p>'
               . '<p style="margin:0 0 8px;">' . e(self::m('cle_1', $lang)) . '</p>'
               . Mailer::bouton($lien, self::m('inv_bouton', $lang))
               . '<p style="margin:0 0 26px;text-align:center;font-size:13px;line-height:1.6;color:#777;">'
               . e(self::m('cle_2', $lang, self::CLE_MINUTES)) . '</p>'
               . '<p style="margin:0;font-size:13px;color:#777;">' . e(self::m('cle_3', $lang)) . '</p>'
               . '</div>';

        return Mailer::send([$email], $sujet, Mailer::wrap($sujet, $corps));
    }

    /** La personne active qui porte cette adresse, ou null. */
    public static function parAdresse(string $email): ?array
    {
        $email = trim(mb_strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        return DB::one('SELECT * FROM collaborators WHERE email = ? AND active = 1', [$email]) ?: null;
    }

    /**
     * Ce qui empêcherait TOUT envoi, dit avant d'y toucher.
     *
     * Il n'en reste qu'un : aucun serveur SMTP réglé et pas de mail() sur cet
     * hébergement, auquel cas rien ne peut partir, quoi qu'on fasse. Tout le
     * reste — mot de passe faux, serveur qui boude, adresse refusée — se
     * découvre à l'envoi, se lit dans le compte rendu personne par personne, et
     * se rattrape en renvoyant. Renvoie '' quand la voie est libre.
     */
    public static function obstacle(): string
    {
        $hote = trim((string)setting('smtp_host', ''));
        if ($hote === '' && !function_exists('mail')) {
            return ta('inv_err_nosmtp');
        }
        /* [14.08.2026] LA VÉRIFICATION PRÉALABLE EST RETIRÉE, et il faut dire
           pourquoi, parce qu'elle a été ajoutée hier avec de bonnes raisons.

           Elle appelait Smtp::verifie(), qui ouvre une connexion, fait STARTTLS
           et AUTH sans rien envoyer. Sa justification était entière : tant que
           l'invitation portait une clé, un envoi qui échouait avait DÉJÀ refait
           la clé de la personne, et l'ancienne était morte. Soixante-dix-sept
           messages refusés, c'était soixante-dix-sept accès cassés.

           Cette justification est morte hier soir, quand l'invitation a cessé
           de porter une clé. Un envoi qui échoue ne laisse plus rien derrière
           lui : rien n'est écrit en base, et l'on réessaie.

           CE QU'ELLE COÛTAIT, EN REVANCHE, EST BIEN RÉEL. Chaque envoi ouvrait
           DEUX connexions à Gmail au lieu d'une, et Gmail compte les connexions.
           Et surtout, quand elle se trompait, elle bloquait TOUT : le 14.08 au
           matin, en plein envoi, elle a refusé un lot en annonçant « le serveur
           refuse la connexion » alors que le serveur avait répondu 250, qui est
           un code de succès, et que les envois précédents étaient partis.

           Un contrôle qui ne protège plus rien et qui peut interdire ce qui
           marche n'est pas un filet, c'est un obstacle. Le compte rendu par
           personne, lui, reste : il donne la raison exacte de chaque échec, et
           l'on renvoie autant de fois qu'il le faut. */
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

        /* [14.08.2026] L'INVITATION NE PORTE PLUS DE CLÉ.

           Elle en portait une, à usage unique et sans expiration, fabriquée
           ici même. Comme lienNouveau() écrase la précédente, réexpédier à
           soixante-dix-sept personnes annulait les clés de celles qui avaient
           déjà reçu la leur : un envoi de trop ne coûtait pas un message, il en
           coûtait soixante-dix-sept, et toute la prudence de l'écran d'envoi
           venait de là.

           Le bouton mène maintenant à la porte, où la personne demande sa clé
           elle-même. L'envoi devient répétable sans dégât, ce qui vaut mieux
           qu'un clic de moins. La clé manuelle existe toujours, à la demande,
           depuis la fiche de la personne : c'est le dépannage de celle qui ne
           reçoit rien.

           Effet de bord assumé : le freinage de la porte compte 8 demandes par
           dix minutes et par adresse IP, pas par adresse mail. Une personne
           chez elle n'y touche jamais ; plusieurs derrière un même réseau, dans
           un théâtre, partagent le compteur. */
        $lien = MemberAuth::porteUrl();
        $sujet = self::rendreTexte(self::sujet($lang), $nom, $lien);
        $html  = Mailer::wrap($sujet, self::rendreHtml(self::texte($lang), $nom, $lien, $lang));

        if (Mailer::send([$email], $sujet, $html)) { $r['ok'] = true; return $r; }

        // Un échec ne laisse plus rien derrière lui : rien n'a été écrit en
        // base, il n'y a donc rien à rattraper ni à nettoyer. On réessaie, tout
        // simplement, et autant de fois qu'il le faut.
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
        /* [14.08.2026] La même adresse que l'envoi réel, et non plus une fausse
           clé de soixante-quatre zéros. Un exemple qui ne mène pas où mène le
           vrai message ne prouve rien : c'est justement le bouton qu'on vient
           vérifier en s'envoyant l'exemple, et il doit pouvoir être cliqué. */
        $lien  = MemberAuth::porteUrl();
        $sujet = ta('inv_test_prefix') . ' ' . self::rendreTexte(self::sujet($lang), $nom, $lien);
        $html  = Mailer::wrap($sujet, self::rendreHtml(self::texte($lang), $nom, $lien, $lang));

        if (Mailer::send([$email], $sujet, $html)) { $r['ok'] = true; return $r; }
        $r['raison'] = trim(Smtp::$erreur) !== '' ? trim(Smtp::$erreur) : ta('inv_err_send');
        return $r;
    }
}

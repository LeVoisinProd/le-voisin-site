<?php
/**
 * Les avis qui accompagnent un document.                     [V36-FACTURES]
 *
 * Un espace personnel où l'on dépose sans que personne ne soit prévenu n'est
 * pas un espace, c'est une boîte aux lettres qu'il faut penser à relever. Le
 * dépôt et le changement de statut sont donc dits par courriel, des deux
 * côtés, et toujours à un seul destinataire à la fois :
 *
 *   — la personne dépose sa facture → l'ASSOCIATION concernée est prévenue,
 *     à sa propre adresse, celle qui reçoit déjà ses justificatifs ;
 *   — le bureau la marque payée → LA PERSONNE est prévenue, dans sa langue ;
 *   — la personne confirme avoir reçu → le bureau est prévenu, ce qui referme
 *     la boucle : sans cela, « Payée » resterait le dernier mot connu ;
 *   — le bureau dépose des documents → LA PERSONNE est prévenue, une fois
 *     pour tout l'envoi. Déposer douze fiches de salaire d'un coup ne doit
 *     pas produire douze courriels.
 *
 * Un envoi qui échoue n'annule jamais ce qui vient d'être fait : le document
 * est déposé, le statut est posé, et l'avis manque. L'inverse — refuser le
 * dépôt parce que le serveur de courrier boude — perdrait le travail de
 * quelqu'un pour une panne qui ne le concerne pas.
 *
 * Les textes sont dans app/i18n/admin.fr.php et admin.en.php, clefs « mn_… ».
 */
class MemberNotify
{
    /**
     * L'adresse à prévenir pour un document donné.
     *
     * Celle de l'association s'il y en a une — c'est déjà elle qui reçoit les
     * justificatifs du formulaire public, et une facture ne se traite pas
     * ailleurs que la dépense qu'elle accompagne. Sinon l'adresse générale du
     * site. Si les deux manquent, rien ne part : mieux vaut un avis manquant
     * qu'un avis envoyé à une adresse inventée.
     */
    public static function adresseBureau(string $assoc): string
    {
        $assoc = trim($assoc);
        if ($assoc !== '') {
            $a = trim((string)(Settings::pairs('form_assoc_options')[$assoc] ?? ''));
            if (filter_var($a, FILTER_VALIDATE_EMAIL)) return $a;
        }
        $g = trim((string)setting('contact_email', ''));
        return filter_var($g, FILTER_VALIDATE_EMAIL) ? $g : '';
    }

    /**
     * Vers quel onglet renvoyer, pour un lot de documents.
     *
     * Les liens de ce fichier pointaient tous vers `#partie-contrats`, écrit à
     * la main. C'était juste quand l'espace n'avait qu'une liste ; depuis la
     * séparation en quatre onglets, une facture payée renvoyait la personne
     * vers l'onglet des contrats, où elle ne la trouve pas.
     *
     * Un lot mélangé n'a pas d'onglet commun : on renvoie alors vers l'espace,
     * sans ancre, plutôt que vers un onglet qui n'en contient qu'une partie.
     */
    private static function ancreDocs(array $docs): string
    {
        $volets = [];
        foreach ($docs as $d) $volets[MemberDocs::volet((string)($d['category'] ?? ''))] = true;
        return count($volets) === 1 ? MemberDocs::ancre((string)array_key_first($volets)) : '';
    }

    /** Un libellé traduit dans la langue voulue, sans toucher à celle de la page. */
    private static function m(string $cle, string $lang, ...$args): string
    {
        $s = I18n::ta($cle, Invitations::langue($lang));
        return $args ? vsprintf($s, $args) : $s;
    }

    /** Un paragraphe, dans le style sobre des autres messages du site. */
    private static function p(string $texte): string
    {
        return '<p style="font-size:15px;line-height:1.65;margin:0 0 14px;">' . e($texte) . '</p>';
    }

    /** Le bouton-lien de fin de message, écrit en toutes lettres pour rester copiable. */
    private static function lien(string $url, string $libelle): string
    {
        return '<p style="font-size:15px;line-height:1.65;margin:18px 0 0;">'
             . e($libelle) . '<br><a href="' . e($url) . '" style="color:#111;word-break:break-all;">'
             . e($url) . '</a></p>';
    }

    /**
     * Le nom sous lequel on s'adresse à quelqu'un.
     *
     * Son adresse à défaut de son nom : « Bonjour , » serait pire qu'un
     * courriel un peu sec, et un compte sans nom, cela arrive le jour de sa
     * création.
     */
    private static function nomPersonne(array $c): string
    {
        return trim((string)($c['name'] ?? '')) ?: trim((string)($c['email'] ?? ''));
    }

    /** Le nom lisible d'un document. */
    private static function nomDoc(array $doc): string
    {
        $t = trim((string)($doc['title'] ?? ''));
        return $t !== '' ? $t : trim((string)($doc['filename'] ?? ''));
    }

    /**
     * Une facture vient d'être déposée par la personne. → l'association.
     *
     * Le message est en français : il va au bureau, dont la langue de travail
     * est celle de l'administration.
     */
    public static function factureDeposee(array $c, array $doc, string $montant = '',
                                          string $devise = '', string $periode = ''): bool
    {
        $lang   = I18n::ADMIN_DEFAULT;
        $assoc  = trim((string)($doc['assoc'] ?? ''));
        $nom    = trim((string)($c['name'] ?? '')) ?: (string)($c['email'] ?? '');
        $projet = self::titreProjet($doc, $lang);

        /* ---- L'objet, mot pour mot celui du formulaire public ----
           Le bureau reçoit les deux dans la même boîte. Deux objets différents
           pour le même événement obligent à connaître par quelle porte la
           personne est passée avant de pouvoir trier, ce qui est précisément
           l'information dont on se moque. */
        $sujet = '[' . ($assoc !== '' ? $assoc : setting('site_name', 'Le Voisin')) . '] '
               . 'Facture / note de frais';
        if ($nom !== '')     $sujet .= ' — ' . $nom;
        if ($montant !== '') $sujet .= ' — ' . $montant . ' ' . $devise;

        /* ---- Le corps, dans l'ordre du formulaire public ---- */
        $fichier = trim((string)($doc['filename'] ?? ''));
        $corps = self::tableauFacture($c, $doc, $montant, $devise, $assoc, $projet, $nom, $periode, 'fr')
               . self::lien(url('/admin/collaborator-edit.php?id=' . (int)($c['id'] ?? 0)),
                            self::m('mn_dep_go', $lang));

        /* ---- LA PIÈCE VOYAGE AVEC L'AVIS ----
           Elle ne le faisait pas, et c'est ce qui vidait toute la chaîne : la
           boîte de dépôt de Bexio se nourrit du PDF joint au message. Un avis
           qui ne portait qu'un lien vers le CMS lui donnait un courriel et zéro
           document, et la comptabilité ne voyait jamais rien arriver. */
        $pj = [];
        $chemin = MemberDocs::dir((int)($doc['id'] ?? 0)) . '/' . $fichier;
        if ($fichier !== '' && is_file($chemin)) {
            $pj[] = ['path' => $chemin, 'name' => $fichier];
        } else {
            self::journal("Dépôt {$doc['id']} : fichier introuvable, avis envoyé sans pièce jointe");
        }

        $replyTo = filter_var((string)($c['email'] ?? ''), FILTER_VALIDATE_EMAIL)
                 ? (string)$c['email'] : null;

        /* ---- DEUX DESTINATAIRES, comme le formulaire public ----
           Avant, l'avis partait à la seule adresse de l'association, qui est la
           boîte de dépôt de Bexio : personne ne le lisait. Le bureau n'était
           donc jamais prévenu qu'une facture venait d'arriver, et l'apprenait
           en ouvrant le CMS par hasard. */
        $bureau = Settings::emails('form_expenses_to');
        if (!$bureau) {
            $g = trim((string)setting('contact_email', ''));
            if (filter_var($g, FILTER_VALIDATE_EMAIL)) $bureau = [$g];
        }

        $ok = false;
        if ($bureau) $ok = Mailer::send($bureau, $sujet, Mailer::wrap($sujet, $corps), $pj, $replyTo);

        [$compta, $note] = Forms::adresseComptable($assoc);
        if ($note !== '') self::journal("Dépôt depuis l'espace : $note");
        if ($compta && $compta !== $bureau) {
            Mailer::send($compta, $sujet, Mailer::wrap($sujet, $corps), $pj, $replyTo);
        }

        /* ---- ET LA PERSONNE REÇOIT LE MÊME MESSAGE ----          [13.08.2026]
           Elle recevait avant un accusé de trois lignes, sans pièce jointe et
           sans le détail. Or c'est elle qui, dans six mois, devra prouver ce
           qu'elle a envoyé et quand : un accusé qui ne contient pas la pièce
           ne prouve rien, et l'oblige à revenir sur le site pour vérifier ce
           qu'elle a écrit. Le tableau est le même, dans SA langue — le sujet,
           lui, ne bouge pas, pour que les trois exemplaires du message se
           retrouvent d'un seul coup dans n'importe quelle boîte. */
        $sien = trim((string)($c['email'] ?? ''));
        if (filter_var($sien, FILTER_VALIDATE_EMAIL)) {
            $sl = Invitations::langue((string)($c['lang'] ?? ''));
            $sonCorps = self::tableauFacture($c, $doc, $montant, $devise, $assoc,
                                             self::titreProjet($doc, $sl), $nom, $periode, $sl)
                      . self::lien(url('/espace/' . self::ancreDocs([$doc])), self::m('mn_go', $sl));
            Mailer::send([$sien], $sujet, Mailer::wrap($sujet, $sonCorps), $pj);
        }

        return $ok;
    }

    /**
     * Le tableau d'une facture déposée, dans une langue ou dans l'autre.
     *
     * Écrit une fois pour trois destinataires — le bureau, la comptabilité et
     * la personne — parce que trois versions d'un même tableau divergent au
     * premier ajout de ligne, et que c'est toujours celle qu'on ne relit pas
     * qui garde l'ancienne.
     *
     * Les libellés reprennent MOT POUR MOT ceux du formulaire public : le
     * bureau reçoit les deux dans la même boîte, et deux vocabulaires pour les
     * mêmes champs obligent à traduire de tête à chaque tri.
     */
    private static function tableauFacture(array $c, array $doc, string $montant, string $devise,
                                           string $assoc, string $projet, string $nom,
                                           string $periode, string $lang): string
    {
        $fr = $lang !== 'en';
        $fichier = trim((string)($doc['filename'] ?? ''));
        $taille  = (int)($doc['size'] ?? 0);
        $vide    = $fr ? 'non renseigné' : 'not provided';

        $lignes = [
            ['__sec', $fr ? 'Dépense' : 'Expense'],
            [$fr ? 'De quoi s’agit-il ?' : 'What is it?',
                   MemberDocs::catLabel((string)($doc['category'] ?? ''), $fr ? 'fr' : 'en')],
            [$fr ? 'Association'   : 'Association',   $assoc],
            [$fr ? 'Projet + lieu' : 'Project + place', $projet],
            [$fr ? 'Montant'       : 'Amount',        $montant !== '' ? $montant . ' ' . $devise : ''],
            /* La période, et pas la date du dépôt : une facture de juillet
               déposée en août se range en juillet. */
            [$fr ? 'Période'       : 'Period',        $periode],
            [$fr ? 'Justificatif'  : 'Receipt',
                   $fichier . ($taille > 0 ? ' (' . Docs::human($taille) . ')' : '')],
            ['__sec', $fr ? 'Contact' : 'Contact'],
            [$fr ? 'Nom Prénom' : 'Full name', $nom],
            [$fr ? 'E-mail'     : 'E-mail',    trim((string)($c['email'] ?? ''))],
            ['__sec', $fr ? 'Envoi' : 'Submission'],
            [$fr ? 'Date et heure' : 'Date and time', date($fr ? 'd.m.Y \à H\hi' : 'd.m.Y, H:i')],
            /* Cette ligne n'existe pas dans le formulaire public, et c'est
               justement pourquoi elle est utile : elle dit par quelle porte la
               pièce est entrée. Une personne qui a un compte n'a pas eu à
               retaper son IBAN — il est dans sa fiche, chiffré, et c'est là
               qu'on va le chercher plutôt que dans un courriel. */
            [$fr ? 'Déposé depuis' : 'Sent from',
             $fr ? 'l’espace collaborateur' : 'the collaborator area'],
        ];

        $out = '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
        foreach ($lignes as [$label, $valeur]) {
            if ($label === '__sec') {
                $out .= '<tr><td colspan="2" style="padding:14px 8px 4px;font-weight:bold;'
                      . 'text-transform:uppercase;font-size:12px;letter-spacing:.08em;'
                      . 'border-bottom:1px solid #ddd;">' . e($valeur) . '</td></tr>';
                continue;
            }
            $cellule = trim((string)$valeur) === ''
                ? '<td style="padding:6px 8px;color:#999;font-style:italic;">' . e($vide) . '</td>'
                : '<td style="padding:6px 8px;font-weight:600;">' . e($valeur) . '</td>';
            $out .= '<tr><td style="padding:6px 8px;color:#555;vertical-align:top;width:45%;">'
                  . e($label) . '</td>' . $cellule . '</tr>';
        }
        return $out . '</table>';
    }

    /** Le même journal que celui des formulaires, pour ne pas en avoir deux. */
    private static function journal(string $ligne): void
    {
        $dir = LV_APP . '/logs';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/mail.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $ligne . "\n", FILE_APPEND);
    }

    /** Le titre du projet d'un document, ou '' s'il n'en a pas. */
    private static function titreProjet(array $doc, string $lang): string
    {
        $pid = (int)($doc['project_id'] ?? 0);
        if ($pid <= 0) return '';
        /* projetTitres() : le courriel NOMME le projet d'un document, il ne le
           propose pas. Avec la liste des seuls projets en cours, une facture
           rattachée à une tournée finie arrivait sans son projet. */
        return (string)(MemberDocs::projetTitres(Invitations::langue($lang))[$pid] ?? '');
    }

    /**
     * Le bureau a marqué une facture payée. → la personne, dans sa langue.
     */
    public static function facturePayee(array $c, array $doc): bool
    {
        $to = trim((string)($c['email'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

        $lang  = Invitations::langue((string)($c['lang'] ?? ''));
        $sujet = self::m('mn_paid_s', $lang);

        $corps  = self::p(self::m('mn_paid_1', $lang, self::nomPersonne($c)));
        $corps .= self::p(self::m('mn_paid_2', $lang, self::nomDoc($doc)));
        $corps .= self::p(self::m('mn_paid_3', $lang));
        $corps .= self::lien(url('/espace/' . self::ancreDocs([$doc])), self::m('mn_go', $lang));

        return Mailer::send([$to], $sujet, Mailer::wrap($sujet, $corps));
    }

    /**
     * La personne a confirmé une réception. → le bureau.
     *
     * Deux cas, un seul message : elle a reçu son paiement, ou elle a bien
     * reçu un document qu'on lui avait déposé. Le bureau a besoin de savoir
     * l'un comme l'autre, et c'est la même bonne nouvelle — c'est arrivé.
     */
    public static function receptionConfirmee(array $c, array $doc): bool
    {
        $to = self::adresseBureau((string)($doc['assoc'] ?? ''));
        if ($to === '') return false;

        $lang  = I18n::ADMIN_DEFAULT;
        $nom   = trim((string)($c['name'] ?? '')) ?: (string)($c['email'] ?? '');
        $quoi  = MemberDocs::parLaPersonne($doc) ? 'paiement' : 'document';
        $sujet = self::m($quoi === 'paiement' ? 'mn_rec_s' : 'mn_ack_s', $lang, $nom);

        $corps  = self::p(self::m($quoi === 'paiement' ? 'mn_rec_1' : 'mn_ack_1', $lang, $nom));
        $corps .= self::p(self::m('mn_dep_2', $lang, self::nomDoc($doc)));
        $corps .= self::lien(url('/admin/collaborator-edit.php?id=' . (int)($c['id'] ?? 0)),
                             self::m('mn_dep_go', $lang));

        return Mailer::send([$to], $sujet, Mailer::wrap($sujet, $corps));
    }

    /**
     * La personne a modifié sa fiche. → le bureau.       [13.08.2026]
     *
     * L'invitation annonce que le bureau est prévenu de ce qui change. Ceci
     * tient la promesse, et surtout : la première saisie est l'événement que le
     * bureau attend depuis des semaines, celui après lequel un contrat peut
     * enfin s'établir. Elle a donc son propre sujet.
     *
     * CE MESSAGE NE PORTE AUCUNE VALEUR, ni ancienne ni nouvelle. Il porte les
     * NOMS des cases qui ont changé, et l'adresse de la fiche. Trois raisons,
     * dans l'ordre d'importance :
     *
     *   — l'IBAN et le numéro AVS sont chiffrés dans la base. Les envoyer en
     *     clair par courriel, dans une boîte qui les gardera pour toujours,
     *     annulerait tout le travail du chiffrement ;
     *   — une liste de cases interdites pourrit. Le jour où quelqu'un ajoute un
     *     champ au formulaire, il fuit par défaut, et personne ne s'en aperçoit.
     *     Ne rien porter du tout ne peut pas pourrir ;
     *   — c'est suffisant. « Adresse et Téléphone ont changé, voici la fiche »
     *     répond à la question ; les vraies valeurs se lisent dans le CMS,
     *     déchiffrées, derrière la connexion de l'administration.
     *
     * @param string[] $champs Les libellés, déjà traduits, des cases changées.
     */
    public static function ficheModifiee(array $c, array $champs, bool $premiere = false): bool
    {
        if (!$champs && !$premiere) return false;
        $to = trim((string)(Settings::emails('form_infos_to')[0] ?? ''));
        if ($to === '') $to = self::adresseBureau('');
        if ($to === '') return false;

        $lang  = I18n::ADMIN_DEFAULT;
        $nom   = trim((string)($c['name'] ?? '')) ?: (string)($c['email'] ?? '');
        $sujet = self::m($premiere ? 'mn_fic_s1' : 'mn_fic_s', $lang, $nom);

        $corps  = self::p(self::m($premiere ? 'mn_fic_1a' : 'mn_fic_1b', $lang, $nom));
        if ($champs) {
            $corps .= self::p(self::m('mn_fic_2', $lang));
            $corps .= '<ul style="font-size:15px;line-height:1.65;margin:0 0 14px;padding-left:20px;">';
            foreach ($champs as $ch) $corps .= '<li>' . e($ch) . '</li>';
            $corps .= '</ul>';
        }
        $corps .= self::p(self::m('mn_fic_3', $lang));
        $corps .= self::lien(url('/admin/collaborator-edit.php?id=' . (int)($c['id'] ?? 0)),
                             self::m('mn_dep_go', $lang));

        return Mailer::send([$to], $sujet, Mailer::wrap($sujet, $corps));
    }

    /**
     * La personne vient de déposer. → elle, en accusé de réception.
     *
     * [13.08.2026] C'était le seul des quatre sens qui manquait. Un dépôt
     * prévenait l'association et laissait la personne sans rien : elle avait
     * cliqué, un message vert s'affichait, et il n'en restait aucune trace hors
     * de l'écran qu'elle venait de quitter.
     *
     * Ce que ce message apporte n'est pas la politesse, c'est la preuve. Sans
     * elle, qui doute d'avoir bien envoyé renvoie le même document, et le
     * bureau reçoit des doublons qu'il doit distinguer à la main. Le message
     * porte donc ce qui sert de preuve : quoi, et quand.
     *
     * Un seul courriel pour tout le lot, comme pour les dépôts du bureau.
     *
     * @param array<int, array<string, mixed>> $docs
     */
    public static function depotConfirme(array $c, array $docs): bool
    {
        $to = trim((string)($c['email'] ?? ''));
        if (!$docs || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

        $lang  = Invitations::langue((string)($c['lang'] ?? ''));
        $n     = count($docs);
        $quand = date('d.m.Y, H:i');
        $sujet = $n === 1 ? self::m('mn_conf_s1', $lang) : self::m('mn_conf_sn', $lang, $n);

        $corps  = self::p(self::m('mn_conf_1', $lang, self::nomPersonne($c)));
        $corps .= self::p($n === 1 ? self::m('mn_conf_2a', $lang, $quand)
                                   : self::m('mn_conf_2b', $lang, $n, $quand));
        $corps .= '<ul style="font-size:15px;line-height:1.65;margin:0 0 14px;padding-left:20px;">';
        foreach ($docs as $d) {
            $corps .= '<li>' . e(self::nomDoc($d))
                    . ' <span style="color:#666;">('
                    . e(MemberDocs::catLabel((string)($d['category'] ?? ''), $lang))
                    . ')</span></li>';
        }
        $corps .= '</ul>';
        $corps .= self::p(self::m('mn_conf_3', $lang));
        $corps .= self::lien(url('/espace/' . self::ancreDocs($docs)), self::m('mn_go', $lang));

        return Mailer::send([$to], $sujet, Mailer::wrap($sujet, $corps));
    }

    /**
     * Le bureau vient de déposer un ou plusieurs documents. → la personne.
     *
     * Un seul message pour tout l'envoi : la fiche du collaborateur accepte
     * plusieurs fichiers d'un coup, et c'est ainsi qu'on dépose les fiches de
     * salaire du mois. Douze courriels pour douze fiches se lisent comme une
     * panne, pas comme une attention.
     *
     * @param array<int, array<string, mixed>> $docs
     */
    public static function documentsDeposes(array $c, array $docs): bool
    {
        $to = trim((string)($c['email'] ?? ''));
        if (!$docs || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

        $lang  = Invitations::langue((string)($c['lang'] ?? ''));
        $n     = count($docs);
        $sujet = $n === 1 ? self::m('mn_new_s1', $lang) : self::m('mn_new_sn', $lang, $n);

        $corps  = self::p(self::m('mn_new_1', $lang, self::nomPersonne($c)));
        $corps .= self::p($n === 1 ? self::m('mn_new_2a', $lang) : self::m('mn_new_2b', $lang, $n));
        $corps .= '<ul style="font-size:15px;line-height:1.65;margin:0 0 14px;padding-left:20px;">';
        foreach ($docs as $d) {
            $corps .= '<li>' . e(self::nomDoc($d))
                    . ' <span style="color:#666;">— '
                    . e(MemberDocs::catLabel((string)$d['category'], Invitations::langue($lang)))
                    . '</span></li>';
        }
        $corps .= '</ul>';
        $corps .= self::p(self::m('mn_new_3', $lang));
        $corps .= self::lien(url('/espace/' . self::ancreDocs($docs)), self::m('mn_go', $lang));

        return Mailer::send([$to], $sujet, Mailer::wrap($sujet, $corps));
    }
}

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
    public static function factureDeposee(array $c, array $doc): bool
    {
        $to = self::adresseBureau((string)($doc['assoc'] ?? ''));
        if ($to === '') return false;

        $lang = I18n::ADMIN_DEFAULT;
        $nom  = trim((string)($c['name'] ?? '')) ?: (string)($c['email'] ?? '');
        $sujet = self::m('mn_dep_s', $lang, $nom);

        $corps  = self::p(self::m('mn_dep_1', $lang, $nom));
        $corps .= self::p(self::m('mn_dep_2', $lang, self::nomDoc($doc)));
        $assoc = trim((string)($doc['assoc'] ?? ''));
        if ($assoc !== '') $corps .= self::p(self::m('mn_dep_3', $lang, $assoc));
        $projet = self::titreProjet($doc, $lang);
        if ($projet !== '') $corps .= self::p(self::m('mn_dep_4', $lang, $projet));
        $corps .= self::lien(url('/admin/collaborator-edit.php?id=' . (int)($c['id'] ?? 0)),
                             self::m('mn_dep_go', $lang));

        return Mailer::send([$to], $sujet, Mailer::wrap($sujet, $corps), [],
                            filter_var((string)($c['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string)$c['email'] : null);
    }

    /** Le titre du projet d'un document, ou '' s'il n'en a pas. */
    private static function titreProjet(array $doc, string $lang): string
    {
        $pid = (int)($doc['project_id'] ?? 0);
        if ($pid <= 0) return '';
        return (string)(MemberDocs::projetChoix(Invitations::langue($lang))[$pid] ?? '');
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

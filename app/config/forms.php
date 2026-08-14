<?php
/**
 * Définition des formulaires (mêmes champs que le site actuel).   [V7-ENVOI]
 *
 * Chaque champ : label/help bilingues, type, obligatoire, options…
 * types : text, email, tel, date, number, textarea, select, yesno, file, section
 * options dynamiques : 'source' => 'artists' (module Artistes), 'assoc' (réglages CMS)
 *   ou 'assoc_artists' (les associations des réglages avec leur direction
 *   artistique, « Encontro / Louis Matute »)
 * 'show_if' => ['champ', 'valeur'] : champ affiché seulement si condition remplie.
 */
return [

    'form_infos' => [
        'name'      => ['en' => 'Personal information', 'fr' => 'Infos personnelles'],
        'to_key'    => 'form_infos_to',
        'subject'   => 'Infos personnelles',
        'confirm'   => true,   // copie de confirmation à la personne
        'fields'    => [
            ['key' => 'sec_id', 'type' => 'section', 'label' => ['en' => 'Identity & contact', 'fr' => 'Identité & contact']],
            ['key' => 'full_name', 'type' => 'text', 'required' => true,
             'label' => ['en' => 'Full name', 'fr' => 'Nom et prénom'],
             'help'  => ['en' => 'As written on your identity document. This is the one that goes on contracts and payslips.',
                         'fr' => 'Tel qu\'il figure sur ta pièce d\'identité. C\'est celui-là qui va sur les contrats et les fiches de salaire.']],
            /* [14.08.2026] LE NOM D'USAGE MANQUAIT, et beaucoup de personnes en
               ont un très différent du nom légal. Sans cette case, le seul nom
               connu du site était celui du contrat, et c'est sous celui-là qu'on
               annonçait quelqu'un dans un programme ou un dossier.

               Deux noms parce que deux usages, et l'un ne remplace pas l'autre :
               la loi veut le nom de la pièce d'identité, le métier veut celui
               sous lequel la personne travaille et sous lequel le public la
               connaît.

               Facultatif, parce que la plupart n'en ont qu'un : réclamer une
               réponse à qui n'en a pas l'oblige à écrire deux fois la même
               chose, et une case obligatoire remplie pour s'en débarrasser ne
               vaut rien. */
            ['key' => 'stage_name', 'type' => 'text',
             'label' => ['en' => 'Stage name or preferred name',
                         'fr' => 'Nom artistique ou nom d\'usage'],
             'help'  => ['en' => 'Optional. The name you work under, and the one we announce you with: programmes, applications, website. Leave it empty if it is the same as above.',
                         'fr' => 'Facultatif. Le nom sous lequel tu travailles, et sous lequel nous t\'annonçons : programmes, dossiers, site. À laisser vide s\'il est le même que ci-dessus.']],
            ['key' => 'pronoun', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Pronoun', 'fr' => 'Pronom']],
            ['key' => 'email', 'type' => 'email', 'required' => true, 'label' => ['en' => 'E-mail', 'fr' => 'E-mail']],
            ['key' => 'phone', 'type' => 'tel', 'required' => true, 'label' => ['en' => 'Phone', 'fr' => 'Téléphone']],

            ['key' => 'sec_addr', 'type' => 'section', 'label' => ['en' => 'Address', 'fr' => 'Adresse']],
            ['key' => 'street', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Street', 'fr' => 'Rue']],
            ['key' => 'city', 'type' => 'text', 'required' => true, 'label' => ['en' => 'City', 'fr' => 'Ville']],
            ['key' => 'zip', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Postal code', 'fr' => 'Code postal']],
            ['key' => 'country', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Country', 'fr' => 'Pays']],

            ['key' => 'sec_civil', 'type' => 'section', 'label' => ['en' => 'Civil status', 'fr' => 'État civil']],
            ['key' => 'nationality', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Nationality', 'fr' => 'Nationalité']],
            ['key' => 'birth_place', 'type' => 'text', 'required' => true,
             'label' => ['en' => 'Place of birth (city and region)', 'fr' => 'Lieu de naissance (ville et département)']],
            ['key' => 'birth_date', 'type' => 'date', 'required' => true, 'label' => ['en' => 'Date of birth', 'fr' => 'Date de naissance']],
            ['key' => 'civil_status', 'type' => 'select', 'required' => true,
             'label' => ['en' => 'Civil status', 'fr' => 'Situation civile'],
             'options' => [
                 ['en' => 'Single', 'fr' => 'Célibataire'],
                 ['en' => 'Married', 'fr' => 'Marié·e'],
                 ['en' => 'Registered partnership', 'fr' => 'Partenariat enregistré'],
                 ['en' => 'Divorced', 'fr' => 'Divorcé·e'],
                 ['en' => 'Widowed', 'fr' => 'Veuf·ve'],
             ]],
            /* [14.08.2026] LE LIBELLÉ EST AUSSI UNE VALEUR, et c'est le piège de
               ce fichier. Les options n'ont pas de code interne : ce qui part
               dans le formulaire, ce qui se compare ici, et ce qui s'enregistre
               dans la fiche, c'est le libellé lui-même. Passer « Marié(e) » au
               point médian sans toucher cette ligne aurait fait disparaître la
               date de mariage sans une seule erreur : la condition ne serait
               plus jamais remplie, et personne ne l'aurait vu avant le
               bouclement.

               Les DEUX écritures y figurent, l'ancienne et la nouvelle. Les
               fiches déjà remplies portent « Marié(e) » en base ; les retirer
               d'ici ferait disparaître leur date de mariage à la relecture. */
            ['key' => 'marriage_date', 'type' => 'date', 'required' => true,
             'show_if' => ['civil_status', ['Married', 'Marié·e', 'Marié(e)',
                                            'Registered partnership', 'Partenariat enregistré']],
             'label' => ['en' => 'Date of marriage / partnership', 'fr' => 'Date de mariage / partenariat']],

            ['key' => 'sec_docs', 'type' => 'section', 'label' => ['en' => 'Documents', 'fr' => 'Documents']],
            ['key' => 'passport', 'type' => 'file', 'required' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'doc_cat' => 'identity',
             'rename' => ['from' => 'full_name', 'suffix' => 'passeport'],
             'label' => ['en' => 'Passport / ID (PDF or image, max. 5 MB)', 'fr' => 'Passeport / carte d\'identité (PDF ou image, max. 5 Mo)']],
            ['key' => 'has_permit', 'type' => 'yesno', 'required' => true,
             'label' => ['en' => 'Do you hold a Swiss residence permit?', 'fr' => 'Avez-vous un permis de séjour suisse ?']],
            ['key' => 'residence_permit', 'type' => 'file', 'required' => true, 'show_if' => ['has_permit', ['yes']], 'doc_cat' => 'identity',
             'accept' => '.pdf,.jpg,.jpeg,.png',
             'rename' => ['from' => 'full_name', 'suffix' => 'permis'],
             'label' => ['en' => 'Residence permit (max. 5 MB)', 'fr' => 'Permis de séjour (max. 5 Mo)']],

            ['key' => 'sec_pro', 'type' => 'section', 'label' => ['en' => 'Professional information', 'fr' => 'Informations professionnelles']],
            /* [13.08.2026] Salarié·e ou indépendant·e, et ce que cela entraîne.

               La question décide de tout ce qui suit : un·e indépendant·e n'est
               pas payé·e par une fiche de salaire mais sur facture, et la loi
               suisse demande l'attestation de son caisse AVS pour l'année en
               cours. Sans elle, l'association qui le paie risque d'être
               requalifiée en employeur et de devoir les charges rétroactivement.
               D'où l'attestation OBLIGATOIRE dès que la réponse est
               « indépendant·e », et invisible sinon.
               Le show_if compare la valeur envoyée, qui est le libellé dans la
               langue de la personne : les deux langues doivent donc y figurer. */
            ['key' => 'statut_pro', 'type' => 'select', 'required' => true,
             'label' => ['en' => 'Status', 'fr' => 'Statut'],
             'options' => [
                 ['en' => 'Employee', 'fr' => 'Salarié·e'],
                 ['en' => 'Self-employed', 'fr' => 'Indépendant·e'],
             ]],
            ['key' => 'attestation_independant', 'type' => 'file', 'required' => true, 'doc_cat' => 'attestation',
             'show_if' => ['statut_pro', ['Indépendant·e', 'Self-employed']],
             'accept' => '.pdf',
             'rename' => ['from' => 'full_name', 'suffix' => 'AttestationIndependant'],
             'label' => ['en' => 'Certificate of self-employed status for the current year (PDF)',
                         'fr' => 'Attestation d\'indépendant·e de l\'année en cours (PDF)']],
            ['key' => 'profession', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Profession', 'fr' => 'Profession']],
            ['key' => 'avs_number', 'type' => 'text', 'required' => true,
             'label' => ['en' => 'AVS / social security number', 'fr' => 'Numéro AVS / Sécurité sociale']],
            ['key' => 'health_insurance', 'type' => 'text', 'required' => true,
             'label' => ['en' => 'Health insurance', 'fr' => 'Assurance maladie']],
            ['key' => 'conges_spectacles', 'type' => 'text',
             'label' => ['en' => 'Congés Spectacles number (if applicable)', 'fr' => 'Numéro Congés Spectacles (si applicable)']],
            ['key' => 'tax_at_source', 'type' => 'yesno', 'required' => true,
             'label' => ['en' => 'Taxed at source?', 'fr' => 'Impôt à la source ?']],
            ['key' => 'unemployed', 'type' => 'yesno', 'required' => true,
             'label' => ['en' => 'Registered as unemployed?', 'fr' => 'Inscrit·e au chômage ?']],

            ['key' => 'sec_pay', 'type' => 'section', 'label' => ['en' => 'Payment', 'fr' => 'Paiement']],
            ['key' => 'iban', 'type' => 'text', 'required' => true, 'label' => ['en' => 'IBAN', 'fr' => 'IBAN']],
            ['key' => 'bic', 'type' => 'text', 'label' => ['en' => 'BIC', 'fr' => 'BIC']],

            ['key' => 'sec_transport', 'type' => 'section', 'label' => ['en' => 'Transport', 'fr' => 'Transports']],
            ['key' => 'discount_cards', 'type' => 'yesno',
             'label' => ['en' => 'Discount / travel cards?', 'fr' => 'Cartes de réduction ?']],
            ['key' => 'card_1', 'type' => 'text', 'show_if' => ['discount_cards', ['yes']],
             'label' => ['en' => 'Card no. 1', 'fr' => 'N° carte 1']],
            ['key' => 'card_2', 'type' => 'text', 'show_if' => ['discount_cards', ['yes']],
             'label' => ['en' => 'Card no. 2', 'fr' => 'N° carte 2']],

            /* [V17-CHOIX] « Pour travailler avec » : la question méritait sa
               propre rubrique. Rangée sous « Divers », elle avait l'air d'un
               détail alors qu'elle dit avec quelle équipe la personne
               travaille — c'est ce qui rattache la fiche à un projet.
               Le titre s'écrit en minuscules : la feuille de style met les
               titres de rubrique en capitales toute seule. */
            ['key' => 'sec_work', 'type' => 'section', 'label' => ['en' => 'Working with', 'fr' => 'Pour travailler avec']],
            /* [14.08.2026] Plusieurs réponses, parce que c'est la réalité : la
               plupart des gens d'ici travaillent avec deux ou trois associations,
               et le menu à choix unique les obligeait à en désigner une et à
               taire les autres. Le bureau lisait ensuite une fiche qui disait
               moins que ce qu'il savait déjà. */
            ['key' => 'artist', 'type' => 'multi', 'required' => true, 'source' => 'assoc_artists',
             'label' => ['en' => 'Association / associated artist', 'fr' => 'Association / Artiste associé·e'],
             'help'  => ['en' => 'Tick every one you work with. Several answers are possible.',
                         'fr' => 'Coche toutes celles avec lesquelles tu travailles. Plusieurs réponses possibles.']],

            ['key' => 'sec_misc', 'type' => 'section', 'label' => ['en' => 'Other', 'fr' => 'Divers']],
            ['key' => 'comments', 'type' => 'textarea', 'label' => ['en' => 'Comments', 'fr' => 'Commentaires']],
        ],
        'notice' => [
            'en' => 'The information collected will never be shared for advertising purposes.',
            'fr' => 'Les informations collectées ne seront en aucun cas partagées à des fins publicitaires.',
        ],
    ],

    'form_expenses' => [
        'name'       => ['en' => 'Invoices / expense receipts', 'fr' => 'Factures / justificatifs de dépenses'],
        'to_key'     => 'form_expenses_to',
        // Copie comptable : elle part dans la boîte de dépôt de l'association
        // choisie (réglage « Associations », lignes « Nom | adresse »).
        // « bexio_key » ne sert plus que d'adresse de secours, quand une
        // association n'a pas encore la sienne.
        'bexio_key'  => 'form_expenses_bexio',
        'assoc_key'  => 'association',
        'subject'    => 'Facture / note de frais',
        'confirm'    => true,   // copie de confirmation à la personne
        'fields'     => [
            ['key' => 'sec_exp', 'type' => 'section', 'label' => ['en' => 'Expense', 'fr' => 'Dépense']],
            /* [13.08.2026] Le formulaire s'appelait déjà « Factures /
               justificatifs de dépenses » et ne demandait jamais lequel des
               deux. Une facture se doit, un justificatif se rembourse : le
               bureau devait ouvrir la pièce pour le savoir, alors que la
               personne qui l'envoie, elle, le sait. */
            ['key' => 'doc_genre', 'type' => 'select', 'required' => true,
             'label' => ['en' => 'What is it?', 'fr' => 'De quoi s’agit-il ?'],
             'options' => [
                 ['en' => 'An invoice, which we owe you', 'fr' => 'Une facture, que nous vous devons'],
                 ['en' => 'An expense receipt, to reimburse you', 'fr' => 'Un justificatif de dépense, à vous rembourser'],
             ]],
            ['key' => 'association', 'type' => 'select', 'required' => true, 'source' => 'assoc',
             'label' => ['en' => 'Association', 'fr' => 'Association']],
            ['key' => 'project_place', 'type' => 'text', 'required' => true,
             'label' => ['en' => 'Project + place', 'fr' => 'Projet + lieu']],
            ['key' => 'work_stage', 'type' => 'select', 'required' => true,
             'label' => ['en' => 'Work stage', 'fr' => 'Étape de travail'],
             'options' => [
                 ['en' => 'Creation', 'fr' => 'Création'],
                 ['en' => 'Touring', 'fr' => 'Tournée'],
                 ['en' => 'Administration', 'fr' => 'Administration'],
             ]],
            // [V31-DEPENSES] Les huit catégories de dépense, dans l'ordre voulu
            // par Anna. Elles suivent le déroulé d'une tournée : on se déplace,
            // on mange, on dort, puis viennent le matériel et les costumes, la
            // scénographie, et enfin les deux catégories de bureau.
            //
            // « Matériel technique » ne détaille plus son contenu : la liste
            // entre parenthèses (câbles, adaptateurs…) rallongeait le menu sans
            // rien clarifier, et laissait croire que seul ce qui y figurait
            // était accepté.
            //
            // Ces libellés servent aussi à nommer les justificatifs envoyés
            // (voir « rename » plus bas) : les fichiers reçus à partir de
            // maintenant porteront ces nouveaux mots, les anciens gardent les
            // leurs.
            ['key' => 'expense_type', 'type' => 'select', 'required' => true,
             'label' => ['en' => 'Type of expense', 'fr' => 'Type de dépense'],
             'options' => [
                 ['en' => 'Transport',           'fr' => 'Transport'],
                 ['en' => 'Meals',               'fr' => 'Repas'],
                 ['en' => 'Accommodation',       'fr' => 'Logement'],
                 ['en' => 'Technical material',  'fr' => 'Matériel technique'],
                 ['en' => 'Costumes / make-up',  'fr' => 'Costumes / maquillage'],
                 ['en' => 'Set design',          'fr' => 'Scénographie'],
                 ['en' => 'Production expenses', 'fr' => 'Frais de production'],
                 ['en' => 'Administration',      'fr' => 'Administration'],
             ]],
            ['key' => 'amount', 'type' => 'number', 'required' => true, 'label' => ['en' => 'Amount', 'fr' => 'Montant']],
            ['key' => 'currency', 'type' => 'select', 'required' => true,
             'label' => ['en' => 'Currency', 'fr' => 'Devise'],
             'options' => [['en' => 'CHF', 'fr' => 'CHF'], ['en' => 'EUR', 'fr' => 'EUR']]],
            // PNG accepté en plus du PDF et du JPG : une capture d'écran de billet
            // de train ou de confirmation de commande est presque toujours un PNG,
            // et le formulaire la refusait jusqu'ici.
            // UN SEUL justificatif par envoi.
            //
            // Les champs montant, devise et catégorie décrivent un document
            // précis. Autoriser plusieurs fichiers revenait à leur attribuer à
            // tous le même montant et la même catégorie : la nomenclature
            // devenait fausse et la comptabilité recevait des lots impossibles
            // à ventiler. Une dépense = un envoi.
            ['key' => 'receipt', 'type' => 'file', 'required' => true, 'wide' => true, 'accept' => '.pdf,.jpg,.jpeg,.png',
             /* [13.08.2026] L'association entre dans le nom, en troisième position.
                Le bureau tient treize associations et les justificatifs de toutes
                arrivent dans les mêmes dossiers : sans elle, le nom ne dit pas qui
                paie. Même ordre que le dépôt depuis l'espace, pour qu'un fichier
                se lise pareil d'où qu'il vienne. */
             'rename' => ['template' => ['amount', 'currency', 'association', 'expense_type', 'project_place', 'full_name']],
             'label' => ['en' => 'Receipt — one single file (PDF, JPG or PNG, max. 5 MB)', 'fr' => 'Justificatif — un seul fichier (PDF, JPG ou PNG, max. 5 Mo)'],
             'help'  => ['en' => 'One file for this expense. Several receipts of the SAME category and the SAME currency may be gathered in a single PDF. As soon as the category or the currency changes, it is a separate submission — after sending, a button lets you start again with your details already filled in. No need to rename your file: the site does it for you.',
                         'fr' => 'Un seul fichier pour cette dépense. Plusieurs quittances de la MÊME catégorie et de la MÊME devise peuvent être réunies dans un seul PDF. Dès que la catégorie ou la devise change, c\'est un envoi séparé — après l\'envoi, un bouton vous permet de recommencer avec vos coordonnées déjà remplies. Inutile de renommer votre fichier : le site s\'en charge.']],

            ['key' => 'sec_contact', 'type' => 'section', 'label' => ['en' => 'Contact & payment', 'fr' => 'Contact & paiement']],
            ['key' => 'full_name', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Full name', 'fr' => 'Nom Prénom']],
            ['key' => 'email', 'type' => 'email', 'required' => true, 'label' => ['en' => 'E-mail', 'fr' => 'E-mail']],
            ['key' => 'phone', 'type' => 'tel', 'required' => true, 'label' => ['en' => 'Phone', 'fr' => 'Téléphone']],
            ['key' => 'iban', 'type' => 'text', 'required' => true, 'label' => ['en' => 'IBAN', 'fr' => 'IBAN']],
            // BIC facultatif, comme sur l'ancien formulaire. Pour un IBAN suisse
            // ou européen le BIC ne sert plus à rien depuis des années, et
            // beaucoup de gens ne l'ont tout simplement pas sous la main : le
            // rendre obligatoire bloquait des envois pour une donnée inutile.
            ['key' => 'bic', 'type' => 'text', 'label' => ['en' => 'BIC', 'fr' => 'BIC'],
             'help' => ['en' => 'Optional. Only needed for a bank outside Europe.',
                        'fr' => 'Facultatif. Utile seulement pour une banque hors d\'Europe.']],
            ['key' => 'street', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Street', 'fr' => 'Rue']],
            ['key' => 'city', 'type' => 'text', 'required' => true, 'label' => ['en' => 'City', 'fr' => 'Ville']],
            ['key' => 'zip', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Postal code', 'fr' => 'Code postal']],
            ['key' => 'country', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Country', 'fr' => 'Pays']],
            ['key' => 'observations', 'type' => 'textarea', 'label' => ['en' => 'Observations', 'fr' => 'Observations']],
        ],
        // Champs repris d'un envoi au suivant quand la personne clique
        // « Envoyer un autre justificatif ». On garde ce qui ne change pas
        // dans une même série (identité, paiement, projet) et on remet à zéro
        // ce qui décrit la dépense elle-même : type, montant, devise,
        // document, observations. C'est ce qui rend supportable la règle
        // « une dépense = un envoi ».
        'repeat' => ['association', 'project_place', 'work_stage',
                     'full_name', 'email', 'phone', 'iban', 'bic',
                     'street', 'city', 'zip', 'country'],
        'notice' => [
            'en' => 'All payments and reimbursements are processed at the end of the month during which the documents were sent. Thank you for your cooperation!',
            'fr' => 'Tous les paiements et remboursements sont traités à la fin du mois au cours duquel les documents ont été envoyés. Merci de votre coopération !',
        ],
    ],
];

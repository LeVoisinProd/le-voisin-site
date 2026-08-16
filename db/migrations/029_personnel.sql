-- Le personnel: les personnes employées et leurs engagements. [16.08.2026]
--
-- Demandé par Anna, écrans à l'appui: Employé·e·s, Contrats, Salaires, AGI,
-- Feuilles de temps, Équipe & Accès.
--
-- POURQUOI DEUX TABLES NEUVES ALORS QUE LE SITE CONNAÎT DÉJÀ CES GENS. Il en
-- connaît une partie, et pas celle-là. `collaborators` (49) porte le compte —
-- e-mail, mot de passe, dernière connexion — et `member_profiles` (42) porte la
-- personne — adresse, nationalité, AVS, IBAN, chiffrés. Aucun des deux ne porte
-- L'EMPLOI: quelle association l'engage, à quelle fonction, à quel tarif, sous
-- quel type de contrat. C'est exactement ce que le dashboard tient à part dans
-- `lv-rh-employees` (89 lignes) et `lv-rh-engagements` (72).
--
-- ET ON NE FUSIONNE PAS. `collaborators` est un compte: il donne accès à
-- l'espace personnel. `rh_employe` est un emploi: il peut exister sans compte —
-- 89 personnes contre 49 comptes, et l'écart n'est pas une erreur, c'est le
-- personnel de tournée qui n'ouvre jamais l'espace. Exiger un compte pour
-- pouvoir payer quelqu'un serait le genre de règle qui fait ressaisir dans un
-- tableur à côté.
--
-- `collaborator_id` RELIE QUAND LE LIEN EXISTE, et reste vide sinon. C'est ce
-- qui permettra plus tard de lire l'AVS et l'IBAN depuis la fiche chiffrée sans
-- les recopier ici — Anna: « depois vamos ligar esta parte com o cms ».
--
-- CE QUI N'EST PAS RECOPIÉ, ET C'EST DÉLIBÉRÉ: l'AVS et l'IBAN. Ils vivent dans
-- `member_profiles`, chiffrés depuis ce matin. Les remettre ici en clair
-- déferait en une migration le travail de la journée. Les colonnes existent
-- pour les personnes SANS fiche — le dashboard en porte — et elles sont
-- chiffrées de la même façon.

CREATE TABLE IF NOT EXISTS rh_employe (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_ref      VARCHAR(32)  NULL COMMENT 'l id du dashboard, pour une reprise sans doublon',
    collaborator_id INT UNSIGNED NULL COMMENT 'la fiche du CMS, quand la personne en a une',

    prenom          VARCHAR(96)  NULL,
    nom             VARCHAR(96)  NOT NULL,
    pronom          VARCHAR(32)  NULL,
    email           VARCHAR(190) NULL,
    telephone       VARCHAR(48)  NULL,

    -- Qui l'engage, à quoi, et sous quelle forme.
    asso_ref        VARCHAR(32)  NULL COMMENT 'la clef de l association cote dashboard',
    organisation_id INT UNSIGNED NULL,
    fonction        VARCHAR(190) NULL,
    -- Texte libre: le dashboard porte « CDD », « CDI », « mandat », « stage »
    -- et d'autres qui arrivent. Un enum demanderait une migration par nouveauté.
    type_engagement VARCHAR(60)  NULL,

    paie_mensuelle  DECIMAL(10,2) NULL,
    paie_horaire    DECIMAL(10,2) NULL,
    devise          CHAR(3) NOT NULL DEFAULT 'CHF',

    -- Identité. `avs` et `iban` sont CHIFFRÉS (préfixe sb1:), comme les fiches.
    naissance       DATE NULL,
    nationalite     VARCHAR(96)  NULL,
    permis          VARCHAR(60)  NULL,
    rue             VARCHAR(190) NULL,
    cp              VARCHAR(20)  NULL,
    ville           VARCHAR(96)  NULL,
    pays            VARCHAR(64)  NULL,
    avs             VARCHAR(255) NULL COMMENT 'chiffre',
    iban            VARCHAR(255) NULL COMMENT 'chiffre',

    notes           TEXT NULL,
    actif           TINYINT(1) NOT NULL DEFAULT 1,
    cree_le         DATETIME NOT NULL DEFAULT current_timestamp(),
    modifie_le      DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    supprime_le     DATETIME NULL,

    PRIMARY KEY (id),
    UNIQUE KEY u_ref (source_ref),
    KEY k_nom (nom, prenom),
    KEY k_asso (asso_ref),
    KEY k_collab (collaborator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un engagement: une personne, une association, un projet, une période.
--
-- C'EST LA LIGNE QUI DEVIENT UN CONTRAT ET UNE FICHE DE SALAIRE. Les deux
-- écrans qu'Anna montre — Contrats et Salaires — lisent la même table par deux
-- bouts: l'un par personne et par période, l'autre groupé par association et
-- par mois. Deux tables auraient divergé au premier changement d'heures.
CREATE TABLE IF NOT EXISTS rh_engagement (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_ref      VARCHAR(32)  NULL,

    employe_id      INT UNSIGNED NULL,
    -- Le nom est gardé en clair à côté de la clef: la reprise du dashboard
    -- porte 70 empId sur 72 lignes, et les deux sans identifiant doivent
    -- pouvoir s'afficher quand même.
    employe_nom     VARCHAR(190) NULL,

    asso_ref        VARCHAR(32)  NULL,
    organisation_id INT UNSIGNED NULL,
    projet          VARCHAR(190) NULL,

    debut           DATE NULL,
    fin             DATE NULL,
    mois            VARCHAR(7)   NULL COMMENT 'AAAA-MM, pour le groupement des salaires',

    jours           DECIMAL(6,2) NULL,
    heures          DECIMAL(7,2) NULL,
    duree_jours     DECIMAL(6,2) NULL,

    paie_mensuelle  DECIMAL(10,2) NULL,
    paie_horaire    DECIMAL(10,2) NULL,
    devise          CHAR(3) NOT NULL DEFAULT 'CHF',

    -- « à éditer », « édité », « signé », « envoyé »… relevés tels quels.
    statut          VARCHAR(40)  NULL,

    notes           TEXT NULL,
    cree_le         DATETIME NOT NULL DEFAULT current_timestamp(),
    modifie_le      DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    supprime_le     DATETIME NULL,

    PRIMARY KEY (id),
    UNIQUE KEY u_ref (source_ref),
    KEY k_emp (employe_id),
    KEY k_mois (mois),
    KEY k_asso (asso_ref),
    KEY k_debut (debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

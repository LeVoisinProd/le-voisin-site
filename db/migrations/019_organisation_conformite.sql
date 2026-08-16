-- La conformité suisse d'une association, et ce qu'il faut pour la tenir.
-- [16.08.2026]
--
-- La table `organisation` couvrait l'onglet Infos et rien d'autre. Les quatre
-- autres onglets du dashboard — LAA/LPP/AMPG, AVS, Impôt Source, Impôt Direct
-- — n'avaient aucune colonne. C'est pourtant la seule capacité qu'aucun des
-- dix-sept logiciels du marché ne couvre, et l'écran qu'Alessandra ouvre tous
-- les mois.
--
-- ── LES DEUX MOTS DE PASSE SONT CHIFFRÉS ────────────────────────────────
--
-- `email_mdp` et `instagram_mdp` existent dans le dashboard actuel EN CLAIR.
-- Ici ils passent par app/lib/Crypto.php, le même qui protège déjà les IBAN et
-- les AVS des fiches personnelles, et pour la même raison: le 16.08.2026 on a
-- mesuré qu'un dump de cette base se lit sans clé, et l'on en produit un par
-- jour qui part dans le Drive.
--
-- La longueur tient compte du chiffrement: « sb1: » plus le base64 du nonce,
-- du texte et du sceau. 255 laisse de la marge pour un mot de passe long.
--
-- Ce n'est pas la bonne solution, c'est la moins mauvaise. La bonne est un
-- gestionnaire de mots de passe, où vivent déjà ceux du FTP et du SSH. Mais
-- ces deux-là sont dans le dashboard aujourd'hui, et les faire disparaître
-- sans prévenir casserait le travail de quelqu'un.

ALTER TABLE organisation
    -- Infos
    ADD COLUMN forme_juridique  VARCHAR(80)  NULL AFTER genre,
    ADD COLUMN date_creation    DATE         NULL AFTER forme_juridique,
    ADD COLUMN reference_poste  VARCHAR(60)  NULL AFTER ree,
    ADD COLUMN cp               VARCHAR(20)  NULL AFTER adresse,
    ADD COLUMN ville            VARCHAR(96)  NULL AFTER cp,

    -- Contact de l'association
    ADD COLUMN contact_prenom   VARCHAR(96)  NULL AFTER email,
    ADD COLUMN contact_nom      VARCHAR(96)  NULL AFTER contact_prenom,
    ADD COLUMN email_mdp        VARCHAR(255) NULL COMMENT 'chiffré par Crypto.php' AFTER contact_nom,
    ADD COLUMN instagram_mdp    VARCHAR(255) NULL COMMENT 'chiffré par Crypto.php' AFTER instagram,

    -- LAA · LPP · AMPG
    ADD COLUMN rc_pro           VARCHAR(120) NULL,
    ADD COLUMN rc_police        VARCHAR(80)  NULL,
    ADD COLUMN laa              ENUM('non','souscrite','en_cours') NOT NULL DEFAULT 'non',
    ADD COLUMN lpp              ENUM('non','oui','en_cours')       NOT NULL DEFAULT 'non',
    ADD COLUMN ampg             ENUM('non','souscrite','en_cours') NOT NULL DEFAULT 'non',
    ADD COLUMN assureur_laa     VARCHAR(120) NULL,
    ADD COLUMN assureur_lpp     VARCHAR(120) NULL,
    ADD COLUMN trianon          VARCHAR(80)  NULL,

    -- AVS. `avs_employeur` existait déjà et porte le numéro d'employeur;
    -- celui-ci est le numéro d'inscription, qui n'est pas le même.
    ADD COLUMN avs_inscription  VARCHAR(40)  NULL,
    ADD COLUMN caisse_avs       VARCHAR(120) NULL,
    ADD COLUMN convention_coll  VARCHAR(120) NULL,

    -- Impôt direct, Suisse
    ADD COLUMN canton_fiscal    CHAR(2)      NULL,
    ADD COLUMN contribuable_cant VARCHAR(60) NULL,
    ADD COLUMN tva_ch           ENUM('non','oui') NOT NULL DEFAULT 'non',
    ADD COLUMN tva_ch_num       VARCHAR(40)  NULL,
    ADD COLUMN notes_fisc_ch    VARCHAR(500) NULL,

    -- Impôt direct, France
    ADD COLUMN rna              VARCHAR(40)  NULL,
    ADD COLUMN urssaf           VARCHAR(60)  NULL,
    ADD COLUMN audiens          VARCHAR(60)  NULL,
    ADD COLUMN tva_fr           ENUM('non','oui') NOT NULL DEFAULT 'non',
    ADD COLUMN tva_fr_num       VARCHAR(40)  NULL,
    ADD COLUMN notes_fisc_fr    VARCHAR(500) NULL;


-- L'impôt à la source se déclare dans CHAQUE canton de résidence des
-- employé·e·s, pas dans celui du siège. Une association qui engage quelqu'un
-- à Vaud et quelqu'un à Berne a deux comptes, et les oublier coûte une amende.
-- D'où une table et non deux colonnes.
CREATE TABLE IF NOT EXISTS organisation_is (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    organisation_id INT UNSIGNED NOT NULL,
    canton          CHAR(2)      NOT NULL,
    compte          VARCHAR(80)  NULL COMMENT 'n° de compte ou DPI attribué par le canton',
    notes           VARCHAR(300) NULL,
    cree_le         DATETIME NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY u_org_canton (organisation_id, canton)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Les grilles à cocher des onglets LAA et AVS: une case par période et par
-- année, dont on change l'état d'un clic.
--
-- UNE LIGNE N'EXISTE QUE SI L'ON A CLIQUÉ. Créer d'avance quatre lignes par an
-- et par association ferait des milliers de lignes « rien à signaler » qu'il
-- faudrait ensuite distinguer de celles qui veulent dire quelque chose.
CREATE TABLE IF NOT EXISTS organisation_declaration (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    organisation_id INT UNSIGNED NOT NULL,
    type            ENUM('laa','avs') NOT NULL,
    annee           SMALLINT UNSIGNED NOT NULL,
    periode         ENUM('T1','T2','T3','T4','annuel') NOT NULL,
    statut          ENUM('a_faire','envoye','paye','sans_objet') NOT NULL DEFAULT 'a_faire',
    note            VARCHAR(300) NULL,
    modifie_le      DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY u_decl (organisation_id, type, annee, periode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

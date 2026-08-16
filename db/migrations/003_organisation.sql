-- Associations et artistes. [16.08.2026]
--
-- UNE SEULE TABLE POUR LES DEUX, et c'est la structure qu'Anna a demandée:
-- « Associations/Artists ». Elles ne sont pas la même chose mais elles vivent au
-- même endroit du travail, et une fiche d'artiste devient parfois une
-- association quand le projet grandit. Deux tables obligeraient à déménager la
-- fiche ce jour-là, et à réécrire tout ce qui pointait dessus.
--
-- La colonne `genre` les distingue:
--   association   une entité juridique de la maison. Treize aujourd'hui, avec
--                 IDE, numéro AVS employeur, banque, comité
--   artiste       une compagnie ou une personne accompagnée. Le Voisin n'en est
--                 pas l'employeur mais lui tient l'administration
--
-- CE QU'ELLE A DEMANDÉ D'AJOUTER, et qui n'existe nulle part aujourd'hui:
-- « tout ce qui se répète entre les shows: modèles de contrat, modèles de deal,
-- devises et frais de booking par artiste ». C'est le mécanisme qui évite de
-- ressaisir les mêmes conditions à chaque date. Les modèles eux-mêmes viendront
-- dans leur table; ici vivent les valeurs par défaut qui s'appliquent seules.
--
-- LES MOTS DE PASSE NE SONT PAS ICI ET N'Y SERONT PAS. Les fiches du dashboard
-- portaient onze mots de passe de courriel et d'Instagram en clair, retirés du
-- code le 16.08.2026 et rangés dans le gestionnaire. Une base qui se copie pour
-- monter un environnement de test ne doit pas les emporter avec elle.

CREATE TABLE IF NOT EXISTS organisation (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,

    source          VARCHAR(16)      NULL,
    source_ref      VARCHAR(32)      NULL,

    genre           ENUM('association','artiste') NOT NULL DEFAULT 'artiste',
    nom             VARCHAR(190) NOT NULL,
    nom_legal       VARCHAR(190)     NULL,

    -- Identité administrative. Vide pour un artiste, remplie pour une association.
    ide             VARCHAR(32)      NULL COMMENT 'CHE-xxx.xxx.xxx',
    registre        VARCHAR(32)      NULL,
    avs_employeur   VARCHAR(32)      NULL,
    ree             VARCHAR(32)      NULL,
    siret           VARCHAR(32)      NULL COMMENT 'les entités françaises',

    -- Où elle est, et sous quel droit. Le pays décide des obligations sociales,
    -- des délais et du formulaire A1: ce n'est pas un champ décoratif.
    pays            VARCHAR(64)      NULL,
    canton          VARCHAR(64)      NULL,
    adresse         VARCHAR(255)     NULL,

    email           VARCHAR(96)      NULL,
    telephone       VARCHAR(48)      NULL,
    site            VARCHAR(190)     NULL,
    instagram       VARCHAR(190)     NULL,

    -- La banque. L'IBAN d'une association figure sur chaque devis et chaque
    -- contrat: ce n'est pas un secret, c'est ce par quoi on la paie.
    banque_nom      VARCHAR(96)      NULL,
    banque_iban     VARCHAR(48)      NULL,
    banque_bic      VARCHAR(16)      NULL,

    -- Ce qui se répète d'un show à l'autre, et qu'on ne veut plus ressaisir.
    devise_defaut   CHAR(3)      NOT NULL DEFAULT 'CHF',
    frais_booking   DECIMAL(6,3)     NULL COMMENT 'en pourcentage du cachet',
    marge_defaut    DECIMAL(6,3)     NULL COMMENT 'la marge de la maison, 10 % depuis le 14.08.2026',

    -- La relation avec la maison.
    discipline      VARCHAR(96)      NULL,
    direction       VARCHAR(190)     NULL COMMENT 'direction artistique',
    debut_collab    DATE             NULL,
    statut          ENUM('actif','pause','termine') NOT NULL DEFAULT 'actif',
    comite          TEXT             NULL,
    notes           TEXT             NULL,

    cree_le         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    supprime_le     DATETIME     NULL,

    PRIMARY KEY (id),
    UNIQUE KEY u_source (source, source_ref),
    KEY i_genre  (genre, statut, nom),
    KEY i_vivant (supprime_le, nom)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

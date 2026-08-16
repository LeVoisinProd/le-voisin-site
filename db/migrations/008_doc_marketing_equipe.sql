-- Documentation, marketing et équipe. [16.08.2026]

-- ── La docuthèque ──────────────────────────────────────────────────────────
--
-- Des liens vers le Drive, rangés par usage. Les fichiers ne bougent pas: le
-- Drive reste où il est, et c'est voulu. Le mapa du dépôt de travail le dit
-- depuis le 07.08.2026: « DOCUMENT va au Google Drive ». Copier les fichiers ici
-- ferait deux endroits où chercher, et le second serait toujours périmé.
CREATE TABLE IF NOT EXISTS document_lien (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rubrique   ENUM('guides','contrats','proddiff','fiches','compta','autre')
               NOT NULL DEFAULT 'autre',
    titre      VARCHAR(190) NOT NULL,
    url        VARCHAR(500) NOT NULL,
    description TEXT            NULL,
    ordre      SMALLINT     NOT NULL DEFAULT 0,
    cree_le    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    supprime_le DATETIME        NULL,
    PRIMARY KEY (id),
    KEY i_rub (rubrique, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Les publications ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS publication (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    date_prevue     DATE             NULL,
    plateforme      VARCHAR(48)      NULL,
    titre           VARCHAR(190) NOT NULL,
    contenu         TEXT             NULL,
    lien            VARCHAR(500)     NULL,
    organisation_id INT UNSIGNED     NULL,
    booking_id      INT UNSIGNED     NULL COMMENT 'la date qu elle annonce',
    statut          ENUM('idee','a_ecrire','prete','publiee','annulee')
                    NOT NULL DEFAULT 'idee',
    cree_le         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    supprime_le     DATETIME         NULL,
    PRIMARY KEY (id),
    KEY i_date (date_prevue, statut),
    KEY i_statut (statut),
    CONSTRAINT fk_pub_org FOREIGN KEY (organisation_id)
        REFERENCES organisation (id) ON DELETE SET NULL,
    CONSTRAINT fk_pub_booking FOREIGN KEY (booking_id)
        REFERENCES booking (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Les rôles ──────────────────────────────────────────────────────────────
--
-- LE POINT LE PLUS IMPORTANT DE CETTE MIGRATION, et il vient d'un défaut mesuré
-- le 15.08.2026: le dashboard actuel a une grille de permissions par module sur
-- la fiche de chaque personne, et RIEN NE LA LIT. Ni showSection(), ni sv(), ni
-- le serveur. C'est une étiquette. Tout le monde voit et modifie tout, salaires,
-- IBAN et AVS des 116 fiches compris.
--
-- Ici le rôle est une colonne de la table `users` du CMS, celle qui décide déjà
-- qui entre. Une grille qui vit à côté de l'authentification finit toujours par
-- s'en détacher; celle-ci ne le peut pas.
--
-- TROIS RÔLES ET PAS DOUZE. Une grille fine que personne ne tient à jour protège
-- moins qu'un rôle grossier qu'on comprend.
--   direction   tout, y compris l'argent et les fiches de salaire
--   production  les dates, les projets, les contacts. Pas les salaires
--   lecture     regarder, sans rien changer
ALTER TABLE users
    ADD COLUMN role_dash ENUM('direction','production','lecture')
        NOT NULL DEFAULT 'direction' AFTER name;

-- La couche production d'un projet. [16.08.2026]
--
-- CE QUI N'EST PAS FAIT ICI, ET POURQUOI C'EST LE POINT DE CETTE MIGRATION.
--
-- Anna, le 16.08.2026: « nous allons revoir ce qui est ici est déjà dans le cms,
-- on ne veut pas travailler en double. Aujourd'hui je mets les infos directement
-- dans le cms qui est lié au site, dans l'avenir le site devrait récupérer les
-- infos directement depuis le dashboard. »
--
-- Un spectacle se saisit aujourd'hui à TROIS endroits, mesuré le 15.08.2026:
-- `projects` du CMS, `lv-prods` et `lv-fiches` du dashboard. Créer ici une
-- quatrième table `projet` en ferait quatre. On ne le fait donc pas.
--
-- LE DASHBOARD ÉCRIT DANS `projects`, LA TABLE DU CMS. C'est la même donnée, le
-- site la lit déjà, et le sens de la dépendance qu'Anna décrit se met en place
-- tout seul: la fiche devient la source, le site devient un lecteur.
--
-- CETTE TABLE-CI NE PORTE QUE CE QUE `projects` N'A PAS: la phase de production,
-- qui en est responsable, le budget, la validation. Ce sont les colonnes de
-- lv-prods qui n'ont pas d'équivalent côté CMS. Une ligne par projet, en
-- extension et jamais en copie.
--
-- Les colonnes `start` et `end` de lv-prods ne sont pas reprises: vides sur les
-- dix-neuf projets, vérifié. Les dates vivent dans `booking`.

CREATE TABLE IF NOT EXISTS projet_prod (
    project_id      INT UNSIGNED NOT NULL COMMENT 'la clef de projects, jamais un doublon',

    -- Où en est la fabrication. Les six valeurs sont celles de lv-prods.
    phase           ENUM('dev','creation','production','promo','tournee','cloture')
                    NOT NULL DEFAULT 'dev',

    -- Qui porte le projet dans le bureau, et qui l'a validé. Dix des dix-neuf
    -- projets ont ces deux champs remplis aujourd'hui.
    responsable     VARCHAR(96)      NULL,
    valide_par      VARCHAR(96)      NULL,

    -- Le budget du projet artistique. ATTENTION AU SENS: ce n'est pas de
    -- l'argent qui passe par Le Voisin, c'est le budget porté par le projet,
    -- souvent par une autre association. Confondre les deux fait lire 874 800
    -- comme un chiffre d'affaires, et c'est faux. Le même avertissement figure
    -- dans la série financière du dossier cantonal.
    budget          DECIMAL(12,2)    NULL,
    devise          CHAR(3)      NOT NULL DEFAULT 'CHF',

    -- L'entité qui porte juridiquement le projet.
    organisation_id INT UNSIGNED     NULL,

    -- Le lieu de création, qui n'est pas une date de tournée et n'a donc pas sa
    -- place dans booking.
    lieu_creation   VARCHAR(190)     NULL,

    -- Qui fait quoi. Gardé en JSON parce que la forme varie d'un projet à
    -- l'autre et qu'une table de liaison pour dix-neuf lignes coûterait plus
    -- qu'elle ne rapporte. Le jour où l'on cherche « tous les projets dont X
    -- est responsable », il faudra la faire.
    raci            JSON             NULL,

    notes           TEXT             NULL,

    cree_le         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (project_id),
    KEY i_phase (phase),
    KEY i_resp  (responsable),

    CONSTRAINT fk_projet_prod_project FOREIGN KEY (project_id)
        REFERENCES projects (id) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

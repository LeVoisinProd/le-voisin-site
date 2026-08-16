-- Les bookings: une date jouée, ou en cours de l'être. [16.08.2026]
--
-- L'OBJET CENTRAL DU DASHBOARD, et il n'existait nulle part comme donnée. Il
-- était éclaté en deux moitiés qui ne se parlent pas, mesuré le 15.08.2026:
--
--   `events` du CMS         51 lignes. Une chaîne d'affichage et une date de
--                           tri. Pas de cachet, pas de statut, pas de client
--   `lv-tour` du dashboard  35 lignes codées en dur dans 04_state.js, EN
--                           LECTURE SEULE: aucun formulaire ne permet d'en
--                           créer une. On édite la feuille Google à la main
--
-- Et la synchronisation censée les relier pousse vers /admin/api/sync.php, un
-- fichier qui n'existe ni dans le dépôt ni sur le serveur. Vérifié le 16.08:
-- le dashboard croit synchroniser depuis toujours et n'a jamais rien envoyé.
--
-- LES COLONNES SONT CELLES QU'ANNA A DEMANDÉES, dans son ordre:
--   venue, projet, date, artiste, heure, prix de cession, pays, ville,
--   statut (option, confirmed, canceled, pending), prix de vente, client
--
-- DEUX PRIX ET PAS UN, et c'est le point qui n'existe nulle part aujourd'hui.
-- Le prix de cession est ce que le lieu paie. Le prix de vente est ce qui est
-- annoncé ou négocié. Les confondre empêche de voir où l'on a cédé, et c'est
-- exactement ce que le modèle économique du 15.08 cherchait sans le trouver.

CREATE TABLE IF NOT EXISTS booking (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- L'identifiant d'origine quand la ligne vient d'ailleurs: « td035 » pour
    -- lv-tour, « ev12 » pour les events du CMS. Sert à rejouer une reprise sans
    -- créer de doublon, et à retrouver d'où vient une ligne le jour où un
    -- chiffre surprend.
    source          VARCHAR(16)      NULL,
    source_ref      VARCHAR(32)      NULL,

    -- QUOI, QUI, OÙ
    projet          VARCHAR(190)     NULL,
    artiste         VARCHAR(190)     NULL,
    venue           VARCHAR(190)     NULL,
    venue_url       VARCHAR(400)     NULL,
    ville           VARCHAR(96)      NULL,
    pays            VARCHAR(64)      NULL,

    -- QUAND. `date_debut` est la vraie date, celle sur laquelle on trie et on
    -- compte. `date_texte` est ce qu'on affiche, parce que « du 8 au 13 février »
    -- ne se dérive pas proprement de deux dates et que le site l'écrit à la main.
    date_debut      DATE             NULL,
    date_fin        DATE             NULL,
    date_texte      VARCHAR(190)     NULL,
    heure           TIME             NULL,

    -- COMBIEN. DECIMAL et jamais FLOAT: un cachet est de l'argent, et un FLOAT
    -- rend 1234.5599999 là où il faut 1234.56.
    prix_cession    DECIMAL(10,2)    NULL,
    prix_vente      DECIMAL(10,2)    NULL,
    devise          CHAR(3)          NOT NULL DEFAULT 'CHF',
    client          VARCHAR(190)     NULL,

    -- OÙ ÇA EN EST. Les quatre valeurs d'Anna, écrites telles quelles.
    statut          ENUM('option','confirmed','canceled','pending')
                    NOT NULL DEFAULT 'pending',

    -- Combien de représentations ce jour-là. Compte pour le prix: une deuxième
    -- représentation le même jour vaut 1,5 jour de salaire et non 2, décidé le
    -- 15.08.2026 après qu'un modèle antérieur ait fait sortir huit
    -- représentations en quatre jours au prix de quatre.
    representations SMALLINT UNSIGNED NOT NULL DEFAULT 1,

    -- DEUX NATURES DE NOTES, et la distinction est le point, pas un détail.
    -- Anna: « les notes artiste, visibles par l'artiste, et les notes internes,
    -- réservées à votre équipe ». Une seule colonne obligerait à se relire avant
    -- chaque partage, ce que personne ne fait.
    notes_artiste   TEXT             NULL,
    notes_internes  TEXT             NULL,

    cree_le         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
    supprime_le     DATETIME     NULL,

    PRIMARY KEY (id),
    UNIQUE KEY u_source (source, source_ref),

    -- L'écran par défaut trie par date décroissante en filtrant les supprimés:
    -- cet index sert exactement cette requête-là.
    KEY i_agenda  (supprime_le, date_debut),
    KEY i_statut  (statut, date_debut),
    KEY i_projet  (projet),
    KEY i_artiste (artiste)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

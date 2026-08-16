-- Le carnet d'adresses de la diffusion. [16.08.2026]
--
-- POURQUOI CETTE TABLE EXISTE. Les 7 841 contacts vivaient jusqu'ici dans
-- src/js/modules/10_annuaire_trombinoscope.js du dépôt du dashboard: 2,23 Mo de
-- code, soit 44 % de tout le JavaScript du projet, chargés dans le navigateur à
-- chaque ouverture avant qu'on puisse chercher quoi que ce soit. La recherche
-- se faisait ensuite en mémoire, sur l'ensemble, à chaque frappe.
--
-- CE QUE LE PROFIL DES DONNÉES A MONTRÉ, et qui explique les choix ci-dessous.
-- Mesuré le 16.08.2026 sur les 7 841 fiches:
--
--   pronom, instagram, linkedin   0 fiche remplie sur 7 841. Colonnes mortes,
--                                 elles ne sont pas reprises
--   adresse2                      3 fiches. Fusionnée dans adresse
--   nom                           183 caractères au plus long
--   notes                         797 caractères au plus long
--   date_notes                    478 caractères, 75 fiches
--
-- LES LONGUEURS SONT CALÉES SUR LE MESURÉ, PAS SUR L'HABITUDE. Un VARCHAR(255)
-- partout est une façon de ne pas décider. Les tailles ci-dessous laissent de la
-- marge sur ce qui a été observé, et rien de plus.

CREATE TABLE IF NOT EXISTS contact (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    -- L'identifiant d'origine, « c001 » à « c7841 ». On le garde parce que
    -- lv-relances et lv-crm-stages y renvoient, et que la reprise se fera
    -- dessus. Il n'est pas la clef primaire: un identifiant venu d'ailleurs ne
    -- doit jamais devenir la colonne dont tout dépend.
    ref             VARCHAR(16)     NOT NULL,

    nom             VARCHAR(200)    NOT NULL,
    prenom          VARCHAR(64)         NULL,
    nom_famille     VARCHAR(64)         NULL,
    fonction        VARCHAR(128)        NULL,

    structure       VARCHAR(160)        NULL,
    categorie       VARCHAR(48)         NULL,

    -- Deux géographies: celle de la structure et celle de la personne. Elles
    -- diffèrent sur 1 174 fiches, donc elles ne se fondent pas.
    ville_struct    VARCHAR(96)         NULL,
    pays_struct     VARCHAR(64)         NULL,
    region          VARCHAR(96)         NULL,
    adresse         VARCHAR(200)        NULL,
    cp              VARCHAR(40)         NULL,
    ville           VARCHAR(96)         NULL,
    dept            VARCHAR(24)         NULL,
    pays            VARCHAR(64)         NULL,

    email1          VARCHAR(64)         NULL,
    email2          VARCHAR(64)         NULL,
    email_pro1      VARCHAR(64)         NULL,
    tel1            VARCHAR(64)         NULL,
    tel_pro1        VARCHAR(32)         NULL,
    site            VARCHAR(160)        NULL,

    mots_cles       VARCHAR(120)        NULL,
    description     VARCHAR(96)         NULL,
    participations  VARCHAR(40)         NULL,
    photo           VARCHAR(64)         NULL,
    date_mois       VARCHAR(16)         NULL,
    date_notes      TEXT                NULL,
    notes           TEXT                NULL,

    cree_le         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    supprime_le     DATETIME            NULL,

    PRIMARY KEY (id),
    UNIQUE  KEY u_ref (ref),

    -- Les trois filtres que l'écran d'annuaire propose aujourd'hui.
    KEY i_categorie (categorie),
    KEY i_pays      (pays_struct),
    KEY i_ville     (ville_struct),

    -- La suppression est logique, comme dans le dashboard: une fiche effacée
    -- par erreur se retrouve. Cet index évite que le filtre « non supprimé »,
    -- présent sur toutes les requêtes, ait à parcourir la table.
    KEY i_vivant    (supprime_le, nom),

    -- La recherche. FULLTEXT plutôt qu'un LIKE '%mot%', qui ne peut utiliser
    -- aucun index et lit les 7 841 lignes à chaque frappe. InnoDB en MariaDB
    -- 10.11 le gère nativement, sans extension.
    --
    -- Attention en s'en servant: ft_min_word_len vaut 3 par défaut, donc les
    -- mots de deux lettres sont ignorés. Le code doit garder un LIKE en repli
    -- pour les recherches courtes, sinon chercher « GE » ne rendra rien.
    FULLTEXT KEY ft_recherche (nom, structure, ville_struct, mots_cles, notes)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

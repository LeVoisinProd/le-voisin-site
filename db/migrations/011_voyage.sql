-- La logistique de voyage d'un booking. [16.08.2026]
--
-- POURQUOI CETTE TABLE. Aujourd'hui ces informations existent, mais comme
-- FICHIERS: des catégories de documents dans l'espace collaborateur, un PDF de
-- billet, une confirmation d'hôtel en pièce jointe. On ne peut donc ni les
-- additionner, ni voir d'un coup qui voyage quand, ni rapprocher leur coût du
-- prix de cession. Un fichier ne se compte pas.
--
-- LE VOCABULAIRE DE `charge` EST CELUI DE deal_item, ET C'EST VOULU. Incluse,
-- lieu, nous: qui supporte la dépense. C'est ce qui manque le plus souvent
-- quand un prix est mal compris — un hôtel que le lieu croyait payer et que
-- nous avions inclus. Reprendre le même mot permet de lire les deux ensemble
-- au lieu d'inventer un second vocabulaire pour la même idée.
--
-- LES DATES SONT DES DATETIME et non des DATE: un vol a une heure, et c'est
-- justement l'heure qui décide si l'on arrive à temps pour le montage.

CREATE TABLE IF NOT EXISTS trip_item (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id  INT UNSIGNED NOT NULL,

    type        ENUM('vol','train','bus','voiture','transfert','hotel','autre')
                NOT NULL DEFAULT 'vol',

    -- Qui voyage. Texte libre et non un lien vers `collaborators`: une partie
    -- des personnes en tournée ne sont pas des collaborateur·rices du Voisin,
    -- et attendre qu'elles aient une fiche empêcherait de noter le billet.
    qui         VARCHAR(190) NULL,
    libelle     VARCHAR(190) NULL,

    depart      VARCHAR(190) NULL,
    arrivee     VARCHAR(190) NULL,
    date_debut  DATETIME NULL,
    date_fin    DATETIME NULL,

    -- Le numéro du billet, le PNR, la référence de la réservation d'hôtel.
    reference   VARCHAR(120) NULL,

    montant     DECIMAL(10,2) NULL,
    devise      CHAR(3) NOT NULL DEFAULT 'CHF',
    charge      ENUM('incluse','lieu','nous') NOT NULL DEFAULT 'incluse',

    notes       VARCHAR(500) NULL,
    cree_a      DATETIME NOT NULL DEFAULT current_timestamp(),

    PRIMARY KEY (id),
    KEY k_booking (booking_id),
    KEY k_debut   (date_debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

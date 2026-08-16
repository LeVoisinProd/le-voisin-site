-- Les fichiers attachés à une date. [16.08.2026]
--
-- Anna: « Glisser-déposer des fiches techniques, itinéraires, contrats sur un
-- booking. » Aujourd'hui ces pièces vivent dans des fils d'e-mails, et la
-- question « où est la fiche technique de Metz » se répond en fouillant une
-- boîte de réception.
--
-- POURQUOI UNE TABLE DE PLUS, alors que contract et advancing_field portent
-- déjà des fichiers. Parce que ceux-là ont un rôle: un contrat se signe, un
-- élément d'advancing a un état et se demande au lieu. Ici il n'y a pas de
-- rôle — c'est un plan d'accès, un itinéraire, une photo de la salle. Les
-- forcer dans contract obligerait à inventer un statut de signature pour un
-- plan de métro.
--
-- LA COLONNE `partage` REPREND LA DISTINCTION DES NOTES, et c'est le point.
-- notes_artiste et notes_internes existent déjà sur booking parce que « une
-- seule colonne obligerait à se relire avant chaque partage ». Un fichier pose
-- exactement le même problème: le plan de feu se partage avec l'artiste, la
-- grille de négociation non.

CREATE TABLE IF NOT EXISTS booking_file (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id INT UNSIGNED NOT NULL,

    -- Le nom lisible, et le nom sur le disque. Les deux, parce que le second
    -- est nettoyé — accents et espaces retirés — et qu'on veut réafficher
    -- celui que la personne a déposé.
    titre      VARCHAR(190) NOT NULL,
    fichier    VARCHAR(190) NOT NULL,
    taille     INT UNSIGNED NOT NULL DEFAULT 0,

    partage    ENUM('interne','artiste') NOT NULL DEFAULT 'interne',

    depose_par VARCHAR(190) NULL,
    cree_a     DATETIME NOT NULL DEFAULT current_timestamp(),

    PRIMARY KEY (id),
    KEY k_booking (booking_id, cree_a)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

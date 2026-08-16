-- Le pipeline en colonnes. [16.08.2026]
--
-- Demandé par Anna: « queria uma visao em pipilene assim, para eu acompanhar o
-- andamento das coisas, onde eu possa puxar um evento de uma coluna para
-- outra, escrever dentro dizendo o que tem que ser feito, mudar a coluna de
-- posicao ».
--
-- POURQUOI DEUX TABLES ET NON UN CHAMP « statut » SUR LE CONTACT. Un statut est
-- une liste fermée que le code connaît; ces colonnes-là sont écrites par Anna,
-- renommées, déplacées, supprimées. « À relancer AVRIL » n'a de sens qu'en mars
-- et disparaîtra; en faire une valeur d'enum demanderait une migration à chaque
-- saison. Le tableau appartient à qui l'utilise, pas au schéma.
--
-- LA CARTE PEUT POINTER SUR N'IMPORTE QUOI, OU SUR RIEN. Un contact à relancer,
-- une date à confirmer, une offre à répondre, un projet — ou juste une note
-- libre. Quatre clefs facultatives plutôt qu'une table par type: le tableau
-- sert à voir l'avancement de choses hétérogènes côte à côte, et forcer un type
-- unique obligerait à tenir trois tableaux qu'on ne regarde jamais ensemble.
--
-- `ordre` EST UN ENTIER ESPACÉ DE 10 à la création. Insérer une carte entre deux
-- autres se fait alors sans renuméroter la colonne entière — et une renumérotation
-- pendant qu'une autre personne glisse une carte échangerait les deux.
--
-- ON N'EFFACE PAS, ON ARCHIVE. Une carte glissée hors du tableau porte souvent
-- la seule trace d'un échange («relancé en mars, sans réponse»). `archive_le`
-- la retire de la vue sans la détruire.

CREATE TABLE IF NOT EXISTS kanban_colonne (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titre      VARCHAR(120) NOT NULL,
    ordre      INT NOT NULL DEFAULT 0,
    -- Une teinte parmi celles de l'écran, pas une valeur libre: un tableau où
    -- chaque colonne a sa couleur choisie au hasard cesse d'être lisible.
    couleur    VARCHAR(16) NULL COMMENT 'neutre, jaune, orange, vert, rouge',
    cree_le    DATETIME NOT NULL DEFAULT current_timestamp(),
    archive_le DATETIME NULL,
    PRIMARY KEY (id),
    KEY k_ordre (ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kanban_carte (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    colonne_id INT UNSIGNED NOT NULL,
    ordre      INT NOT NULL DEFAULT 0,

    titre      VARCHAR(190) NOT NULL,
    -- Ce qu'il faut faire. C'est le champ que demande Anna: « escrever dentro
    -- dizendo o que tem que ser feito ».
    note       TEXT NULL,
    echeance   DATE NULL,

    -- Sur quoi la carte porte. Toutes facultatives, jamais plus d'une remplie
    -- en pratique — mais rien ne l'interdit, et l'écran affiche ce qu'il trouve.
    contact_id INT UNSIGNED NULL,
    booking_id INT UNSIGNED NULL,
    offer_id   INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,

    cree_le    DATETIME NOT NULL DEFAULT current_timestamp(),
    modifie_le DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    archive_le DATETIME NULL,

    PRIMARY KEY (id),
    KEY k_col (colonne_id, ordre),
    KEY k_contact (contact_id),
    KEY k_booking (booking_id),
    KEY k_echeance (echeance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les colonnes de départ, relevées sur le tableau qu'Anna utilise aujourd'hui.
-- `INSERT ... SELECT` avec un garde: rejouer la migration ne les redouble pas,
-- et surtout ne les fait pas revenir si elles ont été supprimées à l'écran.
INSERT INTO kanban_colonne (titre, ordre, couleur)
SELECT * FROM (
    SELECT 'En cours'      AS t, 10 AS o, 'neutre' AS c UNION ALL
    SELECT 'À relancer',      20, 'jaune'  UNION ALL
    SELECT 'En discussion',   30, 'neutre' UNION ALL
    SELECT 'Vérifier la fiche', 40, 'orange' UNION ALL
    SELECT 'Finalisé',        50, 'vert'   UNION ALL
    SELECT 'Pas intéressé',   60, 'neutre'
) AS d
WHERE NOT EXISTS (SELECT 1 FROM kanban_colonne);

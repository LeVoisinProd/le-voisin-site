-- Les coordonnées d'une date, pour la carte de l'aperçu.  [Anna, 21.08.2026]
--
-- « dans la partie événements — aperçu, on pourrait mettre une visualisation
-- avec Google Maps », « laisser avec une visibilité comme dans le print ».
--
-- POURQUOI DES COLONNES ET NON UN APPEL À CHAQUE AFFICHAGE. Un géocodage par
-- ouverture de fiche, c'est un aller-retour vers un service tiers sur le
-- chemin du rendu: la page devient aussi lente que lui, et elle casse quand il
-- tombe. Les coordonnées d'une salle ne changent pas. On les cherche une fois,
-- on les garde, et l'affichage n'appelle plus personne.
--
-- POURQUOI PAS GOOGLE, malgré la demande. L'API Maps de Google exige une clef
-- facturable, qu'il faudrait créer, poser dans les réglages et surveiller.
-- OpenStreetMap affiche la même chose sans clef ni compte. Le lien « ouvrir
-- dans Google Maps » reste à côté de la carte: qui veut l'itinéraire l'a en un
-- clic, et c'est le seul usage pour lequel Google est vraiment meilleur.
--
-- `geo_libelle` GARDE CE QUE LE SERVICE A COMPRIS, et ce n'est pas décoratif:
-- « Ecolint, Genève » peut tomber sur le bon campus ou sur un homonyme à
-- l'autre bout de la ville. En écrivant l'adresse trouvée sous la carte, une
-- erreur de géocodage se voit au lieu de se croire.
--
-- `geo_a` DIT QUAND, pour pouvoir refaire les recherches ratées plus tard sans
-- retenter à chaque ouverture ce qui vient d'échouer. Une ligne géocodée sans
-- résultat porte donc une date et des coordonnées nulles: c'est un « cherché,
-- pas trouvé », qui ne se confond pas avec « jamais cherché ».

ALTER TABLE booking
    ADD COLUMN lat DECIMAL(10,7) NULL
        COMMENT 'latitude de la salle, cherchee une fois puis gardee'
        AFTER venue_url,
    ADD COLUMN lon DECIMAL(10,7) NULL
        COMMENT 'longitude de la salle'
        AFTER lat,
    ADD COLUMN geo_libelle VARCHAR(255) NULL
        COMMENT 'adresse telle que le service l a comprise, pour verifier a l oeil'
        AFTER lon,
    ADD COLUMN geo_a DATETIME NULL
        COMMENT 'quand la recherche a eu lieu, meme si elle n a rien trouve'
        AFTER geo_libelle;

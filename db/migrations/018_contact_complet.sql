-- Les cinq colonnes qui manquaient au carnet d'adresses. [16.08.2026]
--
-- La table `contact` reprenait 28 des 33 champs du dashboard. Les cinq
-- absentes, avec ce qu'elles portent réellement dans les 8432 fiches:
--
--   pronom      236 fiches. « elle », « Mme ». Ce n'est pas un détail:
--               écrire à un programmateur en se trompant est le genre de
--               faute qui ferme une porte avant la première phrase.
--   adresse2      4 fiches, mais le formulaire l'a et l'import la connaît:
--               sans la colonne, la valeur se perdait en silence.
--   instagram     0 · linkedin  0. Vides aujourd'hui et présentes dans le
--               formulaire d'Anna. Les ajouter maintenant coûte une migration;
--               les ajouter le jour où on en saisit deux cents en coûte une
--               aussi, plus la ressaisie.
--   directions    0. « Directions artistiques liées »: quels spectacles du
--               roster peuvent intéresser ce contact. C'est le champ qui
--               transforme un carnet d'adresses en outil de diffusion — on
--               cherche à qui proposer Bestiarium, pas qui est programmateur.
--
-- LE FORMAT RESTE CELUI DU DASHBOARD, une chaîne séparée par des virgules,
-- pour `directions` comme pour `participations` et `date_mois`. Ce n'est pas
-- le plus propre, et c'est volontaire: la reprise depuis lv-contacts doit
-- rester sans perte, et une table de liaison se construira le jour où l'on
-- voudra interroger dans l'autre sens — « qui pourrait programmer Bestiarium ».

ALTER TABLE contact
    ADD COLUMN pronom     VARCHAR(40)  NULL AFTER prenom,
    ADD COLUMN adresse2   VARCHAR(190) NULL AFTER adresse,
    ADD COLUMN instagram  VARCHAR(190) NULL AFTER site,
    ADD COLUMN linkedin   VARCHAR(190) NULL AFTER instagram,
    ADD COLUMN directions VARCHAR(500) NULL
        COMMENT 'les directions artistiques du roster susceptibles de l''intéresser'
        AFTER mots_cles;

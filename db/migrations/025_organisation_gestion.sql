-- Administrer une association, ou seulement vendre ses spectacles. [16.08.2026]
--
-- LA DISTINCTION VIENT D'ANNA, le 16.08.2026: « sao artistas novos, mas que nao
-- gerenciamos a associacao, so a venda de shows ». Deux choses très différentes
-- portaient jusqu'ici le même mot « association ».
--
--   complete   Le Voisin tient l'administration: statuts, IDE, AVS, LPP, LAA,
--              impôt à la source, comptabilité, salaires. Treize associations.
--   diffusion  Le Voisin ne vend que les spectacles. Improvável Produções et
--              Tainá E I O U. Elles ont un Shared Drive et des dates, et
--              c'est tout ce que nous avons à en savoir.
--
-- POURQUOI CETTE COLONNE EXISTE PLUTÔT QU'UNE NOTE. Sans elle, les cinq onglets
-- de conformité — LAA/LPP/AMPG, AVS, impôt source, impôt direct — affichent une
-- fiche vide pour Improvável et Tainá, exactement comme pour une association
-- qu'on administre et dont personne n'a rempli les champs. LES DEUX SE
-- RESSEMBLENT À L'ÉCRAN ET NE SE RESSEMBLENT PAS DU TOUT: l'une est un travail
-- en retard, l'autre est un travail qui ne nous revient pas. Un écran qui ne
-- distingue pas les deux fabrique une liste de tâches fantôme, et une liste de
-- tâches fantôme finit par ne plus être lue du tout.
--
-- `complete` PAR DÉFAUT, et c'est le bon défaut: se tromper en croyant devoir
-- administrer fait vérifier une fiche pour rien; se tromper dans l'autre sens
-- fait manquer une déclaration.

ALTER TABLE organisation
    ADD COLUMN gestion ENUM('complete','diffusion') NOT NULL DEFAULT 'complete'
        COMMENT 'complete = Le Voisin administre; diffusion = on ne vend que les spectacles'
        AFTER genre;

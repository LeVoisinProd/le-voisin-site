-- Un travail qui ne tient dans aucun projet. [17.08.2026]
--
-- Anna: « dentro da parte projet colocar "autre" e a pessoa pode incluir nomes.
-- as vezes as pessoas fazem trabalhos de outras naturezas que nao estao ligadas
-- a projetos que ja estao acontecendo. ou administrativos ».
--
-- LE MENU N'A QUE DEUX RÉPONSES ET IL EN MANQUE UNE. Aujourd'hui on choisit un
-- spectacle en cours, ou « aucun projet ». Le deuxième est un fourre-tout: il
-- range dans le même tas une facture d'administration, une répétition pour une
-- création qui n'est pas encore au site, et un travail ponctuel qui n'a rien à
-- voir. Le bureau reçoit alors une facture « sans projet » et rouvre le PDF
-- pour savoir de quoi il s'agit — exactement ce que le genre du document et le
-- montant, demandés depuis le 13.08, servaient à éviter.
--
-- POURQUOI UNE COLONNE ET NON UN PROJET DE PLUS DANS `projects`. `projects` est
-- la table du SITE PUBLIC: y créer « administratif » ou « répétitions mai »
-- ferait des pages, des slugs et des entrées de catalogue pour des choses qui
-- ne sont pas des spectacles. Et la personne qui dépose ne doit pas pouvoir
-- écrire dans le catalogue en remplissant une note de frais.
--
-- LE CHAMP EST LIBRE, ET C'EST ASSUMÉ. On ne saura pas regrouper « admin »,
-- « administratif » et « Administration » — c'est le prix d'un champ libre, et
-- le bénéfice est plus grand: aujourd'hui cette information n'existe nulle part
-- et le bureau la reconstitue en ouvrant les pièces une par une. Le jour où une
-- nature revient assez pour mériter une case, on la lira dans ces valeurs.
--
-- `project_id` RESTE, ET LES DEUX COEXISTENT. Une facture peut porter un projet
-- du site ET une précision — « Bestiarium, résidence de novembre ». Écraser
-- l'un par l'autre perdrait le rattachement qui sert au classement.

ALTER TABLE member_documents
    ADD COLUMN projet_libre VARCHAR(190) NULL
        COMMENT 'un projet ou une nature de travail que le site ne connait pas'
        AFTER project_id;

-- D'où vient le porteur d'un projet. [16.08.2026]
--
-- CE QUI A FAILLI ÊTRE CONSTRUIT, ET POURQUOI ON L'A DÉFAIT. Anna a dicté le
-- modèle en une phrase — « cada asso pode portar varios projetos de varios
-- artistas » — et la première réponse a été de créer une table `projet`. Elle a
-- été écrite, appliquée, remplie de 32 lignes, puis supprimée dans la même
-- heure: le site MODÉLISAIT DÉJÀ EXACTEMENT CELA, sous des noms anglais que la
-- recherche avait manqués.
--
--   projects         35 pièces, bilingues, celles du catalogue public
--   artists          46 artistes
--   project_artists  la table de liaison — PLUSIEURS artistes par pièce
--   projet_prod      la fiche de production, une par pièce, avec déjà un
--                    `organisation_id` pour le porteur juridique
--
-- La recherche avait cherché `projet`, `production`, `spectacle`, `oeuvre` et
-- conclu « aucune table ne connaît les projets ». La table s'appelait
-- `projects`. La leçon vaut plus que la migration: DANS CETTE BASE LES TABLES
-- DU SITE SONT EN ANGLAIS ET CELLES DU DASHBOARD EN FRANÇAIS, et une recherche
-- qui n'interroge qu'une langue conclut à tort que rien n'existe. Lire
-- `SHOW TABLES` en entier coûte une seconde.
--
-- IL NE MANQUAIT DONC QU'UNE CHOSE, et c'est tout ce que fait cette migration:
-- garder la trace de la production du dashboard d'où vient le rattachement,
-- pour que la reprise soit rejouable sans doublon et qu'on sache, dans six
-- mois, si un porteur a été saisi à la main ou importé.
--
-- CE QUE LA REPRISE N'IMPORTERA PAS, mesuré sur les 32 avant d'écrire:
--   · 9 lignes sont des MOIS de « Dolce Vita » (« — Janvier 2026 », « — Février
--     2026 »…). Ce ne sont pas des pièces mais des périodes de tournée, et les
--     dates vivent déjà dans `booking`. Les créer dans `projects` publierait
--     onze « Dolce Vita » dans le catalogue.
--   · plusieurs lignes sont des ARTISTES et non des pièces — « Captains of the
--     Imagination », « Evita Koné » sont dans `artists`, pas dans `projects`.
-- `lv-prods` mélange les trois niveaux. C'est précisément ce que le modèle
-- d'Anna sépare, et la reprise doit trancher au lieu de recopier le mélange.

ALTER TABLE projet_prod
    ADD COLUMN source_ref VARCHAR(64) NULL
        COMMENT 'la production du dashboard qui a fourni le porteur, pour rejouer sans doublon'
        AFTER project_id;

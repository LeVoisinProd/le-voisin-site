-- Le rôle par défaut devient « lecture ». [16.08.2026]
--
-- POURQUOI UNE MIGRATION DE PLUS PLUTÔT QUE CORRIGER LA 008. Parce que la 008
-- est déjà appliquée en local, et que migrer.php signale — à raison — une
-- migration dont le fichier change après coup: le disque et la base cessent de
-- dire la même chose, et rejouer casserait davantage. Le système de migrations
-- sert exactement à cela: on ajoute, on ne réécrit pas.
--
-- POURQUOI CE CHANGEMENT. La 008 déclarait
--
--     role_dash ENUM('direction','production','lecture') NOT NULL DEFAULT 'direction'
--
-- c'est-à-dire que toute personne créée sans rôle explicite naissait avec le
-- rôle le plus puissant, celui qui voit l'Administration, les Finances et les
-- salaires. Un oubli d'INSERT donnait les pleins pouvoirs.
--
-- En contrôle d'accès, ce qui manque doit valoir MOINS, jamais plus. Une
-- personne à qui on a oublié de donner un rôle doit voir le calendrier, pas les
-- IBAN. Si elle a besoin de davantage, elle le demande et cela se voit; dans
-- l'autre sens, personne ne s'aperçoit de rien.
--
-- LES COMPTES EXISTANTS NE BOUGENT PAS. Un DEFAULT ne s'applique qu'aux lignes
-- créées ensuite. Les rôles déjà attribués restent tels quels, ce qui est
-- voulu: cette migration ne doit priver personne de son accès du jour au
-- lendemain.

ALTER TABLE users
    ALTER COLUMN role_dash SET DEFAULT 'lecture';

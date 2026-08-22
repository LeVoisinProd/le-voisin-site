-- « Ce que nous faisons » devient plusieurs choses à la fois.  [Anna, 22.08.2026]
--
-- « na parte associations infos tem o campo Ce que nous faisons: deixar um menu
-- que podemos escolher as opções (adm, prod, diff), deixar essas 3 opções de
-- escolha ». Et, à la question posée: plusieurs à la fois.
--
-- ELLE A RAISON SUR LE FOND, ET L'ANCIEN CHAMP MENTAIT PAR CONSTRUCTION. Il
-- offrait « complète » ou « diffusion seulement », donc une association qui
-- fait l'administration ET la diffusion devait choisir laquelle taire. Les
-- trois métiers sont indépendants: on peut tenir la compta d'une association
-- sans jamais vendre ses dates, et vendre les dates d'une autre sans toucher à
-- ses comptes.
--
-- UNE COLONNE `SET` ET NON TROIS BOOLÉENS. MySQL sait faire, la valeur se lit
-- telle quelle — « adm,diff » — et une quatrième activité ne demandera pas une
-- migration de plus par activité. Trois colonnes auraient aussi permis un état
-- qui ne veut rien dire: les trois à zéro.
--
-- LA REPRISE EST SANS PERTE PARCE QU'IL N'Y AVAIT RIEN À PERDRE, et c'est
-- mesuré: les 72 fiches vivantes portent toutes « complete », aucune n'a jamais
-- été passée à « diffusion ». Le champ existait sans que personne s'en serve.
-- « complete » disait « administration et comptabilité »: il devient donc
-- `adm`, et non les trois — écrire `diff` sur 72 fiches affirmerait que nous
-- vendons les dates de toutes, ce qui est faux. Anna corrigera les quelques-
-- unes qui font plus; un champ trop plein se corrige moins bien qu'un champ
-- juste.
--
-- LE BLOC BEXIO SUIT `adm`, et non plus « pas diffusion ». Question posée,
-- réponse d'Anna: qui fait l'administration est qui tient la comptabilité. Une
-- association que nous ne faisons que diffuser n'aura jamais de jeton, et un
-- écran qui continue de le réclamer apprend à ignorer ses propres alertes.

ALTER TABLE organisation
    ADD COLUMN activites SET('adm','prod','diff') NOT NULL DEFAULT ''
        COMMENT 'ce que nous faisons pour elle: administration, production, diffusion'
        AFTER gestion;

UPDATE organisation SET activites = 'adm'  WHERE gestion = 'complete';
UPDATE organisation SET activites = 'diff' WHERE gestion = 'diffusion';

-- `gestion` reste en place, sans être lue ni écrite. La retirer dans la même
-- migration que celle qui la remplace ne laisse aucun retour en arrière si la
-- reprise s'avère fausse sur une fiche. Elle se supprimera quand `activites`
-- aura vécu.

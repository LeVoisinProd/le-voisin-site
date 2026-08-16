-- Le « chez » d'une adresse d'association. [16.08.2026]
--
-- Anna: « pucar as infos de todos os enderecos e colcoar nas cadas certas, vc
-- colocu tudo em uma so ». L'adresse arrivait de la reprise en un seul bloc
-- multi-ligne et s'affichait tel quel, ce qui donnait quatre lignes empilées
-- dans une cellule prévue pour une.
--
-- LE FORMAT EST RÉGULIER, mesuré sur les douze qui en ont une:
--
--     ℅ Antonella Infantino      ← facultatif
--     Via Coremmo 13
--     6900 Lugano
--
-- Trois lignes, parfois deux. La dernière porte toujours « CP Ville ». Il y a
-- donc quatre informations et non une, et `cp` comme `ville` existaient déjà,
-- vides.
--
-- LE « ℅ » MÉRITE SA COLONNE ET N'EST PAS UN DÉTAIL. Dix associations sur douze
-- sont domiciliées chez quelqu'un — le président, la trésorière, un tiers. Ce
-- nom-là doit figurer sur une enveloppe sinon le courrier ne monte pas, et il ne
-- doit PAS figurer sur un devis, où l'on écrit l'association. Collés dans le
-- même champ, on ne peut ni l'imprimer ni le taire séparément.

ALTER TABLE organisation
    ADD COLUMN chez VARCHAR(190) NULL
        COMMENT 'le « c/o »: chez qui l association est domiciliee'
        AFTER forme_juridique;

-- ── UNE ZONE DE NOTES PAR ONGLET ───────────────────────────────────────────
--
-- Anna: « na parte associacoes em casa sous page deixar um campo para notes,
-- nenhum tem ». Les cinq onglets — Infos, LAA·LPP·AMPG, AVS, Impôt Source,
-- Impôt Direct — portaient des champs et aucune place pour écrire ce qui ne
-- rentre dans aucun d'eux.
--
-- DEUX COLONNES ET NON CINQ, parce que trois onglets en avaient déjà une et que
-- personne ne l'avait vue: `notes` sert l'onglet Infos, `notes_fisc_ch` l'Impôt
-- Source, `notes_fisc_fr` l'Impôt Direct. Seuls LAA·LPP·AMPG et AVS n'avaient
-- rien. En ajouter cinq aurait fait deux zones de notes sur trois onglets, et
-- l'on n'aurait plus su laquelle est lue.
--
-- POURQUOI PAS UNE SEULE ZONE POUR TOUTE LA FICHE: `notes` existe et sert au
-- général. Mais « la caisse AVS a changé de numéro en mars, l'ancien traîne
-- encore sur les décomptes » n'est utile QU'À CÔTÉ du numéro d'AVS. Une note
-- rangée loin de ce qu'elle explique n'est pas relue au moment où elle
-- servirait, et c'est le seul moment qui compte.

ALTER TABLE organisation
    ADD COLUMN notes_laa VARCHAR(1000) NULL
        COMMENT 'notes de l onglet LAA LPP AMPG' AFTER notes,
    ADD COLUMN notes_avs VARCHAR(1000) NULL
        COMMENT 'notes de l onglet AVS' AFTER notes_laa;

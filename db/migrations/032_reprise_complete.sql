-- Ce que la reprise du dashboard avait laissé derrière. [17.08.2026]
--
-- Anna, la veille au soir: « atualize tudo por favor, extraia o maximo de
-- informacao do dashboard do script para, os dados dos employes, os dados
-- completos dos contatos, os dados completos dos projetos e das associacoes ».
--
-- L'INVENTAIRE A ÉTÉ FAIT TABLE PAR TABLE, et il dit trois choses:
--
--   `lv-contacts`   8432 lignes, 32 champs — COMPLET, rien à reprendre. Les 32
--                   colonnes existent et portent le même nombre de valeurs des
--                   deux côtés. Mesuré champ par champ avant d'écrire ceci.
--   `lv-personnel`  93 lignes contre 89 reprises. Les 4 manquantes sont
--                   l'équipe interne — Anna, Mirta, Alessandra, Félicia — qui
--                   portent deux champs que les 89 autres n'ont pas.
--   `lv-fiches`     14 spectacles, 46 champs, JAMAIS REPRIS. C'est le trou
--                   principal: distribution, technique, calendrier, liens.
--
-- ── LES DEUX CHAMPS DE L'ÉQUIPE INTERNE ────────────────────────────────────
--
-- `role_interne` n'est pas `fonction` et les confondre ferait perdre les deux.
-- `fonction` dit ce que la personne fait SUR UN SPECTACLE — « Guitare »,
-- « Régie lumière » — et change d'un engagement à l'autre. `role_interne` dit
-- sa place AU BUREAU — « Directrice Générale », « Comptable » — et ne change
-- pas. Une même personne peut porter les deux: Anna dirige le bureau et fait
-- de la diffusion sur les dates.
--
-- `couleur` sert à la relecture, pas à la décoration. Sur un planning où six
-- personnes se croisent, la couleur est ce qui permet de suivre une ligne des
-- yeux sans lire chaque étiquette. Les quatre valeurs viennent du dashboard et
-- sont reprises telles quelles: les changer casserait l'habitude de l'œil, qui
-- est la seule chose que cette colonne sert.

ALTER TABLE rh_employe
    ADD COLUMN role_interne VARCHAR(120) NULL
        COMMENT 'la place au bureau, distincte de fonction qui est le poste sur un spectacle'
        AFTER fonction,
    ADD COLUMN couleur CHAR(7) NULL
        COMMENT 'couleur de reperage sur les plannings, reprise du dashboard'
        AFTER role_interne;

-- ── L'ÂGE CONSEILLÉ ────────────────────────────────────────────────────────
--
-- « dès 5 ans », « tout public », « dès 12 ans ». C'est la première question
-- d'un programmateur jeune public et elle figure sur toutes les fiches.
--
-- POURQUOI PAS `public_cible`, QUI EXISTE. Ce champ-là porte une catégorie —
-- « jeune public », « tout public » — sur laquelle on filtre le catalogue.
-- « dès 5 ans » n'est pas une catégorie: c'est un seuil, et deux spectacles
-- « dès 5 ans » et « dès 12 ans » sont tous deux jeune public. Mettre le seuil
-- dans la catégorie casserait le filtre du catalogue, et le filtre est ce que
-- `public_cible` sert. En prime `public_cible` fait 16 caractères et
-- « dès 12 ans, tout public » n'y rentre pas.

ALTER TABLE projects
    ADD COLUMN age_conseille VARCHAR(60) NULL
        COMMENT 'l age a partir duquel le spectacle se voit. Un seuil, pas une categorie'
        AFTER public_cible;

-- ── LA DISCIPLINE ET LE DÉBUT DE COLLABORATION ─────────────────────────────
--
-- Les deux colonnes existent déjà sur `organisation` et sont VIDES sur les 18.
-- `discipline` est affichée sur la liste des associations — Anna: « na pagina
-- Associations mostrar - nome - direction - ville, canton, discipline,
-- statut » — et une colonne demandée nommément qui n'affiche rien est pire
-- qu'absente: on la croit sans valeur alors que la valeur existe ailleurs.
--
-- Les deux se remplissent par `db/importer_assos_plus.php` depuis `lv-artists`,
-- qui malgré son nom porte les associations et non les artistes. Rien à
-- altérer ici, la note est là pour qu'on ne recrée pas les colonnes.

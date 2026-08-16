-- La Docuthèque. [16.08.2026]
--
-- Les modèles et guides de la maison: guides internes et charte, modèles de
-- contrats, templates de production et de diffusion, fiches de poste, et le
-- reste. Aujourd'hui ils vivent dans un dossier Drive dont il faut connaître
-- le chemin, et la question « où est le modèle de CDDU suisse » se répond en
-- demandant à quelqu'un.
--
-- LE FICHIER N'EST PAS ICI, ET C'EST VOULU. La colonne porte un LIEN vers le
-- Drive, pas un dépôt. Un modèle de contrat se modifie à plusieurs, se
-- commente, garde son historique de versions: c'est ce que le Drive fait bien
-- et qu'une colonne de fichier ferait mal. Ce que le dashboard ajoute, c'est
-- de savoir QUELS modèles existent, dans quel état ils sont, et de les
-- retrouver sans chercher.
--
-- `statut` PORTE « à compléter », et c'est la colonne qui sert le plus: sur la
-- capture d'Anna, huit modèles de contrats sur huit le sont. Un modèle
-- incomplet qu'on croit prêt part chez un·e artiste avec des blancs dedans.

CREATE TABLE IF NOT EXISTS docutheque (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Les cinq onglets, dans l'ordre d'Anna.
    rubrique    ENUM('guides','contrats','prod','postes','docs')
                NOT NULL DEFAULT 'docs',

    titre       VARCHAR(190) NOT NULL,
    description VARCHAR(400) NULL,

    -- Le lien Drive. NULL quand le document n'existe pas encore: une ligne
    -- sans lien est une ligne « à faire », et elle doit pouvoir exister.
    url         VARCHAR(600) NULL,

    statut      ENUM('pret','a-completer','a-faire','obsolete')
                NOT NULL DEFAULT 'pret',

    -- « manuel » et « generer » ouvrent autre chose qu'un lien: le manuel
    -- s'ouvre dans le dashboard, la fiche projet se génère. Le reste est un
    -- simple lien.
    action      ENUM('lien','manuel','generer') NOT NULL DEFAULT 'lien',

    ordre       SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    notes       VARCHAR(500) NULL,

    cree_le     DATETIME NOT NULL DEFAULT current_timestamp(),
    modifie_le  DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    supprime_le DATETIME NULL,

    PRIMARY KEY (id),
    KEY k_rub (rubrique, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les pièces annuelles d'une association. [18.08.2026]
--
-- Anna: « dentro do dashboard - associations colocar um campo attestation
-- d'affiliation année en cours. deixar espaço para se escolher ano e depositar
-- a atestação em pdf (…) assim como: Attestation d'affiliation de l'année en
-- cours à une institution de prévoyance du deuxième pilier — que é a LPP ».
--
-- CE QUE C'EST, ET POURQUOI ÇA NE TIENT PAS DANS UNE CASE. L'attestation
-- d'affiliation au deuxième pilier est un PDF que la caisse LPP émet CHAQUE
-- ANNÉE. On la redemande à chaque dossier de subvention et à chaque contrôle:
-- ce n'est pas un état — la fiche a déjà `lpp` pour dire « souscrite » — c'est
-- un document daté qu'il faut pouvoir ressortir.
--
-- UNE TABLE ET NON UNE COLONNE, parce qu'il y en a une par an et que celle de
-- l'an dernier ne s'efface pas quand la nouvelle arrive. Un contrôle porte sur
-- l'exercice qu'il contrôle, pas sur l'année en cours.
--
-- `type` PLUTÔT QU'UNE TABLE PAR DOCUMENT: l'attestation LPP est la première
-- demandée, elle ne sera pas la dernière — l'attestation AVS, la police LAA et
-- le certificat d'assujettissement obéissent à la même forme, association ×
-- année × un PDF. Une table par pièce ferait quatre écrans identiques.
--
-- LA CLEF UNIQUE (organisation, type, année) est ce qui empêche deux
-- attestations 2026 de coexister sans qu'on sache laquelle vaut. Déposer à
-- nouveau REMPLACE, et c'est le geste attendu: on ne corrige pas une
-- attestation, on en reçoit une meilleure.

CREATE TABLE IF NOT EXISTS organisation_piece (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    organisation_id INT UNSIGNED NOT NULL,

    -- 'lpp_affiliation' aujourd'hui. Le champ est court et libre plutôt qu'un
    -- ENUM: ajouter une pièce ne doit pas demander une migration.
    type            VARCHAR(40)  NOT NULL,
    annee           SMALLINT UNSIGNED NOT NULL,

    fichier         VARCHAR(255) NOT NULL,
    ext             VARCHAR(8)   NOT NULL DEFAULT 'pdf',
    taille          INT UNSIGNED NOT NULL DEFAULT 0,
    -- Ce que la personne qui dépose veut dire au suivant: la caisse, le numéro
    -- de contrat, « en attente du renouvellement ».
    note            VARCHAR(300) NULL,

    depose_par      VARCHAR(96)  NULL,
    cree_le         DATETIME     NOT NULL DEFAULT current_timestamp(),

    PRIMARY KEY (id),
    UNIQUE KEY u_piece (organisation_id, type, annee),
    KEY k_org (organisation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

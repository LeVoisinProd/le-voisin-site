-- Les demandes de fonds. [16.08.2026]
--
-- La troisième vue des Finances, à côté de l'Aperçu et du Relevé. Le modèle
-- est relevé sur les 87 demandes réelles du dashboard, pas deviné.
--
-- CE QU'ELLES RÉPONDENT, ET AUCUN AUTRE ÉCRAN NE LE FAIT: combien on a
-- demandé, à qui, pour quoi, et ce qui est revenu. Aujourd'hui la réponse est
-- dans une feuille et dans la mémoire de qui a écrit la demande.
--
-- DEUX MONTANTS ET NON UN. `demande` est ce qu'on a osé demander, `accorde`
-- ce qui est tombé. Les confondre effacerait le seul chiffre qui aide à
-- préparer la demande suivante: le taux réel d'obtention. Mesuré sur les 87:
-- 59 portent un montant demandé, 15 un montant accordé.
--
-- DEUX DATES ET NON UNE. `delai` est la date limite de dépôt du financeur;
-- `reponse` est la date à laquelle il répond ou a répondu. Ce sont deux
-- calendriers différents et l'on se fait avoir par les deux: 19 demandes ont
-- un délai, 39 une date de réponse, et ce ne sont pas les mêmes.
--
-- LES STATUTS SONT CEUX D'ANNA, relevés tels quels: a-preparer, en-cours,
-- soumis, en-attente, en-suspens, accorde, refuse, decompte. « decompte » est
-- le dernier et il compte: une subvention accordée demande un décompte final,
-- et l'oublier fait rendre l'argent.

CREATE TABLE IF NOT EXISTS demande_fonds (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ref         VARCHAR(32)  NULL COMMENT 'l identifiant du dashboard, pour une reprise sans doublon',

    -- Qui demande, à qui, pour quoi.
    asso        VARCHAR(190) NOT NULL,
    inst        VARCHAR(190) NOT NULL COMMENT 'institution ou bailleur',
    proj        VARCHAR(190) NULL,

    -- Création, Tournée, Soutien, Reprise, Concert, Écriture, Décompte.
    -- Texte libre et non un enum: la liste s'est allongée deux fois cette
    -- année, et un enum demanderait une migration à chaque nouveau type.
    type        VARCHAR(60)  NULL,
    canton      VARCHAR(8)   NULL COMMENT 'GE, VD, CH pour fédéral, INT pour international',

    priorite    ENUM('P0','P1','P2','P3','P4') NOT NULL DEFAULT 'P2',
    statut      ENUM('a-preparer','en-cours','soumis','en-attente','en-suspens',
                     'accorde','refuse','decompte') NOT NULL DEFAULT 'a-preparer',

    demande     DECIMAL(12,2) NULL,
    accorde     DECIMAL(12,2) NULL,
    devise      CHAR(3) NOT NULL DEFAULT 'CHF',

    delai       DATE NULL COMMENT 'date limite de dépôt',
    reponse     DATE NULL COMMENT 'date de réponse du financeur',

    notes       TEXT NULL,
    cree_le     DATETIME NOT NULL DEFAULT current_timestamp(),
    modifie_le  DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    supprime_le DATETIME NULL,

    PRIMARY KEY (id),
    UNIQUE KEY u_ref (ref),
    KEY k_statut (statut),
    KEY k_delai (delai),
    KEY k_asso (asso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

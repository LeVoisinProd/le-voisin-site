-- La conformité suisse et française. [16.08.2026]
--
-- POURQUOI CET ÉCRAN JUSTIFIE LE PROJET ENTIER.
--
-- Le benchmark du 15.08.2026 a passé dix-sept logiciels du métier: Orfeo, Bob
-- Booking, Mascaron, Artistu, Overture, System One, Yesplan, Artifax, Momentus,
-- Master Tour, sPAIEctacle, Movinmotion et les autres. AUCUN ne fait ceci. La
-- paie française est bien couverte, la tournée aussi; l'impôt à la source canton
-- par canton, l'AVS employeur, les attestations A1 et le calendrier d'un bureau
-- qui administre treize associations n'ont d'équivalent nulle part.
--
-- C'est aussi l'écran qu'Alessandra ouvre tous les mois, et elle dispose de
-- quatre heures par semaine.
--
-- UNE TABLE AU LIEU DE TROIS. Le dashboard actuel éclate la même chose en
-- `lv-fiscal`, `lv-admin-mensuel` et `lv-declarations`. Une obligation y existe
-- donc à trois endroits selon qu'on la regarde comme une échéance, comme une
-- ligne de la liste du mois ou comme une déclaration. Mesuré le 15.08: tenir les
-- trois cohérentes coûte cinq minutes par mois et par association, soit treize
-- heures par an pour rien.

-- Le modèle: ce qui se répète tous les mois, sans dates.
CREATE TABLE IF NOT EXISTS admin_modele (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code          VARCHAR(32)  NOT NULL COMMENT 'stable, sert à la déduplication',
    categorie     ENUM('declarations','rh','compta','juridique','autre') NOT NULL DEFAULT 'autre',
    libelle       VARCHAR(190) NOT NULL,

    -- Le territoire décide de l'obligation, du délai et de qui la reçoit. GE, VD,
    -- BE, VS, ZH, CH, FR. Ce n'est pas une étiquette de rangement.
    territoire    VARCHAR(8)       NULL,

    -- Le jour du mois où c'est dû. Sert à ordonner la liste et à dire ce qui
    -- presse, pas à déclencher quoi que ce soit tout seul.
    jour_echeance TINYINT UNSIGNED NULL,

    aide          TEXT             NULL,
    actif         TINYINT(1)   NOT NULL DEFAULT 1,
    ordre         SMALLINT     NOT NULL DEFAULT 0,

    PRIMARY KEY (id),
    UNIQUE KEY u_code (code),
    KEY i_actif (actif, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une occurrence: ce modèle, ce mois, cette association.
CREATE TABLE IF NOT EXISTS admin_tache (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    modele_id       INT UNSIGNED     NULL COMMENT 'null pour une tâche ponctuelle',
    organisation_id INT UNSIGNED     NULL,

    -- AAAA-MM. Une chaîne et non une date: une tâche mensuelle n'a pas de jour,
    -- et lui en inventer un ferait croire à une précision qui n'existe pas.
    periode         CHAR(7)      NOT NULL,

    libelle         VARCHAR(190)     NULL COMMENT 'seulement si hors modèle',
    territoire      VARCHAR(8)       NULL,
    echeance        DATE             NULL,

    etat            ENUM('a_faire','en_cours','fait','sans_objet') NOT NULL DEFAULT 'a_faire',
    fait_le         DATETIME         NULL,
    fait_par        VARCHAR(96)      NULL,
    note            TEXT             NULL,

    cree_le         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- Une occurrence et une seule par modèle, période et association: c'est ce
    -- qui rend la génération du mois rejouable sans créer de doublons.
    UNIQUE KEY u_occurrence (modele_id, periode, organisation_id),
    KEY i_periode (periode, etat),
    KEY i_org     (organisation_id, periode),

    CONSTRAINT fk_tache_modele FOREIGN KEY (modele_id)
        REFERENCES admin_modele (id) ON DELETE CASCADE,
    CONSTRAINT fk_tache_org FOREIGN KEY (organisation_id)
        REFERENCES organisation (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les attestations A1, une par personne et par date hors de Suisse.
--
-- OBLIGATION LÉGALE ET PAS FORMALITÉ. Détacher quelqu'un dans l'Union sans A1
-- expose à un contrôle sur place et à une amende, et la demande prend quatre
-- semaines. Le dashboard actuel les dérive déjà des dates hors Suisse avec un
-- rappel à quatorze jours; ici elles se dérivent de `booking.pays`, qui est
-- désormais une vraie colonne.
CREATE TABLE IF NOT EXISTS a1_demande (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id   INT UNSIGNED NOT NULL,
    personne     VARCHAR(190) NOT NULL,
    etat         ENUM('a_demander','demande','recu','sans_objet') NOT NULL DEFAULT 'a_demander',
    demande_le   DATE         NULL,
    recu_le      DATE         NULL,
    note         TEXT         NULL,

    cree_le      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY u_personne (booking_id, personne),
    KEY i_etat (etat),
    CONSTRAINT fk_a1_booking FOREIGN KEY (booking_id)
        REFERENCES booking (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Le modèle mensuel, repris de 14_admin_mensuel_alessandra.js.
-- Les jours d'échéance sont ceux de la pratique, à confirmer avec elle.
INSERT INTO admin_modele (code, categorie, libelle, territoire, jour_echeance, ordre) VALUES
 ('is_ge',      'declarations', 'Impôt à la source',              'GE', 15,  10),
 ('avs_ge',     'declarations', 'Cotisations AVS, AC et AI',      'GE', 20,  20),
 ('is_vd',      'declarations', 'Impôt à la source',              'VD', 15,  30),
 ('is_be',      'declarations', 'Quellensteuer',                  'BE', 15,  40),
 ('is_vs',      'declarations', 'Impôt à la source',              'VS', 15,  50),
 ('is_zh',      'declarations', 'Quellensteuer',                  'ZH', 15,  60),
 ('dsn_fr',     'declarations', 'DSN et URSSAF',                  'FR',  5,  70),
 ('contrats_ch','rh',           'Préparer les contrats du mois',  'CH',  1,  80),
 ('contrats_fr','rh',           'Préparer les contrats du mois',  'FR',  1,  90),
 ('salaires_ch','rh',           'Fiches de salaire préparées',    'CH', 25, 100),
 ('salaires_fr','rh',           'Fiches de salaire préparées',    'FR', 25, 110),
 ('paiem_ch',   'rh',           'Préparer les paiements Bexio',   'CH', 25, 120),
 ('paiem_ok',   'rh',           'Confirmer les paiements versés', 'CH', 28, 130),
 ('classe_ch',  'compta',       'Classement comptable du mois',   'CH', 10, 140),
 ('classe_fr',  'compta',       'Classement comptable du mois',   'FR', 10, 150),
 ('bexio_ver',  'compta',       'Vérification et rapports Bexio', 'CH', 10, 160)
ON DUPLICATE KEY UPDATE libelle = VALUES(libelle);

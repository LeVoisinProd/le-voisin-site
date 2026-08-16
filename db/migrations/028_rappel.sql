-- Les rappels: le to-do du Voisin. [16.08.2026]
--
-- Anna: « separar agenda projets et agenda rappels (é o to do do voisin) », et
-- plus tôt: « le système de rappels centralisé doit m'alerter automatiquement
-- sur tous les éléments du CRM — contacts, e-mails, rendez-vous, notes ».
--
-- CETTE TABLE NE PORTE QUE LES RAPPELS QU'ON ÉCRIT. Les échéances qui existent
-- déjà ailleurs — 188 obligations administratives, 19 délais de demande de
-- fonds, 39 dates de réponse, 32 dates jouées non encaissées, les échéances des
-- cartes du pipeline — NE SONT PAS RECOPIÉES ICI. Elles sont lues où elles
-- vivent et fondues dans la même liste à l'affichage.
--
-- C'est le choix qui compte, et il se prend une fois. Recopier aurait donné une
-- table pleine, facile à lire, et fausse dès la première fois qu'on coche une
-- obligation dans son écran sans que le rappel le sache. Deux vérités pour la
-- même échéance, c'est une échéance qu'on finit par ignorer des deux côtés.
--
-- `quand` EST UN DATETIME ET NON UNE DATE. « Rappeler jeudi » et « rappeler
-- jeudi à 9h avant qu'il parte » ne sont pas la même consigne. L'heure vaut
-- 00:00 quand on n'en met pas, et l'écran ne l'affiche alors pas.
--
-- LES LIENS SONT FACULTATIFS ET MULTIPLES, comme pour les cartes du pipeline:
-- un rappel porte sur un contact, une date, une offre, une pièce, une
-- association — ou sur rien du tout. Forcer un rattachement ferait inventer un
-- lien pour pouvoir écrire « appeler la banque ».

CREATE TABLE IF NOT EXISTS rappel (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,

    quand           DATETIME NOT NULL,
    texte           VARCHAR(500) NOT NULL,
    -- Ce qu'on veut relire au moment où le rappel tombe: le contexte, pas la
    -- consigne. « Il a dit qu'il déciderait après son conseil d'administration. »
    note            TEXT NULL,

    contact_id      INT UNSIGNED NULL,
    booking_id      INT UNSIGNED NULL,
    offer_id        INT UNSIGNED NULL,
    project_id      INT UNSIGNED NULL,
    organisation_id INT UNSIGNED NULL,

    -- Qui doit le faire. Texte libre et non une clef vers `users`: le bureau
    -- est de trois personnes, deux d'entre elles n'ont pas de compte, et un
    -- rappel adressé à quelqu'un qui n'existe pas en base est perdu.
    pour_qui        VARCHAR(96) NULL,

    fait_le         DATETIME NULL,
    fait_par        VARCHAR(96) NULL,

    cree_par        VARCHAR(96) NULL,
    cree_le         DATETIME NOT NULL DEFAULT current_timestamp(),
    modifie_le      DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    archive_le      DATETIME NULL,

    PRIMARY KEY (id),
    -- L'index porte `fait_le` en premier: la question posée à chaque ouverture
    -- est « qu'est-ce qui reste à faire », jamais « que s'est-il passé le 12 ».
    KEY k_ouvert (fait_le, quand),
    KEY k_contact (contact_id),
    KEY k_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

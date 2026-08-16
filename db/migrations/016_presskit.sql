-- Le lien de presskit d'un spectacle. [16.08.2026]
--
-- Anna: « une page presskit par projet, pour partager fiches techniques,
-- photos et documents techniques. »
--
-- CE QUE CETTE TABLE NE CONTIENT PAS: le contenu du presskit. Il existe déjà,
-- dans le CMS — `projects` porte le titre, l'intro, la distribution et les
-- infos; `images` porte la couverture et la galerie; `documents` porte les
-- fiches techniques. Recopier tout cela ici créerait une deuxième vérité qui
-- divergerait au premier changement, et c'est exactement le défaut que la
-- spécification reproche à l'existant: la même pièce saisie dans trois fiches.
--
-- Il n'y a donc à stocker qu'une chose: QUI A LE DROIT DE VOIR, et jusqu'à
-- quand. D'où une table d'un seul rôle.
--
-- POURQUOI UN JETON ET PAS UNE PAGE PUBLIQUE. Les fiches techniques sont
-- passées derrière le mot de passe du Catalogue le 11.08.2026, et le
-- 13.08.2026 on a découvert qu'elles restaient joignables par leur adresse
-- directe. Une page presskit publique referait ce trou par la porte d'à côté.
-- Le jeton se révoque; une URL publique, une fois partagée, ne se reprend pas.

CREATE TABLE IF NOT EXISTS presskit_link (
    project_id    INT UNSIGNED NOT NULL,
    jeton         CHAR(64) NOT NULL,

    -- À qui on l'a remis. Sert à relancer, et à savoir quel lien couper quand
    -- une relation se termine.
    destinataire  VARCHAR(190) NULL,

    expire_a      DATETIME NULL,
    revoque       TINYINT(1) NOT NULL DEFAULT 0,

    visites       INT UNSIGNED NOT NULL DEFAULT 0,
    dernier_acces DATETIME NULL,
    cree_a        DATETIME NOT NULL DEFAULT current_timestamp(),

    PRIMARY KEY (project_id),
    UNIQUE KEY u_jeton (jeton)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

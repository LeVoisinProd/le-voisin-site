-- Les skills du Claude Code OS, et ce qu'elles produisent. [16.08.2026]
--
-- CE QUE CETTE TABLE N'EST PAS: un moyen de les lancer. Les skills tournent
-- dans le Claude Code, sur le Mac d'Anna, avec le dépôt de travail et le Drive
-- montés. Ce site en PHP ne peut pas les exécuter, et l'on ne construit pas
-- aujourd'hui le pont qui le permettrait: il demanderait qu'un serveur public
-- déclenche des commandes sur une machine personnelle, ce qui se décide à
-- froid et pas en passant.
--
-- CE QU'ELLE EST: la base pour deux choses.
--
--   1. LE CATALOGUE. Treize skills existent et personne d'autre qu'Anna ne
--      sait lesquelles ni ce qu'elles font. Une skill qu'on ignore n'est pas
--      un outil, c'est du code mort. L'écran les liste, avec leur nom d'appel,
--      ce qu'elles lisent et ce qu'elles écrivent.
--   2. LE REGISTRE DES SORTIES. Ce qu'une skill produit — un devis, un plan de
--      diffusion, une prospection — vit aujourd'hui dans `dados/` du dépôt de
--      travail et dans le Drive, et le dashboard ne le voit pas. `skill_sortie`
--      est l'endroit où les skills viendront déclarer ce qu'elles ont fait,
--      quand on branchera l'écriture.
--
-- LE LIEN VERS UN OBJET EST VOLONTAIREMENT LÂCHE — `objet_type` et `objet_id`
-- plutôt que des clefs étrangères. Une prospection ne pend à rien de précis,
-- un devis pend à une date, un plan de diffusion à un spectacle. Trois clefs
-- étrangères dont deux vides à chaque ligne coûteraient plus qu'elles ne
-- garantissent.

CREATE TABLE IF NOT EXISTS skill (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom         VARCHAR(60)  NOT NULL COMMENT 'le nom d appel, sans la barre oblique',
    titre       VARCHAR(120) NOT NULL,
    resume      VARCHAR(600) NULL,

    -- Ce qu'elle lit et ce qu'elle écrit, en clair: c'est ce qui permet de
    -- comprendre pourquoi une skill donne un mauvais résultat quand un fichier
    -- de contexte est faux.
    lit         VARCHAR(400) NULL,
    ecrit       VARCHAR(400) NULL,

    -- « metier » sert la diffusion et la production; « systeme » entretient le
    -- workspace lui-même. La distinction compte: les premières intéressent
    -- toute l'équipe, les secondes seulement qui tient le dépôt.
    famille     ENUM('metier','systeme') NOT NULL DEFAULT 'metier',
    ordre       SMALLINT UNSIGNED NOT NULL DEFAULT 100,

    PRIMARY KEY (id),
    UNIQUE KEY u_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS skill_sortie (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    skill_nom   VARCHAR(60)  NOT NULL,

    titre       VARCHAR(190) NOT NULL,
    resume      VARCHAR(600) NULL,

    -- Où la sortie vit vraiment: un chemin du dépôt, un lien Drive, une
    -- adresse. Pas le contenu: une prospection fait quarante pages.
    ou          VARCHAR(600) NULL,

    objet_type  ENUM('booking','projet','contact','organisation','aucun')
                NOT NULL DEFAULT 'aucun',
    objet_id    INT UNSIGNED NULL,

    fait_le     DATETIME NOT NULL DEFAULT current_timestamp(),
    par         VARCHAR(120) NULL,

    PRIMARY KEY (id),
    KEY k_skill (skill_nom, fait_le),
    KEY k_objet (objet_type, objet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

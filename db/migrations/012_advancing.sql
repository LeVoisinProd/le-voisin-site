-- L'advancing: ce qu'on demande au lieu, et ce qu'il répond. [16.08.2026]
--
-- CE QUE C'EST. Les semaines avant une date, on demande au lieu une liste de
-- choses: plan de feu, fiche technique validée, horaires de montage, contact
-- du régisseur, nombre de loges, adresse de livraison du décor. Aujourd'hui
-- cela se fait par e-mail, et l'état de chaque point vit dans la tête de la
-- personne qui a écrit. Rien ne dit, à un moment donné, ce qui manque encore.
--
-- LE POINT N'EST PAS LE FORMULAIRE, C'EST L'ÉTAT PAR CHAMP. « demandé »,
-- « reçu », « accepté »: la différence entre les deux derniers est celle qui
-- compte. Un plan de feu reçu n'est pas un plan de feu accepté, et c'est
-- précisément là que les tournées se cassent.
--
-- DEUX TABLES ET PAS UNE. Les champs pendent au booking; le lien qui donne
-- accès au lieu pend aussi au booking mais vit sa vie: il s'ouvre, il expire,
-- il se révoque, et il porte la trace des visites. Les mélanger obligerait à
-- répéter le jeton sur chaque ligne.

CREATE TABLE IF NOT EXISTS advancing_field (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id  INT UNSIGNED NOT NULL,

    -- Pour regrouper: « Technique », « Accueil », « Logistique ». Texte libre
    -- plutôt qu'une liste fermée: chaque lieu a ses particularités, et une
    -- liste fermée obligerait à une migration pour ajouter « Billetterie ».
    section     VARCHAR(80)  NULL,
    libelle     VARCHAR(190) NOT NULL,

    type        ENUM('texte','long','nombre','date','heure','oui_non','fichier')
                NOT NULL DEFAULT 'texte',
    obligatoire TINYINT(1) NOT NULL DEFAULT 0,
    ordre       SMALLINT UNSIGNED NOT NULL DEFAULT 100,

    -- « demande » on attend, « recu » le lieu a répondu, « accepte » nous
    -- l'avons validé, « refuse » il faut refaire. Le passage de recu à accepte
    -- est un geste du bureau, jamais du lieu: sinon la validation ne veut rien
    -- dire.
    etat        ENUM('demande','recu','accepte','refuse') NOT NULL DEFAULT 'demande',

    reponse     TEXT NULL,
    fichier     VARCHAR(190) NULL,

    -- Ce que le lieu voit, et ce qu'il ne voit pas. La consigne part avec la
    -- demande; la note reste chez nous.
    consigne    VARCHAR(500) NULL,
    note_interne VARCHAR(500) NULL,

    repondu_a   DATETIME NULL,
    cree_a      DATETIME NOT NULL DEFAULT current_timestamp(),

    PRIMARY KEY (id),
    KEY k_booking (booking_id, ordre),
    KEY k_etat    (etat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Le lien remis au lieu. UNE ligne par booking.
--
-- POURQUOI UN JETON ET PAS UN COMPTE. Le régisseur d'un théâtre n'ouvrira pas
-- un compte chez nous pour remplir six champs, et lui en imposer un revient à
-- garantir qu'il répondra par e-mail comme avant. Le jeton est long, il
-- expire, il se révoque, et il ne donne accès qu'à l'advancing de CETTE date.
--
-- CE QU'IL NE DONNE PAS: le prix de cession, le deal, les contrats, les notes
-- internes. Le portail ne lit que les colonnes de advancing_field qui le
-- concernent.

CREATE TABLE IF NOT EXISTS advancing_link (
    booking_id    INT UNSIGNED NOT NULL,
    jeton         CHAR(64) NOT NULL,

    -- À qui on l'a remis, pour savoir qui relancer.
    destinataire  VARCHAR(190) NULL,

    expire_a      DATETIME NULL,
    revoque       TINYINT(1) NOT NULL DEFAULT 0,

    visites       INT UNSIGNED NOT NULL DEFAULT 0,
    dernier_acces DATETIME NULL,
    cree_a        DATETIME NOT NULL DEFAULT current_timestamp(),

    PRIMARY KEY (booking_id),
    UNIQUE KEY u_jeton (jeton)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

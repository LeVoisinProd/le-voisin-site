-- Les demandes de booking entrantes. [16.08.2026]
--
-- CE QUE ÇA REMPLACE. Anna: « Les demandes entrantes ne restent plus dans
-- votre boîte de réception. » Aujourd'hui une demande de programmateur arrive
-- par e-mail, se répond par e-mail, et n'existe nulle part ailleurs. On ne
-- peut donc ni compter combien il en arrive, ni voir lesquelles n'ont pas eu
-- de réponse, ni savoir quelle proportion se transforme en date — c'est-à-dire
-- exactement les trois chiffres qu'un dossier de subvention demande.
--
-- POURQUOI UNE TABLE À PART ET PAS UN BOOKING « pending ». Parce qu'une
-- demande n'est pas une date: elle porte ce que le demandeur dit, avec ses
-- mots, y compris quand c'est vague ou faux — « en novembre, plutôt en début
-- de mois ». Un booking porte ce que NOUS savons. Les confondre obligerait à
-- polluer la table des dates avec des lignes qui n'en sont pas, et à se
-- souvenir de les exclure de tous les comptes.
--
-- LA CONVERSION EST À SENS UNIQUE ET LAISSE LA TRACE: `booking_id` relie
-- l'offre à la date née d'elle, et le booking porte source='offre'. On peut
-- donc, un an après, dire combien de dates sont venues du formulaire.

CREATE TABLE IF NOT EXISTS offer (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- ── Ce que le demandeur écrit ──────────────────────────────────────
    projet         VARCHAR(190) NULL,
    venue          VARCHAR(190) NULL,
    venue_url      VARCHAR(400) NULL,
    ville          VARCHAR(96)  NULL,
    pays           VARCHAR(64)  NULL,

    -- Les deux, et c'est voulu: une date ferme quand il y en a une, et le
    -- texte quand la demande est « une semaine en mars ». Forcer une date
    -- exacte ferait inventer une précision qui n'existe pas.
    date_souhaitee DATE NULL,
    date_texte     VARCHAR(190) NULL,
    representations SMALLINT UNSIGNED NULL,

    budget         DECIMAL(10,2) NULL,
    devise         CHAR(3) NOT NULL DEFAULT 'EUR',

    contact_nom    VARCHAR(190) NULL,
    contact_role   VARCHAR(120) NULL,
    contact_email  VARCHAR(190) NULL,
    contact_tel    VARCHAR(40)  NULL,
    structure      VARCHAR(190) NULL,
    message        TEXT NULL,

    -- ── Notre côté ─────────────────────────────────────────────────────
    statut         ENUM('nouvelle','en_discussion','contre_proposee',
                        'acceptee','refusee','sans_suite')
                   NOT NULL DEFAULT 'nouvelle',
    contre_prix    DECIMAL(10,2) NULL,
    notes_internes VARCHAR(1000) NULL,

    booking_id     INT UNSIGNED NULL,

    -- Pour le plafond par adresse, et pour reconnaître une vague de spam.
    ip             VARCHAR(45) NULL,

    cree_a         DATETIME NOT NULL DEFAULT current_timestamp(),
    traite_a       DATETIME NULL,

    PRIMARY KEY (id),
    KEY k_statut (statut),
    KEY k_cree   (cree_a),
    KEY k_ip     (ip, cree_a)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les factures d'un booking. [16.08.2026]
--
-- CE QUE CETTE TABLE FAIT, ET CE QU'ELLE NE FAIT PAS. Elle suit: quelles
-- factures existent sur une date, pour quel montant, envoyées quand, payées
-- quand. Elle NE produit PAS la facture et ne parle PAS à bexio.
--
-- Ce n'est pas un demi-travail, c'est la moitié qui n'est pas bloquée. Le
-- client bexio vit dans Apps Script et son portage en PHP est chiffré entre
-- 12 h et 20 h pour le seul OAuth2, plus 6 à 10 h par endpoint. En attendant,
-- la question qui coûte cher n'est pas « comment j'émets » — cela se fait déjà
-- dans bexio — mais « qu'est-ce qui est parti, et qu'est-ce qui n'est pas
-- rentré ». C'est exactement ce qui a manqué pendant la crise de paiements
-- d'août 2026: personne ne pouvait dire, date par date, ce qui restait dû.
--
-- LA COLONNE bexio_id EXISTE ET RESTE VIDE. Elle est là pour que le
-- rapprochement futur n'oblige pas à une migration de plus, et parce qu'une
-- colonne vide déclarée dit mieux qu'un commentaire ce qui est prévu.

CREATE TABLE IF NOT EXISTS invoice (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id   INT UNSIGNED NOT NULL,

    -- Le numéro est celui de bexio ou du carnet: on le recopie, on ne
    -- l'invente pas. Deux numérotations parallèles seraient pires que pas de
    -- numéro du tout.
    numero       VARCHAR(60)  NULL,

    type         ENUM('acompte','solde','totale','note_frais','avoir')
                 NOT NULL DEFAULT 'totale',
    libelle      VARCHAR(190) NULL,

    -- Le destinataire est le plus souvent le lieu, mais pas toujours: une
    -- coproduction se facture à la structure porteuse.
    destinataire VARCHAR(190) NULL,

    montant      DECIMAL(10,2) NOT NULL DEFAULT 0,
    devise       CHAR(3) NOT NULL DEFAULT 'CHF',

    -- « brouillon » pas encore émise, « envoyee » partie, « payee » encaissée,
    -- « annulee » ne compte plus. L'écart entre envoyee et payee est le seul
    -- chiffre qui intéresse quand on cherche ce qui manque.
    statut       ENUM('brouillon','envoyee','payee','annulee')
                 NOT NULL DEFAULT 'brouillon',

    date_emission DATE NULL,
    date_echeance DATE NULL,
    date_paiement DATE NULL,

    -- Vide pour l'instant. Le rapprochement avec bexio viendra la remplir.
    bexio_id     VARCHAR(60) NULL,

    notes        VARCHAR(500) NULL,
    cree_a       DATETIME NOT NULL DEFAULT current_timestamp(),

    PRIMARY KEY (id),
    KEY k_booking (booking_id),
    KEY k_statut  (statut),
    KEY k_echeance (date_echeance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

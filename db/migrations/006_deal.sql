-- La ventilation financière d'une date. [16.08.2026]
--
-- Anna: « Chaque booking affiche sa ventilation financière, des cachets de
-- représentation et frais de booking aux coûts de voyage et d'hébergement. Les
-- totaux se mettent à jour au fil de la saisie, les budgets restent donc
-- transparents sans tableur à côté. »
--
-- CE QUE CETTE TABLE REND POSSIBLE ET QUI MANQUAIT. Le modèle économique du
-- 15.08.2026 a buté sur une question qu'aucune donnée ne permettait de trancher:
-- où passe la marge, et qui paie quoi. Les devis se calculent dans un générateur
-- à part, en JavaScript, et le résultat n'atterrit nulle part: le prix figure sur
-- un PDF et ce qui le compose disparaît. Ici il reste.
--
-- LA COLONNE `charge` EST LE POINT, et elle n'a pas d'équivalent dans le
-- générateur actuel. Une ligne de devis peut être:
--
--   incluse    dans le prix de cession. Le lieu paie, nous encaissons
--   lieu       à la charge du lieu, hors cession. L'hébergement en général:
--              on l'écrit en chambres et en nuits, jamais en francs
--   nous       à notre charge, déduit de ce qui reste
--
-- Sans cette distinction, un total de 4 120 CHF ne dit pas si les voyages sont
-- dedans, et deux personnes lisant le même devis n'y comprennent pas la même
-- chose. C'est déjà arrivé.

CREATE TABLE IF NOT EXISTS deal_item (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id    INT UNSIGNED NOT NULL,

    -- Les natures viennent du modèle de devis en vigueur, plus celles d'artistu
    -- qui manquaient: droits d'auteur, matériel, catering.
    type          ENUM('cachet','frais_booking','voyage','hebergement','per_diem',
                       'droits','materiel','catering','marge','autre')
                  NOT NULL DEFAULT 'autre',
    libelle       VARCHAR(190)     NULL,

    -- Qui supporte la ligne. Voir l'explication ci-dessus: c'est ce qui manque
    -- le plus souvent quand un prix est mal compris.
    charge        ENUM('incluse','lieu','nous') NOT NULL DEFAULT 'incluse',

    quantite      DECIMAL(8,2) NOT NULL DEFAULT 1,
    prix_unitaire DECIMAL(10,2)    NULL,

    -- Le montant est STOCKÉ et non calculé à la lecture. Une colonne générée
    -- serait plus propre en apparence et fausse en pratique: certaines lignes se
    -- négocient à forfait, sans quantité ni prix unitaire, et il faut pouvoir
    -- écrire 4 120 sans inventer 1 × 4 120.
    montant       DECIMAL(10,2)    NULL,
    devise        CHAR(3)      NOT NULL DEFAULT 'CHF',

    note          TEXT             NULL,
    ordre         SMALLINT     NOT NULL DEFAULT 0,

    cree_le       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY i_booking (booking_id, ordre),
    KEY i_type    (type),
    CONSTRAINT fk_deal_booking FOREIGN KEY (booking_id)
        REFERENCES booking (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

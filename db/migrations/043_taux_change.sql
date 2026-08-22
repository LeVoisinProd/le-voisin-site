-- Les taux de change, gardés jour par jour.  [Anna, 22.08.2026]
--
-- « dans la partie budget deixar a escolha da moeda dos valores em euro e chf,
-- quando for em chf fazer a conversão com a taxa cambial do dia. »
--
-- LE TAUX SE FIGE SUR LA LIGNE, IL NE SUIT PAS LE MARCHÉ — sa réponse à la
-- question posée. Un budget dont le total change tous les matins ne s'envoie pas
-- à un financeur et ne se compare pas à celui du mois dernier. La ligne garde
-- donc le taux du jour où elle a été écrite, et un bouton réévalue tout au taux
-- du jour quand on le décide.
--
-- CETTE TABLE EST UN CACHE, ET C'EST TOUT CE QU'ELLE EST. Le taux qui compte est
-- recopié sur la ligne de budget elle-même, dans son JSON. Perdre cette table ne
-- ferait perdre aucun montant: elle éviterait seulement d'aller redemander les
-- taux passés, ce qui n'arriverait de toute façon jamais.
--
-- LA SOURCE EST LA BANQUE CENTRALE EUROPÉENNE, son taux de référence quotidien.
-- Pas de clef, pas de compte, pas de facture, et c'est la référence qu'un
-- fiduciaire reconnaît — vérifié depuis le serveur avant d'écrire une ligne de
-- code: HTTP 200, 1 EUR = 0,9353 CHF au 21.08.2026.
--
-- ELLE NE PUBLIE QUE LES JOURS OUVRÉS, vers 16 h. Un samedi, un dimanche, un
-- 1er janvier, il n'y a pas de taux du jour: c'est pourquoi la date du taux est
-- gardée à côté de sa valeur et affichée. « Le taux du jour » d'un dimanche est
-- celui du vendredi, et le dire vaut mieux que de laisser croire autre chose.

CREATE TABLE IF NOT EXISTS taux_change (
    jour      DATE         NOT NULL COMMENT 'date de publication du taux, pas celle de la requete',
    devise    VARCHAR(3)   NOT NULL COMMENT 'la devise cotee contre EUR',
    taux      DECIMAL(14,6) NOT NULL COMMENT '1 EUR = ce montant de la devise',
    source    VARCHAR(40)  NOT NULL DEFAULT 'BCE',
    releve_le DATETIME     NOT NULL COMMENT 'quand nous l avons demande',
    PRIMARY KEY (jour, devise)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

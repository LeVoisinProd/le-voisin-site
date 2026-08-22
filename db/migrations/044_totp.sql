-- Le deuxième facteur des comptes du bureau.  [revue de sécurité, 22.08.2026]
--
-- Point 3 de la revue. Un mot de passe volé — hameçonnage, réutilisation, poste
-- compromis — ouvre aujourd'hui le dashboard entier: 91 fiches de personnel avec
-- AVS et IBAN, 7 890 contacts, les contrats, les jetons bexio de sept
-- associations. Le frein à dix tentatives protège du forçage, pas d'un mot de
-- passe connu.
--
-- LE SECRET EST CHIFFRÉ, comme les AVS, les IBAN et les jetons bexio, avec le
-- même `Crypto::`. Un secret TOTP en clair dans un dump permet de fabriquer les
-- codes: il vaut exactement le deuxième facteur qu'il est censé être.
--
-- `totp_actif` EST SÉPARÉ DU SECRET, et ce n'est pas une redondance. On génère
-- un secret AVANT de savoir si la personne arrive à le poser dans son
-- application; tant qu'elle n'a pas prouvé un code, le compte ne doit pas
-- exiger un deuxième facteur qui ne marche pas. Le secret existe, le facteur
-- s'active à la première preuve.
--
-- `totp_dernier_pas` FERME LA REJOUABILITÉ. Un code TOTP vaut trente secondes:
-- sans mémoire du dernier pas accepté, un code intercepté se rejoue pendant ce
-- temps-là. On garde donc le numéro de pas et on refuse celui qui a déjà servi.

ALTER TABLE users
    ADD COLUMN totp_secret VARCHAR(255) NULL
        COMMENT 'secret TOTP, chiffre par Crypto::',
    ADD COLUMN totp_actif TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'un code a ete prouve au moins une fois',
    ADD COLUMN totp_dernier_pas BIGINT NULL
        COMMENT 'dernier pas de trente secondes accepte, contre le rejeu';

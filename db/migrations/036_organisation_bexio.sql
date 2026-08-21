-- Le jeton bexio d'une association.  [Anna, 21.08.2026]
--
-- « fazemos o api avec bexio ? »
--
-- UN JETON PAR ASSOCIATION, ET NON UN POUR LA MAISON. Chaque association a sa
-- propre comptabilité bexio, avec son plan de comptes et ses factures. Un
-- jeton global n'existe pas: il n'y a pas de compte bexio « Le Voisin » qui
-- verrait les treize autres.
--
-- PAS D'OAUTH2, ET C'EST MESURÉ. La note laissée dans `bookings.php` le 16.08
-- chiffrait « entre 12 h et 20 h pour le seul OAuth2 ». La documentation de
-- bexio dit le contraire, mot pour mot: « Personal Access Tokens (PAT) allow
-- server-to-server connections without the consent flow ». Un jeton collé
-- suffit. Deux portées seulement: `kb_invoice_edit` et `contact_edit` — la
-- lecture vient avec l'écriture.
--
-- LE JETON EST CHIFFRÉ, comme les IBAN et les AVS, avec le même `Crypto::`.
-- Un jeton bexio ouvre la comptabilité entière d'une association: en clair
-- dans la base, il vaut un accès permanent que personne ne verrait partir.
--
-- `bexio_teste_a` ET `bexio_societe` DISENT SI ÇA MARCHE ET CHEZ QUI. Un jeton
-- collé n'est pas un jeton qui répond, et un jeton qui répond n'est pas
-- forcément celui de la bonne société — c'est l'erreur qui coûte le plus cher
-- ici, une facture émise dans la comptabilité d'une autre association. On
-- garde donc le nom que bexio renvoie, et on l'affiche à côté du champ.

ALTER TABLE organisation
    ADD COLUMN bexio_token TEXT NULL
        COMMENT 'jeton personnel bexio, chiffre par Crypto::',
    ADD COLUMN bexio_societe VARCHAR(190) NULL
        COMMENT 'nom de la societe que bexio renvoie, pour verifier a l oeil',
    ADD COLUMN bexio_teste_a DATETIME NULL
        COMMENT 'derniere fois que le jeton a repondu';

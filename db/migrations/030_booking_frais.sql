-- La commission de booking sur une date. [16.08.2026]
--
-- Anna, écran de référence à l'appui: « copiar tal e qual ». Le tableau qu'elle
-- montre est celui d'une agence — Venue, Artist, Client, Performance Fee,
-- Booking Fee — et la dernière colonne n'existait nulle part chez nous.
--
-- UN CHAMP VIDE N'EST PAS UNE RAISON DE NE PAS LE CRÉER. Anna, le 16.08.2026:
-- « estar vazio nao é um criterio de exclusao, ja disse, estamos criando a
-- base ». C'est la deuxième fois qu'il faut le dire, et la règle vaut pour tout
-- ce dépôt: on construit la structure d'abord, elle se remplit ensuite. Une
-- colonne absente empêche de saisir; une colonne vide attend, et c'est tout ce
-- qu'on lui demande.
--
-- Ce qui reste vrai et n'est pas la même chose: ne pas AFFICHER un total calculé
-- à partir de rien comme s'il était mesuré. Créer le champ, oui, toujours.
-- Prétendre qu'il dit quelque chose alors qu'il est vide, non.
--
-- DEUX COLONNES ET NON UNE, et c'est ce qui rend la chose utilisable.
--   `frais_booking`      le montant, en toutes lettres. C'est lui qui compte,
--                        c'est lui qu'on facture, et il peut être négocié hors
--                        barème sur une date précise.
--   `frais_booking_taux` le pourcentage, quand il y en a un. Il sert à calculer
--                        le montant quand on le renseigne, et à relire six mois
--                        après pourquoi c'est ce montant-là.
--
-- Le montant N'EST PAS recalculé à l'affichage à partir du taux: une date dont
-- le prix de cession change ne doit pas voir sa commission bouger toute seule
-- après qu'elle a été facturée. Le taux propose, le montant fait foi. C'est la
-- même règle que pour le prix de cession, qui est ce qui a été ENVOYÉ et non ce
-- que le barème recalculerait aujourd'hui.
--
-- `organisation.frais_booking` existe déjà et sert de valeur par défaut à la
-- saisie. Il est vide sur les quinze associations: personne n'a encore décidé
-- de taux, et le champ ne prétend pas le contraire.

ALTER TABLE booking
    ADD COLUMN frais_booking      DECIMAL(10,2) NULL
        COMMENT 'la commission sur cette date, en montant. Fait foi.'
        AFTER prix_vente,
    ADD COLUMN frais_booking_taux DECIMAL(5,2) NULL
        COMMENT 'le pourcentage applique, pour relire d ou vient le montant'
        AFTER frais_booking;

-- Le compte et la taxe qu'une facture bexio doit porter.  [Anna, 21.08.2026]
--
-- POURQUOI CE N'EST PAS UNE CONSTANTE. Une position de facture bexio exige un
-- `account_id` et un `tax_id`, et ces nombres n'ont de sens que dans UNE
-- comptabilité: le « 3404 Cachet spectacle » du Voisin CH porte l'identifiant
-- 246, celui d'une autre association en portera un autre. Les coder en dur
-- écrirait dans le mauvais compte sans que rien ne proteste — une facture part
-- avec un numéro, elle ne s'annule que par une note de crédit.
--
-- LES DEUX SE CHOISISSENT DANS UNE LISTE LUE CHEZ BEXIO, jamais tapés. On ne
-- demande pas à quelqu'un de retenir un identifiant technique, et une faute de
-- frappe sur un nombre à trois chiffres ne se voit pas.
--
-- CE QUE J'AI VU EN LISANT LA VRAIE COMPTABILITÉ DU VOISIN CH, et qui a évité
-- une supposition: le plan de comptes contient « 3404 Cachet spectacle », qui
-- est exactement le compte d'un prix de cession, et les seules taxes actives
-- sont à 0 % — l'association n'est pas assujettie. Le défaut proposé sera donc
-- juste pour elle, et il restera un défaut: c'est la fiche qui décide.

ALTER TABLE organisation
    ADD COLUMN bexio_compte INT NULL
        COMMENT 'account_id bexio ou porter le prix de cession',
    ADD COLUMN bexio_compte_nom VARCHAR(120) NULL
        COMMENT 'le numero et le nom du compte, pour le relire sans appeler bexio',
    ADD COLUMN bexio_taxe INT NULL
        COMMENT 'tax_id bexio applique aux positions',
    ADD COLUMN bexio_taxe_nom VARCHAR(120) NULL
        COMMENT 'le libelle de la taxe, pour le relire sans appeler bexio';

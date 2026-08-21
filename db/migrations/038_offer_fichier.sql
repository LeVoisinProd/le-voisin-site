-- Le devis d'une offre, attaché à l'offre.  [Anna, 21.08.2026]
--
-- « na página Offres eu não consigo baixar o pdf da oferta », « eu preciso
-- baixar o pdf desde esta página ».
--
-- CE QUI MANQUAIT N'ÉTAIT PAS UN BOUTON, C'ÉTAIT LE FICHIER. L'écran Offres
-- suit des demandes: qui a écrit, ce qu'il propose, où en est la discussion. Le
-- devis, lui, est produit ailleurs — par `gerar_devis.js`, dans le dépôt — et
-- vit sur le Drive. L'offre n'en gardait que le NOM, dans une note:
-- « Devis envoyé le 2026-08-07. Fichier: …_Devis_V1.html — sur le Drive ».
-- Autrement dit elle disait qu'un devis existait sans permettre de l'ouvrir.
--
-- UNE TABLE À PART, ET NON UNE COLONNE DE PLUS SUR `offer`. Une offre peut
-- porter plusieurs pièces: le devis, sa version corrigée, un accord par
-- courriel. Une colonne unique obligerait à écraser la précédente, et c'est
-- justement l'historique d'une négociation qu'on veut garder.
--
-- POURQUOI PAS `booking_file`. Une offre n'est pas encore une date: elle peut
-- ne jamais le devenir. Y accrocher un `booking_id` nul ferait une table dont
-- la moitié des lignes ne désignent rien, et le jour où l'offre se convertit
-- il faudra de toute façon décider quoi recopier.

CREATE TABLE IF NOT EXISTS offer_file (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    offer_id    INT UNSIGNED NOT NULL,
    titre       VARCHAR(190) NOT NULL DEFAULT '',
    fichier     VARCHAR(190) NOT NULL,
    taille      INT UNSIGNED NOT NULL DEFAULT 0,
    depose_par  VARCHAR(190) NULL,
    cree_a      DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY k_offre (offer_id, cree_a)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

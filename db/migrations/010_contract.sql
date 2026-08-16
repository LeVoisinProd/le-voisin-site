-- Les contrats d'un booking, et leur signature. [16.08.2026]
--
-- POURQUOI UNE TABLE PLUTÔT QUE RÉUTILISER member_documents. Celle-là pend à
-- un collaborateur (`collaborator_id`) et sert les fiches de salaire, les
-- contrats de travail, les pièces d'identité. Un contrat de cession ne pend
-- pas à une personne: il pend à une DATE, et son signataire est le plus
-- souvent quelqu'un du lieu, qui n'a pas de fiche chez nous et n'en aura
-- jamais. Les tordre ensemble donnerait une table dont la moitié des colonnes
-- est vide selon la ligne.
--
-- CE QUE LA TABLE NE FAIT PAS: produire le PDF. Le site n'a aucune
-- bibliothèque de génération — vérifié le 16.08.2026, ni FPDF, ni TCPDF, ni
-- Dompdf — et Skribble::send() attend le chemin d'un fichier qui existe déjà.
-- Le contrat se rédige donc ailleurs, comme aujourd'hui, et se dépose ici.
-- C'est aussi ce que fait l'espace collaborateur, et c'est éprouvé.

CREATE TABLE IF NOT EXISTS contract (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id    INT UNSIGNED NOT NULL,

    -- Les natures qui reviennent dans la diffusion. « avenant » compte:
    -- une date qui bouge en produit un, et il doit vivre à côté du contrat
    -- qu'il modifie, pas le remplacer.
    type          ENUM('cession','coproduction','engagement','avenant','autre')
                  NOT NULL DEFAULT 'cession',
    titre         VARCHAR(190) NOT NULL,

    -- Le fichier déposé, puis sa version signée. Les deux se gardent: le
    -- signé fait foi, le déposé dit ce qu'on avait envoyé.
    fichier       VARCHAR(190) NOT NULL,
    fichier_signe VARCHAR(190) NULL,

    -- Le signataire est du côté du lieu et n'a pas de compte chez nous.
    -- Le mobile est facultatif: Skribble s'en sert pour l'identification
    -- renforcée quand la qualité de signature l'exige.
    signataire_nom    VARCHAR(190) NULL,
    signataire_email  VARCHAR(190) NULL,
    signataire_mobile VARCHAR(40)  NULL,

    -- « depose »  le PDF est là, rien n'est parti
    -- « envoye »  Skribble a la demande, on attend
    -- « signe »   signé, et la copie signée est rapatriée
    -- « refuse »  le signataire a décliné
    statut        ENUM('depose','envoye','signe','refuse') NOT NULL DEFAULT 'depose',

    skribble_request_id VARCHAR(120) NULL,
    signing_url   VARCHAR(500) NULL,

    envoye_a      DATETIME NULL,
    signe_a       DATETIME NULL,
    cree_a        DATETIME NOT NULL DEFAULT current_timestamp(),

    PRIMARY KEY (id),
    KEY k_booking (booking_id),
    KEY k_statut  (statut),
    KEY k_req     (skribble_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

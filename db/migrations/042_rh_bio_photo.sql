-- La biographie et le portrait d'une personne.  [Anna, 22.08.2026]
--
-- « na parte dossier (…) colocar o item foto + bio. »
--
-- ELLES SONT DE LA PERSONNE, PAS DU SPECTACLE, et c'est sa réponse à la question
-- posée. Une bio écrite une fois sert tous les dossiers où la personne entre, et
-- une bio corrigée se corrige partout du même geste. L'inverse — une bio par
-- projet — obligeait à réécrire le même paragraphe à chaque nouvelle production,
-- et c'est le genre de recopie qui finit par ne plus être faite: le dossier part
-- alors avec la bio d'il y a trois ans.
--
-- LA PHOTO EST UN NOM DE FICHIER, PAS LE FICHIER. Elle vit dans
-- `uploads/private/rh/<id>/`, qu'Apache ne sert pas — décidé par Anna à la même
-- question. Un portrait est une donnée personnelle: il ne s'attrape pas en
-- devinant une adresse, et un lien d'image circule bien plus loin qu'on ne
-- l'imagine. Le dashboard le sert lui-même, à qui a une session.
--
-- `bio` EST DU TEXTE ET NON DU HTML. Ce qui est écrit là part dans un PDF de
-- dossier; les retours à la ligne suffisent, et accepter du balisage ouvrirait
-- une porte qu'aucun besoin ne justifie.

ALTER TABLE rh_employe
    ADD COLUMN bio TEXT NULL
        COMMENT 'biographie de la personne, reprise dans les dossiers'
        AFTER fonction,
    ADD COLUMN photo VARCHAR(190) NULL
        COMMENT 'nom du fichier dans uploads/private/rh/<id>/, jamais servi par Apache'
        AFTER bio;

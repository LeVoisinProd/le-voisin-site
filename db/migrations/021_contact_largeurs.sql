-- Trois colonnes trop courtes pour ce qu'on y met. [16.08.2026]
--
-- Trouvées par l'importateur des 8432 contacts, qui a refusé de se taire: il
-- a compté les valeurs coupées au lieu de les tronquer en silence, et c'est ce
-- qui a permis de mesurer la bonne largeur au lieu de la deviner.
--
--   photo           64 caractères, il en faut 5307. Ce n'est pas un nom de
--                   fichier: le dashboard y met une image ENTIÈRE en data URI.
--                   C'est discutable — une image dans une colonne de texte
--                   gonfle chaque requête qui fait SELECT * — mais c'est ce
--                   qu'il y a, et la reprise doit être sans perte. TEXT plutôt
--                   qu'un VARCHAR géant: 60 fiches sur 8432 en portent une, et
--                   TEXT ne coûte rien aux 8372 autres.
--   participations  40, il en faut 44 aujourd'hui. Porté à 500, la longueur
--                   qu'écrit l'écran quand on coche plusieurs cases.
--   date_mois       16, il en faut 26. Douze mois écrits en toutes lettres et
--                   séparés par des virgules font 120 caractères; 160 laisse
--                   la marge.
--
-- `mots_cles` (109 sur 120) et `description` (67 sur 96) tiennent, mesuré. On
-- ne les élargit pas: une colonne élargie « au cas où » est une colonne dont
-- personne ne connaît plus la contrainte réelle.

ALTER TABLE contact
    MODIFY COLUMN photo          TEXT         NULL,
    MODIFY COLUMN participations VARCHAR(500) NULL,
    MODIFY COLUMN date_mois      VARCHAR(160) NULL;

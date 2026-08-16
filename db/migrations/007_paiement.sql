-- L'état de paiement d'une date. [16.08.2026]
--
-- POUR LE RELEVÉ. Anna a montré le « Statement » d'artistu comme modèle: une
-- ligne par date, le cachet, les frais, ce qui est déduit, ce qui reste dû, et
-- un état de versement par ligne. C'est ce qui manque pour répondre à la seule
-- question qui compte en fin de période: qui attend encore son argent.
--
-- DEUX ÉTATS ET PAS UN, parce que deux flux distincts se croisent sur la même
-- date et qu'on les confond aujourd'hui:
--
--   encaissement  le lieu a-t-il payé la cession
--   versement     l'artiste a-t-il été payé
--
-- Ils ne vont pas ensemble. Une date peut être encaissée sans que l'artiste ait
-- été payé, et c'est même le cas normal pendant quelques semaines. L'inverse
-- arrive aussi: on avance le salaire avant que le lieu ne règle, et c'est
-- précisément le trou de trésorerie que la réserve de trois mois doit couvrir.
-- Une seule colonne « payé » ne saurait pas dire lequel des deux.

ALTER TABLE booking
    ADD COLUMN encaissement ENUM('attendu','recu','partiel','sans_objet')
        NOT NULL DEFAULT 'attendu' AFTER statut,
    ADD COLUMN encaisse_le  DATE NULL AFTER encaissement,
    ADD COLUMN versement    ENUM('attendu','verse','partiel','sans_objet')
        NOT NULL DEFAULT 'attendu' AFTER encaisse_le,
    ADD COLUMN verse_le     DATE NULL AFTER versement,
    ADD KEY i_encaissement (encaissement, date_debut),
    ADD KEY i_versement    (versement, date_debut);

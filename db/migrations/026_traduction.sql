-- Les traductions, gardées et corrigeables. [16.08.2026]
--
-- POURQUOI UNE TABLE ET NON UN APPEL À CHAQUE IMPRESSION. Anna: « eu nao quero
-- escrever o segundo campo, quero que a traducao seja automatica ». D'accord —
-- mais traduire à la volée, à chaque ouverture, donne trois défauts qu'on ne
-- voit qu'après:
--
--   1. LE TEXTE CHANGE TOUT SEUL. Deux impressions du même dossier à deux jours
--      d'intervalle ne rendent pas exactement la même chose. On envoie un
--      dossier à un financeur, on le réimprime pour l'archive, et l'archive ne
--      correspond plus à ce qui est parti.
--   2. ON NE PEUT PAS CORRIGER. Une note d'intention traduite par une machine
--      se relit et se retouche; sans mémoire, la retouche est perdue à la
--      prochaine ouverture.
--   3. ÇA COÛTE ET ÇA RALENTIT à chaque fois, pour un texte qui n'a pas bougé.
--
-- LA CLEF EST L'EMPREINTE DU TEXTE SOURCE, pas l'identifiant du champ. Deux
-- spectacles qui portent la même phrase la traduisent une fois; et surtout, si
-- l'on corrige le texte français, l'empreinte change, donc la traduction
-- devient caduque d'elle-même au lieu de rester à traîner, périmée et
-- silencieuse. C'est le seul mécanisme qui empêche une vieille traduction de
-- survivre à son original.
--
-- `revise` EST LE CHAMP QUI COMPTE. Tant qu'il vaut 0, le document imprimé
-- porte un bandeau « traduction automatique, non relue » — visible à l'écran,
-- et il s'imprime. Une fois relue et mise à 1, le bandeau disparaît et la
-- traduction ne sera JAMAIS réécrite par la machine, même si l'on relance.
-- Sans cette garantie, personne ne prendrait la peine de corriger.

CREATE TABLE IF NOT EXISTS traduction (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- SHA-256 du texte source normalisé (espaces réduits, extrémités coupées).
    empreinte   CHAR(64)    NOT NULL,
    de_langue   CHAR(2)     NOT NULL DEFAULT 'fr',
    vers_langue CHAR(2)     NOT NULL,

    -- La source est gardée, et ce n'est pas une redondance: c'est ce qui permet
    -- de relire côte à côte au moment de corriger, sans remonter à la fiche.
    source      MEDIUMTEXT  NOT NULL,
    texte       MEDIUMTEXT  NOT NULL,

    moteur      VARCHAR(24) NULL COMMENT 'deepl, anthropic — qui a produit cette version',
    revise      TINYINT(1)  NOT NULL DEFAULT 0,
    revise_par  VARCHAR(96) NULL,
    revise_le   DATETIME    NULL,

    cree_le     DATETIME NOT NULL DEFAULT current_timestamp(),
    modifie_le  DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

    PRIMARY KEY (id),
    UNIQUE KEY u_txt (empreinte, de_langue, vers_langue),
    KEY k_revise (revise)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

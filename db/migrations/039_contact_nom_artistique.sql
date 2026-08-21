-- Le nom sous lequel la personne travaille.  [Anna, 21.08.2026]
--
-- « na fiche contact de chaque personne não tem nom artistique/usage », « tem
-- que ter todas as casas iguais ao espace utilisateur en ligne ».
--
-- L'ESPACE COLLABORATEUR L'A DEPUIS LE 14.08, LE CARNET D'ADRESSES NON. Le
-- raisonnement écrit alors dans `forms.php` vaut mot pour mot ici: « beaucoup
-- de personnes en ont un très différent du nom légal. Sans cette case, le seul
-- nom connu était celui du contrat, et c'est sous celui-là qu'on annonçait
-- quelqu'un dans un programme ou un dossier. »
--
-- CE QUI N'EST PAS REPRIS DE L'ESPACE, ET POURQUOI. Le profil en ligne demande
-- aussi la nationalité, le lieu et la date de naissance, l'état civil, l'AVS et
-- l'IBAN. Ces champs appartiennent à quelqu'un QUI TRAVAILLE POUR NOUS et qui
-- signe un contrat. Le carnet compte huit mille cinq cents fiches, dont des
-- programmateurs et des institutions: leur demander une date de naissance
-- serait collecter ce dont on n'a pas l'usage, et un champ qu'on ne remplit
-- jamais finit par faire douter de ceux qu'on remplit.
--
-- Tout le reste du bloc « identité et contact » de l'espace existait déjà ici:
-- nom, prénom, nom de famille, pronom, courriels, téléphones, adresse complète.

ALTER TABLE contact
    ADD COLUMN nom_artistique VARCHAR(190) NULL
        COMMENT 'nom d usage ou de scene, celui sous lequel on annonce la personne'
        AFTER nom_famille;

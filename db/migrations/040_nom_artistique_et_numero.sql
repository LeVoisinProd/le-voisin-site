-- Le nom artistique du personnel, et le numéro de rue séparé.  [Anna, 22.08.2026]
--
-- « coloca nessa listagem o nome artistico das pessoas », « na page personnel
-- ainda nao tem o campo nom artistique », « na pagina personnel so tem o campo
-- endereco, tem que colocar o campo numero separadamente, na parte contatos tb ».
--
-- LE NOM ARTISTIQUE N'EXISTAIT NULLE PART POUR LE PERSONNEL, et c'est mesuré:
-- `rh_employe` n'a aucune colonne de ce genre, et la colonne homonyme ajoutée
-- aux contacts le 21.08 est remplie sur 0 fiches de 7841. Une liste « par nom
-- artistique » n'aurait donc rien affiché du tout. On crée d'abord l'endroit
-- où l'écrire; il se remplira fiche à fiche.
--
-- IL NE REMPLACE PAS LE NOM OFFICIEL, IL S'AJOUTE À CÔTÉ. Ce sont deux noms
-- pour deux usages, et les confondre coûte cher dans les deux sens: un contrat,
-- une fiche de salaire ou une déclaration AVS au nom de scène est nul, et un
-- dossier de diffusion au nom d'état civil ne désigne pas l'artiste que le
-- programmateur connaît. Anna: le nom artistique dans le dossier, le nom
-- officiel dans la logistique.
--
-- LE NUMÉRO SE SÉPARE DE LA RUE parce qu'il ne se cherche pas de la même façon.
-- « Rue des Vieux-Grenadiers 10 » dans un seul champ ne se trie pas, ne se
-- compare pas d'une fiche à l'autre, et les administrations françaises et
-- suisses ne l'attendent pas au même endroit du formulaire — devant en France,
-- derrière en Suisse. Les valeurs déjà saisies restent où elles sont: on ne
-- découpe rien automatiquement, parce qu'un découpage à l'aveugle sur l'espace
-- casse « Chemin du 23-Août » et « Rue 1er-Mars ».

ALTER TABLE rh_employe
    ADD COLUMN nom_artistique VARCHAR(190) NULL
        COMMENT 'nom de scene ou nom d usage; le nom officiel reste dans prenom/nom'
        AFTER nom,
    ADD COLUMN numero VARCHAR(24) NULL
        COMMENT 'numero de rue, separe de la rue'
        AFTER rue;

ALTER TABLE contact
    ADD COLUMN numero VARCHAR(24) NULL
        COMMENT 'numero de rue, separe de adresse'
        AFTER adresse;

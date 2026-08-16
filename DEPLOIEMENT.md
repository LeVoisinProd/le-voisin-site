# Poser un paquet sur le serveur

> Écrit le 16.08.2026, en préparant l'installation du dashboard. La marche
> décrite ici a trouvé, ce jour-là, une page entière que le paquet s'apprêtait
> à effacer. Elle existe pour cela.

## La règle qui contient toutes les autres

**Comparer le dépôt et le serveur DANS LES DEUX SENS, avant de construire quoi
que ce soit.**

Ce qui manque au serveur, c'est le paquet. **Ce qui manque au dépôt, c'est du
travail qu'on s'apprête à écraser.** La deuxième moitié est celle qu'on oublie,
et c'est la seule qui détruit quelque chose.

Le 16.08.2026 la comparaison a montré que `app/views/team.php` faisait 88 lignes
sur le serveur et 21 dans le dépôt, et que `app/views/partials/apropos.php`,
222 lignes, n'existait pas du tout dans le dépôt. C'était la page « À propos »,
construite le 12.08 dans la mauvaise copie et posée par zip. Le paquet contenait
`team.php`. Installé tel quel, il aurait fait disparaître la page sans que rien
ne le signale.

## La marche

### 1. Personne d'autre ne construit

`git status` dans les deux copies, `le-voisin-site` et `le-voisin-local`. Rien
en attente. Deux sessions qui empaquettent le même site s'effacent l'une
l'autre, ficheiro à ficheiro — c'est arrivé le 13.08.2026 à 20h37.

### 2. Les empreintes, des deux côtés

```sh
# localement
git ls-files > /tmp/tracked.txt
while IFS= read -r f; do printf '%s  %s\n' "$(shasum -a 256 "$f" | cut -c1-16)" "$f"; done \
  < /tmp/tracked.txt | sort -k2 > /tmp/local.sha

# sur le serveur, la même liste
ssh levoisin "cd sites/test.le-voisin.com && while IFS= read -r f; do \
  if [ -f \"\$f\" ]; then printf '%s  %s\n' \"\$(sha256sum \"\$f\" | cut -c1-16)\" \"\$f\"; \
  else printf 'AUSENTE  %s\n' \"\$f\"; fi; done" < /tmp/tracked.txt | sort -k2 > /tmp/serveur.sha
```

Puis comparer **avec des ensembles, pas avec `comm`**: les deux listes n'ont pas
le même ordre de tri selon la locale, et `comm` rend alors n'importe quoi. Il a
menti le 16.08 avant qu'on s'en aperçoive.

Et lister **ce que le serveur a et que le dépôt n'a pas**:

```sh
ssh levoisin "cd sites/test.le-voisin.com && find . -type f \
  \( -name '*.php' -o -name '*.css' -o -name '*.js' \) \
  -not -path './uploads/*' -not -path './medias/*'" | sed 's|^\./||' | sort
```

Tout orphelin est soit à récupérer dans le dépôt, soit à supprimer du serveur.
Jamais à ignorer.

### 3. Le paquet, et ce qu'on en retire

Il contient ce qui manque au serveur, plus ce qu'on a modifié. **On en retire:**

| Fichier | Pourquoi |
|---|---|
| `.gitignore` | fichier de dépôt, pas de site |
| `.htaccess` | ne l'installer que si les règles changent VRAIMENT. Il porte le CMS et les refus du 11.08 |
| `config.php` | jamais. Il est dans le .gitignore et il porte la clé |
| tout ce que le serveur a de plus récent | voir l'étape 2 |

Le paquet est construit **depuis HEAD**, jamais depuis des fichiers choisis à la
main dans des états différents. Vérifier: chaque fichier du paquet doit être
identique au fichier du dépôt, `cmp` à l'appui.

### 4. Poser, puis MESURER

Le `installateur.php` ne fusionne rien: le dernier qui installe gagne, fichier
par fichier.

Après l'installation, ne pas croire l'écran. Reprendre les empreintes de
l'étape 2 et vérifier que les fichiers du paquet ont bien changé. Le 13.08 une
installation donnée pour faite ne l'avait jamais été: il y avait deux zips au
nom proche dans le dossier et c'est la mauvaise qui était partie.

### 5. Les migrations vont AVEC le code, jamais avant

```sh
ssh levoisin 'cd sites/test.le-voisin.com && /opt/php8.4/bin/php db/migrer.php --etat'
ssh levoisin 'cd sites/test.le-voisin.com && /opt/php8.4/bin/php db/migrer.php'
```

Un schéma posé avant son code donne une base qui dit qu'une fonctionnalité
existe et un site qui dit le contraire. Et une migration appliquée puis
modifiée est signalée « déjà appliquée mais le fichier a changé », sans retour
possible: on ne les applique donc pas tant que le code qui s'en sert n'est pas
stable.

### 6. Avant toute écriture en base: un dump

```sh
ssh levoisin 'bash -s' < dados/site_espace_projets/dump_base_site.sh   # dépôt de travail
```

Le rapatrier dans le Drive, le nommer `_AVANT-<ce qu'on va faire>`, et en
reprendre un après. Les deux permettent de comparer; un seul ne permet rien.

## Ce qui traîne sur le serveur et qu'il faudra retirer

Mesuré le 16.08.2026, pas encore fait:

- **`le-voisin-site/`**, 307 fichiers et 11 Mo à la racine web, depuis le
  05.08.2026: une copie complète du site, déballée là par erreur. Elle porte
  son propre `install/index.php`, qui **répond 200**. Il s'arrête aux
  prérequis et n'offre pas de formulaire, mais un installateur de CMS joignable
  sur un site en production n'a aucune raison d'exister
- **`zzz-version.php`**, la marque de diagnostic du 12.08 qui a servi à prouver
  que le cache d'opcode ne relit pas `index.php`. Elle répond 200 et rend
  `ZZZ_VERSION_OK_V42`

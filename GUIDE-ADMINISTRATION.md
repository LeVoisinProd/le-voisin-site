# Le Voisin — Guide de l'administration

L'administration est accessible sur **`/admin/`** avec votre email et votre mot de
passe. Aucune connaissance technique n'est nécessaire.

## Les grands principes

- **Deux langues partout.** Chaque contenu a un onglet *English* et un onglet
  *Français*. Si une traduction manque, le site affiche automatiquement la
  version anglaise (langue par défaut) — rien n'est jamais vide.
- **Enregistrer.** Les textes et réglages d'une fiche sont enregistrés avec le
  bouton **Enregistrer**. Les photos, vidéos et documents, eux, sont enregistrés
  immédiatement dès l'ajout.
- **En ligne / Hors ligne.** Chaque page et chaque fiche a un interrupteur
  *Publié*. Une nouvelle fiche est créée hors ligne : remplissez-la, puis publiez.

## Structure & pages

C'est l'arborescence du site : elle définit le **menu** et les adresses des pages.

- **Glissez** les pages pour changer leur ordre, y compris d'un niveau à l'autre.
- **« + »** crée une sous-page ; **« + Nouvelle page »** crée une page au niveau principal.
- Chaque page peut être une **page de texte** (contenu libre dans l'éditeur) ou
  porter un **module** (Projets, Artistes, Agenda, Équipe, un formulaire…) : le
  module s'affiche alors sous le texte de la page.
- *Afficher dans le menu* : décochez pour une page accessible mais hors menu
  (ex. politique de confidentialité).

## L'éditeur de texte (WYSIWYG)

L'éditeur reprend **exactement les styles du site** : ce que vous voyez est ce qui
sera affiché. Le menu *Paragraphe / Titre de section / Sous-titre / Introduction /
Citation* applique les styles officiels — pas de mise en forme exotique, votre
site reste cohérent.

- **Liens** : le bouton lien propose la liste de vos pages, projets et artistes
  (liens internes) ou une adresse externe.
- **Images dans le texte** : bouton image → l'image est automatiquement
  redimensionnée et optimisée.

## Photos

- **Glisser-déposer** une ou plusieurs images (JPG, PNG, WebP) — elles sont
  automatiquement converties en **WebP** optimisé et déclinées dans les formats
  prédéfinis des maquettes.
- **Recadrer** : le bouton en forme de ciseaux ouvre le recadrage ; les
  proportions du format choisi sont conservées automatiquement.
- **Texte alternatif** FR/EN sous chaque vignette : décrivez l'image en une
  phrase (important pour le référencement et l'accessibilité).
- Glissez les vignettes pour les **réordonner**.

## Vidéos

Collez simplement un lien **YouTube, Vimeo ou Dailymotion** → le titre et la
vignette sont récupérés automatiquement. Si l'ID de la chaîne YouTube est
renseigné dans *Réglages*, le bouton **« Choisir dans la chaîne »** liste les
dernières vidéos : un clic suffit pour les ajouter.

Sur le site, les vidéos respectent le consentement cookies : tant que le visiteur
n'a pas accepté les « médias externes », une vignette avec bouton d'autorisation
s'affiche à la place du lecteur.

## Documents

Glissez vos PDF (ou Word/Excel/ZIP) : ils apparaissent en listing sur la page.
La **couverture** est extraite automatiquement de la première page du PDF quand
le serveur le permet. Le titre est modifiable en FR et EN.

## Modules

- **Projets** : titre, image représentative, introduction, texte, galerie,
  vidéos, documents, **catégories** et **artistes liés**. Les liens croisés
  (projet ↔ artiste ↔ agenda) s'affichent automatiquement sur le site.
- **Artistes** : mêmes possibilités ; la fiche montre automatiquement les
  projets et les dates liés.
- **Agenda (On Tour)** : la *date affichée* est un texte libre
  (« 12–14 décembre 2026 »), la *date de classement* sert au tri automatique
  (à venir / passées). Liez un artiste et/ou un projet : si aucune image n'est
  choisie, **celle du projet ou de l'artiste est utilisée automatiquement**.
  Les visiteurs peuvent filtrer par artiste ou projet.
- **Équipe** : prénom, nom, fonction FR/EN, photo, biographie, crédit photo.
- **Catégories** : les catégories de projets (Danse, Musique…), réordonnables.

## Référencement (SEO)

Sur chaque page et chaque fiche, le panneau **Référencement (SEO)** permet de
définir le méta-titre, la méta-description et l'**image de partage** pour les
réseaux sociaux. Laissez vide pour utiliser automatiquement le titre,
l'introduction et l'image représentative. Le plan du site (`/sitemap.xml`) et
les balises multilingues (hreflang) sont générés automatiquement.

## Formulaires

Dans **Réglages → Formulaires** :

- **Destinataires** des envois « Infos personnelles » et « Factures / dépenses »
  (plusieurs adresses possibles, séparées par des virgules).
- **Adresse BEXIO** : chaque note de frais y est aussi envoyée automatiquement,
  pièces jointes comprises — prête à être importée dans BEXIO.
- La liste des **associations** proposées dans le formulaire de dépenses
  (une par ligne).
- Le menu « Artiste concerné » du formulaire d'infos personnelles se remplit
  automatiquement depuis le module Artistes.

## Cookies & statistiques

Dans **Réglages → Statistiques & cookies** :

- **Version avancée** (recommandée) : le visiteur choisit par catégorie
  (mesure d'audience / médias externes) et peut changer d'avis à tout moment
  via « Gérer les cookies » dans le pied de page.
- **Version simple** : un bandeau d'information avec un bouton OK.
- Les textes du bandeau sont personnalisables en FR et EN.
- L'identifiant **Google Analytics** ne se charge qu'après consentement.

## Utilisateurs

Créez un compte par personne (10 caractères minimum pour le mot de passe).
Chaque compte peut changer son mot de passe ; il faut toujours au moins un compte.

## Bons réflexes

1. Préparez des images **suffisamment grandes** (2000 px de large ou plus) :
   le CMS s'occupe de tout le reste.
2. Remplissez les deux langues quand c'est possible — sinon l'anglais s'affiche.
3. Pensez aux **textes alternatifs** des images et aux méta-descriptions des
   pages importantes : c'est ce qui nourrit le référencement.

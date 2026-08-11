# Le Voisin — CMS sur mesure · Guide d'installation

CMS développé sur mesure pour le site **le-voisin.com** : administration simple et
intuitive, contenus bilingues **EN/FR** (repli automatique sur l'anglais), images
optimisées **WebP**, modules Projets / Artistes / Agenda (On Tour) / Équipe,
formulaires avec envoi par email (+ copie BEXIO) et bandeau cookies conforme.

## 1. Prérequis serveur

- PHP **8.0 ou plus** (8.1+ recommandé) avec les extensions : `pdo_mysql`, `gd`
  (avec WebP), `mbstring`, `curl`. Facultatif : `imagick` (couvertures de PDF automatiques).
- Une base de données **MySQL / MariaDB** (utf8mb4).
- Apache avec `mod_rewrite` (le fichier `.htaccess` fourni s'en occupe).

L'hébergement **Infomaniak** actuel répond à toutes ces exigences. Dans le Manager
Infomaniak, vérifiez que la version PHP du site est réglée sur 8.1 ou plus
(Hébergement → Paramètres avancés → PHP).

## 2. Installation (10 minutes)

1. **Base de données** : créez une base MySQL et un utilisateur
   (Manager Infomaniak → Bases de données). Notez le nom, l'utilisateur et le mot de passe.
2. **Fichiers** : transférez tout le contenu du dossier dans la racine web du site
   (par FTP ou le gestionnaire de fichiers). Aucune compilation n'est nécessaire.
3. **Assistant** : ouvrez `https://votre-domaine/install/` dans le navigateur.
   L'assistant vérifie les prérequis, demande les accès à la base, l'URL du site
   et crée votre compte administrateur. Cochez « contenu de démonstration » pour
   partir de la structure actuelle du site (pages, catégories, exemples).
4. **Sécurité** : une fois l'installation terminée, **supprimez le dossier `/install`**.
5. Connectez-vous à `https://votre-domaine/admin/` et, dans **Réglages** :
   - renseignez les **destinataires des formulaires** (et l'adresse BEXIO le cas échéant) ;
   - renseignez l'**expéditeur des emails** (une adresse du domaine, ex. `no-reply@le-voisin.com`) ;
   - ajoutez l'identifiant **Google Analytics** si souhaité (chargé uniquement après consentement).

## 3. Ce qui est inclus

| Élément | Détail |
|---|---|
| CMS sur mesure | Gestion de la structure (arborescence), pages, éditeur WYSIWYG aux styles du site |
| Multilingue | EN + FR partout ; si un contenu n'est pas traduit, la version anglaise s'affiche |
| Module Photos | Glisser-déposer, formats prédéfinis, recadrage dans le CMS, WebP + repli JPEG |
| Module Vidéos | YouTube / Vimeo / Dailymotion par simple lien (titre + vignette automatiques), flux de chaîne YouTube |
| Module Documents | Listing avec couverture (extraite du PDF si Imagick est disponible) |
| Module Projets | Titre, image, intro, texte, galerie, vidéos, documents, catégories, artistes liés |
| Module Artistes | Fiche complète + projets et agenda liés (liens croisés pour le SEO) |
| Module Agenda | On Tour : dates, artiste/projet lié, lieu + lien, image automatique, filtres |
| Module Équipe | Nom, prénom, fonction, photo, bio, crédit photo |
| Formulaires | « Infos personnelles » et « Factures / justificatifs » — mêmes champs que le site actuel, destinataires gérés dans le CMS, copie BEXIO avec pièces jointes |
| Cookies | Bandeau version simple **ou** avancée (choix par catégorie), textes modifiables, scripts chargés selon le consentement |
| SEO | Meta titre/description et image de partage (OG) par page et par fiche, sitemap.xml, hreflang, URLs propres |
| Statistiques | Google Analytics intégré, activé uniquement après consentement |

## 4. Organisation technique

```
/index.php            Contrôleur du site public (routage /en/…, /fr/…)
/.htaccess            Réécriture d'URL + protections
/app/                 Cœur applicatif (protégé, inaccessible par le web)
  bootstrap.php       Amorçage, helpers
  lib/                DB, Auth, I18n, Img, Mailer, VideoLib, Docs, Forms, …
  config/             formats d'images, modules (entities), formulaires
  views/              Templates du site public
  i18n/               Libellés d'interface EN/FR
/admin/               Interface d'administration (+ API AJAX)
/assets/              CSS/JS du site public
/uploads/             Médias (originaux + déclinaisons générées)
/install/             Assistant d'installation + schéma SQL (à supprimer ensuite)
```

- **Base** : requêtes préparées PDO uniquement (pas d'injection SQL possible).
- **Sécurité** : sessions httponly/SameSite, CSRF sur toute écriture, limitation
  des tentatives de connexion, mots de passe hachés (bcrypt), aucun script
  exécutable dans `/uploads`.
- **Design** : basé sur le langage graphique du thème **Tortoise** (wwwows)
  utilisé par le site actuel — monochrome noir/blanc, filets épais, boutons
  pilule — avec la police **Space Grotesk** auto-hébergée (licence SIL OFL,
  `assets/fonts/`, aucun appel à Google Fonts : conforme protection des données).
  Tout le graphisme public est concentré dans `assets/css/site.css` (variables
  en tête de fichier) : à l'arrivée des maquettes définitives du graphiste,
  seuls les templates (`app/views/`) et ce fichier CSS sont à adapter — le CMS
  et les contenus restent inchangés.

## 5. Ajouter un module ou un champ (évolutivité)

Les modules d'administration sont générés depuis `app/config/entities.php`.
Ajouter un champ = une ligne de configuration (+ la colonne SQL correspondante).
Ajouter un module = une entrée dans ce fichier + sa table + un template public.
Aucune limite du système : tout est développé sur mesure.

## 6. Mise en production depuis l'ancien site

1. Installer le CMS sur un sous-domaine de test (ex. `nouveau.le-voisin.com`).
2. Saisir/copier les contenus réels dans l'administration (textes, photos, vidéos).
3. Le jour du basculement : pointer le domaine sur le nouveau dossier.
4. L'ancien site WordPress peut ensuite être archivé puis supprimé.
   ⚠️ Voir la note de sécurité transmise avec la livraison au sujet du site actuel.

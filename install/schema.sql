-- ============================================================
-- Le Voisin — CMS sur mesure — Schéma de base de données
-- MySQL / MariaDB, utf8mb4
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL DEFAULT '',
  pass_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(64) NOT NULL,
  email VARCHAR(190) NOT NULL DEFAULT '',
  at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ip_at (ip, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [V39-JOURNAL] Qui a telecharge quel document, qui a ouvert l'espace de
-- qui. 'actor' vaut 'member' (la personne elle-meme) ou 'admin' (le bureau,
-- pendant une visite) ; 'actor_id' porte alors l'identifiant admin (users).
CREATE TABLE IF NOT EXISTS access_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  collaborator_id INT UNSIGNED NOT NULL,
  actor VARCHAR(10) NOT NULL,
  actor_id INT UNSIGNED NULL,
  action VARCHAR(20) NOT NULL,
  detail VARCHAR(255) NOT NULL DEFAULT '',
  ip VARCHAR(60) NOT NULL DEFAULT '',
  KEY collaborator_at (collaborator_id, at),
  KEY at (at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  skey VARCHAR(120) NOT NULL PRIMARY KEY,
  sval MEDIUMTEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Structure du site : arborescence de pages.
-- Une page peut porter un module (projects, artists, agenda, team,
-- form_infos, form_expenses) : elle affiche alors ce module.
CREATE TABLE IF NOT EXISTS pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  sort INT NOT NULL DEFAULT 0,
  template VARCHAR(40) NOT NULL DEFAULT 'standard',
  module VARCHAR(40) NULL,
  visible TINYINT(1) NOT NULL DEFAULT 1,
  in_nav TINYINT(1) NOT NULL DEFAULT 1,
  title_en VARCHAR(255) NOT NULL DEFAULT '',
  title_fr VARCHAR(255) NOT NULL DEFAULT '',
  slug_en VARCHAR(255) NOT NULL DEFAULT '',
  slug_fr VARCHAR(255) NOT NULL DEFAULT '',
  body_en MEDIUMTEXT,
  body_fr MEDIUMTEXT,
  meta_title_en VARCHAR(255) NOT NULL DEFAULT '',
  meta_title_fr VARCHAR(255) NOT NULL DEFAULT '',
  meta_desc_en VARCHAR(500) NOT NULL DEFAULT '',
  meta_desc_fr VARCHAR(500) NOT NULL DEFAULT '',
  og_image_id INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY parent_sort (parent_id, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sort INT NOT NULL DEFAULT 0,
  name_en VARCHAR(190) NOT NULL DEFAULT '',
  name_fr VARCHAR(190) NOT NULL DEFAULT '',
  slug_en VARCHAR(190) NOT NULL DEFAULT '',
  slug_fr VARCHAR(190) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sort INT NOT NULL DEFAULT 0,
  visible TINYINT(1) NOT NULL DEFAULT 1,
  title_en VARCHAR(255) NOT NULL DEFAULT '',
  title_fr VARCHAR(255) NOT NULL DEFAULT '',
  slug_en VARCHAR(255) NOT NULL DEFAULT '',
  slug_fr VARCHAR(255) NOT NULL DEFAULT '',
  intro_en TEXT,
  intro_fr TEXT,
  body_en MEDIUMTEXT,
  body_fr MEDIUMTEXT,
  distribution_en MEDIUMTEXT,
  distribution_fr MEDIUMTEXT,
  infos_en MEDIUMTEXT,
  infos_fr MEDIUMTEXT,
  cover_image_id INT UNSIGNED NULL,
  meta_title_en VARCHAR(255) NOT NULL DEFAULT '',
  meta_title_fr VARCHAR(255) NOT NULL DEFAULT '',
  meta_desc_en VARCHAR(500) NOT NULL DEFAULT '',
  meta_desc_fr VARCHAR(500) NOT NULL DEFAULT '',
  og_image_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY vis_sort (visible, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS artists (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sort INT NOT NULL DEFAULT 0,
  visible TINYINT(1) NOT NULL DEFAULT 1,
  status VARCHAR(16) NOT NULL DEFAULT 'current',
  name VARCHAR(255) NOT NULL DEFAULT '',
  slug_en VARCHAR(255) NOT NULL DEFAULT '',
  slug_fr VARCHAR(255) NOT NULL DEFAULT '',
  intro_en TEXT,
  intro_fr TEXT,
  body_en MEDIUMTEXT,
  body_fr MEDIUMTEXT,
  spotify_url VARCHAR(500) NOT NULL DEFAULT '',
  instagram_url VARCHAR(1000) NOT NULL DEFAULT '',
  website_url VARCHAR(500) NOT NULL DEFAULT '',   -- [V31-SITE-ARTISTE]
  cover_image_id INT UNSIGNED NULL,
  meta_title_en VARCHAR(255) NOT NULL DEFAULT '',
  meta_title_fr VARCHAR(255) NOT NULL DEFAULT '',
  meta_desc_en VARCHAR(500) NOT NULL DEFAULT '',
  meta_desc_fr VARCHAR(500) NOT NULL DEFAULT '',
  og_image_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY vis_sort (visible, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_categories (
  project_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (project_id, category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_artists (
  project_id INT UNSIGNED NOT NULL,
  artist_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (project_id, artist_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agenda / On Tour
CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visible TINYINT(1) NOT NULL DEFAULT 1,
  image_id INT UNSIGNED NULL,
  date_text_en VARCHAR(190) NOT NULL DEFAULT '',
  date_text_fr VARCHAR(190) NOT NULL DEFAULT '',
  date_sort DATE NOT NULL,
  date_end DATE NULL,
  artist_id INT UNSIGNED NULL,
  project_id INT UNSIGNED NULL,
  venue VARCHAR(255) NOT NULL DEFAULT '',
  venue_url VARCHAR(500) NOT NULL DEFAULT '',
  city VARCHAR(190) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY date_sort_idx (visible, date_sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_members (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sort INT NOT NULL DEFAULT 0,
  visible TINYINT(1) NOT NULL DEFAULT 1,
  first_name VARCHAR(190) NOT NULL DEFAULT '',
  last_name VARCHAR(190) NOT NULL DEFAULT '',
  role_en VARCHAR(190) NOT NULL DEFAULT '',
  role_fr VARCHAR(190) NOT NULL DEFAULT '',
  bio_en MEDIUMTEXT,
  bio_fr MEDIUMTEXT,
  image_id INT UNSIGNED NULL,
  photo_credit VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Médias : une ligne par image originale ; les déclinaisons
-- (formats + recadrages) sont générées sur disque.
CREATE TABLE IF NOT EXISTS images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type VARCHAR(40) NOT NULL DEFAULT '',
  owner_id INT UNSIGNED NOT NULL DEFAULT 0,
  zone VARCHAR(40) NOT NULL DEFAULT 'gallery',
  ext VARCHAR(8) NOT NULL DEFAULT 'jpg',
  width INT NOT NULL DEFAULT 0,
  height INT NOT NULL DEFAULT 0,
  alt_en VARCHAR(255) NOT NULL DEFAULT '',
  alt_fr VARCHAR(255) NOT NULL DEFAULT '',
  sort INT NOT NULL DEFAULT 0,
  crops TEXT NULL,
  focus VARCHAR(24) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY owner (owner_type, owner_id, zone, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS videos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type VARCHAR(40) NOT NULL DEFAULT '',
  owner_id INT UNSIGNED NOT NULL DEFAULT 0,
  provider VARCHAR(20) NOT NULL DEFAULT '',
  vid VARCHAR(64) NOT NULL DEFAULT '',
  url VARCHAR(500) NOT NULL DEFAULT '',
  title VARCHAR(255) NOT NULL DEFAULT '',
  thumb VARCHAR(500) NOT NULL DEFAULT '',
  duration SMALLINT UNSIGNED NOT NULL DEFAULT 6,
  sort INT NOT NULL DEFAULT 0,
  KEY owner (owner_type, owner_id, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type VARCHAR(40) NOT NULL DEFAULT '',
  owner_id INT UNSIGNED NOT NULL DEFAULT 0,
  -- [V31-PRESSE] Deux listes sur une même fiche : « doc » pour les documents
  -- à télécharger, « press » pour la revue de presse. Même table, même
  -- machinerie ; seul l'endroit où la ligne s'affiche change.
  zone VARCHAR(40) NOT NULL DEFAULT 'doc',
  title_en VARCHAR(255) NOT NULL DEFAULT '',
  title_fr VARCHAR(255) NOT NULL DEFAULT '',
  filename VARCHAR(255) NOT NULL DEFAULT '',
  -- [V31-DOC-LIEN] Un document est soit un fichier hébergé ici (filename),
  -- soit un lien vers un fichier hébergé ailleurs (url). Jamais les deux.
  url VARCHAR(1000) NOT NULL DEFAULT '',
  ext VARCHAR(10) NOT NULL DEFAULT '',
  size INT UNSIGNED NOT NULL DEFAULT 0,
  cover_image_id INT UNSIGNED NULL,
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY owner (owner_type, owner_id, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copie de sécurité optionnelle des envois de formulaires
-- (désactivée par défaut, réglable dans le CMS).
CREATE TABLE IF NOT EXISTS submissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  form VARCHAR(40) NOT NULL,
  data MEDIUMTEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS collaborators (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL,
  mobile VARCHAR(40) NOT NULL DEFAULT '',
  lang VARCHAR(5) NOT NULL DEFAULT 'fr',
  pass_hash VARCHAR(255) NOT NULL DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  reset_token VARCHAR(64) NULL,
  reset_expires DATETIME NULL,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  collaborator_id INT UNSIGNED NOT NULL,
  category VARCHAR(20) NOT NULL DEFAULT 'other',
  -- [V32-DOC-ASSO] L'association pour laquelle ce document a ete depose, ecrite
  -- sous son NOM et non sous un identifiant : les associations vivent dans un
  -- reglage en texte libre (form_assoc_options), elles n'ont pas de table ni
  -- d'id. Vide = document sans association, affiche comme avant.
  assoc VARCHAR(120) NOT NULL DEFAULT '',
  project_id INT UNSIGNED NULL,
  -- [V36-FACTURES] Qui a depose le document : 'admin' (le bureau) ou 'member'
  -- (la personne, depuis son espace). Ce n'est pas une trace administrative :
  -- c'est ce qui donne son sens au statut ci-dessous. Sur une facture deposee
  -- par la personne, 'received' veut dire que l'argent est arrive ; sur une
  -- fiche de salaire deposee par le bureau, que le document est arrive.
  uploaded_by VARCHAR(10) NOT NULL DEFAULT 'admin',
  -- '' (rien a dire) | 'sent' Envoyee | 'paid' Payee | 'received' Recue.
  status VARCHAR(12) NOT NULL DEFAULT '',
  -- La date du dernier changement de statut, pour pouvoir ecrire « Payee le
  -- 12.08.2026 » plutot qu'un mot sans repere.
  status_at DATETIME NULL,
  title VARCHAR(255) NOT NULL DEFAULT '',
  filename VARCHAR(255) NOT NULL DEFAULT '',
  ext VARCHAR(8) NOT NULL DEFAULT 'pdf',
  size INT UNSIGNED NOT NULL DEFAULT 0,
  needs_signature TINYINT(1) NOT NULL DEFAULT 0,
  sign_status VARCHAR(12) NOT NULL DEFAULT 'none',
  skribble_request_id VARCHAR(80) NOT NULL DEFAULT '',
  skribble_signing_url VARCHAR(600) NOT NULL DEFAULT '',
  signed_filename VARCHAR(255) NOT NULL DEFAULT '',
  signed_at DATETIME NULL,
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY owner (collaborator_id, category, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_profiles (
  collaborator_id INT UNSIGNED NOT NULL PRIMARY KEY,
  data MEDIUMTEXT,
  -- [V17-BIO] 2400 pour une limite affichee de 2000 : la marge evite qu'un
  -- caractere compte double quelque part et fasse sauter la derniere lettre.
  -- La limite reelle est tenue par le formulaire, pas par la colonne.
  bio VARCHAR(2400) NOT NULL DEFAULT '',
  -- [V31-FICHE-DEJA] Ce que le bureau sait deja de la personne et lui propose
  -- d'avance : adresse, naissance, AVS, IBAN... Separe de « data », qui ne
  -- contient que ce que la personne a elle-meme ecrit et relu.
  prefill MEDIUMTEXT NULL,
  photo_image_id INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

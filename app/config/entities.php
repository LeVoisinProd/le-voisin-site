<?php
/**
 * Description des modules de contenu : chaque module de l'administration
 * (listes + formulaires d'édition) est généré depuis cette configuration.
 * Ajouter un module = ajouter une entrée ici + sa table.
 *
 * [V10-CMS-BILINGUE] — l'administration existe maintenant en français et en
 * anglais. Tous les textes affichés ('label', 'plural', 'menu', 'help' et les
 * 'options' des listes déroulantes) s'écrivent donc sous la forme
 *
 *     ['fr' => 'Texte français', 'en' => 'English text']
 *
 * La fonction tc() choisit la bonne version selon la langue choisie dans
 * l'administration. Une simple chaîne de caractères reste acceptée : elle
 * s'affiche telle quelle dans les deux langues (pratique pour un mot
 * identique en français et en anglais, ou pour ajouter vite un champ).
 *
 * [V14-DUPLIQUER] Deux clefs facultatives commandent la duplication :
 *
 *     'duplicable' => true      affiche le bouton ⧉ dans la liste et le
 *                               bouton « Enregistrer et dupliquer » dans la
 *                               fiche. La copie est toujours créée hors ligne.
 *     'dup_note'   => [...]     fin de la phrase affichée après duplication,
 *                               pour dire quoi modifier dans la copie.
 */
return [

    'project' => [
        'table'    => 'projects',
        'label'    => ['fr' => 'Projet',  'en' => 'Project'],
        'plural'   => ['fr' => 'Projets', 'en' => 'Projects'],
        'menu'     => ['fr' => 'Projets', 'en' => 'Projects'],
        'orderby'  => 'sort, id',
        'sortable' => true,
        'duplicable' => true,
        'list'     => ['cover' => 'cover_image_id', 'title' => 'title', 'extra' => 'categories'],
        'fields'   => [
            'title'          => ['type' => 'i18n_text', 'required' => true,
                                 'label' => ['fr' => 'Titre', 'en' => 'Title']],
            'status'         => ['type' => 'select_static',
                                 'label' => ['fr' => 'Statut', 'en' => 'Status'],
                                 'options' => [
                                     'current' => ['fr' => 'Projet actuel', 'en' => 'Current project'],
                                     'former'  => ['fr' => 'Ancien projet', 'en' => 'Past project'],
                                 ],
                                 'help' => ['fr' => 'Les anciens projets apparaissent sur une page séparée du site (les projets actuels sont ceux en tournée).',
                                            'en' => 'Past projects appear on a separate page of the site (current projects are the ones on tour).']],
            'slug'           => ['type' => 'i18n_slug', 'from' => 'title',
                                 'label' => ['fr' => 'Adresse (slug)', 'en' => 'Address (slug)']],
            'cover_image_id' => ['type' => 'image', 'zone' => 'cover',
                                 'label' => ['fr' => 'Image représentative', 'en' => 'Main image']],
            'intro'          => ['type' => 'i18n_textarea',
                                 'label' => ['fr' => 'Texte d\'introduction', 'en' => 'Introduction text']],
            'body'           => ['type' => 'i18n_html',
                                 'label' => ['fr' => 'Texte descriptif', 'en' => 'Descriptive text']],
            'distribution'   => ['type' => 'i18n_html',
                                 'label' => ['fr' => 'Distribution', 'en' => 'Credits'],
                                 'help' => ['fr' => 'Une ligne par fonction — ex. « Mise en scène — Prénom Nom ». Affiché dans la colonne de droite de la fiche.',
                                            'en' => 'One line per role — e.g. “Director — First name Last name”. Displayed in the right-hand column of the page.']],
            'infos'          => ['type' => 'i18n_html',
                                 'label' => ['fr' => 'Infos pratiques', 'en' => 'Practical information'],
                                 'help' => ['fr' => 'Durée, âge conseillé, billetterie… Affiché dans la colonne de droite de la fiche.',
                                            'en' => 'Running time, recommended age, ticketing… Displayed in the right-hand column of the page.']],
            /* [V16-CATEGORIES] Un projet peut relever de plusieurs catégories —
               une pièce dansée et musicale en est une seule fois. Les cases se
               cochent donc librement, autant qu'on veut ; la mention ci-dessous
               le dit, parce qu'une suite de cases se lit souvent comme un choix
               unique tant que personne n'a essayé d'en cocher deux. */
            'categories'     => ['type' => 'rel_multi', 'entity' => 'category',
                                 'label' => ['fr' => 'Catégories', 'en' => 'Categories'],
                                 'help'  => ['fr' => 'Plusieurs catégories sont possibles : cochez toutes celles qui conviennent. Le projet apparaîtra dans chacune d\'elles, en français comme en anglais.',
                                             'en' => 'Several categories are allowed: tick every one that applies. The project will appear under each of them, in both French and English.'],
                                 'pivot' => 'project_categories', 'fk' => 'project_id', 'ok' => 'category_id', 'display' => 'name'],
            'artists'        => ['type' => 'rel_multi', 'entity' => 'artist',
                                 'label' => ['fr' => 'Artistes liés', 'en' => 'Linked artists'],
                                 'pivot' => 'project_artists', 'fk' => 'project_id', 'ok' => 'artist_id', 'display' => 'name'],
            'gallery'        => ['type' => 'gallery', 'zone' => 'gallery',
                                 'label' => ['fr' => 'Galerie photos', 'en' => 'Photo gallery']],
            'videos'         => ['type' => 'videos',
                                 'label' => ['fr' => 'Vidéos', 'en' => 'Videos']],
            /* [V31-PRESSE] La revue de presse. Même bloc que les documents —
               on dépose un PDF ou on colle une adresse — mais rangée dans sa
               propre liste (« zone ») et affichée ailleurs sur la fiche :
               dans la colonne de droite, au-dessus des infos pratiques,
               tandis que les documents restent en dessous.

               Deux listes plutôt qu'une seule, parce qu'un article de presse
               et une fiche technique ne s'adressent pas aux mêmes yeux : on
               télécharge l'une, on lit l'autre. Mélangées, la fiche technique
               se perdait entre deux articles. */
            'press'          => ['type' => 'documents', 'zone' => 'press', 'mots' => 'press',
                                 'label' => ['fr' => 'Presse', 'en' => 'Press'],
                                 'help'  => ['fr' => 'Les articles parus sur le projet. Ils s\'affichent dans la colonne de droite de la fiche, au-dessus des infos pratiques. Un article lisible en ligne s\'ajoute par son adresse ; un article de journal papier, ou d\'un site qui pourrait fermer, s\'ajoute en PDF.',
                                             'en' => 'Articles published about the project. They appear in the right-hand column of the page, above the practical information. An article that can be read online is added by its address; one from a printed paper, or from a site that might close, is added as a PDF.']],
            'documents'      => ['type' => 'documents', 'zone' => 'doc',
                                 'label' => ['fr' => 'Documents', 'en' => 'Documents']],
            'visible'        => ['type' => 'toggle',
                                 'label' => ['fr' => 'Publié', 'en' => 'Published']],
            'seo'            => ['type' => 'seo',
                                 'label' => ['fr' => 'Référencement (SEO)', 'en' => 'Search engines (SEO)']],
        ],
    ],

    'artist' => [
        'table'    => 'artists',
        'label'    => ['fr' => 'Artiste',  'en' => 'Artist'],
        'plural'   => ['fr' => 'Artistes', 'en' => 'Artists'],
        'menu'     => ['fr' => 'Artistes', 'en' => 'Artists'],
        'orderby'  => 'sort, id',
        'sortable' => true,
        'duplicable' => true,
        'list'     => ['cover' => 'cover_image_id', 'title' => 'name'],
        'fields'   => [
            'name'           => ['type' => 'text', 'required' => true,
                                 'label' => ['fr' => 'Nom', 'en' => 'Name']],
            'status'         => ['type' => 'select_static',
                                 'label' => ['fr' => 'Collaboration', 'en' => 'Collaboration'],
                                 'options' => [
                                     'current' => ['fr' => 'Artiste actuel',         'en' => 'Current artist'],
                                     'former'  => ['fr' => 'Ancienne collaboration', 'en' => 'Past collaboration'],
                                 ],
                                 'help' => ['fr' => 'Les anciennes collaborations apparaissent sur une page séparée du site.',
                                            'en' => 'Past collaborations appear on a separate page of the site.']],
            'slug'           => ['type' => 'i18n_slug', 'from' => 'name',
                                 'label' => ['fr' => 'Adresse (slug)', 'en' => 'Address (slug)']],
            'cover_image_id' => ['type' => 'image', 'zone' => 'cover',
                                 'label' => ['fr' => 'Image représentative', 'en' => 'Main image']],
            'intro'          => ['type' => 'i18n_textarea',
                                 'label' => ['fr' => 'Texte d\'introduction', 'en' => 'Introduction text']],
            'body'           => ['type' => 'i18n_html',
                                 'label' => ['fr' => 'Texte descriptif', 'en' => 'Descriptive text']],
            /* [V26-SPOTIFY] Un seul champ suffit : le lecteur est construit
               à partir de l'identifiant contenu dans l'adresse partagée.
               'check' => 'spotify' fait vérifier l'adresse à l'enregistrement,
               plutôt que de laisser un lien faux passer sans rien dire. */
            'spotify_url'    => ['type' => 'url', 'check' => 'spotify',
                                 'label' => ['fr' => 'Lien Spotify', 'en' => 'Spotify link'],
                                 'help'  => ['fr' => 'Dans Spotify : « … » → Partager → Copier le lien, puis collez-le ici. Artiste, album, playlist ou titre — au choix. Un lecteur apparaît alors dans la colonne de droite de la fiche. Laissez vide pour ne rien afficher.',
                                             'en' => 'In Spotify: “…” → Share → Copy link, then paste it here. Artist, album, playlist or track — whichever you prefer. A player then appears in the right-hand column of the page. Leave empty to show nothing.']],
            /* [V28-INSTAGRAM] Plusieurs adresses, une par ligne : le compte et
               les publications à mettre en avant. Instagram ne permet pas
               d'afficher tout seul « les dernières publications » d'un compte
               sans un compte développeur Meta et une clef à renouveler tous
               les deux mois — on choisit donc ce qu'on montre, et le lien vers
               le compte fait le reste. */
            'instagram_url'  => ['type' => 'urls', 'check' => 'instagram',
                                 'label' => ['fr' => 'Instagram', 'en' => 'Instagram'],
                                 'help'  => ['fr' => 'Une adresse par ligne. Sur la première, le compte (https://www.instagram.com/lecompte/) : il donne le bouton « Voir toutes les publications », qui reste juste indéfiniment. En dessous, une ou deux publications à mettre en avant (dans Instagram : « … » → Partager → Copier le lien). Deux au maximum sont affichées : au-delà, la colonne de droite devient interminable. Laissez vide pour ne rien afficher.',
                                             'en' => 'One address per line. On the first, the account (https://www.instagram.com/theaccount/): it provides the “See all posts” button, which never goes out of date. Below it, one or two posts to highlight (in Instagram: “…” → Share → Copy link). At most two are shown: beyond that the right-hand column becomes endless. Leave empty to show nothing.']],
            /* [V31-SITE-ARTISTE] Le site personnel de l'artiste, tout en bas de
               la colonne de droite — [V32-ORDRE-ASIDE] sous Spotify, depuis
               qu'Instagram est passé devant lui. Un simple lien : contrairement à
               Spotify et Instagram, rien n'est chargé depuis l'extérieur, donc
               pas de bandeau de consentement à passer avant de le voir. */
            'website_url'    => ['type' => 'url',
                                 'label' => ['fr' => 'Site internet', 'en' => 'Website'],
                                 'help'  => ['fr' => 'L\'adresse du site personnel de l\'artiste, par exemple https://exemple.com. Elle apparaît en bas de la colonne de droite de la fiche, sous Spotify. Laissez vide pour ne rien afficher.',
                                             'en' => 'The address of the artist’s own website, for example https://example.com. It appears at the foot of the right-hand column of the page, below Spotify. Leave empty to show nothing.']],
            'gallery'        => ['type' => 'gallery', 'zone' => 'gallery',
                                 'label' => ['fr' => 'Galerie photos', 'en' => 'Photo gallery']],
            'videos'         => ['type' => 'videos',
                                 'label' => ['fr' => 'Vidéos', 'en' => 'Videos'],
                                 'help'  => ['fr' => 'Affichées dans la colonne de gauche de la fiche, sous la biographie et la galerie.',
                                             'en' => 'Displayed in the left-hand column of the page, below the biography and the gallery.']],
            'documents'      => ['type' => 'documents',
                                 'label' => ['fr' => 'Documents', 'en' => 'Documents']],
            'visible'        => ['type' => 'toggle',
                                 'label' => ['fr' => 'Publié', 'en' => 'Published']],
            'seo'            => ['type' => 'seo',
                                 'label' => ['fr' => 'Référencement (SEO)', 'en' => 'Search engines (SEO)']],
        ],
    ],

    'event' => [
        'table'    => 'events',
        'label'    => ['fr' => 'Événement',        'en' => 'Event'],
        'plural'   => ['fr' => 'Agenda (On Tour)', 'en' => 'Calendar (On Tour)'],
        'menu'     => ['fr' => 'Agenda',           'en' => 'Calendar'],
        'orderby'  => 'date_sort DESC, id DESC',
        'sortable' => false,
        'duplicable' => true,
        'dup_note' => ['fr' => 'changez la date et le lieu de la copie, puis publiez-la.',
                       'en' => 'change the date and the venue of the copy, then publish it.'],
        'list'     => ['cover' => 'image_id', 'title' => 'venue', 'extra' => 'event_info'],
        'fields'   => [
            'date_text'  => ['type' => 'i18n_text', 'required' => true,
                             'label' => ['fr' => 'Dates (texte affiché)', 'en' => 'Dates (displayed text)'],
                             'help'  => ['fr' => 'Exemple : « 12, 13, 14 décembre 2026 »',
                                         'en' => 'Example: “12, 13, 14 December 2026”']],
            'date_sort'  => ['type' => 'date', 'required' => true,
                             'label' => ['fr' => 'Date (pour le classement)', 'en' => 'Date (used for sorting)']],
            'date_end'   => ['type' => 'date',
                             'label' => ['fr' => 'Date de fin (facultatif)', 'en' => 'End date (optional)']],
            'artist_id'  => ['type' => 'select_entity', 'entity' => 'artist', 'display' => 'name',
                             'label' => ['fr' => 'Artiste lié', 'en' => 'Linked artist']],
            'project_id' => ['type' => 'select_entity', 'entity' => 'project', 'display' => 'title',
                             'label' => ['fr' => 'Projet lié', 'en' => 'Linked project']],
            'venue'      => ['type' => 'text', 'required' => true,
                             'label' => ['fr' => 'Nom du lieu', 'en' => 'Venue name']],
            'venue_url'  => ['type' => 'url',
                             'label' => ['fr' => 'Site du lieu (URL)', 'en' => 'Venue website (URL)']],
            'city'       => ['type' => 'text',
                             'label' => ['fr' => 'Ville, pays', 'en' => 'City, country'],
                             'help'  => ['fr' => 'Exemple : « Genève, CH »', 'en' => 'Example: “Geneva, CH”']],
            'image_id'   => ['type' => 'image', 'zone' => 'event',
                             'label' => ['fr' => 'Image représentative', 'en' => 'Main image'],
                             'help'  => ['fr' => 'Facultatif : sinon l\'image du projet ou de l\'artiste lié est utilisée automatiquement.',
                                         'en' => 'Optional: otherwise the image of the linked project or artist is used automatically.']],
            'visible'    => ['type' => 'toggle',
                             'label' => ['fr' => 'Publié', 'en' => 'Published']],
        ],
    ],

    'team' => [
        'table'    => 'team_members',
        'label'    => ['fr' => 'Membre de l\'équipe', 'en' => 'Team member'],
        'plural'   => ['fr' => 'Équipe',              'en' => 'Team'],
        'menu'     => ['fr' => 'Équipe',              'en' => 'Team'],
        'orderby'  => 'sort, id',
        'sortable' => true,
        'list'     => ['cover' => 'image_id', 'title' => 'last_name', 'extra' => 'team_info'],
        'fields'   => [
            'first_name'   => ['type' => 'text', 'required' => true,
                               'label' => ['fr' => 'Prénom', 'en' => 'First name']],
            'last_name'    => ['type' => 'text', 'required' => true,
                               'label' => ['fr' => 'Nom', 'en' => 'Last name']],
            'role'         => ['type' => 'i18n_text',
                               'label' => ['fr' => 'Titre / fonction', 'en' => 'Title / role']],
            'image_id'     => ['type' => 'image', 'zone' => 'team',
                               'label' => ['fr' => 'Photo', 'en' => 'Photo']],
            'photo_credit' => ['type' => 'text',
                               'label' => ['fr' => 'Crédit photo', 'en' => 'Photo credit']],
            'bio'          => ['type' => 'i18n_html',
                               'label' => ['fr' => 'Biographie', 'en' => 'Biography']],
            'visible'      => ['type' => 'toggle',
                               'label' => ['fr' => 'Publié', 'en' => 'Published']],
        ],
    ],

    'category' => [
        'table'    => 'categories',
        'label'    => ['fr' => 'Catégorie',              'en' => 'Category'],
        'plural'   => ['fr' => 'Catégories de projets',  'en' => 'Project categories'],
        'menu'     => ['fr' => 'Catégories',             'en' => 'Categories'],
        'orderby'  => 'sort, id',
        'sortable' => true,
        'list'     => ['title' => 'name'],
        'fields'   => [
            'name' => ['type' => 'i18n_text', 'required' => true,
                       'label' => ['fr' => 'Nom', 'en' => 'Name']],
            'slug' => ['type' => 'i18n_slug', 'from' => 'name',
                       'label' => ['fr' => 'Adresse (slug)', 'en' => 'Address (slug)']],
        ],
    ],
];

<?php
/**
 * La fiche de production et ses neuf onglets. [16.08.2026]
 *
 * Reprise de `19_productions.js` du dashboard Apps Script — 2708 lignes de JS
 * dont environ 600 pour les onglets. Les mêmes neuf, dans le même ordre, avec
 * les mêmes champs: Synthèse, Dossier, Planning, Logistique, Feuille de route,
 * Rémunération, Budget, Devis, Droits d'auteur.
 *
 * TOUT VIT DANS UNE COLONNE JSON, `projet_prod.donnees`, et la migration 017
 * dit pourquoi: le modèle existant est irrégulier — sur les dix fiches
 * mesurées, une seule porte les quinze clefs — et trois de ses listes sont
 * vides partout. Normaliser d'avance, ce serait décider la forme de champs
 * qu'on n'a jamais vus remplis.
 *
 * CETTE CLASSE NE FAIT QUE TROIS CHOSES: lire la fiche, écrire un champ par son
 * chemin, et tenir les listes. Le rendu est dans `app/dash/_prod_fiche.php`,
 * parce qu'un fichier qui fait le HTML et la donnée devient le fichier de
 * 6,3 Mo que le dashboard actuel est devenu.
 */
declare(strict_types=1);

class ProdFiche
{
    /** Les phases d'une étape de travail, dans l'ordre où elles arrivent. */
    public const PHASES = [
        'conception'  => 'Conception',
        'residences'  => 'Résidences',
        'repetitions' => 'Répétitions',
        'montage'     => 'Montage',
        'jeu'         => 'Jeu',
        'demontage'   => 'Démontage',
    ];

    /**
     * LES QUATRE POSTES DE CHARGES D'UN BUDGET DE PRODUCTION. [16.08.2026]
     *
     * Ce sont ceux qu'Anna utilise et qu'un financeur attend — pas une liste
     * de natures à plat. Un dossier de subvention se lit poste par poste, et
     * « Hébergement » n'est pas un poste: c'est une ligne DANS les frais de
     * production. Regrouper à l'affichage ce que la saisie a éclaté obligeait
     * à additionner de tête, ce qu'on ne fait pas devant un jury.
     *
     * L'ORDRE COMPTE et il est celui du formulaire: le personnel d'abord, parce
     * que c'est toujours le plus gros et le premier regardé.
     */
    public const BUDGET_POSTES = [
        'personnel'     => 'Frais de personnel — salaires & honoraires',
        'production'    => 'Frais de production',
        'communication' => 'Communication',
        'administration'=> 'Administration',
    ];

    /**
     * Les natures de produit, côté recettes.
     *
     * Un produit porte un PARTENAIRE et non un libellé libre: « Coproduction »
     * seule ne dit pas qui coproduit, et c'est la première question posée.
     */
    public const BUDGET_PRODUITS = [
        'coproduction' => 'Coproduction',
        'subvention'   => 'Subvention',
        'residence'    => 'Résidence',
        'fondation'    => 'Fondation',
        'prive'        => 'Don privé',
        'cession'      => 'Cession',
        'billetterie'  => 'Billetterie',
        'autre'        => 'Autre',
    ];

    /* Conservées pour lire les lignes saisies avant le 16.08.2026: une reprise
       ne doit pas rendre illisible ce qui est déjà en base. Les anciennes
       natures se rangent dans le poste correspondant, ci-dessous. */
    public const BUDGET_DEPENSE = [
        'salaires'    => 'Salaires équipe',
        'hebergement' => 'Hébergement',
        'repas'       => 'Repas',
        'transport'   => 'Transport',
        'technique'   => 'Technique et matériel',
        'communication' => 'Communication',
        'droits'      => 'Droits d\'auteur',
        'autre_d'     => 'Autre dépense',
    ];
    public const BUDGET_RECETTE = [
        'cession'      => 'Cession',
        'subvention'   => 'Subvention',
        'coproduction' => 'Coproduction',
        'residence'    => 'Résidence',
        'fondation'    => 'Don fondation',
        'prive'        => 'Don privé',
        'billetterie'  => 'Billetterie',
        'autre_r'      => 'Autre recette',
    ];

    /** Où se range une ancienne nature dans les quatre postes. */
    public const BUDGET_ANCIEN = [
        'salaires' => 'personnel',   'hebergement' => 'production',
        'repas'    => 'production',  'transport'   => 'production',
        'technique'=> 'production',  'droits'      => 'administration',
        'communication' => 'communication', 'autre_d' => 'production',
    ];

    /* ── LE CALCUL D'UN PRIX DE CESSION ─────────────────────── [17.08.2026]
       Anna: « na parte devis temos que criar a logica que usamos para fazer com
       bestiarium: saber quem viaja, a quantidade de dias, o preço de le voisin,
       os custos de produção (que no bestiarium não tinha) e depois a margem ».

       LA CHAÎNE N'EST PAS INVENTÉE ICI: elle est écrite dans
       `_contexto/modele_donnees_documents.md` du dépôt de travail, corrigée par
       Anna le 14 puis le 15.08.2026. Ce fichier ne fait que l'exécuter.

       ET C'EST POUR ÇA QUE LES HUIT DEVIS DU BESTIARIUM NE LA SUIVENT PAS: ils
       datent du 07.08, donc d'AVANT la correction. Ils portent deux grilles
       contradictoires — l'une dégressive par jour, l'autre par représentation —
       parce qu'elles ont été écrites à la main avant que la règle existe.

       LES QUATRE CONSTANTES, ET D'OÙ ELLES VIENNENT. Elles ne s'inventent pas
       et ne se changent pas sans changer la note du dépôt de travail avec. */

    /** Un mois de salaire vaut vingt jours ouvrés. Diviseur du barème. */
    public const DEVIS_DIVISEUR = 20;

    /** Indemnité de vacances, en pourcentage du salaire de base. */
    public const DEVIS_VACANCES = 8.33;

    /** Charges patronales, en pourcentage du brut. */
    public const DEVIS_PATRONALES = 19.0;

    /**
     * UN JOUR À DEUX REPRÉSENTATIONS VAUT UN JOUR ET DEMI DE SALAIRE, la
     * deuxième représentation étant un service et non une seconde journée.
     *
     * Corrigé par Anna le 15.08.2026. La version de la veille comptait la
     * deuxième gratuite, si bien que huit représentations en quatre jours
     * sortaient au même prix que quatre — et personne ne l'aurait vu sur le
     * devis fini, seulement sur la paie.
     */
    public const DEVIS_JOUR_DOUBLE = 1.5;

    /**
     * L'ADMINISTRATION N'EST PAS LE PRIX DU VOISIN, ET LES CONFONDRE COÛTE
     * CHER. [précisé par Anna, 17.08.2026]
     *
     * « esse adm é meio dia de trabalho de uma pessoa de adm da chicoria que
     * não é o Le Voisin ». La demi-journée d'administration du devis du
     * Bestiarium est celle de quelqu'un de la GRAN CHICHORNIA — l'association
     * qui porte la pièce. Le prix du Voisin, lui, ce sont les heures de
     * diffusion, et rien d'autre.
     *
     * Les tenir séparés n'est pas une finesse comptable: « tem que deixar
     * espaço para uma pessoa de administração se for o caso, será o caso de
     * Improvável Produções que não fazemos a adm ». Sur Improvável, le Voisin
     * ne fait pas l'administration. Une demi-journée cousue dans le prix du
     * Voisin y facturerait un travail que personne ne fait de notre côté.
     *
     * D'où le modèle: l'administration est UNE LIGNE D'ÉQUIPE COMME UNE AUTRE,
     * qu'on ajoute ou qu'on n'ajoute pas, avec son propre tarif. Ce qui la
     * distingue de celles qui partent en tournée tient en un champ:
     * `suit_jeu` — ses jours ne montent pas avec le nombre de représentations.
     */

    /**
     * Les postes de coûts de production, fixes. [dictés par Anna, 17.08.2026]
     *
     * « pode colocar casas e os valores eu coloco caso a caso ». Des cases
     * nommées plutôt qu'une liste libre: ce sont toujours les mêmes cinq, et
     * une liste libre ferait écrire « costumes » ici et « Costumes » là, donc
     * deux postes qui ne s'additionnent jamais.
     *
     * ELLES SONT VIDES SUR LE BESTIARIUM, et c'est le constat d'Anna elle-même
     * — « que no bestiarium não tinha ». Le champ vide se voit et attend; c'est
     * exactement ce qu'on lui demande.
     */
    public const DEVIS_PRODUCTION = [
        'materiel_technique' => 'Matériel technique',
        'consommables'       => 'Consommables',
        'costumes'           => 'Costumes',
        'maquillage'         => 'Maquillage',
        'frais_production'   => 'Frais de production',
    ];

    /** Les quatre volets de la logistique. */
    public const LOGI = [
        'voyages'     => 'Voyages',
        'hebergement' => 'Hébergement',
        'repas'       => 'Repas',
        'transports'  => 'Transports',
    ];

    /** La fiche vide, avec toutes ses clefs: un onglet ne doit jamais tomber
        sur un null qu'il n'attendait pas. */
    public static function vide(): array
    {
        return [
            'resume'        => '',
            'coproductions' => '',
            'soutiens'      => '',
            /* Repris le 17.08.2026 de `lv-fiches`, où quatorze spectacles les
               portaient et où la première reprise n'avait rien pour les
               recevoir — ils seraient donc partis à la poubelle.

               `production` N'EST PAS `coproductions`. C'est qui porte le
               spectacle — « Gran Chichornia — direction de production: Beat
               Ryser — diffusion: Anna Ladeira (Le Voisin) » — quand les
               coproductions sont les maisons qui ont mis de l'argent. Sur un
               dossier les deux se lisent à des endroits différents, et un jury
               qui lit « coproduction: Gran Chichornia » comprend qu'ils ont
               financé la pièce. Ils l'ont produite, ce n'est pas pareil.

               `bio` est celle de l'ARTISTE et non de la pièce, et elle vit
               quand même ici: elle s'imprime avec le dossier, et le même
               artiste n'écrit pas la même bio pour deux spectacles. */
            'production'    => '',
            'bio'           => '',
            'tournee'       => [],   // [{lieu, saison}] — les lieux déjà joués
            /* Le calcul du prix de cession. Voir les constantes DEVIS_*. */
            'devis'         => [
                'equipe'        => [],     // [{nom, role, paie, jours_fixes, suit_jeu}]
                'diffusion'     => ['heures' => '', 'taux' => '80'],
                'production'    => [],     // les cinq postes de DEVIS_PRODUCTION
                'marge'         => '10',
                'repr_jour'     => '2',
                'seuil'         => '10',
                'tarif_semaine' => '',
                'notes'         => '',
            ],
            'statistiques'  => ['representations'=>'','spectateurs'=>'','recettes'=>'','villes'=>'','notes'=>''],
            'dossier'       => ['lettre'=>'','description'=>'','intention'=>'','calendrier'=>'',
                                'publicCible'=>'','benefice'=>''],
            'planning'      => ['dateArrivee'=>'','dateRetour'=>'','jours'=>[],'dates'=>[]],
            'equipe'        => [],
            'logistique'    => ['voyages'=>[],'hebergement'=>[],'repas'=>[],'transports'=>[]],
            'remuneration'  => [],
            'budget'        => [],
            'partenaires'   => [],
            'admin'         => ['contratTexte'=>''],
            'droits'        => ['auteurs'=>[],'cols'=>[],'editeur'=>'','repartition'=>'','notes'=>'','ssa'=>[]],
            'fdr'           => ['texte'=>''],
            'diffusionDocs' => ['dossier'=>'','fiches'=>'','photos'=>'','autres'=>[]],
            'technique'     => ['plateau'=>[], 'temps'=>[], 'besoins'=>[],
                                'contact'=>['nom'=>'','role'=>'','email'=>'','tel'=>''],
                                'adaptations'=>'', 'notes'=>'', 'versions'=>[]],
        ];
    }

    /* ── La fiche technique ─────────────────────────────────────────────────
       Les champs sont ceux que les lieux demandent, dans l'ordre où ils les
       demandent, relevé sur les fiches techniques que le bureau envoie déjà.
       Ils sont en trois groupes parce qu'ils se remplissent à trois moments:
       le plateau à la création, les temps au premier montage, les besoins à
       chaque nouvelle configuration.

       AUCUN N'EST OBLIGATOIRE, ET C'EST VOULU. Une fiche technique à moitié
       remplie et envoyée vaut mieux qu'une fiche complète jamais finie: le
       lieu qui manque une information la demande, le lieu qui n'a rien reçu
       suppose. */

    public const TECH_PLATEAU = [
        'ouverture'   => ['Ouverture',            'mur à mur, en mètres'],
        'profondeur'  => ['Profondeur',           'du nez de scène au fond, en mètres'],
        'hauteur'     => ['Hauteur sous grill',   'en mètres'],
        'aireJeu'     => ['Aire de jeu minimale', 'en dessous, le spectacle ne se joue pas'],
        'sol'         => ['Sol',                  'tapis de danse, plancher, noir ou clair'],
        'pendrillon'  => ['Pendrillonnage',       'à l\'italienne, boîte noire, aucun'],
        'occultation' => ['Occultation',          'nécessaire en journée ?'],
        'jauge'       => ['Jauge maximale',       'au delà, le rapport au public change'],
    ];

    public const TECH_TEMPS = [
        'montage'    => ['Montage',              'en heures ou en services'],
        'reglages'   => ['Réglages',             'lumière et son, hors montage'],
        'raccord'    => ['Raccord ou filage',    ''],
        'demontage'  => ['Démontage',            ''],
        'preMontage' => ['Pré-montage demandé',  'ce que le lieu fait avant l\'arrivée'],
        'duree'      => ['Durée du spectacle',   'sans entracte, en minutes'],
        'entracte'   => ['Entracte',             ''],
        'enchaine'   => ['Deux services par jour', 'possible, et à quel intervalle'],
    ];

    public const TECH_BESOINS = [
        'tourneeNb'   => ['Personnes en tournée',   'total, artistes et technique'],
        'tourneeTech' => ['dont technique',         ''],
        'lieuEquipe'  => ['Équipe demandée au lieu', 'régie lumière, son, plateau, cintres'],
        'lumiere'     => ['Lumière',                'puissance, gradateurs, console, ce qui est apporté'],
        'son'         => ['Son',                    'diffusion, console, retours, micros'],
        'video'       => ['Vidéo',                  'vidéoprojecteur, écran, ce qui est apporté'],
        'electricite' => ['Électricité',            'puissance, monophasé ou triphasé, arrivées'],
        'loges'       => ['Loges',                  'nombre, douches, catering'],
        'transport'   => ['Transport du décor',     'volume, poids, véhicule'],
    ];

    /* ── Lire ───────────────────────────────────────────────────────────── */

    /** La ligne de production d'un spectacle du CMS, créée au besoin. */
    public static function ligne(int $projectId): array
    {
        $r = DB::one('SELECT * FROM projet_prod WHERE project_id = ?', [$projectId]);
        if (!$r) {
            DB::insert('projet_prod', ['project_id' => $projectId]);
            $r = DB::one('SELECT * FROM projet_prod WHERE project_id = ?', [$projectId]);
        }
        return $r ?: [];
    }

    /** La fiche, complétée des clefs manquantes. */
    public static function donnees(int $projectId): array
    {
        $r = self::ligne($projectId);
        $d = json_decode((string)($r['donnees'] ?? ''), true);
        if (!is_array($d)) $d = [];
        return self::fusion(self::vide(), $d);
    }

    /** Complète `$base` avec `$sur` sans écraser ce qui existe. */
    private static function fusion(array $base, array $sur): array
    {
        foreach ($sur as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k]) && !array_is_list($v)) {
                $base[$k] = self::fusion($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }
        return $base;
    }

    /* ── Écrire ─────────────────────────────────────────────────────────── */

    public static function ecrire(int $projectId, array $donnees): void
    {
        self::ligne($projectId);
        DB::update('projet_prod',
            ['donnees' => json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            'project_id = ?', [$projectId]);
    }

    /**
     * Écrit un champ par son chemin pointé: « dossier.lettre »,
     * « statistiques.spectateurs ».
     *
     * Le chemin est vérifié contre la fiche vide: une clef inconnue est
     * refusée. Sans cela un POST fabriqué écrirait n'importe quoi dans le JSON,
     * et un JSON n'a pas de schéma pour l'en empêcher.
     */
    /**
     * TROIS NIVEAUX DEPUIS LA FICHE TECHNIQUE, et la vérification devient plus
     * stricte, pas moins. `technique.plateau.ouverture` a trois segments, et la
     * fiche vide ne peut pas les valider seule: `technique.plateau` y est un
     * tableau vide, exprès — écrire les huit clefs dans `vide()` obligerait à
     * les tenir à jour à deux endroits.
     *
     * Le troisième segment est donc validé contre les constantes TECH_*, qui
     * sont la définition unique de ces champs. Une clef absente de la constante
     * est refusée, exactement comme au deuxième niveau. On garde la propriété
     * qui compte: UN POST FABRIQUÉ NE PEUT RIEN ÉCRIRE QUI NE SOIT DÉCLARÉ.
     */
    /**
     * LES CHAMPS DE LA DÉCLARATION SSA. [16.08.2026]
     *
     * Ceux du formulaire officiel M23F0525, dans son ordre. Le titre, le
     * producteur, la première représentation et le partage des droits ne sont
     * PAS ici: ils viennent du projet et de l'onglet Droits, et les redemander
     * ferait deux vérités — celle qu'on déclare et celle qu'on tient.
     *
     * Chaque entrée: [libellé, type, aide]. `type` vaut 'texte', 'oui_non',
     * ou le nom d'une liste.
     */
    public const SSA_GENRES = [
        'a' => 'Théâtre',                    'i' => 'Arts de rue',
        'b' => 'Sketch/es',                  'j' => 'Revue',
        'c' => 'Improvisation',              'k' => 'Opéra',
        'd' => 'Chorégraphie sans musique',  'l' => '(Panto)mime',
        'e' => 'Chorégraphie avec musique',  'm' => 'Comédie musicale',
        'f' => 'Cirque',                     'n' => 'Œuvre dramatico-musicale',
        'g' => 'Marionnettes',               'h' => 'Magie',
        'o' => 'Autre',
    ];

    public const SSA_CHAMPS = [
        'soustitre'    => ['Sous-titre',                      'texte',   ''],
        'autreTitre'   => ['Autre titre (titre de travail)',  'texte',   ''],
        'langue'       => ['Langue/s',                        'texte',   ''],
        'genre'        => ['Genre d\'œuvre',                  'genres',  ''],
        'genreAutre'   => ['Genre « Autre » (préciser)',      'texte',   'seulement si le genre est « Autre »'],
        'duree'        => ['Durée de l\'œuvre (min)',         'texte',   'reprise du CMS si vide'],
        'musique'      => ['Comporte une musique ?',          'oui_non', ''],
        'dureeMusOrig' => ['Durée musique originale (min)',   'texte',   ''],
        'dureeMusProt' => ['Musique préexistante protégée (min)', 'texte', ''],
        'dureeMusDP'   => ['Musique domaine public (min)',    'texte',   ''],
        'editee'       => ['Œuvre/musique éditée ?',          'oui_non', ''],
        'editionLieu'  => ['Édition : lieu et année',         'texte',   ''],
        'originale'    => ['L\'œuvre est',                    'orig',    ''],
        'diffusion'    => ['Diffusion radio/TV/web prévue ?', 'oui_non', ''],
        'producteur'   => ['Producteur',                      'texte',   'l\'association qui porte, reprise si vide'],
        'date1'        => ['Date de la 1ère représentation',  'date',    'reprise des dates si vide'],
        'lieu1'        => ['Lieu de la 1ère représentation',  'texte',   ''],
        'description'  => ['Description de l\'œuvre',         'zone',    'le résumé de la Synthèse si vide'],
        'remarques'    => ['Remarques',                       'texte',   ''],
    ];

    private const TECH_GROUPES = [
        'plateau' => 'TECH_PLATEAU',
        'temps'   => 'TECH_TEMPS',
        'besoins' => 'TECH_BESOINS',
        'contact' => null,   // quatre clefs fixes, listées ci-dessous
    ];
    private const TECH_CONTACT = ['nom' => 1, 'role' => 1, 'email' => 1, 'tel' => 1];

    public static function champ(int $projectId, string $chemin, string $valeur): bool
    {
        $parts = explode('.', $chemin);
        if (count($parts) > 3) return false;

        $vide = self::vide();
        if (!array_key_exists($parts[0], $vide)) return false;

        if (count($parts) === 3) {
            /* Deux familles ont trois niveaux: la fiche technique et la
               déclaration SSA. Chacune valide son troisième segment contre sa
               propre liste — un POST fabriqué ne peut rien écrire qui ne soit
               déclaré quelque part. */
            if ($parts[0] === 'technique') {
                if (!array_key_exists($parts[1], self::TECH_GROUPES)) return false;
                $const = self::TECH_GROUPES[$parts[1]];
                $permis = $const === null ? self::TECH_CONTACT : constant('self::' . $const);
                if (!array_key_exists($parts[2], $permis)) return false;
            } elseif ($parts[0] === 'droits' && $parts[1] === 'ssa') {
                if (!array_key_exists($parts[2], self::SSA_CHAMPS)) return false;
            } elseif ($parts[0] === 'devis' && $parts[1] === 'production') {
                if (!array_key_exists($parts[2], self::DEVIS_PRODUCTION)) return false;
            } elseif ($parts[0] === 'devis' && $parts[1] === 'diffusion') {
                if (!in_array($parts[2], ['heures', 'taux'], true)) return false;
            } else {
                return false;
            }
        } elseif (count($parts) === 2) {
            if (!is_array($vide[$parts[0]]) || !array_key_exists($parts[1], $vide[$parts[0]])) return false;
            if (is_array($vide[$parts[0]][$parts[1]])) return false;   // pas une liste
        } elseif (is_array($vide[$parts[0]])) {
            return false;
        }

        $d = self::donnees($projectId);
        if (count($parts) === 3) {
            if (!isset($d[$parts[0]][$parts[1]]) || !is_array($d[$parts[0]][$parts[1]])) {
                $d[$parts[0]][$parts[1]] = [];
            }
            $d[$parts[0]][$parts[1]][$parts[2]] = $valeur;
        } elseif (count($parts) === 2) {
            $d[$parts[0]][$parts[1]] = $valeur;
        } else {
            $d[$parts[0]] = $valeur;
        }
        self::ecrire($projectId, $d);
        return true;
    }

    /* ── Les listes ─────────────────────────────────────────────────────── */

    /** Un identifiant court et unique pour une ligne de liste. */
    public static function gid(): string
    {
        return substr(bin2hex(random_bytes(6)), 0, 10);
    }

    /**
     * Ajoute une ligne à une liste. `$ou` est « planning.dates », « equipe »,
     * « budget », « logistique.voyages », « droits.auteurs »…
     */
    public static function ajouter(int $projectId, string $ou, array $ligne): string
    {
        $d = self::donnees($projectId);
        $ref =& self::liste($d, $ou);
        if ($ref === null) return '';
        $ligne['id'] = self::gid();
        $ref[] = $ligne;
        self::ecrire($projectId, $d);
        return $ligne['id'];
    }

    public static function modifier(int $projectId, string $ou, string $id, string $champ, string $valeur): void
    {
        $d = self::donnees($projectId);
        $ref =& self::liste($d, $ou);
        if ($ref === null) return;
        foreach ($ref as &$l) {
            if (($l['id'] ?? '') === $id) { $l[$champ] = $valeur; break; }
        }
        unset($l);
        self::ecrire($projectId, $d);
    }

    public static function retirer(int $projectId, string $ou, string $id): void
    {
        $d = self::donnees($projectId);
        $ref =& self::liste($d, $ou);
        if ($ref === null) return;
        $ref = array_values(array_filter($ref, fn($l) => ($l['id'] ?? '') !== $id));
        self::ecrire($projectId, $d);
    }

    /** La liste désignée par un chemin, par référence, ou null si inconnue. */
    private static function &liste(array &$d, string $ou)
    {
        $nul = null;
        $p = explode('.', $ou);
        if (count($p) === 1) {
            if (!isset($d[$p[0]]) || !is_array($d[$p[0]])) return $nul;
            return $d[$p[0]];
        }
        if (count($p) === 2) {
            if (!isset($d[$p[0]][$p[1]]) || !is_array($d[$p[0]][$p[1]])) return $nul;
            return $d[$p[0]][$p[1]];
        }
        return $nul;
    }

    /** Coche ou décoche un jour de la grille du planning. */
    public static function jour(int $projectId, string $jour): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $jour)) return;
        $d = self::donnees($projectId);
        $j = $d['planning']['jours'] ?? [];
        $i = array_search($jour, $j, true);
        if ($i === false) $j[] = $jour; else unset($j[$i]);
        sort($j);
        $d['planning']['jours'] = array_values($j);
        self::ecrire($projectId, $d);
    }

    /* ── Ce que les onglets calculent ───────────────────────────────────── */

    /** Les totaux du budget: dépenses, recettes, solde. */
    public static function budgetTotaux(array $d): array
    {
        $dep = $rec = 0.0;
        foreach ($d['budget'] ?? [] as $l) {
            $m = (float)str_replace(',', '.', (string)($l['montant'] ?? 0));
            if (($l['sens'] ?? 'depense') === 'recette') $rec += $m; else $dep += $m;
        }
        return ['depenses' => $dep, 'recettes' => $rec, 'solde' => $rec - $dep];
    }

    /**
     * LA MASSE SALARIALE, CALCULÉE ET NON SAISIE. [16.08.2026]
     *
     * L'écran d'Anna la marque « auto »: c'est la somme de l'onglet
     * Rémunération, pas une ligne qu'on retape. Une somme retapée diverge à la
     * première rémunération modifiée — et c'est le poste le plus gros d'un
     * budget, celui dont l'erreur se voit le moins et coûte le plus.
     *
     * Elle n'est donc JAMAIS une ligne de `budget`: elle s'ajoute au total à
     * l'affichage. Sans quoi il faudrait la supprimer et la recréer à chaque
     * changement, et deux lignes « salaires » finiraient par cohabiter.
     */
    public static function masseSalariale(array $d): float
    {
        $t = 0.0;
        foreach ($d['remuneration'] ?? [] as $l) {
            $t += (float)str_replace([' ', "'", ',', ' '], ['', '', '.', ''], (string)($l['montant'] ?? 0));
        }
        return $t;
    }

    /**
     * Le budget rangé en quatre postes, plus les produits et le solde.
     *
     * UNE LIGNE SANS POSTE — saisie avant le 16.08 — EST RECLASSÉE d'après son
     * ancienne nature, et aucune ne tombe hors des quatre: « production » sert
     * de recours. Une ligne invisible dans un budget est pire qu'une ligne mal
     * rangée: la seconde se corrige, la première fausse le total sans qu'on
     * sache pourquoi.
     */
    public static function budgetParPoste(array $d): array
    {
        $postes = [];
        foreach (array_keys(self::BUDGET_POSTES) as $k) {
            $postes[$k] = ['lignes' => [], 'total' => 0.0, 'auto' => 0.0];
        }
        $mt = static fn($x): float
            => (float)str_replace([' ', "'", ',', ' '], ['', '', '.', ''], (string)($x ?? 0));

        $produits = []; $totProd = 0.0;
        foreach ($d['budget'] ?? [] as $l) {
            $m = $mt($l['montant'] ?? 0);
            if (($l['sens'] ?? 'depense') === 'recette') {
                $produits[] = $l + ['_m' => $m];
                $totProd += $m;
                continue;
            }
            $p = (string)($l['poste'] ?? '');
            if (!isset($postes[$p])) {
                $p = self::BUDGET_ANCIEN[(string)($l['nature'] ?? '')] ?? 'production';
            }
            $postes[$p]['lignes'][] = $l + ['_m' => $m];
            $postes[$p]['total'] += $m;
        }

        /* La masse salariale entre dans le poste personnel, en tête et marquée. */
        $sal = self::masseSalariale($d);
        $postes['personnel']['auto']   = $sal;
        $postes['personnel']['total'] += $sal;

        $totCharges = 0.0;
        foreach ($postes as $p) $totCharges += $p['total'];

        return ['postes' => $postes, 'produits' => $produits,
                'charges' => $totCharges, 'recettes' => $totProd,
                'solde'   => $totProd - $totCharges];
    }

    /** La somme des parts de droits, qui doit faire 100. */
    /**
     * Un nombre saisi à la main. « 5 000 », « 5'000 », « 5000.50 » — les trois
     * arrivent, et `(float)` sur le premier rend 5. Une paie lue à 5 au lieu de
     * 5 000 ne se voit pas: elle rend un prix de cession plausible et faux.
     */
    private static function nb($v): float
    {
        $s = str_replace([' ', "'", "\u{202f}", "\u{a0}"], '', (string)$v);
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? (float)$s : 0.0;
    }

    /**
     * LE PRIX DE CESSION POUR UN NOMBRE DE JOURS DE JEU. [17.08.2026]
     *
     * L'ordre est celui de la note du dépôt de travail, et il compte:
     *
     *     par personne   base       = (paie mensuelle ÷ 20) × jours travaillés
     *                    vacances   = base × 8,33 %
     *                    brut       = base + vacances
     *                    patronales = brut × 19 %
     *                    TTC        = brut + patronales
     *
     *     A  SALAIRES    = Σ des TTC              ← qui voyage, et l'admin
     *     B  LE VOISIN   = heures de diffusion × taux
     *     C  PRODUCTION  = Σ des cinq postes
     *     CHARGES        = A + B + C
     *     PRIX           = CHARGES × (1 + marge)
     *
     * `suit_jeu` DÉCIDE SI LES JOURS D'UNE PERSONNE MONTENT AVEC LA SÉRIE.
     * Annina et la régie: oui — un jour de plus, c'est un jour de plus pour
     * elles. L'administration: non — sa demi-journée est la même qu'on joue
     * deux fois ou dix.
     *
     * LE VOISIN N'EST PAS DANS LES SALAIRES et n'y sera jamais: son temps se
     * facture à l'heure, il ne se paie pas en cachet. Depuis le 14.08.2026 il
     * est un COÛT DIRECT et non un prélèvement sur la marge — conséquence
     * qu'Anna a assumée: les 10 % qui restent sont du résultat, et les céder en
     * négociation ne réduit plus une provision, cela réduit le bénéfice.
     *
     * Le voyage, l'hébergement, les per diem et le transport du décor NE SONT
     * PAS ICI: modèle « plus, plus, plus » du manuel Reso, hors prix de cession.
     */
    public static function devisCalcul(array $d, float $joursJeu, ?float $reprJour = null): array
    {
        $v = $d['devis'] ?? [];
        $reprJour = $reprJour ?? self::nb($v['repr_jour'] ?? 2);
        if ($reprJour <= 0) $reprJour = 1.0;

        /* Un jour à deux représentations vaut 1,5 jour, la seconde étant un
           service. Au delà de deux dans la journée on ne sait pas, et on ne
           devine pas: le poids reste celui du jour double. */
        $poids = $reprJour >= 2 ? self::DEVIS_JOUR_DOUBLE : 1.0;
        $ponderes = $joursJeu * $poids;

        $gens = [];
        $salaires = 0.0;
        $sansTarif = 0;

        foreach ((array)($v['equipe'] ?? []) as $p) {
            $paie  = self::nb($p['paie'] ?? 0);
            $fixes = self::nb($p['jours_fixes'] ?? 0);
            /* Le défaut est « suit le jeu »: c'est le cas de tout le monde sauf
               l'administration, et un défaut qui se trompe dans le sens de
               l'oubli produirait des salaires trop bas. */
            $suit  = (string)($p['suit_jeu'] ?? '1') !== '0';
            $jours = $fixes + ($suit ? $ponderes : 0);

            $base = $paie / self::DEVIS_DIVISEUR * $jours;
            $vac  = $base * self::DEVIS_VACANCES / 100;
            $brut = $base + $vac;
            $pat  = $brut * self::DEVIS_PATRONALES / 100;
            $ttc  = $brut + $pat;

            if ($paie <= 0) $sansTarif++;
            $salaires += $ttc;
            $gens[] = [
                'nom' => (string)($p['nom'] ?? ''), 'role' => (string)($p['role'] ?? ''),
                'paie' => $paie, 'jours' => $jours, 'suit' => $suit,
                'base' => $base, 'vacances' => $vac, 'brut' => $brut,
                'patronales' => $pat, 'ttc' => $ttc,
            ];
        }

        $heures    = self::nb($v['diffusion']['heures'] ?? 0);
        $taux      = self::nb($v['diffusion']['taux'] ?? 80);
        $diffusion = $heures * $taux;

        $prod = 0.0;
        $postes = [];
        foreach (self::DEVIS_PRODUCTION as $k => $lib) {
            $m = self::nb($v['production'][$k] ?? 0);
            $postes[$k] = $m;
            $prod += $m;
        }

        $charges = $salaires + $diffusion + $prod;
        $marge   = self::nb($v['marge'] ?? 10);
        $prix    = $charges * (1 + $marge / 100);
        $repr    = (int)round($joursJeu * $reprJour);

        return [
            'jours'      => $joursJeu,
            'ponderes'   => $ponderes,
            'repr'       => $repr,
            'personnes'  => $gens,
            'sans_tarif' => $sansTarif,
            'salaires'   => $salaires,
            'diffusion'  => $diffusion,
            'heures'     => $heures,
            'postes'     => $postes,
            'production' => $prod,
            'charges'    => $charges,
            'taux_marge' => $marge,
            'marge'      => $prix - $charges,
            'prix'       => $prix,
            'unitaire'   => $repr > 0 ? $prix / $repr : 0.0,
        ];
    }

    /**
     * LES VALEURS DE DÉPART, QUI SONT CELLES DU BESTIARIUM. [17.08.2026]
     *
     * Anna: « voce pode pegar os valores diarios iguais aos de bestiarium ».
     * Elles sont relevées dans `Calcul_Devis_LeVoisin.xlsx`, onglet « Barèmes »,
     * qui porte pour chacune sa source et sa date — ce qui est la seule raison
     * de pouvoir les recopier ici sans les inventer:
     *
     *     5 000 CHF / mois ÷ 20 jours ouvrables = 250 CHF de base par jour
     *     vacances 8,33 %          CCT SSRS (10,64 % au delà de 50 ans)
     *     patronales 19 %          Anna, 14.08.2026, d'après son budget
     *     marge 10 %               Anna, 14.08.2026 (le manuel Reso dit 20 %)
     *     diffusion 80 CHF/h       Le Voisin, 4 h par date dans le modèle
     *
     * 250 CHF DE BASE EST EXACTEMENT LE PLANCHER LÉGAL — minimum CCT pour une
     * représentation isolée. La feuille le dit en toutes lettres: « il n'y a
     * rien à céder de ce côté ». Une négociation qui descend le salaire au lieu
     * de la marge sort de la légalité, pas du confort.
     *
     * LA LIGNE ADMINISTRATION EST PROPOSÉE ET SE SUPPRIME. C'est la demi-
     * journée de quelqu'un de l'association qui porte la pièce — la Gran
     * Chichornia pour le Bestiarium — et sur Improvável Produções, où le Voisin
     * ne fait pas l'administration, elle n'a rien à faire là.
     */
    public static function devisDefaut(): array
    {
        return [
            'equipe' => [
                ['id' => self::gid(), 'role' => 'Mise en scène / Jeu', 'nom' => '',
                 'paie' => '5000', 'jours_fixes' => '1',   'suit_jeu' => '1'],
                /* UN JOUR FIXE, PAS DEUX, ET LA DIFFÉRENCE EST UNE ERREUR DE
                   LECTURE QUE J'AI FAITE. [Anna, 21.08.2026]

                   « colocar sempre 2 dias de trabalho para a Annina e 2 para a
                   tech » : ce sont DEUX JOURS AU TOTAL pour une date, pas deux
                   jours fixes auxquels s'ajouteraient les jours de jeu. Anna l'a
                   redit en montrant sa feuille — « para uma apresentacao sao 2
                   dias de trabalho » — où une représentation donne bien C20 = 2.

                   Le compte, pour une date : 1 jour fixe + 1 jour de jeu = 2.
                   Pour une série, le jour fixe reste unique et les jours de jeu
                   suivent, pondérés à 1.5 quand deux représentations tombent le
                   même jour.

                   Lu à l'envers, cela gonflait chaque devis de 354 CHF et faisait
                   passer une date isolée de 2 002 à 2 711 CHF. */
                ['id' => self::gid(), 'role' => 'Technique', 'nom' => '',
                 'paie' => '5000', 'jours_fixes' => '1',   'suit_jeu' => '1'],
                ['id' => self::gid(), 'role' => 'Administration', 'nom' => '',
                 'paie' => '5000', 'jours_fixes' => '0.5', 'suit_jeu' => '0'],
            ],
            'diffusion'     => ['heures' => '4', 'taux' => '80'],
            'production'    => ['costumes' => '50'],
            'marge'         => '10',
            'repr_jour'     => '2',
            'seuil'         => '10',
            'tarif_semaine' => '',
            'notes'         => '',
        ];
    }

    /**
     * La grille, du premier jour jusqu'au seuil. Au delà, la note du devis dit
     * que la série relève d'un tarif à la semaine — décision d'Anna confirmée
     * le 17.08.2026 — et la grille s'arrête donc au lieu de prolonger une
     * dégressivité qui ne tient plus.
     */
    public static function devisGrille(array $d): array
    {
        $v     = $d['devis'] ?? [];
        $repJ  = max(1.0, self::nb($v['repr_jour'] ?? 2));
        $seuil = max(1.0, self::nb($v['seuil'] ?? 10));

        $out = [];
        for ($j = 1; $j <= 40; $j++) {
            $c = self::devisCalcul($d, (float)$j, $repJ);
            if ($c['repr'] > $seuil) break;
            $out[] = $c;
        }
        return $out;
    }

    public static function droitsTotal(array $d): float
    {
        $t = 0.0;
        foreach ($d['droits']['auteurs'] ?? [] as $a) {
            $t += (float)str_replace(',', '.', (string)($a['part'] ?? 0));
        }
        return $t;
    }

    /**
     * Le calendrier du dossier, rédigé depuis les étapes du Planning.
     *
     * Repris tel quel du dashboard: « Les périodes du Planning sont imprimées
     * en tableau dans le dossier », et le texte se recopie d'un bouton plutôt
     * que de se ressaisir.
     */
    public static function calendrierDepuisPlanning(array $d): string
    {
        $dates = $d['planning']['dates'] ?? [];
        if (!$dates) return '';
        usort($dates, fn($a, $b) => (string)($a['debut'] ?? '9999') <=> (string)($b['debut'] ?? '9999'));
        $out = [];
        foreach ($dates as $r) {
            $q = trim((string)($r['debut'] ?? ''));
            if ($q === '') continue;
            $f = trim((string)($r['fin'] ?? ''));
            $quand = $f !== '' && $f !== $q
                ? date('d.m.Y', strtotime($q)) . ' au ' . date('d.m.Y', strtotime($f))
                : date('d.m.Y', strtotime($q));
            $lieu = trim(implode(', ', array_filter([$r['lieu'] ?? '', $r['ville'] ?? ''])));
            $out[] = $quand . ' — ' . (self::PHASES[$r['phase'] ?? ''] ?? 'Étape')
                   . ($lieu !== '' ? ' — ' . $lieu : '');
        }
        return implode("\n", $out);
    }

    /**
     * La feuille de route, rédigée depuis Planning, Équipe et Logistique.
     *
     * Générée puis ÉDITABLE, comme dans le dashboard: « génère-la, modifie-la
     * librement ». Le texte produit n'écrase donc jamais celui qui existe sans
     * qu'on le demande.
     */
    public static function feuilleDeRoute(array $p, array $d): string
    {
        $L = [];
        $L[] = mb_strtoupper(trim((string)($p['title_fr'] ?: $p['title_en'] ?: 'Spectacle')));
        $L[] = str_repeat('─', 46);
        $L[] = '';

        $cal = self::calendrierDepuisPlanning($d);
        if ($cal !== '') { $L[] = 'CALENDRIER'; $L[] = $cal; $L[] = ''; }

        if ($d['equipe']) {
            $L[] = 'ÉQUIPE';
            foreach ($d['equipe'] as $m) {
                $nom = trim(($m['prenom'] ?? '') . ' ' . ($m['nom'] ?? ''));
                $L[] = '  ' . ($nom !== '' ? $nom : '—')
                     . (($m['fonction'] ?? '') !== '' ? ' — ' . $m['fonction'] : '');
            }
            $L[] = '';
        }

        foreach (self::LOGI as $k => $lib) {
            $lignes = $d['logistique'][$k] ?? [];
            if (!$lignes) continue;
            $L[] = mb_strtoupper($lib);
            foreach ($lignes as $l) {
                $bout = array_filter([
                    $l['quand'] ?? '', $l['qui'] ?? '', $l['libelle'] ?? '',
                    $l['depart'] ?? '', $l['arrivee'] ?? '', $l['reference'] ?? '',
                ], fn($x) => trim((string)$x) !== '');
                if ($bout) $L[] = '  ' . implode(' · ', $bout);
            }
            $L[] = '';
        }
        return implode("\n", $L);
    }
}

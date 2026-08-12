<?php
/**
 * Le Catalogue : les spectacles, et les fichiers déposés pour chacun.
 * [V42-CATALOGUE]
 *
 * Deux sources, et une seule des deux est une base de données.
 *
 *   — les TEXTES et les métadonnées viennent de la table `projects`, saisis
 *     dans la fiche projet du CMS comme n'importe quel autre champ ;
 *   — les FICHIERS viennent du dossier `medias/{slug}/`, déposé par FTP. Le
 *     site regarde ce qu'il y a et construit la liste tout seul.
 *
 * Rien ne relie les deux sinon le nom du dossier, gardé dans `media_slug`.
 * C'est délibéré : le dossier est le contrat. Peu importe ce qui y dépose les
 * fichiers — un client FTP aujourd'hui, autre chose demain —, le site lit le
 * dossier et rien d'autre. Aucun formulaire d'envoi à écrire, aucune ligne de
 * base à tenir à jour, et un fichier remplacé est un fichier remplacé.
 *
 * Le dossier peut ne pas exister, et c'est l'état normal aujourd'hui : le
 * compte FTP n'est pas encore créé. La fiche s'affiche alors sans colonne de
 * téléchargement, sans se plaindre — mais l'administration, elle, le dira.
 */
class Catalog
{
    /** Où vivent les fichiers, à la racine du site, à côté de uploads/. */
    public const RACINE = 'medias';

    /** Le poids d'un fichier au-delà duquel on ne propose plus la lecture. */
    private const LECTURE_MAX = 400 * 1024 * 1024;

    /**
     * Les noms connus, et ce qu'ils affichent.
     *
     * L'ordre de ce tableau EST l'ordre de la colonne de téléchargement : le
     * teaser d'abord, puis la captation, puis ce qu'on demande le plus souvent.
     * Un programmateur qui ouvre une fiche cherche d'abord à voir, ensuite à
     * savoir si ça tient dans sa salle.
     *
     * Un nom absent de cette table s'affiche quand même, déduit de son propre
     * nom de fichier et rangé à la fin. Rien ne casse pour un fichier imprévu.
     */
    private const CONNUS = [
        'teaser'             => ['fr' => 'Teaser',              'en' => 'Teaser'],
        'captation_stream'   => ['fr' => 'Captation',           'en' => 'Full recording'],
        'captation_hd'       => ['fr' => 'Captation (HD)',      'en' => 'Full recording (HD)'],
        'fiche_technique'    => ['fr' => 'Fiche technique',     'en' => 'Technical rider'],
        'photos_hd'          => ['fr' => 'Photos haute définition', 'en' => 'High-resolution photos'],
        'dossier_artistique' => ['fr' => 'Dossier artistique',  'en' => 'Artistic dossier'],
        'revue_presse'       => ['fr' => 'Revue de presse',     'en' => 'Press review'],
        'plan_feu'           => ['fr' => 'Plan de feu',         'en' => 'Lighting plan'],
        'conduite'           => ['fr' => 'Conduite',            'en' => 'Cue sheet'],
    ];

    /** Le chemin absolu du dossier d'un spectacle, sans vérifier qu'il existe. */
    public static function dossier(string $slug): string
    {
        return LV_ROOT . '/' . self::RACINE . '/' . self::slugSur($slug);
    }

    /**
     * Nettoie un nom de dossier avant de le coller à un chemin.
     *
     * Le `media_slug` vient de la base, donc de l'administration, donc d'un
     * humain. On ne lui fait pas confiance pour autant : la valeur ne garde
     * que ce qu'un nom de dossier de ce site peut contenir. Sans barres, sans
     * points, un « .. » ne remonte nulle part.
     */
    private static function slugSur(string $slug): string
    {
        return substr(preg_replace('/[^a-z0-9_-]+/', '', mb_strtolower(trim($slug))) ?? '', 0, 190);
    }

    /**
     * Les spectacles du Catalogue, du plus récent au plus ancien.
     *
     * Le tri est celui de l'année de création, et pas celui de la page
     * publique : un programmateur lit un catalogue par date, pas dans l'ordre
     * de préférence du bureau. Les pièces sans année passent à la fin — elles
     * sont en création, et une création se présente après ce qui tourne.
     */
    public static function spectacles(): array
    {
        return DB::all(
            "SELECT * FROM projects
              WHERE visible = 1 AND catalog_visible = 1
              ORDER BY (year_creation IS NULL), year_creation DESC, sort, id"
        );
    }

    /** Un spectacle du Catalogue par son adresse, ou null. */
    public static function spectacle(string $slug, string $lang): array|null
    {
        return DB::one(
            "SELECT * FROM projects
              WHERE visible = 1 AND catalog_visible = 1
                AND (slug_$lang = ? OR slug_" . I18n::$default . ' = ?) LIMIT 1',
            [$slug, $slug]
        ) ?: null;
    }

    /**
     * Les fichiers déposés pour un spectacle, prêts à afficher.
     *
     * @return array<int, array{cle:string, nom:string, libelle:string, taille:int,
     *                          ext:string, lecture:bool, chemin:string}>
     */
    public static function ressources(string $mediaSlug, string $lang): array
    {
        $base = self::dossier($mediaSlug);
        if ($mediaSlug === '' || !is_dir($base)) return [];

        $trouves = [];
        foreach (['video', 'photos', 'docs'] as $sous) {
            $d = $base . '/' . $sous;
            if (!is_dir($d)) continue;
            foreach ((scandir($d) ?: []) as $f) {
                if ($f === '.' || $f === '..' || str_starts_with($f, '.')) continue;
                $chemin = $d . '/' . $f;
                if (!is_file($chemin)) continue;

                $cle = pathinfo($f, PATHINFO_FILENAME);
                $ext = mb_strtolower(pathinfo($f, PATHINFO_EXTENSION));

                /* Le poster n'est pas une ressource : c'est l'image d'attente
                   du lecteur. Il se sert, mais il ne se liste pas. */
                if ($cle === 'poster') continue;

                $taille = (int)@filesize($chemin);
                $trouves[] = [
                    'cle'     => $cle,
                    'nom'     => $f,
                    'libelle' => self::libelle($cle, $lang),
                    'taille'  => $taille,
                    'ext'     => $ext,
                    'sous'    => $sous,
                    'chemin'  => $chemin,
                    /* La lecture en ligne n'est proposée que pour une vidéo
                       allégée. Un fichier HD dans une balise <video> fait
                       ramer le navigateur du programmateur et donne une
                       mauvaise impression de la pièce, pas du fichier. */
                    'lecture' => $sous === 'video' && $ext === 'mp4'
                                 && $taille > 0 && $taille <= self::LECTURE_MAX
                                 && !str_ends_with($cle, '_hd'),
                ];
            }
        }

        /* L'ordre de CONNUS d'abord, l'inconnu ensuite, par ordre alphabétique
           pour que deux fichiers imprévus ne changent pas de place d'une
           lecture à l'autre — scandir ne garantit pas l'ordre partout. */
        $rang = array_flip(array_keys(self::CONNUS));
        usort($trouves, static function (array $a, array $b) use ($rang): int {
            $ra = $rang[$a['cle']] ?? 900;
            $rb = $rang[$b['cle']] ?? 900;
            return $ra === $rb ? strcmp($a['nom'], $b['nom']) : $ra <=> $rb;
        });
        return $trouves;
    }

    /** Le teaser, s'il existe — la seule ressource que la fiche publique lit aussi. */
    public static function teaser(string $mediaSlug): ?string
    {
        $f = self::dossier($mediaSlug) . '/video/teaser.mp4';
        return ($mediaSlug !== '' && is_file($f)) ? $f : null;
    }

    /**
     * Le libellé d'un fichier.
     *
     * Un nom connu prend sa traduction. Un nom inconnu se lit tel quel, les
     * soulignés devenant des espaces et la première lettre passant en
     * majuscule : « programme_saison.pdf » donne « Programme saison ». C'est
     * moins joli qu'un vrai libellé et infiniment mieux qu'un fichier qui
     * n'apparaîtrait pas.
     */
    private static function libelle(string $cle, string $lang): string
    {
        if (isset(self::CONNUS[$cle])) {
            return self::CONNUS[$cle][$lang] ?? self::CONNUS[$cle][I18n::$default];
        }
        return mb_convert_case(str_replace('_', ' ', $cle), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Les mots-clefs d'un spectacle, découpés et nettoyés.
     *
     * Ils sont saisis séparés par des virgules, et personne ne tape proprement :
     * on retire les blancs, les vides et les doublons de casse.
     */
    public static function tags(array $p): array
    {
        $out = [];
        foreach (explode(',', (string)($p['tags'] ?? '')) as $t) {
            $t = trim($t);
            if ($t === '') continue;
            $out[mb_strtolower($t)] = $t;
        }
        return array_values($out);
    }

    /** Tous les mots-clefs du Catalogue, pour construire les filtres. */
    public static function tousLesTags(array $spectacles): array
    {
        $out = [];
        foreach ($spectacles as $p) {
            foreach (self::tags($p) as $t) $out[mb_strtolower($t)] = $t;
        }
        ksort($out);
        return array_values($out);
    }
}

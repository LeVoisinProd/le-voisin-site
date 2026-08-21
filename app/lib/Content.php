<?php
/**
 * Lecture/écriture générique des modules de contenu (config app/config/entities.php).
 * [V10-CMS-BILINGUE] — messages et préfixe « Copie de » traduits.
 * [V14-DUPLIQUER] — la copie reçoit désormais ses propres images.
 */
class Content
{
    private static ?array $entities = null;

    public static function entities(): array
    {
        return self::$entities ??= require LV_APP . '/config/entities.php';
    }

    public static function def(string $entity): array
    {
        $defs = self::entities();
        if (!isset($defs[$entity])) throw new RuntimeException('Module inconnu : ' . $entity);
        return $defs[$entity] + ['key' => $entity];
    }

    public static function get(string $entity, int $id): ?array
    {
        $def = self::def($entity);
        return DB::one('SELECT * FROM `' . $def['table'] . '` WHERE id = ?', [$id]);
    }

    public static function listAll(string $entity, bool $onlyVisible = false): array
    {
        $def = self::def($entity);
        $where = ($onlyVisible && self::hasField($def, 'visible')) ? ' WHERE visible = 1' : '';
        return DB::all('SELECT * FROM `' . $def['table'] . '`' . $where . ' ORDER BY ' . $def['orderby']);
    }

    /**
     * La fiche précédente et la suivante, dans l'ordre exact de la liste.
     * [Anna, 21.08.2026]
     *
     * « assim não temos que voltar a cada vez para seguirmos os eventos e
     * corrigir ou mudar coisas mais rapidamente ». Trente-cinq projets à
     * relire un par un, c'était trente-quatre retours par la liste.
     *
     * ON LIT LA COLONNE DES ID ENTIÈRE plutôt que « le premier après celui-ci
     * dans l'ordre ». Les `orderby` des modules ne sont pas tous uniques —
     * `sort, id` chez les uns, un titre chez les autres — et une comparaison
     * sur une clé qui se répète saute des fiches ou tourne en rond. La colonne
     * d'entiers donne le même ordre que la liste par construction, et les
     * modules se comptent en dizaines.
     *
     * @return array{prec:?int, suiv:?int, rang:int, total:int}
     */
    public static function voisins(string $entity, int $id): array
    {
        $def = self::def($entity);
        $ids = array_map('intval', DB::pdo()
            ->query('SELECT id FROM `' . $def['table'] . '` ORDER BY ' . $def['orderby'])
            ->fetchAll(PDO::FETCH_COLUMN));

        $i = array_search($id, $ids, true);
        if ($i === false) return ['prec' => null, 'suiv' => null, 'rang' => 0, 'total' => count($ids)];

        return [
            'prec'  => $ids[$i - 1] ?? null,
            'suiv'  => $ids[$i + 1] ?? null,
            'rang'  => $i + 1,
            'total' => count($ids),
        ];
    }

    private static function hasField(array $def, string $f): bool
    {
        return isset($def['fields'][$f]) || in_array($f, ['visible'], true) && isset($def['fields']['visible']);
    }

    /* ---------------------------------------------------------------------
       Référencement : le remplissage automatique.            [V30-SEO-AUTO]

       Ce que Google affiche d'une page, c'est un titre et une description.
       Jusqu'ici, tant que les deux cases « Référencement (SEO) » de la fiche
       restaient vides, le titre se débrouillait tout seul mais la description
       sortait tronquée au caractère près, en plein milieu d'un mot.

       Les trois fonctions ci-dessous fabriquent ce qui manque, à partir de ce
       qui est déjà écrit dans la fiche : le titre de la pièce, son texte
       d'introduction, sa photo. Trois choses à en savoir :

         — elles n'écrivent RIEN dans la base. Elles répondent à la question
           au moment où on la pose. Le jour où le titre d'une pièce change,
           sa description automatique change avec lui, sans qu'il faille y
           repenser ni rouvrir quoi que ce soit.

         — le site public et l'administration appellent ces mêmes fonctions.
           Ce que la fiche montre en gris clair dans les cases vides est donc
           mot pour mot ce que Google lira. Pas une approximation.

         — écrire quelque chose dans la case reprend toujours le dessus, et
           vider la case redonne la main à l'automatique. Rien n'est jamais
           bloqué.
       --------------------------------------------------------------------- */

    /** Longueur maximale d'une description, coupure comprise. */
    public const SEO_DESC_MAX = 160;

    /** Les colonnes d'image, de la plus précise à la plus générale. */
    public const SEO_IMG_COLS = ['og_image_id', 'cover_image_id', 'image_id'];

    /**
     * Le titre que liront les moteurs de recherche, faute de meta titre écrit.
     *
     * La forme est « Titre de la pièce — Le Voisin », sauf sur la page
     * d'accueil où l'on met le nom de la maison d'abord, parce qu'elle est
     * la maison et non l'une de ses pièces.
     */
    public static function seoTitle(array $row, string $entityKey, string $lang): string
    {
        $site = trim((string)setting('site_name', 'Le Voisin'));
        if (($row['template'] ?? '') === 'home') {
            $t = trim(f($row, 'title', $lang));
            return $t === '' ? $site : $site . ' — ' . $t;
        }
        // Un artiste n'a pas de « titre » : il a un nom, et le même dans les
        // deux langues.
        $t = $entityKey === 'artist'
            ? trim((string)($row['name'] ?? ''))
            : trim(f($row, 'title', $lang));
        return $t === '' ? $site : $t . ' — ' . $site;
    }

    /**
     * La description, faute de meta description écrite : le texte
     * d'introduction, ou à défaut le début du texte descriptif, ramené à
     * 160 caractères.
     *
     * 160, c'est la longueur au-delà de laquelle Google coupe lui-même, et
     * il coupe sans ménagement. Autant couper nous-mêmes, et proprement :
     * jamais au milieu d'un mot, jamais sur une virgule orpheline.
     */
    public static function seoDesc(array $row, string $lang): string
    {
        foreach (['intro', 'body'] as $champ) {
            $texte = self::texteNu(f($row, $champ, $lang));
            if ($texte !== '') return self::couper($texte, self::SEO_DESC_MAX);
        }
        return '';
    }

    /**
     * L'image de partage, faute d'image choisie : l'image représentative de
     * la fiche. Renvoie la ligne image, ou null s'il n'y en a aucune —
     * y compris quand la case pointe vers une image entre-temps supprimée,
     * auquel cas on redescend d'un cran plutôt que de ne rien montrer.
     */
    public static function seoImage(array $row): ?array
    {
        foreach (self::SEO_IMG_COLS as $col) {
            if (!empty($row[$col]) && ($img = Img::row((int)$row[$col]))) return $img;
        }
        return null;
    }

    /** Du texte mis en forme vers du texte nu, sur une seule ligne. */
    private static function texteNu(string $html): string
    {
        // Les balises de bloc laissent la place d'une espace : sans cela,
        // « …fin.</p><p>Début… » donnerait « fin.Début ».
        $t = preg_replace('~<(br|/p|/div|/li|/h[1-6])\b[^>]*>~i', ' ', $html) ?? $html;
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('~\s+~u', ' ', $t) ?? $t;
        return trim($t);
    }

    /**
     * Coupe un texte à la longueur voulue, sur une frontière de mot, points
     * de suspension compris dans le compte.
     *
     * Le repli sur la coupure brutale n'arrive que pour un « mot » de plus de
     * 96 caractères — une adresse web collée, en pratique. Mieux vaut alors
     * couper net que rendre une description vide.
     */
    private static function couper(string $texte, int $max): string
    {
        if (mb_strlen($texte) <= $max) return $texte;
        $court  = mb_substr($texte, 0, $max - 1);
        $espace = mb_strrpos($court, ' ');
        if ($espace !== false && $espace > (int)($max * 0.6)) $court = mb_substr($court, 0, $espace);
        // Une description qui se termine par « , » ou « — » suivi de points de
        // suspension se lit mal ; on retire la ponctuation restée en l'air.
        $court = preg_replace('~[\s\p{P}]+$~u', '', $court) ?? $court;
        return $court . '…';
    }

    /** Crée un brouillon vide (non publié) et retourne son id. */
    public static function createDraft(string $entity): int
    {
        $def = self::def($entity);
        $data = [];
        if (isset($def['fields']['visible'])) $data['visible'] = 0;
        if ($def['sortable'] ?? false) {
            $data['sort'] = 1 + (int)DB::val('SELECT COALESCE(MAX(sort),0) FROM `' . $def['table'] . '`');
        }
        foreach ($def['fields'] as $name => $f) {
            if ($f['type'] === 'date' && !empty($f['required'])) $data[$name] = date('Y-m-d');
        }
        return DB::insert($def['table'], $data ?: ['id' => null]);
    }

    /**
     * Enregistre un POST du formulaire d'édition générique.
     * Retourne la liste des erreurs (vide si tout va bien).
     */
    public static function save(string $entity, int $id, array $post): array
    {
        $def = self::def($entity);
        $langs = I18n::$langs;
        $data = [];
        $errors = [];
        $relations = [];

        foreach ($def['fields'] as $name => $f) {
            switch ($f['type']) {
                case 'text':
                case 'url':
                    $v = trim((string)($post[$name] ?? ''));
                    if (!empty($f['required']) && $v === '') $errors[] = ta('com_required', tc($f['label']));
                    /* [V26-SPOTIFY] Vérification facultative d'une adresse
                       (pour l'instant : Spotify). Une adresse non comprise est
                       signalée au lieu de rester silencieusement sans effet,
                       et une adresse comprise est remise au propre — le
                       « ?si=… » ajouté par le bouton Partager est un
                       identifiant de suivi, il n'a rien à faire dans la base. */
                    if ($v !== '' && ($f['check'] ?? '') === 'spotify') {
                        $sp = Spotify::parse($v);
                        if ($sp === null) $errors[] = ta('com_bad_spotify', tc($f['label']));
                        else $v = Spotify::pageUrl($sp['kind'], $sp['id']);
                    }
                    $data[$name] = $v;
                    break;

                /* [V28-INSTAGRAM] Plusieurs adresses, une par ligne. Chacune
                   est vérifiée séparément : une ligne mal collée est nommée
                   telle quelle dans le message d'erreur, plutôt que de faire
                   rejeter tout le champ sans dire laquelle est en cause. Les
                   adresses comprises sont réécrites au propre — le « ?igsh=… »
                   ajouté par le bouton Partager est un identifiant de suivi. */
                case 'urls':
                    $v = trim((string)($post[$name] ?? ''));
                    if (!empty($f['required']) && $v === '') $errors[] = ta('com_required', tc($f['label']));
                    if ($v !== '' && ($f['check'] ?? '') === 'instagram') {
                        $lu = Instagram::lire($v);
                        foreach ($lu['refus'] as $ligne) $errors[] = ta('com_bad_instagram', tc($f['label']), $ligne);
                        if (!$lu['refus']) $v = Instagram::normaliser($v);
                    }
                    $data[$name] = $v;
                    break;

                /* [V16-DATES] La date arrive maintenant écrite jour d'abord —
                   31.07.2026. Dates::versIso() la rend à la base dans l'ordre
                   qui se trie, et refuse un 31.02 au lieu de l'accepter en
                   silence. */
                case 'date':
                    $brut = trim((string)($post[$name] ?? ''));
                    $v    = Dates::versIso($brut);
                    if ($brut !== '' && $v === null) $errors[] = ta('com_bad_date', tc($f['label']), Dates::gabarit(I18n::$admin));
                    if (!empty($f['required']) && $v === null) {
                        if ($brut === '') $errors[] = ta('com_required_date', tc($f['label']));
                        $v = date('Y-m-d');
                    }
                    $data[$name] = $v;
                    break;

                case 'toggle':
                    $data[$name] = empty($post[$name]) ? 0 : 1;
                    break;

                case 'image':
                    $v = (int)($post[$name] ?? 0);
                    $data[$name] = $v > 0 ? $v : null;
                    break;

                case 'select_entity':
                    $v = (int)($post[$name] ?? 0);
                    $data[$name] = $v > 0 ? $v : null;
                    break;

                case 'select_static':
                    $v = (string)($post[$name] ?? '');
                    $opts = array_keys($f['options'] ?? []);
                    $data[$name] = in_array($v, $opts, true) ? $v : ($opts[0] ?? '');
                    break;

                case 'i18n_text':
                case 'i18n_textarea':
                case 'i18n_html':
                case 'i18n_date_text':
                    $filled = false;
                    foreach ($langs as $lg) {
                        $v = (string)($post[$name . '_' . $lg] ?? '');
                        if ($f['type'] !== 'i18n_html') $v = trim($v);
                        $data[$name . '_' . $lg] = $v;
                        if (trim(strip_tags($v)) !== '') $filled = true;
                    }
                    if (!empty($f['required']) && !$filled) $errors[] = ta('com_required_i18n', tc($f['label']));
                    break;

                case 'i18n_slug':
                    foreach ($langs as $lg) {
                        $v = trim((string)($post[$name . '_' . $lg] ?? ''));
                        if ($v === '') {
                            $src = $f['from'] ?? 'title';
                            $srcVal = (string)($post[$src . '_' . $lg] ?? ($post[$src] ?? ''));
                            if (trim($srcVal) === '') $srcVal = (string)($post[$src . '_' . I18n::$default] ?? '');
                            $v = $srcVal;
                        }
                        $v = slugify($v);
                        // unicité dans la table
                        $col = $name . '_' . $lg;
                        $exists = DB::val('SELECT id FROM `' . $def['table'] . "` WHERE `$col` = ? AND id <> ?", [$v, $id]);
                        if ($exists) $v .= '-' . $id;
                        $data[$col] = $v;
                    }
                    break;

                case 'rel_multi':
                    $ids = array_map('intval', (array)($post[$name] ?? []));
                    $relations[] = ['def' => $f, 'ids' => $ids];
                    break;

                case 'seo':
                    foreach ($langs as $lg) {
                        $data['meta_title_' . $lg] = trim((string)($post['meta_title_' . $lg] ?? ''));
                        $data['meta_desc_' . $lg]  = trim((string)($post['meta_desc_' . $lg] ?? ''));
                    }
                    $og = (int)($post['og_image_id'] ?? 0);
                    $data['og_image_id'] = $og > 0 ? $og : null;
                    break;

                case 'gallery':
                case 'videos':
                case 'documents':
                    // gérés en direct par l'interface (AJAX), rien à enregistrer ici
                    break;
            }
        }

        if ($errors) return $errors;

        DB::update($def['table'], $data, 'id = ?', [$id]);
        foreach ($relations as $rel) {
            $f = $rel['def'];
            DB::delete($f['pivot'], '`' . $f['fk'] . '` = ?', [$id]);
            foreach (array_unique($rel['ids']) as $rid) {
                if ($rid > 0) DB::insert($f['pivot'], [$f['fk'] => $id, $f['ok'] => $rid]);
            }
        }
        return [];
    }

    public static function delete(string $entity, int $id): void
    {
        $def = self::def($entity);
        // médias attachés
        foreach (DB::all('SELECT id FROM images WHERE owner_type = ? AND owner_id = ?', [$entity, $id]) as $img) {
            Img::delete((int)$img['id']);
        }
        DB::delete('videos', 'owner_type = ? AND owner_id = ?', [$entity, $id]);
        foreach (DB::all('SELECT * FROM documents WHERE owner_type = ? AND owner_id = ?', [$entity, $id]) as $doc) {
            Docs::delete((int)$doc['id']);
        }
        // pivots
        foreach ($def['fields'] as $f) {
            if ($f['type'] === 'rel_multi') DB::delete($f['pivot'], '`' . $f['fk'] . '` = ?', [$id]);
        }
        // références inverses connues
        if ($entity === 'artist') {
            DB::run('UPDATE events SET artist_id = NULL WHERE artist_id = ?', [$id]);
            DB::delete('project_artists', 'artist_id = ?', [$id]);
        }
        if ($entity === 'project') {
            DB::run('UPDATE events SET project_id = NULL WHERE project_id = ?', [$id]);
        }
        if ($entity === 'category') {
            DB::delete('project_categories', 'category_id = ?', [$id]);
        }
        DB::delete($def['table'], 'id = ?', [$id]);
    }

    /** Duplique un élément (copie non publiée) avec ses relations pivot. */
    public static function duplicate(string $entity, int $id): int
    {
        $def = self::def($entity);
        $table = $def['table'];
        $row = DB::one("SELECT * FROM `$table` WHERE id = ?", [$id]);
        if (!$row) throw new RuntimeException(tu('sys_item_nf'));
        unset($row['id'], $row['created_at'], $row['updated_at']);
        if (array_key_exists('visible', $row)) $row['visible'] = 0;
        if ($def['sortable'] ?? false) {
            $row['sort'] = 1 + (int)DB::val("SELECT COALESCE(MAX(sort),0) FROM `$table`");
        }
        foreach (I18n::$langs as $lg) {
            $tk = 'title_' . $lg;
            if (array_key_exists($tk, $row) && trim((string)$row[$tk]) !== '') {
                $row[$tk] = tu('sys_copy_prefix') . $row[$tk];
            }
        }
        if (array_key_exists('name', $row) && trim((string)$row['name']) !== '') {
            $row['name'] = tu('sys_copy_prefix') . $row['name'];
        }
        // Slugs : neutralisés puis rendus uniques après insertion (id garanti unique).
        $slugBases = [];
        foreach (I18n::$langs as $lg) {
            $sk = 'slug_' . $lg;
            if (!array_key_exists($sk, $row)) continue;
            $src = (string)($row['title_' . $lg] ?? $row['name'] ?? '');
            $slugBases[$sk] = slugify($src) ?: 'copie';
            $row[$sk] = '';
        }
        $newId = (int)DB::insert($table, $row);
        if ($slugBases) {
            $upd = [];
            foreach ($slugBases as $sk => $base) $upd[$sk] = $base . '-' . $newId;
            DB::update($table, $upd, 'id = ?', [$newId]);
        }
        // [V14-DUPLIQUER] Images : la copie reçoit ses propres fichiers.
        // Sans cela, la copie pointait vers les images de l'original ;
        // supprimer l'original effaçait donc aussi les photos de la copie.
        $imgMap = [];
        foreach (DB::all('SELECT id FROM images WHERE owner_type = ? AND owner_id = ? ORDER BY sort, id',
                         [$entity, $id]) as $im) {
            $old = (int)$im['id'];
            $new = Img::duplicate($old, $entity, $newId);
            if ($new) $imgMap[$old] = $new;
        }
        if ($imgMap) {
            $upd = [];
            foreach (['image_id', 'cover_image_id', 'og_image_id'] as $col) {
                if (!array_key_exists($col, $row)) continue;
                $ref = (int)($row[$col] ?? 0);
                if (isset($imgMap[$ref])) $upd[$col] = $imgMap[$ref];
            }
            if ($upd) DB::update($table, $upd, 'id = ?', [$newId]);
        }
        // Relations pivot (catégories, artistes liés…).
        foreach ($def['fields'] as $f) {
            if (($f['type'] ?? '') === 'rel_multi') {
                foreach (DB::all('SELECT `' . $f['ok'] . '` AS x FROM `' . $f['pivot'] . '` WHERE `' . $f['fk'] . '` = ?', [$id]) as $r) {
                    DB::insert($f['pivot'], [$f['fk'] => $newId, $f['ok'] => (int)$r['x']]);
                }
            }
        }
        return $newId;
    }

    /** Ids liés via une table pivot. */
    public static function relIds(array $f, int $id): array
    {
        return array_map('intval', array_column(
            DB::all('SELECT `' . $f['ok'] . '` AS x FROM `' . $f['pivot'] . '` WHERE `' . $f['fk'] . '` = ?', [$id]),
            'x'
        ));
    }

    /**
     * Lignes liées via pivot (pour l'affichage public).
     * $inverse = false : cibles listées par le pivot pour un propriétaire (projet → artistes).
     * $inverse = true  : propriétaires du pivot pour une cible (artiste → projets).
     */
    public static function related(string $targetEntity, array $f, int $id, bool $onlyVisible = true, bool $inverse = false): array
    {
        $def = self::def($targetEntity);
        $vis = ($onlyVisible && isset($def['fields']['visible'])) ? ' AND t.visible = 1' : '';
        $joinCol  = $inverse ? $f['fk'] : $f['ok'];
        $whereCol = $inverse ? $f['ok'] : $f['fk'];
        return DB::all(
            'SELECT t.* FROM `' . $def['table'] . '` t
             JOIN `' . $f['pivot'] . '` p ON p.`' . $joinCol . '` = t.id
             WHERE p.`' . $whereCol . '` = ?' . $vis . ' ORDER BY t.' . $def['orderby'],
            [$id]
        );
    }
}

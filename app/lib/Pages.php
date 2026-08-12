<?php
/** Structure du site : arborescence de pages, résolution d'URL, navigation.
 *  [V10-CMS-BILINGUE] — les noms des modules proposés dans le formulaire
 *  d'une page s'écrivent en français et en anglais ; tc() choisit la bonne
 *  version selon la langue de l'administration. Les clés (colonne de gauche)
 *  ne changent jamais : ce sont elles qui sont enregistrées en base. */
class Pages
{
    public const MODULES = [
        ''              => ['fr' => '— Page simple (texte) —',
                            'en' => '— Simple page (text) —'],
        'projects'      => ['fr' => 'Module Projets',
                            'en' => 'Projects module'],
        'artists'       => ['fr' => 'Module Artistes',
                            'en' => 'Artists module'],
        'agenda'        => ['fr' => 'Module Agenda (On Tour)',
                            'en' => 'Calendar module (On Tour)'],
        'team'          => ['fr' => 'Module Équipe',
                            'en' => 'Team module'],
        'form_infos'    => ['fr' => 'Formulaire Infos personnelles',
                            'en' => 'Personal details form'],
        'form_expenses' => ['fr' => 'Formulaire Factures / dépenses',
                            'en' => 'Invoices / expenses form'],
        'forms_portal'  => ['fr' => 'Page Formulaires (accès dépenses / espace collaborateur)',
                            'en' => 'Forms page (expenses / team area access)'],
        'pro'           => ['fr' => 'Page PRO (accès privé CMS / tableau de bord)',
                            'en' => 'PRO page (private access: CMS / dashboard)'],
        'admin_portal'  => ['fr' => 'Page Administration (connexion équipe → tableau de bord / CMS)',
                            'en' => 'Administration page (team login → dashboard / CMS)'],
        // Sans cette ligne le gabarit espaces.php ne sert à rien : la liste du
        // CMS est fermée, et page-edit.php refuse toute valeur absente d'ici.
        'espaces'       => ['fr' => 'Page Espaces dédiés (cartes Membres / Pros)',
                            'en' => 'Dedicated spaces page (Members / Pros cards)'],
        'catalog'       => ['fr' => 'Catalogue professionnel (grille + fiches, sous mot de passe)',
                            'en' => 'Professional catalogue (grid + shows, password protected)'],
    ];

    private static ?array $all = null;

    /** @return array[] toutes les pages, indexées par id */
    public static function all(): array
    {
        if (self::$all === null) {
            self::$all = [];
            foreach (DB::all('SELECT * FROM pages ORDER BY sort, id') as $p) {
                self::$all[(int)$p['id']] = $p;
            }
        }
        return self::$all;
    }

    public static function reset(): void
    {
        self::$all = null;
    }

    public static function byId(?int $id): ?array
    {
        return $id ? (self::all()[$id] ?? null) : null;
    }

    public static function children(?int $parentId, bool $onlyVisible = false): array
    {
        $out = [];
        foreach (self::all() as $p) {
            if ((int)($p['parent_id'] ?? 0) === (int)$parentId) {
                if ($onlyVisible && !$p['visible']) continue;
                $out[] = $p;
            }
        }
        return $out;
    }

    /** Page d'accueil (template home). */
    public static function home(): ?array
    {
        foreach (self::all() as $p) if ($p['template'] === 'home') return $p;
        $roots = self::children(null, true);
        return $roots[0] ?? null;
    }

    /** Première page visible portant un module donné. */
    public static function moduleP(string $module): ?array
    {
        foreach (self::all() as $p) {
            if ($p['module'] === $module && $p['visible']) return $p;
        }
        return null;
    }

    /** Slug effectif d'une page dans une langue (repli langue par défaut). */
    public static function slug(array $page, string $lang): string
    {
        $s = trim((string)($page['slug_' . $lang] ?? ''));
        if ($s === '') $s = trim((string)($page['slug_' . I18n::$default] ?? ''));
        return $s;
    }

    /** Chemin complet (slugs des ancêtres inclus) sans la langue. */
    public static function path(array $page, string $lang): string
    {
        if ($page['template'] === 'home') return '';
        $parts = [self::slug($page, $lang)];
        $cur = $page;
        while (!empty($cur['parent_id']) && ($cur = self::byId((int)$cur['parent_id']))) {
            if ($cur['template'] === 'home') break;
            array_unshift($parts, self::slug($cur, $lang));
        }
        return implode('/', $parts);
    }

    /** URL publique d'une page. */
    public static function url(array $page, ?string $lang = null): string
    {
        $lang ??= I18n::$lang;
        $path = self::path($page, $lang);
        return url('/' . $lang . ($path ? '/' . $path : ''));
    }

    /**
     * Résout un chemin ([segments]) vers une page dans une langue.
     * Les slugs de la langue par défaut sont aussi acceptés (contenus non traduits).
     * Retourne [page, segmentsRestants].
     */
    public static function resolve(array $segments, string $lang): array
    {
        if (!$segments) return [self::home(), []];
        $parentId = null;
        $page = null;
        while ($segments) {
            $seg = $segments[0];
            $found = null;
            foreach (self::children($parentId, true) as $p) {
                if ($seg === self::slug($p, $lang) || $seg === trim((string)$p['slug_' . I18n::$default])
                    || $seg === trim((string)$p['slug_' . $lang])) {
                    $found = $p; break;
                }
            }
            if (!$found) break;
            array_shift($segments);
            $page = $found;
            $parentId = (int)$found['id'];
        }
        return [$page, $segments];
    }

    /** Navigation principale (pages racines visibles marquées in_nav). */
    public static function nav(): array
    {
        $out = [];
        foreach (self::children(null, true) as $p) {
            if ($p['in_nav'] && $p['template'] !== 'home') $out[] = $p;
        }
        return $out;
    }

    /** Descendants (pour éviter les boucles dans le choix du parent). */
    public static function descendantIds(int $id): array
    {
        $out = [];
        foreach (self::children($id) as $c) {
            $out[] = (int)$c['id'];
            $out = array_merge($out, self::descendantIds((int)$c['id']));
        }
        return $out;
    }

    public static function delete(int $id): void
    {
        foreach (self::children($id) as $c) self::delete((int)$c['id']);
        foreach (DB::all('SELECT id FROM images WHERE owner_type = ? AND owner_id = ?', ['page', $id]) as $img) {
            Img::delete((int)$img['id']);
        }
        DB::delete('videos', 'owner_type = ? AND owner_id = ?', ['page', $id]);
        foreach (DB::all('SELECT * FROM documents WHERE owner_type = ? AND owner_id = ?', ['page', $id]) as $doc) {
            Docs::delete((int)$doc['id']);
        }
        DB::delete('pages', 'id = ?', [$id]);
        self::reset();
    }
}

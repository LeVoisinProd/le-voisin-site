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

    /** Les natures d'une ligne de budget, dépenses puis recettes. */
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
        ];
    }

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
    public static function champ(int $projectId, string $chemin, string $valeur): bool
    {
        $parts = explode('.', $chemin);
        if (count($parts) > 2) return false;

        $vide = self::vide();
        if (!array_key_exists($parts[0], $vide)) return false;
        if (count($parts) === 2) {
            if (!is_array($vide[$parts[0]]) || !array_key_exists($parts[1], $vide[$parts[0]])) return false;
            if (is_array($vide[$parts[0]][$parts[1]])) return false;   // pas une liste
        } elseif (is_array($vide[$parts[0]])) {
            return false;
        }

        $d = self::donnees($projectId);
        if (count($parts) === 2) $d[$parts[0]][$parts[1]] = $valeur;
        else                     $d[$parts[0]] = $valeur;
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

    /** La somme des parts de droits, qui doit faire 100. */
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

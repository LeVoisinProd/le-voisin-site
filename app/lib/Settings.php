<?php
/** Réglages du site (table settings), avec cache par requête.   [V7-ENVOI] */
class Settings
{
    private static ?array $cache = null;

    private static function load(): void
    {
        if (self::$cache !== null) return;
        self::$cache = [];
        foreach (DB::all('SELECT skey, sval FROM settings') as $r) {
            self::$cache[$r['skey']] = (string)$r['sval'];
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        self::load();
        return array_key_exists($key, self::$cache) ? self::$cache[$key] : $default;
    }

    public static function set(string $key, string $value): void
    {
        self::load();
        DB::run('INSERT INTO settings (skey, sval) VALUES (?, ?) ON DUPLICATE KEY UPDATE sval = VALUES(sval)', [$key, $value]);
        self::$cache[$key] = $value;
    }

    /** Liste d'emails depuis un réglage (séparés par virgule ou retour ligne). */
    public static function emails(string $key): array
    {
        $raw = preg_split('/[\s,;]+/', self::get($key, ''));
        $out = [];
        foreach ($raw as $e) {
            $e = trim($e);
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) $out[] = $e;
        }
        return $out;
    }

    /** Liste de lignes depuis un réglage multi-lignes. */
    public static function lines(string $key): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n|\\\\n/', self::get($key, '')) as $l) {
            $l = trim($l);
            if ($l !== '') $out[] = $l;
        }
        return $out;
    }

    /**
     * Réglage écrit en lignes « Nom | adresse | direction artistique | sigle »,
     * découpé en morceaux.   [V18-DIRECTION] [V32-DOC-ASSO]
     *
     * C'est la syntaxe des listes déroulantes de Contact Form 7, celle qui
     * servait déjà sur l'ancien site : la liste des associations et de leurs
     * boîtes de dépôt peut être collée telle quelle, sans rien réécrire. Une
     * troisième colonne s'y est ajoutée, la personne qui porte la direction
     * artistique de l'association ; puis une quatrième, le sigle qui termine
     * le nom des documents déposés pour elle (2026_07_NOM_Contrat_LVCH.pdf).
     *
     *   Le Voisin FR | upload.LVFR@exemple.com     | Anna Ladeira | LVFR
     *   Encontro     | upload.encontro@exemple.com | Louis Matute | ENC
     *   Tympan       |                             | Marc Crofts  |
     *   Nouvelle asso                                             ← rien d'autre de connu
     *
     * Aucune colonne n'est obligatoire au-delà du nom : une ligne sans barre
     * verticale reste une option valable du menu déroulant, elle n'a
     * simplement pas encore d'adresse, ni de direction, ni de sigle. Les
     * anciennes listes à deux ou trois colonnes continuent donc de
     * fonctionner sans être retouchées.
     *
     * @return array<string, array{mail: string, direction: string, sigle: string}>
     */
    public static function trios(string $key): array
    {
        $out = [];
        foreach (self::lines($key) as $l) {
            $bouts = array_map('trim', explode('|', $l));
            $nom   = array_shift($bouts);
            if ($nom === '' || $nom === null) continue;
            $out[$nom] = [
                'mail'      => (string)($bouts[0] ?? ''),
                'direction' => (string)($bouts[1] ?? ''),
                'sigle'     => (string)($bouts[2] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Le même réglage, rendu sous forme nom => sigle.   [V32-DOC-ASSO]
     *
     * Les associations sans sigle sont absentes de la liste plutôt que
     * présentes avec une valeur vide : celui qui appelle cette méthode veut
     * savoir quels sigles existent, et un sigle vide n'en est pas un. Une
     * association sans sigle reste choisissable — son nom de fichier ne
     * portera simplement aucun suffixe.
     *
     * L'ordre des lignes est conservé.
     *
     * @return array<string, string>
     */
    public static function sigles(string $key): array
    {
        $out = [];
        foreach (self::trios($key) as $nom => $bouts) {
            if ($bouts['sigle'] !== '') $out[$nom] = $bouts['sigle'];
        }
        return $out;
    }

    /**
     * Le même réglage, rendu simplement sous forme nom => adresse.  [V7-ENVOI]
     *
     * C'est ce dont a besoin l'envoi des justificatifs : à quelle boîte de
     * dépôt appartient l'association choisie. La direction artistique, s'il y
     * en a une d'inscrite, est ignorée ici — elle sert à l'affichage, pas à
     * l'acheminement.
     *
     * L'adresse est renvoyée telle qu'elle a été saisie, même si elle est mal
     * formée : c'est à celui qui l'utilise de la contrôler et de le dire. Une
     * faute de frappe corrigée en silence deviendrait un envoi perdu en
     * silence.
     *
     * L'ordre des lignes est conservé — c'est l'ordre du menu déroulant.
     */
    public static function pairs(string $key): array
    {
        $out = [];
        foreach (self::trios($key) as $nom => $bouts) {
            $out[$nom] = $bouts['mail'];
        }
        return $out;
    }
}

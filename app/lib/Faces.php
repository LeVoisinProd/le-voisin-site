<?php
/**
 * Où sont les visages sur une photo ?   [V31-VISAGES]
 *
 * Une même photo sert de vignette carrée, de bandeau très large, d'image de
 * partage. Découpée en son centre — ce que faisait le site jusqu'ici —, elle
 * coupe les têtes une fois sur deux : sur un portrait vertical, le centre
 * géométrique tombe sur le ventre. On cherche donc les visages, et le cadrage
 * automatique se règle sur eux.
 *
 * La méthode est celle de Viola et Jones : une cascade de petits tests très
 * grossiers, appliquée à toutes les positions et à toutes les tailles. Le
 * premier étage élimine la quasi-totalité des fenêtres en trois mesures ; les
 * suivantes ne travaillent que sur le peu qui reste. C'est ce qui rend le
 * procédé praticable en PHP, sans extension particulière : l'hébergement
 * mutualisé n'a que GD, il n'aura jamais OpenCV.
 *
 * Le détecteur lui-même — les 2135 mesures et leurs seuils — n'est pas de
 * nous : c'est la cascade frontale de Rainer Lienhart distribuée avec OpenCV,
 * convertie du XML en JSON pour être lue vite. Sa licence est reproduite dans
 * le fichier de données, comme elle l'exige.
 *
 * Coût : quelques secondes pour une grande photo, une seule fois, à l'envoi.
 * Le résultat est inscrit dans la base ; les recadrages suivants s'en servent
 * sans jamais refaire le calcul.
 */
final class Faces
{
    /** Largeur d'analyse. Au-delà, on ne gagne plus rien : un visage se
     *  reconnaît très bien sur une image de la taille d'une vignette. */
    private const LARGEUR_TRAVAIL = 384;

    /** Plus petit visage cherché, en fraction du petit côté de l'image.
     *  Un visage plus petit que ça ne commande pas le cadrage d'une photo. */
    private const MIN_RELATIF = 0.10;

    /** D'une taille à la suivante. Mesuré sur les photos du Voisin : 1,25
     *  laisse passer six visages sur trente-cinq, 1,15 n'en laisse qu'un,
     *  pour un tiers de temps en plus. C'est le seul réglage qui gagne des
     *  visages sans en inventer. */
    private const FACTEUR = 1.15;

    /** Pas de balayage, en pixels de l'image réduite. Descendre à 1 ne
     *  rattrape rien et fabrique de fausses alertes — cinq à dix sur le même
     *  jeu d'essai. Un cadrage faux étant pire qu'un cadrage centré, le pas
     *  reste à 2. */
    private const PAS = 2;

    /** Nombre de détections superposées exigé pour retenir un visage.
     *  Un vrai visage est trouvé plusieurs fois, à des tailles voisines ;
     *  une fausse alerte, presque toujours une seule. */
    private const VOISINS_MIN = 3;

    private static ?array $casc = null;
    private static ?array $tables = null;   // tables d'offsets, liées à un pas
    private static int $strideTables = -1;

    /** Le nécessaire est-il là ? */
    public static function disponible(): bool
    {
        return function_exists('imagecreatetruecolor')
            && is_readable(LV_APP . '/data/visages.json');
    }

    /** La cascade, chargée une fois pour toutes. */
    private static function cascade(): ?array
    {
        if (self::$casc !== null) return self::$casc ?: null;
        $f = LV_APP . '/data/visages.json';
        $j = is_readable($f) ? json_decode((string)file_get_contents($f), true) : null;
        if (!is_array($j) || empty($j['feats']) || empty($j['stages'])) { self::$casc = []; return null; }
        return self::$casc = $j;
    }

    /**
     * Les visages d'une photo, en pixels de l'image d'origine.
     * @return array [['x'=>,'y'=>,'w'=>,'h'=>,'n'=>nombre de détections], …]
     *
     * Deux passages, et non un seul. Avant de reconnaître quoi que ce soit, il
     * faut réduire la photo, et il y a deux façons honnêtes de le faire : en
     * moyennant les pixels perdus, ou en les échantillonnant. Mesurées sur les
     * trente-cinq photos du Voisin, elles ne se trompent pas aux mêmes
     * endroits — la moyennée trouve Jolie Ngemi et rate Annina Mosimann, la
     * simple fait l'inverse. Aucune n'est meilleure ; elles sont
     * complémentaires.
     *
     * On tente donc la seconde uniquement quand la première n'a rien vu. Une
     * photo où un visage a été trouvé n'y repasse pas : le coût du second
     * passage ne tombe que sur les paysages et les vues de plateau, où l'on ne
     * perd qu'une demi-seconde, une seule fois, à l'envoi de l'image.
     */
    public static function detecter(string $chemin): array
    {
        $casc = self::cascade();
        if (!$casc || !is_readable($chemin)) return [];

        $im = self::ouvrir($chemin);
        if (!$im) return [];

        // La photo n'est ouverte qu'une fois pour les deux passages : c'est le
        // décodage qui coûte, pas la reconnaissance. Sur la plus grande photo
        // du Voisin — 8256 × 5504 —, l'ouvrir deux fois doublerait le temps.
        $v = self::analyser($casc, $im, true);
        if (!$v) $v = self::analyser($casc, $im, false);
        imagedestroy($im);
        return $v;
    }

    /** Un passage de détection, avec l'une ou l'autre réduction. */
    private static function analyser(array $casc, $im, bool $moyenner): array
    {
        $W = imagesx($im); $H = imagesy($im);
        if ($W < 40 || $H < 40) return [];

        // Réduction unique, en niveaux de gris : tout le reste travaille dessus.
        $ech = min(1.0, self::LARGEUR_TRAVAIL / max($W, $H));
        $tw = max(1, (int)round($W * $ech));
        $th = max(1, (int)round($H * $ech));
        $base = self::reduire($im, $tw, $th, $moyenner);
        if (!$base) return [];
        imagefilter($base, IMG_FILTER_GRAYSCALE);

        $fen = (int)$casc['w'];                       // 20
        $minVisage = max((float)$fen, self::MIN_RELATIF * min($tw, $th));

        // Toutes les fenêtres sont de 20×20 : c'est l'image qu'on rétrécit,
        // pas la mesure qu'on agrandit. Une taille de visage correspond donc
        // à une réduction, et le premier niveau est déjà le plus grand.
        $niveaux = [];
        for ($taille = $minVisage; $taille <= max($tw, $th); $taille *= self::FACTEUR) {
            $r = $fen / $taille;                       // rapport de réduction
            $lw = (int)round($tw * $r); $lh = (int)round($th * $r);
            if ($lw <= $fen || $lh <= $fen) break;
            $niveaux[] = [$lw, $lh, $taille];
        }
        if (!$niveaux) { imagedestroy($base); return []; }

        // Un seul pas pour tous les niveaux : les tables d'offsets, calculées
        // une fois, resservent telles quelles d'un niveau à l'autre.
        $stride = $niveaux[0][0] + 1;
        self::preparerTables($casc, $stride);

        $trouves = [];
        foreach ($niveaux as [$lw, $lh, $taille]) {
            $lvl = self::reduire($base, $lw, $lh, $moyenner);
            if (!$lvl) continue;
            foreach (self::balayer($casc, $lvl, $lw, $lh, $stride) as $r) {
                // remise à l'échelle de l'image réduite, puis de l'origine
                $k = $taille / $fen;
                $trouves[] = [
                    ($r[0] * $k) / $ech, ($r[1] * $k) / $ech,
                    ($r[2] * $k) / $ech, ($r[3] * $k) / $ech,
                ];
            }
            imagedestroy($lvl);
        }
        imagedestroy($base);

        return self::regrouper($trouves);
    }

    /**
     * Le point vers lequel cadrer, en fractions de l'image (0 → 1).
     * @return array|null ['x'=>float, 'y'=>float, 'n'=>int] — n = 0 si aucun visage
     */
    public static function pointFocal(string $chemin, ?int $W = null, ?int $H = null): array
    {
        $visages = self::detecter($chemin);
        if (!$visages) return ['x' => 0.5, 'y' => 0.5, 'n' => 0];

        if (!$W || !$H) { $t = @getimagesize($chemin); $W = (int)($t[0] ?? 0); $H = (int)($t[1] ?? 0); }
        if ($W < 1 || $H < 1) return ['x' => 0.5, 'y' => 0.5, 'n' => 0];

        // Plusieurs visages : on vise leur milieu, mais un gros visage tire
        // plus qu'un petit — il est au premier plan, c'est le sujet.
        $sx = 0.0; $sy = 0.0; $sp = 0.0;
        foreach ($visages as $v) {
            $p = $v['w'] * $v['h'];
            $sx += ($v['x'] + $v['w'] / 2) * $p;
            $sy += ($v['y'] + $v['h'] * 0.45) * $p;   // la ligne des yeux, pas le menton
            $sp += $p;
        }
        if ($sp <= 0) return ['x' => 0.5, 'y' => 0.5, 'n' => 0];

        return [
            'x' => min(1.0, max(0.0, ($sx / $sp) / $W)),
            'y' => min(1.0, max(0.0, ($sy / $sp) / $H)),
            'n' => count($visages),
        ];
    }

    /* ------------------------------------------------------------------ */

    /** Ce qui reste de mémoire allouable, en octets. 0 = pas de limite. */
    private static function memoireDisponible(): float
    {
        $m = trim((string)ini_get('memory_limit'));
        if ($m === '' || $m === '-1') return 0.0;
        $n = (float)$m;
        $n *= match (strtolower(substr($m, -1))) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 };
        // On garde huit mégaoctets pour le reste du programme.
        return max(0.0, $n - memory_get_usage(true) - 8388608);
    }

    /**
     * Réduire la photo, d'une des deux façons.
     *
     * Moyennée — imagecopyresampled() : en divisant la taille par trois, elle
     * fait la moyenne des neuf pixels d'origine. L'image est propre, les
     * contours adoucis.
     *
     * Simple — imagescale(IMG_BILINEAR_FIXED) : elle interpole sans moyenner,
     * et laisse donc passer un léger crénelage. Ce défaut n'en est pas
     * toujours un : la cascade a été entraînée sur des images réduites de
     * cette manière, et il lui arrive de ne reconnaître un visage que sous
     * cette forme-là. D'où les deux passages de detecter().
     */
    private static function reduire($src, int $w, int $h, bool $moyenner)
    {
        if (!$moyenner) return imagescale($src, $w, $h, IMG_BILINEAR_FIXED);
        $d = imagecreatetruecolor($w, $h);
        if (!$d) return null;
        imagecopyresampled($d, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
        return $d;
    }

    /**
     * Ouvre la photo, ou renonce.
     *
     * Renoncer fait partie du travail. Une photo de 8000 pixels de côté tient
     * cent quatre-vingts mégaoctets une fois décompressée en mémoire, et
     * l'hébergement mutualisé n'en accorde pas toujours autant. Dépasser la
     * limite ne donnerait pas un mauvais cadrage : cela arrêterait PHP net, au
     * milieu d'un envoi d'image. On préfère mesurer avant, et rendre la main —
     * l'image sera simplement cadrée au centre, comme avant.
     */
    private static function ouvrir(string $chemin)
    {
        $t = @getimagesize($chemin);
        if (!$t) return null;

        $limite = self::memoireDisponible();
        if ($limite > 0) {
            // ~4 octets par pixel pour l'image vraies couleurs, plus autant
            // pour le décodeur et la première réduction : on compte large.
            $besoin = (float)$t[0] * (float)$t[1] * 6.0;
            if ($besoin > $limite) return null;
        }

        $type = $t[2] ?? 0;
        $im = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($chemin),
            IMAGETYPE_PNG  => @imagecreatefrompng($chemin),
            IMAGETYPE_GIF  => @imagecreatefromgif($chemin),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($chemin) : false,
            default        => false,
        };
        return $im ?: null;
    }

    /**
     * Les quatre coins de chaque mesure, convertis en déplacements dans le
     * tableau des sommes cumulées. Ils ne dépendent que de la largeur des
     * lignes — d'où le stride commun à tous les niveaux : cette préparation,
     * qui coûte plus que le balayage d'un niveau entier, n'a lieu qu'une fois.
     */
    private static function preparerTables(array $casc, int $stride): void
    {
        if (self::$tables !== null && self::$strideTables === $stride) return;
        $t = [];
        foreach ($casc['feats'] as $i => $rects) {
            $plat = [];
            foreach ($rects as $r) {
                [$x, $y, $w, $h, $p] = $r;
                $plat[] = (float)$p;
                $plat[] = $y * $stride + $x;                 // A (haut gauche)
                $plat[] = $y * $stride + $x + $w;            // B (haut droite)
                $plat[] = ($y + $h) * $stride + $x;          // C (bas gauche)
                $plat[] = ($y + $h) * $stride + $x + $w;     // D (bas droite)
            }
            $t[$i] = $plat;
        }
        self::$tables = $t;
        self::$strideTables = $stride;
    }

    /** Balayage d'un niveau : toutes les fenêtres 20×20, pas de self::PAS. */
    private static function balayer(array $casc, $lvl, int $lw, int $lh, int $stride): array
    {
        $fen = (int)$casc['w'];

        // Sommes cumulées, et sommes des carrés : elles donnent en quatre
        // lectures la somme des pixels de n'importe quel rectangle.
        $n = $stride * ($lh + 1);
        $I = array_fill(0, $n, 0);
        $Q = array_fill(0, $n, 0);
        for ($y = 0; $y < $lh; $y++) {
            $li = 0; $lq = 0;
            $prev = $y * $stride; $cur = $prev + $stride;
            for ($x = 0; $x < $lw; $x++) {
                $g = imagecolorat($lvl, $x, $y) & 0xFF;
                $li += $g; $lq += $g * $g;
                $I[$cur + $x + 1] = $I[$prev + $x + 1] + $li;
                $Q[$cur + $x + 1] = $Q[$prev + $x + 1] + $lq;
            }
        }

        // Le rectangle de normalisation : le cadre 20×20 moins un pixel tout
        // autour. C'est son écart-type qui met les mesures à l'échelle du
        // contraste local — sans quoi une photo sombre ne serait jamais lue.
        $m = $fen - 1;
        $n0 = 1 * $stride + 1;   $n1 = 1 * $stride + $m;
        $n2 = $m * $stride + 1;  $n3 = $m * $stride + $m;
        $aire = ($fen - 2) * ($fen - 2);

        $tables  = self::$tables;
        $stages  = $casc['stages'];
        $nodes   = $casc['nodes'];
        $res     = [];
        $pas     = self::PAS;
        $ymax    = $lh - $fen; $xmax = $lw - $fen;

        for ($y = 0; $y <= $ymax; $y += $pas) {
            $ligne = $y * $stride;
            for ($x = 0; $x <= $xmax; $x += $pas) {
                $b = $ligne + $x;

                $s  = $I[$b + $n3] - $I[$b + $n1] - $I[$b + $n2] + $I[$b + $n0];
                $q  = $Q[$b + $n3] - $Q[$b + $n1] - $Q[$b + $n2] + $Q[$b + $n0];
                $nf = $aire * $q - $s * $s;
                $nf = $nf > 0 ? sqrt($nf) : 1.0;

                $k = 0; $passe = true;
                foreach ($stages as $st) {
                    $somme = 0.0;
                    $fin = $k + $st[1];
                    for (; $k < $fin; $k++) {
                        $nd = $nodes[$k];
                        $o  = $tables[$nd[0]];
                        $v  = $o[0] * ($I[$b + $o[4]] - $I[$b + $o[2]] - $I[$b + $o[3]] + $I[$b + $o[1]])
                            + $o[5] * ($I[$b + $o[9]] - $I[$b + $o[7]] - $I[$b + $o[8]] + $I[$b + $o[6]]);
                        if (isset($o[10])) {
                            $v += $o[10] * ($I[$b + $o[14]] - $I[$b + $o[12]] - $I[$b + $o[13]] + $I[$b + $o[11]]);
                        }
                        $somme += $v < $nd[1] * $nf ? $nd[2] : $nd[3];
                    }
                    if ($somme < $st[0]) { $passe = false; break; }
                }
                if ($passe) $res[] = [$x, $y, $fen, $fen];
            }
        }
        return $res;
    }

    /**
     * Regroupement des détections voisines.
     *
     * Un visage est trouvé plusieurs fois — un pixel plus à droite, une taille
     * plus grand. On rassemble ce qui se superpose et on ne garde que les
     * grappes assez fournies : c'est le seul filtre contre les fausses
     * alertes, et il est efficace parce qu'une fausse alerte n'est presque
     * jamais confirmée par ses voisines.
     */
    private static function regrouper(array $rects): array
    {
        $n = count($rects);
        if (!$n) return [];

        $eps = 0.25;
        $cl = array_fill(0, $n, -1);
        $nb = 0;
        for ($i = 0; $i < $n; $i++) {
            if ($cl[$i] >= 0) continue;
            $cl[$i] = $nb;
            // propagation simple : tout ce qui touche i, puis ce qui touche ceux-là
            $pile = [$i];
            while ($pile) {
                $a = array_pop($pile);
                for ($j = 0; $j < $n; $j++) {
                    if ($cl[$j] >= 0) continue;
                    $d = $eps * (min($rects[$a][2], $rects[$j][2]) + min($rects[$a][3], $rects[$j][3])) * 0.5;
                    if (abs($rects[$a][0] - $rects[$j][0]) <= $d && abs($rects[$a][1] - $rects[$j][1]) <= $d
                     && abs($rects[$a][2] - $rects[$j][2]) <= $d && abs($rects[$a][3] - $rects[$j][3]) <= $d) {
                        $cl[$j] = $nb; $pile[] = $j;
                    }
                }
            }
            $nb++;
        }

        $acc = array_fill(0, $nb, [0, 0.0, 0.0, 0.0, 0.0]);
        foreach ($rects as $i => $r) {
            $c = $cl[$i];
            $acc[$c][0]++;
            $acc[$c][1] += $r[0]; $acc[$c][2] += $r[1];
            $acc[$c][3] += $r[2]; $acc[$c][4] += $r[3];
        }

        $out = [];
        foreach ($acc as $a) {
            if ($a[0] < self::VOISINS_MIN) continue;
            $out[] = [
                'x' => $a[1] / $a[0], 'y' => $a[2] / $a[0],
                'w' => $a[3] / $a[0], 'h' => $a[4] / $a[0],
                'n' => $a[0],
            ];
        }

        // Une grappe entièrement contenue dans une autre est le même visage
        // vu deux fois : on garde la plus fournie.
        usort($out, static fn($p, $q) => $q['n'] <=> $p['n']);
        $gardes = [];
        foreach ($out as $c) {
            $dedans = false;
            foreach ($gardes as $g) {
                $cx = $c['x'] + $c['w'] / 2; $cy = $c['y'] + $c['h'] / 2;
                if ($cx > $g['x'] && $cx < $g['x'] + $g['w'] && $cy > $g['y'] && $cy < $g['y'] + $g['h']) {
                    $dedans = true; break;
                }
            }
            if (!$dedans) $gardes[] = $c;
        }
        return $gardes;
    }
}

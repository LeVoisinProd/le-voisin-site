<?php
/**
 * Où se trouve une salle.  [Anna, 21.08.2026]
 *
 * Une seule chose à faire: transformer « New Morning · Paris · FR » en un
 * point sur une carte, une fois, et ne plus jamais y revenir.
 *
 * NOMINATIM ET NON GOOGLE. Pas de clef, pas de compte, pas de facture. La
 * contrepartie est une politique d'usage stricte qu'on respecte ici: un appel
 * à la fois, un `User-Agent` qui nous nomme et donne une adresse de contact,
 * et surtout AUCUN APPEL EN MASSE — on géocode la fiche qu'on ouvre, pas les
 * cinquante-et-une d'un coup.
 *
 * ON N'APPELLE QU'UNE FOIS PAR DATE, ET ON GARDE MÊME LES ÉCHECS. `geo_a` est
 * posée dans les deux cas. Sans cela, une salle introuvable serait recherchée
 * à chaque ouverture de sa fiche: le service nous bloquerait, et l'écran
 * traînerait de quelques secondes à chaque fois pour rien.
 *
 * DEUX ESSAIS, DU PLUS PRÉCIS AU PLUS GROSSIER. « Salle, ville, pays »
 * d'abord; si rien ne sort, « ville, pays » seule. Un point sur la ville vaut
 * mieux que pas de carte — on veut voir où l'on va, et la ville suffit à le
 * dire. La différence se lit dans `geo_libelle`, qui garde ce que le service a
 * réellement compris.
 *
 * CE QU'ON NE FAIT PAS: échouer bruyamment. Une carte est un confort. Si le
 * réseau tombe ou si le service répond mal, la fiche s'affiche sans carte et
 * personne n'est bloqué. Aucune exception ne sort d'ici.
 */
declare(strict_types=1);

class Geo
{
    /** Au-delà, on renonce: la fiche ne doit pas attendre un tiers. */
    private const DELAI = 5;

    private const AGENT = 'LeVoisinDashboard/1.0 (+https://le-voisin.com; anna@le-voisin.com)';

    /**
     * Les coordonnées d'une date, cherchées si on ne les a pas encore.
     *
     * @return array{lat:?float, lon:?float, libelle:?string}
     */
    public static function pourBooking(array $b): array
    {
        $vide = ['lat' => null, 'lon' => null, 'libelle' => null];

        if ($b['lat'] !== null && $b['lon'] !== null) {
            return ['lat' => (float)$b['lat'], 'lon' => (float)$b['lon'],
                    'libelle' => $b['geo_libelle'] ?? null];
        }

        /* Déjà cherché et rien trouvé: on ne recommence pas maintenant. La
           colonne se remet à null à la main le jour où l'on corrige le nom de
           la salle, et la recherche repart d'elle-même. */
        if (!empty($b['geo_a'])) return $vide;

        $ville = trim((string)($b['ville'] ?? ''));
        $pays  = trim((string)($b['pays'] ?? ''));
        $salle = trim((string)($b['venue'] ?? ''));
        if ($ville === '' && $salle === '') return $vide;

        $essais = [];
        if ($salle !== '') $essais[] = trim($salle . ', ' . $ville . ', ' . $pays, ', ');
        if ($ville !== '') $essais[] = trim($ville . ', ' . $pays, ', ');

        $trouve = $vide;
        foreach ($essais as $q) {
            $r = self::chercher($q);
            if ($r !== null) { $trouve = $r; break; }
        }

        /* On écrit dans tous les cas: la date de recherche est ce qui empêche
           de retenter en boucle. */
        try {
            DB::update('booking', [
                'lat'         => $trouve['lat'],
                'lon'         => $trouve['lon'],
                'geo_libelle' => $trouve['libelle'],
                'geo_a'       => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int)$b['id']]);
        } catch (Throwable $e) { /* la carte n'est pas une raison de casser */ }

        return $trouve;
    }

    /** @return array{lat:float, lon:float, libelle:string}|null */
    private static function chercher(string $q): ?array
    {
        if ($q === '' || !function_exists('curl_init')) return null;

        try {
            $c = curl_init('https://nominatim.openstreetmap.org/search?format=json&limit=1&q='
                         . rawurlencode($q));
            curl_setopt_array($c, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::DELAI,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_USERAGENT      => self::AGENT,
            ]);
            $rep  = curl_exec($c);
            $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
            curl_close($c);

            if ($code !== 200 || !is_string($rep)) return null;
            $j = json_decode($rep, true);
            if (!is_array($j) || !isset($j[0]['lat'], $j[0]['lon'])) return null;

            return [
                'lat'     => (float)$j[0]['lat'],
                'lon'     => (float)$j[0]['lon'],
                'libelle' => mb_substr((string)($j[0]['display_name'] ?? $q), 0, 250),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * La carte, en tuiles d'images et non en programme.  [21.08.2026]
     *
     * PREMIÈRE VERSION: UN `iframe` VERS `openstreetmap.org/export/embed.html`.
     * Il a rendu, chez Anna, un rectangle bleu disant « your browser does not
     * support WebGL ». Ce visualiseur est passé au rendu vectoriel: il exige
     * une carte graphique accessible au navigateur, ce qui n'est pas acquis —
     * accélération matérielle coupée, machine virtuelle, poste ancien.
     *
     * UNE CARTE EST UNE IMAGE, ET ELLE DOIT LE RESTER. Les tuiles brutes de
     * `tile.openstreetmap.org` sont de simples PNG de 256 pixels. Assemblées
     * en mosaïque par du CSS, elles donnent la même carte sans une ligne de
     * JavaScript, sans WebGL, et sans dépendre d'un service tiers qui change
     * de technologie sans prévenir. Vérifié: le service statique
     * `staticmap.openstreetmap.de`, l'autre voie sans clef, ne répond plus.
     *
     * LE CALCUL EST CELUI DE TOUTES LES CARTES EN TUILES (« slippy map »): on
     * projette le point en pixels du monde au zoom voulu, on ouvre une fenêtre
     * centrée dessus, et on ne demande que les tuiles qu'elle recouvre — six
     * en général, jamais plus de douze.
     *
     * @return array{tuiles:array<int,array{src:string,x:int,y:int}>, w:int, h:int, mx:int, my:int}
     */
    public static function mosaique(float $lat, float $lon,
                                    int $w = 560, int $h = 260, int $z = 15): array
    {
        $n  = 2 ** $z;
        $px = (($lon + 180) / 360) * $n * 256;
        $r  = deg2rad(max(-85.05, min(85.05, $lat)));
        $py = (1 - log(tan($r) + 1 / cos($r)) / M_PI) / 2 * $n * 256;

        /* Le coin haut-gauche de la fenêtre, en pixels du monde. */
        $gx = $px - $w / 2;
        $gy = $py - $h / 2;

        $tuiles = [];
        $tx0 = (int)floor($gx / 256);
        $ty0 = (int)floor($gy / 256);
        $tx1 = (int)floor(($gx + $w) / 256);
        $ty1 = (int)floor(($gy + $h) / 256);

        for ($tx = $tx0; $tx <= $tx1; $tx++) {
            for ($ty = $ty0; $ty <= $ty1; $ty++) {
                /* Hors du monde en haut ou en bas: il n'y a pas de tuile, et en
                   demander une donnerait un carré cassé. */
                if ($ty < 0 || $ty >= $n) continue;
                $tuiles[] = [
                    'src' => 'https://tile.openstreetmap.org/' . $z . '/'
                           . (($tx % $n) + $n) % $n . '/' . $ty . '.png',
                    'x'   => (int)round($tx * 256 - $gx),
                    'y'   => (int)round($ty * 256 - $gy),
                ];
            }
        }

        return ['tuiles' => $tuiles, 'w' => $w, 'h' => $h,
                'mx' => (int)round($w / 2), 'my' => (int)round($h / 2)];
    }

    /** Le lien vers la carte complète d'OpenStreetMap, pour zoomer et se promener. */
    public static function urlOsm(float $lat, float $lon, int $z = 16): string
    {
        return 'https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lon
             . '#map=' . $z . '/' . $lat . '/' . $lon;
    }

    /** L'itinéraire, chez Google: c'est le seul usage où il est vraiment meilleur. */
    public static function urlGoogle(array $b, ?float $lat = null, ?float $lon = null): string
    {
        $q = $lat !== null && $lon !== null
            ? $lat . ',' . $lon
            : trim(implode(', ', array_filter([
                  (string)($b['venue'] ?? ''), (string)($b['ville'] ?? ''), (string)($b['pays'] ?? '')
              ])), ', ');
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q);
    }
}

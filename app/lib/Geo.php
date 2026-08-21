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
     * L'adresse de la carte affichée, cadrée serré autour du point.
     *
     * Le delta fixe le zoom: 0,006 degré fait à peu près six cents mètres de
     * côté, c'est-à-dire le quartier. Assez large pour reconnaître la rue,
     * assez serré pour que le marqueur ne se perde pas.
     */
    public static function urlCarte(float $lat, float $lon, float $d = 0.006): string
    {
        return 'https://www.openstreetmap.org/export/embed.html?bbox='
             . rawurlencode(implode(',', [$lon - $d, $lat - $d, $lon + $d, $lat + $d]))
             . '&layer=mapnik&marker=' . rawurlencode($lat . ',' . $lon);
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

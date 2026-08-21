<?php
/**
 * Le lien avec bexio, une association à la fois.  [Anna, 21.08.2026]
 *
 * PAS D'OAUTH2. La note laissée dans `bookings.php` le 16.08 chiffrait « entre
 * 12 h et 20 h pour le seul OAuth2 » et se trompait de mécanisme. La
 * documentation de bexio est explicite: « Personal Access Tokens (PAT) allow
 * server-to-server connections without the consent flow ». Un jeton collé dans
 * la fiche de l'association suffit, et il ne se périme pas tout seul — donc
 * pas de rafraîchissement, pas de redirection, pas d'écran de consentement à
 * refaire tous les trois mois.
 *
 * UN JETON PAR ASSOCIATION. Chacune a sa comptabilité, son plan de comptes et
 * ses factures; il n'existe pas de compte bexio qui les verrait toutes.
 *
 * CE QUE FAIT CE FICHIER AUJOURD'HUI: il vérifie qu'un jeton répond et il dit
 * de quelle société. Rien d'autre. L'émission de factures viendra ensuite, et
 * elle n'a aucun sens tant que ce premier pas n'est pas prouvé sur une
 * association réelle.
 *
 * POURQUOI COMMENCER PAR LÀ, ET NON PAR LA FACTURE. Une facture émise dans la
 * comptabilité de la mauvaise association est l'erreur la plus chère que cet
 * écran puisse commettre: elle est visible du fiduciaire, elle porte un
 * numéro, et elle s'annule par une note de crédit et non par un bouton. Le nom
 * de la société renvoyé par bexio est donc lu, gardé et affiché à côté du
 * champ: on voit chez qui l'on est avant d'écrire quoi que ce soit.
 *
 * AUCUNE EXCEPTION NE SORT D'ICI. Un service tiers injoignable ne doit pas
 * emporter la fiche d'une association.
 */
declare(strict_types=1);

class Bexio
{
    private const BASE  = 'https://api.bexio.com';
    private const DELAI = 12;

    /** Le jeton en clair d'une association, ou '' s'il n'y en a pas. */
    public static function jeton(array $org): string
    {
        $t = (string)($org['bexio_token'] ?? '');
        if ($t === '') return '';
        try { return Crypto::dechiffrer($t); }
        catch (Throwable $e) { return ''; }
    }

    public static function configure(array $org): bool
    {
        return self::jeton($org) !== '';
    }

    /**
     * Pose ou retire le jeton. Une chaîne vide efface.
     *
     * ON NE RÉAFFICHE JAMAIS UN JETON. Le champ du formulaire part donc
     * toujours vide, et vide veut dire « je n'y touche pas ». Pour retirer un
     * jeton il y a une case dédiée — même règle que les clefs de traduction,
     * et pour la même raison: sinon un enregistrement distrait coupe le
     * service sans que personne s'en aperçoive.
     */
    public static function poserJeton(int $orgId, string $jeton): void
    {
        DB::update('organisation', [
            'bexio_token'   => $jeton === '' ? null : Crypto::chiffrer($jeton),
            'bexio_societe' => null,
            'bexio_teste_a' => null,
        ], 'id = ?', [$orgId]);
    }

    /**
     * Le jeton répond-il, et pour quelle société ?
     *
     * @return array{ok:bool, societe:string, message:string}
     */
    public static function essai(array $org): array
    {
        $jeton = self::jeton($org);
        if ($jeton === '') {
            return ['ok' => false, 'societe' => '',
                    'message' => 'Aucun jeton enregistré pour cette association.'];
        }

        [$code, $corps] = self::appel($jeton, '/2.0/company_profile');

        if ($code === 401 || $code === 403) {
            return ['ok' => false, 'societe' => '',
                    'message' => 'bexio refuse ce jeton (' . $code . '). Il est peut-être révoqué, '
                               . 'ou il lui manque les portées kb_invoice_edit et contact_edit.'];
        }
        if ($code !== 200) {
            return ['ok' => false, 'societe' => '',
                    'message' => 'bexio a répondu ' . ($code ?: 'rien') . '. Réessayer plus tard.'];
        }

        /* `company_profile` renvoie une liste d'un élément. */
        $p = is_array($corps) && isset($corps[0]) ? $corps[0] : (is_array($corps) ? $corps : []);
        $nom = trim((string)($p['name'] ?? ''));
        if ($nom === '') $nom = '(société sans nom)';

        DB::update('organisation', [
            'bexio_societe' => mb_substr($nom, 0, 190),
            'bexio_teste_a' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int)$org['id']]);

        return ['ok' => true, 'societe' => $nom,
                'message' => 'Le jeton répond. Comptabilité: « ' . $nom . ' ».'];
    }

    /**
     * Un appel, et le corps décodé.
     *
     * @return array{0:int, 1:mixed}
     */
    private static function appel(string $jeton, string $chemin,
                                  string $methode = 'GET', ?array $corps = null): array
    {
        if (!function_exists('curl_init')) return [0, null];

        try {
            $c = curl_init(self::BASE . $chemin);
            $opt = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::DELAI,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_CUSTOMREQUEST  => $methode,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $jeton,
                    'Accept: application/json',
                    'Content-Type: application/json',
                ],
            ];
            if ($corps !== null) $opt[CURLOPT_POSTFIELDS] = json_encode($corps);
            curl_setopt_array($c, $opt);

            $rep  = curl_exec($c);
            $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
            curl_close($c);

            return [$code, is_string($rep) ? json_decode($rep, true) : null];
        } catch (Throwable $e) {
            return [0, null];
        }
    }
}

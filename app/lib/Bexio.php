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
     * Les comptes de produits, pour choisir où va un prix de cession.
     *
     * ON NE MONTRE QUE LES 3xxx. Le plan de comptes en compte deux cents; les
     * charges, les actifs et les comptes de bilan n'ont rien à faire dans une
     * liste où l'on choisit un compte de RECETTE. Se tromper de classe est
     * l'erreur la plus facile et la plus coûteuse à défaire.
     *
     * @return array<int,array{id:int, libelle:string}>
     */
    public static function comptes(array $org): array
    {
        $j = self::jeton($org);
        if ($j === '') return [];
        [$c, $r] = self::appel($j, '/2.0/accounts?limit=500');
        if ($c !== 200 || !is_array($r)) return [];

        $out = [];
        foreach ($r as $a) {
            $no = (string)($a['account_no'] ?? '');
            if ($no === '' || $no[0] !== '3') continue;
            $out[] = ['id' => (int)$a['id'],
                      'libelle' => $no . ' · ' . (string)($a['name'] ?? '')];
        }
        usort($out, fn($x, $y) => strcmp($x['libelle'], $y['libelle']));
        return $out;
    }

    /**
     * Les taux de TVA actifs.
     *
     * `/3.0/taxes` ET NON `/2.0`: le second répond 404 sur les comptes
     * d'aujourd'hui. Mesuré, pas supposé.
     *
     * @return array<int,array{id:int, libelle:string}>
     */
    public static function taxes(array $org): array
    {
        $j = self::jeton($org);
        if ($j === '') return [];
        [$c, $r] = self::appel($j, '/3.0/taxes?limit=100');
        if ($c !== 200 || !is_array($r)) return [];

        $out = [];
        foreach ($r as $t) {
            if (empty($t['is_active'])) continue;
            $pc = $t['percentage'] ?? $t['value'] ?? null;
            $out[] = ['id' => (int)$t['id'],
                      'libelle' => trim(((string)($t['code'] ?? '')) . ' · '
                                 . ($pc === null ? '' : rtrim(rtrim(number_format((float)$pc, 2, ',', ''), '0'), ',') . ' %')
                                 . ' ' . (string)($t['display_name'] ?? $t['name'] ?? ''))];
        }
        return $out;
    }

    /**
     * Le contact du lieu chez bexio: trouvé s'il existe, créé sinon.
     *
     * ON CHERCHE AVANT DE CRÉER, ET SUR LE NOM EXACT. Créer un doublon dans une
     * comptabilité est une saleté qui se voit six mois plus tard, au moment où
     * l'on cherche pourquoi un lieu a deux historiques. La recherche est stricte
     * — `=` et non `like` — parce qu'un « Théâtre de Carouge » et un « Théâtre
     * de Carouge (grande salle) » sont deux fiches légitimes.
     *
     * `contact_type_id` 2 = entreprise. Un lieu est une structure, jamais une
     * personne physique.
     *
     * @return array{id:?int, cree:bool, message:string}
     */
    public static function contactLieu(array $org, array $b): array
    {
        $j = self::jeton($org);
        $nom = trim((string)($b['venue'] ?? ''));
        if ($j === '')   return ['id' => null, 'cree' => false, 'message' => 'Aucun jeton bexio.'];
        if ($nom === '') return ['id' => null, 'cree' => false, 'message' => 'Cette date n’a pas de lieu.'];

        [$c, $r] = self::appel($j, '/2.0/contact/search', 'POST',
            [['field' => 'name_1', 'value' => $nom, 'criteria' => '=']]);
        if ($c === 200 && is_array($r) && isset($r[0]['id'])) {
            return ['id' => (int)$r[0]['id'], 'cree' => false,
                    'message' => 'Contact trouvé chez bexio.'];
        }

        [$c2, $r2] = self::appel($j, '/2.0/contact', 'POST', array_filter([
            'contact_type_id' => 2,
            'name_1'          => mb_substr($nom, 0, 255),
            'city'            => trim((string)($b['ville'] ?? '')) ?: null,
            'country_id'      => null,
            'user_id'         => 1,
            'owner_id'        => 1,
        ], fn($x) => $x !== null));

        if ($c2 === 201 || $c2 === 200) {
            $id = (int)($r2['id'] ?? 0);
            return $id > 0
                ? ['id' => $id, 'cree' => true, 'message' => 'Contact créé chez bexio.']
                : ['id' => null, 'cree' => false, 'message' => 'bexio a répondu sans identifiant.'];
        }
        return ['id' => null, 'cree' => false,
                'message' => 'bexio refuse la création du contact (' . $c2 . ').'];
    }

    /**
     * Un BROUILLON de facture à partir d'une date.  [Anna, 22.08.2026]
     *
     * « criar as infos de uma fatura com as informações criadas para o evento,
     * e deixar que eu posso mudar manualmente ».
     *
     * BROUILLON, JAMAIS ÉMIS. L'API crée en brouillon et « émettre » est un
     * appel séparé qu'on ne fait pas: une facture émise porte un numéro et ne
     * s'annule que par une note de crédit. Le dashboard prépare, Anna relit
     * dans bexio, corrige les comptes, et émet elle-même.
     *
     * SEULES LES LIGNES « INCLUSE ». Décision d'Anna: ce qui est dans le prix
     * de cession se facture, ce que le lieu paie directement non — le
     * facturer serait encaisser deux fois — et ce qui reste à notre charge est
     * un coût, pas une recette.
     *
     * PAS DE COMPTE IMPOSÉ. Le compte dépend de la nature de l'entrée et ce
     * n'est jamais le même: c'est Anna qui l'a dit, après que j'eus construit
     * l'inverse. La ligne part donc avec son libellé et son montant, et le
     * compte se choisit dans bexio, là où le plan de comptes est sous les yeux.
     *
     * @return array{ok:bool, id:?int, message:string, lignes:int}
     */
    public static function brouillonFacture(array $org, array $b, array $deal): array
    {
        $j = self::jeton($org);
        if ($j === '') return ['ok' => false, 'id' => null, 'lignes' => 0,
                               'message' => 'Aucun jeton bexio pour cette association.'];

        $incluses = array_values(array_filter($deal,
            fn($l) => (string)($l['charge'] ?? '') === 'incluse'));
        if (!$incluses) {
            return ['ok' => false, 'id' => null, 'lignes' => 0,
                    'message' => 'Aucune ligne « incluse » dans le deal: il n’y a rien à facturer. '
                               . 'Les lignes se saisissent dans l’onglet Deal.'];
        }

        $ct = self::contactLieu($org, $b);
        if (!$ct['id']) return ['ok' => false, 'id' => null, 'lignes' => 0, 'message' => $ct['message']];

        $quand = trim((string)($b['date_texte'] ?: (string)($b['date_debut'] ?? '')));
        $tete  = trim(implode(' · ', array_filter([
            (string)($b['projet'] ?? ''), (string)($b['venue'] ?? ''), $quand,
            ((int)($b['representations'] ?? 0) ?: null)
                ? (int)$b['representations'] . ' représentation'
                  . ((int)$b['representations'] > 1 ? 's' : '')
                : null,
        ])));

        $pos = [];
        foreach ($incluses as $i => $l) {
            $q = (float)($l['quantite'] ?? 1) ?: 1.0;
            $pu = (float)($l['prix_unitaire'] ?? 0)
                ?: ((float)($l['montant'] ?? 0) / $q);
            $pos[] = [
                'type'       => 'KbPositionCustom',
                'amount'     => number_format($q, 2, '.', ''),
                'unit_price' => number_format($pu, 2, '.', ''),
                'text'       => mb_substr(trim((string)($l['libelle'] ?? '')) ?: (string)$l['type'], 0, 500),
                'pos'        => $i + 1,
            ];
        }

        [$c, $r] = self::appel($j, '/2.0/kb_invoice', 'POST', [
            'title'       => mb_substr($tete, 0, 255),
            'contact_id'  => $ct['id'],
            'user_id'     => 1,
            'is_valid_from' => date('Y-m-d'),
            'api_reference' => 'lv-booking-' . (int)($b['id'] ?? 0),
            'positions'   => $pos,
        ]);

        if ($c === 201 || $c === 200) {
            $id = (int)($r['id'] ?? 0);
            return $id > 0
                ? ['ok' => true, 'id' => $id, 'lignes' => count($pos),
                   'message' => 'Brouillon créé chez bexio, ' . count($pos) . ' ligne'
                              . (count($pos) > 1 ? 's' : '') . '. Il n’est PAS émis.'
                              . ($ct['cree'] ? ' Le contact du lieu a été créé.' : '')]
                : ['ok' => false, 'id' => null, 'lignes' => 0,
                   'message' => 'bexio a répondu sans identifiant de facture.'];
        }
        return ['ok' => false, 'id' => null, 'lignes' => 0,
                'message' => 'bexio refuse la création (' . $c . '). '
                           . (is_array($r) ? mb_substr(json_encode($r, JSON_UNESCAPED_UNICODE), 0, 200) : '')];
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

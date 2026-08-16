<?php
/**
 * Traduire les textes libres des documents qui sortent. [16.08.2026]
 *
 * Anna: « eu nao quero escrever o segundo campo, quero que a traducao seja
 * automatica ». Cette classe traduit, garde le résultat, et laisse corriger.
 *
 * TROIS RÈGLES, ET ELLES SONT LE COEUR DE LA CHOSE:
 *
 *   1. ON NE TRADUIT QU'UNE FOIS. La clef est l'empreinte du texte source: le
 *      même paragraphe n'est jamais repayé ni réattendu. Si le français change,
 *      l'empreinte change, et la vieille traduction cesse d'être servie sans
 *      que personne ait à y penser.
 *   2. UNE TRADUCTION RELUE N'EST JAMAIS ÉCRASÉE. `revise = 1` est définitif du
 *      côté machine. Sans cette garantie, corriger serait du travail perdu, et
 *      personne ne corrigerait.
 *   3. SANS CLEF D'API, ON NE TRADUIT PAS ET ON LE DIT. Le document sort avec
 *      son texte français et un bandeau qui l'explique. Ne jamais faire passer
 *      du français pour de l'anglais en silence: le destinataire s'en aperçoit,
 *      pas nous.
 *
 * DEUX MOTEURS, ET LE CHOIX N'EST PAS INDIFFÉRENT.
 *   deepl      meilleur sur le français↔anglais courant, et il conserve les
 *              sauts de ligne, ce qui compte pour une note d'intention.
 *   anthropic  plus lent et plus cher, mais il tient le registre: on peut lui
 *              dire que c'est un dossier de subvention et il écrit comme tel.
 * Le premier configuré gagne; les deux clefs se posent dans Paramètres.
 *
 * CE QUE CETTE CLASSE NE FAIT PAS: traduire à la volée dans une page publique.
 * Elle est appelée depuis les documents imprimables, où l'on accepte d'attendre
 * deux secondes. Un appel réseau dans une page vue par des visiteurs est une
 * panne qui attend son heure.
 */
declare(strict_types=1);

final class Traduction
{
    /** Au-delà, on n'envoie pas: un texte de cette taille n'est pas un champ. */
    private const MAX = 24000;

    /** Le temps qu'on accepte d'attendre, par appel. */
    private const DELAI = 25;

    public static function moteur(): string
    {
        if (trim(setting('deepl_key')) !== '')     return 'deepl';
        if (trim(setting('anthropic_key')) !== '') return 'anthropic';
        return '';
    }

    public static function configured(): bool { return self::moteur() !== ''; }

    /** L'empreinte du texte, sur une forme normalisée. */
    private static function empreinte(string $t): string
    {
        return hash('sha256', preg_replace('/[ \t]+/', ' ', trim($t)) ?? trim($t));
    }

    /**
     * La traduction d'un texte, ou null si elle n'existe pas encore et qu'on ne
     * peut pas la produire.
     *
     * $forcer = false : ne consulte que ce qui est déjà en base. C'est ce
     * qu'utilise l'affichage quand on ne veut surtout pas d'appel réseau.
     */
    public static function de(string $texte, string $vers, bool $forcer = false, string $de = 'fr'): ?string
    {
        $texte = trim($texte);
        if ($texte === '' || $vers === $de) return $texte === '' ? null : $texte;

        $emp = self::empreinte($texte);
        $r = DB::one('SELECT texte FROM traduction WHERE empreinte = ? AND de_langue = ? AND vers_langue = ?',
                     [$emp, $de, $vers]);
        if ($r) return (string)$r['texte'];
        if (!$forcer || !self::configured() || mb_strlen($texte) > self::MAX) return null;

        $out = self::appeler($texte, $vers, $de);
        if ($out === null || trim($out) === '') return null;

        /* INSERT ... ON DUPLICATE: deux impressions lancées en même temps sur la
           même fiche arriveraient ici ensemble, et la clef unique refuserait la
           seconde. On ne touche pas à une ligne déjà relue. */
        DB::run('INSERT INTO traduction (empreinte, de_langue, vers_langue, source, texte, moteur)
                 VALUES (?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   texte  = IF(revise = 1, texte, VALUES(texte)),
                   moteur = IF(revise = 1, moteur, VALUES(moteur))',
                [$emp, $de, $vers, $texte, $out, self::moteur()]);
        return $out;
    }

    /**
     * Traduire plusieurs textes en UN SEUL appel réseau.
     *
     * POURQUOI CE N'EST PAS UN DÉTAIL. Un dossier porte neuf blocs de texte.
     * Neuf appels à la suite, à deux secondes chacun, font une page qui met
     * vingt secondes à s'ouvrir — et quelqu'un finit par croire qu'elle est
     * cassée et par recharger, ce qui relance les neuf. Groupés, c'est un
     * appel et deux secondes.
     *
     * Renvoie un tableau de même longueur et dans le même ordre; une entrée
     * vaut null si elle n'a pas pu être traduite. Le rendu remet alors le
     * français, jamais un trou.
     *
     * @param string[] $textes
     * @return array<int,?string>
     */
    public static function plusieurs(array $textes, string $vers, bool $forcer = false, string $de = 'fr'): array
    {
        $out = [];
        $manquants = [];           // index => texte
        foreach ($textes as $i => $t) {
            $t = trim((string)$t);
            if ($t === '' || $vers === $de) { $out[$i] = $t === '' ? null : $t; continue; }
            $out[$i] = null;
            $manquants[$i] = $t;
        }
        if (!$manquants) return $out;

        /* Ce qui est déjà en base, en une requête et non une par bloc. */
        $emps = array_map([self::class, 'empreinte'], $manquants);
        $tr   = implode(',', array_fill(0, count($emps), '?'));
        $deja = [];
        foreach (DB::all("SELECT empreinte, texte FROM traduction
                           WHERE de_langue = ? AND vers_langue = ? AND empreinte IN ($tr)",
                         [$de, $vers, ...array_values($emps)]) as $r) {
            $deja[(string)$r['empreinte']] = (string)$r['texte'];
        }
        foreach ($manquants as $i => $t) {
            $e = $emps[$i];
            if (isset($deja[$e])) { $out[$i] = $deja[$e]; unset($manquants[$i]); }
        }
        if (!$manquants || !$forcer || !self::configured()) return $out;

        /* Les textes trop longs restent en français plutôt que d'être coupés:
           un dossier tronqué au milieu d'une phrase est pire que non traduit. */
        foreach ($manquants as $i => $t) if (mb_strlen($t) > self::MAX) unset($manquants[$i]);
        if (!$manquants) return $out;

        $index  = array_keys($manquants);
        $suite  = array_values($manquants);
        $traduits = self::moteur() === 'deepl'
            ? self::deeplLot($suite, $vers, $de)
            : array_map(fn($t) => self::appeler($t, $vers, $de), $suite);

        foreach ($index as $rang => $i) {
            $v = $traduits[$rang] ?? null;
            if ($v === null || trim($v) === '') continue;
            $out[$i] = $v;
            DB::run('INSERT INTO traduction (empreinte, de_langue, vers_langue, source, texte, moteur)
                     VALUES (?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                       texte  = IF(revise = 1, texte, VALUES(texte)),
                       moteur = IF(revise = 1, moteur, VALUES(moteur))',
                    [$emps[$i], $de, $vers, $manquants[$i], $v, self::moteur()]);
        }
        return $out;
    }

    /** DeepL accepte plusieurs textes par requête et les rend dans l'ordre. */
    private static function deeplLot(array $textes, string $vers, string $de): array
    {
        $cle  = trim(setting('deepl_key'));
        $hote = str_ends_with($cle, ':fx') ? 'api-free.deepl.com' : 'api.deepl.com';
        [$code, $corps] = self::http("https://$hote/v2/translate", [
            'Authorization: DeepL-Auth-Key ' . $cle,
            'Content-Type: application/json',
        ], json_encode([
            'text'                => array_values($textes),
            'source_lang'         => strtoupper($de),
            'target_lang'         => $vers === 'en' ? 'EN-GB' : strtoupper($vers),
            'tag_handling'        => 'plain',
            'preserve_formatting' => true,
        ], JSON_UNESCAPED_UNICODE));

        if ($code !== 200) { self::journal("deepl lot HTTP $code: " . mb_substr($corps, 0, 300)); return []; }
        $j = json_decode($corps, true);
        /* On vérifie la longueur: si DeepL rendait moins de lignes qu'envoyé,
           les traductions se décaleraient et l'on collerait le texte d'un bloc
           sous le titre d'un autre. Silencieux, et faux. */
        $t = $j['translations'] ?? [];
        if (count($t) !== count($textes)) {
            self::journal('deepl lot: ' . count($t) . ' réponses pour ' . count($textes) . ' envois');
            return [];
        }
        return array_map(fn($x) => (string)($x['text'] ?? ''), $t);
    }

    /** Le nombre de traductions non relues, pour l'écran qui les propose. */
    public static function aRelire(): int
    {
        return (int)DB::val('SELECT COUNT(*) FROM traduction WHERE revise = 0');
    }

    /** Marque une traduction comme relue: la machine n'y touchera plus. */
    public static function reviser(int $id, string $texte, string $par): void
    {
        DB::run('UPDATE traduction SET texte = ?, revise = 1, revise_par = ?, revise_le = NOW()
                  WHERE id = ?', [trim($texte), mb_substr($par, 0, 96), $id]);
    }

    /** L'appel au moteur. Renvoie null sur toute erreur — et la journalise. */
    private static function appeler(string $texte, string $vers, string $de): ?string
    {
        try {
            return self::moteur() === 'deepl'
                 ? self::deepl($texte, $vers, $de)
                 : self::anthropic($texte, $vers, $de);
        } catch (Throwable $e) {
            self::journal('erreur ' . self::moteur() . ': ' . $e->getMessage());
            return null;
        }
    }

    private static function deepl(string $texte, string $vers, string $de): ?string
    {
        $cle = trim(setting('deepl_key'));
        /* Les clefs gratuites finissent par « :fx » et vivent sur un autre
           domaine. Se tromper de domaine donne un 403 qui ressemble à une clef
           invalide, et l'on cherche au mauvais endroit. */
        $hote = str_ends_with($cle, ':fx') ? 'api-free.deepl.com' : 'api.deepl.com';

        [$code, $corps] = self::http("https://$hote/v2/translate", [
            'Authorization: DeepL-Auth-Key ' . $cle,
            'Content-Type: application/json',
        ], json_encode([
            'text'             => [$texte],
            'source_lang'      => strtoupper($de),
            'target_lang'      => $vers === 'en' ? 'EN-GB' : strtoupper($vers),
            /* Le texte vient de nos champs, pas de HTML: sans cela DeepL
               échapperait les « < » et les « & » à sa façon. */
            'tag_handling'     => 'plain',
            'preserve_formatting' => true,
        ], JSON_UNESCAPED_UNICODE));

        if ($code !== 200) { self::journal("deepl HTTP $code: " . mb_substr($corps, 0, 300)); return null; }
        $j = json_decode($corps, true);
        return $j['translations'][0]['text'] ?? null;
    }

    private static function anthropic(string $texte, string $vers, string $de): ?string
    {
        $langue = $vers === 'en' ? 'English' : $vers;
        $consigne =
            "Translate the following text from " . ($de === 'fr' ? 'French' : $de) . " into $langue.\n" .
            "Context: it is part of a performing-arts touring document — a funding application, " .
            "a tour sheet, a technical rider or a quotation. Keep the register formal and " .
            "professional.\n" .
            "Rules: keep line breaks and paragraph structure exactly. Do not translate proper " .
            "nouns, show titles, venue names or people's names. Do not add any comment, " .
            "preamble or quotation marks. Output the translation and nothing else.";

        [$code, $corps] = self::http('https://api.anthropic.com/v1/messages', [
            'x-api-key: ' . trim(setting('anthropic_key')),
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ], json_encode([
            'model'      => trim(setting('anthropic_model')) ?: 'claude-sonnet-5',
            'max_tokens' => 8000,
            'system'     => $consigne,
            'messages'   => [['role' => 'user', 'content' => $texte]],
        ], JSON_UNESCAPED_UNICODE));

        if ($code !== 200) { self::journal("anthropic HTTP $code: " . mb_substr($corps, 0, 300)); return null; }
        $j = json_decode($corps, true);
        $out = '';
        foreach ($j['content'] ?? [] as $bloc) if (($bloc['type'] ?? '') === 'text') $out .= $bloc['text'];
        return trim($out) !== '' ? $out : null;
    }

    /** @return array{0:int,1:string} */
    private static function http(string $url, array $entetes, string $corps): array
    {
        $c = curl_init($url);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $entetes,
            CURLOPT_POSTFIELDS     => $corps,
            CURLOPT_TIMEOUT        => self::DELAI,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $r = (string)curl_exec($c);
        $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
        if (curl_errno($c)) { self::journal('curl: ' . curl_error($c)); $code = 0; }
        curl_close($c);
        return [$code, $r];
    }

    /* Le journal ne porte JAMAIS la clef ni le texte source: il est lu à
       plusieurs et un dossier de subvention n'a pas à traîner dans un log. */
    private static function journal(string $m): void
    {
        $f = LV_APP . '/logs/traduction.log';
        @mkdir(dirname($f), 0775, true);
        @file_put_contents($f, date('c') . ' ' . $m . "\n", FILE_APPEND);
    }
}

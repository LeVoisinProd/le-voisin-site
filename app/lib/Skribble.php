<?php
/**
 * Intégration Skribble — signature électronique (API v2).
 * Docs : https://api-doc.skribble.com/  ·  Auth par nom d'utilisateur + clé API.
 * Réglages : skribble_username, skribble_api_key, skribble_quality (SES/AES/QES).
 * [V10-CMS-BILINGUE] — messages d'erreur traduits (clefs « sys_skr_… »).
 */
class Skribble
{
    private const BASE = 'https://api.skribble.com/v2';

    /**
     * Le journal des échanges avec Skribble.            [13.08.2026]
     *
     * Écrit ici pour la même raison que mail.log et invitations.log : un
     * message qui ne s'affiche qu'une fois et disparaît ne se diagnostique
     * pas. Aujourd'hui, un envoi a été tenté, quelque chose s'est affiché en
     * haut d'une longue page, et personne n'a pu dire quoi.
     *
     * Il compte double ici : le retour du document signé est écrit d'après une
     * documentation lue de loin, sans pouvoir l'essayer. Si la réponse de
     * l'API n'est pas celle attendue, c'est cette ligne qui le dira.
     */
    public static function journal(string $ligne): void
    {
        $dir = LV_APP . '/logs';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/skribble.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $ligne . "\n", FILE_APPEND);
    }

    /** Les dernières lignes du journal, pour l'administration. */
    public static function journalLire(int $lignes = 40): string
    {
        $f = LV_APP . '/logs/skribble.log';
        if (!is_file($f)) return '';
        $t = @file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        return implode("\n", array_slice($t, -$lignes));
    }

    public static function configured(): bool
    {
        return trim(setting('skribble_username')) !== '' && trim(setting('skribble_api_key')) !== '';
    }

    /** Authentifie et renvoie le jeton d'accès (mis en cache pour la requête). */
    private static function token(): string
    {
        static $tok = null;
        if ($tok !== null) return $tok;
        [$code, $resp] = self::http('POST', '/access/login', json_encode([
            'username' => trim(setting('skribble_username')),
            'api-key'  => trim(setting('skribble_api_key')),
        ]), false);
        if ($code >= 400 || trim($resp) === '') {
            throw new RuntimeException(tu('sys_skr_auth'));
        }
        $resp = trim($resp);
        if ($resp !== '' && $resp[0] === '{') {
            $j = json_decode($resp, true) ?: [];
            $tok = (string)($j['token'] ?? $j['access_token'] ?? '');
        } else {
            $tok = trim($resp, "\"");
        }
        if ($tok === '') throw new RuntimeException(tu('sys_skr_token'));
        return $tok;
    }

    /**
     * Envoie un PDF à la signature pour un signataire.
     * @return array{id:string, signing_url:string, status:string}
     */
    public static function send(string $pdfPath, string $title, string $email, string $mobile = '', string $lang = ''): array
    {
        // Le titre et le message partent au signataire : sa langue, pas celle du CMS.
        $lang = in_array($lang, I18n::ADMIN_LANGS, true) ? $lang : I18n::$ui;
        if (!is_file($pdfPath)) throw new RuntimeException(tu('sys_skr_pdf'));
        /* [13.08.2026] account_email EN PLUS des données d'identité.
        
           La documentation est explicite : les deux ensemble permettent de
           signer SANS avoir de compte Skribble. Nous n'envoyions que les
           secondes, ce qui revient à demander à quelqu'un d'ouvrir un compte
           avant de pouvoir signer son contrat. */
        $signerData = ['email_address' => $email];
        if (trim($mobile) !== '') $signerData['mobile_number'] = trim($mobile);
        $signataires = [[
            'account_email'        => $email,
            'signer_identity_data' => $signerData,
            'sequence'             => 1,
        ]];

        /* [13.08.2026] Le bureau signe aussi. Un contrat de travail a deux
           parties, et jusqu'ici une seule était appelée à signer : le document
           revenait avec la signature de la personne et rien du côté employeur.
        
           L'ordre compte : la personne d'abord, le bureau ensuite. C'est le
           bureau qui vérifie, et il ne contresigne qu'après avoir vu signer. */
        $bureau = trim((string)setting('skribble_signataire', ''));
        if ($bureau === '') $bureau = trim((string)setting('mail_from', ''));
        if (filter_var($bureau, FILTER_VALIDATE_EMAIL) && mb_strtolower($bureau) !== mb_strtolower($email)) {
            $signataires[] = [
                'account_email'        => $bureau,
                'signer_identity_data' => ['email_address' => $bureau],
                'sequence'             => 2,
            ];
        }

        $payload = [
            'title'      => mb_substr(trim($title), 0, 250) ?: I18n::ta('sys_skr_title', $lang),
            'message'    => I18n::ta('sys_skr_msg', $lang),
            'content'    => base64_encode((string)file_get_contents($pdfPath)),
            'signatures' => $signataires,
        ];
        $quality = strtoupper(trim(setting('skribble_quality')));
        if (in_array($quality, ['SES', 'AES', 'QES'], true)) $payload['quality'] = $quality;

        [$code, $resp] = self::http('POST', '/signature-requests', json_encode($payload), true);
        $j = json_decode($resp, true);
        self::journal(sprintf('ENVOI | %s | %d signataire(s) | HTTP %d | %s',
            $email, count($signataires), $code, $code >= 400 ? mb_substr($resp, 0, 400) : 'OK'));
        if ($code >= 400 || !is_array($j)) {
            $msg = is_array($j) ? (string)($j['message'] ?? $j['error'] ?? '') : '';
            throw new RuntimeException(tu('sys_skr_create') . $msg);
        }
        $url = (string)($j['signatures'][0]['signing_url'] ?? $j['signing_url'] ?? '');
        return [
            'id'          => (string)($j['id'] ?? ''),
            'signing_url' => $url,
            'status'      => (string)($j['status_overall'] ?? 'OPEN'),
        ];
    }

    /** La demande complète, telle que Skribble la renvoie. */
    public static function requete(string $requestId): array
    {
        if (trim($requestId) === '') return [];
        [$code, $resp] = self::http('GET', '/signature-requests/' . rawurlencode($requestId), null, true);
        $j = json_decode($resp, true);
        return $code < 400 && is_array($j) ? $j : [];
    }

    /**
     * Le PDF signé, en octets.                          [13.08.2026]
     *
     * C'était le tuyau manquant. La colonne « signed_filename » existait, le
     * téléchargement la préférait déjà, et RIEN ne la remplissait : le site
     * marquait « signé » et continuait de servir le fichier d'avant. Les deux
     * étaient vrais en même temps, et aucune signature nulle part.
     *
     * La réponse est lue de trois façons parce que je n'ai pas pu l'essayer :
     * un PDF brut commence par %PDF ; certains comptes rendent un objet JSON
     * qui porte le contenu en base64 ; d'autres rendent le base64 tout seul. On
     * accepte les trois plutôt que de parier sur une, et le journal dit
     * laquelle est arrivée — la prochaine lecture de ce fichier saura.
     */
    public static function documentContenu(string $documentId): string
    {
        if (trim($documentId) === '') return '';
        [$code, $resp] = self::http('GET', '/documents/' . rawurlencode($documentId) . '/content', null, true);
        if ($code >= 400 || $resp === '') {
            self::journal('DOCUMENT | ' . $documentId . ' | HTTP ' . $code . ' | ' . mb_substr($resp, 0, 300));
            return '';
        }
        if (str_starts_with($resp, '%PDF')) { self::journal('DOCUMENT | ' . $documentId . ' | PDF brut'); return $resp; }
        $j = json_decode($resp, true);
        if (is_array($j)) {
            $b64 = (string)($j['content'] ?? $j['document'] ?? '');
            $bin = $b64 !== '' ? (string)base64_decode($b64, true) : '';
            self::journal('DOCUMENT | ' . $documentId . ' | JSON base64 ' . ($bin !== '' ? 'ok' : 'vide'));
            return str_starts_with($bin, '%PDF') ? $bin : '';
        }
        $bin = (string)base64_decode(trim($resp), true);
        self::journal('DOCUMENT | ' . $documentId . ' | base64 nu ' . (str_starts_with($bin, '%PDF') ? 'ok' : 'inattendu'));
        return str_starts_with($bin, '%PDF') ? $bin : '';
    }

    /** Statut global d'une demande : OPEN, SIGNING, SIGNED, DECLINED, WITHDRAWN, ERROR. */
    public static function status(string $requestId): string
    {
        if (trim($requestId) === '') return '';
        [$code, $resp] = self::http('GET', '/signature-requests/' . rawurlencode($requestId), null, true);
        $j = json_decode($resp, true);
        if ($code >= 400 || !is_array($j)) throw new RuntimeException(tu('sys_skr_status'));
        return (string)($j['status_overall'] ?? '');
    }

    private static function http(string $method, string $path, ?string $body, bool $auth): array
    {
        $ch = curl_init(self::BASE . $path);
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($auth) $headers[] = 'Authorization: Bearer ' . self::token();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 40,
            CURLOPT_CONNECTTIMEOUT => 12,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false) throw new RuntimeException(tu('sys_skr_conn', $err));
        return [$code, (string)$resp];
    }
}

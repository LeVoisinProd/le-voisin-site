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
        $signerData = ['email_address' => $email];
        if (trim($mobile) !== '') $signerData['mobile_number'] = trim($mobile);

        $payload = [
            'title'      => mb_substr(trim($title), 0, 250) ?: I18n::ta('sys_skr_title', $lang),
            'message'    => I18n::ta('sys_skr_msg', $lang),
            'content'    => base64_encode((string)file_get_contents($pdfPath)),
            'signatures' => [['signer_identity_data' => $signerData]],
        ];
        $quality = strtoupper(trim(setting('skribble_quality')));
        if (in_array($quality, ['SES', 'AES', 'QES'], true)) $payload['quality'] = $quality;

        [$code, $resp] = self::http('POST', '/signature-requests', json_encode($payload), true);
        $j = json_decode($resp, true);
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

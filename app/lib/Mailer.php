<?php
/**
 * Envoi d'emails (multipart, pièces jointes).
 *
 * Version V2-FORMULAIRES / V3-SMTP (29.07.2026) :
 *  - envoi par SMTP authentifié dès qu'un serveur est configuré. C'est
 *    désormais la voie normale : sur le serveur d'Infomaniak la fonction
 *    mail() de PHP est purement et simplement absente (« Call to undefined
 *    function mail() »), aucun envoi ne pouvait aboutir par cette voie ;
 *  - mail() reste utilisé si aucun SMTP n'est configuré ET si la fonction
 *    existe, pour ne rien casser sur un hébergeur qui la propose ;
 *  - si ni l'un ni l'autre n'est disponible, le journal le dit en toutes
 *    lettres au lieu de laisser PHP planter ;
 *  - la détection du type des pièces jointes ne dépend plus de l'extension
 *    fileinfo, absente sur certains serveurs ;
 *  - quand un envoi échoue, le journal dit POURQUOI (voie utilisée, adresse
 *    d'expédition, poids du message, réponse exacte du serveur).
 */
class Mailer
{
    /**
     * @param string[] $to
     * @param array    $attachments  [['path' => ..., 'name' => ...], ...]
     */
    public static function send(array $to, string $subject, string $html, array $attachments = [], ?string $replyTo = null): bool
    {
        $to = array_values(array_filter($to, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
        if (!$to) return false;

        $from     = setting('mail_from', 'no-reply@' . (parse_url(cfg('base_url', ''), PHP_URL_HOST) ?: 'localhost'));
        $fromName = setting('site_name', 'Le Voisin');
        $boundary = 'lv-' . bin2hex(random_bytes(12));
        $altBoundary = 'alt-' . bin2hex(random_bytes(12));

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'From: ' . self::encodeName($fromName) . ' <' . $from . '>';
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $headers[] = 'X-Mailer: LeVoisinCMS';
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

        $text = trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/tr|\/h\d)>/i', "\n", $html)), ENT_QUOTES, 'UTF-8'));

        $body  = "--$boundary\r\n";
        $body .= "Content-Type: multipart/alternative; boundary=\"$altBoundary\"\r\n\r\n";
        $body .= "--$altBoundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($text)) . "\r\n";
        $body .= "--$altBoundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($html)) . "\r\n";
        $body .= "--$altBoundary--\r\n";

        foreach ($attachments as $att) {
            if (empty($att['path']) || !is_file($att['path'])) continue;
            $name = $att['name'] ?? basename($att['path']);
            $mime = self::mimeOf($att['path']);
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: $mime; name=\"" . addslashes($name) . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"" . addslashes($name) . "\"\r\n\r\n";
            $body .= chunk_split(base64_encode((string)file_get_contents($att['path']))) . "\r\n";
        }
        $body .= "--$boundary--\r\n";

        $dest     = implode(', ', $to);
        $sujetEnc = self::encodeName($subject);
        $entetes  = implode("\r\n", $headers);
        $poids    = round(strlen($body) / 1024);

        // ---- Choix de la voie d'envoi ----
        //
        // SMTP dès qu'un serveur est renseigné dans les réglages : c'est la
        // seule voie qui fonctionne chez Infomaniak, et de toute façon la plus
        // fiable ailleurs (le message est authentifié, donc bien moins souvent
        // classé en indésirable).
        $smtpConfigure = trim((string)setting('smtp_host', '')) !== '';

        if ($smtpConfigure) {
            // L'adresse d'enveloppe doit correspondre au compte authentifié,
            // sinon le serveur refuse le message comme usurpation.
            $user      = trim((string)setting('smtp_user', ''));
            $enveloppe = filter_var($user, FILTER_VALIDATE_EMAIL) ? $user : $from;

            $entetesComplets = $entetes . "\r\n"
                . 'Date: ' . date('r') . "\r\n"
                . 'To: ' . $dest . "\r\n"
                . 'Subject: ' . $sujetEnc . "\r\n"
                . 'Message-ID: <' . bin2hex(random_bytes(10)) . '@'
                . (parse_url((string)cfg('base_url', ''), PHP_URL_HOST) ?: 'le-voisin.com') . '>';

            $ok = Smtp::send($enveloppe, $to, $entetesComplets . "\r\n\r\n" . $body);

            $detail = 'voie: SMTP ' . setting('smtp_host', '') . ':' . setting('smtp_port', '587')
                    . ' | de: ' . $from
                    . ($enveloppe !== $from ? ' (enveloppe ' . $enveloppe . ')' : '')
                    . ' | ' . count($attachments) . ' pièce(s) jointe(s) | message ' . $poids . ' Ko';
            if (!$ok) $detail .= ' | ÉCHEC : ' . Smtp::$erreur;

            self::log($to, $subject, $ok, $detail);
            return $ok;
        }

        // ---- Repli : la fonction mail() de PHP ----
        //
        // Elle n'existe pas partout. Sans ce garde-fou, PHP lève une erreur
        // fatale « Call to undefined function mail() » et l'envoi part en
        // erreur sans que personne ne sache pourquoi.
        if (!function_exists('mail')) {
            self::log($to, $subject, false,
                'voie: aucune | la fonction mail() est désactivée sur ce serveur et aucun serveur SMTP '
              . 'n\'est configuré — renseignez Administration > Réglages > Envoi des emails');
            return false;
        }

        $detail = 'voie: mail() | de: ' . $from . ' | ' . count($attachments)
                . ' pièce(s) jointe(s) | message ' . $poids . ' Ko';

        $avant = error_get_last();
        $ok = @mail($dest, $sujetEnc, $body, $entetes, '-f' . $from);

        // Deuxième essai sans le paramètre « -f ».
        //
        // Ce paramètre impose l'adresse d'enveloppe du message. Beaucoup
        // d'hébergeurs le refusent quand l'adresse indiquée ne correspond pas à
        // une vraie boîte du compte : l'envoi échoue alors sans explication.
        // Plutôt que d'abandonner, on retente une fois en laissant le serveur
        // choisir lui-même l'adresse d'enveloppe.
        if (!$ok) {
            $ok = @mail($dest, $sujetEnc, $body, $entetes);
            if ($ok) $detail .= ' | accepté au 2e essai, SANS le paramètre -f'
                              . ' (le serveur refuse ' . $from . ' comme adresse d\'enveloppe)';
        }

        // Raison exacte du refus, telle que PHP la rapporte.
        if (!$ok) {
            $err = error_get_last();
            if ($err && $err !== $avant && !empty($err['message'])) {
                $detail .= ' | PHP dit : ' . preg_replace('/\s+/', ' ', $err['message']);
            } else {
                $detail .= ' | mail() a répondu « non » sans message d\'erreur';
            }
        }

        self::log($to, $subject, (bool)$ok, $detail);
        return (bool)$ok;
    }

    /**
     * Type d'une pièce jointe. mime_content_type() n'existe que si l'extension
     * « fileinfo » est active sur le serveur ; sans ce garde-fou, un serveur
     * sans fileinfo provoquait une erreur fatale au moment de l'envoi.
     */
    private static function mimeOf(string $path): string
    {
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($path);
            if (is_string($m) && $m !== '') return $m;
        }
        if (function_exists('finfo_open')) {
            $fi = @finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $m = @finfo_file($fi, $path);
                @finfo_close($fi);
                if (is_string($m) && $m !== '') return $m;
            }
        }
        return 'application/octet-stream';
    }

    private static function encodeName(string $s): string
    {
        return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private static function log(array $to, string $subject, bool $ok, string $detail = ''): void
    {
        $dir = LV_APP . '/logs';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $line = sprintf("[%s] %s | to: %s | %s%s\n", date('Y-m-d H:i:s'), $ok ? 'OK' : 'FAIL',
            implode(',', $to), $subject, $detail !== '' ? ' | ' . $detail : '');
        @file_put_contents($dir . '/mail.log', $line, FILE_APPEND);
    }

    /** Petit gabarit HTML commun aux emails. */
    public static function wrap(string $title, string $inner): string
    {
        $site = e(setting('site_name', 'Le Voisin'));
        return '<!DOCTYPE html><html><body style="margin:0;padding:24px;background:#f4f4f2;font-family:Helvetica,Arial,sans-serif;color:#111">'
            . '<div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #e5e5e0;">'
            . '<div style="padding:18px 28px;border-bottom:2px solid #111;font-weight:bold;letter-spacing:.12em;">' . $site . '</div>'
            . '<div style="padding:28px;"><h2 style="margin:0 0 16px;font-size:18px;">' . e($title) . '</h2>' . $inner . '</div>'
            . '</div></body></html>';
    }
}

<?php
/**
 * Le taux de change EUR ↔ CHF.  [Anna, 22.08.2026]
 *
 * « quando for em chf fazer a conversão com a taxa cambial do dia. »
 *
 * LA SOURCE EST LA BANQUE CENTRALE EUROPÉENNE, son taux de référence quotidien.
 * Pas de clef, pas de compte, pas de facture, et c'est la référence qu'un
 * fiduciaire reconnaît. Vérifié depuis le serveur avant d'écrire une ligne de
 * code: HTTP 200, 1 EUR = 0,9353 CHF au 21.08.2026.
 *
 * ELLE NE PUBLIE QUE LES JOURS OUVRÉS, vers 16 h. Un samedi, un dimanche, un
 * 1er janvier, il n'y a pas de taux du jour, et le fichier quotidien porte
 * alors la date du dernier jour ouvré. On garde donc TOUJOURS la date que la
 * BCE annonce, jamais celle de notre requête: « le taux du jour » d'un dimanche
 * est celui du vendredi, et le dire vaut mieux que de laisser croire autre chose.
 *
 * SI ELLE NE RÉPOND PAS, ON PREND LE DERNIER TAUX CONNU — décidé par Anna à la
 * question posée. Le travail ne s'arrête pas pour une panne de réseau, et
 * l'écran affiche la date du taux employé. Un taux d'avant-hier annoncé vaut
 * mieux qu'une saisie refusée.
 *
 * ON N'APPELLE PAS PLUS D'UNE FOIS PAR JOUR: dès que le taux du jour est en
 * base, il en sort. La BCE demande explicitement qu'on ne martèle pas son
 * fichier, et rien ne le justifierait — il ne change qu'une fois.
 */
declare(strict_types=1);

final class Change
{
    private const URL   = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
    private const DELAI = 6;

    /** Le dernier recours, si la BCE n'a jamais répondu et que la base est vide. */
    private const SECOURS = ['taux' => 0.9353, 'jour' => '2026-08-21'];

    /**
     * Le taux le plus récent dont nous disposons, et sa date.
     *
     * @return array{taux:float, jour:string, frais:bool} `frais` dit si c'est
     *         bien le taux publié aujourd'hui.
     */
    public static function eurChf(): array
    {
        $auj = date('Y-m-d');

        /* Déjà relevé aujourd'hui: on ne redemande pas. */
        try {
            $r = DB::one('SELECT jour, taux FROM taux_change
                           WHERE devise = ? AND releve_le >= ? ORDER BY jour DESC LIMIT 1',
                         ['CHF', $auj . ' 00:00:00']);
            if ($r) {
                return ['taux' => (float)$r['taux'], 'jour' => (string)$r['jour'],
                        'frais' => (string)$r['jour'] === $auj];
            }
        } catch (Throwable $e) { /* la table peut ne pas exister encore */ }

        $neuf = self::interroger();
        if ($neuf !== null) {
            try {
                DB::pdo()->prepare(
                    'INSERT INTO taux_change (jour, devise, taux, source, releve_le)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE taux = VALUES(taux), releve_le = VALUES(releve_le)'
                )->execute([$neuf['jour'], 'CHF', $neuf['taux'], 'BCE', date('Y-m-d H:i:s')]);
            } catch (Throwable $e) { /* un taux non gardé reste un taux juste */ }
            return $neuf + ['frais' => $neuf['jour'] === $auj];
        }

        /* La BCE n'a pas répondu: le dernier connu, quel que soit son âge. */
        try {
            $r = DB::one('SELECT jour, taux FROM taux_change
                           WHERE devise = ? ORDER BY jour DESC LIMIT 1', ['CHF']);
            if ($r) {
                return ['taux' => (float)$r['taux'], 'jour' => (string)$r['jour'], 'frais' => false];
            }
        } catch (Throwable $e) { /* rien en base non plus */ }

        return self::SECOURS + ['frais' => false];
    }

    /** @return array{taux:float, jour:string}|null */
    private static function interroger(): ?array
    {
        if (!function_exists('curl_init')) return null;
        try {
            $c = curl_init(self::URL);
            curl_setopt_array($c, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::DELAI,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_USERAGENT      => 'LeVoisinDashboard/1.0 (+https://le-voisin.com)',
            ]);
            $rep  = curl_exec($c);
            $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
            curl_close($c);
            if ($code !== 200 || !is_string($rep)) return null;

            /* On lit à l'expression régulière et non en XML: le fichier tient en
               quarante lignes, sa forme n'a pas bougé depuis vingt ans, et
               charger un analyseur pour deux valeurs coûte plus que ça ne
               protège. Ce qu'on vérifie, c'est que les deux valeurs existent. */
            if (!preg_match('~time=[\'"]([0-9]{4}-[0-9]{2}-[0-9]{2})[\'"]~', $rep, $j)) return null;
            if (!preg_match('~currency=[\'"]CHF[\'"]\s+rate=[\'"]([0-9.]+)[\'"]~', $rep, $t)) return null;

            $taux = (float)$t[1];
            /* Une garde de bon sens: l'euro a valu entre 0,85 et 1,70 franc
               depuis qu'il existe. Hors de cette fourchette, c'est que nous
               avons mal lu, et un taux faux se propage dans tous les budgets. */
            if ($taux < 0.5 || $taux > 2.5) return null;

            return ['taux' => $taux, 'jour' => $j[1]];
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Convertit un montant vers CHF avec un taux donné (1 EUR = $taux CHF). */
    public static function versChf(float $montant, string $devise, float $taux): float
    {
        return $devise === 'EUR' ? round($montant * $taux, 2) : $montant;
    }
}

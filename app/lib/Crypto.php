<?php
/**
 * Chiffrement au repos des fiches personnelles (IBAN, numéro AVS, adresse,
 * date de naissance...).                                        [V38-CHIFFRE]
 *
 * Le mot de passe protège l'entrée de l'espace ; ceci protège ce qu'il y a
 * derrière la porte, si la base de données elle-même est un jour lue par
 * quelqu'un qui n'aurait pas dû (sauvegarde égarée, accès mal cloisonné...).
 *
 * La clé n'est pas nouvelle : c'est le même secret que celui déjà présent
 * dans config.php pour les sessions, mais utilisé différemment. On en tire
 * une clé dédiée au chiffrement, distincte de celle qui signe les sessions,
 * en y ajoutant un « contexte » — la même façon de faire que la clé de
 * sessions n'a jamais servi qu'à cela. Rien à ajouter dans config.php,
 * personne n'a de nouveau mot de passe à ranger quelque part.
 *
 * Migration silencieuse : une valeur écrite avant ce chiffrement est du texte
 * clair, sans le préfixe « sb1: ». dechiffrer() la renvoie telle quelle —
 * elle redevient illisible dès le prochain enregistrement de la fiche, sans
 * qu'il y ait de bascule à faire à la main.
 */
class Crypto
{
    private const PREFIXE = 'sb1:';

    private static ?string $cle = null;

    private static function cle(): string
    {
        if (self::$cle === null) {
            $secret = (string)cfg('secret', '');
            if ($secret === '') {
                throw new RuntimeException('Chiffrement impossible : config.secret est vide.');
            }
            self::$cle = sodium_crypto_generichash(
                $secret . '|member-data-v1',
                '',
                SODIUM_CRYPTO_SECRETBOX_KEYBYTES
            );
        }
        return self::$cle;
    }

    /** Chiffre une chaîne. '' reste '' — inutile de chiffrer une fiche vide. */
    public static function chiffrer(string $clair): string
    {
        if ($clair === '') return '';
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $boite = sodium_crypto_secretbox($clair, $nonce, self::cle());
        return self::PREFIXE . base64_encode($nonce . $boite);
    }

    /**
     * Déchiffre. Une valeur vide, ou écrite avant le chiffrement (sans le
     * préfixe), est renvoyée telle quelle.
     */
    public static function dechiffrer(?string $valeur): string
    {
        $valeur = (string)$valeur;
        if ($valeur === '' || !str_starts_with($valeur, self::PREFIXE)) {
            return $valeur;
        }
        $brut = base64_decode(substr($valeur, strlen(self::PREFIXE)), true);
        if ($brut === false || strlen($brut) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return '';
        $nonce = substr($brut, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $boite = substr($brut, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $clair = sodium_crypto_secretbox_open($boite, $nonce, self::cle());
        // Clé changée ou donnée corrompue : mieux vaut une fiche vide qu'un
        // message d'erreur qui casse la page de quelqu'un d'autre.
        return $clair === false ? '' : $clair;
    }
}

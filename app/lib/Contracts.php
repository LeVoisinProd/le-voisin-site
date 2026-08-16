<?php
/**
 * Les contrats d'un booking: déposer, envoyer à la signature, suivre. [16.08.2026]
 *
 * POURQUOI CE FICHIER PLUTÔT QUE DU CODE DANS L'ÉCRAN. Skribble::rafraichirUn()
 * existe déjà et fait exactement ce travail — mais elle écrit en dur dans
 * `member_documents`, parce qu'elle a été écrite pour l'espace collaborateur.
 * La rendre générique demanderait de toucher un chemin qui fonctionne en
 * production et que l'espace utilise tous les jours. On écrit donc à côté, sur
 * les mêmes briques publiques du Skribble: send(), status(), requete(),
 * documentContenu().
 *
 * OÙ VIVENT LES FICHIERS. `uploads/private/c/<id>/`, à côté du `m/<id>/` des
 * documents de collaborateur. Le dossier `private` porte déjà un .htaccess
 * « Require all denied » posé le 27.07.2026: rien n'y est servi par Apache. Le
 * téléchargement passe donc par le dashboard, qui vérifie le rôle — ce qui est
 * mieux qu'un lien deviné, et c'est le point: un contrat de cession porte des
 * montants négociés que tout le monde n'a pas à lire.
 *
 * CE QUI N'EST PAS ICI, ET C'EST VOULU: la génération du PDF. Le site n'a
 * aucune bibliothèque pour cela, vérifié le 16.08.2026. Le contrat se rédige
 * là où il se rédige aujourd'hui et se dépose ici. Le jour où l'on voudra le
 * produire depuis les données du booking, ce sera une brique de plus, pas une
 * réécriture de celle-ci.
 */
declare(strict_types=1);

class Contracts
{
    /** La racine privée, la même que celle des documents de collaborateur. */
    public static function racine(): string
    {
        return dirname(__DIR__, 2) . '/uploads/private/c';
    }

    /**
     * S'assure que uploads/private/ refuse tout, et le repose s'il manque.
     *                                                          [16.08.2026]
     * POURQUOI LE CODE POSE CE FICHIER PLUTÔT QUE LE DÉPÔT. Parce que
     * `/uploads/` est entièrement dans le .gitignore — à raison: il porte des
     * contrats, des fiches de salaire et des pièces d'identité, qui n'ont rien
     * à faire dans un dépôt de code. Mais cela emporte aussi le .htaccess qui
     * les protège, qui n'existe donc QUE sur le serveur, posé à la main le
     * 27.07.2026. Une restauration, un changement d'hébergeur, une copie faite
     * sans les fichiers cachés, et le dossier devient lisible par le web sans
     * que rien ne le signale.
     *
     * C'est déjà le parti pris de Catalog::dossier() pour `medias/`: la
     * protection naît avec le dossier, en code, et se répare toute seule.
     */
    private static function garantirRefus(): void
    {
        $priv = dirname(self::racine());          // …/uploads/private
        if (!is_dir($priv)) @mkdir($priv, 0775, true);

        $ht = $priv . '/.htaccess';
        if (is_file($ht) && str_contains((string)file_get_contents($ht), 'denied')) return;

        @file_put_contents($ht,
            "# Rien d'ici n'est servi par le web. Reposé automatiquement par\n" .
            "# app/lib/Contracts.php: ce fichier n'est pas dans le dépôt, car\n" .
            "# /uploads/ y est exclu, et il ne doit pas dépendre d'une copie.\n" .
            "Require all denied\n" .
            "<IfModule !mod_authz_core.c>\n" .
            "Order allow,deny\n" .
            "Deny from all\n" .
            "</IfModule>\n");
    }

    public static function dossier(int $id): string
    {
        self::garantirRefus();
        $d = self::racine() . '/' . $id;
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }

    public static function chemin(array $c, bool $prefereSigne = true): string
    {
        $d = self::dossier((int)$c['id']);
        $s = trim((string)($c['fichier_signe'] ?? ''));
        if ($prefereSigne && $s !== '') return $d . '/' . $s;
        return $d . '/' . $c['fichier'];
    }

    /** Les contrats d'un booking, le plus récent d'abord. */
    public static function duBooking(int $bookingId): array
    {
        return DB::all('SELECT * FROM contract WHERE booking_id = ? ORDER BY cree_a DESC, id DESC',
                       [$bookingId]);
    }

    public static function un(int $id): ?array
    {
        return DB::one('SELECT * FROM contract WHERE id = ?', [$id]);
    }

    /**
     * Dépose un PDF sur un booking.
     *
     * Refuse tout ce qui n'est pas un PDF, et pas seulement d'après le nom:
     * Skribble refuserait de toute façon, mais un `.pdf` qui n'en est pas un
     * échouerait alors à l'envoi, c'est-à-dire trop tard et loin de la cause.
     *
     * @return int l'id du contrat créé
     */
    public static function deposer(int $bookingId, string $type, string $titre, array $fichier): int
    {
        if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Le dépôt du fichier a échoué.');
        }
        $tmp = (string)$fichier['tmp_name'];
        if (!is_uploaded_file($tmp)) throw new RuntimeException('Fichier inattendu.');

        $tete = (string)file_get_contents($tmp, false, null, 0, 5);
        if (!str_starts_with($tete, '%PDF')) {
            throw new RuntimeException('Seuls les PDF sont acceptés: la signature en ligne ne sait rien signer d\'autre.');
        }

        $nom = self::nomSur((string)($fichier['name'] ?? 'contrat.pdf'));
        $titre = trim($titre) !== '' ? trim($titre) : pathinfo($nom, PATHINFO_FILENAME);

        $id = DB::insert('contract', [
            'booking_id' => $bookingId,
            'type'       => $type,
            'titre'      => mb_substr($titre, 0, 190),
            'fichier'    => $nom,
            'statut'     => 'depose',
        ]);

        $dest = self::dossier($id) . '/' . $nom;
        if (!move_uploaded_file($tmp, $dest)) {
            DB::delete('contract', 'id = ?', [$id]);
            throw new RuntimeException('Impossible d\'enregistrer le fichier.');
        }
        return $id;
    }

    /** Un nom de fichier sans surprise: pas de chemin, pas d'accent douteux. */
    private static function nomSur(string $nom): string
    {
        $nom = basename($nom);
        $nom = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nom) ?: 'contrat.pdf';
        if (!preg_match('/\.pdf$/i', $nom)) $nom .= '.pdf';
        return mb_substr($nom, 0, 180);
    }

    /**
     * Envoie à la signature.
     *
     * Le statut ne passe à « envoye » que si Skribble a bien pris la demande:
     * un envoi raté qui laisserait la ligne en « envoye » ferait attendre une
     * signature qui n'a jamais été demandée, et c'est exactement le genre de
     * mensonge qu'on ne voit qu'un mois plus tard.
     */
    public static function envoyer(int $id, string $email, string $mobile = '', string $nom = ''): array
    {
        $c = self::un($id);
        if (!$c) throw new RuntimeException('Contrat introuvable.');
        if (!Skribble::configured()) {
            throw new RuntimeException('La signature en ligne n\'est pas configurée sur ce site.');
        }
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Il faut une adresse e-mail valide pour envoyer à la signature.');
        }

        $pdf = self::chemin($c, false);
        if (!is_file($pdf)) throw new RuntimeException('Le PDF de ce contrat est introuvable sur le disque.');

        $r = Skribble::send($pdf, (string)$c['titre'], $email, trim($mobile));

        DB::update('contract', [
            'statut'              => 'envoye',
            'signataire_nom'      => trim($nom) !== '' ? mb_substr(trim($nom), 0, 190) : null,
            'signataire_email'    => mb_substr($email, 0, 190),
            'signataire_mobile'   => trim($mobile) !== '' ? mb_substr(trim($mobile), 0, 40) : null,
            'skribble_request_id' => (string)($r['id'] ?? ''),
            'signing_url'         => mb_substr((string)($r['signing_url'] ?? ''), 0, 500),
            'envoye_a'            => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        return $r;
    }

    /**
     * Interroge Skribble et met la ligne à jour. Rend le statut lu.
     *
     * LE RAPATRIEMENT NE REMET JAMAIS LA SIGNATURE EN CAUSE. Même parti pris
     * que Skribble::rafraichirUn: si la copie signée ne se télécharge pas, la
     * signature a quand même eu lieu; c'est le fichier qui manque, et le
     * passage suivant réessaiera. On ne repasse donc jamais « signe » en
     * arrière.
     */
    public static function rafraichir(int $id): string
    {
        $c = self::un($id);
        if (!$c) return '';
        $req = trim((string)($c['skribble_request_id'] ?? ''));
        if ($req === '') return (string)$c['statut'];

        $st = strtoupper(Skribble::status($req));

        if ($st === 'DECLINED') {
            DB::update('contract', ['statut' => 'refuse'], 'id = ?', [$id]);
            return 'refuse';
        }
        if ($st !== 'SIGNED') return (string)$c['statut'];

        if ((string)$c['statut'] !== 'signe') {
            DB::update('contract',
                ['statut' => 'signe', 'signe_a' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        }

        // La copie signée, si on ne l'a pas encore.
        if (trim((string)($c['fichier_signe'] ?? '')) === '') {
            try {
                $rq  = Skribble::requete($req);
                $did = (string)($rq['document_id'] ?? $rq['document']['id'] ?? '');
                if ($did !== '') {
                    $bin = Skribble::documentContenu($did);
                    if (str_starts_with($bin, '%PDF')) {
                        $nom = preg_replace('/\.pdf$/i', '', self::nomSur((string)$c['fichier'])) . '_signe.pdf';
                        file_put_contents(self::dossier($id) . '/' . $nom, $bin);
                        DB::update('contract', ['fichier_signe' => $nom], 'id = ?', [$id]);
                    }
                }
            } catch (Throwable $e) {
                Skribble::journal('CONTRAT ' . $id . ' | rapatriement: ' . $e->getMessage());
            }
        }
        return 'signe';
    }

    /** Rafraîchit d'un coup ceux d'un booking qui attendent encore. */
    public static function rafraichirBooking(int $bookingId): void
    {
        if (!Skribble::configured()) return;
        foreach (self::duBooking($bookingId) as $c) {
            if ((string)$c['statut'] === 'envoye' && trim((string)$c['skribble_request_id']) !== '') {
                try { self::rafraichir((int)$c['id']); }
                catch (Throwable $e) { Skribble::journal('CONTRAT ' . $c['id'] . ' | ' . $e->getMessage()); }
            }
        }
    }

    /** Supprime le contrat et ses fichiers. */
    public static function supprimer(int $id): void
    {
        $d = self::racine() . '/' . $id;
        if (is_dir($d)) {
            foreach (glob($d . '/*') ?: [] as $f) @unlink($f);
            @rmdir($d);
        }
        DB::delete('contract', 'id = ?', [$id]);
    }
}

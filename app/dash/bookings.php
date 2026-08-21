<?php
/**
 * Écran Événements — les dates jouées. [16.08.2026, renommé le 17.08]
 *
 * Une date jouée, ou en cours de l'être. C'est l'objet central du dashboard, et
 * il n'existait nulle part comme donnée: `events` du CMS portait une chaîne
 * d'affichage sans cachet ni statut, `lv-tour` portait 35 lignes codées en dur
 * et EN LECTURE SEULE, sans aucun formulaire pour en créer une.
 *
 * DEUX VUES DANS UN SEUL FICHIER, choisies par ?b=<id>: la liste, et la fiche
 * avec ses cinq onglets. Elles partagent trop pour vivre séparées, et le
 * fichier reste lisible tant qu'il n'y a que ces deux-là.
 *
 * LES CINQ ONGLETS SONT ÉCRITS depuis le 16.08.2026. Ils étaient déclarés et
 * vides, chacun disant ce qui lui manquait comme table; ces tables existent
 * maintenant.
 *
 *   Deal        les lignes qui composent le prix, et l'écart avec le prix annoncé
 *   Factures    ce qui est parti et ce qui n'est pas rentré
 *   Contrats    déposer un PDF, l'envoyer à la signature, suivre l'état
 *   Advancing   ce qu'on demande au lieu, un état par élément, et le portail
 *               où il répond — advancing.php, la quatrième porte du site
 *   Voyage      vols, transferts, hôtels, comme données et non comme fichiers
 *
 * CE QUI RESTE HORS DE PORTÉE, et c'est dit dans l'onglet plutôt qu'ici: la
 * liaison bexio, donc l'émission des factures. Le portage du client depuis
 * Apps Script est chiffré entre 12 h et 20 h pour le seul OAuth2. L'onglet
 * Factures suit ce qui existe; il n'émet rien.
 */
declare(strict_types=1);

const PAR_PAGE = 60;

/** Les onglets de la fiche, dans l'ordre demandé par Anna. */
const ONGLETS = [
    'apercu'    => 'Aperçu',
    'deal'      => 'Deal',
    'factures'  => 'Factures',
    'contrats'  => 'Contrats',
    'advancing' => 'Advancing',
    'voyage'    => 'Voyage',
];

$id = (int)($_GET['b'] ?? 0);

/**
 * Le filtre de la liste, en un seul endroit.  [21.08.2026]
 *
 * Il servait à la liste et il sert maintenant aussi aux flèches « précédent »
 * et « suivant » de la fiche. Deux copies auraient dérivé au premier filtre
 * ajouté, et la dérive serait muette: les flèches emmèneraient hors de ce
 * qu'on croyait parcourir.
 *
 * @return array{0:string,1:array} le WHERE et ses arguments
 */
function bookings_filtre(): array
{
    $q      = trim((string)($_GET['q'] ?? ''));
    $statut = trim((string)($_GET['s'] ?? ''));
    $annee  = trim((string)($_GET['a'] ?? ''));

    $where = ['supprime_le IS NULL'];
    $args  = [];
    if ($statut !== '' && isset(['option'=>1,'confirmed'=>1,'canceled'=>1,'pending'=>1][$statut])) {
        $where[] = 'statut = ?'; $args[] = $statut;
    }
    if ($annee !== '' && ctype_digit($annee)) { $where[] = 'YEAR(date_debut) = ?'; $args[] = (int)$annee; }
    if ($q !== '') {
        $like = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
        $where[] = '(venue LIKE ? OR projet LIKE ? OR artiste LIKE ? OR ville LIKE ? OR client LIKE ?)';
        array_push($args, $like, $like, $like, $like, $like);
    }
    return [implode(' AND ', $where), $args];
}

/** Ce qu'il faut recoller à l'URL pour rester dans la même liste. */
function bookings_contexte(): string
{
    $bout = '';
    foreach (['q', 's', 'a'] as $c) {
        $v = trim((string)($_GET[$c] ?? ''));
        if ($v !== '') $bout .= '&amp;' . $c . '=' . rawurlencode($v);
    }
    return $bout;
}

/**
 * La fiche précédente et la suivante, dans l'ordre exact de la liste.
 *
 * ON LIT LA COLONNE DES ID ENTIÈRE, et ce n'est pas de la paresse. La
 * variante « le premier dont la date est antérieure » se trompe dès qu'une
 * date est nulle — MySQL range les NULL en fin de tri descendant, et une
 * comparaison ne les rattrape pas — et se trompe encore sur deux dates
 * identiques, ce qui est le cas ordinaire d'une série. Quatre-vingt-six
 * lignes aujourd'hui, quelques centaines à terme: une colonne d'entiers ne
 * coûte rien, et elle donne le même ordre que la liste par construction.
 *
 * @return array{prec:?int, suiv:?int, rang:int, total:int}
 */
function bookings_voisins(int $id): array
{
    [$w, $args] = bookings_filtre();
    $st = DB::pdo()->prepare("SELECT id FROM booking WHERE $w ORDER BY date_debut DESC, id DESC");
    $st->execute($args);
    $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

    $i = array_search($id, $ids, true);
    if ($i === false) return ['prec' => null, 'suiv' => null, 'rang' => 0, 'total' => count($ids)];

    return [
        'prec'  => $ids[$i - 1] ?? null,
        'suiv'  => $ids[$i + 1] ?? null,
        'rang'  => $i + 1,
        'total' => count($ids),
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// ENREGISTRER  (avant tout affichage: on redirige, on ne rend rien)
// ═══════════════════════════════════════════════════════════════════════════

$CHAMPS = ['projet','artiste','venue','venue_url','ville','pays','date_debut','date_fin',
           'date_texte','heure','prix_cession','prix_vente','frais_booking',
           'frais_booking_taux','devise','client','statut',
           'representations','notes_artiste','notes_internes'];
$STATUTS = ['pending' => 'en attente', 'option' => 'option',
            'confirmed' => 'confirmé', 'canceled' => 'annulé'];

$err = [];
$saisi = [];

/* TÉLÉCHARGER UN CONTRAT. [16.08.2026]

   Avant toute sortie, parce que cela répond un PDF et non une page.

   Il passe par ici et non par un lien direct: les contrats vivent dans
   uploads/private/, qu'Apache refuse de servir depuis le 27.07.2026, et c'est
   voulu — un contrat de cession porte des montants négociés. Le dashboard a
   déjà vérifié le rôle à la porte, donc arriver ici c'est avoir le droit de
   voir cet écran. La LECTURE suffit: on ne demande pas dash_exige_ecriture,
   télécharger n'est pas modifier. */
$dl = (int)($_GET['dl'] ?? 0);
if ($dl > 0) {
    $c = Contracts::un($dl);
    /* Le contrat doit appartenir AU booking demandé. Sans cette égalité,
       changer `dl` dans l'adresse servirait n'importe quel contrat du site. */
    if (!$c || (int)$c['booking_id'] !== $id) { http_response_code(404); exit('Introuvable'); }

    $signe = ((string)($_GET['v'] ?? '')) === 'signe' && trim((string)$c['fichier_signe']) !== '';
    $f = Contracts::chemin($c, $signe);
    if (!is_file($f)) { http_response_code(404); exit('Fichier introuvable'); }

    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($f));
    header('Content-Disposition: inline; filename="' . basename($f) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($f);
    exit;
}

/* TÉLÉCHARGER UN FICHIER DE LA DATE. Forcé en attachement, jamais rendu dans
   la page: un SVG déposé par un tiers peut porter du script. */
$bf = (int)($_GET['bf'] ?? 0);
if ($bf > 0) {
    $f = BookingFiles::un($bf);
    if (!$f || (int)$f['booking_id'] !== $id) { http_response_code(404); exit('Introuvable'); }
    $p = BookingFiles::chemin($f);
    if (!is_file($p)) { http_response_code(404); exit('Fichier introuvable'); }
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($p));
    header('Content-Disposition: attachment; filename="' . basename($p) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($p);
    exit;
}

/* TÉLÉCHARGER UN FICHIER DÉPOSÉ PAR LE LIEU. Même parti que les contrats:
   uploads/private/ n'est pas servi par Apache, et le rôle est déjà vérifié à
   la porte. Le champ doit appartenir à CE booking. */
$adl = (int)($_GET['adl'] ?? 0);
if ($adl > 0) {
    $c = Advancing::champ($adl);
    if (!$c || (int)$c['booking_id'] !== $id || !$c['fichier']) { http_response_code(404); exit('Introuvable'); }
    $f = Advancing::dossier($adl) . '/' . $c['fichier'];
    if (!is_file($f)) { http_response_code(404); exit('Fichier introuvable'); }

    /* Téléchargement forcé et jamais rendu dans la page: un SVG déposé par un
       tiers peut porter du script, et l'afficher en ligne l'exécuterait dans
       notre domaine. */
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($f));
    header('Content-Disposition: attachment; filename="' . basename($f) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($f);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    /* Le rôle décide aussi de l'écriture, et pas seulement de l'accès à
       l'écran: `production` lit les Finances sans les modifier. Le routeur
       ne peut pas le faire à notre place, lui ne voit pas les POST. */
    dash_exige_ecriture('bookings');

    /* ── LES PRIX SAISIS EN LOT ────────────────────────────────────────────
       [16.08.2026] On n'écrit QUE les lignes qui changent, et un champ vide ne
       vide rien. Une grille de cinquante champs se survole; un enregistrement
       distrait ne doit pas effacer ce qu'on n'a pas regardé. Pour remettre à
       zéro on écrit 0 — geste explicite, qui ne se fait pas par inadvertance. */
    if (($_POST['act'] ?? '') === 'prix_lot') {
        $nbr = static function ($x): ?float {
            $t = trim((string)$x);
            if ($t === '') return null;
            /* On accepte « 12 000 », « 12'000 » et « 12000.50 »: c'est ainsi
               qu'on écrit un montant en Suisse, et refuser l'apostrophe ferait
               perdre la saisie sans dire pourquoi. */
            $t = str_replace([' ', "'", "Â ", ','], ['', '', '', '.'], $t);
            return is_numeric($t) ? (float)$t : null;
        };
        $ids = array_map('intval', array_keys($_POST['c'] ?? []));
        $n = 0;
        foreach ($ids as $bid) {
            if ($bid <= 0) continue;
            $ligne = DB::one('SELECT prix_cession, prix_vente, devise FROM booking
                               WHERE id = ? AND supprime_le IS NULL', [$bid]);
            if (!$ligne) continue;

            $c = $nbr($_POST['c'][$bid] ?? '');
            $v = $nbr($_POST['v'][$bid] ?? '');
            $d = in_array($_POST['d'][$bid] ?? '', ['CHF', 'EUR'], true) ? $_POST['d'][$bid] : (string)$ligne['devise'];

            $maj = [];
            if ($c !== null && (float)$ligne['prix_cession'] !== $c) $maj['prix_cession'] = $c;
            if ($v !== null && (float)$ligne['prix_vente']   !== $v) $maj['prix_vente']   = $v;
            if ($d !== (string)$ligne['devise'])                     $maj['devise']       = $d;
            if (!$maj) continue;

            $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($maj)));
            DB::run("UPDATE booking SET $sets WHERE id = ?", [...array_values($maj), $bid]);
            $n++;
        }
        dash_flash($n > 0 ? "$n date(s) mise(s) à jour." : 'Rien n\'a changé.');
        redirect('/dashboard.php?e=bookings&v=prix');
    }

    /* LES NOTES SE CORRIGENT DEPUIS L'APERÇU. [Anna, 21.08.2026] « laisser
       cette partie éditable ». C'est la même raison que pour les grilles des
       associations le 20.08: écrire une note est déjà une écriture, et elle a
       son propre formulaire. Passer par « modifier » obligeait à ouvrir la
       fiche entière — donc à annoncer qu'on va tout changer — pour ajouter une
       ligne qu'on vient d'apprendre au téléphone.

       DEUX FORMULAIRES ET NON UN. Les deux natures de notes n'ont pas le même
       destinataire: l'une part avec l'artiste, l'autre jamais. Un seul bouton
       enregistrerait les deux d'un coup, et un copier-coller malheureux dans
       la mauvaise case partirait sans qu'on l'ait relu. */
    $note = (string)($_POST['bnote'] ?? '');
    if ($note !== '' && $id > 0) {
        $col = $note === 'artiste' ? 'notes_artiste'
             : ($note === 'internes' ? 'notes_internes' : '');
        if ($col !== '') {
            DB::update('booking', [$col => trim((string)($_POST['texte'] ?? ''))],
                       'id = ?', [$id]);
            dash_flash($note === 'artiste'
                ? 'Notes artiste enregistrées. L\'artiste les voit.'
                : 'Notes internes enregistrées. Elles ne sortent pas de l\'équipe.');
        }
        redirect('/dashboard.php?e=bookings&b=' . $id);
    }

    /* Les fichiers de la date. */
    $actB = (string)($_POST['bfic'] ?? '');
    if ($actB !== '' && $id > 0) {
        try {
            if ($actB === 'deposer') {
                $u = Auth::user();
                BookingFiles::deposer($id, $_FILES['fichier'] ?? [],
                                      (string)($_POST['partage'] ?? 'interne'),
                                      (string)($u['name'] ?? $u['email'] ?? ''));
                dash_flash('Fichier déposé.');
            } elseif ($actB === 'partage') {
                BookingFiles::partage((int)($_POST['ligne'] ?? 0), $id, (string)($_POST['p'] ?? 'interne'));
                dash_flash('Partage changé.');
            } elseif ($actB === 'supprimer') {
                BookingFiles::supprimer((int)($_POST['ligne'] ?? 0), $id);
                dash_flash('Fichier supprimé.');
            }
        } catch (Throwable $ex) { dash_flash($ex->getMessage(), 'err'); }
        redirect('/dashboard.php?e=bookings&b=' . $id);
    }

    /* Les factures: ajouter, changer l'état, supprimer. */
    $actF = (string)($_POST['fact'] ?? '');
    if ($actF !== '' && $id > 0) {
        if ($actF === 'ajouter') {
            $d = static fn(string $k): ?string
                => trim((string)($_POST[$k] ?? '')) !== '' ? (string)$_POST[$k] : null;
            DB::insert('invoice', [
                'booking_id'    => $id,
                'numero'        => $d('numero'),
                'type'          => (string)($_POST['type'] ?? 'totale'),
                'destinataire'  => $d('destinataire'),
                'montant'       => (float)str_replace(',', '.', (string)($_POST['montant'] ?? '0')),
                'devise'        => (string)($_POST['devise'] ?? 'CHF'),
                'date_emission' => $d('date_emission'),
                'date_echeance' => $d('date_echeance'),
                /* Une facture qui porte déjà une date d'émission n'est plus un
                   brouillon: la saisir en brouillon obligerait à un second
                   clic pour dire ce que la date dit déjà. */
                'statut'        => $d('date_emission') ? 'envoyee' : 'brouillon',
            ]);
            dash_flash('Facture notée.');

        } elseif ($actF === 'statut') {
            $st = (string)($_POST['st'] ?? '');
            if (in_array($st, ['brouillon','envoyee','payee','annulee'], true)) {
                $maj = ['statut' => $st];
                /* Passer à « payée » sans date de paiement laisserait un trou
                   qu'on ne saurait plus combler trois mois après. */
                if ($st === 'payee')  $maj['date_paiement'] = date('Y-m-d');
                if ($st === 'envoyee') $maj['date_emission'] = date('Y-m-d');
                DB::update('invoice', $maj, 'id = ? AND booking_id = ?',
                           [(int)($_POST['ligne'] ?? 0), $id]);
                dash_flash('Facture mise à jour.');
            }

        } elseif ($actF === 'supprimer') {
            DB::delete('invoice', 'id = ? AND booking_id = ?',
                       [(int)($_POST['ligne'] ?? 0), $id]);
            dash_flash('Facture supprimée.');
        }
        redirect('/dashboard.php?e=bookings&b=' . $id . '&o=factures');
    }

    /* L'advancing: la liste, les états, et le lien remis au lieu. */
    $actA = (string)($_POST['adv'] ?? '');
    if ($actA !== '' && $id > 0) {
        if ($actA === 'ajouter' && trim((string)($_POST['libelle'] ?? '')) !== '') {
            Advancing::ajouter($id, $_POST);
            dash_flash('Élément ajouté.');

        } elseif ($actA === 'etat') {
            /* Valider est un geste d'ici et jamais du portail: Advancing::etat()
               est la seule voie vers « accepte », et le portail ne l'appelle
               pas. Recevoir n'est pas valider. */
            Advancing::etat((int)($_POST['champ'] ?? 0), $id, (string)($_POST['etat'] ?? ''));
            dash_flash('État changé.');

        } elseif ($actA === 'supprimer') {
            Advancing::supprimer((int)($_POST['champ'] ?? 0), $id);
            dash_flash('Élément supprimé.');

        } elseif ($actA === 'ouvrir') {
            $l = Advancing::ouvrirLien($id, (string)($_POST['destinataire'] ?? ''));
            dash_flash('Lien ouvert. Il expire dans ' . Advancing::JOURS . ' jours.');

        } elseif ($actA === 'revoquer') {
            Advancing::revoquer($id);
            dash_flash('Lien révoqué. Le lieu ne peut plus répondre.');
        }
        redirect('/dashboard.php?e=bookings&b=' . $id . '&o=advancing');
    }

    /* Le voyage: ajouter, supprimer. Même parti que les lignes de deal. */
    $actV = (string)($_POST['voyage'] ?? '');
    if ($actV !== '' && $id > 0) {
        if ($actV === 'ajouter') {
            /* Une date vide reste NULL et non « 0000-00-00 »: le tri met les
               lignes sans date à la fin, ce qui suppose de vrais NULL. */
            $dt = static fn(string $k): ?string
                => trim((string)($_POST[$k] ?? '')) !== ''
                   ? date('Y-m-d H:i:s', strtotime((string)$_POST[$k])) : null;
            $m = trim((string)($_POST['montant'] ?? ''));
            DB::insert('trip_item', [
                'booking_id' => $id,
                'type'       => (string)($_POST['type'] ?? 'vol'),
                'qui'        => trim((string)($_POST['qui'] ?? '')) ?: null,
                'libelle'    => trim((string)($_POST['libelle'] ?? '')) ?: null,
                'depart'     => trim((string)($_POST['depart'] ?? '')) ?: null,
                'arrivee'    => trim((string)($_POST['arrivee'] ?? '')) ?: null,
                'date_debut' => $dt('date_debut'),
                'date_fin'   => $dt('date_fin'),
                'reference'  => trim((string)($_POST['reference'] ?? '')) ?: null,
                'montant'    => $m !== '' ? (float)str_replace(',', '.', $m) : null,
                'devise'     => (string)($_POST['devise'] ?? 'CHF'),
                'charge'     => (string)($_POST['charge'] ?? 'incluse'),
            ]);
            dash_flash('Trajet ajouté.');
        } elseif ($actV === 'supprimer') {
            /* La ligne doit appartenir à CE booking: sans cette condition, un
               id changé dans le formulaire supprimerait le trajet d'une autre
               date. Même garde que pour les contrats. */
            DB::delete('trip_item', 'id = ? AND booking_id = ?',
                       [(int)($_POST['ligne'] ?? 0), $id]);
            dash_flash('Trajet supprimé.');
        }
        redirect('/dashboard.php?e=bookings&b=' . $id . '&o=voyage');
    }

    /* Les contrats: déposer, envoyer à la signature, supprimer. Comme les
       lignes de deal, ils n'ont rien à voir avec le formulaire du booking, et
       on repart aussitôt. */
    $actC = (string)($_POST['contrat'] ?? '');
    if ($actC !== '' && $id > 0) {
        $retour = '/dashboard.php?e=bookings&b=' . $id . '&o=contrats';
        try {
            if ($actC === 'deposer') {
                Contracts::deposer($id, (string)($_POST['type'] ?? 'cession'),
                                   (string)($_POST['titre'] ?? ''), $_FILES['pdf'] ?? []);
                dash_flash('Contrat déposé.');

            } elseif ($actC === 'envoyer') {
                $c = Contracts::un((int)($_POST['ligne'] ?? 0));
                if (!$c || (int)$c['booking_id'] !== $id) throw new RuntimeException('Contrat introuvable.');
                Contracts::envoyer((int)$c['id'], (string)($_POST['email'] ?? ''),
                                   (string)($_POST['mobile'] ?? ''), (string)($_POST['nom'] ?? ''));
                dash_flash('Envoyé à la signature.');

            } elseif ($actC === 'supprimer') {
                $c = Contracts::un((int)($_POST['ligne'] ?? 0));
                if (!$c || (int)$c['booking_id'] !== $id) throw new RuntimeException('Contrat introuvable.');
                Contracts::supprimer((int)$c['id']);
                dash_flash('Contrat supprimé.');
            }
        } catch (Throwable $ex) {
            /* Le message de l'exception est écrit pour être lu par la personne:
               « seuls les PDF sont acceptés », « il faut une adresse valide ».
               Le masquer derrière un « erreur » obligerait à ouvrir le journal. */
            dash_flash($ex->getMessage(), 'err');
        }
        redirect($retour);
    }

    /* Les lignes de deal se saisissent depuis l'onglet Deal et n'ont rien à voir
       avec le formulaire du booking: on les traite ici et on repart. */
    $actD = (string)($_POST['deal'] ?? '');
    if ($actD !== '' && $id > 0) {
        if ($actD === 'ajouter') {
            $q  = (float)str_replace(',', '.', (string)($_POST['quantite'] ?? '1')) ?: 1;
            $pu = trim((string)($_POST['prix_unitaire'] ?? ''));
            $pu = $pu === '' ? null : (float)str_replace([',', ' ', "'"], ['.', '', ''], $pu);
            $mt = trim((string)($_POST['montant'] ?? ''));
            $mt = $mt === '' ? ($pu !== null ? round($q * $pu, 2) : null)
                             : (float)str_replace([',', ' ', "'"], ['.', '', ''], $mt);
            DB::pdo()->prepare(
              'INSERT INTO deal_item (booking_id,type,libelle,charge,quantite,prix_unitaire,montant,devise,ordre)
               VALUES (?,?,?,?,?,?,?,?,(SELECT COALESCE(MAX(o.ordre),0)+10 FROM deal_item o WHERE o.booking_id=?))')
              ->execute([$id,
                (string)($_POST['type'] ?? 'autre'), trim((string)($_POST['libelle'] ?? '')) ?: null,
                (string)($_POST['charge'] ?? 'incluse'), $q, $pu, $mt,
                (string)($_POST['devise'] ?? 'CHF'), $id]);
            dash_flash('Ligne ajoutée.');
        }
        if ($actD === 'supprimer') {
            DB::pdo()->prepare('DELETE FROM deal_item WHERE id = ? AND booking_id = ?')
                     ->execute([(int)($_POST['ligne'] ?? 0), $id]);
            dash_flash('Ligne supprimée.');
        }
        redirect('/dashboard.php?e=bookings&b=' . $id . '&o=deal');
    }

    foreach ($CHAMPS as $c) $saisi[$c] = trim((string)($_POST[$c] ?? ''));

    /* SUPPRESSION LOGIQUE, jamais un DELETE. Une date effacée par erreur se
       retrouve, et c'est déjà la règle du dashboard actuel. */
    if (($_POST['action'] ?? '') === 'supprimer' && $id > 0) {
        DB::pdo()->prepare('UPDATE booking SET supprime_le = NOW() WHERE id = ?')->execute([$id]);
        dash_flash('Booking supprimé. Il reste en base et peut être rétabli.');
        redirect('/dashboard.php?e=bookings');
    }

    // Ce qui est vraiment obligatoire, et rien de plus: sans lieu ni date, la
    // ligne ne veut rien dire et ne peut pas être retrouvée.
    if ($saisi['venue'] === '')      $err['venue'] = 'Le lieu est nécessaire pour retrouver la date.';
    if ($saisi['date_debut'] === '') $err['date_debut'] = 'Sans date, la ligne ne peut ni se trier ni se compter.';

    foreach (['date_debut', 'date_fin'] as $d) {
        if ($saisi[$d] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $saisi[$d])) {
            $err[$d] = 'Format attendu: AAAA-MM-JJ.';
        }
    }
    if ($saisi['date_debut'] !== '' && $saisi['date_fin'] !== ''
        && !isset($err['date_debut']) && !isset($err['date_fin'])
        && $saisi['date_fin'] < $saisi['date_debut']) {
        $err['date_fin'] = 'La fin est avant le début.';
    }
    foreach (['prix_cession', 'prix_vente'] as $p) {
        if ($saisi[$p] === '') continue;
        $saisi[$p] = str_replace([',', ' ', "\u{202f}"], ['.', '', ''], $saisi[$p]);
        if (!is_numeric($saisi[$p])) $err[$p] = 'Un montant, sans texte autour.';
    }
    if (!isset($STATUTS[$saisi['statut']])) $saisi['statut'] = 'pending';
    if ($saisi['devise'] === '') $saisi['devise'] = 'CHF';
    $saisi['representations'] = max(1, (int)($saisi['representations'] ?: 1));

    if (!$err) {
        $vals = [];
        foreach ($CHAMPS as $c) $vals[] = $saisi[$c] === '' ? null : $saisi[$c];
        if ($id > 0) {
            $set = implode(',', array_map(fn($c) => "$c=?", $CHAMPS));
            $vals[] = $id;
            DB::pdo()->prepare("UPDATE booking SET $set WHERE id = ?")->execute($vals);
            dash_flash('Booking enregistré.');
        } else {
            $q = implode(',', array_fill(0, count($CHAMPS), '?'));
            DB::pdo()->prepare('INSERT INTO booking (' . implode(',', $CHAMPS) . ") VALUES ($q)")
                     ->execute($vals);
            $id = (int)DB::pdo()->lastInsertId();
            dash_flash('Booking créé.');
        }
        /* Rediriger après un enregistrement réussi: sans cela, un
           rafraîchissement renvoie le POST et crée un doublon. */
        redirect('/dashboard.php?e=bookings&b=' . $id);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// LE FORMULAIRE
// ═══════════════════════════════════════════════════════════════════════════

if (isset($_GET['mod']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = $id > 0 ? DB::one('SELECT * FROM booking WHERE id = ? AND supprime_le IS NULL', [$id]) : [];
    if ($id > 0 && !$b) { dash_haut('bookings'); echo '<p class="vide">Cet événement n\'existe pas.</p>'; dash_bas(); return; }

    // Ce qui a été saisi prime sur ce qui est en base: un refus ne doit rien effacer.
    $v = fn(string $c) => $saisi[$c] ?? ($b[$c] ?? '');

    dash_haut('bookings', $id > 0 ? 'modifier' : 'nouveau');
    dash_form_style();
    if ($err) echo '<div class="flash err">Rien n\'a été enregistré: '
                 . count($err) . ' champ(s) à corriger. Ce que vous aviez saisi est conservé.</div>';
    ?>
    <?php
    /* LES MÊMES FLÈCHES ICI, ET C'EST ICI QU'ELLES SERVENT. [Anna, 21.08.2026]
       Je les avais mises sur l'aperçu seulement, en écartant le formulaire au
       motif qu'une flèche à côté de champs non enregistrés fait perdre la
       saisie. Anna: « nao vejo as setas » — elle corrige dans le formulaire,
       évidemment: c'est le seul écran où l'on change quelque chose.

       Le risque était réel, il se répare au lieu de s'éviter: les flèches
       demandent confirmation si un champ a bougé, et se taisent sinon. Le
       garde est en bas de page, il compare l'état du formulaire à son état
       d'origine. Sans JavaScript les flèches marchent quand même — on perd
       l'avertissement, pas la navigation. */
    $vz  = $id > 0 ? bookings_voisins($id) : ['prec'=>null,'suiv'=>null,'rang'=>0,'total'=>0];
    $ctx = bookings_contexte();
    $lienMod = fn(?int $n) => '/dashboard.php?e=bookings&amp;b=' . (int)$n . '&amp;mod=1' . $ctx;
    ?>
    <div class="fil"><a href="/dashboard.php?e=bookings<?= $id > 0 ? '&amp;b=' . $id : '' ?><?= $ctx ?>">← retour</a>
      <?php if ($vz['prec'] !== null): ?>
        <a class="pas" href="<?= $lienMod($vz['prec']) ?>">← précédent</a>
      <?php elseif ($id > 0): ?><span class="pas mort">← précédent</span><?php endif; ?>
      <?php if ($vz['rang']): ?><span class="rang"><?= $vz['rang'] ?> / <?= $vz['total'] ?></span><?php endif; ?>
      <?php if ($vz['suiv'] !== null): ?>
        <a class="pas" href="<?= $lienMod($vz['suiv']) ?>">suivant →</a>
      <?php elseif ($id > 0): ?><span class="pas mort">suivant →</span><?php endif; ?>
    </div>
    <form class="saisie" method="post"
          action="/dashboard.php?e=bookings<?= $id > 0 ? '&amp;b=' . $id : '' ?>&amp;mod=1">
      <?= Auth::csrfField() ?>
      <div class="grille">
        <div class="titre-bloc">Quoi, qui, où</div>
        <?php
        ch('projet',  'Projet',  $v('projet'),  $err);
        ch('artiste', 'Artiste', $v('artiste'), $err);
        ch('venue',   'Lieu',    $v('venue'),   $err, ['requis' => true]);
        ch('ville',   'Ville',   $v('ville'),   $err);
        ch('pays',    'Pays',    $v('pays'),    $err);
        ch('client',  'Client',  $v('client'),  $err, ['aide' => 'Qui paie, si ce n\'est pas le lieu']);
        ch('venue_url', 'Site du lieu', $v('venue_url'), $err, ['large' => true]);

        echo '<div class="titre-bloc">Quand</div>';
        ch('date_debut', 'Début', $v('date_debut'), $err, ['type' => 'date', 'requis' => true]);
        ch('date_fin',   'Fin',   $v('date_fin'),   $err, ['type' => 'date',
            'aide' => 'Seulement si la série tient sur plusieurs jours']);
        ch('heure',      'Heure', substr((string)$v('heure'), 0, 5), $err, ['type' => 'time']);
        ch('representations', 'Représentations', $v('representations') ?: 1, $err,
           ['type' => 'number', 'aide' => 'Deux le même jour valent 1,5 jour de salaire, pas 2']);
        ch('date_texte', 'Date affichée', $v('date_texte'), $err, ['large' => true,
            'aide' => 'Ce que lit le public. « du 8 au 13 février » ne se dérive pas de deux dates',
            'placeholder' => '12, 13, 14 décembre 2026']);

        echo '<div class="titre-bloc">Combien</div>';
        /* La commission, à côté des prix: on la négocie dans le même souffle.
           Le taux propose, le montant fait foi — un prix de cession qui change
           ne doit pas déplacer une commission déjà facturée. [16.08.2026] */
        ch('frais_booking', 'Frais de booking (montant)', $v('frais_booking'), $err,
           ['aide' => 'La commission sur cette date. C\'est ce montant qui est facturé.']);
        ch('frais_booking_taux', 'Frais de booking (%)', $v('frais_booking_taux'), $err,
           ['aide' => 'Le pourcentage, pour relire d\'où vient le montant. Il ne le recalcule pas.']);
        ch('prix_cession', 'Prix de cession', $v('prix_cession'), $err,
           ['aide' => 'Ce que le lieu paie']);
        ch('prix_vente',   'Prix de vente',   $v('prix_vente'), $err,
           ['aide' => 'Ce qui est annoncé ou négocié']);
        ch('devise', 'Devise', $v('devise') ?: 'CHF', $err,
           ['type' => 'select', 'choix' => ['CHF' => 'CHF', 'EUR' => 'EUR']]);
        ch('statut', 'Statut', $v('statut') ?: 'pending', $err,
           ['type' => 'select', 'choix' => $STATUTS]);

        echo '<div class="titre-bloc">Notes</div>';
        ch('notes_artiste',  'Notes artiste',  $v('notes_artiste'),  $err,
           ['type' => 'textarea', 'large' => true, 'aide' => 'L\'artiste les voit']);
        ch('notes_internes', 'Notes internes', $v('notes_internes'), $err,
           ['type' => 'textarea', 'large' => true, 'aide' => 'L\'équipe seulement, jamais partagées']);
        ?>
      </div>
      <div class="actions">
        <button type="submit"><?= $id > 0 ? 'Enregistrer' : 'Créer' ?></button>
        <a class="sec2" href="/dashboard.php?e=bookings<?= $id > 0 ? '&amp;b=' . $id : '' ?>">annuler</a>
        <?php if ($id > 0): ?>
          <a class="sup" href="#"
             onclick="if(confirm('Supprimer cet événement ? Il restera en base et pourra être rétabli.')){
                        var f=document.createElement('form');f.method='post';
                        f.action='/dashboard.php?e=bookings&b=<?= $id ?>&mod=1';
                        f.innerHTML='<?= addslashes(Auth::csrfField()) ?><input name=action value=supprimer>';
                        document.body.appendChild(f);f.submit();}return false;">supprimer</a>
        <?php endif; ?>
      </div>
    </form>
    <style>.fil { padding:12px 26px 0; font-size:13px; display:flex; gap:16px; align-items:baseline; }
           .fil a { color:var(--doux); text-decoration:none; }
           .fil .pas { color:var(--encre); font-weight:600; }
           .fil .pas.mort { color:var(--doux); opacity:.35; }
           .fil .rang { color:var(--doux); font-variant-numeric:tabular-nums; }</style>
    <script>
    /* Prévenir avant de quitter une saisie en cours, et seulement alors: une
       confirmation qui se déclenche à chaque fois n'est plus lue au bout de
       trois clics. */
    (function () {
      var f = document.querySelector('form.saisie');
      if (!f) return;
      var depart = new FormData(f), sale = false;
      f.addEventListener('input', function () { sale = true; });
      document.querySelectorAll('.fil a.pas, .fil a').forEach(function (a) {
        a.addEventListener('click', function (e) {
          if (sale && !confirm('Des champs ont été modifiés et ne sont pas enregistrés. Quitter quand même ?')) e.preventDefault();
        });
      });
    })();
    </script>
    <?php
    dash_bas();
    return;
}

// ═══════════════════════════════════════════════════════════════════════════
// LA FICHE
// ═══════════════════════════════════════════════════════════════════════════

if ($id > 0) {
    $b = DB::one('SELECT * FROM booking WHERE id = ? AND supprime_le IS NULL', [$id]);
    if (!$b) { dash_haut('bookings'); echo '<p class="vide">Cet événement n\'existe pas.</p>'; dash_bas(); return; }

    $ong = (string)($_GET['o'] ?? 'apercu');
    if (!isset(ONGLETS[$ong])) $ong = 'apercu';

    /* L'état des signatures se lit en ouvrant l'onglet, et pas sur un bouton.
       Même raison que dans l'espace collaborateur: personne ne pense à cliquer
       sur « rafraîchir », et un contrat déjà signé qui s'affiche « en attente »
       fait relancer quelqu'un pour rien.

       L'appel ne concerne que les contrats encore en attente, et il est enfermé
       dans un try: Skribble injoignable ne doit pas emporter la page. Le
       journal du Skribble garde la trace. */
    if ($ong === 'contrats') {
        try { Contracts::rafraichirBooking($id); }
        catch (Throwable $ex) { Skribble::journal('CONTRATS booking ' . $id . ' | ' . $ex->getMessage()); }
    }

    $titre = trim(($b['projet'] ?? '') . ' · ' . ($b['venue'] ?? ''));
    dash_haut('bookings', e($b['date_texte'] ?: (string)$b['date_debut']) . ' · ' . e($b['ville'] ?? ''));
    ?>
    <?php
    /* PRÉCÉDENT ET SUIVANT, POUR NE PAS REPASSER PAR LA LISTE. [Anna, 21.08.2026]
       « assim não temos que voltar a cada vez para seguirmos os eventos e
       corrigir ou mudar coisas mais rapidamente ». Quatre-vingt-six fiches à
       relire une par une, c'est quatre-vingt-cinq allers-retours évités.

       Les flèches gardent l'onglet ouvert et le filtre de la liste: partir de
       « confirmés 2027 » et se retrouver dans les annulés de 2024 vaudrait
       mieux que rien, mais à peine. Le rang « 12 / 86 » est là pour qu'on
       sache où l'on en est sans compter.

       Elles sont sur la fiche et pas sur le formulaire de modification: une
       flèche à côté de champs saisis et non enregistrés est un piège. */
    $vz  = bookings_voisins($id);
    $ctx = bookings_contexte();
    $lien = fn(?int $n) => '/dashboard.php?e=bookings&amp;b=' . (int)$n
          . '&amp;o=' . rawurlencode($ong) . $ctx;
    ?>
    <div class="fil"><a href="/dashboard.php?e=bookings<?= $ctx ?>">← tous les événements</a>
      <?php if ($vz['prec'] !== null): ?>
        <a class="pas" href="<?= $lien($vz['prec']) ?>" title="Événement précédent">← précédent</a>
      <?php else: ?><span class="pas mort">← précédent</span><?php endif; ?>
      <?php if ($vz['rang']): ?><span class="rang"><?= $vz['rang'] ?> / <?= $vz['total'] ?></span><?php endif; ?>
      <?php if ($vz['suiv'] !== null): ?>
        <a class="pas" href="<?= $lien($vz['suiv']) ?>" title="Événement suivant">suivant →</a>
      <?php else: ?><span class="pas mort">suivant →</span><?php endif; ?>
      <a class="mod" href="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;mod=1">modifier</a></div>
    <?php dash_flash_html(); ?>

    <div class="onglets">
      <?php foreach (ONGLETS as $c => $lib): ?>
        <a href="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=<?= $c ?>"
           class="<?= $c === $ong ? 'ici' : '' ?>"><?= e($lib) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="zone">
    <?php if ($ong === 'apercu'): ?>
      <h2 class="gros"><?= e($titre) ?></h2>

      <?php
      /* LA CARTE, EN HAUT DE L'APERÇU. [Anna, 21.08.2026] « on pourrait mettre
         une visualisation avec Google Maps », « laisser avec une visibilité
         comme dans le print ».

         PAS DE CLEF GOOGLE, ET C'EST DÉLIBÉRÉ. L'API Maps en exige une,
         facturable, à créer, à poser dans les réglages et à surveiller.
         OpenStreetMap montre la même rue sans clef ni compte. Le lien vers
         Google reste sous la carte: qui veut l'itinéraire l'a en un clic, et
         c'est le seul usage où Google est vraiment meilleur.

         LA RECHERCHE N'A LIEU QU'UNE FOIS. Les coordonnées sont écrites dans
         la date à la première ouverture; ensuite l'affichage n'appelle plus
         personne. Une salle introuvable est marquée comme cherchée, sinon on
         retenterait à chaque ouverture — le service nous bloquerait et la
         fiche traînerait pour rien.

         L'ADRESSE TROUVÉE EST ÉCRITE SOUS LA CARTE, et ce n'est pas décoratif:
         « Ecolint, Genève » peut tomber sur le bon campus ou sur un homonyme.
         En la montrant, une erreur de géocodage se voit au lieu de se croire. */
      $geo = Geo::pourBooking($b);
      ?>
      <?php if ($geo['lat'] !== null): ?>
      <?php $mo = Geo::mosaique($geo['lat'], $geo['lon']); ?>
      <div class="carte">
        <div class="carte-vue" style="height:<?= $mo['h'] ?>px">
          <?php foreach ($mo['tuiles'] as $t): ?>
            <img src="<?= e($t['src']) ?>" alt="" width="256" height="256" loading="lazy"
                 referrerpolicy="no-referrer"
                 style="left:<?= $t['x'] ?>px;top:<?= $t['y'] ?>px">
          <?php endforeach; ?>
          <span class="carte-pin" style="left:<?= $mo['mx'] ?>px;top:<?= $mo['my'] ?>px"
                title="<?= e((string)($b['venue'] ?? '')) ?>"></span>
          <a class="carte-clic" href="<?= e(Geo::urlOsm($geo['lat'], $geo['lon'])) ?>"
             target="_blank" rel="noopener"
             title="Ouvrir la carte complète sur OpenStreetMap"><span>agrandir ↗</span></a>
        </div>
        <div class="carte-tete">
          <strong><?= e((string)($b['venue'] ?? '')) ?></strong>
          <span><?= e(trim(((string)$b['ville']) . (($b['ville'] && $b['pays']) ? ', ' : '') . (string)$b['pays'])) ?></span>
        </div>
        <div class="carte-pied">
          <span class="tr" title="<?= e((string)($geo['libelle'] ?? '')) ?>"><?=
            e((string)($geo['libelle'] ?? '')) ?></span>
          <a href="<?= e(Geo::urlGoogle($b, $geo['lat'], $geo['lon'])) ?>"
             target="_blank" rel="noopener">ouvrir dans Google Maps ↗</a>
        </div>
      </div>
      <?php elseif (($b['venue'] ?? '') || ($b['ville'] ?? '')): ?>
        <p class="carte-non">Adresse introuvable pour « <?=
          e(trim(((string)$b['venue']) . ' ' . ((string)$b['ville']))) ?> ».
          <a href="<?= e(Geo::urlGoogle($b)) ?>" target="_blank" rel="noopener">chercher
          dans Google Maps ↗</a></p>
      <?php endif; ?>

      <div class="fiche">
        <?php
        $st = ['option' => 'option', 'confirmed' => 'confirmé', 'canceled' => 'annulé', 'pending' => 'en attente'];
        $l = function (string $k, $v, string $note = '') {
            if ($v === null || $v === '') return;
            printf('<div class="l"><span class="k">%s</span><span class="v">%s%s</span></div>',
                   e($k), e((string)$v), $note ? '<span class="n">' . e($note) . '</span>' : '');
        };
        $l('Statut', $st[$b['statut']] ?? $b['statut']);
        $l('Date', $b['date_texte'] ?: $b['date_debut']);
        if ($b['heure']) $l('Heure', substr((string)$b['heure'], 0, 5));
        $l('Représentations', $b['representations']);
        $l('Projet', $b['projet']);
        $l('Artiste', $b['artiste']);
        $l('Lieu', $b['venue']);
        $l('Ville', trim(($b['ville'] ?? '') . ' ' . ($b['pays'] ? '· ' . $b['pays'] : '')));
        $l('Client', $b['client']);
        if ($b['prix_cession'] !== null)
            $l('Prix de cession', number_format((float)$b['prix_cession'], 2, ',', ' ') . ' ' . $b['devise']);
        if ($b['prix_vente'] !== null)
            $l('Prix de vente', number_format((float)$b['prix_vente'], 2, ',', ' ') . ' ' . $b['devise']);
        $l('Provenance', $b['source'] . ' · ' . $b['source_ref'],
           $b['source'] === 'events' ? 'agenda du site' : 'lv-tour du dashboard');
        ?>
      </div>

      <?php /* Les deux natures de notes, et la distinction est le point:
               l'une part avec l'artiste, l'autre jamais. Une seule colonne
               obligerait à se relire avant chaque partage. */ ?>
      <?php $peutNote = dash_droit('bookings', dash_role()) === 'ecrit'; ?>
      <div class="notes">
        <?php foreach ([
              ['artiste',  'notes_artiste',  'Notes artiste',  "visibles par l'artiste", ''],
              ['internes', 'notes_internes', 'Notes internes', "l'équipe seulement",     ' int'],
            ] as [$cle, $col, $titre, $sous, $cls]): ?>
          <div class="bloc<?= $cls ?>">
            <h3><?= e($titre) ?> <span class="n"><?= e($sous) ?></span></h3>
            <?php if ($peutNote): ?>
              <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>" class="fnote">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="bnote" value="<?= e($cle) ?>">
                <textarea name="texte" rows="4"
                  placeholder="<?= $cle === 'artiste'
                    ? 'Ce que l’artiste doit savoir: horaires, accès, contacts sur place…'
                    : 'Ce qui ne sort pas de l’équipe: négociation, doutes, relances…' ?>"><?=
                  e((string)$b[$col]) ?></textarea>
                <div class="fnote-act"><button type="submit">enregistrer</button></div>
              </form>
            <?php else: ?>
              <p><?= $b[$col] ? nl2br(e($b[$col])) : '<span class="n">rien</span>' ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <?php /* LES FICHIERS DE LA DATE. [16.08.2026]

               Ils vivent ici, sous les notes, parce qu'ils posent le même
               problème et se règlent pareil: le plan de feu se partage avec
               l'artiste, la grille de négociation non. La colonne `partage`
               reprend donc la distinction des deux blocs ci-dessus.

               LE GLISSER-DÉPOSER N'EST QU'UN RACCOURCI. Le champ de fichier
               reste là, visible, et fonctionne sans une ligne de JavaScript.
               Une zone de dépôt qui serait le seul moyen exclurait qui navigue
               au clavier, et tomberait en panne le jour où le script casse. */ ?>
      <?php $fichiers = BookingFiles::liste($id);
            $peutEcrire = dash_droit('bookings', dash_role()) === 'ecrit'; ?>

      <div class="fich">
        <h3>Fichiers <span class="n"><?= count($fichiers) ?: 'aucun' ?><?= count($fichiers) ? ' sur cette date' : '' ?></span></h3>

        <?php if ($fichiers): ?>
          <ul class="lf">
          <?php foreach ($fichiers as $f): $fid = (int)$f['id']; ?>
            <li>
              <a href="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;bf=<?= $fid ?>"><?= e((string)$f['titre']) ?></a>
              <span class="n"><?= e(BookingFiles::poids((int)$f['taille'])) ?>
                · <?= e(date('d.m.Y', strtotime((string)$f['cree_a']))) ?></span>
              <?php if ($peutEcrire): ?>
                <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>" class="inline">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="bfic" value="partage">
                  <input type="hidden" name="ligne" value="<?= $fid ?>">
                  <input type="hidden" name="p" value="<?= $f['partage'] === 'artiste' ? 'interne' : 'artiste' ?>">
                  <button type="submit" class="pg pg-<?= e($f['partage']) ?>"><?= $f['partage'] === 'artiste' ? 'partagé avec l\'artiste' : 'interne' ?></button>
                </form>
                <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>" class="inline"
                      onsubmit="return confirm('Supprimer ce fichier ?')">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="bfic" value="supprimer">
                  <input type="hidden" name="ligne" value="<?= $fid ?>">
                  <button type="submit" class="x">×</button>
                </form>
              <?php else: ?>
                <span class="pg pg-<?= e($f['partage']) ?>"><?= $f['partage'] === 'artiste' ? 'partagé' : 'interne' ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php if ($peutEcrire): ?>
          <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>"
                enctype="multipart/form-data" id="fdrop" class="drop">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="bfic" value="deposer">
            <p class="dz">Glissez un fichier ici, ou choisissez-le :</p>
            <input type="file" name="fichier" id="finput">
            <select name="partage">
              <option value="interne">interne</option>
              <option value="artiste">partagé avec l'artiste</option>
            </select>
            <button type="submit">déposer</button>
            <p class="n">Fiches techniques, plans, itinéraires, photos. 25 Mo au maximum.</p>
          </form>
          <script>
          /* Le glisser-déposer se contente de remplir le champ existant et
             d'envoyer le formulaire: aucun chemin de code séparé, donc rien à
             maintenir en double, et la même validation côté serveur. */
          (function(){
            var f=document.getElementById('fdrop'), i=document.getElementById('finput');
            if(!f||!i||!window.DataTransfer) return;
            ['dragenter','dragover'].forEach(function(n){
              f.addEventListener(n,function(e){e.preventDefault();f.classList.add('sur');});});
            ['dragleave','drop'].forEach(function(n){
              f.addEventListener(n,function(e){e.preventDefault();f.classList.remove('sur');});});
            f.addEventListener('drop',function(e){
              if(!e.dataTransfer||!e.dataTransfer.files.length) return;
              i.files=e.dataTransfer.files;
              f.submit();
            });
          })();
          </script>
        <?php endif; ?>
      </div>

      <style>
      .fich{margin-top:22px}
      .fich h3{margin-bottom:8px}
      .lf{list-style:none;margin:0 0 16px;padding:0}
      .lf li{display:flex;align-items:center;gap:10px;flex-wrap:wrap;
        padding:7px 0;border-bottom:1px solid var(--rule,var(--trait))}
      .lf .n{font-size:12.5px;color:var(--doux)}
      .pg{font-size:11.5px;padding:2px 8px;border-radius:3px;border:1px solid var(--trait);
        background:none;color:var(--doux);cursor:pointer;font:inherit;font-size:11.5px}
      .pg-artiste{border-color:#7bb33a;color:#5c8f28}
      button.pg:hover{border-color:var(--encre);color:var(--encre)}
      .drop{border:1px dashed var(--trait);border-radius:6px;padding:16px 18px;
        display:flex;gap:10px;align-items:center;flex-wrap:wrap;transition:border-color .12s}
      .drop.sur{border-color:var(--encre);border-style:solid;background:var(--fond2)}
      .drop .dz{margin:0;font-size:13.5px;color:var(--doux)}
      .drop .n{flex-basis:100%;margin:0;font-size:12.5px;color:var(--doux)}
      </style>

    <?php /* La carte flotte à droite: sans ce trait, elle déborderait sur
         l'onglet suivant et sur le pied de page. */ ?>
    <div class="fin-flot" style="clear:both"></div>

    <?php elseif ($ong === 'deal'): ?>
      <?php
      $lignes = DB::all('SELECT * FROM deal_item WHERE booking_id = ? ORDER BY ordre, id', [$id]);
      $TY = ['cachet'=>'cachet','frais_booking'=>'frais de booking','voyage'=>'voyage',
             'hebergement'=>'hébergement','per_diem'=>'per diem','droits'=>'droits',
             'materiel'=>'matériel','catering'=>'catering','marge'=>'marge','autre'=>'autre'];
      $CG = ['incluse'=>'incluse dans la cession','lieu'=>'à la charge du lieu','nous'=>'à notre charge'];
      $tot = ['incluse'=>0.0, 'lieu'=>0.0, 'nous'=>0.0];
      foreach ($lignes as $l) $tot[$l['charge']] += (float)$l['montant'];
      ?>
      <?php if ($lignes): ?>
      <div class="tw"><table>
        <thead><tr><th>Nature</th><th>Libellé</th><th>Charge</th><th class="d">Qté</th>
          <th class="d">Prix</th><th class="d">Montant</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lignes as $l): ?>
          <tr class="c-<?= e($l['charge']) ?>">
            <td><?= e($TY[$l['type']] ?? $l['type']) ?></td>
            <td class="sec"><?= e($l['libelle'] ?? '') ?></td>
            <td class="sec"><?= e($CG[$l['charge']]) ?></td>
            <td class="d sec"><?= rtrim(rtrim(number_format((float)$l['quantite'],2,',',' '),'0'),',') ?></td>
            <td class="d sec"><?= $l['prix_unitaire'] !== null
                ? number_format((float)$l['prix_unitaire'],2,',',' ') : '' ?></td>
            <td class="d"><?= $l['montant'] !== null
                ? number_format((float)$l['montant'],2,',',' ') . ' ' . e($l['devise']) : '' ?></td>
            <td class="d">
              <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=deal" class="inline"
                    onsubmit="return confirm('Supprimer cette ligne ?')">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="deal" value="supprimer">
                <input type="hidden" name="ligne" value="<?= (int)$l['id'] ?>">
                <button type="submit" class="x">×</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <?php foreach ($CG as $k => $lib): if (!$tot[$k]) continue; ?>
          <tr class="tot c-<?= $k ?>"><td colspan="5"><?= e(ucfirst($lib)) ?></td>
            <td class="d"><strong><?= number_format($tot[$k],2,',',' ') ?></strong></td><td></td></tr>
          <?php endforeach; ?>
        </tfoot>
      </table></div>

      <?php /* Le rapprochement avec le prix annoncé. Un écart n'est pas une
               erreur en soi: il dit seulement que les deux ne se sont pas encore
               parlé, et c'est exactement ce qu'on veut voir. */ ?>
      <?php if ($b['prix_cession'] !== null): ?>
        <?php $ecart = (float)$b['prix_cession'] - $tot['incluse']; ?>
        <div class="rap <?= abs($ecart) > 0.5 ? 'ecart' : 'ok' ?>">
          Prix de cession annoncé <strong><?= number_format((float)$b['prix_cession'],2,',',' ') ?>
          <?= e($b['devise']) ?></strong>, somme des lignes incluses
          <strong><?= number_format($tot['incluse'],2,',',' ') ?></strong>.
          <?php if (abs($ecart) > 0.5): ?>
            Écart de <strong><?= number_format($ecart,2,',',' ') ?></strong>.
            Ce n'est pas forcément une erreur: les deux ne se sont pas encore parlé.
          <?php else: ?>Les deux concordent.<?php endif; ?>
        </div>
      <?php endif; ?>
      <?php else: ?>
        <p class="sec">Aucune ligne. Le prix de cet événement n'est pour l'instant qu'un
           nombre: ajouter les lignes dit ce qu'il contient.</p>
      <?php endif; ?>

      <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=deal" class="ajl">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="deal" value="ajouter">
        <select name="type"><?php foreach ($TY as $k=>$v): ?>
          <option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
        <input type="text" name="libelle" placeholder="Libellé">
        <select name="charge"><?php foreach ($CG as $k=>$v): ?>
          <option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
        <input type="text" name="quantite" value="1" size="3" title="Quantité">
        <input type="text" name="prix_unitaire" placeholder="Prix unitaire" size="10">
        <input type="text" name="montant" placeholder="ou montant" size="10">
        <select name="devise"><option>CHF</option><option>EUR</option></select>
        <button type="submit">ajouter</button>
      </form>
      <p class="sec pt">Laisser le montant vide le calcule depuis la quantité et le prix.
         Le remplir directement permet un forfait négocié, sans inventer un prix unitaire.</p>

      <style>
      td.d,th.d{text-align:right;white-space:nowrap}
      tr.c-lieu td,tr.c-nous td{opacity:.72}
      tr.tot td{border-top:2px solid var(--encre);background:var(--fond2)}
      tr.tot.c-lieu td,tr.tot.c-nous td{border-top-width:1px;background:transparent}
      button.x{background:none;color:var(--doux);padding:0 6px;font-size:16px;cursor:pointer}
      form.inline{display:inline}
      form.ajl{display:flex;gap:7px;flex-wrap:wrap;align-items:center;margin-top:18px;
        padding-top:16px;border-top:1px solid var(--trait)}
      form.ajl input,form.ajl select{padding:6px 9px;font-size:13.5px;font-family:inherit;
        border:1px solid var(--trait);border-radius:4px;background:var(--papier);color:var(--encre)}
      form.ajl input[name=libelle]{flex:1;min-width:140px}
      form.ajl button{padding:6px 15px;font-size:13px}
      .rap{margin-top:16px;padding:11px 15px;background:var(--fond2);font-size:13.5px;
        border-left:4px solid var(--jaune);max-width:76ch}
      .rap.ecart{border-left-color:var(--orange)}
      .pt{margin-top:8px;font-size:12.5px;max-width:70ch}
      </style>

    <?php elseif ($ong === 'factures'): ?>
      <?php
      /* FACTURES. [16.08.2026]

         CE QUE CET ONGLET FAIT: suivre. Quelles factures existent sur cette
         date, pour quel montant, parties quand, payées quand.

         CE QU'IL NE FAIT PAS: produire la facture, ni parler à bexio. Ce n'est
         pas un demi-travail, c'est la moitié qui n'est pas bloquée — le
         portage du client bexio est chiffré entre 12 h et 20 h pour le seul
         OAuth2. Et la question qui coûte cher n'est pas « comment j'émets »,
         cela se fait déjà dans bexio, mais « qu'est-ce qui est parti et
         qu'est-ce qui n'est pas rentré ». C'est précisément ce qui a manqué
         pendant la crise de paiements d'août 2026: personne ne pouvait dire,
         date par date, ce qui restait dû. */
      $factures   = DB::all('SELECT * FROM invoice WHERE booking_id = ?
                             ORDER BY COALESCE(date_emission,"9999-12-31"), id', [$id]);
      $peutEcrire = dash_droit('bookings', dash_role()) === 'ecrit';
      $TF = ['acompte'=>'Acompte','solde'=>'Solde','totale'=>'Totale',
             'note_frais'=>'Note de frais','avoir'=>'Avoir'];
      $SF = ['brouillon'=>'brouillon','envoyee'=>'envoyée','payee'=>'payée','annulee'=>'annulée'];

      $facture = $paye = $du = 0.0;
      $enRetard = [];
      $auj = date('Y-m-d');
      foreach ($factures as $f) {
          if ($f['statut'] === 'annulee') continue;
          $m = (float)$f['montant'] * ($f['type'] === 'avoir' ? -1 : 1);
          if ($f['statut'] !== 'brouillon') $facture += $m;
          if ($f['statut'] === 'payee') { $paye += $m; }
          elseif ($f['statut'] === 'envoyee') {
              $du += $m;
              if ($f['date_echeance'] && (string)$f['date_echeance'] < $auj) $enRetard[] = $f;
          }
      }
      ?>

      <?php if ($factures): ?>
        <div class="rap <?= $enRetard ? 'ecart' : 'ok' ?>">
          Facturé <strong><?= number_format($facture,2,',',' ') ?></strong>,
          encaissé <strong><?= number_format($paye,2,',',' ') ?></strong>,
          en attente <strong><?= number_format($du,2,',',' ') ?></strong>.
          <?php if ($enRetard): ?>
            <strong><?= count($enRetard) ?></strong> facture<?= count($enRetard)>1?'s':'' ?>
            au-delà de l'échéance.
          <?php endif; ?>
          <?php if ($b['prix_cession'] !== null && abs($facture - (float)$b['prix_cession']) > 0.5): ?>
            <br>Le prix de cession annoncé est
            <strong><?= number_format((float)$b['prix_cession'],2,',',' ') ?> <?= e($b['devise']) ?></strong>:
            écart de <strong><?= number_format($facture - (float)$b['prix_cession'],2,',',' ') ?></strong>.
            Un acompte seul explique un écart; deux mois après la date, non.
          <?php endif; ?>
        </div>

        <div class="tbl"><table>
          <thead><tr>
            <th>Numéro</th><th>Nature</th><th>Destinataire</th><th>Émise</th>
            <th>Échéance</th><th class="d">Montant</th><th>État</th><th></th>
          </tr></thead>
          <tbody>
          <?php foreach ($factures as $f): $fid = (int)$f['id'];
                $ret = $f['statut']==='envoyee' && $f['date_echeance'] && (string)$f['date_echeance'] < $auj; ?>
            <tr>
              <td><?= e($f['numero'] ?: '—') ?>
                <?php if ($f['libelle']): ?><br><span class="pt"><?= e((string)$f['libelle']) ?></span><?php endif; ?>
              </td>
              <td class="sec"><?= e($TF[$f['type']] ?? $f['type']) ?></td>
              <td class="sec"><?= e($f['destinataire'] ?? '') ?></td>
              <td class="sec"><?= $f['date_emission'] ? e(date('d.m.Y', strtotime((string)$f['date_emission']))) : '' ?></td>
              <td class="sec <?= $ret ? 'retard' : '' ?>">
                <?= $f['date_echeance'] ? e(date('d.m.Y', strtotime((string)$f['date_echeance']))) : '' ?>
                <?php if ($ret): ?><br><span class="pt">dépassée</span><?php endif; ?>
              </td>
              <td class="d"><?= $f['type']==='avoir' ? '&minus;' : '' ?><?= number_format((float)$f['montant'],2,',',' ') ?>
                <?= e($f['devise']) ?></td>
              <td><span class="et et-f<?= e($f['statut']) ?>"><?= e($SF[$f['statut']]) ?></span>
                <?php if ($f['statut']==='payee' && $f['date_paiement']): ?>
                  <br><span class="pt">le <?= e(date('d.m.Y', strtotime((string)$f['date_paiement']))) ?></span>
                <?php endif; ?>
              </td>
              <td class="d">
                <?php if ($peutEcrire): ?>
                  <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=factures" class="inline">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="fact" value="statut">
                    <input type="hidden" name="ligne" value="<?= $fid ?>">
                    <?php if ($f['statut']==='brouillon'): ?>
                      <button type="submit" name="st" value="envoyee" class="lien-b">envoyée</button>
                    <?php elseif ($f['statut']==='envoyee'): ?>
                      <button type="submit" name="st" value="payee" class="lien-b">payée</button>
                    <?php endif; ?>
                  </form>
                  <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=factures" class="inline"
                        onsubmit="return confirm('Supprimer cette facture ?')">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="fact" value="supprimer">
                    <input type="hidden" name="ligne" value="<?= $fid ?>">
                    <button type="submit" class="x">×</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php else: ?>
        <p class="sec">Aucune facture notée sur cette date.</p>
      <?php endif; ?>

      <?php if ($peutEcrire): ?>
      <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=factures" class="ajl">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="fact" value="ajouter">
        <input type="text" name="numero" placeholder="Numéro" size="9">
        <select name="type"><?php foreach ($TF as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $k==='totale'?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
        <input type="text" name="destinataire" placeholder="Destinataire" size="16"
               value="<?= e((string)($b['client'] ?: ($b['venue'] ?: ''))) ?>">
        <input type="date" name="date_emission" title="Émise le">
        <input type="date" name="date_echeance" title="Échéance">
        <input type="text" name="montant" placeholder="Montant" size="8">
        <select name="devise"><option>CHF</option><option>EUR</option></select>
        <button type="submit">ajouter</button>
      </form>
      <p class="sec pt">Le numéro se recopie de bexio ou du carnet, il ne s'invente pas ici:
         deux numérotations parallèles seraient pires que pas de numéro du tout.</p>
      <?php endif; ?>

      <div class="rap gris">
        <strong>La liaison bexio n'est pas faite</strong>, et cet onglet ne la remplace pas:
        il note ce qui existe, il n'émet rien. Le portage du client bexio, qui vit dans Apps
        Script, est chiffré entre 12 h et 20 h pour le seul OAuth2, plus 6 à 10 h par
        endpoint. La colonne <code>bexio_id</code> existe déjà et reste vide, pour que le
        rapprochement ne demande pas une migration de plus.
      </div>

      <style>
      .et-fpayee{border-color:#7bb33a;font-weight:600}
      .et-fenvoyee{border-color:#d9a800}
      .et-fannulee{opacity:.55}
      td.retard{color:#e2653a}
      .rap.gris{border-left-color:var(--doux)}
      </style>

    <?php elseif ($ong === 'advancing'): ?>
      <?php
      /* ADVANCING. [16.08.2026]

         LE POINT N'EST PAS LE FORMULAIRE, C'EST L'ÉTAT PAR CHAMP. Un plan de
         feu REÇU n'est pas un plan de feu ACCEPTÉ, et c'est exactement là que
         les tournées se cassent. Le portail ne peut donc jamais faire monter
         un champ au-delà de « reçu »: valider est un geste d'ici.

         L'écran s'ouvre sur ce qui manque, pas sur la liste complète: la
         question qu'on se pose en ouvrant cet onglet est « qu'est-ce qui
         bloque », pas « qu'est-ce que j'avais demandé ». */
      $champs     = Advancing::champs($id);
      $av         = Advancing::avancement($id);
      $lien       = Advancing::lien($id);
      $peutEcrire = dash_droit('bookings', dash_role()) === 'ecrit';

      $sections = [];
      foreach ($champs as $c) $sections[(string)($c['section'] ?? '')][] = $c;

      $urlPortail = $lien && !(int)$lien['revoque']
          ? rtrim((string)cfg('base_url', ''), '/') . '/advancing.php?t=' . $lien['jeton'] : '';
      ?>

      <?php if ($champs): ?>
        <div class="rap <?= $av['manquants_obligatoires'] > 0 ? 'ecart' : 'ok' ?>">
          <strong><?= $av['accepte'] ?></strong> accepté<?= $av['accepte'] > 1 ? 's' : '' ?>,
          <strong><?= $av['recu'] ?></strong> reçu<?= $av['recu'] > 1 ? 's' : '' ?> à valider,
          <strong><?= $av['demande'] ?></strong> en attente<?php if ($av['refuse']): ?>,
          <strong><?= $av['refuse'] ?></strong> à refaire<?php endif; ?>.
          <?php if ($av['manquants_obligatoires'] > 0): ?>
            Il manque <strong><?= $av['manquants_obligatoires'] ?></strong> élément<?= $av['manquants_obligatoires'] > 1 ? 's' : '' ?>
            marqué<?= $av['manquants_obligatoires'] > 1 ? 's' : '' ?> nécessaire<?= $av['manquants_obligatoires'] > 1 ? 's' : '' ?>.
          <?php else: ?>Rien de nécessaire ne manque.<?php endif; ?>
        </div>
      <?php endif; ?>

      <?php /* Le lien remis au lieu, en tête: c'est la première chose qu'on
               vient chercher ici quand on prépare une date. */ ?>
      <div class="lienbox">
        <?php if ($urlPortail): ?>
          <div class="glab">Lien remis au lieu</div>
          <input type="text" class="url" value="<?= e($urlPortail) ?>" readonly
                 onclick="this.select()" aria-label="Lien du portail">
          <p class="sec pt">
            <?php if ($lien['destinataire']): ?>Remis à <strong><?= e((string)$lien['destinataire']) ?></strong>. <?php endif; ?>
            <?= (int)$lien['visites'] ?> visite<?= (int)$lien['visites'] > 1 ? 's' : '' ?><?php
              if ($lien['dernier_acces']): ?>, la dernière le <?= e(date('d.m.Y à H:i', strtotime((string)$lien['dernier_acces']))) ?><?php endif; ?>.
            <?php if ($lien['expire_a']): ?> Expire le <?= e(date('d.m.Y', strtotime((string)$lien['expire_a']))) ?>.<?php endif; ?>
          </p>
          <?php if ($peutEcrire): ?>
            <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=advancing" class="inline"
                  onsubmit="return confirm('Révoquer ce lien ? Le lieu ne pourra plus répondre.')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="adv" value="revoquer">
              <button type="submit" class="lien-b">révoquer</button>
            </form>
            <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=advancing" class="inline"
                  onsubmit="return confirm('Ouvrir un nouveau lien ? L\'ancien cessera de fonctionner.')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="adv" value="ouvrir">
              <button type="submit" class="lien-b">en ouvrir un nouveau</button>
            </form>
          <?php endif; ?>
        <?php elseif ($peutEcrire): ?>
          <div class="glab">Aucun lien ouvert</div>
          <p class="sec">Le lieu répond depuis une page qui ne demande ni compte ni mot de passe.
             Le lien ne vaut que pour cette date, et ne donne accès ni au prix, ni au deal,
             ni aux notes internes.</p>
          <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=advancing" class="ajl">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="adv" value="ouvrir">
            <input type="text" name="destinataire" placeholder="À qui on le remet (facultatif)" size="26">
            <button type="submit">ouvrir le lien</button>
          </form>
        <?php else: ?>
          <div class="glab">Aucun lien ouvert</div>
        <?php endif; ?>
      </div>

      <?php if ($champs): ?>
        <?php foreach ($sections as $nomSec => $liste): ?>
          <?php if ($nomSec !== ''): ?><div class="glab sec-t"><?= e($nomSec) ?></div><?php endif; ?>
          <div class="tbl"><table>
            <thead><tr><th>Élément</th><th>Réponse</th><th>État</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($liste as $c): $cid = (int)$c['id']; ?>
              <tr>
                <td>
                  <?= e((string)$c['libelle']) ?>
                  <?php if ((int)$c['obligatoire'] === 1): ?><span class="ob" title="nécessaire">·</span><?php endif; ?>
                  <br><span class="pt"><?= e(Advancing::TYPES[$c['type']] ?? $c['type']) ?></span>
                  <?php if ($c['note_interne']): ?>
                    <br><span class="pt ni">interne : <?= e((string)$c['note_interne']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="sec">
                  <?php if ($c['type'] === 'fichier' && $c['fichier']): ?>
                    <a href="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=advancing&amp;adl=<?= $cid ?>"><?= e((string)$c['fichier']) ?></a>
                  <?php elseif (trim((string)($c['reponse'] ?? '')) !== ''): ?>
                    <?= nl2br(e(mb_substr((string)$c['reponse'], 0, 400))) ?>
                  <?php else: ?><span class="pt">—</span><?php endif; ?>
                  <?php if ($c['repondu_a']): ?>
                    <br><span class="pt">le <?= e(date('d.m.Y', strtotime((string)$c['repondu_a']))) ?></span>
                  <?php endif; ?>
                </td>
                <td><span class="et et-a<?= e($c['etat']) ?>"><?= e(Advancing::ETATS[$c['etat']]) ?></span></td>
                <td class="d">
                  <?php if ($peutEcrire): ?>
                    <?php if ($c['etat'] === 'recu'): ?>
                      <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=advancing" class="inline">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="adv" value="etat">
                        <input type="hidden" name="champ" value="<?= $cid ?>">
                        <button type="submit" name="etat" value="accepte" class="lien-b">valider</button>
                        <button type="submit" name="etat" value="refuse"  class="lien-b">à refaire</button>
                      </form>
                    <?php endif; ?>
                    <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=advancing" class="inline"
                          onsubmit="return confirm('Supprimer cet élément ?')">
                      <?= Auth::csrfField() ?>
                      <input type="hidden" name="adv" value="supprimer">
                      <input type="hidden" name="champ" value="<?= $cid ?>">
                      <button type="submit" class="x">×</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="sec">Rien de demandé pour l'instant. Chaque élément ajouté ici apparaît
           sur la page du lieu, dans l'ordre choisi.</p>
      <?php endif; ?>

      <?php if ($peutEcrire): ?>
      <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=advancing" class="ajl">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="adv" value="ajouter">
        <input type="text" name="section" placeholder="Section" size="12">
        <input type="text" name="libelle" placeholder="Ce qu'on demande" required>
        <select name="type"><?php foreach (Advancing::TYPES as $k=>$v): ?>
          <option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
        <input type="text" name="ordre" value="100" size="3" title="Ordre">
        <label class="ck"><input type="checkbox" name="obligatoire" value="1"> nécessaire</label>
        <input type="text" name="consigne" placeholder="Consigne visible par le lieu" size="24">
        <button type="submit">ajouter</button>
      </form>
      <p class="sec pt">La consigne part avec la demande. La note interne, elle, ne quitte
         jamais cet écran — c'est là que va « prévoir large, ils sont toujours en retard ».</p>
      <?php endif; ?>

      <style>
      .lienbox{border:1px solid var(--trait);border-radius:6px;padding:15px 18px;margin-bottom:22px}
      .lienbox .url{width:100%;padding:8px 10px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
        font-size:12.5px;border:1px solid var(--trait);border-radius:4px;
        background:var(--fond2);color:var(--encre)}
      .lien-b{background:none;border:0;color:var(--doux);text-decoration:underline;
        cursor:pointer;font:inherit;font-size:12.5px;padding:2px 6px}
      .lien-b:hover{color:var(--encre)}
      .sec-t{margin-top:22px}
      .ob{color:#e2653a;font-weight:700}
      .pt.ni{color:#9a7a2a}
      .ck{font-size:13px;display:inline-flex;align-items:center;gap:5px;white-space:nowrap}
      .et{font-size:12px;padding:2px 7px;border-radius:3px;white-space:nowrap;border:1px solid var(--trait)}
      .et-aaccepte{border-color:#7bb33a;font-weight:600}
      .et-arecu{border-color:#d9a800}
      .et-arefuse{border-color:#e2653a}
      </style>

    <?php elseif ($ong === 'voyage'): ?>
      <?php
      /* VOYAGE. [16.08.2026]

         DES DONNÉES, PAS DES FICHIERS. C'est tout l'objet: ces informations
         existaient déjà, mais comme pièces jointes dans l'espace — un PDF de
         billet, une confirmation d'hôtel. On ne pouvait ni les additionner, ni
         voir qui voyage quand, ni rapprocher leur coût du prix de cession.

         LE TRI EST CHRONOLOGIQUE et non par nature: un voyage se lit dans
         l'ordre où il se vit. Ranger les vols ensemble puis les hôtels
         ensemble obligerait à recomposer la journée de tête. */
      $trajets = DB::all('SELECT * FROM trip_item WHERE booking_id = ?
                          ORDER BY COALESCE(date_debut, "9999-12-31"), id', [$id]);
      $peutEcrire = dash_droit('bookings', dash_role()) === 'ecrit';
      $TV = ['vol'=>'Vol','train'=>'Train','bus'=>'Bus','voiture'=>'Voiture',
             'transfert'=>'Transfert','hotel'=>'Hôtel','autre'=>'Autre'];
      $CG2 = ['incluse'=>'incluse','lieu'=>'à la charge du lieu','nous'=>'à notre charge'];

      $totV = ['incluse'=>0.0,'lieu'=>0.0,'nous'=>0.0];
      foreach ($trajets as $t) if ($t['montant'] !== null) $totV[$t['charge']] += (float)$t['montant'];
      ?>

      <?php if ($trajets): ?>
        <div class="tbl"><table>
          <thead><tr>
            <th>Quand</th><th>Nature</th><th>Qui</th><th>Trajet</th>
            <th>Référence</th><th>Charge</th><th class="d">Montant</th><th></th>
          </tr></thead>
          <tbody>
          <?php foreach ($trajets as $t): ?>
            <tr class="c-<?= e($t['charge']) ?>">
              <td class="sec">
                <?php if ($t['date_debut']): ?>
                  <?= e(date('d.m H:i', strtotime((string)$t['date_debut']))) ?>
                  <?php if ($t['date_fin']): ?><br><span class="pt">au <?= e(date('d.m H:i', strtotime((string)$t['date_fin']))) ?></span><?php endif; ?>
                <?php else: ?><span class="pt">sans date</span><?php endif; ?>
              </td>
              <td><?= e($TV[$t['type']] ?? $t['type']) ?>
                <?php if ($t['libelle']): ?><br><span class="pt"><?= e($t['libelle']) ?></span><?php endif; ?>
              </td>
              <td class="sec"><?= e($t['qui'] ?? '') ?></td>
              <td class="sec">
                <?= e($t['depart'] ?? '') ?><?php if ($t['arrivee']): ?> &rarr; <?= e($t['arrivee']) ?><?php endif; ?>
              </td>
              <td class="sec"><?= e($t['reference'] ?? '') ?></td>
              <td class="sec"><?= e($CG2[$t['charge']]) ?></td>
              <td class="d"><?= $t['montant'] !== null
                  ? number_format((float)$t['montant'],2,',',' ') . ' ' . e($t['devise']) : '' ?></td>
              <td class="d">
                <?php if ($peutEcrire): ?>
                  <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=voyage" class="inline"
                        onsubmit="return confirm('Supprimer cette ligne ?')">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="voyage" value="supprimer">
                    <input type="hidden" name="ligne" value="<?= (int)$t['id'] ?>">
                    <button type="submit" class="x">×</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <?php foreach ($CG2 as $k => $lib): if (!$totV[$k]) continue; ?>
            <tr class="tot c-<?= $k ?>"><td colspan="6"><?= e(ucfirst($lib)) ?></td>
              <td class="d"><strong><?= number_format($totV[$k],2,',',' ') ?></strong></td><td></td></tr>
            <?php endforeach; ?>
          </tfoot>
        </table></div>

        <?php /* Le voyage inclus pèse sur le prix de cession comme n'importe
                 quelle ligne de deal. Le dire ici évite d'ouvrir deux onglets
                 pour savoir si le compte est bon. */ ?>
        <?php if ($totV['incluse'] > 0 && $b['prix_cession'] !== null): ?>
          <div class="rap ok">Le voyage inclus représente
            <strong><?= number_format($totV['incluse'],2,',',' ') ?></strong> sur un prix de cession
            annoncé de <strong><?= number_format((float)$b['prix_cession'],2,',',' ') ?>
            <?= e($b['devise']) ?></strong>, soit
            <strong><?= number_format($totV['incluse'] / max((float)$b['prix_cession'],0.01) * 100, 1, ',', ' ') ?> %</strong>.</div>
        <?php endif; ?>
      <?php else: ?>
        <p class="sec">Aucun trajet. Ces informations existent sans doute déjà quelque part
           en pièce jointe: les poser ici permet de les compter et de voir la journée
           dans l'ordre.</p>
      <?php endif; ?>

      <?php if ($peutEcrire): ?>
      <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=voyage" class="ajl">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="voyage" value="ajouter">
        <select name="type"><?php foreach ($TV as $k=>$v): ?>
          <option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
        <input type="text" name="qui"      placeholder="Qui voyage" size="16">
        <input type="text" name="depart"   placeholder="De" size="10">
        <input type="text" name="arrivee"  placeholder="À"  size="10">
        <input type="datetime-local" name="date_debut" title="Départ">
        <input type="datetime-local" name="date_fin"   title="Arrivée ou fin de séjour">
        <input type="text" name="reference" placeholder="Référence" size="10">
        <select name="charge"><?php foreach ($CG2 as $k=>$v): ?>
          <option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
        <input type="text" name="montant" placeholder="Montant" size="8">
        <select name="devise"><option>CHF</option><option>EUR</option></select>
        <button type="submit">ajouter</button>
      </form>
      <p class="sec pt">Un hôtel se note avec la date d'arrivée et celle de départ.
         « Qui voyage » est du texte libre: une partie des personnes en tournée n'ont
         pas de fiche chez nous, et attendre qu'elles en aient une empêcherait de
         noter le billet.</p>
      <?php endif; ?>

    <?php elseif ($ong === 'contrats'): ?>
      <?php
      /* CONTRATS. [16.08.2026]

         LE PDF SE DÉPOSE, IL NE SE GÉNÈRE PAS. Le site n'a aucune bibliothèque
         de génération — vérifié ce jour: ni FPDF, ni TCPDF, ni Dompdf — et
         Skribble::send() attend un fichier qui existe déjà. Le contrat se
         rédige donc là où il se rédige aujourd'hui, et se dépose ici. C'est
         aussi ce que fait l'espace collaborateur, et c'est éprouvé.

         L'ÉTAT SE LIT À L'OUVERTURE, pas sur un bouton « rafraîchir »:
         personne ne pense à cliquer, et un contrat signé qui s'affiche « en
         attente » fait relancer quelqu'un pour rien. */
      $contrats   = Contracts::duBooking($id);
      $peutEcrire = dash_droit('bookings', dash_role()) === 'ecrit';
      $TYPES = ['cession'=>'Cession','coproduction'=>'Coproduction',
                'engagement'=>'Engagement','avenant'=>'Avenant','autre'=>'Autre'];
      $ETAT  = ['depose'=>'déposé','envoye'=>'envoyé, en attente',
                'signe'=>'signé','refuse'=>'refusé'];
      ?>

      <?php if (!Skribble::configured()): ?>
        <div class="rap ecart">La signature en ligne n'est pas configurée sur ce site.
          Les contrats se déposent et se téléchargent quand même: seul l'envoi à la
          signature est indisponible.</div>
      <?php endif; ?>

      <?php if ($contrats): ?>
        <div class="tbl"><table>
          <thead><tr>
            <th>Titre</th><th>Nature</th><th>Signataire</th><th>État</th><th>Fichiers</th><th></th>
          </tr></thead>
          <tbody>
          <?php foreach ($contrats as $c): $cid = (int)$c['id']; ?>
            <tr>
              <td><?= e($c['titre']) ?></td>
              <td class="sec"><?= e($TYPES[$c['type']] ?? $c['type']) ?></td>
              <td class="sec">
                <?= e($c['signataire_nom'] ?: ($c['signataire_email'] ?: '')) ?>
                <?php if ($c['signataire_nom'] && $c['signataire_email']): ?>
                  <br><span class="pt"><?= e($c['signataire_email']) ?></span>
                <?php endif; ?>
              </td>
              <td><span class="et et-<?= e($c['statut']) ?>"><?= e($ETAT[$c['statut']] ?? $c['statut']) ?></span>
                <?php if ($c['statut'] === 'envoye' && $c['envoye_a']): ?>
                  <br><span class="pt">depuis le <?= e(date('d.m.Y', strtotime((string)$c['envoye_a']))) ?></span>
                <?php elseif ($c['statut'] === 'signe' && $c['signe_a']): ?>
                  <br><span class="pt">le <?= e(date('d.m.Y', strtotime((string)$c['signe_a']))) ?></span>
                <?php endif; ?>
              </td>
              <td class="sec">
                <a href="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=contrats&amp;dl=<?= $cid ?>&amp;v=depose">déposé</a>
                <?php if ($c['fichier_signe']): ?>
                  · <a href="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=contrats&amp;dl=<?= $cid ?>&amp;v=signe"><strong>signé</strong></a>
                <?php endif; ?>
                <?php if ($c['statut'] === 'envoye' && $c['signing_url']): ?>
                  · <a href="<?= e($c['signing_url']) ?>" target="_blank" rel="noopener">lien de signature</a>
                <?php endif; ?>
              </td>
              <td class="d">
                <?php if ($peutEcrire): ?>
                  <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=contrats" class="inline"
                        onsubmit="return confirm('Supprimer ce contrat et ses fichiers ?')">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="contrat" value="supprimer">
                    <input type="hidden" name="ligne" value="<?= $cid ?>">
                    <button type="submit" class="x">×</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php if ($peutEcrire && in_array($c['statut'], ['depose','refuse'], true) && Skribble::configured()): ?>
            <tr class="env"><td colspan="6">
              <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=contrats" class="ajl">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="contrat" value="envoyer">
                <input type="hidden" name="ligne" value="<?= $cid ?>">
                <input type="text"  name="nom"    placeholder="Nom du signataire" size="18">
                <input type="email" name="email"  placeholder="e-mail du signataire" required size="22">
                <input type="text"  name="mobile" placeholder="mobile (facultatif)" size="14">
                <button type="submit">envoyer à la signature</button>
              </form>
            </td></tr>
            <?php endif; ?>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php else: ?>
        <p class="sec">Aucun contrat sur cette date.</p>
      <?php endif; ?>

      <?php if ($peutEcrire): ?>
      <form method="post" action="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=contrats"
            class="ajl" enctype="multipart/form-data">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="contrat" value="deposer">
        <select name="type"><?php foreach ($TYPES as $k=>$v): ?>
          <option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
        <input type="text" name="titre" placeholder="Titre du contrat">
        <input type="file" name="pdf" accept="application/pdf" required>
        <button type="submit">déposer</button>
      </form>
      <p class="sec pt">Le PDF se rédige ailleurs et se dépose ici: le site ne sait pas
         encore produire un contrat depuis les données du booking. Laisser le titre vide
         reprend le nom du fichier.</p>
      <?php endif; ?>

      <style>
      .et{font-size:12px;padding:2px 7px;border-radius:3px;white-space:nowrap;
        border:1px solid var(--trait)}
      .et-signe{border-color:#7bb33a;font-weight:600}
      .et-envoye{border-color:#d9a800}
      .et-refuse{border-color:#e2653a}
      tr.env td{background:var(--fond2);padding-top:2px;padding-bottom:10px}
      tr.env form.ajl{margin-top:0;padding-top:0;border-top:0}
      </style>

    <?php endif; ?>
    </div>

    <style>
    .fil { padding:12px 26px 0; font-size:13px; display:flex; gap:16px; align-items:baseline; }
    .fil a { color:var(--doux); text-decoration:none; }
    .fil a.mod { margin-left:auto; color:var(--encre); font-weight:600; }
    /* Les flèches gardent leur place quand il n'y a plus de voisin: un bouton
       qui disparaît fait bouger les autres sous le curseur, et on clique sur
       « modifier » en croyant avancer d'une fiche. */
    .fil .pas { color:var(--encre); font-weight:600; }
    .fil .pas.mort { color:var(--doux); opacity:.35; }
    .fil .rang { color:var(--doux); font-variant-numeric:tabular-nums; }
    .onglets { display:flex; gap:2px; padding:12px 26px 0; border-bottom:1px solid var(--trait);
               overflow-x:auto; }
    .onglets a { padding:8px 15px; font-size:13.5px; text-decoration:none; white-space:nowrap;
               border-bottom:3px solid transparent; color:var(--doux); }
    .onglets a.ici { color:var(--encre); border-bottom-color:var(--jaune); font-weight:600; }
    h2.gros { font-size:20px; margin:0 0 18px; }
    .fiche { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
             gap:0 32px; max-width:900px; }
    .fiche .l { display:flex; gap:12px; padding:7px 0; border-bottom:1px solid var(--trait); }
    .fiche .k { color:var(--doux); font-size:12.5px; min-width:120px; }
    .fiche .v { font-size:14px; }
    .fiche .n, .notes .n { color:var(--doux); font-size:12px; font-weight:400; margin-left:8px; }
    .notes { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
             gap:18px; margin-top:26px; max-width:900px; }
    .notes .bloc { padding:14px 18px; background:var(--fond2); border-left:4px solid var(--jaune); }
    .notes .bloc.int { border-left-color:var(--orange); }
    .notes h3 { font-size:13.5px; margin:0 0 8px; }
    .notes p { margin:0; font-size:14px; }
/* LA CARTE À DROITE, LE TEXTE AUTOUR. [Anna, 21.08.2026] « la carte doit être
   à droite et pas d'espace vide à gauche, il faut essayer de mettre comme
   l'image d'avant ». Un `float` plutôt qu'une colonne: la fiche, les notes et
   les fichiers continuent de couler à sa gauche et remplissent la place, au
   lieu de laisser une bande blanche sous une carte pleine largeur. */
/* Le champ de note prend la largeur de son bloc et grandit si l'on tire
   dessus. Le bouton reste à droite, discret: on écrit plus souvent qu'on
   n'enregistre, et un bouton noir plein sous chaque note ferait deux appels à
   l'action pour une page qui n'en a pas. */
.fnote{margin:0}
.fnote textarea{width:100%;box-sizing:border-box;padding:8px 10px;font:inherit;
  font-size:13.5px;line-height:1.45;border:1px solid var(--trait);border-radius:5px;
  background:var(--papier);color:var(--encre);resize:vertical;min-height:74px}
.fnote textarea:focus{outline:2px solid var(--jaune,#FFD24D);outline-offset:-1px}
.fnote-act{display:flex;justify-content:flex-end;margin-top:7px}
.fnote-act button{padding:5px 13px;font:inherit;font-size:12.5px;font-weight:600;
  cursor:pointer;border:1px solid var(--trait);border-radius:5px;
  background:transparent;color:var(--encre)}
.fnote-act button:hover{border-color:var(--encre);background:var(--fond2)}

.carte{position:relative;float:right;width:420px;max-width:46%;
  margin:0 0 20px 26px;border:1px solid var(--trait);
  border-radius:8px;overflow:hidden;background:var(--fond2)}
/* La mosaïque: chaque tuile est une image posée à sa place, et la fenêtre
   rogne ce qui dépasse. Aucun script, donc rien à charger et rien à casser. */
.carte-vue{position:relative;overflow:hidden;background:#e8e4dd}
.carte-vue img{position:absolute;display:block;max-width:none}
/* Le marqueur est dessiné en CSS: une image de plus pour un point serait une
   requête de plus, et elle manquerait le jour où le service la déplace. */
.carte-pin{position:absolute;width:14px;height:14px;margin:-7px 0 0 -7px;
  border-radius:50%;background:#c8452f;border:2.5px solid #fff;
  box-shadow:0 1px 4px rgba(0,0,0,.45)}
.carte-clic{position:absolute;inset:0;display:flex;align-items:flex-end;
  justify-content:flex-end;padding:8px;text-decoration:none}
.carte-clic span{background:rgba(255,255,255,.9);color:var(--encre);font-size:11px;
  padding:2px 8px;border-radius:4px;opacity:0;transition:.15s}
.carte-clic:hover span{opacity:1}
.carte-tete{position:absolute;top:0;left:0;right:0;padding:10px 14px;
  background:linear-gradient(to bottom, rgba(0,0,0,.62), rgba(0,0,0,0));
  color:#fff;font-size:13.5px;text-shadow:0 1px 3px rgba(0,0,0,.5);pointer-events:none}
.carte-tete strong{display:block;font-size:15px}
.carte-tete span{opacity:.9;font-size:12.5px}
.carte-pied{padding:8px 14px;font-size:11.5px;color:var(--doux);
  border-top:1px solid var(--trait);background:var(--papier)}
.carte-pied .tr{display:block;overflow:hidden;text-overflow:ellipsis;
  white-space:nowrap;margin-bottom:3px}
.carte-pied a{color:var(--encre);text-decoration:none;white-space:nowrap}
.carte-pied a:hover{text-decoration:underline}
.carte-non{margin:0 0 20px;font-size:13px;color:var(--doux);max-width:760px}
.carte-non a{color:var(--encre)}
@media (max-width:900px){ .carte{float:none;width:auto;max-width:none;margin:0 0 20px} }
    </style>
    <?php
    dash_bas();
    return;
}

// ═══════════════════════════════════════════════════════════════════════════
// LA LISTE
// ═══════════════════════════════════════════════════════════════════════════

$q      = trim((string)($_GET['q'] ?? ''));
$statut = trim((string)($_GET['s'] ?? ''));
$annee  = trim((string)($_GET['a'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));

/* Peu de lignes ici, quatre-vingt-six aujourd'hui et quelques centaines à
   terme: un LIKE suffit et évite d'ajouter un index FULLTEXT qu'il faudrait
   entretenir pour rien. Le filtre lui-même est en haut du fichier, partagé
   avec les flèches de la fiche. */
[$sqlWhere, $args] = bookings_filtre();

$t0 = microtime(true);
$st = DB::pdo()->prepare("SELECT COUNT(*) FROM booking WHERE $sqlWhere");
$st->execute($args);
$total  = (int)$st->fetchColumn();
$pages  = max(1, (int)ceil($total / PAR_PAGE));
$page   = min($page, $pages);

$st = DB::pdo()->prepare("SELECT * FROM booking WHERE $sqlWhere
                          ORDER BY date_debut DESC, id DESC
                          LIMIT " . PAR_PAGE . " OFFSET " . (($page - 1) * PAR_PAGE));
$st->execute($args);
$lignes = $st->fetchAll();
$ms = (int)round((microtime(true) - $t0) * 1000);

$annees = DB::pdo()->query("SELECT YEAR(date_debut) a, COUNT(*) n FROM booking
                             WHERE supprime_le IS NULL AND date_debut IS NOT NULL
                             GROUP BY a ORDER BY a DESC")->fetchAll();
$parStatut = DB::pdo()->query("SELECT statut, COUNT(*) n FROM booking
                                WHERE supprime_le IS NULL GROUP BY statut")->fetchAll(PDO::FETCH_KEY_PAIR);

/* Les dates présentes dans les DEUX sources. C'est la double saisie qu'Anna
   décrit: aujourd'hui la même date s'écrit à la main dans le CMS et dans le
   dashboard. On la compte et on la montre, sans fusionner: choisir laquelle
   gagne demande de lire les deux. */
$doublons = (int)DB::pdo()->query(
    "SELECT COUNT(*) FROM (SELECT date_debut, ville FROM booking
       WHERE supprime_le IS NULL AND date_debut IS NOT NULL
       GROUP BY date_debut, ville HAVING COUNT(DISTINCT source) > 1) x")->fetchColumn();

$lien = function (array $chg) use ($q, $statut, $annee, $page): string {
    $p = array_merge(['e'=>'bookings','q'=>$q,'s'=>$statut,'a'=>$annee,'page'=>$page], $chg);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 1);
    return '/dashboard.php?' . http_build_query($p);
};

$ETIQ = ['option'=>'option','confirmed'=>'confirmé','canceled'=>'annulé','pending'=>'en attente'];

/* ── LA VUE « PRIX » ────────────────────────────────────────────────────────
   [16.08.2026] Une page à part et non une colonne de plus dans la liste: la
   liste sert à retrouver une date, la grille à remplir cinquante nombres. Les
   deux gestes ne se font pas le même jour et ne demandent pas la même mise en
   page. */
if (($_GET['v'] ?? '') === 'prix') {
    $sansPrix = (int)DB::val("SELECT COUNT(*) FROM booking
                               WHERE supprime_le IS NULL AND (prix_cession IS NULL OR prix_cession = 0)");
    dash_haut('bookings', '<a href="/dashboard.php?e=bookings" class="ret">toutes les dates</a> · <strong>les prix</strong>');
    dash_flash_html();
    require __DIR__ . '/_bookings_prix.php';
    dash_bas();
    return;
}

dash_haut('bookings', number_format($total,0,',',' ') . ' événement' . ($total>1?'s':'') . ' · ' . $ms . ' ms');
?>

<form class="filtres" method="get" action="/dashboard.php">
  <input type="hidden" name="e" value="bookings">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Lieu, projet, artiste, ville, client">
  <select name="s">
    <option value="">Tous les statuts</option>
    <?php foreach ($ETIQ as $k => $v): ?>
      <option value="<?= $k ?>"<?= $statut === $k ? ' selected' : '' ?>><?= e($v) ?> (<?= $parStatut[$k] ?? 0 ?>)</option>
    <?php endforeach; ?>
  </select>
  <select name="a">
    <option value="">Toutes les années</option>
    <?php foreach ($annees as $x): ?>
      <option value="<?= $x['a'] ?>"<?= $annee === (string)$x['a'] ? ' selected' : '' ?>><?= $x['a'] ?> (<?= $x['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Chercher</button>
  <?php if ($q !== '' || $statut !== '' || $annee !== ''): ?>
    <a class="vider" href="/dashboard.php?e=bookings">tout effacer</a>
  <?php endif; ?>
  <a class="neuf" href="/dashboard.php?e=bookings&amp;mod=1">+ nouvel événement</a>
  <?php /* Le chemin vers la grille des prix. Sans lui elle n'existe pas: une
       page qu'on atteint en tapant une adresse n'est ouverte par personne. Le
       compte dit combien il reste à faire — « les prix » tout court ne donne
       aucune raison de cliquer. */ ?>
  <?php $sansPx = (int)DB::val("SELECT COUNT(*) FROM booking
          WHERE supprime_le IS NULL AND (prix_cession IS NULL OR prix_cession = 0)"); ?>
  <a class="lien-px" href="/dashboard.php?e=bookings&amp;v=prix">Saisir les prix<?php
    if ($sansPx): ?> <span class="cpt"><?= $sansPx ?></span><?php endif; ?></a>
</form>
<?php dash_flash_html(); ?>

<?php if ($doublons > 0): ?>
<div class="alerte">
  <strong><?= $doublons ?> dates existent dans les deux sources</strong>, l'agenda du site et
  lv-tour du dashboard. C'est la double saisie: la même date écrite à la main des deux côtés.
  Elles sont laissées telles quelles, parce que choisir laquelle gagne demande de les lire.
</div>
<?php endif; ?>

<?php if (!$lignes): ?>
  <p class="vide">Aucun événement ne correspond.</p>
<?php else: ?>
<?php require __DIR__ . '/_filtre_colonnes.php'; ?>
<div class="tw">
<table data-filtres>
  <?php /* ── LES ONZE COLONNES DU MODÈLE D'ANNA ─────────────────────────────
       [16.08.2026] « copiar tal e qual », d'après l'écran d'agence qu'elle a
       montré: Venue · Performance name · Date · Artist · Time · Performance Fee
       · Country · City · Status · Booking Fee. « Client » a été retirée le
       17.08.2026 — elle reste sur la fiche et dans la recherche, elle ne
       tenait simplement pas sa place dans une liste de onze colonnes.

       Trois changements de fond par rapport à avant:

       LE PAYS SORT DE LA VILLE. Ils étaient empilés dans une seule cellule.
       Séparés, on trie et on filtre par pays — c'est la première question quand
       on prépare une tournée, et c'est la colonne qui décide d'une A1.

       L'HEURE SORT DE LA DATE, pour la même raison: elle se compare entre
       lignes, pas dans un coin sous la date.

       « PERFORMANCE NAME » N'EST PAS LE TITRE DE LA PIÈCE mais « Lieu — Ville »,
       comme dans le modèle. C'est le nom de l'ÉVÉNEMENT, pas de l'œuvre; les
       deux cohabitent dans le tableau et c'est voulu. */ ?>
  <thead><tr>
    <?php /* DIX COLONNES ET NON ONZE. [17.08.2026] Anna: « tu peux enlever
         Client des colonnes, donner plus de place pour les dates ». « Client »
         était la onzième et la moins lue: c'est le nom de qui paie, qu'on
         cherche sur la fiche au moment de facturer, jamais en balayant une
         liste. Elle prenait la largeur qui manquait à la date. */ ?>
    <?php /* LE SPECTACLE AVANT LE LIEU. [17.08.2026] Anna: « deixar primeiro a
         coluna do nome do projet e depois a venue ». On cherche une date en
         partant de la pièce — « où joue Bestiarium » — pas du lieu, dont il y a
         quatre-vingt-six. Le modèle d'agence mettait Venue en tête parce qu'une
         agence vend des salles; nous vendons des spectacles. */ ?>
    <?php /* Ce qui se choisit dans une liste, ce qui se tape, ce qui ne se
         filtre pas. Le pays a trois valeurs et le statut quatre: les taper
         serait plus lent que les choisir. Les deux colonnes d'argent ne se
         filtrent pas — on ne cherche pas une date par son montant. */ ?>
    <th class="c-nom" data-f="choix">Performance name</th><th class="c-venue">Venue</th>
    <th class="c-date">Date</th><th data-f="choix">Artist</th>
    <th class="c-h" data-f="non">Time</th><th class="d" data-f="non">Performance Fee</th>
    <th class="c-pays" data-f="choix">Country</th><th class="c-ville">City</th>
    <th data-f="choix">Status</th><th class="d" data-f="non">Booking Fee</th>
  </tr></thead>
  <tbody>
  <?php foreach ($lignes as $r):
    /* « Fri, 24 Jul 26 »: le jour de la semaine en tête, comme dans le modèle.
       Il n'est pas décoratif — on programme un samedi, pas un 25. */
    $jour = $r['date_debut']
        ? date('D, j M y', strtotime((string)$r['date_debut']))
        : (string)$r['date_texte'];
    /* « PERFORMANCE NAME » PORTE LE SPECTACLE, PAS LE LIEU. [17.08.2026]
       Anna: « na page booking ainda esta o doublon do nome do lieu ». Il
       valait « Lieu - Ville », donc il répétait mot pour mot la colonne Venue
       juste à gauche ET la colonne City quatre colonnes plus loin: trois
       cellules pour deux informations, sur onze colonnes déjà serrées.

       D'où venait l'erreur: le modèle d'agence qu'Anna avait donné le 16.08
       met « Venue » et « Performance name » côte à côte, et j'avais lu la
       seconde comme le nom de la SOIRÉE. C'est le nom de ce qui se joue —
       Bestiarium, Dolce Vita — ce qui est aussi la seule colonne qui manquait
       au tableau: on y voyait l'artiste et le lieu, jamais la pièce.

       `projet` est rempli sur les 51 dates, vérifié avant de basculer. */
    $nomEvt = trim((string)($r['projet'] ?? '')); ?>
    <tr>
      <td><a href="/dashboard.php?e=bookings&amp;b=<?= (int)$r['id'] ?>"><?= e($nomEvt) ?></a></td>
      <td class="sec"><?= e($r['venue'] ?? '') ?></td>
      <td class="nb c-date"><?= e($jour) ?></td>
      <td><?= e($r['artiste'] ?? '') ?></td>
      <td class="nb sec c-h"><?= $r['heure'] && $r['heure'] !== '00:00:00'
            ? e(substr((string)$r['heure'], 0, 5)) : '' ?></td>
      <td class="d nb"><?= (float)$r['prix_cession'] > 0
            ? e($r['devise']) . ' ' . number_format((float)$r['prix_cession'], 2, ',', ' ') : '' ?></td>
      <td class="sec c-pays"><?= e($r['pays'] ?? '') ?></td>
      <td><?= e($r['ville'] ?? '') ?></td>
      <td><span class="et <?= e($r['statut']) ?>"><?= e($ETIQ[$r['statut']] ?? $r['statut']) ?></span></td>
      <td class="d nb"><?= (float)($r['frais_booking'] ?? 0) > 0
            ? e($r['devise']) . ' ' . number_format((float)$r['frais_booking'], 2, ',', ' ')
              . ((float)($r['frais_booking_taux'] ?? 0) > 0
                 ? '<div class="sec">' . rtrim(rtrim(number_format((float)$r['frais_booking_taux'], 2, ',', ' '), '0'), ',') . ' %</div>'
                 : '')
            : '' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<nav class="pages">
  <?php if ($page > 1): ?><a href="<?= e($lien(['page'=>$page-1])) ?>">précédent</a><?php endif; ?>
  <span class="mut">page <?= $page ?> sur <?= $pages ?></span>
  <?php if ($page < $pages): ?><a href="<?= e($lien(['page'=>$page+1])) ?>">suivant</a><?php endif; ?>
</nav>
<?php endif; ?>

<style>
td.d, th.d { text-align:right; white-space:nowrap; }
/* ── LES LARGEURS, PARCE QUE LE NAVIGATEUR CHOISIT MAL ──────────── [17.08.2026]
   Anna, capture à l'appui: « cest quoi cette mise en page! ». « Sun, 25 Apr 27 »
   sortait sur QUATRE lignes et « Pôle Nord - Centre de Musique Contemporaine
   (CMC) » sur quatre aussi, pendant que trois colonnes vides gardaient leur
   pleine largeur.

   C'est le comportement normal d'un tableau automatique: il répartit la place
   au prorata du contenu le plus long de chaque colonne, sans savoir qu'une date
   ne se coupe pas et qu'un nom de lieu, si.

   `white-space:nowrap` SUR LA DATE ET LE PAYS: ce sont des valeurs courtes et
   atomiques, les couper ne gagne rien et coûte trois lignes de hauteur à toute
   la ligne. Le lieu et la ville gardent le droit de passer à la ligne — un nom
   de quarante caractères doit bien s'écrire quelque part — mais avec une
   largeur minimale qui lui évite de le faire à chaque mot. */
/* LA RÈGLE EXISTAIT ET NE S'APPLIQUAIT À RIEN. [Anna, 21.08.2026] « a coluna
   da data esta ilisivel ». `td.c-date` était écrit depuis le 17.08, mais aucune
   cellule ne portait la classe — seuls les `<th>`. « Sun, 25 Apr 27 » se coupait
   donc en quatre lignes et étirait toute la rangée. Même chose pour le pays et
   l'heure. Une règle CSS muette ne se voit pas à la relecture: elle se voit en
   comparant les classes des `th` à celles des `td`. */
td.c-date, th.c-date { white-space:nowrap; }
td.c-pays, th.c-pays, td.c-h, th.c-h { white-space:nowrap; }
th.c-venue { min-width:150px; }

/* LES COLONNES COURTES RENDENT LEUR LARGEUR AUX LONGUES. [21.08.2026]
   Mesuré dans un navigateur, fenêtre de 1700: la date recevait 196 px pour
   « Sun, 25 Apr 27 » qui en demande 110, pendant que « Performance name »,
   à 201 px, était la cellule la plus haute dans 60 lignes sur 60 — c'est
   elle qui fixait la hauteur de toute la liste.

   `width:1%` sur une colonne en `nowrap` la réduit à son contenu: la place
   libérée va aux colonnes de texte, qui cessent de se couper à chaque mot. */
th.c-date, th.c-h, th.c-pays { width:1%; }
th.c-nom { min-width:230px; }
th.c-ville { min-width:120px; }
table td, table th { vertical-align:top; }

.et { font-size:11.5px; padding:2px 8px; border-radius:10px; border:1px solid var(--trait);
      white-space:nowrap; }
.et.confirmed { background:#e7f6ea; border-color:#bfe3c8; color:#1c5c2e; }
.et.option    { background:#fff6d9; border-color:#f0dfa3; color:#6b5312; }
.et.pending   { background:var(--fond2); }
.et.canceled  { background:#fbe9e7; border-color:#f0c3bb; color:#7a2b1e; }
.lien-px{display:inline-flex;align-items:center;gap:7px;padding:8px 15px;
  border:1px solid var(--trait);border-radius:4px;text-decoration:none;font-size:13.5px;
  font-weight:600;color:var(--encre)}
.lien-px:hover{border-color:var(--encre)}
.lien-px .cpt{padding:1px 8px;border-radius:9px;background:var(--jaune);color:#0d0d0d;
  font-size:11.5px}
.neuf { margin-left:auto; padding:8px 16px; background:var(--jaune); color:#0d0d0d;
        border-radius:4px; text-decoration:none; font-size:13.5px; font-weight:600; }
.alerte { margin:16px 26px 0; padding:12px 16px; background:var(--fond2);
          border-left:4px solid var(--orange); font-size:13.5px; max-width:80ch; }
</style>

<?php dash_bas(); ?>

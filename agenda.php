<?php
/**
 * Un flux iCalendar par association. [16.08.2026]
 *
 * CE QU'IL RÉSOUT, ET C'EST LA PLAINTE EXACTE D'ANNA: « hj em dia eu vejo tudo
 * misturado no meu google calendar ». Filtrer dans le dashboard range le
 * dashboard; ça ne range pas Google Calendar, qui est là où elle regarde le
 * matin. Un flux .ics par association s'abonne dans Google Calendar comme
 * n'importe quel calendrier partagé: une couleur par association, chacune
 * décochable, sans qu'on ait à toucher au calendrier existant.
 *
 * ET SURTOUT: ÇA NE DEMANDE AUCUNE AUTORISATION OAUTH. La synchronisation dans
 * les deux sens en demanderait une, et elle attend une décision qui n'est pas
 * prise. L'abonnement, lui, marche aujourd'hui, en lecture seule — ce qui est
 * de toute façon le bon sens dans ce sens-là: les dates se saisissent dans le
 * dashboard, pas dans Google.
 *
 * LA SÉCURITÉ, ET ELLE COMPTE PLUS QU'IL N'Y PARAÎT. Google va chercher l'URL
 * SANS AUCUNE SESSION: ce que ce fichier rend est donc lisible par quiconque
 * connaît l'adresse. Deux conséquences tenues ici:
 *
 *   1. UN JETON OBLIGATOIRE, tiré au sort, rangé dans `settings`, changeable
 *      d'un clic dans Paramètres. Changer le jeton coupe tous les abonnements
 *      d'un coup — c'est le geste à faire le jour où une adresse a fuité.
 *   2. AUCUN ARGENT, AUCUN CLIENT, AUCUNE NOTE. Le flux porte la date, le
 *      spectacle, le lieu, la ville et le pays. Le prix de cession, le nom du
 *      client et les notes internes restent dans le dashboard. Une adresse
 *      d'agenda se recopie dans un e-mail sans y penser; ce qui part dedans
 *      doit être ce qu'on afficherait sur une affiche.
 *
 * Les dates ANNULÉES ne sont pas rendues: un calendrier qui garde les annulées
 * fait rater les vraies.
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

/** Le jeton, créé au premier appel pour n'avoir aucune étape d'installation. */
$attendu = trim(setting('agenda_token'));
if ($attendu === '') {
    $attendu = bin2hex(random_bytes(16));
    Settings::set('agenda_token', $attendu);
}

$recu = (string)($_GET['t'] ?? '');
/* `hash_equals` et non `===`: la comparaison naïve s'arrête au premier
   caractère différent, et le temps qu'elle met trahit le jeton lettre à
   lettre. Le coût est nul, l'habitude est ce qui compte. */
if ($recu === '' || !hash_equals($attendu, $recu)) {
    http_response_code(404);
    exit;
}

$asso = (int)($_GET['a'] ?? 0);

$where = ["b.supprime_le IS NULL", "b.date_debut IS NOT NULL", "b.statut <> 'canceled'"];
$args  = [];
if ($asso > 0) {
    $where[] = 'EXISTS (SELECT 1 FROM projects p2
                          JOIN projet_prod pp2 ON pp2.project_id = p2.id
                         WHERE p2.title_fr = b.projet AND pp2.organisation_id = ?)';
    $args[] = $asso;
}

$lignes = DB::all(
    'SELECT b.id, b.projet, b.artiste, b.venue, b.ville, b.pays, b.date_debut, b.date_fin,
            b.heure, b.statut, b.modifie_le, o.nom AS asso_nom
       FROM booking b
       LEFT JOIN projects p     ON p.title_fr = b.projet
       LEFT JOIN projet_prod pp ON pp.project_id = p.id
       LEFT JOIN organisation o ON o.id = pp.organisation_id AND o.supprime_le IS NULL
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY b.date_debut', $args);

$nomAsso = $asso > 0
    ? (string)(DB::val('SELECT nom FROM organisation WHERE id = ?', [$asso]) ?: 'Le Voisin')
    : 'Le Voisin';

/**
 * Échappe selon la RFC 5545 et plie à 75 octets.
 *
 * Le pliage n'est pas cosmétique: Google et Apple acceptent les lignes longues,
 * Outlook non — et une seule ligne trop longue fait rejeter le fichier entier,
 * pas la ligne.
 */
$ligne = static function (string $nom, string $val): string {
    $val = str_replace(['\\', "\r\n", "\n", ';', ','], ['\\\\', '\\n', '\\n', '\\;', '\\,'], $val);
    $s = $nom . ':' . $val;
    $out = ''; $len = 0;
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $c) {
        $l = strlen($c);
        /* On plie sur les caractères et non les octets: couper un « é » en deux
           donne un fichier illisible là où l'on voulait juste une ligne courte. */
        if ($len + $l > 73) { $out .= "\r\n "; $len = 1; }
        $out .= $c; $len += $l;
    }
    return $out;
};

$ics = ["BEGIN:VCALENDAR", "VERSION:2.0",
        "PRODID:-//Le Voisin//Agenda//FR", "CALSCALE:GREGORIAN", "METHOD:PUBLISH"];
$ics[] = $ligne('X-WR-CALNAME', 'Le Voisin — ' . $nomAsso);
$ics[] = $ligne('X-WR-CALDESC', 'Dates de tournée. Lecture seule, depuis le dashboard.');
/* Google ne relit pas un flux abonné plus souvent que toutes les quelques
   heures quoi qu'on écrive ici; on le demande quand même, c'est sans coût. */
$ics[] = "X-PUBLISHED-TTL:PT2H";
$ics[] = "REFRESH-INTERVAL;VALUE=DURATION:PT2H";

$hote = parse_url((string)cfg('base_url', 'le-voisin.com'), PHP_URL_HOST) ?: 'le-voisin.com';

foreach ($lignes as $r) {
    $d1 = (string)$r['date_debut'];
    /* DTEND est exclusif en journée entière: une date du 12 se termine le 13,
       sinon Google l'affiche sur un seul jour trop court — ou pire, la veille. */
    $d2 = $r['date_fin'] && $r['date_fin'] >= $d1 ? (string)$r['date_fin'] : $d1;
    $fin = date('Ymd', strtotime($d2 . ' +1 day'));

    $titre = trim((string)($r['projet'] ?: $r['artiste'] ?: 'Date'));
    if ($r['venue']) $titre .= ' — ' . $r['venue'];
    $lieu = trim(implode(', ', array_filter([(string)$r['venue'], (string)$r['ville'], (string)$r['pays']])));

    $desc = trim(implode("\n", array_filter([
        $r['artiste'] ? 'Artiste: ' . $r['artiste'] : '',
        $r['asso_nom'] ? 'Association: ' . $r['asso_nom'] : '',
        $r['statut'] === 'option' ? 'Statut: option, pas encore confirmée' : '',
    ])));

    $ics[] = "BEGIN:VEVENT";
    $ics[] = "UID:booking-" . (int)$r['id'] . "@" . $hote;
    $ics[] = "DTSTAMP:" . gmdate('Ymd\THis\Z', strtotime((string)($r['modifie_le'] ?: 'now')));
    if ($r['heure'] && $r['heure'] !== '00:00:00') {
        /* Avec une heure, l'événement est daté à la minute — et en heure locale
           déclarée, pas en UTC: une date à 20h à Genève doit s'afficher à 20h,
           quel que soit le fuseau de qui lit. */
        $ics[] = "DTSTART;TZID=Europe/Zurich:" . date('Ymd\THis', strtotime($d1 . ' ' . $r['heure']));
        $ics[] = "DTEND;TZID=Europe/Zurich:" . date('Ymd\THis', strtotime($d1 . ' ' . $r['heure'] . ' +2 hours'));
    } else {
        $ics[] = "DTSTART;VALUE=DATE:" . date('Ymd', strtotime($d1));
        $ics[] = "DTEND;VALUE=DATE:" . $fin;
    }
    $ics[] = $ligne('SUMMARY', $titre);
    if ($lieu !== '') $ics[] = $ligne('LOCATION', $lieu);
    if ($desc !== '') $ics[] = $ligne('DESCRIPTION', $desc);
    /* Une option reste « tentative »: Google la montre alors en trame claire,
       ce qui est exactement l'information — la date n'est pas tenue. */
    $ics[] = "STATUS:" . ($r['statut'] === 'option' ? 'TENTATIVE' : 'CONFIRMED');
    $ics[] = "END:VEVENT";
}
$ics[] = "END:VCALENDAR";

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="le-voisin.ics"');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, max-age=1800');
echo implode("\r\n", $ics) . "\r\n";

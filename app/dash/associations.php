<?php
/**
 * Écran Associations et artistes. [16.08.2026]
 *
 * Anna: « Les profils de chaque asso / artiste regroupent toutes les infos déjà
 * créées dans le dashboard, plus tout ce qui se répète entre les shows: modèles
 * de contrat, modèles de deal, devises et frais de booking par artiste. »
 *
 * LES DEUX DANS UN MÊME ÉCRAN, comme elle l'a demandé. Une association est une
 * entité juridique de la maison, un artiste est une compagnie accompagnée; ils
 * vivent au même endroit du travail et une fiche passe parfois de l'un à
 * l'autre quand un projet grandit.
 *
 * CE QUE LA REPRISE A RÉVÉLÉ, et qui n'est pas un défaut de données: dix noms
 * existent des DEUX côtés. CRILE, Encontro, Tympan et sept autres sont à la fois
 * une association juridique et une compagnie accompagnée. C'est la réalité, pas
 * un doublon: la compagnie a monté son association. L'écran le montre au lieu de
 * choisir à la place de qui lit.
 */
declare(strict_types=1);

const PAR_PAGE = 60;

/* LES 26 CANTONS, DÉCLARÉS ICI ET NON DANS `_assoc_barre.php`.  [21.08.2026]
   Ils y étaient, et `_assoc_barre.php` n'est chargé que par la branche
   « modifier ». Or `_assoc_grilles.php` les demande aussi, et depuis le
   20.08 il est chargé par la vue de LECTURE, qui ne charge pas la barre:
   ouvrir une fiche d'association tuait donc la page sur un « Undefined
   constant CANTONS ».

   Le défaut était muet et le serait resté: le journal des accès montre que
   personne n'avait ouvert une fiche depuis le 20.08 — les deux seuls appels
   sont des `curl` à moi, arrêtés au login. Il aurait sauté à la figure du
   premier qui l'ouvrait. Trouvé en testant autre chose sur la copie locale.

   Ici les deux branches le voient, parce que les deux vivent dans ce
   fichier. */
const CANTONS = ['AG','AI','AR','BE','BL','BS','FR','GE','GL','GR','JU','LU','NE','NW',
                 'OW','SG','SH','SO','SZ','TG','TI','UR','VD','VS','ZG','ZH'];

$STATUTS = ['actif' => 'actif', 'pause' => 'en pause', 'termine' => 'terminé'];
$GENRES  = ['association' => 'association', 'artiste' => 'artiste'];

/* CE QUE LE VOISIN FAIT POUR ELLE, ET CE QU'IL NE FAIT PAS. [Anna, 21.08.2026]
   « tem assos que eu nao me ocupo da contabilidade entao nao vai ter token ».

   La colonne `gestion` existait depuis la reprise et n'était NI affichée NI
   modifiable: une donnée juste pour les deux La Secousse, morte pour les
   treize autres, que personne ne pouvait corriger. Elle décide maintenant ce
   que le bloc bexio demande — et surtout ce qu'il ne demande pas.

   C'est la même règle qu'ailleurs: un champ vide n'est pas toujours un
   manque. Une association dont nous ne tenons pas la comptabilité n'aura
   jamais de jeton, et un écran qui continue de le réclamer apprend à ignorer
   ses propres alertes. */
$GESTIONS = [
    'complete'  => 'complète — administration et comptabilité',
    'diffusion' => 'diffusion seulement — pas de comptabilité chez nous',
];

$id = (int)($_GET['o'] ?? 0);

// ═══════════════════════════════════════════════════════════════════════════
// ENREGISTRER
// ═══════════════════════════════════════════════════════════════════════════

$CHAMPS = ['genre','nom','nom_legal','ide','registre','avs_employeur','ree','siret',
           'pays','canton','adresse','email','telephone','site','instagram',
           'banque_nom','banque_iban','banque_bic','devise_defaut','frais_booking',
           'marge_defaut','discipline','direction','debut_collab','statut','comite','notes',
           'chez','notes_laa','notes_avs',
    /* Ajoutés le 16.08.2026, migration 019: la conformité suisse des quatre
       onglets que la table ne portait pas. */
    'forme_juridique','date_creation','reference_poste','cp','ville',
    'contact_prenom','contact_nom',
    'rc_pro','rc_police','laa','lpp','ampg','assureur_laa','assureur_lpp','trianon',
    'avs_inscription','caisse_avs','convention_coll',
    'canton_fiscal','contribuable_cant','tva_ch','tva_ch_num','notes_fisc_ch',
    'rna','urssaf','audiens','tva_fr','tva_fr_num','notes_fisc_fr',
    'email_mdp','instagram_mdp'];
$err = $saisi = [];

/* LE JETON BEXIO. [Anna, 21.08.2026] Il se colle ici et nulle part
   ailleurs: chaque association a sa comptabilité, et il n'existe pas de
   compte bexio qui les verrait toutes.

   UN CHAMP VIDE NE VIDE RIEN. On ne réaffiche jamais un jeton — c'est un
   accès permanent à une comptabilité — donc le champ part toujours vide,
   et vide veut dire « je n'y touche pas ». Pour le retirer il y a une case
   dédiée. Même règle que les clefs de traduction, et pour la même raison:
   sinon un enregistrement distrait coupe le service en silence. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['bx'] ?? '') !== '' && ($_GET['o'] ?? 0)) {
    Auth::requireCsrf();
    dash_exige_ecriture('associations');
    $oid = (int)$_GET['o'];
    if ($_POST['bx'] === 'poser') {
        if (($_POST['bx_vider'] ?? '') === '1') {
            Bexio::poserJeton($oid, '');
            dash_flash('Jeton bexio retiré.');
        } else {
            $j = trim((string)($_POST['bx_jeton'] ?? ''));
            if ($j !== '') { Bexio::poserJeton($oid, $j); dash_flash('Jeton enregistré. Essayez-le.'); }
            else            { dash_flash('Rien n\'a été changé: le champ était vide.'); }
        }
    } elseif ($_POST['bx'] === 'comptes') {
        /* LE COMPTE ET LA TAXE SE CHOISISSENT DANS UNE LISTE, jamais tapés: on
           ne demande à personne de retenir un identifiant technique, et une
           faute de frappe sur un nombre à trois chiffres ne se voit pas. On
           garde aussi le LIBELLÉ, pour relire le réglage sans rappeler bexio
           à chaque ouverture de fiche. */
        $oo  = DB::one('SELECT * FROM organisation WHERE id = ?', [$oid]) ?: [];
        $cid = (int)($_POST['bx_compte'] ?? 0);
        $tid = (int)($_POST['bx_taxe'] ?? 0);
        $nomC = $nomT = null;
        foreach (Bexio::comptes($oo) as $x) if ($x['id'] === $cid) $nomC = $x['libelle'];
        foreach (Bexio::taxes($oo)   as $x) if ($x['id'] === $tid) $nomT = $x['libelle'];
        DB::update('organisation', [
            'bexio_compte' => $cid ?: null, 'bexio_compte_nom' => $nomC,
            'bexio_taxe'   => $tid ?: null, 'bexio_taxe_nom'   => $nomT,
        ], 'id = ?', [$oid]);
        dash_flash($cid && $tid
            ? 'Compte et taxe enregistrés: ' . $nomC . ' · ' . $nomT
            : 'Il faut choisir un compte ET une taxe.', $cid && $tid ? '' : 'err');

    } elseif ($_POST['bx'] === 'essai') {
        $o = DB::one('SELECT * FROM organisation WHERE id = ?', [$oid]);
        $r = Bexio::essai($o ?: []);
        dash_flash($r['message'], $r['ok'] ? '' : 'err');
    }
    redirect('/dashboard.php?e=associations&o=' . $oid);
}

/* ══ LES PIÈCES ANNUELLES ═══════════════════════════════════════ [18.08.2026]
   Anna: « colocar um campo attestation d'affiliation année en cours (…) deixar
   espaço para se escolher ano e depositar a atestação em pdf. Attestation
   d'affiliation de l'année en cours à une institution de prévoyance du
   deuxième pilier — que é a LPP ».

   TRAITÉ AVANT TOUT LE RESTE, et séparément du grand formulaire des cinq
   onglets: un envoi de fichier est un `multipart/form-data`, et le HTML
   interdit d'imbriquer un formulaire dans un autre. C'est la même raison qui
   met déjà les grilles trimestrielles dans `_assoc_grilles.php`. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['piece'] ?? '') !== '') {
    Auth::requireCsrf();
    dash_exige_ecriture('associations');
    $act = (string)$_POST['piece'];
    $an  = (int)($_POST['annee'] ?? date('Y'));

    if ($act === 'deposer' && $id > 0) {
        $msg = OrgPieces::deposer($id, (string)($_POST['type'] ?? ''), $an,
                                  $_FILES['fichier'] ?? ['error' => UPLOAD_ERR_NO_FILE],
                                  (string)($_POST['note'] ?? ''),
                                  (string)(Auth::user()['name'] ?? ''));
        dash_flash($msg === '' ? 'Pièce déposée.' : $msg, $msg === '' ? '' : 'err');
    } elseif ($act === 'retirer') {
        /* On vérifie que la pièce appartient bien à CETTE association: l'écran
           n'en montre pas d'autres, mais un POST fabriqué, si. */
        $pc = OrgPieces::une((int)($_POST['ligne'] ?? 0));
        if ($pc && (int)$pc['organisation_id'] === $id) {
            OrgPieces::retirer((int)$pc['id']);
            dash_flash('Pièce retirée.');
        }
    }
    redirect('/dashboard.php?e=associations&o=' . $id . '&mod=1');
}

/* Le téléchargement d'une pièce. Elle vit dans `uploads/private`, qu'Apache
   refuse de servir: c'est ici, après le contrôle du rôle, qu'elle sort. */
if (($_GET['piece_dl'] ?? '') !== '') {
    $pc = OrgPieces::une((int)$_GET['piece_dl']);
    if (!$pc || dash_droit('associations', dash_role()) === '') { http_response_code(404); exit('Introuvable.'); }
    $f = OrgPieces::chemin($pc);
    if (!is_file($f)) { http_response_code(404); exit('Fichier introuvable.'); }
    $mime = match (strtolower((string)$pc['ext'])) {
        'pdf' => 'application/pdf',
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        default => 'application/octet-stream',
    };
    /* `attachment` PAR DÉFAUT, `inline` SEULEMENT POUR UNE IMAGE DEMANDÉE À
       L'ÉCRAN. [21.08.2026] Le logo s'affiche dans la fiche: servi en pièce
       jointe il déclencherait un téléchargement au lieu de se montrer. Mais on
       n'ouvre pas un PDF dans le navigateur pour autant — une attestation se
       range, elle ne se feuillette pas — et un `inline` généreux sur un type
       inconnu est exactement ce qui fait servir un document exécutable. */
    $vue = ($_GET['vue'] ?? '') === '1'
        && in_array(strtolower((string)$pc['ext']), ['jpg', 'jpeg', 'png'], true);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . ($vue ? 'inline' : 'attachment')
         . '; filename="' . addslashes((string)$pc['fichier']) . '"');
    header('Content-Length: ' . filesize($f));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($f);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['decl'] ?? '') !== '' || ($_POST['is_act'] ?? '') !== '')) {
    Auth::requireCsrf();
    dash_exige_ecriture('associations');
    $an = (int)($_POST['annee'] ?? date('Y'));

    if (($_POST['decl'] ?? '') !== '') {
        /* Une ligne n'existe que si l'on a cliqué: ON DUPLICATE la crée au
           premier clic et la fait tourner aux suivants. */
        $t = (string)($_POST['type'] ?? ''); $pe = (string)($_POST['periode'] ?? '');
        $st = (string)($_POST['statut'] ?? 'a_faire');
        if (in_array($t, ['laa','avs'], true)
            && in_array($pe, ['T1','T2','T3','T4','annuel'], true)
            && in_array($st, ['a_faire','envoye','paye','sans_objet'], true)
            && $id > 0 && $an >= 2000 && $an <= 2100) {
            DB::pdo()->prepare(
                'INSERT INTO organisation_declaration (organisation_id,type,annee,periode,statut)
                 VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE statut=VALUES(statut)')
              ->execute([$id, $t, $an, $pe, $st]);
        }
    } elseif (($_POST['is_act'] ?? '') === 'ajouter' && $id > 0) {
        $c = strtoupper(trim((string)($_POST['canton'] ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $c)) {
            try {
                DB::insert('organisation_is', ['organisation_id'=>$id, 'canton'=>$c,
                    'compte'=>trim((string)($_POST['compte'] ?? '')) ?: null,
                    'notes'=>trim((string)($_POST['notes'] ?? '')) ?: null]);
                dash_flash('Compte cantonal ajouté.');
            } catch (Throwable $e) {
                /* La clef unique (association, canton) empêche deux comptes pour
                   le même canton: c'est voulu, il n'y en a qu'un. */
                dash_flash('Ce canton a déjà un compte pour cette association.', 'err');
            }
        }
    } elseif (($_POST['is_act'] ?? '') === 'retirer' && $id > 0) {
        DB::delete('organisation_is', 'id = ? AND organisation_id = ?',
                   [(int)($_POST['ligne'] ?? 0), $id]);
        dash_flash('Compte cantonal retiré.');
    }
    redirect('/dashboard.php?e=associations&o=' . $id . '&mod=1&an=' . $an);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    /* Le rôle décide aussi de l'écriture, et pas seulement de l'accès à
       l'écran: `production` lit les Finances sans les modifier. Le routeur
       ne peut pas le faire à notre place, lui ne voit pas les POST. */
    dash_exige_ecriture('associations');
    foreach ($CHAMPS as $c) $saisi[$c] = trim((string)($_POST[$c] ?? ''));

    /* LES DEUX MOTS DE PASSE. Chiffrés par le même Crypto.php qui protège les
       IBAN et les AVS des fiches personnelles — un dump de cette base se lit
       sans clé, et l'on en produit un par jour qui part dans le Drive.

       Un champ laissé vide NE VIDE PAS le mot de passe enregistré: l'écran ne
       renvoie jamais le secret au navigateur, donc vide veut dire « je n'y ai
       pas touché » et non « efface ». Pour effacer, on écrit un espace. */
    $MDP = ['email_mdp', 'instagram_mdp'];
    foreach ($MDP as $m) {
        $brut = (string)($_POST[$m] ?? '');
        if ($brut === '') { unset($saisi[$m]); continue; }
        $saisi[$m] = trim($brut) === '' ? '' : Crypto::chiffrer(trim($brut));
    }

    if (($_POST['action'] ?? '') === 'supprimer' && $id > 0) {
        DB::pdo()->prepare('UPDATE organisation SET supprime_le = NOW() WHERE id = ?')->execute([$id]);
        dash_flash('Fiche supprimée. Elle reste en base et peut être rétablie.');
        redirect('/dashboard.php?e=associations');
    }

    if ($saisi['nom'] === '') $err['nom'] = 'Sans nom, la fiche ne se retrouve pas.';
    if (!isset($GENRES[$saisi['genre']]))     $saisi['genre']  = 'artiste';
    if (!isset($GESTIONS[$saisi['gestion'] ?? ''])) $saisi['gestion'] = 'complete';
    if (!isset($STATUTS[$saisi['statut']]))   $saisi['statut'] = 'actif';
    if ($saisi['devise_defaut'] === '')       $saisi['devise_defaut'] = 'CHF';

    if ($saisi['debut_collab'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $saisi['debut_collab'])) {
        $err['debut_collab'] = 'Format attendu: AAAA-MM-JJ.';
    }
    foreach (['frais_booking', 'marge_defaut'] as $p) {
        if ($saisi[$p] === '') continue;
        $saisi[$p] = str_replace([',', '%', ' '], ['.', '', ''], $saisi[$p]);
        if (!is_numeric($saisi[$p])) $err[$p] = 'Un pourcentage, en chiffres.';
    }
    /* L'IDE se saisit avec ou sans les points, et sur un formulaire on tape ce
       qu'on a sous les yeux. On normalise plutôt que de refuser. */
    if ($saisi['ide'] !== '') {
        $n = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $saisi['ide']));
        if (preg_match('/^CHE(\d{9})$/', $n, $m)) {
            $saisi['ide'] = 'CHE-' . substr($m[1],0,3) . '.' . substr($m[1],3,3) . '.' . substr($m[1],6,3);
        }
    }

    if (!$err) {
        /* Les champs que le POST n'a pas apportés — les mots de passe laissés
           vides — sortent de la mise à jour au lieu d'y entrer à NULL. Sans
           cela, ouvrir la fiche et enregistrer effacerait le mot de passe sans
           que personne ne l'ait demandé. */
        $cols = array_values(array_filter($CHAMPS, fn($c) => array_key_exists($c, $saisi)));
        $vals = array_map(fn($c) => $saisi[$c] === '' ? null : $saisi[$c], $cols);
        if ($id > 0) {
            $set = implode(',', array_map(fn($c) => "$c=?", $cols));
            DB::pdo()->prepare("UPDATE organisation SET $set WHERE id = ?")->execute([...$vals, $id]);
            dash_flash('Fiche enregistrée.');
        } else {
            $q = implode(',', array_fill(0, count($cols), '?'));
            DB::pdo()->prepare('INSERT INTO organisation (' . implode(',', $cols) . ") VALUES ($q)")
                     ->execute($vals);
            $id = (int)DB::pdo()->lastInsertId();
            dash_flash('Fiche créée.');
        }
        redirect('/dashboard.php?e=associations&o=' . $id);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// LE FORMULAIRE
// ═══════════════════════════════════════════════════════════════════════════

if (isset($_GET['mod']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $o = $id > 0 ? DB::one('SELECT * FROM organisation WHERE id = ? AND supprime_le IS NULL', [$id]) : [];
    if ($id > 0 && !$o) { dash_haut('associations'); echo '<p class="vide">Cette fiche n\'existe pas.</p>'; dash_bas(); return; }
    $v = fn(string $c) => $saisi[$c] ?? ($o[$c] ?? '');

    dash_haut('associations', $id > 0 ? 'modifier' : 'nouvelle fiche');
    dash_form_style();
    if ($err) echo '<div class="flash err">Rien n\'a été enregistré: ' . count($err)
                 . ' champ(s) à corriger. Ce que vous aviez saisi est conservé.</div>';
    ?>
    <div class="fil"><a href="/dashboard.php?e=associations<?= $id > 0 ? '&amp;o=' . $id : '' ?>">← retour</a></div>
    <?php /* Les boutons radio sont ici, AVANT le formulaire, pour que le CSS
             atteigne aussi les panneaux des grilles, qui sont des formulaires
             à part — le HTML interdit de les imbriquer. */
    $annee = (int)($_GET['an'] ?? date('Y'));
    if ($annee < 2000 || $annee > 2100) $annee = (int)date('Y');
    $ecrit = dash_droit('associations', dash_role()) === 'ecrit';
    require __DIR__ . '/_assoc_barre.php'; ?>
    <form class="saisie" method="post"
          action="/dashboard.php?e=associations<?= $id > 0 ? '&amp;o=' . $id : '' ?>&amp;mod=1&amp;an=<?= $annee ?>">
      <?= Auth::csrfField() ?>
      <?php require __DIR__ . '/_assoc_onglets.php'; ?>
      <div class="actions">
        <button type="submit"><?= $id > 0 ? 'Enregistrer' : 'Créer' ?></button>
        <a class="sec2" href="/dashboard.php?e=associations<?= $id > 0 ? '&amp;o=' . $id : '' ?>">annuler</a>
        <?php if ($id > 0): ?>
          <?php /* UN BOUTON DU FORMULAIRE, ET NON UN LIEN QUI EN FABRIQUE UN.
               [Anna, 22.08.2026] « corriger le bouton effacer ». Il fabriquait
               un formulaire en JavaScript dans un attribut `onclick`, et y
               collait le champ CSRF passé à `addslashes()`. Or `addslashes`
               échappe pour une chaîne JavaScript, pas pour un attribut HTML:
               les guillemets du champ fermaient l'attribut, et la fin du code
               s'affichait en rouge au bas de la page — « '; document.body.
               appendChild(f);f.submit();}return false;">supprimer ».

               C'est le troisième écran où ce même code est corrigé, après les
               contacts et les dates: celui-ci avait été manqué. Aucun de ces
               détours n'est nécessaire — le formulaire qui entoure ce bouton
               est déjà en POST et porte déjà son jeton. `formnovalidate` pour
               qu'un champ obligatoire vide n'empêche pas de supprimer. */ ?>
          <button type="submit" name="action" value="supprimer" class="sup" formnovalidate
                  onclick="return confirm('Supprimer cette fiche ? Elle restera en base.')">supprimer</button>
        <?php endif; ?>
      </div>
    </form>
    <?php require __DIR__ . '/_assoc_grilles.php'; ?>
    <style>
/* Un tiret pâle plutôt qu'une cellule vide: une case vide se lit comme
   « il n'y a rien à savoir », un tiret comme « ce n'est pas renseigné ». */
.rien{color:var(--doux);opacity:.45}.fil{padding:12px 26px 0;font-size:13px}.fil a{color:var(--doux);text-decoration:none}</style>
    <?php dash_bas(); return;
}

// ═══════════════════════════════════════════════════════════════════════════
// LA FICHE
// ═══════════════════════════════════════════════════════════════════════════

if ($id > 0) {
    $o = DB::one('SELECT * FROM organisation WHERE id = ? AND supprime_le IS NULL', [$id]);
    if (!$o) { dash_haut('associations'); echo '<p class="vide">Cette fiche n\'existe pas.</p>'; dash_bas(); return; }

    /* Les dates de cette organisation. C'est ce qui fait qu'une fiche sert:
       elle relie l'entité à ce qu'elle a joué. */
    /* ── LES DATES DE L'ASSOCIATION, PAR LE VRAI LIEN ───────────────────────
       [16.08.2026] Elle les cherchait en comparant des NOMS: `artiste = 'X' OR
       projet = 'X'`. Ça marchait par accident pour Encontro, dont l'association
       et l'artiste portent le même nom, et ne trouvait RIEN pour Gran
       Chicornia, dont les dates disent « Annina Mosimann ».

       Le lien existe depuis ce matin: `projet_prod.organisation_id` dit quelle
       association porte quelle pièce, et `booking.projet` porte le titre de la
       pièce. On passe donc par là. Une coïncidence de noms n'est pas un lien,
       et surtout: son absence n'est pas l'absence de dates.

       Le compte total est séparé de la liste — on n'affiche que douze, et dire
       « 12 » quand il y en a trente cacherait les dix-huit autres. */
    $st = DB::pdo()->prepare(
        "SELECT b.* FROM booking b
           JOIN projects p     ON p.title_fr = b.projet
           JOIN projet_prod pp ON pp.project_id = p.id
          WHERE b.supprime_le IS NULL AND pp.organisation_id = ?
          ORDER BY b.date_debut DESC LIMIT 12");
    $st->execute([$id]);
    $dates = $st->fetchAll();

    $st = DB::pdo()->prepare(
        "SELECT COUNT(*) FROM booking b
           JOIN projects p     ON p.title_fr = b.projet
           JOIN projet_prod pp ON pp.project_id = p.id
          WHERE b.supprime_le IS NULL AND pp.organisation_id = ?");
    $st->execute([$id]);
    $datesN = (int)$st->fetchColumn();

    dash_haut('associations', e($GENRES[$o['genre']]) . ' · ' . e($STATUTS[$o['statut']] ?? ''));
    ?>
    <div class="fil"><a href="/dashboard.php?e=associations">← toutes les fiches</a>
      <a class="mod" href="/dashboard.php?e=associations&amp;o=<?= $id ?>&amp;mod=1">modifier</a></div>
    <?php dash_flash_html(); ?>
    <div class="zone">
      <h2 class="gros"><?= e($o['nom']) ?></h2>

      <?php /* L'alerte « existe aussi comme artiste » est retirée. [16.08.2026]
           Anna: « porque crile tem a fiche artiste nas associacoes ? nao precisa
           ter ». Depuis que les artistes ne sont plus listés sur cet écran, elle
           renvoyait vers une fiche qu'on ne peut plus atteindre — un avertissement
           dont le lien mène nulle part est pire que pas d'avertissement. */ ?>

      <div class="fiche">
      <?php
      $l = function (string $k, $v, string $note = '') {
          if ($v === null || $v === '') return;
          printf('<div class="l"><span class="k">%s</span><span class="v">%s%s</span></div>',
                 e($k), e((string)$v), $note ? '<span class="n">'.e($note).'</span>' : '');
      };
      $l('Nom légal', $o['nom_legal']);
      $l('Discipline', $o['discipline']);
      $l('Direction artistique', $o['direction']);
      $l('Début de collaboration', $o['debut_collab']);
      $l('Ce que nous faisons', $GESTIONS[$o['gestion']] ?? $o['gestion']);
      $l('IDE', $o['ide']);
      $l('Registre', $o['registre']);
      $l('AVS employeur', $o['avs_employeur']);
      $l('REE', $o['ree']);
      $l('SIRET', $o['siret']);
      /* LES IDENTIFIANTS FRANÇAIS MANQUAIENT À LA FICHE. [Anna, 21.08.2026]
         « porque as fichas das assos baseadas na França não têm todos os
         campos de info ». Les quatre suisses — IDE, Registre, AVS employeur,
         REE — étaient là depuis le début; côté français seul le SIRET l'était.
         `rna`, `urssaf`, `audiens` et la TVA existent dans la table depuis la
         reprise et n'étaient imprimés nulle part: une association française
         pouvait donc être renseignée sans que rien ne le montre.

         Comme tout le reste de cette fiche, une ligne vide ne s'affiche pas:
         les treize fiches suisses ne gagnent aucune ligne creuse. */
      $l('RNA', $o['rna'], 'répertoire national des associations');
      $l('URSSAF', $o['urssaf']);
      $l('Audiens', $o['audiens'], 'retraite et prévoyance du spectacle');
      /* `tva_fr` EST UN enum('non','oui'), PAS UN BOOLÉEN. La chaîne « non »
         est vraie en PHP: testée comme un drapeau, elle affichait
         « assujettie » sur les treize fiches suisses, c'est-à-dire l'inverse
         de la vérité. On compare donc à « oui », jamais à la vérité de la
         valeur. */
      $l('TVA (FR)', $o['tva_fr_num'] ?: ($o['tva_fr'] === 'oui' ? 'assujettie' : ''));
      $l('Pays', trim(($o['pays'] ?? '') . ' ' . ($o['canton'] ? '· ' . $o['canton'] : '')));
      /* Quatre champs et non un. [16.08.2026] L'adresse arrivait de la reprise
         en un bloc multi-ligne et s'empilait dans une cellule prévue pour une
         ligne. Le « chez » est à part parce qu'il va sur l'enveloppe et jamais
         sur un devis. */
      $l('Chez', $o['chez']);
      $l('Adresse', $o['adresse']);
      $l('Code postal', $o['cp']);
      $l('Ville', $o['ville']);
      $l('Courriel', $o['email']);
      $l('Téléphone', $o['telephone']);
      $l('Site', $o['site']);
      $l('Instagram', $o['instagram']);
      $l('Banque', $o['banque_nom']);
      $l('IBAN', $o['banque_iban'], 'figure sur les devis');
      $l('BIC', $o['banque_bic']);
      $l('Devise par défaut', $o['devise_defaut']);
      if ($o['frais_booking'] !== null) $l('Frais de booking', $o['frais_booking'] . ' %');
      if ($o['marge_defaut'] !== null)  $l('Marge', $o['marge_defaut'] . ' %');
      $l('Provenance', $o['source'] . ' · ' . $o['source_ref']);
      ?>
      </div>

      <?php if ($o['comite']): ?>
        <div class="bl"><h3>Comité</h3><p><?= nl2br(e($o['comite'])) ?></p></div><?php endif; ?>
      <?php if ($o['notes']): ?>
        <div class="bl"><h3>Notes</h3><p><?= nl2br(e($o['notes'])) ?></p></div><?php endif; ?>

      <h3 class="sect">Dates <span class="n"><?= $datesN ?></span><?php
        if ($datesN > count($dates)): ?> <span class="sec">les <?= count($dates) ?> dernières</span><?php endif; ?></h3>
      <?php if (!$dates): ?>
        <p class="sec">Aucune date rattachée. Le rapprochement se fait sur le nom:
           si les dates portent une autre orthographe, elles n'apparaissent pas ici.</p>
      <?php else: ?>
      <div class="tw"><table><tbody>
        <?php foreach ($dates as $d): ?>
        <tr>
          <td><a href="/dashboard.php?e=bookings&amp;b=<?= (int)$d['id'] ?>"><?=
            e($d['date_texte'] ?: (string)$d['date_debut']) ?></a></td>
          <td><?= e($d['venue'] ?? '') ?></td>
          <td class="sec"><?= e($d['ville'] ?? '') ?></td>
          <td><span class="et <?= e($d['statut']) ?>"><?= e($d['statut']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody></table></div>
      <?php endif; ?>
    </div>
    <style>
    .fil{padding:12px 26px 0;font-size:13px;display:flex;gap:16px}
    .fil a{color:var(--doux);text-decoration:none}
    .fil a.mod{margin-left:auto;color:var(--encre);font-weight:600}
    h2.gros{font-size:21px;margin:0 0 16px}
    h3.sect{font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:var(--doux);
      margin:30px 0 8px;border-bottom:1px solid var(--trait);padding-bottom:5px}
    h3.sect .n{font-weight:400}
    /* Deux colonnes larges au lieu de trois étroites. [16.08.2026] Anna: « alargar
       as colunas, esta uma em cima da outra ». Avec `minmax(280px,1fr)` un écran
       large fabriquait une troisième colonne, et un IBAN ou une adresse s'y
       cassait sur quatre lignes. Deux colonnes de 420 px laissent passer la
       plupart des valeurs d'un trait. */
    .fiche{display:grid;grid-template-columns:repeat(auto-fill,minmax(420px,1fr));
      gap:0 40px;max-width:1240px}
    .fiche .k{min-width:150px}
    .fiche .v{overflow-wrap:anywhere}
    .fiche .l{display:flex;gap:12px;padding:7px 0;border-bottom:1px solid var(--trait)}
    .fiche .k{color:var(--doux);font-size:12.5px;min-width:150px}
    .fiche .v{font-size:14px}
    .fiche .n{color:var(--doux);font-size:12px;margin-left:8px}
    .bl{margin-top:22px;padding:12px 16px;background:var(--fond2);max-width:800px}
    .bl h3{font-size:13px;margin:0 0 6px}.bl p{margin:0;font-size:14px}
    .alerte{margin:0 0 18px;padding:11px 15px;background:var(--fond2);
      border-left:4px solid var(--orange);font-size:13.5px;max-width:80ch}
    .et{font-size:11px;padding:2px 8px;border-radius:10px;border:1px solid var(--trait)}

    /* ── LES GRILLES SE VOIENT SANS ENTRER EN ÉDITION ────────── [20.08.2026]
       Elles étaient construites depuis le 16.08 et invisibles: `_assoc_grilles`
       n'était inclus que dans la branche `mod=1`. Pour cocher une case T2 il
       fallait donc ouvrir « modifier », c'est-à-dire annoncer qu'on va changer
       la fiche entière alors qu'on vient marquer une déclaration envoyée.

       Or CLIQUER UNE CASE EST DÉJÀ UNE ÉCRITURE, et elle a son propre
       formulaire: le détour par l'édition n'ajoutait aucune sécurité, il
       ajoutait un geste. Anna l'a signalé deux fois.

       `.pane{display:block}` remis ici parce que `_assoc_barre.php` les cache
       toutes sauf celle de l'onglet coché — et sur cette page il n'y a pas
       d'onglets. Les quatre s'empilent donc, chacune sous son titre, ce qui
       est la bonne forme pour une page qu'on lit de haut en bas. */
    .logo-a{max-width:800px}
    .logo-a h3{margin:0 0 8px}
    .logo-a .n{color:var(--doux);font-size:12.5px;margin:0 0 10px}
    /* Le damier derrière le logo: sans lui, un PNG blanc sur fond blanc paraît
       vide et l'on croit que le dépôt a échoué. */
    .logo-vue{display:flex;gap:16px;align-items:flex-start;margin:0 0 12px}
    .logo-vue img{max-width:220px;max-height:110px;padding:8px;border:1px solid var(--trait);
      border-radius:6px;background-color:#fff;
      background-image:linear-gradient(45deg,#eee 25%,transparent 25%,transparent 75%,#eee 75%),
        linear-gradient(45deg,#eee 25%,transparent 25%,transparent 75%,#eee 75%);
      background-size:14px 14px;background-position:0 0,7px 7px}
    .bx{max-width:800px}
    .bx h3{margin:0 0 8px}
    .bx p{margin:0 0 10px;font-size:13.5px}
    .bx .n{color:var(--doux);font-size:12.5px}
    .bx code{font-size:12px;background:var(--papier);padding:1px 5px;border-radius:3px}
    .bx-ok{color:#1c5c2e}
    .bx-att{color:#8a6a00}
    .bx-h{margin:18px 0 8px;font-size:13px;padding-top:14px;border-top:1px solid var(--trait)}
    .bx-lien{color:var(--encre);font-size:12.5px}
    .bx-f2{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin:0 0 8px}
    .bx-f2 label{display:flex;flex-direction:column;gap:3px;font-size:11.5px;color:var(--doux);
      text-transform:uppercase;letter-spacing:.06em}
    .bx-f2 select{min-width:230px;max-width:340px;padding:7px 9px;font:inherit;font-size:13px;
      text-transform:none;letter-spacing:0;color:var(--encre);
      border:1px solid var(--trait);border-radius:5px}
    .bx-f2 button{padding:7px 15px;font:inherit;font-size:13px;font-weight:600;cursor:pointer;
      border:1px solid var(--encre);border-radius:5px;background:transparent;color:var(--encre)}
    .bx-f{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 10px}
    .bx-f input[type=password]{flex:1;min-width:220px;padding:7px 9px;font:inherit;font-size:13px;
      border:1px solid var(--trait);border-radius:5px;box-sizing:border-box}
    .bx-f button{padding:7px 15px;font:inherit;font-size:13px;font-weight:600;cursor:pointer;
      border:1px solid var(--encre);border-radius:5px;background:transparent;color:var(--encre)}
    .bx-f button:hover{background:var(--encre);color:#fff}
    .bx-vider{display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--doux)}
    .agenda-ics{max-width:800px}
    /* Sans box-sizing, un champ à 100 % ajoute ses 18 px de padding par-dessus
       et pousse la page à déborder — c'est ce débordement qui faisait glisser
       tout le contenu sous le menu. */
    .agenda-ics input{box-sizing:border-box}
    .agenda-ics p{margin:0 0 10px;font-size:13.5px;color:var(--doux)}
    .agenda-ics input{width:100%;padding:7px 9px;font:inherit;font-size:12.5px;
      border:1px solid var(--trait);background:#fff;color:var(--encre)}
    .agenda-ics .av{margin:10px 0 0;font-size:12px}
    .grilles-lect{margin-top:30px;padding-top:22px;border-top:2px solid var(--encre)}
    .grilles-lect .pane{display:block;margin-bottom:26px}
    .grilles-lect h3{font-size:13px;text-transform:uppercase;letter-spacing:.05em;
      color:var(--doux);margin:0 0 10px}
    </style>

    <?php
    /* `_assoc_grilles.php` attend ces trois-là. En lecture, `$ecrit` reste le
       droit réel: qui ne peut pas écrire voit les grilles sans les cliquer. */
    $ecrit = dash_droit('associations', dash_role()) === 'ecrit';
    $annee = (int)($_GET['an'] ?? date('Y'));
    if ($annee < 2000 || $annee > 2100) $annee = (int)date('Y');
    ?>
    <?php
    /* L'ADRESSE D'ABONNEMENT VIT ICI, PAS DANS L'AGENDA. [Anna, 21.08.2026]
       « mettre les adresses du calendrier dans les fiches des associations et
       pas dans l'agenda ». Elles étaient toutes empilées dans un dépliant du
       Calendrier: dix-huit lignes à parcourir pour trouver la bonne, sur un
       écran qui ne parle pas d'associations. Ici il n'y en a qu'une, et c'est
       celle qu'on regarde — l'adresse se copie sans chercher.

       Le jeton est le même pour toutes: c'est un jeton de lecture d'agenda,
       pas un secret par association. Le changer dans Paramètres coupe tous les
       abonnements d'un coup, ce qui est justement le geste voulu en cas de
       fuite. */
    $jetonAg = trim(setting('agenda_token'));
    if ($jetonAg === '') { $jetonAg = bin2hex(random_bytes(16)); Settings::set('agenda_token', $jetonAg); }
    $urlAg = rtrim((string)cfg('base_url', ''), '/') . '/agenda.php?t=' . $jetonAg . '&a=' . (int)$id;
    $nDates = (int)DB::val(
        "SELECT COUNT(*) FROM booking b
           JOIN projects p     ON p.title_fr = b.projet
           JOIN projet_prod pp ON pp.project_id = p.id
          WHERE b.supprime_le IS NULL AND pp.organisation_id = ?", [$id]);
    ?>
    <?php /* LES DEUX DERNIERS BLOCS ÉTAIENT HORS DE `.zone`, DONC SANS GOUTTIÈRE.
         [Anna, 21.08.2026] « ainda estamos assim ». La zone se refermait avant
         eux: le titre « Déclarations et pièces » commençait à zéro, c'est-à-dire
         sous la barre noire du menu, et la grille des trimestres s'étalait
         jusqu'au bord droit de la fenêtre en poussant la page à déborder — d'où
         l'impression que tout avait glissé vers la gauche.
         Une seconde zone plutôt qu'une marge posée à la main: c'est la même
         règle de 26 px que tous les autres écrans, et elle ne se réinvente pas. */ ?>
    <div class="zone">

    <div class="grilles-lect">
      <h3>Déclarations et pièces — <?= $annee ?></h3>
      <?php require __DIR__ . '/_assoc_grilles.php'; ?>
    </div>

    <?php /* LES DEUX BLOCS DE RÉGLAGE EN BAS DE PAGE. [Anna, 21.08.2026]
         « colocar a parte de conexão com bexio e o calendário no final da
         página ». Ils étaient entre la fiche et les déclarations, c'est-à-dire
         au milieu de ce qu'on vient lire: on ouvre une association pour voir
         ses coordonnées et ses obligations, pas pour coller un jeton. Un
         réglage se pose une fois et se relit rarement; sa place est après. */ ?>
    <?php /* LE LOGO DE L'ASSOCIATION. [Anna, 21.08.2026] « na ficha associação
         mettre un champ pour télécharger le logo de l'asso ».

         IL PASSE PAR LE MÊME MÉCANISME QUE LES ATTESTATIONS, et non par un
         second: même dépôt, même téléchargement protégé, même suppression.
         Ce qui change est qu'il n'a pas d'exercice — un logo n'existe pas
         « pour 2026 » — d'où la distinction `annuel` posée dans `OrgPieces`.

         IL S'AFFICHE, ET C'EST TOUT L'INTÉRÊT. Un fichier déposé qu'on ne voit
         pas ne se vérifie jamais: on découvre au moment d'imprimer un devis
         qu'on a chargé la mauvaise version, ou un carré blanc sur blanc. */ ?>
    <?php $logo = OrgPieces::liste($id, 'logo'); $logo = $logo[0] ?? null; ?>
    <div class="bl logo-a">
      <h3>Logo</h3>
      <?php if ($logo): ?>
        <div class="logo-vue">
          <img src="/dashboard.php?e=associations&amp;piece_dl=<?= (int)$logo['id'] ?>&amp;vue=1"
               alt="Logo de <?= e((string)$o['nom']) ?>">
          <div>
            <p class="n"><?= e($logo['fichier']) ?> ·
               <?= number_format((int)$logo['taille'] / 1024, 0, ',', ' ') ?> Ko ·
               déposé le <?= e(date('d.m.Y', strtotime((string)$logo['cree_le']))) ?></p>
            <form method="post" action="/dashboard.php?e=associations&amp;o=<?= $id ?>"
                  onsubmit="return confirm('Retirer le logo ? Le fichier est supprimé.')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="piece" value="retirer">
              <input type="hidden" name="ligne" value="<?= (int)$logo['id'] ?>">
              <button type="submit" class="sup">retirer</button>
            </form>
          </div>
        </div>
      <?php else: ?>
        <p class="n">Aucun logo. Il sert sur les devis et les factures de cette association.</p>
      <?php endif; ?>
      <form method="post" action="/dashboard.php?e=associations&amp;o=<?= $id ?>"
            enctype="multipart/form-data" class="ajl">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="piece" value="deposer">
        <input type="hidden" name="type" value="logo">
        <label class="fic">Fichier <input type="file" name="fichier" accept=".jpg,.jpeg,.png" required></label>
        <button type="submit"><?= $logo ? 'remplacer' : 'déposer' ?></button>
      </form>
      <p class="n">PNG ou JPG, 25 Mo au maximum. Le PNG garde la transparence, ce qui
         compte sur un devis. Ni PDF ni SVG: le premier n'est pas une image, le second
         est un document exécutable qu'on ne sert pas.</p>
    </div>

    <?php /* LE JETON BEXIO, SUR LA FICHE DE L'ASSOCIATION QU'IL OUVRE.
         [Anna, 21.08.2026] « fazemos o api avec bexio ? »

         PAS D'OAUTH2: la documentation de bexio dit « Personal Access Tokens
         allow server-to-server connections without the consent flow ». Un
         jeton collé suffit, et il ne se périme pas tout seul.

         LE NOM DE LA SOCIÉTÉ EST AFFICHÉ, ET C'EST LE POINT. Un jeton qui
         répond n'est pas forcément celui de la bonne comptabilité, et une
         facture émise chez la mauvaise association ne s'annule pas d'un
         bouton: elle porte un numéro et demande une note de crédit. On voit
         donc chez qui l'on est avant d'écrire quoi que ce soit. */ ?>
    <div class="bl bx" id="bx">
      <h3>bexio</h3>
      <?php $bxOk = Bexio::configure($o); $bxCompta = ($o['gestion'] ?? 'complete') !== 'diffusion'; ?>
      <?php if (!$bxCompta && !$bxOk): ?>
        <p class="n">Nous ne tenons pas la comptabilité de cette association: elle n'a pas
           de jeton, et c'est normal. Pour en poser un, changez d'abord
           « Ce que nous faisons » dans <a href="/dashboard.php?e=associations&amp;o=<?= $id ?>&amp;mod=1">modifier</a>.</p>
      <?php else: ?>
      <?php if ($bxOk && $o['bexio_societe']): ?>
        <p class="bx-ok">Jeton en place · comptabilité <strong><?= e((string)$o['bexio_societe']) ?></strong>
          <span class="n">essayé le <?= e(date('d.m.Y à H:i', strtotime((string)$o['bexio_teste_a']))) ?></span></p>
      <?php elseif ($bxOk): ?>
        <p class="bx-att">Jeton en place, jamais essayé. Essayez-le avant de vous y fier.</p>
      <?php else: ?>
        <p class="n">Aucun jeton. Dans bexio: <strong>Réglages → Interfaces → API</strong>,
           créer un <em>Personal Access Token</em> avec les portées
           <code>kb_invoice_edit</code> et <code>contact_edit</code>, puis le coller ici.</p>
      <?php endif; ?>

      <form method="post" action="/dashboard.php?e=associations&amp;o=<?= $id ?>" class="bx-f">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="bx" value="poser">
        <input type="password" name="bx_jeton" autocomplete="off"
               placeholder="<?= $bxOk ? 'coller un nouveau jeton pour remplacer' : 'coller le jeton ici' ?>">
        <button type="submit">enregistrer</button>
        <?php if ($bxOk): ?>
          <label class="bx-vider"><input type="checkbox" name="bx_vider" value="1"> retirer le jeton</label>
        <?php endif; ?>
      </form>
      <?php if ($bxOk): ?>
        <form method="post" action="/dashboard.php?e=associations&amp;o=<?= $id ?>" class="bx-f">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="bx" value="essai">
          <button type="submit">essayer la connexion</button>
          <span class="n">Demande à bexio de qui est cette comptabilité. Rien n'est écrit là-bas.</span>
        </form>
      <?php endif; ?>
      <p class="n">Le jeton est chiffré dans la base, comme les IBAN. Il n'est jamais réaffiché:
         un champ laissé vide ne l'efface pas.</p>

      <?php /* OÙ VA L'ARGENT D'UNE FACTURE, ET AVEC QUELLE TAXE. Une position
           de facture bexio exige un compte et un taux, et ces identifiants
           n'ont de sens que dans CETTE comptabilité: le « 3404 Cachet
           spectacle » du Voisin CH porte le numéro 246, celui d'une autre
           association en portera un autre. Les coder en dur écrirait dans le
           mauvais compte sans que rien ne proteste.

           LES LISTES NE SE CHARGENT QUE QUAND ON CONFIGURE. Les appeler à
           chaque ouverture de fiche ajouterait deux allers-retours vers bexio
           pour une information qui ne change jamais. Le réglage déjà posé se
           relit depuis la base, sans appel. */ ?>
      <?php $bxConf = ($_GET['bx_conf'] ?? '') === '1'; ?>
      <h4 class="bx-h">Compte et taxe des factures</h4>
      <?php if ($o['bexio_compte'] && !$bxConf): ?>
        <p class="bx-ok"><?= e((string)$o['bexio_compte_nom']) ?><br>
           <span class="n"><?= e((string)$o['bexio_taxe_nom']) ?></span></p>
        <p><a class="bx-lien" href="/dashboard.php?e=associations&amp;o=<?= $id ?>&amp;bx_conf=1#bx">changer</a></p>
      <?php elseif (!$bxConf): ?>
        <p class="bx-att">Pas encore choisis. Une facture ne peut pas partir sans eux.</p>
        <p><a class="bx-lien" href="/dashboard.php?e=associations&amp;o=<?= $id ?>&amp;bx_conf=1#bx">choisir</a></p>
      <?php else: ?>
        <?php $lc = Bexio::comptes($o); $lt = Bexio::taxes($o); ?>
        <?php if (!$lc): ?>
          <p class="bx-att">bexio n’a renvoyé aucun compte de produit. Essayez la connexion.</p>
        <?php else: ?>
        <form method="post" action="/dashboard.php?e=associations&amp;o=<?= $id ?>" class="bx-f2">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="bx" value="comptes">
          <label>Compte de produit
            <select name="bx_compte" required>
              <option value="">— choisir —</option>
              <?php foreach ($lc as $x): ?>
                <option value="<?= (int)$x['id'] ?>"<?= (int)$o['bexio_compte'] === $x['id'] ? ' selected' : '' ?>><?=
                  e($x['libelle']) ?></option>
              <?php endforeach; ?>
            </select></label>
          <label>Taxe
            <select name="bx_taxe" required>
              <option value="">— choisir —</option>
              <?php foreach ($lt as $x): ?>
                <option value="<?= (int)$x['id'] ?>"<?= (int)$o['bexio_taxe'] === $x['id'] ? ' selected' : '' ?>><?=
                  e($x['libelle']) ?></option>
              <?php endforeach; ?>
            </select></label>
          <button type="submit">enregistrer</button>
        </form>
        <p class="n"><?= count($lc) ?> comptes de produit et <?= count($lt) ?> taxes actives,
           lus chez bexio à l’instant. Seuls les comptes 3xxx sont proposés: une recette ne
           se pose pas sur un compte de charge.</p>
        <?php endif; ?>
      <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="bl agenda-ics">
      <h3>Agenda Google — <?= $nDates ?> date<?= $nDates > 1 ? 's' : '' ?></h3>
      <p>Dans Google Agenda: <strong>Autres agendas → + → À partir de l'URL</strong>, puis
         collez cette adresse. L'agenda se met à jour tout seul, en lecture seule, et se
         décoche comme n'importe quel autre.</p>
      <input type="text" readonly value="<?= e($urlAg) ?>" onclick="this.select()">
      <p class="av"><strong>Cette adresse est une clef.</strong> Qui l'a voit les dates de
         cette association, sans mot de passe — c'est ainsi que Google les lit. Elle ne porte
         ni prix, ni client, ni note interne, seulement la date, le spectacle et le lieu. Si
         elle fuite, changez le jeton dans Paramètres: tous les abonnements se coupent d'un
         coup et se recollent avec la nouvelle adresse.</p>
    </div>

    </div><!-- .zone -->

    <?php dash_bas(); return;
}

// ═══════════════════════════════════════════════════════════════════════════
// LA LISTE
// ═══════════════════════════════════════════════════════════════════════════

$q      = trim((string)($_GET['q'] ?? ''));
$genre  = trim((string)($_GET['g'] ?? ''));
$statut = trim((string)($_GET['st'] ?? ''));

/* ── L'ÉCRAN NE PORTE PLUS QUE LES ASSOCIATIONS ─────────────────────────────
   [16.08.2026] Anna: « na pagina Associations et artistes tirar os artistas ».

   Les lignes `genre = 'artiste'` venaient d'une reprise: le dashboard et le CMS
   du site ont chacun une liste d'artistes, et l'import les avait versées ici à
   côté des associations. Elles n'y avaient rien à faire — un artiste n'est pas
   une entité juridique, il n'a ni IDE, ni AVS employeur, ni régime fiscal, et
   les cinq onglets de conformité lui demandaient tout cela pour rien.

   ELLES NE SONT PAS SUPPRIMÉES, elles ne sont plus affichées. En production il
   n'y en a aucune de toute façon — mesuré: 15 lignes, toutes des associations.
   Les artistes vivent dans `artists`, côté site, où ils ont leur page publique. */
$where = ['supprime_le IS NULL', "genre = 'association'"]; $args = [];
if (isset($STATUTS[$statut])) { $where[] = 'statut = ?'; $args[] = $statut; }
if ($q !== '') {
    $like = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
    $where[] = '(nom LIKE ? OR nom_legal LIKE ? OR discipline LIKE ? OR direction LIKE ? OR ide LIKE ?)';
    array_push($args, $like, $like, $like, $like, $like);
}
$sql = implode(' AND ', $where);

$t0 = microtime(true);
$st = DB::pdo()->prepare("SELECT * FROM organisation WHERE $sql ORDER BY nom");
$st->execute($args);
$lignes = $st->fetchAll();
$ms = (int)round((microtime(true) - $t0) * 1000);

$parGenre = DB::pdo()->query("SELECT genre, COUNT(*) n FROM organisation
                               WHERE supprime_le IS NULL GROUP BY genre")->fetchAll(PDO::FETCH_KEY_PAIR);

dash_haut('associations', count($lignes) . ' fiche' . (count($lignes)>1?'s':'') . ' · ' . $ms . ' ms');
?>
<?php /* LES FILTRES QUE LE MENU DE COLONNE REMPLACE SONT PARTIS.
     [Anna, 21.08.2026] « este tipo de filtro acaba de colocar os outros
     filtros em desuso, pode tirar ». Statut, phase, type, et la recherche
     libre: toutes ces colonnes ont leur menu, avec en plus le compte de
     chaque valeur et le tri.

     CE QUI RESTE N'EST PAS UN FILTRE, C'EST UN CHOIX DE JEU DE DONNÉES. Le
     menu de colonne ne voit que les lignes déjà rendues; ce qui décide
     LESQUELLES sont rendues doit rester. Le retirer ne masquerait pas des
     lignes, il les rendrait inatteignables. */ ?>
<p class="barre-neuf"><a class="neuf" href="/dashboard.php?e=associations&amp;mod=1">+ nouvelle fiche</a></p>
<?php dash_flash_html(); ?>

<?php if (!$lignes): ?><p class="vide">Aucune fiche.</p><?php else: ?>
<?php require __DIR__ . '/_filtre_colonnes.php'; ?>
<div class="tw"><table data-filtres>
  <?php /* ── LES CINQ COLONNES ─────────────────────────────────────────────
       [16.08.2026] Choisies par Anna: nom, direction, ville et canton,
       discipline, statut.

       L'IDE SORT DE LA LISTE. C'est un numéro à douze chiffres qu'on ne lit pas
       en balayant une colonne: on le cherche quand on remplit un formulaire, et
       on l'a alors sous les yeux dans la fiche. Une colonne qu'on ne lit jamais
       prend la largeur de celles qu'on lit.

       LA VILLE REMPLACE LE PAYS, avec le canton à côté. « Suisse » sur treize
       lignes sur quinze n'apprend rien; « Genève GE » situe l'association, et
       c'est le canton qui décide de l'impôt à la source. */ ?>
  <?php /* LE PAYS REPREND SA COLONNE. [17.08.2026] Anna: « nome + direction +
       ville, canton + pays + discipline + statut ».

       Il en était sorti le 16.08 avec l'argument que « Suisse » sur treize
       lignes sur quinze n'apprend rien. L'argument ne tient plus: le carnet
       porte maintenant le Brésil, le Portugal et la France, et c'est le pays
       qui décide des obligations sociales et de l'attestation A1. Une colonne
       muette quatorze fois sur dix-sept mérite quand même sa place quand les
       trois autres fois changent le travail. */ ?>
  <?php /* Ce qui a peu de valeurs passe en liste — pays, discipline, statut,
       canton se choisissent plus vite qu'ils ne se tapent. Le nom et la
       direction restent en texte: dix-huit noms propres ne font pas une liste
       qu'on parcourt. [Anna, 21.08.2026] */ ?>
  <thead><tr><th>Nom</th><th>Direction</th><th>Ville, canton</th>
    <th>Pays</th>
    <th>Discipline</th><th>Statut</th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $r): ?>
    <tr>
      <?php /* Le nom légal ne suit plus le nom. [16.08.2026] Anna: « esta doublon
           os noms das assos, deixa so com o lien, o nome em baixo nao serve para
           nada ». « CRILE » et « Association CRILE » sur deux lignes disent la
           même chose et doublent la hauteur de chaque ligne. Le nom légal reste
           dans la fiche, où il sert — sur un contrat. */ ?>
      <td><a href="/dashboard.php?e=associations&amp;o=<?= (int)$r['id'] ?>"><?= e($r['nom']) ?></a></td>
      <td class="sec"><?= e($r['direction'] ?? '') ?></td>
      <?php /* Ville et canton dans la même cellule — ils se lisent ensemble,
           « Genève GE » — et le pays dans la sienne, depuis le 17.08. */ ?>
      <td class="sec"><?php
        $lieu = trim((string)($r['ville'] ?? ''));
        if (($r['canton'] ?? '') !== '') $lieu = trim($lieu . ($lieu !== '' ? ' ' : '') . $r['canton']);
        echo $lieu !== '' ? e($lieu) : '<span class="rien">—</span>';
      ?></td>
      <td class="sec"><?= ($r['pays'] ?? '') !== ''
            ? e((string)$r['pays']) : '<span class="rien">—</span>' ?></td>
      <td class="sec"><?= ($r['discipline'] ?? '') !== ''
            ? e((string)$r['discipline']) : '<span class="rien">—</span>' ?></td>
      <td><span class="et s-<?= e($r['statut']) ?>"><?= e($STATUTS[$r['statut']] ?? '') ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>

<style>
/* Le bloc des pièces annuelles, sous la grille trimestrielle. [18.08.2026] */
.grille-h{margin:26px 0 10px}
.grille-h h4{margin:0 0 4px;font-size:14px}
.grille-h .alerte{color:var(--orange)}
form.piece-f{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin:12px 0 6px}
form.piece-f label{display:flex;flex-direction:column;gap:4px;font-size:11.5px;font-weight:700;
  text-transform:uppercase;letter-spacing:.07em;color:var(--doux)}
form.piece-f input,form.piece-f select{padding:7px 9px;font:inherit;font-size:14px;font-weight:400;
  text-transform:none;letter-spacing:0;color:var(--encre);border:1px solid var(--trait);
  border-radius:5px;background:var(--papier)}
form.piece-f label.nt{flex:1 1 220px}
form.piece-f label.nt input{width:100%}
form.piece-f button{padding:8px 16px;font-size:13.5px}
.grille-h tr.ici td{background:#fffbe9}
.grille-h button.x{background:none;border:0;color:var(--orange);text-decoration:underline;
  cursor:pointer;font-family:inherit;font-size:13px;padding:0}

.alerte{margin:16px 26px 0;padding:11px 16px;background:var(--fond2);
  border-left:4px solid var(--orange);font-size:13.5px;max-width:82ch}
.et{font-size:11px;padding:2px 8px;border-radius:10px;border:1px solid var(--trait);white-space:nowrap}
.et.s-actif{background:#e7f6ea;border-color:#bfe3c8;color:#1c5c2e}
.et.s-pause{background:#fff6d9;border-color:#f0dfa3;color:#6b5312}
.et.s-termine{background:var(--fond2);color:var(--doux)}
</style>
<?php dash_bas(); ?>

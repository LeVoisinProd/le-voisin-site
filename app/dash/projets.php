<?php
/**
 * Écran Projets. [16.08.2026]
 *
 * LE PARTAGE DU TRAVAIL, ET C'EST LA DÉCISION LA PLUS STRUCTURANTE DE L'ÉCRAN.
 *
 * Un spectacle se saisissait à trois endroits: `projects` du CMS, `lv-prods` et
 * `lv-fiches` du dashboard. Anna: « on ne veut pas travailler en double ».
 *
 * Cet écran n'ajoute donc PAS une quatrième fiche. Il lit et écrit `projects`,
 * la table du CMS, et lui ajoute par-dessus la couche `projet_prod` qui porte ce
 * que le CMS n'a pas: phase, responsable, budget, validation.
 *
 *   ce que le CMS porte      titre, textes, images, catégories, ce qui est publié
 *   ce que ce dashboard ajoute  phase, responsable, budget, porteur juridique
 *   ce qui est commun        la même ligne, le même identifiant
 *
 * Le sens de la dépendance qu'Anna décrit se met ainsi en place tout seul: la
 * fiche devient la source et le site la lit, sans qu'aucune donnée ne soit
 * recopiée. L'édition des textes et des images reste pour l'instant dans
 * l'administration du site, et le lien y renvoie: la déplacer ici est un autre
 * chantier, et le faire à moitié rouvrirait la double saisie.
 */
declare(strict_types=1);

$PHASES = ['dev'=>'développement','creation'=>'création','production'=>'production',
           'promo'=>'promotion','tournee'=>'tournée','cloture'=>'clôturé'];

$id = (int)($_GET['p'] ?? 0);

// ═══════════════════════════════════════════════════════════════════════════
// ENREGISTRER la couche production
// ═══════════════════════════════════════════════════════════════════════════

$CHAMPS = ['phase','responsable','valide_par','budget','devise','organisation_id',
           'lieu_creation','notes'];
$err = $saisi = [];

/* ══ LA FICHE DE PRODUCTION, ses neuf onglets. [16.08.2026] ══════════════
   Ouverte par ?p=<id du spectacle du CMS>. Elle est traitée avant tout le
   reste de cet écran, y compris les POST de la liste: elle a ses propres
   actions et ne partage rien avec eux. */
$pcms = (int)($_GET['p'] ?? 0);
if ($pcms > 0) {
    $p = DB::one('SELECT * FROM projects WHERE id = ?', [$pcms]);
    if (!$p) { dash_haut('projets'); echo '<p class="vide">Ce spectacle n\'existe pas.</p>'; dash_bas(); return; }

    $onglet = preg_replace('/[^a-z]/', '', strtolower((string)($_GET['o'] ?? 'synthese')));
    $retour = '/dashboard.php?e=projets&p=' . $pcms . '&o=' . $onglet;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pf'] ?? '') !== '') {
        Auth::requireCsrf();
        dash_exige_ecriture('projets');
        $act = (string)$_POST['pf'];

        if ($act === 'champs') {
            /* Les champs libres de la fiche, vérifiés un à un contre le modèle:
               une clef inconnue est refusée, parce qu'un JSON n'a pas de schéma
               pour s'en défendre tout seul. */
            $n = 0;
            foreach ((array)($_POST['v'] ?? []) as $chemin => $val) {
                if (ProdFiche::champ($pcms, (string)$chemin, (string)$val)) $n++;
            }
            /* Les colonnes typées de projet_prod ne passent pas par le JSON. */
            $pr = (array)($_POST['prod'] ?? []);
            $maj = [];
            if (isset($pr['phase']) && in_array($pr['phase'], ['dev','creation','production','promo','tournee','cloture'], true)) {
                $maj['phase'] = $pr['phase'];
            }
            foreach (['responsable','valide_par','lieu_creation'] as $c) {
                if (isset($pr[$c])) $maj[$c] = mb_substr(trim((string)$pr[$c]), 0, 190) ?: null;
            }
            if (isset($pr['notes'])) $maj['notes'] = trim((string)$pr['notes']) ?: null;
            if (isset($pr['devise']) && in_array($pr['devise'], ['CHF','EUR'], true)) $maj['devise'] = $pr['devise'];
            if (isset($pr['organisation_id'])) $maj['organisation_id'] = (int)$pr['organisation_id'] ?: null;
            if (isset($pr['budget'])) {
                /* « 12 000 », « 12'000 » et « 12,5 » arrivent tous les trois. Ce
                   qui ne se lit pas comme un nombre est ignoré plutôt qu'écrit à
                   zéro: un budget effacé par une virgule serait pire que rien. */
                $b = str_replace([',', ' ', "'", ' '], ['.', '', '', ''], trim((string)$pr['budget']));
                $maj['budget'] = $b === '' ? null : (is_numeric($b) ? (float)$b : null);
                if ($b !== '' && !is_numeric($b)) unset($maj['budget']);
            }
            if ($maj) { ProdFiche::ligne($pcms); DB::update('projet_prod', $maj, 'project_id = ?', [$pcms]); }

            /* ── CE QUI APPARTIENT AU CMS S'ÉCRIT DANS LE CMS.  [Anna, 22.08.2026] ──
               « esta parte do projeto tem que ser a página de onde saem todas as
               infos sobre o projeto, é a fonte ».

               ON NE RECOPIE RIEN, ON ÉCRIT À LA SOURCE. Le titre, l'année, la
               durée et le public vivent dans `projects`, la table que le site
               public lit pour son catalogue. Les dupliquer dans `projet_prod`
               aurait fait deux vérités et, au premier écart, personne pour dire
               laquelle est la bonne. Conséquence assumée et dite à Anna: ce qui
               se change ici change la page publique dans l'instant.

               LE SLUG NE BOUGE PAS. Il porte l'adresse publique de la fiche; le
               refaire à chaque changement de titre casserait tous les liens déjà
               partagés, y compris ceux qui sont dans des dossiers envoyés.

               LE RENOMMAGE EMPORTE LES DATES ET LES OFFRES AVEC LUI, et c'est la
               seule partie délicate: `booking.projet` et `offer.projet` portent
               le TITRE en clair, pas l'identifiant. Mesuré avant d'écrire une
               ligne: 71 des 86 dates se rattachent à leur pièce par ce texte.
               Renommer sans les suivre les aurait détachées en silence — la
               fiche association aurait cessé d'afficher les dates, sans erreur
               nulle part. */
            $cm = (array)($_POST['cms'] ?? []);
            if ($cm) {
                $mc  = [];
                $ancienTitre = (string)$p['title_fr'];

                foreach (['title_fr', 'title_en'] as $c) {
                    if (!isset($cm[$c])) continue;
                    $v = mb_substr(trim((string)$cm[$c]), 0, 255);
                    /* Un titre français vide rendrait la pièce introuvable et
                       détacherait ses dates. On refuse plutôt que d'obéir. */
                    if ($c === 'title_fr' && $v === '') continue;
                    $mc[$c] = $v ?: null;
                }
                if (isset($cm['year_creation'])) {
                    $a = (int)preg_replace('/\D/', '', (string)$cm['year_creation']);
                    $mc['year_creation'] = ($a >= 1900 && $a <= 2100) ? $a : null;
                }
                if (isset($cm['duration_min'])) {
                    /* « 75 », « 75 min » et « 1h15 » arrivent tous les trois. Les
                       deux premiers se lisent; le troisième est laissé tel quel
                       plutôt qu'écrit à 1 — l'aide du champ dit d'écrire des
                       minutes, et une durée fausse voyage jusqu'au contrat. */
                    $t = trim((string)$cm['duration_min']);
                    $mc['duration_min'] = $t === '' ? null
                        : (preg_match('/^\s*(\d{1,4})\s*(min)?\s*$/i', $t, $mm) ? (int)$mm[1] : null);
                    if ($t !== '' && $mc['duration_min'] === null) unset($mc['duration_min']);
                }
                if (isset($cm['public_cible'])) {
                    $v = (string)$cm['public_cible'];
                    if (in_array($v, ['', 'young', 'all', 'adult'], true)) $mc['public_cible'] = $v;
                }

                if ($mc) {
                    $pdo = DB::pdo();
                    $pdo->beginTransaction();
                    try {
                        DB::update('projects', $mc + ['updated_at' => date('Y-m-d H:i:s')],
                                   'id = ?', [$pcms]);
                        $nouveau = $mc['title_fr'] ?? $ancienTitre;
                        if ($nouveau !== $ancienTitre && $ancienTitre !== '') {
                            $st = $pdo->prepare('UPDATE booking SET projet = ? WHERE projet = ?');
                            $st->execute([$nouveau, $ancienTitre]);
                            $nb = $st->rowCount();
                            $st = $pdo->prepare('UPDATE offer SET projet = ? WHERE projet = ?');
                            $st->execute([$nouveau, $ancienTitre]);
                            $no = $st->rowCount();
                            $pdo->commit();
                            dash_flash('Enregistré. Le titre a changé: ' . $nb . ' date(s) et '
                                     . $no . ' offre(s) suivent, et la page publique du site aussi.');
                            redirect($retour);
                        }
                        $pdo->commit();
                    } catch (Throwable $ex) {
                        $pdo->rollBack();
                        throw $ex;
                    }
                }
            }

            dash_flash($n || $maj || !empty($mc) ? 'Enregistré.' : 'Rien à enregistrer.');

        } elseif ($act === 'liste_ajouter') {
            $l = array_map(fn($x) => mb_substr(trim((string)$x), 0, 500), (array)($_POST['l'] ?? []));

            /* LA LOGISTIQUE PROPOSE DEUX FAÇONS DE DIRE LA MÊME CHOSE, et une
               seule est enregistrée. [Anna, 22.08.2026] « Quand » se choisit
               dans les étapes du planning OU se pose sur une date; « Qui » se
               prend dans l'équipe de la pièce OU s'écrit à la main.

               LA LISTE L'EMPORTE SUR LA SAISIE LIBRE, et pas l'inverse: qui a
               choisi dans un menu l'a fait exprès, alors qu'un champ resté
               rempli d'un essai précédent part sans qu'on le relise. Les deux
               champs auxiliaires ne sont jamais gardés — la fiche imprime
               « Quand » et « Qui » tels quels et n'a que faire du chemin par
               lequel ils sont arrivés. */
            if (($l['quand'] ?? '') === '' && ($l['quand_date'] ?? '') !== '') {
                $t = strtotime((string)$l['quand_date']);
                $l['quand'] = $t ? date('d.m.Y', $t) : (string)$l['quand_date'];
            }
            if (($l['qui_choix'] ?? '') !== '') $l['qui'] = (string)$l['qui_choix'];
            unset($l['quand_date'], $l['qui_choix']);

            /* ── UNE PERSONNE AJOUTÉE ARRIVE AVEC LES JOURNÉES DU PLANNING ──
               [Anna, 22.08.2026] « no dashboard já estava tudo automatizado:
               quando incluíamos uma pessoa ele já pré-preenchia as datas que
               estavam preenchidas no planning do projeto ».

               ON COCHE TOUT, ET ON DÉCOCHE ENSUITE. C'est le sens de marche qui
               demande le moins de gestes: la plupart des gens font la période
               entière, et ceux qui n'en font qu'une partie sont l'exception. Le
               contraire — tout décoché — obligerait à cliquer trois cases pour
               le cas courant et laisserait des lignes à zéro jour que personne
               ne remarque.

               SI LE PLANNING EST VIDE, RIEN N'EST COCHÉ et le champ « Jours »
               reste saisissable à la main: on n'invente pas des dates. */
            $ouAj = (string)($_POST['ou'] ?? '');
            if ($ouAj === 'remuneration' && ($l['jours_dates'] ?? '') === '') {
                $jj = ProdFiche::toutesLesJournees(ProdFiche::donnees($pcms));
                if ($jj) {
                    $l['jours_dates'] = implode(',', $jj);
                    if (trim((string)($l['jours'] ?? '')) === '') $l['jours'] = (string)count($jj);
                }
            }
            ProdFiche::ajouter($pcms, $ouAj, $l);
            dash_flash('Ligne ajoutée.');

        } elseif ($act === 'liste_retirer') {
            ProdFiche::retirer($pcms, (string)($_POST['ou'] ?? ''), (string)($_POST['ligne'] ?? ''));
            dash_flash('Ligne retirée.');

        } elseif ($act === 'liste_modifier') {
            /* [17.08.2026] Ajoutée pour l'équipe du devis, qui se corrige ligne
               par ligne — un tarif, un nombre de jours. Sans elle il fallait
               retirer et ressaisir, ce qu'on ne fait pas deux fois avant de
               laisser tomber.

               Le nom du champ n'est pas vérifié contre un modèle, à la
               différence de `champs`: une ligne de liste n'a pas de forme
               déclarée — un voyage, un poste de budget et une personne n'ont
               pas les mêmes clefs. Ce qui borne l'écriture c'est `ou`, que
               `ProdFiche::modifier` résout contre les listes existantes et qui
               refuse tout chemin inconnu, plus le motif ci-dessous qui écarte
               les noms de champ fabriqués. */
            /* UN GROUPE DE CASES ARRIVE EN TABLEAU.  [22.08.2026] Les journées
               travaillées d'une personne sont cochées une à une et repartent
               donc en `l[jours_dates][]`. On les recolle par des virgules: une
               liste de dates dans une chaîne se relit sans schéma, et le reste
               du code n'a rien à apprendre. */
            $n   = 0;
            $ou  = (string)($_POST['ou'] ?? '');
            $lig = (string)($_POST['ligne'] ?? '');
            $vus = [];
            foreach ((array)($_POST['l'] ?? []) as $champ => $val) {
                if (!preg_match('/^[a-z_]{1,24}$/', (string)$champ)) continue;
                /* On jette les vides avant de recoller: le formulaire porte un
                   champ vide en tête, pour que tout décocher envoie quand même
                   la clef. Sans ce filtre la valeur stockée commencerait par une
                   virgule — sans conséquence, mais illisible à qui la relit. */
                $v = is_array($val)
                    ? implode(',', array_filter(array_map(fn($x) => trim((string)$x), $val),
                                                fn($x) => $x !== ''))
                    : trim((string)$val);
                ProdFiche::modifier($pcms, $ou, $lig, (string)$champ, mb_substr($v, 0, 500));
                $vus[] = (string)$champ;
                $n++;
            }

            /* LE NOMBRE DE JOURS N'EST PLUS TAPÉ, IL EST COMPTÉ. Le laisser à la
               main à côté des cases donnerait deux vérités sur le même écran, et
               c'est celle qu'on ne regarde pas qui part dans le contrat. Le
               décocher d'un jour doit donc le faire descendre tout seul.
               Une case décochée n'envoie rien: `jours_dates` absent du POST veut
               dire « aucune », et c'est pour cela que le formulaire porte un
               champ vide en tête. */
            if ($ou === 'remuneration' && in_array('jours_dates', $vus, true)) {
                $dd = ProdFiche::donnees($pcms);
                foreach (($dd['remuneration'] ?? []) as $r) {
                    if (($r['id'] ?? '') !== $lig) continue;
                    $j = array_filter(explode(',', (string)($r['jours_dates'] ?? '')));
                    ProdFiche::modifier($pcms, $ou, $lig, 'jours', (string)count($j));
                    break;
                }
            }
            dash_flash($n ? 'Ligne enregistrée.' : 'Rien à enregistrer.');

        } elseif ($act === 'devis_defauts') {
            /* Remplit le bloc devis avec les valeurs du Bestiarium — Anna:
               « voce pode pegar os valores diarios iguais aos de bestiarium ».
               N'ÉCRASE RIEN: si une équipe est déjà saisie on ne la remplace
               pas, on le dit. Le geste est fait pour une fiche vide. */
            $d = ProdFiche::donnees($pcms);
            if (!empty($d['devis']['equipe'])) {
                dash_flash('Cette fiche porte déjà une équipe de devis: rien n\'a été touché.', 'err');
            } else {
                $d['devis'] = ProdFiche::devisDefaut();
                ProdFiche::ecrire($pcms, $d);
                dash_flash('Valeurs du Bestiarium reprises. À ajuster ligne par ligne.');
            }

        } elseif ($act === 'jour') {
            ProdFiche::jour($pcms, (string)($_POST['jour'] ?? ''));

        } elseif ($act === 'fdr_generer') {
            /* Elle remplace le texte, et la page prévient avant. Générer sans
               écraser donnerait deux feuilles de route et personne ne saurait
               laquelle est partie au lieu. */
            $dd = ProdFiche::donnees($pcms);
            $dd['fdr']['texte'] = ProdFiche::feuilleDeRoute($p, $dd);
            ProdFiche::ecrire($pcms, $dd);
            dash_flash('Feuille de route générée. Modifiez-la librement.');
        }
        redirect($retour);
    }

    /* Les deux vues imprimables: le dossier et la feuille de route. */
    /* LES DIX ONGLETS S'IMPRIMENT, et non plus deux. Anna, 16.08.2026: « todas
       as etapas (…) tem que poder imprimir ». La liste fermée reste une liste
       fermée — `$onglet` vient de l'URL, et on n'inclut pas un fichier d'après
       une chaîne qu'un visiteur choisit. */
    if (($_GET['imprimer'] ?? '') === '1' && in_array($onglet,
        ['synthese','dossier','planning','logistique','technique','fdr',
         'remuneration','budget','devis','droits'], true)) {
        require __DIR__ . '/_prod_imprimer.php';
        return;
    }

    require __DIR__ . '/_prod_fiche.php';
    return;
}

/* LES LIENS DE PRESSKIT. Traités avant le bloc ci-dessous, qui exige un projet
   ouvert ($id > 0): ceux-ci portent un identifiant de spectacle du CMS, pas de
   projet du dashboard, et se postent depuis la liste. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pk'] ?? '') !== '') {
    Auth::requireCsrf();
    dash_exige_ecriture('projets');

    $cms = (int)($_POST['projet_cms'] ?? 0);
    if ($cms > 0) {
        if ((string)$_POST['pk'] === 'ouvrir') {
            Presskit::ouvrir($cms, (string)($_POST['destinataire'] ?? ''));
            dash_flash('Lien ouvert. Il expire dans ' . Presskit::JOURS . ' jours, et tout ancien lien cesse de fonctionner.');
        } elseif ((string)$_POST['pk'] === 'revoquer') {
            Presskit::revoquer($cms);
            dash_flash('Lien révoqué.');
        }
    }
    redirect('/dashboard.php?e=projets');
}


/* ══ L'ANCIENNE FICHE A ÉTÉ REMPLACÉE. [16.08.2026] ═════════════════════════
   Elle vivait ici et montrait la seule couche `projet_prod`: phase,
   responsable, validé par, lieu, budget, porteur juridique, notes. Ses champs
   sont tous repris dans l'onglet Synthèse de la nouvelle fiche, qui en ajoute
   huit autres. La garder aurait donné deux fiches pour le même spectacle,
   atteignables par la même adresse — et c'est la première qui aurait gagné.

   La liste des dates du spectacle qu'elle affichait est passée dans l'onglet
   Devis, qui répond à la même question avec les prix en plus. */


// ═══════════════════════════════════════════════════════════════════════════
// LA LISTE
// ═══════════════════════════════════════════════════════════════════════════

$q     = trim((string)($_GET['q'] ?? ''));
$phase = trim((string)($_GET['ph'] ?? ''));
/* LES SPECTACLES PASSÉS NE S'OUVRENT PLUS PAR DÉFAUT. Anna, 16.08.2026: « tem
   que tirar os inativos. ja foram, nao precisam estar ali ». Quatorze des
   trente-cinq sont en `status = 'former'`, et ils remplissaient plus du tiers
   d'une liste qu'on ouvre pour travailler sur les vingt et un autres.

   ILS NE SONT PAS SUPPRIMÉS POUR AUTANT, et « tous » reste à un clic: leurs
   fiches de production portent des budgets, des feuilles de route et des
   contrats qu'on va rechercher des années après. Cacher n'est pas effacer, et
   la différence se voit le jour d'un contrôle. */
$etat  = trim((string)($_GET['st'] ?? ''));
if ($etat === '' && !isset($_GET['st'])) $etat = 'current';

/* LE TYPE VIENT DU SITE, il n'est pas ressaisi ici. Anna, 16.08.2026: « vc pode
   puxar a classificacao de tipo de projeto do nosso site ». Les six catégories
   — Danse, Musique, Théâtre, Arts visuels, Performance, Marionnettes — vivent
   dans `categories` et se rattachent par `project_categories`, qui est une
   table de liaison: une pièce peut en porter deux, et « Danse · Marionnettes »
   est une information, pas une hésitation. Les recopier dans le dashboard
   ferait deux vérités qui divergeraient à la première correction faite côté
   site. */
$typeId = (int)($_GET['ty'] ?? 0);
$TYPES  = [];
foreach (DB::all("SELECT c.id, c.name_fr,
                    (SELECT COUNT(*) FROM project_categories pc WHERE pc.category_id = c.id) n
                  FROM categories c ORDER BY c.sort, c.name_fr") as $c)
    if ((int)$c['n'] > 0) $TYPES[(int)$c['id']] = ['nom' => (string)$c['name_fr'], 'n' => (int)$c['n']];

$where = ['1=1']; $args = [];
if (isset($PHASES[$phase])) { $where[] = 'pp.phase = ?'; $args[] = $phase; }
if ($etat === 'current' || $etat === 'former') { $where[] = 'pr.status = ?'; $args[] = $etat; }
if (isset($TYPES[$typeId])) {
    $where[] = 'EXISTS (SELECT 1 FROM project_categories pc WHERE pc.project_id = pr.id AND pc.category_id = ?)';
    $args[] = $typeId;
}
if ($q !== '') {
    $like = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
    $where[] = '(pr.title_fr LIKE ? OR pr.title_en LIKE ? OR pp.responsable LIKE ?)';
    array_push($args, $like, $like, $like);
}

$t0 = microtime(true);
$st = DB::pdo()->prepare(
    "SELECT pr.id, pr.title_fr, pr.title_en, pr.status, pr.visible, pr.year_creation,
            pr.duration_min, pp.phase, pp.responsable, pp.budget, pp.devise,
            o.nom AS organisation,
            (SELECT GROUP_CONCAT(c.name_fr ORDER BY c.sort SEPARATOR ' · ')
               FROM project_categories pc JOIN categories c ON c.id = pc.category_id
              WHERE pc.project_id = pr.id) AS types,
            (SELECT COUNT(*) FROM booking b
              WHERE b.supprime_le IS NULL AND b.projet = COALESCE(pr.title_fr, pr.title_en)) AS n_dates
       FROM projects pr
       LEFT JOIN projet_prod  pp ON pp.project_id = pr.id
       LEFT JOIN organisation o  ON o.id = pp.organisation_id
      WHERE " . implode(' AND ', $where) . "
      ORDER BY pr.status, pr.sort, pr.id");
$st->execute($args);
$lignes = $st->fetchAll();
$ms = (int)round((microtime(true) - $t0) * 1000);

$parPhase = DB::pdo()->query("SELECT phase, COUNT(*) n FROM projet_prod GROUP BY phase")
                     ->fetchAll(PDO::FETCH_KEY_PAIR);
/* ── L'AVERTISSEMENT « n projets sans couche production » EST RETIRÉ ────────
   [16.08.2026] Anna: « mais uma vez estamos preparando o site essa msg nao tem
   porque ». C'est la même règle qu'elle a déjà donnée deux fois aujourd'hui:
   on construit la base, et un champ vide n'est pas un défaut à signaler.

   Une fiche de production se crée toute seule à la première ouverture —
   `ProdFiche::ligne()` l'insère — donc « n'a pas de couche production » veut
   seulement dire « personne n'a encore ouvert cette fiche ». Le dire en rouge
   en haut de l'écran transforme un état normal de chantier en reproche
   quotidien, et un reproche quotidien qu'on ne peut pas faire taire finit par
   couvrir les vrais.

   La requête part avec le bloc: elle tournait à chaque ouverture pour un
   affichage qui n'existe plus. */

dash_haut('projets', count($lignes) . ' projet' . (count($lignes)>1?'s':'') . ' · ' . $ms . ' ms');
?>
<?php /* LES FILTRES QUE LE MENU DE COLONNE REMPLACE SONT PARTIS.
     [Anna, 21.08.2026] « este tipo de filtro acaba de colocar os outros
     filtros em desuso, pode tirar ».

     CE QUI RESTE N'EST PAS UN FILTRE, C'EST UN CHOIX DE JEU DE DONNÉES: par
     défaut cet écran ne montre pas tout, et le menu de colonne ne voit que ce
     qui est rendu. Retirer ce sélecteur ne masquerait pas des lignes, il les
     rendrait inatteignables. */ ?>
<form class="filtres" method="get" action="/dashboard.php">
  <input type="hidden" name="e" value="projets">
  <select name="st">
    <option value="tous"<?= $etat==='tous'?' selected':'' ?>>tous, y compris les passés</option>
    <option value="current"<?= $etat==='current'?' selected':'' ?>>en cours</option>
    <option value="former"<?= $etat==='former'?' selected':'' ?>>passés</option>
  </select>
  <button type="submit">Chercher</button>
  <?php if ($etat !== 'current'): ?>
    <a class="vider" href="/dashboard.php?e=projets">revenir aux projets en cours</a><?php endif; ?>
</form>
<?php dash_flash_html(); ?>

<?php /* LA GOUTTIÈRE DE 26 PX, QUI MANQUAIT. [Anna, 21.08.2026] « il y a encore
     des choses collées au menu ». Quatre écrans n'enveloppaient rien dans
     `.zone` — offres, personnel, projets, calendrier — et c'était le même
     oubli: le contenu commençait à zéro, donc sous la barre noire, et les
     tableaux poussaient la page à déborder vers la droite. */ ?>
<div class="zone">

<?php require __DIR__ . '/_filtre_colonnes.php'; ?>
<div class="tw"><table data-filtres>
  <?php /* L'ORDRE EST CELUI D'ANNA. [17.08.2026] « projet + porteur + type +
       annee + duree + budget + dates + phase ». Il suit la façon dont on lit
       une ligne à voix haute — quelle pièce, portée par qui, de quel genre —
       et il finit par la phase, qui est le seul de ces champs qui change tout
       seul avec le temps. */ ?>
  <thead><tr><th>Projet</th><th>Porteur</th><th>Type</th>
    <th class="d">Année</th><th class="d">Durée</th><th class="d">Budget</th>
    <th class="d">Dates</th><th>Phase</th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $r): ?>
    <tr class="<?= $r['status']==='former' ? 'passe' : '' ?>">
      <td><a href="/dashboard.php?e=projets&amp;p=<?= (int)$r['id'] ?>"><?=
        e($r['title_fr'] ?: $r['title_en']) ?></a>
        <?php if (!$r['visible']): ?><span class="np">non publié</span><?php endif; ?></td>
      <td class="sec"><?= e($r['organisation'] ?? '') ?></td>
      <td class="sec"><?= $r['types'] ? e((string)$r['types']) : '<span class="sec">—</span>' ?></td>
      <td class="d sec"><?= $r['year_creation'] ? (int)$r['year_creation'] : '' ?></td>
      <td class="d sec"><?= $r['duration_min'] ? (int)$r['duration_min'] . ' min' : '' ?></td>
      <td class="d"><?= $r['budget'] !== null
          ? number_format((float)$r['budget'], 0, ',', ' ') . ' ' . e($r['devise']) : '' ?></td>
      <td class="d"><?= $r['n_dates'] ? (int)$r['n_dates'] : '<span class="sec">0</span>' ?></td>
      <td><?php if ($r['phase']): ?><span class="ph <?= e($r['phase']) ?>"><?=
        e($PHASES[$r['phase']]) ?></span><?php else: ?><span class="sec">—</span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<?php /* ── LES PRESSKITS ────────────────────────────────────────────────────
       [16.08.2026]

       ILS NE PENDENT PAS AUX PROJETS CI-DESSUS, et il faut le dire plutôt que
       de le cacher. La table `projet` du dashboard porte la production — la
       phase, le budget, les dates. Le contenu qu'un presskit partage — intro,
       distribution, photos, fiches techniques — vit dans `projects`, la table
       du CMS, parce que c'est elle qui alimente le site public.

       Ce sont donc deux listes, et c'est exactement la duplication que la
       spécification veut supprimer: « nous allons revoir ce qui est ici est
       déjà dans le cms, on ne veut pas travailler en double. » Tant qu'elle
       n'est pas faite, mieux vaut deux listes honnêtes qu'une seule qui
       mentirait sur ce qu'elle montre. */ ?>

<h2 class="sect2">Les spectacles du site</h2>
<p class="sec expl">Cliquez un titre pour ouvrir sa <strong>fiche de production</strong> et ses
   neuf onglets: Synthèse, Dossier, Planning, Logistique, Feuille de route, Rémunération,
   Budget, Devis, Droits d'auteur.
   <br>La colonne <em>presskit</em> donne le lien qu'on envoie à un programmateur — intro,
   photos et fiches techniques, sans compte ni mot de passe du Catalogue. Il se révoque,
   contrairement à une adresse publique une fois partagée.
   <br>Ces spectacles sont ceux du <strong>site</strong>, pas les projets de production
   ci-dessus: leur contenu vit dans le CMS.</p>

<div class="tw"><table>
  <thead><tr><th>Spectacle</th><th>Lien de presskit</th><th>Visites</th><th></th></tr></thead>
  <tbody>
  <?php foreach (Presskit::projets() as $s): $sid = (int)$s['id'];
        $actif = $s['jeton'] && !(int)$s['revoque']
                 && (!$s['expire_a'] || strtotime((string)$s['expire_a']) > time());
        $url = $actif ? rtrim((string)cfg('base_url',''), '/') . '/presskit.php?t=' . $s['jeton'] : ''; ?>
    <tr>
      <td><a href="/dashboard.php?e=projets&amp;p=<?= $sid ?>"><strong><?= e((string)($s['title_fr'] ?: $s['title_en'])) ?></strong></a></td>
      <td class="sec">
        <?php if ($actif): ?>
          <input type="text" class="url" value="<?= e($url) ?>" readonly onclick="this.select()"
                 aria-label="Lien du presskit">
          <?php if ($s['destinataire']): ?><br><span class="np">remis à <?= e((string)$s['destinataire']) ?></span><?php endif; ?>
        <?php elseif ($s['jeton']): ?>
          <span class="sec">révoqué ou expiré</span>
        <?php else: ?>
          <span class="sec">—</span>
        <?php endif; ?>
      </td>
      <td class="d sec"><?= $s['visites'] !== null ? (int)$s['visites'] : '' ?>
        <?php if ($s['dernier_acces']): ?><br><span class="np"><?= e(date('d.m.Y', strtotime((string)$s['dernier_acces']))) ?></span><?php endif; ?>
      </td>
      <td class="d">
        <?php if (dash_droit('projets', dash_role()) === 'ecrit'): ?>
          <form method="post" action="/dashboard.php?e=projets" class="inline">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="pk" value="ouvrir">
            <input type="hidden" name="projet_cms" value="<?= $sid ?>">
            <button type="submit" class="lien-b"><?= $actif ? 'renouveler' : 'ouvrir' ?></button>
          </form>
          <?php if ($actif): ?>
            <form method="post" action="/dashboard.php?e=projets" class="inline"
                  onsubmit="return confirm('Révoquer ce lien ?')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="pk" value="revoquer">
              <input type="hidden" name="projet_cms" value="<?= $sid ?>">
              <button type="submit" class="lien-b">révoquer</button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<style>
td.d,th.d{text-align:right;white-space:nowrap}
tr.passe{opacity:.55}
.sect2{margin:34px 26px 4px;font-size:16px}
.expl{margin:0 26px 12px;max-width:80ch;font-size:13.5px}
.url{width:100%;max-width:420px;padding:5px 8px;font-family:ui-monospace,Menlo,monospace;
  font-size:11.5px;border:1px solid var(--trait);border-radius:4px;
  background:var(--fond2);color:var(--encre)}
.lien-b{background:none;border:0;color:var(--doux);text-decoration:underline;
  cursor:pointer;font:inherit;font-size:12.5px;padding:2px 6px}
.lien-b:hover{color:var(--encre)}
.np{font-size:10.5px;border:1px solid var(--trait);border-radius:3px;padding:0 4px;
    margin-left:6px;color:var(--doux)}
.ph{font-size:11px;padding:2px 8px;border-radius:10px;border:1px solid var(--trait);white-space:nowrap}
.ph.tournee{background:#e7f6ea;border-color:#bfe3c8;color:#1c5c2e}
.ph.creation,.ph.production{background:#fff6d9;border-color:#f0dfa3;color:#6b5312}
.ph.cloture{background:var(--fond2);color:var(--doux)}
.alerte{margin:16px 26px 0;padding:11px 16px;background:var(--fond2);
  border-left:4px solid var(--orange);font-size:13.5px;max-width:82ch}
</style>
</div><!-- .zone -->

<?php dash_bas(); ?>

<?php
/**
 * Écran Contacts. [16.08.2026]
 *
 * Le carnet d'adresses de la diffusion: 7 841 fiches, cherchées et filtrées par
 * MariaDB. Le dashboard actuel les embarque dans son JavaScript, 2,23 Mo, et
 * cherche en mémoire à chaque frappe.
 *
 * TROIS VUES DANS UN FICHIER, choisies par ?c=<id> et ?mod: la liste, la fiche,
 * le formulaire. Lire, chercher, filtrer, ouvrir, créer, modifier, supprimer.
 *
 * LA SUPPRESSION EST LOGIQUE. Une fiche effacée reste en base avec sa date et
 * sort des listes. Sur 7 841 contacts construits en des années, une suppression
 * définitive est une perte qu'on ne remarque que le jour où l'on cherche.
 */
declare(strict_types=1);

const PAR_PAGE = 50;

/** En dessous de cette longueur, FULLTEXT ne voit pas le mot. */
const FT_MIN = 4;

$cid = (int)($_GET['c'] ?? 0);

// ═══════════════════════════════════════════════════════════════════════════
// ENREGISTRER
// ═══════════════════════════════════════════════════════════════════════════

$CH_CONTACT = ['nom','prenom','nom_famille','fonction','structure','categorie',
               'ville_struct','pays_struct','region','adresse','cp','ville','dept','pays',
               'email1','email2','email_pro1','tel1','tel_pro1','site',
               'mots_cles','description','participations','notes',
               /* Ajoutées le 16.08.2026, migration 018. `pronom` et `adresse2`
                  existaient dans le dashboard et se perdaient en silence à
                  l'enregistrement, faute de colonne. */
               'pronom','adresse2','instagram','linkedin','directions',
               'date_mois','date_notes'];
$err = $saisi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    /* Le rôle décide aussi de l'écriture, et pas seulement de l'accès à
       l'écran: `production` lit les Finances sans les modifier. Le routeur
       ne peut pas le faire à notre place, lui ne voit pas les POST. */
    dash_exige_ecriture('contacts');
    foreach ($CH_CONTACT as $c) $saisi[$c] = trim((string)($_POST[$c] ?? ''));

    /* LA PHOTO. Convertie en data URI et rangée dans la fiche, comme le fait
       le dashboard — c'est ce qui rend la reprise sans perte, et c'est aussi
       discutable: une image dans une colonne de texte pèse sur chaque requête
       qui fait SELECT *. On garde la forme existante plutôt que d'en inventer
       une seconde, et 60 fiches sur 8432 en portent une.

       400 Ko au maximum, et une liste de types fermée: un data URI n'est pas
       servi par Apache mais il est rendu par le navigateur, et un SVG accepté
       ici s'exécuterait dans la page. */
    if (!empty($_POST['photo_retirer'])) {
        $saisi['photo'] = '';
    } elseif (($_FILES['photo_f']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $f = $_FILES['photo_f'];
        $ok = ['image/jpeg' => 1, 'image/png' => 1, 'image/webp' => 1, 'image/gif' => 1];
        $mime = function_exists('mime_content_type') && is_uploaded_file((string)$f['tmp_name'])
              ? (string)@mime_content_type((string)$f['tmp_name']) : '';
        if (!isset($ok[$mime])) {
            $err['photo_f'] = 'JPEG, PNG, GIF ou WebP seulement.';
        } elseif ((int)$f['size'] > 400 * 1024) {
            $err['photo_f'] = 'La photo dépasse 400 Ko.';
        } else {
            $saisi['photo'] = 'data:' . $mime . ';base64,'
                            . base64_encode((string)file_get_contents((string)$f['tmp_name']));
        }
    } else {
        /* Aucun fichier envoyé: on ne touche pas à celle qui est enregistrée. */
        unset($saisi['photo']);
    }

    /* LES TROIS LISTES À COCHER. Elles arrivent en tableau et repartent en
       chaîne à virgules — le format du dashboard, pour que la reprise depuis
       lv-contacts reste sans perte et qu'un import relise ce que l'écran écrit.

       `participations_libre` permet d'en ajouter une qui n'est pas dans les
       douze proposées: une liste fermée demanderait une migration à chaque
       nouveau festival, et le prochain arrive toujours. */
    foreach (['participations', 'date_mois', 'directions'] as $liste) {
        $c = array_filter(array_map('trim', (array)($_POST[$liste . '_c'] ?? [])),
                          fn($x) => $x !== '');
        if ($liste === 'participations') {
            foreach (explode(',', (string)($_POST['participations_libre'] ?? '')) as $x) {
                $x = trim($x);
                if ($x !== '' && !in_array($x, $c, true)) $c[] = $x;
            }
        }
        /* Le champ n'est écrasé que si le formulaire portait bien ses cases:
           sans cela, un POST qui ne les contient pas viderait la liste. */
        if (isset($_POST[$liste . '_c']) || ($liste === 'participations' && isset($_POST['participations_libre']))) {
            $saisi[$liste] = mb_substr(implode(', ', array_unique($c)), 0, 500);
        }
    }

    if (($_POST['action'] ?? '') === 'supprimer' && $cid > 0) {
        DB::pdo()->prepare('UPDATE contact SET supprime_le = NOW() WHERE id = ?')->execute([$cid]);
        dash_flash('Contact supprimé. Il reste en base et peut être rétabli.');
        redirect('/dashboard.php?e=contacts');
    }

    if ($saisi['nom'] === '') $err['nom'] = 'Sans nom, la fiche ne se retrouve pas.';
    foreach (['email1','email2','email_pro1'] as $m) {
        if ($saisi[$m] !== '' && !filter_var($saisi[$m], FILTER_VALIDATE_EMAIL)) {
            $err[$m] = 'Cette adresse ne ressemble pas à une adresse.';
        }
    }

    if (!$err) {
        /* Les champs absents du POST — la photo qu'on n'a pas retouchée —
           sortent de l'écriture au lieu d'y entrer à NULL. */
        $cols = array_values(array_filter($CH_CONTACT, fn($c) => array_key_exists($c, $saisi)));
        $vals = array_map(fn($c) => $saisi[$c] === '' ? null : $saisi[$c], $cols);
        if ($cid > 0) {
            $set = implode(',', array_map(fn($c) => "$c=?", $cols));
            DB::pdo()->prepare("UPDATE contact SET $set WHERE id = ?")->execute([...$vals, $cid]);
            dash_flash('Contact enregistré.');
        } else {
            /* `ref` est NOT NULL et unique: elle vient de la reprise du dashboard.
               Une fiche créée ici s'en donne une qui ne peut pas entrer en
               collision avec les « c001 » à « c7841 » déjà repris. */
            $ref = 'n' . date('ymdHis') . random_int(10, 99);
            /* $cols porte déjà les seules colonnes que le POST a apportées:
               on lui ajoute `ref` devant, et surtout PAS $CH_CONTACT entier —
               sinon l'INSERT réclamerait plus de valeurs que $vals n'en a. */
            $colsIns = array_merge(['ref'], $cols);
            $q = implode(',', array_fill(0, count($colsIns), '?'));
            DB::pdo()->prepare('INSERT INTO contact (' . implode(',', $colsIns) . ") VALUES ($q)")
                     ->execute([$ref, ...$vals]);
            $cid = (int)DB::pdo()->lastInsertId();
            dash_flash('Contact créé.');
        }
        redirect('/dashboard.php?e=contacts&c=' . $cid);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// LE FORMULAIRE
// ═══════════════════════════════════════════════════════════════════════════

if (isset($_GET['mod']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $k = $cid > 0 ? DB::one('SELECT * FROM contact WHERE id = ? AND supprime_le IS NULL', [$cid]) : [];
    if ($cid > 0 && !$k) { dash_haut('contacts'); echo '<p class="vide">Ce contact n\'existe pas.</p>'; dash_bas(); return; }
    $v = fn(string $c) => $saisi[$c] ?? ($k[$c] ?? '');

    $cats = DB::pdo()->query("SELECT DISTINCT categorie FROM contact
        WHERE supprime_le IS NULL AND categorie IS NOT NULL ORDER BY categorie")->fetchAll(PDO::FETCH_COLUMN);
    $choixCat = ['' => '(aucune)'] + array_combine($cats, $cats);

    dash_haut('contacts', $cid > 0 ? 'modifier' : 'nouveau contact');
    dash_form_style();
    if ($err) echo '<div class="flash err">Rien n\'a été enregistré: ' . count($err)
                 . ' champ(s) à corriger. Ce que vous aviez saisi est conservé.</div>';
    ?>
    <div class="fil"><a href="/dashboard.php?e=contacts<?= $cid > 0 ? '&amp;c=' . $cid : '' ?>">← retour</a></div>
    <form class="saisie" method="post" enctype="multipart/form-data"
          action="/dashboard.php?e=contacts<?= $cid > 0 ? '&amp;c=' . $cid : '' ?>&amp;mod=1">
      <?= Auth::csrfField() ?>
      <div class="grille">
        <div class="titre-bloc">Qui</div>
        <?php
        ch('nom', 'Nom affiché', $v('nom'), $err, ['requis'=>true, 'large'=>true,
           'aide'=>'Ce qui apparaît dans les listes. Souvent le nom de la structure']);
        ch('prenom', 'Prénom', $v('prenom'), $err);
        ch('nom_famille', 'Nom de famille', $v('nom_famille'), $err);
        /* Le pronom n'est pas un ornement: écrire à un programmateur en se
           trompant est le genre de faute qui ferme une porte avant la première
           phrase. 236 fiches le portent déjà. */
        ch('pronom', 'Pronom', $v('pronom'), $err, ['aide'=>'elle, il, iel, Mme, M.']);
        ch('fonction', 'Fonction', $v('fonction'), $err);
        ch('categorie', 'Catégorie', $v('categorie'), $err, ['type'=>'select','choix'=>$choixCat]);

        echo '<div class="titre-bloc">La structure</div>';
        ch('structure', 'Structure', $v('structure'), $err, ['large'=>true]);
        ch('ville_struct', 'Ville', $v('ville_struct'), $err);
        /* Deux champs s'appelaient « Pays » dans le même formulaire, à vingt
           lignes l'un de l'autre, et rien ne disait lequel remplir. Ils portent
           d'ailleurs la même valeur sur les 7481 fiches renseignées. Le libellé
           dit maintenant lequel est lequel; les deux colonnes restent, parce
           qu'une structure française peut avoir un contact qui habite ailleurs
           et que la fusion se déciderait sur des données, pas sur un écran. */
        ch('pays_struct', 'Pays de la structure', $v('pays_struct'), $err);
        ch('region', 'Région', $v('region'), $err);
        ch('site', 'Site', $v('site'), $err, ['large'=>true]);
        ch('instagram', 'Instagram', $v('instagram'), $err);
        ch('linkedin', 'LinkedIn', $v('linkedin'), $err);

        echo '<div class="titre-bloc">Joindre</div>';
        ch('email_pro1', 'Courriel professionnel', $v('email_pro1'), $err, ['type'=>'email']);
        ch('email1', 'Courriel', $v('email1'), $err, ['type'=>'email']);
        ch('email2', 'Autre courriel', $v('email2'), $err, ['type'=>'email']);
        ch('tel_pro1', 'Téléphone professionnel', $v('tel_pro1'), $err);
        ch('tel1', 'Téléphone', $v('tel1'), $err);

        echo '<div class="titre-bloc">Adresse postale</div>';
        ch('adresse', 'Adresse', $v('adresse'), $err, ['large'=>true]);
        ch('adresse2', 'Adresse (suite)', $v('adresse2'), $err, ['large'=>true]);
        ch('cp', 'Code postal', $v('cp'), $err);
        ch('ville', 'Ville', $v('ville'), $err);
        ch('dept', 'Département', $v('dept'), $err);
        ch('pays', 'Pays de la personne', $v('pays'), $err);

        echo '<div class="titre-bloc">Le reste</div>';
        ch('mots_cles', 'Mots-clefs', $v('mots_cles'), $err, ['large'=>true,
           'aide'=>'Ils entrent dans la recherche par index']);
        ch('description', 'Description', $v('description'), $err, ['large'=>true]);
        ?>
        <div class="ch large">
          <label for="photo_f">Photo</label>
          <?php if (trim((string)$v('photo')) !== ''): ?>
            <div class="ph-apercu"><img src="<?= e((string)$v('photo')) ?>" alt="">
              <label class="ph-sup"><input type="checkbox" name="photo_retirer" value="1"> retirer</label>
            </div>
          <?php endif; ?>
          <input type="file" id="photo_f" name="photo_f" accept="image/*">
          <p class="aide">JPEG, PNG ou WebP, 400 Ko au maximum. Elle est stockée dans la fiche
             elle-même, comme le fait le dashboard: c'est ce qui permet la reprise sans perte.</p>
        </div>
        <?php
        ch('notes', 'Notes', $v('notes'), $err, ['type'=>'textarea','large'=>true,'rows'=>5,
           'aide'=>'Elles entrent aussi dans la recherche']);
        ?>
      </div>

      <?php require __DIR__ . '/_contact_listes.php'; ?>
      <div class="actions">
        <button type="submit"><?= $cid > 0 ? 'Enregistrer' : 'Créer' ?></button>
        <a class="sec2" href="/dashboard.php?e=contacts<?= $cid > 0 ? '&amp;c=' . $cid : '' ?>">annuler</a>
        <?php if ($cid > 0): ?>
        <?php /* UN BOUTON DE FORMULAIRE, ET NON UN FORMULAIRE FABRIQUÉ EN JAVASCRIPT.
                     [Anna, 21.08.2026] « corrigir o botão effacer ».
        
                     Ce lien construisait un formulaire à la volée et y collait le
                     champ CSRF passé par `addslashes()`. Or `addslashes()` échappe
                     pour une chaîne JavaScript, pas pour un ATTRIBUT HTML: les
                     guillemets du champ fermaient le `onclick` au milieu, et la fin du
                     code s'affichait en clair à côté d'une boîte de saisie orpheline.
                     C'est ce qu'Anna a vu.
        
                     La correction n'est pas de mieux échapper, c'est de ne rien
                     fabriquer: on est déjà dans le formulaire de la fiche. Un bouton
                     `name=action value=supprimer` le soumet avec le bon champ, et le
                     CSRF est celui du formulaire, écrit une seule fois.
        
                     `formnovalidate` parce qu'une fiche incomplète doit pouvoir être
                     supprimée: sans lui, un champ requis vide empêcherait l'envoi et
                     le bouton ne ferait rien, sans dire pourquoi. */ ?>
        <button type="submit" class="sup" name="action" value="supprimer"
                formnovalidate
                onclick="return confirm(&#39;Supprimer ce contact ? Il restera en base.&#39;)">supprimer</button>
        <?php endif; ?>
      </div>
    </form>
    <style>.fil{padding:12px 26px 0;font-size:13px}.fil a{color:var(--doux);text-decoration:none}</style>
    <?php dash_bas(); return;
}

// ═══════════════════════════════════════════════════════════════════════════
// LA FICHE
// ═══════════════════════════════════════════════════════════════════════════

if ($cid > 0) {
    $k = DB::one('SELECT * FROM contact WHERE id = ? AND supprime_le IS NULL', [$cid]);
    if (!$k) { dash_haut('contacts'); echo '<p class="vide">Ce contact n\'existe pas.</p>'; dash_bas(); return; }

    dash_haut('contacts', e(trim((string)($k['fonction'] ?? '') . ' ' . ($k['categorie'] ? '· ' . $k['categorie'] : ''))));
    ?>
    <div class="fil"><a href="/dashboard.php?e=contacts">← tous les contacts</a>
      <a class="mod" href="/dashboard.php?e=contacts&amp;c=<?= $cid ?>&amp;mod=1">modifier</a></div>
    <?php dash_flash_html(); ?>
    <div class="zone">
      <div class="tete-c">
        <?php /* LA PLACE DE LA PHOTO EST TOUJOURS TENUE, avec ou sans photo.
                 Demandé par Anna le 16.08.2026. Sans emplacement fixe, le titre
                 saute de 80 pixels selon que la fiche en porte une, et deux
                 fiches côte à côte n'ont pas la même tête. Le carré vide dit
                 aussi qu'il en manque une, ce qu'une absence ne dit pas.

                 La photo est un data URI dans le dashboard, pas un chemin: on
                 la rend telle quelle. 60 fiches sur 8432 en portent une. */ ?>
        <?php $ph = trim((string)($k['photo'] ?? '')); ?>
        <?php if ($ph !== ''): ?>
          <img class="ph-c" src="<?= e($ph) ?>" alt="">
        <?php else:
          $ini = mb_strtoupper(mb_substr(trim((string)($k['prenom'] ?: $k['nom'])), 0, 1)
                             . mb_substr(trim((string)($k['nom_famille'] ?: '')), 0, 1)); ?>
          <div class="ph-c ph-vide" title="Aucune photo — elle s'ajoute en modifiant la fiche"><?=
            e($ini) ?></div>
        <?php endif; ?>
        <div>
          <h2 class="gros"><?= e($k['nom']) ?></h2>
          <?php if ($k['prenom'] || $k['nom_famille'] || $k['pronom']): ?>
            <p class="sst2"><?= e(trim(($k['prenom'] ?? '') . ' ' . ($k['nom_famille'] ?? ''))) ?><?php
              if ($k['pronom']): ?> <span class="pron"><?= e((string)$k['pronom']) ?></span><?php endif; ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php /* ── DEUX COLONNES, ET NON TROIS ────────────────────────────────
           [16.08.2026] La grille s'ajustait toute seule et faisait trois
           colonnes sur un écran large. Une structure appelée « Association
           Labo'Art / Festival 48ème de rue » se coupait alors sur trois lignes
           dans 290 pixels, et une adresse de site sur deux. Anna: « desse jeito
           fica informacao cortada, vamos deixar so com 2 colunas para poder ler
           melhor ».

           À gauche l'état civil — ce qui identifie et ce qui sert à écrire. À
           droite ce qui sert à décider à qui écrire: les mois, les rencontres,
           les associations, les notes. Ce sont deux lectures différentes et
           elles ne se mélangent pas. */ ?>
      <div class="fiche2">
      <div class="col-g">
      <div class="fiche">
      <?php
      $l = function (string $lib, $val, string $href = '') {
          if ($val === null || $val === '') return;
          $v = $href ? '<a href="' . e($href . $val) . '">' . e((string)$val) . '</a>' : e((string)$val);
          printf('<div class="l"><span class="k">%s</span><span class="v">%s</span></div>', e($lib), $v);
      };
      $l('Fonction', $k['fonction']);
      $l('Catégorie', $k['categorie']);
      $l('Structure', $k['structure']);
      $l('Ville', trim((string)($k['ville_struct'] ?? '') . ' ' . ($k['pays_struct'] ? '· ' . $k['pays_struct'] : '')));
      $l('Région', $k['region']);
      $l('Site', $k['site']);
      $l('Instagram', $k['instagram']);
      $l('LinkedIn', $k['linkedin']);
      $l('Courriel pro', $k['email_pro1'], 'mailto:');
      $l('Courriel', $k['email1'], 'mailto:');
      /* « AUTRE COURRIEL » NE S'AFFICHE QUE S'IL EST VRAIMENT AUTRE. [17.08.2026]
         Anna: « na ficha de contatos, tirar autre couriel ». Le retirer tout
         court aurait caché une VRAIE seconde adresse sur 512 fiches — mesuré:
         896 en portent une, 198 recopient `email1` et 186 recopient le courriel
         professionnel, donc 384 répétaient mot pour mot la ligne juste
         au-dessus. C'est cette répétition-là qu'elle voyait, pas le champ. */
      $autre = trim((string)($k['email2'] ?? ''));
      if ($autre !== '' && $autre !== trim((string)$k['email1']) && $autre !== trim((string)$k['email_pro1']))
          $l('Autre courriel', $autre, 'mailto:');
      $l('Téléphone pro', $k['tel_pro1'], 'tel:');
      $l('Téléphone', $k['tel1'], 'tel:');
      /* LE PAYS EST DANS LA LIGNE D'ADRESSE ET PLUS SUR SA PROPRE LIGNE. Anna:
         « ta duplicado pays ». Il l'était pour de bon: `pays` est égal à
         `pays_struct` sur les 7481 fiches qui en portent un — 100 %, pas une
         exception — et `pays_struct` s'affiche déjà quatre lignes plus haut,
         collé à la ville de la structure. Deux lignes disaient donc toujours la
         même chose. Il reste ici, où une adresse postale en a besoin. */
      $l('Adresse', trim((string)($k['adresse'] ?? '') . ' ' . ($k['adresse2'] ?? '')
                        . ' ' . ($k['cp'] ?? '') . ' ' . ($k['ville'] ?? '')
                        . ' ' . ($k['pays'] ?? '')));
      $l('Département', $k['dept']);
      $l('Description', $k['description']);
      $l('Référence', $k['ref']);
      ?>
      </div>
      </div>

      <div class="col-d">

      <?php /* LES QUATRE BLOCS S'AFFICHENT TOUJOURS, remplis ou vides. Anna les
           a listés le 16.08.2026 comme manquants — et ils manquaient en effet:
           ils ne s'affichaient que lorsqu'ils portaient quelque chose, donc sur
           une fiche neuve la moitié droite était blanche et rien ne disait ce
           qui pouvait y aller. Un bloc vide qui dit « aucune » enseigne la
           fiche; un bloc absent la cache.

           Découpé en pastilles plutôt qu'en une ligne: « Chalon 2024, Jeune
           public, Carnet diffusion » se lit mal d'un trait, et découpé on voit
           d'un coup où l'on s'est croisés. */
      $past = function (string $titre, $val, string $rien) {
          $v = trim((string)($val ?? ''));
          echo '<div class="past"><div class="past-t">' . e($titre) . '</div>';
          if ($v === '') {
              echo '<p class="past-rien">' . e($rien) . '</p></div>';
              return;
          }
          echo '<div class="past-g">';
          foreach (array_filter(array_map('trim', explode(',', $v))) as $x)
              echo '<span class="past-p">' . e($x) . '</span>';
          echo '</div></div>';
      };

      $past('Date de réalisation', $k['date_mois'],
            'Aucun mois retenu. Ils se cochent en modifiant la fiche.');
      if (trim((string)($k['date_notes'] ?? '')) !== '')
          echo '<p class="past-n">' . nl2br(e((string)$k['date_notes'])) . '</p>';

      $past('Participations et rencontres professionnelles', $k['participations'],
            'Jamais croisé, ou pas encore noté.');

      /* Les mots-clefs en pastilles, comme le reste. [16.08.2026] Ils étaient
         une ligne de texte au milieu de l'état civil; ce sont eux qui servent à
         chercher, ils appartiennent à la colonne de droite. */
      $past('Mots-clefs', $k['mots_cles'], 'Aucun mot-clef.');

      $past('Associations liées', $k['directions'],
            'Aucune association liée. C\'est ce champ qui dit à qui proposer quoi.');
      ?>

      <div class="past">
        <div class="past-t">Notes</div>
        <?php if (trim((string)($k['notes'] ?? '')) !== ''): ?>
          <p class="past-n"><?= nl2br(e((string)$k['notes'])) ?></p>
        <?php else: ?>
          <p class="past-rien">Aucune note.</p>
        <?php endif; ?>
      </div>

      </div>
      </div>
    </div>
    <style>
    .tete-c{display:flex;gap:16px;align-items:flex-start;margin-bottom:14px}
    .ph-c{width:64px;height:64px;object-fit:cover;border-radius:8px;flex:none;border:1px solid var(--trait)}
    .pron{font-size:12.5px;color:var(--doux);border:1px solid var(--trait);border-radius:10px;padding:1px 8px;margin-left:6px}
    .past{margin:0 0 22px}
    .past-t{font-size:11.5px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--doux);margin-bottom:6px}
    .past-g{display:flex;flex-wrap:wrap;gap:6px}
    .past-p{font-size:13px;padding:3px 11px;border:1px solid var(--trait);border-radius:13px}
    a.sans{color:var(--trait);text-decoration:none}
    a.sans:hover{color:var(--doux)}
    .fil{padding:12px 26px 0;font-size:13px;display:flex;gap:16px}
    .fil a{color:var(--doux);text-decoration:none}
    .fil a.mod{margin-left:auto;color:var(--encre);font-weight:600}
    h2.gros{font-size:21px;margin:0 0 4px}
    .sst2{margin:0 0 18px;color:var(--doux);font-size:14px}
    /* Deux colonnes fixes, pas d'auto-fill: c'est l'auto-fill qui fabriquait la
       troisième colonne et coupait les longs noms de structure. */
    .fiche2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:0 44px;
      align-items:start;max-width:1180px}
    @media (max-width:900px){ .fiche2{grid-template-columns:minmax(0,1fr)} }
    .fiche{display:block}
    .col-d{padding-top:1px}
    .past-rien{margin:0;font-size:13.5px;color:var(--doux);font-style:italic}
    .past-n{margin:6px 0 0;font-size:14px;white-space:pre-wrap}
    .ph-vide{display:flex;align-items:center;justify-content:center;background:var(--fond2);
      color:var(--doux);font-size:20px;font-weight:600;letter-spacing:.02em}
    .fiche .l{display:flex;gap:12px;padding:7px 0;border-bottom:1px solid var(--trait)}
    .fiche .k{color:var(--doux);font-size:12.5px;min-width:140px}
    .fiche .v{font-size:14px;word-break:break-word}
    .bl{margin-top:24px;padding:13px 17px;background:var(--fond2);max-width:800px}
    .bl h3{font-size:13px;margin:0 0 6px}.bl p{margin:0;font-size:14px}
    </style>
    <?php dash_bas(); return;
}

$q    = trim((string)($_GET['q'] ?? ''));
$cat  = trim((string)($_GET['cat'] ?? ''));
$pays = trim((string)($_GET['pays'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));

$where = ['supprime_le IS NULL'];
$args  = [];
if ($cat !== '')  { $where[] = 'categorie = ?';   $args[] = $cat; }
if ($pays !== '') { $where[] = 'pays_struct = ?'; $args[] = $pays; }

/* Trois filtres de plus, et ce sont eux qui servent à la diffusion: on ne
   cherche pas « un programmateur », on cherche « qui, dans cette région, a été
   croisé à Chalon et pourrait prendre Bestiarium ».

   `participations` et `directions` sont des chaînes à virgules: on y cherche
   en LIKE. Un index n'y servirait à rien tant qu'elles ne sont pas normalisées,
   et les normaliser demande d'abord de savoir si l'on s'en sert. */
$reg  = trim((string)($_GET['reg'] ?? ''));
$part = trim((string)($_GET['part'] ?? ''));
$dir  = trim((string)($_GET['dir'] ?? ''));
$tag  = trim((string)($_GET['tag'] ?? ''));
if ($reg  !== '') { $where[] = 'region = ?';           $args[] = $reg; }
if ($part !== '') { $where[] = 'participations LIKE ?'; $args[] = '%' . $part . '%'; }
if ($dir  !== '') { $where[] = 'directions LIKE ?';     $args[] = '%' . $dir . '%'; }
/* La recherche est sans casse — `LIKE` l'est déjà avec la collation
   `utf8mb4_unicode_ci` de cette base — et bornée par des virgules pour que
   « danse » ne remonte pas « danse contemporaine ». Les extrémités sont
   couvertes en encadrant la colonne elle-même. */
if ($tag !== '') {
    $where[] = "CONCAT(',', REPLACE(mots_cles, ', ', ','), ',') LIKE ?";
    $args[] = '%,' . $tag . ',%';
}

/* DEUX CHEMINS DE RECHERCHE, ET C'EST VOULU.
 *
 * FULLTEXT utilise un index et rend en millisecondes sur 7 841 lignes. Mais il
 * ignore les mots plus courts que ft_min_word_len, qui vaut 4 sur InnoDB.
 * Chercher « GE » ou un nom de trois lettres ne rendrait rien du tout, et l'on
 * en conclurait que le contact n'existe pas.
 *
 * D'où le repli en LIKE quand le mot le plus long est trop court: il lit toute
 * la table, ce qui coûte une douzaine de millisecondes ici. Un résultat lent
 * vaut mieux qu'un résultat vide et faux. L'écran dit lequel des deux il a
 * utilisé, pour que ce ne soit pas une magie invisible. */
$mode = '';
if ($q !== '') {
    $plusLong = 0;
    foreach (preg_split('/\s+/', $q) ?: [] as $mot) $plusLong = max($plusLong, mb_strlen($mot));
    if ($plusLong >= FT_MIN) {
        $mode    = 'index';
        $where[] = 'MATCH(nom, structure, ville_struct, mots_cles, notes) AGAINST (? IN NATURAL LANGUAGE MODE)';
        $args[]  = $q;
    } else {
        $mode    = 'balayage';
        $like    = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $where[] = '(nom LIKE ? OR structure LIKE ? OR ville_struct LIKE ? OR email1 LIKE ?
                     OR email_pro1 LIKE ? OR prenom LIKE ? OR nom_famille LIKE ?)';
        array_push($args, $like, $like, $like, $like, $like, $like, $like);
    }
}

/* Les listes des trois filtres nouveaux. `participations` et `directions`
   sont des chaînes à virgules: on les découpe ici pour proposer les valeurs
   réellement présentes, plutôt qu'une liste écrite en dur qui vieillirait. */
$regions = DB::all("SELECT region, COUNT(*) n FROM contact
                    WHERE supprime_le IS NULL AND region IS NOT NULL AND region <> ''
                    GROUP BY region HAVING n >= 3 ORDER BY n DESC LIMIT 40");
$lesParts = $lesDirs = [];
foreach (DB::all("SELECT participations FROM contact
                  WHERE supprime_le IS NULL AND participations IS NOT NULL AND participations <> ''") as $r)
    foreach (array_map('trim', explode(',', (string)$r['participations'])) as $x)
        if ($x !== '') $lesParts[$x] = ($lesParts[$x] ?? 0) + 1;
foreach (DB::all("SELECT directions FROM contact
                  WHERE supprime_le IS NULL AND directions IS NOT NULL AND directions <> ''") as $r)
    foreach (array_map('trim', explode(',', (string)$r['directions'])) as $x)
        if ($x !== '') $lesDirs[$x] = ($lesDirs[$x] ?? 0) + 1;
arsort($lesParts); arsort($lesDirs);

/* ── LES MOTS-CLEFS, LE FILTRE QU'ANNA ATTENDAIT ────────────────────────────
   [16.08.2026] « ataca as tags (…) é uma funcao muito importate para eu poder
   fazer pesquisa ». Ils étaient importés — 4713 fiches en portent — et
   n'apparaissaient que comme une ligne de texte dans la fiche: ni pastilles, ni
   filtre. La donnée était là et ne servait à rien.

   LES VARIANTES DE CASSE SONT FONDUES, et c'est la moitié du travail. Relevé
   sur les 8432: « jeune public » 900 fois et « JEUNE PUBLIC » 748, « AVIGNON
   2026 » 206 et « Avignon 2026 » 68. Un filtre exact aurait perdu la moitié des
   fiches sans rien dire — et un filtre qui perd la moitié est pire qu'aucun
   filtre, parce qu'on croit avoir la liste complète.

   On garde l'orthographe la PLUS FRÉQUENTE comme étiquette, on additionne les
   comptes, et la recherche se fait sans casse. Rien n'est réécrit en base: on ne
   corrige pas 4713 fiches sur une supposition, on lit mieux ce qui est écrit. */
$lesTags = [];
foreach (DB::all("SELECT mots_cles FROM contact
                   WHERE supprime_le IS NULL AND mots_cles IS NOT NULL AND mots_cles <> ''") as $r) {
    foreach (array_filter(array_map('trim', explode(',', (string)$r['mots_cles']))) as $t) {
        $k = mb_strtolower($t);
        if (!isset($lesTags[$k])) $lesTags[$k] = ['n' => 0, 'formes' => []];
        $lesTags[$k]['n']++;
        $lesTags[$k]['formes'][$t] = ($lesTags[$k]['formes'][$t] ?? 0) + 1;
    }
}
foreach ($lesTags as $k => &$t) { arsort($t['formes']); $t['lib'] = array_key_first($t['formes']); }
unset($t);
uasort($lesTags, fn($a, $b) => $b['n'] <=> $a['n']);

/* LES ASSOCIATIONS SONT PROPOSÉES MÊME QUAND AUCUN CONTACT N'EN PORTE ENCORE.
   Les deux autres filtres se déduisent des fiches, et c'est juste pour eux: une
   participation qui n'existe sur personne n'est pas un filtre. Une association,
   si — elle existe indépendamment du carnet, et le filtre construit à partir
   des seules fiches disparaissait entièrement tant que personne n'avait coché,
   ce qui donne à l'écran l'air de n'avoir pas changé. On ajoute donc les
   associations connues à zéro, et le compte ne s'affiche que s'il y en a un. */
foreach (DB::all("SELECT nom FROM organisation
                   WHERE supprime_le IS NULL AND genre = 'association' AND nom <> ''
                   ORDER BY nom") as $r)
    $lesDirs[(string)$r['nom']] ??= 0;

$sqlWhere = implode(' AND ', $where);
$t0 = microtime(true);

$st = DB::pdo()->prepare("SELECT COUNT(*) FROM contact WHERE $sqlWhere");
$st->execute($args);
$total = (int)$st->fetchColumn();

$pages  = max(1, (int)ceil($total / PAR_PAGE));
$page   = min($page, $pages);
$offset = ($page - 1) * PAR_PAGE;

$st = DB::pdo()->prepare(
    "SELECT id, ref, nom, prenom, nom_famille, fonction, structure, categorie,
            ville_struct, pays_struct, email1, email_pro1, tel1, site
       FROM contact WHERE $sqlWhere ORDER BY nom
      LIMIT " . PAR_PAGE . " OFFSET $offset");
$st->execute($args);
$lignes = $st->fetchAll();
$ms = (int)round((microtime(true) - $t0) * 1000);

/* Les listes des filtres viennent de la base, jamais d'une constante: une
   catégorie nouvelle apparaît toute seule, une catégorie disparue cesse d'être
   proposée, et personne n'a à tenir une liste à jour à la main. */
$cats  = DB::pdo()->query("SELECT categorie, COUNT(*) n FROM contact
                            WHERE supprime_le IS NULL AND categorie IS NOT NULL
                            GROUP BY categorie ORDER BY n DESC")->fetchAll();
$payss = DB::pdo()->query("SELECT pays_struct, COUNT(*) n FROM contact
                            WHERE supprime_le IS NULL AND pays_struct IS NOT NULL
                            GROUP BY pays_struct ORDER BY n DESC LIMIT 20")->fetchAll();

$lien = function (array $chg) use ($q, $cat, $pays, $page): string {
    $p = array_merge(['e' => 'contacts', 'q' => $q, 'cat' => $cat, 'pays' => $pays, 'page' => $page], $chg);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 1);
    return '/dashboard.php?' . http_build_query($p);
};

$sst = number_format($total, 0, ',', ' ') . ' fiche' . ($total > 1 ? 's' : '')
     . (($q !== '' || $cat !== '' || $pays !== '') ? ' trouvée' . ($total > 1 ? 's' : '') : '')
     . ' · ' . $ms . ' ms'
     . ($mode === 'balayage' ? ' · balayage, mot court' : '');

dash_haut('contacts', e($sst));
?>

<?php /* ── LA BARRE, SUR DEUX LIGNES ─────────────────────────────────────────
     [16.08.2026] Demandé par Anna. Les six contrôles se suivaient à la file et
     passaient à la ligne au hasard de la largeur de l'écran: le bouton
     « Chercher » et « nouveau contact » se retrouvaient tantôt à droite,
     tantôt au milieu de la deuxième ligne, jamais au même endroit d'un écran à
     l'autre. On cherche un bouton qu'on a déjà cliqué cent fois — il doit être
     là où on l'a laissé.

     Ligne du haut: le champ de recherche, large, et à droite les deux gestes
     qui terminent — chercher, ou créer. Ligne du bas: les cinq filtres, qui
     précisent une recherche sans jamais la déclencher seuls. */ ?>
<form class="filtres deux-lignes" method="get" action="/dashboard.php">
  <input type="hidden" name="e" value="contacts">

  <div class="fl-haut">
    <input type="search" name="q" value="<?= e($q) ?>"
           placeholder="Nom, structure, ville, mots-clefs, notes" autofocus>
    <button type="submit">Chercher</button>
    <a class="neuf" href="/dashboard.php?e=contacts&amp;mod=1">+ nouveau contact</a>
  </div>

  <div class="fl-bas">
  <select name="cat">
    <option value="">Toutes les catégories</option>
    <?php foreach ($cats as $c): ?>
      <option value="<?= e($c['categorie']) ?>"<?= $cat === $c['categorie'] ? ' selected' : '' ?>><?=
        e($c['categorie']) ?> (<?= $c['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <select name="reg">
    <option value="">Toutes les régions</option>
    <?php foreach ($regions as $r): ?>
      <option value="<?= e((string)$r['region']) ?>"<?= $reg === $r['region'] ? ' selected' : '' ?>><?=
        e((string)$r['region']) ?> (<?= (int)$r['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <select name="part">
    <option value="">Toutes les participations</option>
    <?php foreach ($lesParts as $x => $n): ?>
      <option value="<?= e((string)$x) ?>"<?= $part === (string)$x ? ' selected' : '' ?>><?=
        e((string)$x) ?> (<?= (int)$n ?>)</option>
    <?php endforeach; ?>
  </select>
  <?php if ($lesDirs): ?>
  <select name="dir">
    <option value="">Toutes les associations</option>
    <?php foreach ($lesDirs as $x => $n): ?>
      <option value="<?= e((string)$x) ?>"<?= $dir === (string)$x ? ' selected' : '' ?>><?=
        e((string)$x) ?><?= $n ? ' (' . (int)$n . ')' : '' ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>
  <?php /* Les mots-clefs. Les vingt-cinq plus portés sont proposés; au-delà la
       liste devient un mur qu'on ne parcourt plus. Les autres restent
       atteignables par la recherche en texte libre, qui couvre `mots_cles`. */ ?>
  <select name="tag">
    <option value="">Tous les mots-clefs</option>
    <?php $i = 0; foreach ($lesTags as $k => $t): if (++$i > 25) break; ?>
      <option value="<?= e($k) ?>"<?= mb_strtolower($tag) === $k ? ' selected' : '' ?>><?=
        e($t['lib']) ?> (<?= $t['n'] ?>)</option>
    <?php endforeach; ?>
    <?php /* Si la valeur choisie n'est pas dans les vingt-cinq, on l'ajoute pour
         que le filtre ne se réinitialise pas tout seul en rechargeant. */ ?>
    <?php if ($tag !== '' && !array_key_exists(mb_strtolower($tag), array_slice($lesTags, 0, 25, true))): ?>
      <option value="<?= e($tag) ?>" selected><?= e($tag) ?></option>
    <?php endif; ?>
  </select>
  <select name="pays">
    <option value="">Tous les pays</option>
    <?php foreach ($payss as $p): ?>
      <option value="<?= e($p['pays_struct']) ?>"<?= $pays === $p['pays_struct'] ? ' selected' : '' ?>><?=
        e($p['pays_struct']) ?> (<?= $p['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <?php if ($q !== '' || $cat !== '' || $reg !== '' || $part !== '' || $dir !== '' || $tag !== '' || $pays !== ''): ?>
    <a class="vider" href="/dashboard.php?e=contacts">tout effacer</a>
  <?php endif; ?>
  </div>
</form>
<?php dash_flash_html(); ?>
<style>
.filtres.deux-lignes{display:block}
/* À DROITE COMME PARTOUT, MAIS SANS ÉCRASER LE CHAMP. [Anna, 21.08.2026]
   « colocar os campos de recherche alinhados à direita, e todos os outros ».
   La règle d'origine faisait remplir toute la largeur au champ, et elle avait
   sa raison — « un champ court invite à taper court », sur huit mille cinq
   cents fiches. Le compromis garde un champ large, plafonné, poussé à droite
   avec le reste. */
.fl-haut{display:flex;align-items:center;gap:10px;margin-bottom:9px;justify-content:flex-end}
.fl-bas{justify-content:flex-end}
/* Le champ prend toute la place restante: c'est le geste principal, et un
   champ court invite à taper court. `min-width:0` sinon un flex-item refuse de
   passer sous sa largeur intrinsèque et pousse les boutons hors de l'écran. */
.fl-haut input[type=search]{flex:0 1 460px;min-width:0}
.fl-haut button{white-space:nowrap}
/* `margin-left:0` annule le `margin-left:auto` d'origine: c'est le conteneur
   qui pousse maintenant, et laisser les deux ferait un trou entre le bouton et
   le lien. */

.fl-bas{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.fl-bas select{max-width:100%}
@media (max-width:640px){
  .fl-haut{flex-wrap:wrap}
  .fl-haut input[type=search]{flex:1 1 100%}
}
</style>

<?php if (!$lignes): ?>
  <p class="vide">Aucune fiche ne correspond.<?php if ($mode === 'index'): ?>
    La recherche par index ignore les mots de moins de <?= FT_MIN ?> lettres.<?php endif; ?></p>
<?php else: ?>
<div class="tw">
<?php require __DIR__ . '/_filtre_colonnes.php'; ?>
<?php /* LE MENU DE COLONNE NE VOIT QUE LA PAGE, ET LA RECHERCHE RESTE POUR ÇA.
     [Anna, 21.08.2026] Huit mille cinq cent trente-deux contacts, cinquante par
     page: le menu trie et filtre ce qui est affiché, la barre du haut est la
     seule chose qui atteint les huit mille quatre cent quatre-vingts autres.
     Les retirer ici — comme on l'a fait sur les écrans qui tiennent en une
     page — couperait l'accès au reste du fichier. */ ?>
<table data-filtres>
  <thead><tr>
    <th>Nom</th><th>Fonction</th><th>Structure</th><th>Lieu</th><th>Catégorie</th><th>Contact</th>
  </tr></thead>
  <tbody>
  <?php foreach ($lignes as $r): ?>
    <tr>
      <?php /* LA PREMIÈRE COLONNE PORTE LA PERSONNE, PAS LA STRUCTURE. Anna,
           16.08.2026: « a primeira coluna tem que ter somente o nome da pessoa,
           esta em doublon com o nome da estrutura ».

           D'où venait le doublon, mesuré sur les 8432: 3373 fiches — 40 % —
           ont `nom` STRICTEMENT ÉGAL à `structure`. Ce n'est pas une faute de
           saisie mais l'héritage du carnet: quand on ne connaissait que le
           lieu, on écrivait le lieu dans les deux. La colonne répétait donc
           mot pour mot celle d'à côté, en occupant deux lignes chacune.

           565 de ces 3373 portent en plus une vraie personne dans `prenom` et
           `nom_famille` — c'est elle qu'on cherche, et elle était reléguée en
           petit sous le nom du lieu.

           L'ordre est donc: la personne d'abord; à défaut le `nom` libre, mais
           seulement s'il n'est pas déjà la structure; sinon rien, et la
           colonne Structure porte l'information une seule fois. */
        $pers = trim(((string)($r['prenom'] ?? '')) . ' ' . ((string)($r['nom_famille'] ?? '')));
        $lib  = $pers;
        $sous = '';
        if ($lib === '') {
            $n = trim((string)($r['nom'] ?? ''));
            /* `nom` n'est retenu que s'il apporte autre chose que la structure. */
            if ($n !== '' && $n !== trim((string)($r['structure'] ?? ''))) $lib = $n;
        } elseif (trim((string)($r['nom'] ?? '')) !== ''
                  && trim((string)$r['nom']) !== trim((string)($r['structure'] ?? ''))
                  && mb_stripos((string)$r['nom'], $pers) === false) {
            /* Un `nom` qui dit encore autre chose — un service, une mention —
               reste lisible en dessous plutôt que d'être perdu. */
            $sous = trim((string)$r['nom']);
        }
      ?>
      <?php /* La cellule reste VIDE quand il n'y a pas de personne. [16.08.2026]
           J'y avais mis « sans personne nommée » pour que le trou se voie; sur
           2808 fiches ça répétait la même phrase à chaque ligne et couvrait les
           noms qu'on cherche. Anna: « nao faz sentido algum, apaga isso ». Une
           colonne vide se voit très bien toute seule. */ ?>
      <td><?php if ($lib !== ''): ?><a href="/dashboard.php?e=contacts&amp;c=<?= (int)$r['id'] ?>"><?=
        e($lib) ?></a><?php else: ?><a class="sans" href="/dashboard.php?e=contacts&amp;c=<?= (int)$r['id'] ?>"
        title="Ouvrir la fiche">—</a><?php endif; ?>
        <?php if ($sous !== ''): ?><div class="sec"><?= e(mb_substr($sous, 0, 70)) ?></div><?php endif; ?></td>
      <td class="sec"><?= e($r['fonction'] ?? '') ?></td>
      <td><?= e($r['structure'] ?? '') ?><?php if ($r['site']): ?>
        <div class="sec"><a href="<?= e($r['site']) ?>" target="_blank" rel="noopener">site</a></div>
      <?php endif; ?></td>
      <td><?= e($r['ville_struct'] ?? '') ?><?php if ($r['pays_struct']): ?>
        <div class="sec"><?= e($r['pays_struct']) ?></div><?php endif; ?></td>
      <td class="sec"><?= e($r['categorie'] ?? '') ?></td>
      <td class="sec"><?php $m = $r['email_pro1'] ?: $r['email1']; ?>
        <?php if ($m): ?><a href="mailto:<?= e($m) ?>"><?= e($m) ?></a><?php endif; ?>
        <?php if ($r['tel1']): ?><div><?= e($r['tel1']) ?></div><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<nav class="pages">
  <?php if ($page > 1): ?><a href="<?= e($lien(['page' => $page - 1])) ?>">précédent</a><?php endif; ?>
  <?php
  /* Les cinq pages autour, plus les extrémités. Sur 157 pages, tout afficher
     ferait une barre plus haute que le tableau. */
  $vus = [];
  foreach ([1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages] as $p) {
      if ($p >= 1 && $p <= $pages) $vus[$p] = 1;
  }
  ksort($vus);
  $prec = 0;
  foreach (array_keys($vus) as $p) {
      if ($p > $prec + 1) echo '<span class="mut">…</span>';
      echo $p === $page ? '<span class="ici">' . $p . '</span>'
                        : '<a href="' . e($lien(['page' => $p])) . '">' . $p . '</a>';
      $prec = $p;
  }
  ?>
  <?php if ($page < $pages): ?><a href="<?= e($lien(['page' => $page + 1])) ?>">suivant</a><?php endif; ?>
  <span class="mut">page <?= $page ?> sur <?= $pages ?></span>
</nav>
<?php endif; ?>

<?php dash_bas(); ?>

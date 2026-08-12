<?php
/**
 * Mise à niveau de la base — où va chaque vidéo.   [V42-CATALOGUE]
 *
 * Une colonne sur « videos », et une seule question : cette vidéo est-elle
 * publique, ou réservée au Catalogue ?
 *
 * Jusqu'ici toutes les vidéos d'un projet allaient sur sa page publique. Le
 * Catalogue n'a alors plus rien à protéger : une captation intégrale visible
 * de tous, ce sont les droits des artistes et de la musique qui sautent, et un
 * programmateur qui peut la regarder librement n'a plus de raison d'écrire au
 * bureau. C'est exactement ce que le lien Drive protégeait jusqu'à présent.
 *
 * La valeur par défaut est 0, « publique ». Aucune vidéo déjà en ligne ne
 * change de statut : le site reste ce qu'il est, et c'est en cochant la case
 * qu'on retire une vidéo du public, jamais l'inverse. Une migration qui rendrait
 * des vidéos privées d'office serait plus prudente en apparence et fausse en
 * pratique : elle déciderait à la place d'Anna, sur des dizaines de fiches,
 * sans que personne ne l'ait relu.
 *
 * Idempotent : il regarde l'état avant d'agir et se rejoue sans risque. Il
 * n'affiche que ce qu'il a réellement fait.
 *
 * À déposer à la RACINE du zip, puis « Mettre à jour la base ».
 */

/* L'amorçage. L'installateur ne charge pas l'application, et ce script tourne
   depuis un fichier temporaire hors du site : __DIR__ ne désigne donc pas la
   racine. C'est pourquoi l'installateur pose $LV_RACINE juste avant le require. */
if (!class_exists('DB', false)) {
    $racineSite = $LV_RACINE ?? ($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($racineSite === '' || !is_file($racineSite . '/app/bootstrap.php')) {
        echo '<p>Impossible de trouver l’application depuis ce script '
           . '(app/bootstrap.php introuvable). Rien n’a été modifié.</p>';
        return;
    }
    require_once $racineSite . '/app/bootstrap.php';
}

$existeColonne = static function (string $t, string $c): bool {
    return (bool)DB::val(
        'SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
        [$t, $c]
    );
};

$faits = [];

if (!$existeColonne('videos', 'catalog_only')) {
    $apres = $existeColonne('videos', 'sort') ? ' AFTER `sort`' : '';
    DB::run("ALTER TABLE `videos` ADD COLUMN `catalog_only` TINYINT(1) NOT NULL DEFAULT 0$apres");
    $faits[] = 'videos.catalog_only';
}

if ($faits) {
    echo '<p>Colonnes ajoutées : <strong>' . count($faits) . '</strong></p><ul>';
    foreach ($faits as $f) echo '<li>' . htmlspecialchars($f, ENT_QUOTES, 'UTF-8') . '</li>';
    echo '</ul><p>Chaque vidéo peut maintenant être réservée au Catalogue, une par une, '
       . 'dans la fiche du projet. Aucune n’a changé de statut : elles sont toutes '
       . 'restées publiques, comme avant.</p>';
} else {
    echo '<p>Base déjà à jour — la colonne existe, rien à faire.</p>';
}

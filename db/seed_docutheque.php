<?php
/**
 * Le contenu de départ de la Docuthèque. [16.08.2026]
 *
 *   php db/seed_docutheque.php
 *
 * Repris des captures d'écran du dashboard: les mêmes documents, dans les
 * mêmes rubriques, avec les mêmes statuts — huit modèles de contrats sur huit
 * sont « à compléter », et c'est l'information la plus utile de la liste.
 *
 * IDEMPOTENT par (rubrique, titre): rejouer ne duplique pas. Les liens Drive
 * ne sont pas ici — ils se collent dans l'écran, un par un, parce que personne
 * ne les a rassemblés ailleurs.
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

const DOCS = [
    ['guides','Guide de fonctionnement interne','',                                   'pret'],
    ['guides','Internal Operating Guide','',                                           'pret'],
    ['guides','Charte Le Voisin','',                                                   'pret'],
    ['guides','Procédure dépôt facture','',                                            'pret'],
    ['guides','Manuel d’utilisation du dashboard (FR)','Guide complet des fonctionnalités','pret'],

    ['contrats','Template (modèle de contrat vierge)','',                              'a-completer'],
    ['contrats','CDDU — France','Contrat à durée déterminée d’usage (FR)',             'a-completer'],
    ['contrats','CDDU — Suisse','Contrat à durée déterminée d’usage (CH)',             'a-completer'],
    ['contrats','Contrat de prestation de services — CH','',                           'a-completer'],
    ['contrats','Contrat de prestation de services — FR','',                           'a-completer'],
    ['contrats','CDI — France','',                                                     'a-completer'],
    ['contrats','CDI — Suisse','',                                                     'a-completer'],
    ['contrats','Contrat de stage — CH','',                                            'a-completer'],

    ['prod','Template budget de création','',                                          'pret'],
    ['prod','Template convention','',                                                  'pret'],
    ['prod','Confirmation d’accueil de spectacle','',                                   'pret'],
    ['prod','Fiche projet — base dossier de demande','Fiche pré-remplie asso + projet', 'pret'],

    ['postes','Assistant·e à la mise en scène','',                                     'pret'],
    ['postes','Dramaturge','',                                                         'pret'],
    ['postes','Directeur·trice technique','',                                          'pret'],
    ['postes','Créateur·trice / régisseur·se son','',                                  'pret'],
    ['postes','Créateur·trice / régisseur·se lumière','',                              'pret'],
    ['postes','Créateur·trice / régisseur·se vidéo','',                                'pret'],
];

$n = 0;
foreach (DOCS as $i => [$rub, $titre, $desc, $st]) {
    $ex = DB::one('SELECT id FROM docutheque WHERE rubrique = ? AND titre = ?', [$rub, $titre]);
    if ($ex) continue;
    DB::insert('docutheque', ['rubrique'=>$rub, 'titre'=>$titre,
        'description'=>$desc !== '' ? $desc : null, 'statut'=>$st, 'ordre'=>($i + 1) * 10]);
    $n++;
}
printf("%d documents ajoutés, %d en base\n", $n,
    (int)DB::val('SELECT COUNT(*) FROM docutheque WHERE supprime_le IS NULL'));
echo "Les liens Drive se collent dans l'écran: personne ne les a rassemblés ailleurs.\n";

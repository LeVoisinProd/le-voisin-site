<?php
/**
 * La porte du dashboard.                           [D01-COQUE] [16.08.2026]
 *
 * POURQUOI UN FICHIER À LA RACINE PLUTÔT QU'UNE PAGE DE L'ADMINISTRATION
 *
 * Même raison que catalogue.php, et elle a été remesurée le 16.08.2026: le
 * cache d'opcode de ce serveur garde index.php compilé et refuse de le relire.
 * La preuve s'est faite avec la marque de diagnostic posée le 12.08 exactement
 * pour cela: le fichier du serveur et la copie locale font tous deux 25 319
 * octets, la marque y figure quatre fois, et elle n'apparaissait pas dans la
 * sortie. Un fichier au nom neuf, lui, se compile à la première requête.
 *
 * CE FICHIER NE FAIT QUE TROIS CHOSES, et c'est voulu: il amorce, il vérifie
 * qui entre, il sert l'écran demandé. Tout le reste vit dans app/dash/. La
 * première version, écrite ce matin, portait l'écran des contacts dans son
 * corps; à dix-huit écrans ce serait ingérable, et c'est exactement ainsi que
 * le dashboard actuel est devenu un seul fichier de 6,3 Mo.
 *
 *     app/dash/_ecrans.php   la carte: quels écrans, quel groupe, quel état
 *     app/dash/_layout.php   l'enveloppe commune, menu compris
 *     app/dash/<clef>.php    un écran
 *
 * QUI ENTRE. Auth, les comptes du bureau, les mêmes que l'administration du
 * site. Pas MemberAuth, qui est l'espace des 77 collaborateur·rices, ni
 * CatalogAuth, le mot de passe unique donné aux programmateur·rices. Trois
 * portes distinctes, et celle-ci est la première.
 *
 * L'ADRESSE:  https://le-voisin.com/dashboard.php
 *             https://le-voisin.com/dashboard.php?e=contacts
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/dash/_ecrans.php';
require __DIR__ . '/app/dash/_layout.php';

I18n::init();
session_boot();

if (!Auth::check()) redirect('/admin/login.php');

/* La clef vient de l'adresse et n'est jamais un chemin. On ne garde que les
   lettres et le tiret bas, puis on vérifie qu'elle est déclarée: rien de ce que
   le navigateur écrit ne peut désigner un fichier hors de app/dash/. */
$clef = preg_replace('/[^a-z_]/', '', strtolower((string)($_GET['e'] ?? '')));
if ($clef === '' || !isset(DASH_ECRANS[$clef])) $clef = DASH_DEFAUT;

if (!dash_existe($clef)) {
    dash_a_faire($clef);   // déclaré au menu, pas encore écrit
    exit;
}

require __DIR__ . '/app/dash/' . $clef . '.php';

<?php
/**
 * L'enveloppe commune des écrans du dashboard. [16.08.2026]
 *
 * Deux fonctions, appelées par chaque écran: dash_haut() avant le contenu,
 * dash_bas() après. Entre les deux, l'écran n'écrit que ce qui lui est propre.
 *
 * POURQUOI UNE ENVELOPPE ET PAS UN GABARIT PAR ÉCRAN. Le dashboard actuel est
 * un seul fichier HTML de 6,3 Mo où toutes les sections coexistent dans le DOM
 * et où l'on bascule de l'une à l'autre en JavaScript. La conséquence mesurée le
 * 15.08.2026: dix-neuf pages y existent sans qu'aucun menu n'y mène, et douze
 * sections sont des marqueurs vides. Ici chaque écran est un fichier, servi par
 * une adresse, et le menu vient de la déclaration: une page sans entrée de menu
 * ne peut pas exister par accident.
 *
 * LE STYLE EST ICI ET NON DANS UN .css. Un seul fichier de moins à installer,
 * et surtout: le style suit le code dans le même commit. Sur ce serveur, où
 * l'installateur écrit fichier par fichier et où deux sessions se sont déjà
 * écrasées l'une l'autre le 13.08, un style qui voyage avec sa page ne peut pas
 * arriver à moitié.
 */
declare(strict_types=1);

function dash_haut(string $ecranActif, string $sousTitre = ''): void
{
    $u = Auth::user();
    ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(dash_libelle($ecranActif)) ?> — Dashboard Le Voisin</title>

<?php /* La Space Grotesk du site, déjà déclarée dans assets/css/fonts.css et
         déjà servie aux pages publiques. On la lie plutôt que de la
         redéclarer: une seule source, et le navigateur l'a souvent déjà en
         cache pour avoir visité le site. */ ?>
<link rel="preload" href="<?= e(url('/assets/fonts/space-grotesk-latin-wght-normal.woff2')) ?>"
      as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(url('/assets/css/fonts.css')) ?>">
<style>
/* LE DASHBOARD NE SUIT PLUS LE THÈME DU SYSTÈME. Choisi par Anna le 16.08.2026:
   « menu fundo preto e a parte da direita fundo branco e police preta ». Il
   suivait `prefers-color-scheme`, si bien que le contenu passait au gris foncé
   chez qui a son Mac en thème sombre — et le même écran n'avait pas la même
   tête d'une personne à l'autre du bureau.

   ON ASSUME DONC UN SEUL LOOK: rail noir à gauche, papier blanc à droite,
   encre noire. C'est aussi ce qui est imprimé — les relevés, la feuille de
   route et le devis sortent sur du papier blanc — et un écran qui ressemble à
   ce qui sort de l'imprimante fait gagner la relecture d'avant envoi.

   Le bloc `prefers-color-scheme` a été retiré, pas commenté: une variante
   sombre laissée en place se réveille toute seule à la prochaine modification
   de ces variables. Le site public, lui, garde son propre thème — ceci ne
   concerne que `/dashboard.php`. */
:root { --encre:#0d0d0d; --papier:#fff; --doux:#6b6b6b; --trait:#e4e4e4;
        --jaune:#FFD24D; --orange:#FF7142; --fond2:#f7f7f7; --barre:#0a0a0a;
        /* La hauteur de la ligne de titre, sous laquelle les en-têtes de
           tableau viennent se coller. Valeur de repli: le script de bas de page
           la remplace par la hauteur RÉELLE. Une valeur devinée était fausse dès
           que le sous-titre passait à la ligne, et les en-têtes se cachaient
           alors derrière la barre — on voyait le premier contact et plus les
           noms de colonnes. */
        --h-tete:59px; }
/* color-scheme:light dit au navigateur de ne pas assombrir de lui-même les
   contrôles de formulaire: sans cette ligne, les `select` et les `input`
   restent sombres sous un système en thème sombre, sur fond blanc. */
:root { color-scheme:light; }
* { box-sizing:border-box; }
body { margin:0; background:var(--papier); color:var(--encre); font-size:15px;
       font-family:'Space Grotesk',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
       line-height:1.5; }
a { color:inherit; }

/* La disposition: un rail de navigation à gauche, le contenu à droite. Sur
   petit écran le rail passe au-dessus et défile à l'horizontale. */
/* TOUT L'ÉCRAN À 90 %. [Anna, 21.08.2026] « to achando que tem que diminuir a
   fonte de tudo, pois está muito difícil de visualizar tudo ».

   POURQUOI `zoom` ET NON UNE REPASSE SUR LES TAILLES. Le dashboard porte 399
   déclarations `font-size` en pixels, réparties sur seize fichiers. Les
   diviser une à une changerait le texte sans toucher aux marges, aux hauteurs
   de ligne ni aux paddings: on lirait plus petit dans des cases restées
   grandes, et l'on n'en verrait pas davantage. `zoom` réduit tout ensemble,
   tient en une ligne, et se retire en une ligne.

   MESURÉ AVANT D'APPLIQUER, dans un navigateur à 1700×1000: les Événements
   passent de 11 lignes visibles à 17, les Contacts de 11 à 14. Et le léger
   débordement de la fenêtre des tableaux existait déjà — ce n'est pas la
   réduction qui l'introduit. */
.enveloppe { display:grid; grid-template-columns:212px minmax(0,1fr); min-height:100vh;
        zoom:0.9; }
@media (max-width:820px) {
  .enveloppe { grid-template-columns:1fr; }
  /* Sur petit écran le sous-titre passe à la ligne: la barre est plus haute, et
     les en-têtes de tableau doivent descendre d'autant. */
  :root { --h-tete:84px; }
}

/* ── LE MENU RESTE À L'ÉCRAN ──────────────────────────────────── [17.08.2026]
   Anna: « o menu a gauche tb tem que ficar fixo, ele scrola como a pagina
   inteira, nao pode ser ». Sur 8432 contacts ou 51 dates on descend loin, et
   arrivé en bas il fallait remonter tout le chemin pour changer d'écran.

   `sticky` ET NON `fixed`, et la différence n'est pas cosmétique: en `fixed`
   l'aside sort du flux, la grille de deux colonnes perd sa première, et le
   contenu passe dessous. En `sticky` la colonne garde sa place, elle se
   contente de ne pas défiler.

   `height:100vh` avec `overflow-y:auto` PARCE QUE LE MENU PEUT ÊTRE PLUS HAUT
   QUE L'ÉCRAN: treize entrées, leurs sous-entrées et le pied. Sans cela un
   portable de 13 pouces perdrait Paramètres, et il n'y aurait aucun moyen d'y
   arriver — un menu collant qui dépasse est pire qu'un menu qui défile.

   `align-self:start` est ce qui rend le collant effectif: dans une grille, un
   élément est étiré sur toute la hauteur de la rangée par défaut, et un
   élément déjà aussi haut que sa rangée n'a nulle part où coller. */
aside { background:var(--barre); color:#e8e8e8; padding:18px 0 40px;
        position:sticky; top:0; align-self:start; height:100vh; overflow-y:auto;
        /* La barre de défilement du menu, sur fond noir. Laissée au thème
           clair elle dessine un rail blanc au milieu du rail noir. */
        scrollbar-width:thin; scrollbar-color:#3a3a3a var(--barre); }
aside .marque { display:block; padding:2px 18px 20px; font-size:14px; font-weight:600;
        letter-spacing:.02em; text-decoration:none; color:#e8e8e8; border-left:0; }
aside .marque img { display:block; width:100%; max-width:132px; height:auto; }
aside .marque .sous { display:block; margin-top:5px; font-weight:400; font-size:10px;
        letter-spacing:.14em; text-transform:uppercase; color:#9a9a94; }
/* AUCUN FILTRE. Le logo porte son propre fond: bloc noir avec « LE » en blanc,
   bloc jaune avec « VOISIN » en noir. Un invert() posé le 16.08.2026 pour le
   « faire ressortir » a tourné le jaune de la marque en bleu. Une image qui
   porte son fond n'a pas besoin d'aide. */
@media (max-width:820px) {
  aside .marque { padding:4px 12px 8px; }
  aside .marque img { max-width:92px; }
  aside .marque .sous { font-size:9px; margin-top:3px; }
}
aside .groupe { padding:16px 18px 5px; font-size:10.5px; text-transform:uppercase;
        letter-spacing:.09em; color:#8a8a8a; }
aside a, aside span.mort { display:block; padding:6px 18px; font-size:13.5px;
        text-decoration:none; color:#d2d2d2; border-left:3px solid transparent; }
aside a:hover { background:#1c1c1c; color:#fff; }
aside a.ici { background:#1c1c1c; color:#fff; border-left-color:var(--jaune); font-weight:600; }
aside span.mort { color:#5e5e5e; cursor:default; }
aside a.fils, aside span.mort.fils { padding-left:34px; font-size:12.5px; }
aside .pied { margin-top:26px; padding:14px 18px 0; border-top:1px solid #262626;
        font-size:11.5px; color:#7d7d7d; }
aside .pied a { padding:3px 0; color:#9a9a9a; font-size:11.5px; }
@media (max-width:820px) {
  aside { padding:10px 0; }
  aside .groupe, aside .pied { display:none; }
  aside .rail { display:flex; overflow-x:auto; gap:2px; padding:0 10px; }
  aside a, aside span.mort { white-space:nowrap; border-left:0;
        border-bottom:3px solid transparent; }
  aside a.ici { border-left:0; border-bottom-color:var(--jaune); }
}

main { min-width:0; }
/* ── LA LIGNE DU TITRE RESTE VISIBLE ──────────────────────────────── [16.08.2026]
   Demandé par Anna: « eu quero que cima da linha onde tem o nome da pagina
   fique fixo para sempre vermos o scrollando para baixo ». Sur 8432 contacts ou
   sur une fiche de production, on descend loin et l'on ne sait plus sur quel
   écran on est ni ce que dit le compteur.

   `--h-tete` existe parce que les en-têtes de tableau collent DÉJÀ en haut. Sans
   décalage ils se glisseraient sous le titre et l'on perdrait les noms de
   colonnes — exactement ce qu'on venait de gagner. La valeur suit la hauteur
   réelle: 15 px de marge en haut, 15 en bas, une ligne de 18 px à 1,5.

   Le fond est explicite. Un élément collant garde son fond d'origine, et le
   contenu qui passe dessous se lit au travers. */
.tete { position:sticky; top:0; z-index:30; background:var(--papier);
        border-bottom:2px solid var(--encre); padding:15px 26px; display:flex;
        align-items:baseline; gap:16px; flex-wrap:wrap; }
.tete h1 { font-size:18px; margin:0; }
.tete .sst { color:var(--doux); font-size:13px; }
.tete .droite { margin-left:auto; font-size:12.5px; color:var(--doux); }

.zone { padding:22px 26px; }
.avis { margin:26px; padding:18px 22px; background:var(--fond2);
        border-left:4px solid var(--jaune); max-width:62ch; }
.avis h2 { font-size:15px; margin:0 0 8px; }
.avis p { margin:0 0 8px; font-size:14px; color:var(--doux); }
.avis p:last-child { margin:0; }

/* Les briques que les écrans réutilisent. Déclarées ici une fois, pour que le
   deuxième écran n'ait pas à redéclarer un tableau. */
/* ── LE TABLEAU DÉFILE DANS SA PROPRE FENÊTRE ──────────────────── [17.08.2026]
   Anna, deux fois: « o titulo das colunas ainda esta no lugar errado », capture
   à l'appui — la première ligne au-dessus des noms de colonnes, la deuxième
   cachée derrière.

   J'AI CORRIGÉ ÇA DEUX FOIS ET DEUX FOIS À CÔTÉ, et il faut l'écrire pour ne
   pas recommencer:

     16.08  on a mesuré `--h-tete` au lieu de la deviner. Juste, et sans effet:
            ça n'a changé que de combien l'en-tête descendait trop bas.
     17.08  on a posé `overflow-y:clip` en croyant que `.tw` cessait d'être un
            conteneur de défilement. Faux. La spec est claire: un conteneur de
            défilement naît dès que `overflow-x` OU `overflow-y` vaut `auto` ou
            `scroll`. `overflow-x:auto` suffit, et `position:sticky` se résout
            contre lui SUR LES DEUX AXES.

   Donc `top:var(--h-tete)` ne plaçait pas l'en-tête sous la barre de titre: il
   le poussait de 75 px VERS LE BAS à l'intérieur du tableau, et la première
   ligne passait au-dessus.

   LA VRAIE RÉPONSE EST D'ASSUMER CE CONTENEUR au lieu de lutter contre lui. Le
   tableau reçoit sa propre fenêtre — une hauteur maximale — et l'en-tête colle
   à `top:0` DEDANS. Il reste alors visible pendant qu'on parcourt les 8432
   contacts, sans jamais chevaucher quoi que ce soit.

   `max-height` ET NON `height`: un tableau de trois lignes garde trois lignes.
   La règle ne s'applique qu'à ceux qui dépassent, c'est-à-dire aux listes, et
   laisse tranquilles les petits tableaux à l'intérieur des fiches. */
.tw { overflow:auto; max-height:calc(100vh - var(--h-tete) - 10px); }
/* UNE TABLE HORS `.zone` PORTE SA GOUTTIÈRE ELLE-MÊME. [Anna, 21.08.2026]
   « nada nunca nenhum texto tem que ficar colado no menu ». Audit fait dans un
   navigateur sur les seize écrans: quatre laissaient encore du texte à la
   frontière exacte du menu, et c'étaient tous des en-têtes de tableau — la
   liste des Événements, celle des Contacts, celle des Associations. Leur `.tw`
   n'est pas dans une `.zone`, et rien d'autre ne leur donnait de marge.

   La règle vise `.tw` dont le parent n'est PAS une zone: celles qui y sont
   tiennent déjà leur gouttière de la zone, et une double marge se verrait. */
:not(.zone) > .tw { padding-left:26px; padding-right:26px; }
table { border-collapse:collapse; width:100%; font-size:14px; }
th, td { padding:8px 14px; border-bottom:1px solid var(--trait); text-align:left;
         vertical-align:top; }
/* `top:0` et non `var(--h-tete)`: le collant se résout contre `.tw`, qui
   commence déjà sous la barre de titre. Décaler encore le ferait descendre
   dans le tableau — c'est exactement le défaut qu'on vient de corriger. */
th { background:var(--fond2); font-size:11.5px; text-transform:uppercase;
     letter-spacing:.04em; color:var(--encre); font-weight:700;
     position:sticky; top:0; z-index:10; }
tbody tr:hover { background:var(--fond2); }
td .sec { color:var(--doux); font-size:12.5px; }
/* UNE BARRE DU HAUT QUI N'EST PLUS UN FORMULAIRE. [21.08.2026] Quand le menu
   de colonne a remplacé les filtres, il n'est resté sur certains écrans qu'un
   bouton « + nouvelle fiche »: plus rien à soumettre, donc plus de `<form>`,
   donc la règle `form.filtres` ne l'atteignait plus et le bouton se collait au
   menu. La règle vit ici parce que c'est le seul fichier que tous les écrans
   émettent — quatrième fois aujourd'hui qu'un style posé dans une feuille
   partielle ne rend pas là où on l'attend. */
.barre-neuf { margin:0; padding:14px 26px; border-bottom:1px solid var(--trait);
        background:var(--fond2); text-align:right; }
/* LE BOUTON JAUNE, DÉFINI UNE FOIS. [Anna, 21.08.2026] « ela não tem a cara
   dos outros botões, arruma isso para ficar amarelo ».

   Il était écrit dans CINQ écrans — associations, bookings, contacts,
   calendrier, personnel — à l'identique, et pas dans les Offres: le bouton y
   sortait donc en lien nu. C'est la cinquième fois aujourd'hui qu'une règle
   posée dans une feuille partielle ne rend pas là où on l'attend, et la
   cinquième fois que le remède est le même: la règle habite le seul fichier
   que tout le monde charge. Les cinq copies sont retirées; elles étaient
   identiques au `white-space` près, gardé ici parce qu'un bouton d'action ne
   doit jamais se couper en deux lignes. */
.neuf { padding:8px 16px; background:var(--jaune); color:#0d0d0d; border-radius:4px;
        text-decoration:none; font-size:13.5px; font-weight:600; white-space:nowrap; }
.neuf:hover { filter:brightness(.94); }
/* LA BARRE DE RECHERCHE EST À DROITE. [Anna, 21.08.2026] « colocar os campos
   de recherche alinhados à direita », « e todos os outros do site ».

   ET IL FAUT NEUTRALISER LES `margin-left:auto`. Plusieurs écrans poussaient
   leur bouton « + nouveau » à droite par une marge automatique; cette marge
   absorbe tout l'espace libre, donc `justify-content:flex-end` seul n'aurait
   rien déplacé — les champs seraient restés à gauche et j'aurais cru avoir
   corrigé. La règle plus spécifique la désarme, quel que soit l'ordre des
   feuilles: c'est la spécificité qui tranche, pas la position. */
form.filtres { padding:14px 26px; border-bottom:1px solid var(--trait); display:flex;
        gap:10px; flex-wrap:wrap; align-items:center; justify-content:flex-end;
        background:var(--fond2); }
form.filtres .neuf, form.filtres .vider { margin-left:0; }
.barre-neuf { text-align:right; }
input[type=search], input[type=text], input[type=date], input[type=datetime-local],
input[type=number], input[type=email], input[type=tel], input[type=url],
input[type=password], textarea, select, button { font-family:inherit; }

/* ── LES CHAMPS DE DATE, COMME LES AUTRES ──────────────────────────── [16.08.2026]
   Demandé par Anna pour tout le site. Un `input[type=date]` est dessiné par le
   navigateur: il ignore la taille, la police et le fond des champs voisins, et
   sort une boîte étroite marquée « dd/mm/yyyy » qui n'a rien à voir avec le
   champ de texte d'à côté. Sur la même ligne, l'œil lit deux formulaires.

   Trois choses le remettent d'aplomb, et les trois sont nécessaires:
     `appearance:none`   retire l'habillage natif de WebKit, sans quoi le fond
                         et le rayon restent ceux du système;
     `min-height`        le champ natif est plus bas que les autres de quelques
                         pixels, ce qui se voit dès qu'ils sont alignés;
     `min-width`         sans elle il se réduit au strict nécessaire et devient
                         le plus petit champ de la ligne.

   L'icône de calendrier est gardée: c'est elle qui dit que le champ s'ouvre.
   On la teinte simplement pour qu'elle ne reste pas bleue sur un thème noir et
   blanc. */
input[type=date], input[type=datetime-local], input[type=time], input[type=month],
input[type=number], input[type=email], input[type=tel], input[type=url] {
        appearance:none; -webkit-appearance:none;
        padding:8px 12px; min-height:38px; min-width:150px;
        border:1px solid var(--trait); border-radius:4px; font-size:15px;
        background:var(--papier); color:var(--encre); box-sizing:border-box; }
input[type=datetime-local] { min-width:210px; }
input[type=date]:focus, input[type=datetime-local]:focus, input[type=number]:focus,
input[type=email]:focus, input[type=tel]:focus, input[type=url]:focus,
input[type=search]:focus, input[type=text]:focus { outline:2px solid var(--encre);
        outline-offset:-1px; border-color:var(--encre); }
input[type=date]::-webkit-calendar-picker-indicator,
input[type=datetime-local]::-webkit-calendar-picker-indicator { cursor:pointer; opacity:.55; }
input[type=date]:hover::-webkit-calendar-picker-indicator,
input[type=datetime-local]:hover::-webkit-calendar-picker-indicator { opacity:1; }
/* Firefox met une flèche de pas sur les champs numériques: elle ne sert à rien
   pour un montant et déplace le texte. */
input[type=number] { -moz-appearance:textfield; }
input[type=search], input[type=text] { flex:1 1 240px; min-width:180px; padding:8px 12px;
        border:1px solid var(--trait); border-radius:4px; font-size:15px;
        background:var(--papier); color:var(--encre); }
select { padding:8px 10px; border:1px solid var(--trait); border-radius:4px;
        font-size:14px; background:var(--papier); color:var(--encre); max-width:220px; }
button { padding:8px 18px; border:0; background:var(--encre); color:var(--papier);
        border-radius:4px; font-size:14px; cursor:pointer; }
a.vider { color:var(--doux); font-size:13px; }

/* ── LES BOUTONS D'ENREGISTREMENT, TOUJOURS À DROITE ───────────────── [16.08.2026]
   Demandé par Anna pour tout le dashboard. C'est le geste qui termine: on lit le
   formulaire de haut en bas et de gauche à droite, et le bouton se trouve au
   bout du chemin, pas au début. À gauche il se lit avant d'avoir rempli.

   `.act` et `.actions` sont les deux conteneurs déjà employés — le second garde
   son lien « supprimer » collé à gauche par son propre `margin-left:auto` sur
   `.sup`, qui continue de fonctionner puisqu'on ne change que l'alignement du
   conteneur. */
.act { display:flex; justify-content:flex-end; gap:10px; align-items:center; margin-top:22px; }
.actions { justify-content:flex-end; }
/* Le lien de suppression reste à l'opposé des boutons: il ne doit jamais se
   trouver sous le doigt qui vise « Enregistrer ». */
.actions .sup { margin-right:auto; margin-left:0; }
/* `.sup` ÉTAIT UN LIEN, C'EST DEVENU UN BOUTON. [21.08.2026] Le lien
   fabriquait son formulaire en JavaScript et se cassait à l'échappement du
   champ CSRF; il est maintenant un `submit` du formulaire de la fiche. Sans
   ces règles il sortirait avec l'aspect gris du système, à côté d'un
   « Enregistrer » noir: le geste le plus dangereux de la page aurait l'air du
   plus banal. Il garde donc l'allure d'un lien discret, en rouge, à l'opposé
   du bouton d'enregistrement. */
button.sup { background:none; border:0; padding:0; font:inherit; font-size:13.5px;
        color:#c8452f; cursor:pointer; text-decoration:underline;
        text-underline-offset:2px; }
button.sup:hover { color:#9c3524; }
button.sup:focus-visible { outline:2px solid #c8452f; outline-offset:3px; }
nav.pages { padding:16px 26px; display:flex; gap:7px; align-items:center; flex-wrap:wrap; }
nav.pages a, nav.pages span { padding:5px 11px; border:1px solid var(--trait);
        border-radius:4px; text-decoration:none; font-size:13px; }
nav.pages span.ici { background:var(--encre); color:var(--papier); border-color:var(--encre); }
nav.pages .mut { border:0; color:var(--doux); }
.vide { padding:44px 26px; color:var(--doux); }

/* LE MESSAGE DE CONFIRMATION VIT ICI, PAS DANS LA FEUILLE DES FORMULAIRES.
   [Anna, 21.08.2026] « nada nunca nenhum texto tem que ficar colado no menu ».

   Il était défini dans `dash_form_style()`, appelée par CINQ écrans, alors que
   `dash_flash_html()` est utilisée par ONZE. Sur les six autres le message
   sortait sans une seule règle: pas de marge, pas de fond, collé à la barre
   noire. C'est ce qu'Anna a vu après avoir collé son jeton bexio.

   Une règle qui vit dans une feuille que la page n'émet pas ne se voit pas à
   la relecture — c'est le troisième cas aujourd'hui, après le style des
   grilles d'association et celui de la carte. Le remède est le même: la règle
   habite le fichier que tout le monde charge. */
.flash { margin:16px 26px 0; padding:11px 16px; font-size:13.5px;
        border-left:4px solid var(--jaune); background:var(--fond2); }
.flash.err { border-left-color:var(--orange); }
/* ── LA COPIE LOCALE SE DÉNONCE ELLE-MÊME ─────────────────────── [17.08.2026]
   Troisième fois en deux jours qu'Anna lit la copie de test en croyant lire le
   site, et chaque fois cela produit un rapport de panne qui n'en est pas un:
   l'envoi d'e-mail « cassé » le 16 (la copie n'a pas de SMTP, exprès), les
   « 86 dates » du 17 (la production en a 51), et l'écran Administration « tout
   faux » (la copie porte des tâches générées AVANT la correction des
   territoires du 16.08; la production est juste).

   LE COÛT N'EST PAS LE MALENTENDU, C'EST CE QU'IL APPREND. Trois fausses
   alertes de suite et l'on cesse de signaler ce qu'on voit — or c'est
   exactement ce qu'on lui demande de faire.

   ON NE PEUT PAS COMPTER SUR L'ADRESSE. « localhost:8080 » et
   « le-voisin.com » se ressemblent dans une barre d'onglets à demi masquée, et
   les deux pages sont identiques au pixel près. Il faut donc que la page le
   dise, en grand, dans le champ de vision — pas dans un coin.

   LE TEST PORTE SUR L'HÔTE ET NON SUR LA BASE, parce qu'il doit être vrai même
   quand la copie locale pointe par erreur ailleurs: si l'on ouvre le site par
   localhost, on n'est pas sur le site public, quoi qu'il serve. Et il ne peut
   pas se déclencher en production — le-voisin.com n'est jamais localhost. */
.local-avert { background:var(--orange); color:#fff; padding:7px 26px; font-size:13px;
  font-weight:600; letter-spacing:.02em; grid-column:1 / -1; }
.local-avert code { background:rgba(0,0,0,.18); padding:1px 6px; border-radius:3px;
  font-weight:400; }
</style>
</head>
<body>
<div class="enveloppe">

<?php
$hote = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$hote = (string)preg_replace('/:\d+$/', '', $hote);
if (in_array($hote, ['localhost', '127.0.0.1', '[::1]', '::1'], true)): ?>
  <div class="local-avert">Copie locale de test — ce ne sont pas les données du site.
    Le vrai dashboard est sur <code>le-voisin.com/dashboard.php</code></div>
<?php endif; ?>

<aside>
  <a class="marque" href="/dashboard.php" aria-label="Le Voisin">
    <?php
    /* Le vrai logo, et la même cascade que le site public: d'abord celui posé
       dans les réglages du CMS, sinon le fichier livré avec le code, et le
       texte seulement si les deux manquent. Une seule source pour les deux
       côtés: changer le logo dans les réglages le change ici aussi. */
    $logoId  = (int)setting('logo_image_id', '0');
    $logoImg = $logoId ? Img::row($logoId) : null;
    if ($logoImg) {
        printf('<img src="%s" alt="Le Voisin">',
               e(upload_url('i/' . $logoImg['id'] . '/orig.' . $logoImg['ext'])));
    } elseif (is_file(LV_ROOT . '/assets/img/logo-levoisin.png')) {
        printf('<img src="%s" alt="Le Voisin">', e(url('/assets/img/logo-levoisin.png')));
    } else {
        echo 'LE&nbsp;VOISIN';
    }
    /* DIRE LEQUEL DES DEUX ON REGARDE. [Anna, 21.08.2026]
       Le CMS et le tableau de bord portent le même logo depuis aujourd'hui, et
       à côté de « Administration » sous celui du CMS, celui-ci ne disait rien.
       Deux barres noires identiques, deux onglets ouverts: on ne sait plus
       lequel on a devant soi. */
    echo '<span class="sous">Dashboard</span>';
    ?>
  </a>
  <div class="rail">
  <?php
  $branche = dash_parent($ecranActif);
  $entree = function (string $clef, string $libelle, bool $enfant) use ($ecranActif): void {
      $cls = ($clef === $ecranActif ? 'ici' : '') . ($enfant ? ' fils' : '');
      if (dash_existe($clef)) {
          printf('<a href="/dashboard.php?e=%s" class="%s">%s</a>',
                 e($clef), trim($cls), e($libelle));
      } else {
          printf('<span class="mort%s" title="Pas encore écrit">%s</span>',
                 $enfant ? ' fils' : '', e($libelle));
      }
  };
  foreach (DASH_ECRANS as $clef => [$libelle, $etat, $enfants]) {
      /* CE QUE LE RÔLE NE PEUT PAS OUVRIR NE FIGURE PAS. [16.08.2026]

         Différence voulue avec les écrans « pas encore écrits », qui restent
         en gris: ceux-là parlent de l'outil et la carte doit être vraie pour
         tout le monde. Un écran fermé par le rôle, lui, parle de la personne.
         L'afficher en gris ne lui apprendrait rien d'utile et transformerait
         son menu en liste de portes closes. */
      if (!dash_visible($clef)) continue;

      $entree($clef, $libelle, false);
      /* Les sous-écrans ne s'affichent que sous leur branche ouverte: dix-huit
         entrées toutes dépliées font un mur, et le menu cesse d'être lisible. */
      if ($enfants && $branche === $clef) {
          foreach ($enfants as $c => [$l, $e]) {
              if (dash_visible($c)) $entree($c, $l, true);
          }
      }
  }
  ?>
  </div>
  <div class="pied">
    <?= e($u['name'] ?? $u['email'] ?? '') ?><br>
    <a href="/admin/">Administration du site</a><br>
    <a href="/admin/logout.php">Sortir</a>
  </div>
</aside>

<main>
<div class="tete">
  <h1><?= e(dash_libelle($ecranActif)) ?></h1>
  <?php if ($sousTitre !== ''): ?><span class="sst"><?= $sousTitre ?></span><?php endif; ?>
  <span class="droite">dashboard <?= date('d.m.Y') ?></span>
</div>
<?php
}

function dash_bas(): void
{
    ?>
</main>
</div>
<?php /* ── LA HAUTEUR RÉELLE DE LA BARRE DE TITRE ───────────────────── [16.08.2026]
     `--h-tete` était une valeur devinée — 59 px — et elle était fausse dès que le
     sous-titre passait à la ligne ou que la police rendait un peu plus haut. Les
     en-têtes de tableau se collaient alors DERRIÈRE la barre: on voyait le premier
     contact et plus les noms de colonnes, ce qu'Anna a vu tout de suite.

     On mesure donc, au lieu de supposer. Au chargement et à chaque
     redimensionnement, parce que c'est en changeant la largeur que le sous-titre
     passe à la ligne. Sans JavaScript la valeur de repli tient: elle est juste
     dans le cas courant, et le défaut redevient cosmétique. */ ?>
<script>
(function () {
  var t = document.querySelector('.tete');
  if (!t) return;
  function mesurer() {
    document.documentElement.style.setProperty('--h-tete', t.offsetHeight + 'px');
  }
  mesurer();
  addEventListener('resize', mesurer);
  /* La police se charge après le premier rendu et change la hauteur d'un ou deux
     pixels: on remesure quand elle est prête. */
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(mesurer);
})();
</script>
</body>
</html>
<?php
}

/**
 * L'écran déclaré mais pas encore écrit.
 *
 * Il dit ce qu'il fera et où vit la chose aujourd'hui, pour que cliquer dessus
 * apprenne quelque chose au lieu de montrer une page blanche.
 */
function dash_a_faire(string $clef): void
{
    dash_haut($clef);
    ?>
    <div class="avis">
      <h2>Pas encore repris</h2>
      <p>Cet écran existe dans le dashboard actuel, sur Apps Script, et n'a pas
         encore été porté ici.</p>
      <p>Il figure dans le menu parce que la carte doit être vraie: voilà ce que
         l'application couvrira, et voilà où en est la reprise. Le cacher
         donnerait pendant des mois l'impression d'un outil qui sait faire trois
         choses.</p>
    </div>
    <?php
    dash_bas();
}

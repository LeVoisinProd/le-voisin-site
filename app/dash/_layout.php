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
:root { --encre:#0d0d0d; --papier:#fff; --doux:#6b6b6b; --trait:#e4e4e4;
        --jaune:#FFD24D; --orange:#FF7142; --fond2:#f7f7f7; --barre:#111; }
@media (prefers-color-scheme: dark) { :root:not([data-theme=light]) {
        --encre:#f0f0f0; --papier:#151515; --doux:#9a9a9a; --trait:#2c2c2c;
        --fond2:#1d1d1d; --barre:#0a0a0a; } }
* { box-sizing:border-box; }
body { margin:0; background:var(--papier); color:var(--encre); font-size:15px;
       font-family:'Space Grotesk',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
       line-height:1.5; }
a { color:inherit; }

/* La disposition: un rail de navigation à gauche, le contenu à droite. Sur
   petit écran le rail passe au-dessus et défile à l'horizontale. */
.enveloppe { display:grid; grid-template-columns:212px minmax(0,1fr); min-height:100vh; }
@media (max-width:820px) { .enveloppe { grid-template-columns:1fr; } }

aside { background:var(--barre); color:#e8e8e8; padding:18px 0 40px; }
aside .marque { padding:0 18px 18px; font-size:14px; font-weight:600; letter-spacing:.02em; }
aside .marque span { color:var(--jaune); }
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
.tete { border-bottom:2px solid var(--encre); padding:15px 26px; display:flex;
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
.tw { overflow-x:auto; }
table { border-collapse:collapse; width:100%; font-size:14px; }
th, td { padding:8px 14px; border-bottom:1px solid var(--trait); text-align:left;
         vertical-align:top; }
th { background:var(--fond2); font-size:11.5px; text-transform:uppercase;
     letter-spacing:.04em; color:var(--doux); position:sticky; top:0; }
tbody tr:hover { background:var(--fond2); }
td .sec { color:var(--doux); font-size:12.5px; }
form.filtres { padding:14px 26px; border-bottom:1px solid var(--trait); display:flex;
        gap:10px; flex-wrap:wrap; align-items:center; background:var(--fond2); }
input[type=search], input[type=text], select, button { font-family:inherit; }
input[type=search], input[type=text] { flex:1 1 240px; min-width:180px; padding:8px 12px;
        border:1px solid var(--trait); border-radius:4px; font-size:15px;
        background:var(--papier); color:var(--encre); }
select { padding:8px 10px; border:1px solid var(--trait); border-radius:4px;
        font-size:14px; background:var(--papier); color:var(--encre); max-width:220px; }
button { padding:8px 18px; border:0; background:var(--encre); color:var(--papier);
        border-radius:4px; font-size:14px; cursor:pointer; }
a.vider { color:var(--doux); font-size:13px; }
nav.pages { padding:16px 26px; display:flex; gap:7px; align-items:center; flex-wrap:wrap; }
nav.pages a, nav.pages span { padding:5px 11px; border:1px solid var(--trait);
        border-radius:4px; text-decoration:none; font-size:13px; }
nav.pages span.ici { background:var(--encre); color:var(--papier); border-color:var(--encre); }
nav.pages .mut { border:0; color:var(--doux); }
.vide { padding:44px 26px; color:var(--doux); }
</style>
</head>
<body>
<div class="enveloppe">

<aside>
  <div class="marque">LE <span>VOISIN</span></div>
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
      $entree($clef, $libelle, false);
      /* Les sous-écrans ne s'affichent que sous leur branche ouverte: dix-huit
         entrées toutes dépliées font un mur, et le menu cesse d'être lisible. */
      if ($enfants && $branche === $clef) {
          foreach ($enfants as $c => [$l, $e]) $entree($c, $l, true);
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

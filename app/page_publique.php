<?php
/**
 * L'enveloppe des pages publiques hors CMS. [16.08.2026]
 *
 * Sert le portail d'advancing et le formulaire de demande de booking. Écrite
 * d'abord pour le premier, généralisée quand le second est arrivé: les deux
 * s'ouvrent sans compte, ne font qu'une chose, et doivent avoir la même allure
 * — y compris leurs pages d'erreur, parce qu'un lien mort doit montrer qu'on
 * est au bon endroit et que c'est le lien qui est vieux, pas le site.
 *
 * PAS DE MENU, PAS DE LIENS VERS LE SITE. Ce n'est pas de l'avarice: cette
 * page s'ouvre sans compte, et tout lien qu'on y met est une invitation à se
 * promener. Elle fait une chose.
 *
 * Attend $titre et $corps.
 */
declare(strict_types=1);
/** @var string $titre */
/** @var string $corps */
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titre) ?></title>
<style>
:root{
  --papier:#fff; --encre:#0d0d0d; --doux:#6a6a68; --trait:#e6e6e4;
  --fond2:#fafaf9; --jaune:#FFD24D; --vert:#7bb33a; --orange:#e2653a;
}
@media (prefers-color-scheme:dark){
  :root{--papier:#101010;--encre:#f2f2f0;--doux:#9a9a98;--trait:#2a2a2a;--fond2:#171717}
}
*{box-sizing:border-box}
body{margin:0;background:var(--papier);color:var(--encre);
  font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  -webkit-font-smoothing:antialiased}
.w{max-width:640px;margin:0 auto;padding:38px 22px 70px}
h1{font-size:25px;letter-spacing:-.02em;margin:0 0 6px}
h2{font-size:15px;text-transform:uppercase;letter-spacing:.1em;color:var(--doux);
  margin:34px 0 4px;font-weight:600}
p{margin:0 0 14px}
.chapo{font-size:17px;line-height:1.45;padding-bottom:16px;border-bottom:1px solid var(--trait);margin-bottom:20px}
.msg{background:var(--fond2);border-left:3px solid var(--vert);padding:12px 15px;margin:0 0 22px}
.vide{color:var(--doux)}
.ch{padding:15px 0;border-bottom:1px solid var(--trait)}
.ch.refait{border-left:3px solid var(--orange);padding-left:13px}
label{display:block;font-weight:600;font-size:15.5px;margin-bottom:5px}
.ob{color:var(--orange);font-weight:700}
.cons{font-size:13.5px;color:var(--doux);margin:0 0 8px}
.cons.refait{color:var(--orange)}
.deja{font-size:13.5px;margin:0 0 8px}
.ok{font-size:13px;color:var(--vert);margin:7px 0 0}
input[type=text],input[type=number],input[type=date],input[type=time],textarea,select{
  width:100%;padding:9px 11px;font:inherit;font-size:15px;
  border:1px solid var(--trait);border-radius:5px;
  background:var(--papier);color:var(--encre)}
input[type=file]{font-size:14px;max-width:100%}
textarea{resize:vertical}
input:focus,textarea:focus,select:focus{outline:2px solid var(--encre);outline-offset:1px;border-color:transparent}
button{margin-top:26px;padding:11px 26px;font:inherit;font-weight:600;font-size:15px;
  border:0;border-radius:5px;background:var(--encre);color:var(--papier);cursor:pointer}
button:hover{opacity:.88}
button:focus-visible{outline:2px solid var(--encre);outline-offset:2px}
.pied{margin-top:14px;font-size:13px;color:var(--doux)}
@media (prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
</head>
<body>
<div class="w">
  <h1><?= e($titre) ?></h1>
  <?= $corps ?>
</div>
</body>
</html>

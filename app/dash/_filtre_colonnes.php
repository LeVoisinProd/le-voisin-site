<?php
/**
 * Filtre par colonne, pour les tableaux du dashboard.  [Anna, 21.08.2026]
 *
 * « dans la page finances du dashboard, laisser la possibilité de filtres par
 * colonne — dans toutes les parties, relevé, demande de fonds ».
 *
 * CE QUI SE PASSE DANS LE NAVIGATEUR ET POURQUOI. Les filtres existants de cet
 * écran rechargent la page: c'est juste pour la saison et l'association, qui
 * changent la REQUÊTE. Un filtre par colonne ne change pas la requête, il
 * cache des lignes déjà là — trente-cinq dates, cent demandes. Un aller-retour
 * par frappe coûterait une seconde pour rien, et ferait perdre la position
 * dans la page à chaque lettre.
 *
 * SANS JAVASCRIPT ON PERD LE FILTRE, PAS LES DONNÉES. La ligne de filtres est
 * construite par le script; si rien ne s'exécute, le tableau reste entier et
 * lisible. C'est la règle inverse de celle des formulaires d'`_assoc_onglets`,
 * et pour la bonne raison: là on saisit des numéros AVS qu'on ne retape pas,
 * ici on regarde.
 *
 * LES TOTAUX SE REFONT, ET C'EST LE POINT DÉLICAT. Un relevé filtré dont le
 * pied garde la somme de tout est un document qui ment: on lit « Total » sous
 * six lignes et on croit qu'il les concerne. Chaque pied marqué `data-somme`
 * est donc recalculé sur les lignes VISIBLES.
 *
 * ET LA SOMME NE RELIT PAS LE TEXTE AFFICHÉ. « 12 345 » avec une espace de
 * millier, un tiret pour le vide, une devise collée: reconstruire un nombre à
 * partir de ça se casse au premier format qui change. Chaque cellule chiffrée
 * porte sa valeur brute en `data-v`, et c'est elle qu'on additionne.
 *
 * COMMENT ON S'EN SERT
 *   <table data-filtres>                        active le filtre
 *   <th data-f="choix">                          liste déroulante des valeurs présentes
 *   <th data-f="non">                            colonne non filtrable
 *   <th>                                          champ de texte (défaut)
 *   <td data-v="1234.50">                        valeur brute, pour les totaux
 *   <td data-somme> dans <tfoot>                 pied à recalculer
 */
declare(strict_types=1);

/* Une seule fois par page, même si deux tableaux l'utilisent. */
if (defined('LV_FILTRE_COLONNES')) return;
define('LV_FILTRE_COLONNES', true);
?>
<style>
.f-ligne th{padding:6px 8px 10px;border-bottom:1px solid var(--trait);vertical-align:top}
.f-ligne input,.f-ligne select{width:100%;box-sizing:border-box;padding:5px 7px;font:inherit;
  font-size:12px;font-weight:400;border:1px solid var(--trait);border-radius:4px;
  background:var(--papier);color:var(--encre)}
.f-ligne input::placeholder{color:var(--doux);opacity:.7}
.f-ligne input:focus,.f-ligne select:focus{outline:2px solid var(--jaune,#FFD24D);outline-offset:-1px}
/* Le compte des lignes gardées, et le bouton qui rend tout. Ils n'apparaissent
   qu'une fois un filtre posé: un écran non filtré n'a rien à annoncer. */
.f-etat{display:flex;align-items:center;gap:12px;margin:0 0 10px;font-size:12.5px;
  color:var(--doux)}
.f-etat[hidden]{display:none}
.f-etat b{color:var(--encre);font-variant-numeric:tabular-nums}
.f-etat button{padding:4px 11px;font:inherit;font-size:12px;cursor:pointer;
  border:1px solid var(--trait);border-radius:5px;background:transparent;color:var(--encre)}
.f-etat button:hover{border-color:var(--encre)}
.f-vide td{padding:18px 10px;color:var(--doux);font-size:13.5px}
</style>
<script>
(function () {
  'use strict';

  /* Sans accents et sans casse: on tape « geneve » et on trouve « Genève ».
     Qui cherche dans un tableau ne met pas les accents. */
  var pli = function (s) {
    return (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
  };

  /* Le texte d'une cellule, y compris quand elle contient un menu: c'est
     l'option choisie qui compte, pas la liste entière. */
  var texte = function (td) {
    var sel = td.querySelector('select');
    if (sel) return sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
    return td.textContent;
  };

  var nombre = function (v) {
    var n = parseFloat(v);
    return isFinite(n) ? n : 0;
  };

  /* Même écriture que number_format($v, 0, ',', ' ') côté PHP: espace de
     millier, pas de décimale. Deux formats pour le même tableau se
     remarqueraient tout de suite. */
  var ecrire = function (n) {
    return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  };

  /* ATTENDRE QUE LA PAGE SOIT LÀ. Ce partiel est chargé AVANT les tableaux —
     il doit l'être, pour que son style s'applique dès le premier rendu — donc
     au moment où ce script s'exécute il n'y a encore rien à filtrer. Sans cette
     attente la ligne de filtres ne se construit jamais, sans la moindre erreur
     en console: le sélecteur ne trouve simplement rien. [21.08.2026] */
  var poser = function () {
  document.querySelectorAll('table[data-filtres]').forEach(function (tbl) {
    var thead = tbl.tHead, tbody = tbl.tBodies[0];
    if (!thead || !tbody) return;
    var ths   = Array.prototype.slice.call(thead.rows[thead.rows.length - 1].cells);
    var lignes = Array.prototype.slice.call(tbody.rows);
    if (!lignes.length) return;

    /* La ligne de filtres, une case par colonne. */
    var tr = thead.insertRow(-1);
    tr.className = 'f-ligne';
    var champs = [];

    ths.forEach(function (th, i) {
      var cel = document.createElement('th');
      var mode = th.getAttribute('data-f') || 'texte';

      if (mode === 'non') { champs.push(null); tr.appendChild(cel); return; }

      if (mode === 'choix') {
        var vues = [], sel = document.createElement('select');
        sel.innerHTML = '<option value="">tous</option>';
        lignes.forEach(function (l) {
          var t = (texte(l.cells[i]) || '').trim();
          if (t && t !== '·' && vues.indexOf(t) === -1) vues.push(t);
        });
        vues.sort(function (a, b) { return a.localeCompare(b, 'fr'); });
        vues.forEach(function (v) {
          var o = document.createElement('option');
          o.value = pli(v); o.textContent = v; sel.appendChild(o);
        });
        cel.appendChild(sel); champs.push(sel);
      } else {
        var inp = document.createElement('input');
        inp.type = 'search';
        inp.placeholder = (th.textContent || '').trim().toLowerCase();
        cel.appendChild(inp); champs.push(inp);
      }
      tr.appendChild(cel);
    });

    /* L'état: combien de lignes restent, et de quoi tout rendre. */
    var etat = document.createElement('p');
    etat.className = 'f-etat';
    etat.hidden = true;
    etat.innerHTML = '<span><b class="n"></b> sur <b class="tot">'
                   + lignes.length + '</b> lignes</span>';
    var vider = document.createElement('button');
    vider.type = 'button';
    vider.textContent = 'tout afficher';
    etat.appendChild(vider);
    var hote = tbl.closest('.tw') || tbl;
    hote.parentNode.insertBefore(etat, hote);

    /* Le message quand rien ne passe: une table vide sans un mot se lit comme
       une table cassée. */
    var rienTr = document.createElement('tr');
    rienTr.className = 'f-vide';
    rienTr.hidden = true;
    var rienTd = document.createElement('td');
    rienTd.colSpan = ths.length;
    rienTd.textContent = 'Aucune ligne ne correspond à ces filtres.';
    rienTr.appendChild(rienTd);
    tbody.appendChild(rienTr);

    /* Les pieds à recalculer, avec leur colonne réelle: un colspan décale tout
       ce qui suit, et sommer la mauvaise colonne serait pire que ne rien
       sommer. */
    var pieds = [];
    if (tbl.tFoot) {
      Array.prototype.forEach.call(tbl.tFoot.rows, function (fr) {
        var col = 0;
        Array.prototype.forEach.call(fr.cells, function (td) {
          if (td.hasAttribute('data-somme')) pieds.push({ td: td, col: col });
          col += td.colSpan || 1;
        });
      });
    }

    var passer = function () {
      var actifs = 0, gardees = 0;

      lignes.forEach(function (l) {
        var ok = true;
        champs.forEach(function (ch, i) {
          if (!ch || !ch.value) return;
          var v = pli(ch.value);
          if (!v) return;
          var t = pli(texte(l.cells[i]));
          if (ch.tagName === 'SELECT' ? t !== v : t.indexOf(v) === -1) ok = false;
        });
        l.hidden = !ok;
        if (ok) gardees++;
      });

      champs.forEach(function (ch) { if (ch && ch.value) actifs++; });

      pieds.forEach(function (p) {
        var s = 0;
        lignes.forEach(function (l) {
          if (l.hidden) return;
          var td = l.cells[p.col];
          if (td && td.hasAttribute('data-v')) s += nombre(td.getAttribute('data-v'));
        });
        /* Écrire dans le <strong> s'il y en a un: remplacer le contenu de la
           cellule effacerait le gras, et un total qui maigrit en cours de
           filtrage se lit comme une autre ligne. */
        var cible = p.td.querySelector('strong') || p.td;
        cible.textContent = s ? ecrire(s) : '';
      });

      rienTr.hidden = gardees > 0;
      etat.hidden = actifs === 0;
      etat.querySelector('.n').textContent = gardees;
    };

    champs.forEach(function (ch) {
      if (!ch) return;
      ch.addEventListener('input', passer);
      ch.addEventListener('change', passer);
    });
    vider.addEventListener('click', function () {
      champs.forEach(function (ch) { if (ch) ch.value = ''; });
      passer();
    });
  });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', poser);
  } else {
    poser();
  }
})();
</script>

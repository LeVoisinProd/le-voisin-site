<?php
/**
 * Le filtre automatique des colonnes, comme dans un tableur.  [Anna, 21.08.2026]
 *
 * « o filtro que pedi nas colunas deveria ser um filtro com as funcionalidades
 * como no Excel, não precisa criar uma casa abaixo do nome das colunas ».
 *
 * PREMIÈRE VERSION: UNE LIGNE DE CHAMPS SOUS LES EN-TÊTES. Elle marchait et
 * elle coûtait cher: une rangée permanente sur toute la largeur, et surtout
 * une largeur imposée à chaque colonne — un champ de recherche vaut vingt
 * caractères de large, une liste vaut son option la plus longue. Sur les
 * Événements elle poussait la date à 196 px pour un contenu de 110. Le tableur
 * ne fait pas cela: il met une petite flèche dans le titre et n'ouvre rien
 * tant qu'on ne clique pas.
 *
 * CE QUE FAIT LE MENU, ET DANS CET ORDRE. Trier A→Z et Z→A, chercher dans les
 * valeurs, puis cocher celles qu'on garde. C'est l'ordre du tableur, et il
 * suit la façon dont on s'en sert: on trie pour voir, on cherche pour trouver,
 * on coche pour restreindre.
 *
 * DES CASES À COCHER PLUTÔT QU'UN CHAMP DE TEXTE. « Genève » et « Genève 2 »
 * ne se distinguent pas en tapant; en cochant, si. Et l'on voit d'un coup
 * d'œil TOUT ce que la colonne contient, ce qu'un champ de saisie ne dit
 * jamais — c'est ainsi qu'on découvre une orthographe en double.
 *
 * LE COMPTE EST À CÔTÉ DE CHAQUE VALEUR. « Genève 12 · Genêve 1 » montre la
 * faute de frappe mieux que n'importe quel contrôle.
 *
 * LES TOTAUX SE REFONT SUR LES LIGNES VISIBLES. Un relevé filtré dont le pied
 * garde la somme de tout est un document qui ment. Et la somme ne relit pas le
 * texte affiché — chaque cellule chiffrée porte sa valeur brute en `data-v`.
 *
 * SANS JAVASCRIPT ON PERD LE FILTRE, PAS LES DONNÉES. Les flèches sont posées
 * par le script; s'il ne s'exécute pas, le tableau reste entier et lisible.
 *
 * COMMENT ON S'EN SERT
 *   <table data-filtres>            active le filtre sur toutes les colonnes
 *   <th data-f="non">               colonne sans menu (les actions, par exemple)
 *   <td data-v="1234.50">           valeur brute: totaux justes et tri numérique
 *   <td data-somme> dans <tfoot>    pied à recalculer
 */
declare(strict_types=1);

/* Une seule fois par page, même si plusieurs tableaux l'utilisent. */
if (defined('LV_FILTRE_COLONNES')) return;
define('LV_FILTRE_COLONNES', true);
?>
<style>
/* La flèche vit DANS le titre et ne lui prend pas de largeur propre: c'est
   tout ce que la colonne gagne comme encombrement. */
table[data-filtres] th{position:relative}
.fc-b{margin-left:6px;padding:0 2px;border:0;background:none;cursor:pointer;
  color:var(--doux);font:inherit;font-size:9px;line-height:1;vertical-align:middle;
  opacity:.45;transition:.12s}
.fc-b:hover{opacity:1;color:var(--encre)}
th:hover .fc-b{opacity:.85}
/* Une colonne filtrée le dit: sans cela on cherche dix minutes pourquoi une
   ligne « a disparu ». */
.fc-b.actif{opacity:1;color:#c8452f}

.fc-m{position:absolute;top:100%;left:0;z-index:40;min-width:236px;max-width:330px;
  background:var(--papier);border:1px solid var(--encre);border-radius:6px;
  box-shadow:0 6px 22px rgba(0,0,0,.16);padding:8px;text-align:left;
  font-weight:400;text-transform:none;letter-spacing:0;font-size:13px;color:var(--encre)}
.fc-m[hidden]{display:none}
.fc-tri{display:flex;gap:5px;margin-bottom:7px}
.fc-tri button{flex:1;padding:5px 6px;font:inherit;font-size:11.5px;cursor:pointer;
  border:1px solid var(--trait);border-radius:4px;background:transparent;color:var(--encre)}
.fc-tri button:hover{border-color:var(--encre);background:var(--fond2)}
.fc-q{width:100%;box-sizing:border-box;padding:5px 7px;font:inherit;font-size:12px;
  border:1px solid var(--trait);border-radius:4px;margin-bottom:6px}
.fc-l{max-height:228px;overflow:auto;border:1px solid var(--trait);border-radius:4px;padding:4px}
.fc-l label{display:flex;gap:7px;align-items:center;padding:3px 5px;cursor:pointer;
  border-radius:3px;font-size:12.5px}
.fc-l label:hover{background:var(--fond2)}
.fc-l label[hidden]{display:none}
.fc-l input{margin:0}
.fc-l .t{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.fc-l .n{color:var(--doux);font-size:11px;font-variant-numeric:tabular-nums}
.fc-p{display:flex;gap:8px;align-items:center;margin-top:7px;font-size:12px}
.fc-p button{padding:4px 10px;font:inherit;font-size:12px;cursor:pointer;
  border:1px solid var(--trait);border-radius:4px;background:transparent;color:var(--encre)}
.fc-p .sep{margin-left:auto;color:var(--doux);font-variant-numeric:tabular-nums}

.fc-etat{display:flex;align-items:center;gap:12px;margin:0 0 10px;font-size:12.5px;color:var(--doux)}
.fc-etat[hidden]{display:none}
.fc-etat b{color:var(--encre);font-variant-numeric:tabular-nums}
.fc-etat button{padding:4px 11px;font:inherit;font-size:12px;cursor:pointer;
  border:1px solid var(--trait);border-radius:5px;background:transparent;color:var(--encre)}
.fc-etat button:hover{border-color:var(--encre)}
.fc-vide td{padding:18px 10px;color:var(--doux);font-size:13.5px}
</style>
<script>
(function () {
  'use strict';

  var pli = function (s) {
    return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
  };

  /* Le texte d'une cellule, y compris quand elle contient un menu: c'est
     l'option choisie qui compte, pas la liste entière. */
  var texte = function (td) {
    if (!td) return '';
    var sel = td.querySelector('select');
    if (sel) return sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
    return (td.textContent || '').replace(/\s+/g, ' ').trim();
  };

  /* Même écriture que number_format($v, 0, ',', ' ') côté PHP. */
  var ecrire = function (n) {
    return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  };

  var VIDE = '(vide)';

  var poser = function () {
    document.querySelectorAll('table[data-filtres]').forEach(function (tbl) {
      var thead = tbl.tHead, tbody = tbl.tBodies[0];
      if (!thead || !tbody) return;
      var ths    = Array.prototype.slice.call(thead.rows[thead.rows.length - 1].cells);
      var lignes = Array.prototype.slice.call(tbody.rows);
      if (!lignes.length) return;

      /* L'ordre d'origine, pour pouvoir y revenir: un tri qui ne se défait pas
         oblige à recharger la page. */
      var ordre0 = lignes.slice();
      var etats  = ths.map(function () { return null; });

      var etat = document.createElement('p');
      etat.className = 'fc-etat';
      etat.hidden = true;
      etat.innerHTML = '<span><b class="n"></b> sur <b>' + lignes.length + '</b> lignes</span>';
      var vider = document.createElement('button');
      vider.type = 'button';
      vider.textContent = 'tout afficher';
      etat.appendChild(vider);
      var hote = tbl.closest('.tw') || tbl;
      hote.parentNode.insertBefore(etat, hote);

      /* Une table vide sans un mot se lit comme une table cassée. */
      var rienTr = document.createElement('tr');
      rienTr.className = 'fc-vide';
      rienTr.hidden = true;
      var rienTd = document.createElement('td');
      rienTd.colSpan = ths.length;
      rienTd.textContent = 'Aucune ligne ne correspond à ces filtres.';
      rienTr.appendChild(rienTd);
      tbody.appendChild(rienTr);

      /* Les pieds à recalculer, avec leur colonne réelle: un colspan décale
         tout ce qui suit, et sommer la mauvaise colonne serait pire que ne
         rien sommer. */
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

      var appliquer = function () {
        var gardees = 0, actifs = 0;

        lignes.forEach(function (l) {
          var ok = true;
          etats.forEach(function (set, i) {
            if (!set) return;
            if (!set.has(texte(l.cells[i]) || VIDE)) ok = false;
          });
          l.hidden = !ok;
          if (ok) gardees++;
        });

        etats.forEach(function (set, i) {
          var b = ths[i] && ths[i].querySelector('.fc-b');
          if (b) b.classList.toggle('actif', !!set);
          if (set) actifs++;
        });

        pieds.forEach(function (p) {
          var s = 0;
          lignes.forEach(function (l) {
            if (l.hidden) return;
            var td = l.cells[p.col];
            if (td && td.hasAttribute('data-v')) {
              var n = parseFloat(td.getAttribute('data-v'));
              if (isFinite(n)) s += n;
            }
          });
          /* Écrire dans le <strong> s'il y en a un: remplacer le contenu de la
             cellule effacerait le gras. */
          (p.td.querySelector('strong') || p.td).textContent = s ? ecrire(s) : '';
        });

        rienTr.hidden = gardees > 0;
        etat.hidden   = actifs === 0;
        etat.querySelector('.n').textContent = gardees;
      };

      var trier = function (i, sens) {
        if (sens === 0) {
          ordre0.forEach(function (l) { tbody.insertBefore(l, rienTr); });
          return;
        }
        /* Tri numérique quand la colonne porte des `data-v`, alphabétique
           sinon: « 1 200 » et « 900 » se rangent à l'envers en texte, et c'est
           dans les colonnes d'argent que l'erreur coûterait le plus. */
        var chiffre = lignes.some(function (l) {
          return l.cells[i] && l.cells[i].hasAttribute('data-v');
        });
        var clef = function (l) {
          var td = l.cells[i];
          if (chiffre) {
            var n = parseFloat(td && td.getAttribute('data-v'));
            return isFinite(n) ? n : -Infinity;
          }
          return pli(texte(td));
        };
        lignes.slice().sort(function (a, b) {
          var x = clef(a), y = clef(b);
          if (x < y) return -sens;
          if (x > y) return sens;
          return 0;
        }).forEach(function (l) { tbody.insertBefore(l, rienTr); });
      };

      ths.forEach(function (th, i) {
        if (th.getAttribute('data-f') === 'non') return;

        var toutes = lignes.map(function (l) { return texte(l.cells[i]) || VIDE; });
        /* Une colonne d'une seule valeur n'a rien à filtrer ni à trier. */
        var distinctes = {};
        toutes.forEach(function (v) { distinctes[v] = (distinctes[v] || 0) + 1; });
        var valeurs = Object.keys(distinctes);
        if (valeurs.length < 2) return;
        valeurs.sort(function (a, b) { return a.localeCompare(b, 'fr', { numeric: true }); });

        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'fc-b';
        b.setAttribute('aria-label', 'Filtrer ' + (th.textContent || '').trim());
        b.textContent = '▼';
        th.appendChild(b);

        var m = document.createElement('div');
        m.className = 'fc-m';
        m.hidden = true;
        m.innerHTML =
          '<div class="fc-tri"><button type="button" data-t="1">A → Z</button>' +
          '<button type="button" data-t="-1">Z → A</button>' +
          '<button type="button" data-t="0">d’origine</button></div>' +
          '<input class="fc-q" type="search" placeholder="chercher une valeur…">' +
          '<div class="fc-l"></div>' +
          '<div class="fc-p"><button type="button" data-a="tout">tout</button>' +
          '<button type="button" data-a="rien">rien</button><span class="sep"></span></div>';
        th.appendChild(m);

        var liste = m.querySelector('.fc-l');
        var rech  = m.querySelector('.fc-q');

        valeurs.forEach(function (v) {
          var lab = document.createElement('label');
          lab.innerHTML = '<input type="checkbox" checked><span class="t"></span>'
                        + '<span class="n">' + distinctes[v] + '</span>';
          lab.querySelector('.t').textContent = v;
          lab.setAttribute('data-v', v);
          liste.appendChild(lab);
        });

        var compte = function () {
          m.querySelector('.sep').textContent =
            liste.querySelectorAll('input:checked').length + ' / ' + valeurs.length;
        };

        var lire = function () {
          var gardes = [], tous = true;
          liste.querySelectorAll('label').forEach(function (lab) {
            if (lab.querySelector('input').checked) gardes.push(lab.getAttribute('data-v'));
            else tous = false;
          });
          etats[i] = tous ? null : new Set(gardes);
          compte();
          appliquer();
        };

        liste.addEventListener('change', lire);
        rech.addEventListener('input', function () {
          var q = pli(rech.value);
          liste.querySelectorAll('label').forEach(function (lab) {
            lab.hidden = q !== '' && pli(lab.getAttribute('data-v')).indexOf(q) === -1;
          });
        });
        m.querySelectorAll('[data-a]').forEach(function (bt) {
          bt.addEventListener('click', function () {
            var val = bt.getAttribute('data-a') === 'tout';
            liste.querySelectorAll('label').forEach(function (lab) {
              if (!lab.hidden) lab.querySelector('input').checked = val;
            });
            lire();
          });
        });
        m.querySelectorAll('[data-t]').forEach(function (bt) {
          bt.addEventListener('click', function () {
            trier(i, parseInt(bt.getAttribute('data-t'), 10));
          });
        });

        b.addEventListener('click', function (e) {
          e.stopPropagation();
          var ouvert = !m.hidden;
          document.querySelectorAll('.fc-m').forEach(function (x) { x.hidden = true; });
          m.hidden = ouvert;
          if (!m.hidden) { compte(); rech.focus(); }
        });
        m.addEventListener('click', function (e) { e.stopPropagation(); });
      });

      vider.addEventListener('click', function () {
        etats = etats.map(function () { return null; });
        tbl.querySelectorAll('.fc-l input').forEach(function (c) { c.checked = true; });
        tbl.querySelectorAll('.fc-q').forEach(function (q) { q.value = ''; });
        tbl.querySelectorAll('.fc-l label').forEach(function (l) { l.hidden = false; });
        appliquer();
      });
    });

    /* Un clic ailleurs, ou Échap, referme: un menu qui reste ouvert cache la
       ligne qu'on venait lire. */
    document.addEventListener('click', function () {
      document.querySelectorAll('.fc-m').forEach(function (m) { m.hidden = true; });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        document.querySelectorAll('.fc-m').forEach(function (m) { m.hidden = true; });
      }
    });
  };

  /* ATTENDRE QUE LA PAGE SOIT LÀ: ce partiel est chargé AVANT les tableaux,
     pour que son style s'applique dès le premier rendu. */
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', poser);
  else poser();
})();
</script>

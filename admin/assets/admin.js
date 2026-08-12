/* Le Voisin — comportements de l'administration.   [V10-CMS-BILINGUE]
   Aucun texte visible n'est écrit ici : tous les libellés viennent de
   window.LV_ADMIN.i18n, rempli côté PHP depuis app/i18n/admin.fr.php ou
   admin.en.php selon la langue choisie dans l'administration. */
(function () {
  'use strict';
  var A = window.LV_ADMIN || { base: '/admin', csrf: '', formats: {}, cropUi: {}, linkList: [] };
  var T = A.i18n || {};
  // Si un libellé manque, on renvoie une chaîne vide plutôt que « undefined ».
  function t(cle) { return T[cle] || ''; }

  /* ---------- Utilitaires ---------- */
  function toast(msg, isErr) {
    var el = document.createElement('div');
    el.className = 'toast' + (isErr ? ' err' : '');
    el.textContent = msg;
    document.body.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('show'); });
    setTimeout(function () { el.classList.remove('show'); setTimeout(function () { el.remove(); }, 300); }, 2600);
  }
  function api(path, data) {
    return fetch(A.base + '/api/' + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF': A.csrf },
      body: JSON.stringify(data)
    }).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || t('err')); return j; }); });
  }
  function apiUpload(path, formData) {
    return fetch(A.base + '/api/' + path, {
      method: 'POST', headers: { 'X-CSRF': A.csrf }, body: formData
    }).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || t('err')); return j; }); });
  }
  function htmlToNode(html) {
    var t = document.createElement('template');
    t.innerHTML = html.trim();
    return t.content.firstChild;
  }
  function debounce(fn, ms) {
    var to = null;
    return function () {
      var args = arguments, ctx = this;
      clearTimeout(to);
      to = setTimeout(function () { fn.apply(ctx, args); }, ms);
    };
  }

  /* ---------- Onglets de langue (synchronisés) ---------- */
  document.addEventListener('click', function (e) {
    var tab = e.target.closest('.ltab');
    if (!tab) return;
    var lang = tab.getAttribute('data-lang');
    document.querySelectorAll('.ltab').forEach(function (t) {
      t.classList.toggle('on', t.getAttribute('data-lang') === lang);
    });
    document.querySelectorAll('.lpane').forEach(function (p) {
      p.hidden = p.getAttribute('data-lang') !== lang;
    });
  });

  /* ---------- Slugs automatiques ---------- */
  document.querySelectorAll('input[data-slug-for]').forEach(function (slugInput) {
    var ref = slugInput.getAttribute('data-slug-for').split(':'); // ["title","fr"]
    var form = slugInput.closest('form');
    if (!form) return;
    var src = form.querySelector('[name="' + ref[0] + '_' + ref[1] + '"]') || form.querySelector('[name="' + ref[0] + '"]');
    if (!src) return;
    var auto = slugInput.value === '';
    slugInput.addEventListener('input', function () { auto = slugInput.value === ''; });
    src.addEventListener('input', function () {
      if (!auto) return;
      slugInput.placeholder = slugify(src.value) || t('slugAuto');
    });
  });
  function slugify(s) {
    return (s || '').toLowerCase()
      .normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  }

  /* ---------- Confirmations & garde de modifications ---------- */
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('form.js-confirm');
    if (f && !window.confirm(f.getAttribute('data-confirm') || t('confirm'))) e.preventDefault();
  });
  var dirty = false;
  /* [V30-VOIR-LA-PAGE] Le bouton « Voir la page » ouvre un nouvel onglet, ce
     qui ne déclenche aucune alerte de sortie : rien ne préviendrait donc
     qu'on s'apprête à regarder la version enregistrée et non celle qu'on
     vient de taper. D'où cette phrase, cachée tant qu'on n'a rien modifié et
     qui apparaît à la première frappe. */
  function dirtyHint() {
    var h = document.getElementById('viewHint');
    if (h) h.hidden = !dirty;
  }
  document.addEventListener('input', function (e) {
    if (e.target.closest('form.js-dirty')) { dirty = true; dirtyHint(); }
  });
  document.addEventListener('submit', function (e) {
    if (e.target.closest('form.js-dirty')) { dirty = false; dirtyHint(); }
    if (window.tinymce) window.tinymce.triggerSave();
  });
  window.addEventListener('beforeunload', function (e) {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });

  /* ---------- Référencement : le compteur de caractères ----------
     [V30-SEO-AUTO] 160 caractères, c'est la longueur au-delà de laquelle
     Google coupe la description lui-même. Le compteur suit donc ce qui sera
     réellement publié : le texte tapé, ou — tant que la case est vide — la
     description automatique affichée en gris clair. Il passe en alerte au
     161e caractère, sans jamais empêcher d'écrire : c'est un avertissement,
     pas une barrière, et une description un peu longue vaut mieux qu'une
     phrase amputée à la saisie. */
  function seoCount(ta) {
    var p = ta.parentNode.querySelector('.seo-count');
    if (!p) return;
    var texte = ta.value || ta.placeholder || '';
    var n = Array.from(texte).length;   // compte les accents comme un caractère
    var b = p.querySelector('b');
    if (b) b.textContent = String(n);
    p.classList.toggle('over', n > 160);
    p.classList.toggle('auto', ta.value === '');
  }
  Array.prototype.forEach.call(document.querySelectorAll('.js-seo-desc'), seoCount);
  document.addEventListener('input', function (e) {
    if (e.target.classList && e.target.classList.contains('js-seo-desc')) seoCount(e.target);
  });

  /* ---------- Tri par glisser-déposer (listes) ---------- */
  if (window.Sortable) {
    document.querySelectorAll('.js-sortable').forEach(function (list) {
      new Sortable(list, {
        handle: '.vid-drag', animation: 150,
        onEnd: function () {
          var ids = Array.prototype.map.call(list.querySelectorAll('.rowitem'), function (r) { return r.getAttribute('data-id'); });
          api('sort.php', { table: list.getAttribute('data-table'), ids: ids })
            .then(function () { toast(t('orderSaved')); })
            .catch(function (er) { toast(er.message, true); });
        }
      });
    });

    /* Arborescence des pages */
    document.querySelectorAll('.js-pages-branch').forEach(function (branch) {
      new Sortable(branch, {
        group: 'pages', handle: '.vid-drag', animation: 150, fallbackOnBody: true,
        onEnd: function (evt) {
          [evt.from, evt.to].filter(function (v, i, a) { return a.indexOf(v) === i; }).forEach(function (container) {
            var ids = Array.prototype.filter.call(container.children, function (c) { return c.classList.contains('page-node'); })
              .map(function (c) { return c.getAttribute('data-id'); });
            api('sort.php', { mode: 'pages', parent: container.getAttribute('data-parent'), ids: ids })
              .then(function () { toast(t('structSaved')); })
              .catch(function (er) { toast(er.message, true); });
          });
        }
      });
    });
  }

  /* ---------- Image seule (couverture) ---------- */
  document.querySelectorAll('.imgpick').forEach(function (box) {
    var input = box.querySelector('input[type=hidden]');
    var fileInput = box.querySelector('input[type=file]');
    var preview = box.querySelector('.imgpick-preview');
    var cropBtn = box.querySelector('.js-crop');
    var removeBtn = box.querySelector('.js-imgremove');

    function upload(file) {
      var fd = new FormData();
      fd.append('owner', box.getAttribute('data-owner'));
      fd.append('zone', box.getAttribute('data-zone'));
      fd.append('file', file);
      preview.innerHTML = '<span class="imgpick-empty">' + t('sending') + '</span>';
      apiUpload('upload-image.php', fd).then(function (j) {
        input.value = j.id;
        dirty = true;
        preview.innerHTML = '<img src="' + j.thumb + '" alt="">';
        cropBtn.hidden = false; cropBtn.setAttribute('data-img', j.id);
        removeBtn.hidden = false;
        toast(t('imgAdded'));
      }).catch(function (er) {
        preview.innerHTML = '<span class="imgpick-empty">' + t('noImage') + '</span>';
        toast(er.message, true);
      });
    }
    fileInput.addEventListener('change', function () { if (fileInput.files[0]) upload(fileInput.files[0]); });
    box.addEventListener('dragover', function (e) { e.preventDefault(); box.classList.add('dragover'); });
    box.addEventListener('dragleave', function () { box.classList.remove('dragover'); });
    box.addEventListener('drop', function (e) {
      e.preventDefault(); box.classList.remove('dragover');
      if (e.dataTransfer.files[0]) upload(e.dataTransfer.files[0]);
    });
    if (removeBtn) removeBtn.addEventListener('click', function () {
      input.value = ''; dirty = true;
      preview.innerHTML = '<span class="imgpick-empty">' + t('noImage') + '</span>';
      cropBtn.hidden = true; removeBtn.hidden = true;
    });
  });

  /* ---------- Galeries ---------- */
  document.querySelectorAll('.gal').forEach(function (gal) {
    var grid = gal.querySelector('.gal-grid');
    var drop = gal.querySelector('.dropzone');
    var fileInput = drop.querySelector('input[type=file]');

    function uploadFiles(files) {
      Array.prototype.forEach.call(files, function (file) {
        var fd = new FormData();
        fd.append('owner', gal.getAttribute('data-owner'));
        fd.append('zone', gal.getAttribute('data-zone'));
        fd.append('file', file);
        drop.textContent = t('imgOptim');
        apiUpload('upload-image.php', fd).then(function (j) {
          if (j.html) grid.appendChild(htmlToNode(j.html));
          resetDrop();
        }).catch(function (er) { resetDrop(); toast(er.message, true); });
      });
    }
    function resetDrop() {
      drop.innerHTML = t('galDrop');
      drop.appendChild(fileInput);
    }
    fileInput.addEventListener('change', function () { uploadFiles(fileInput.files); });
    drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('dragover'); });
    drop.addEventListener('dragleave', function () { drop.classList.remove('dragover'); });
    drop.addEventListener('drop', function (e) {
      e.preventDefault(); drop.classList.remove('dragover');
      uploadFiles(e.dataTransfer.files);
    });

    if (window.Sortable) new Sortable(grid, {
      animation: 150,
      onEnd: function () {
        var ids = Array.prototype.map.call(grid.querySelectorAll('.gal-item'), function (n) { return n.getAttribute('data-id'); });
        api('sort.php', { table: 'images', ids: ids }).catch(function (er) { toast(er.message, true); });
      }
    });

    gal.addEventListener('click', function (e) {
      var del = e.target.closest('.js-img-del');
      if (del) {
        var item = del.closest('.gal-item');
        if (window.confirm(t('imgDel'))) {
          api('image.php', { action: 'delete', id: item.getAttribute('data-id') })
            .then(function () { item.remove(); }).catch(function (er) { toast(er.message, true); });
        }
      }
    });
    gal.addEventListener('change', function (e) {
      var alt = e.target.closest('.js-alt');
      if (alt) {
        api('image.php', {
          action: 'alt', id: alt.closest('.gal-item').getAttribute('data-id'),
          lang: alt.getAttribute('data-lang'), value: alt.value
        }).then(function () { toast(t('altSaved')); })
          .catch(function (er) { toast(er.message, true); });
      }
    });
  });

  /* ---------- Recadrage ---------- */
  var cropModal = document.getElementById('cropModal');
  var cropper = null, cropImgId = 0, cropFmt = '', cropInfo = null;

  /* [V31-RECADRAGE] Les cadrages réglés dans la fenêtre et pas encore
     enregistrés : format => [x, y, largeur, hauteur].

     Une même photo sert à plusieurs endroits, et chacun a ses proportions :
     la vignette de la grille est verticale, le bandeau est très large, le
     partage sur les réseaux est presque carré. Il faut donc régler le cadre
     format par format. Auparavant, passer d'un format au suivant effaçait ce
     qu'on venait de faire, et « Enregistrer » n'inscrivait que le format
     affiché avant de refermer la fenêtre : pour cadrer six emplacements, il
     fallait rouvrir six fois, et le moindre aller-retour perdait le travail.

     Les réglages s'accumulent maintenant ici, on circule librement entre les
     formats, et l'enregistrement les envoie tous d'un seul geste.

     Un format n'entre dans cette liste que si le cadre a été DÉPLACÉ à la
     main — l'événement « cropend » de Cropper, qui ne se produit qu'au
     relâchement de la souris. Un format simplement regardé n'y entre pas, et
     c'est voulu : y inscrire son cadrage automatique reviendrait à le figer au
     centre pour toujours, alors qu'il vaut mieux le laisser se recalculer. */
  var cropEnAttente = {};

  // Noms lisibles des formats : « banner (2000×900) » ne dit à personne où
  // l'image va s'afficher. On garde les dimensions entre parenthèses, mais
  // c'est l'emplacement qui est écrit en premier.
  var CROP_NOMS = A.i18n && A.i18n.cropNames ? A.i18n.cropNames : {};

  var cropSaveBtn = document.getElementById('cropSave');
  // Le libellé de repos est lu sur le bouton : il arrive déjà traduit.
  var cropSaveTexte = cropSaveBtn ? cropSaveBtn.textContent : '';

  function cropEnAttenteN() { return Object.keys(cropEnAttente).length; }

  /** Le rectangle courant, dans les pixels de la photo d'origine. */
  function rectActuel() {
    if (!cropper) return null;
    var d = cropper.getData(true);
    return [d.x, d.y, d.width, d.height];
  }

  /* Ce qui reste à enregistrer doit se voir sans avoir à s'en souvenir : une
     pastille sur les formats réglés, et le compte sur le bouton. */
  function marquerFormats() {
    var box = document.getElementById('cropFormats');
    if (box) {
      box.querySelectorAll('button').forEach(function (b) {
        var f = b.getAttribute('data-fmt');
        if (Object.prototype.hasOwnProperty.call(cropEnAttente, f)) b.classList.add('regle');
        else b.classList.remove('regle');
      });
    }
    if (cropSaveBtn) {
      var n = cropEnAttenteN();
      cropSaveBtn.textContent = n ? cropSaveTexte + ' (' + n + ')' : cropSaveTexte;
    }
  }

  /* Avant de quitter un format ou d'enregistrer : si le cadre du format
     affiché avait déjà été déplacé, on relit sa position exacte, au cas où le
     dernier mouvement n'aurait pas été enregistré. */
  function rafraichirCadre() {
    if (cropper && cropFmt && Object.prototype.hasOwnProperty.call(cropEnAttente, cropFmt)) {
      var r = rectActuel();
      if (r) cropEnAttente[cropFmt] = r;
    }
  }

  function openCrop(imgId) {
    cropImgId = imgId;
    api('image.php', { action: 'info', id: imgId }).then(function (info) {
      cropInfo = info;
      cropEnAttente = {};
      var fmts = info.formats;
      var fmtBox = document.getElementById('cropFormats');
      fmtBox.innerHTML = '';
      fmts.forEach(function (f, i) {
        var b = document.createElement('button');
        b.type = 'button';
        b.setAttribute('data-fmt', f);
        var dims = A.formats[f] || [0, 0];
        b.textContent = (CROP_NOMS[f] || f) + ' (' + dims[0] + '×' + dims[1] + ')';
        if (i === 0) b.classList.add('on');
        b.addEventListener('click', function () {
          if (f === cropFmt) return;
          rafraichirCadre();
          fmtBox.querySelectorAll('button').forEach(function (x) { x.classList.remove('on'); });
          b.classList.add('on');
          startCropper(f);
        });
        fmtBox.appendChild(b);
      });
      marquerFormats();
      cropModal.hidden = false;
      var img = document.getElementById('cropImage');
      img.onload = function () { startCropper(fmts[0]); };
      img.src = info.orig + '?t=' + Date.now();
    }).catch(function (er) { toast(er.message, true); });
  }

  /**
   * Replace le cadre sur le recadrage déjà enregistré.
   *
   * On n'utilise pas setData(), qui échouait silencieusement : il convertit
   * les dimensions en pixels d'écran, et la multiplication laisse parfois un
   * milliardième de pixel de trop. Cropper juge alors le cadre « trop grand »
   * et le remet d'où il venait — sans rien signaler. Résultat : en rouvrant la
   * fenêtre, on retrouvait le cadrage automatique du milieu au lieu du sien,
   * et le réenregistrer effaçait pour de bon le cadrage choisi.
   *
   * On calcule donc nous-mêmes, et on borne au maximum autorisé.
   */
  function replacerCadre(c, rect) {
    var ratio = c.imageData.width / c.imageData.naturalWidth;
    var boite = c.cropBoxData, toile = c.canvasData;
    c.setCropBoxData({
      left:   rect[0] * ratio + toile.left,
      top:    rect[1] * ratio + toile.top,
      width:  Math.min(rect[2] * ratio, boite.maxWidth),
      height: Math.min(rect[3] * ratio, boite.maxHeight)
    });
  }

  function startCropper(fmt) {
    cropFmt = fmt;
    var dims = A.formats[fmt] || [1, 1, 'crop'];
    if (cropper) { cropper.destroy(); cropper = null; }
    var img = document.getElementById('cropImage');
    cropper = new Cropper(img, {
      aspectRatio: dims[0] / dims[1],
      viewMode: 1,
      autoCropArea: 1,
      zoomable: false,
      ready: function () {
        // Ce qu'on vient de régler sans l'avoir encore enregistré passe
        // devant ce qui est inscrit dans la base : en revenant sur un
        // format, on retrouve son propre travail, pas l'état d'avant.
        var connu = cropEnAttente[fmt] || (cropInfo && cropInfo.crops && cropInfo.crops[fmt]);
        if (connu) replacerCadre(cropper, connu);
      }
    });
  }

  if (cropModal) {
    /* Un format entre dans la liste des réglages au relâchement de la souris,
       et à ce moment-là seulement. Ces deux écouteurs se posent une fois pour
       toutes : l'image reste la même d'un format à l'autre, seul le cadre
       change.

       On compare la position avant et après le geste. Un clic sans
       déplacement — ou un glissement sur un format dont les proportions sont
       déjà celles de la photo, où le cadre ne peut pas bouger — ne change
       rien et ne doit donc rien inscrire : ce serait figer au centre un
       cadrage que personne n'a choisi. */
    var cropAvantGeste = null;
    var cropImgEl = document.getElementById('cropImage');
    cropImgEl.addEventListener('cropstart', function () { cropAvantGeste = rectActuel(); });
    cropImgEl.addEventListener('cropend', function () {
      if (!cropper || !cropFmt) return;
      var r = rectActuel();
      if (!r) return;
      var a = cropAvantGeste;
      if (a && a[0] === r[0] && a[1] === r[1] && a[2] === r[2] && a[3] === r[3]) return;
      cropEnAttente[cropFmt] = r;
      marquerFormats();
    });

    document.getElementById('cropClose').addEventListener('click', demanderFermeture);
    cropModal.addEventListener('click', function (e) { if (e.target === cropModal) demanderFermeture(); });

    cropSaveBtn.addEventListener('click', function () {
      rafraichirCadre();
      var lot = cropEnAttente, n = cropEnAttenteN();
      if (!n) { toast(t('cropNone')); return; }

      cropSaveBtn.disabled = true;
      api('image.php', { action: 'crops', id: cropImgId, crops: lot })
        .then(function (j) {
          cropSaveBtn.disabled = false;
          // Les cadrages enregistrés cessent d'être « en attente » et
          // rejoignent ce que la fenêtre sait de l'image.
          if (cropInfo) {
            if (!cropInfo.crops || typeof cropInfo.crops !== 'object') cropInfo.crops = {};
            Object.keys(lot).forEach(function (f) { cropInfo.crops[f] = lot[f]; });
          }
          cropEnAttente = {};
          marquerFormats();
          var faits = j.n || n;
          toast(faits > 1 ? t('cropSavedN').replace('%s', faits) : t('cropSaved'));
          document.querySelectorAll('[data-id="' + cropImgId + '"] > img, .imgpick img').forEach(function (im) {
            if (im.closest('.gal-item') && im.closest('.gal-item').getAttribute('data-id') == cropImgId) im.src = j.thumb;
          });
          var pickBtn = document.querySelector('.imgpick .js-crop[data-img="' + cropImgId + '"]');
          if (pickBtn) {
            var pv = pickBtn.closest('.imgpick').querySelector('.imgpick-preview img');
            if (pv) pv.src = j.thumb;
          }
          closeCrop();
        }).catch(function (er) { cropSaveBtn.disabled = false; toast(er.message, true); });
    });
  }

  /* Fermer efface les réglages non enregistrés : on le dit avant, plutôt que
     de laisser découvrir après coup que le travail a disparu. */
  function demanderFermeture() {
    rafraichirCadre();
    if (cropEnAttenteN() && !window.confirm(t('cropLeave'))) return;
    closeCrop();
  }
  function closeCrop() {
    if (cropper) { cropper.destroy(); cropper = null; }
    cropEnAttente = {};
    cropFmt = '';
    marquerFormats();
    cropModal.hidden = true;
  }
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.js-crop');
    if (btn && btn.getAttribute('data-img')) openCrop(parseInt(btn.getAttribute('data-img'), 10));
  });

  /* ---------- Vidéos (fichiers auto-hébergés) ---------- */
  document.querySelectorAll('.vids').forEach(function (box) {
    var list = box.querySelector('.vids-list');
    var drop = box.querySelector('.js-vid-drop');
    var fileInput = drop ? drop.querySelector('input[type=file]') : null;
    var owner = box.getAttribute('data-owner');

    function resetDrop() {
      drop.innerHTML = t('vidDrop');
      drop.appendChild(fileInput);
    }
    function uploadFiles(files) {
      Array.prototype.forEach.call(files, function (file) {
        var fd = new FormData();
        fd.append('owner', owner);
        fd.append('file', file);
        /* Même règle que pour un lien : l'emplacement décide. */
        fd.append('catalogue', box.getAttribute('data-catalogue') === '1' ? '1' : '0');
        drop.textContent = t('vidSending').replace('%s', file.name);
        apiUpload('upload-video.php', fd).then(function (j) {
          if (j.html) list.appendChild(htmlToNode(j.html));
          resetDrop();
          toast(t('vidAdded'));
        }).catch(function (er) { resetDrop(); toast(er.message, true); });
      });
    }
    if (fileInput) {
      fileInput.addEventListener('change', function () { if (fileInput.files.length) uploadFiles(fileInput.files); });
      drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('dragover'); });
      drop.addEventListener('dragleave', function () { drop.classList.remove('dragover'); });
      drop.addEventListener('drop', function (e) {
        e.preventDefault(); drop.classList.remove('dragover');
        if (e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files);
      });
    }

    // Ajout par lien (YouTube, Vimeo, Dailymotion) — coexiste avec l'upload de fichiers.
    var urlInput = box.querySelector('.js-vid-url');
    var addBtn = box.querySelector('.js-vid-add');
    var feedBtn = box.querySelector('.js-vid-feed');
    var feedBox = box.querySelector('.vids-feed');
    function addLink(payload) {
      payload.action = 'add';
      payload.owner = owner;
      /* [12.08.2026] L'emplacement décide du destin : déposée sous « Captation
         intégrale », la vidéo part au Catalogue ; sous « Vidéos », elle reste
         publique. Rien à cocher, donc rien à oublier. */
      payload.catalogue = box.getAttribute('data-catalogue') === '1' ? 1 : 0;
      api('video.php', payload).then(function (j) {
        list.appendChild(htmlToNode(j.html));
        if (urlInput) urlInput.value = '';
        toast(t('vidAdded'));
      }).catch(function (er) { toast(er.message, true); });
    }
    if (addBtn) addBtn.addEventListener('click', function () {
      if (!urlInput || urlInput.value.trim() === '') { toast(t('vidNeedLink'), true); return; }
      addLink({ url: urlInput.value.trim() });
    });
    if (urlInput) urlInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); addBtn.click(); }
    });
    if (feedBtn) feedBtn.addEventListener('click', function () {
      if (!feedBox.hidden) { feedBox.hidden = true; return; }
      feedBox.hidden = false;
      feedBox.innerHTML = '<p class="hint">' + t('feedLoading') + '</p>';
      api('video.php', { action: 'feed' }).then(function (j) {
        feedBox.innerHTML = '';
        if (!j.items.length) { feedBox.innerHTML = '<p class="hint">' + t('feedEmpty') + '</p>'; return; }
        j.items.forEach(function (it) {
          var n = htmlToNode('<button type="button" class="feed-item"><img src="' + it.thumb + '" alt=""><span>' + it.title.replace(/</g, '&lt;') + '</span></button>');
          n.addEventListener('click', function () { addLink({ provider: it.provider, vid: it.vid, title: it.title, thumb: it.thumb }); });
          feedBox.appendChild(n);
        });
      }).catch(function (er) { feedBox.innerHTML = ''; toast(er.message, true); });
    });

    if (window.Sortable) new Sortable(list, {
      handle: '.vid-drag', animation: 150,
      onEnd: function () {
        var ids = Array.prototype.map.call(list.querySelectorAll('.vid-item'), function (n) { return n.getAttribute('data-id'); });
        api('sort.php', { table: 'videos', ids: ids }).catch(function (er) { toast(er.message, true); });
      }
    });
    list.addEventListener('click', function (e) {
      var del = e.target.closest('.js-vid-del');
      if (del && window.confirm(t('vidDel'))) {
        var item = del.closest('.vid-item');
        api('video.php', { action: 'delete', id: item.getAttribute('data-id') })
          .then(function () { item.remove(); }).catch(function (er) { toast(er.message, true); });
      }
    });
    list.addEventListener('change', function (e) {
      var inp = e.target.closest('.js-vid-secs');
      if (!inp) return;
      var item = inp.closest('.vid-item');
      var secs = Math.max(1, Math.min(60, parseInt(inp.value, 10) || 6));
      inp.value = secs;
      api('video.php', { action: 'duration', id: item.getAttribute('data-id'), seconds: secs })
        .then(function () { toast(t('durSaved')); }).catch(function (er) { toast(er.message, true); });
    });
  });

  /* ---------- Documents ---------- */
  document.querySelectorAll('.docsadmin').forEach(function (box) {
    var list = box.querySelector('.docs-list');
    var drop = box.querySelector('.dropzone');
    var fileInput = drop.querySelector('input[type=file]');
    var owner = box.getAttribute('data-owner');
    /* [V31-PRESSE] Une fiche projet porte deux listes de documents : celle
       qu'on télécharge et la revue de presse. Le bloc est le même, seule la
       « zone » diffère — elle voyage avec chaque ajout pour que la ligne
       arrive dans la bonne liste. */
    var zone = box.getAttribute('data-zone') || 'doc';

    function uploadFiles(files) {
      Array.prototype.forEach.call(files, function (file) {
        var fd = new FormData();
        fd.append('owner', owner);
        fd.append('zone', zone);
        fd.append('file', file);
        apiUpload('document.php', fd).then(function (j) {
          list.appendChild(htmlToNode(j.html));
          toast(t('docAdded'));
        }).catch(function (er) { toast(er.message, true); });
      });
    }
    fileInput.addEventListener('change', function () { uploadFiles(fileInput.files); });
    drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('dragover'); });
    drop.addEventListener('dragleave', function () { drop.classList.remove('dragover'); });
    drop.addEventListener('drop', function (e) {
      e.preventDefault(); drop.classList.remove('dragover'); uploadFiles(e.dataTransfer.files);
    });

    // Ajout par lien — coexiste avec le dépôt de fichiers, comme pour les vidéos.
    var docUrl = box.querySelector('.js-doc-url');
    var docAdd = box.querySelector('.js-doc-add');
    if (docAdd) docAdd.addEventListener('click', function () {
      if (!docUrl || docUrl.value.trim() === '') { toast(t('docNeedLink'), true); return; }
      api('document.php', { action: 'link', owner: owner, zone: zone, url: docUrl.value.trim() })
        .then(function (j) {
          list.appendChild(htmlToNode(j.html));
          docUrl.value = '';
          toast(t('docAdded'));
        }).catch(function (er) { toast(er.message, true); });
    });
    if (docUrl) docUrl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); docAdd.click(); }
    });

    if (window.Sortable) new Sortable(list, {
      handle: '.vid-drag', animation: 150,
      onEnd: function () {
        var ids = Array.prototype.map.call(list.querySelectorAll('.doc-item'), function (n) { return n.getAttribute('data-id'); });
        api('sort.php', { table: 'documents', ids: ids }).catch(function (er) { toast(er.message, true); });
      }
    });
    list.addEventListener('click', function (e) {
      var del = e.target.closest('.js-doc-del');
      if (del && window.confirm(t('docDel'))) {
        var item = del.closest('.doc-item');
        api('document.php', { action: 'delete', id: item.getAttribute('data-id') })
          .then(function () { item.remove(); }).catch(function (er) { toast(er.message, true); });
      }
    });
    list.addEventListener('change', function (e) {
      var inp = e.target.closest('.js-doc-title');
      if (inp) {
        api('document.php', {
          action: 'title', id: inp.closest('.doc-item').getAttribute('data-id'),
          lang: inp.getAttribute('data-lang'), value: inp.value
        }).then(function () { toast(t('titleSaved')); })
          .catch(function (er) { toast(er.message, true); });
        return;
      }
      var lnk = e.target.closest('.js-doc-link');
      if (lnk) {
        api('document.php', {
          action: 'url', id: lnk.closest('.doc-item').getAttribute('data-id'), value: lnk.value.trim()
        }).then(function () { toast(t('docLinkSaved')); })
          .catch(function (er) { toast(er.message, true); });
      }
    });
  });

  /* ---------- Réseaux sociaux (dates de tournée) ---------- */
  var socialPanel = document.getElementById('socialPanel');
  if (socialPanel) {
    var sId = socialPanel.getAttribute('data-id');
    var sGen = document.getElementById('socialGen');
    var sRes = document.getElementById('socialResult');
    var sImg = document.getElementById('socialImg');
    var sDl = document.getElementById('socialDl');
    var sCap = document.getElementById('socialCaption');
    var sCopy = document.getElementById('socialCopy');
    var sPush = document.getElementById('socialPush');

    sGen.addEventListener('click', function () {
      sGen.disabled = true; sGen.textContent = t('socialGen');
      api('social.php', { action: 'generate', id: sId }).then(function (j) {
        sImg.src = j.image;
        sDl.href = j.image;
        sCap.value = j.caption_fr;
        sRes.hidden = false;
        sGen.textContent = t('socialRegen');
        sGen.disabled = false;
      }).catch(function (er) { toast(er.message, true); sGen.disabled = false; sGen.textContent = t('socialGenBtn'); });
    });
    if (sCopy) sCopy.addEventListener('click', function () {
      navigator.clipboard.writeText(sCap.value).then(function () { toast(t('textCopied')); })
        .catch(function () { sCap.select(); document.execCommand('copy'); toast(t('textCopied')); });
    });
    if (sPush) sPush.addEventListener('click', function () {
      sPush.disabled = true; sPush.textContent = t('sending');
      api('social.php', { action: 'push', id: sId, caption: sCap.value }).then(function () {
        toast(t('socialPushed'));
        sPush.disabled = false; sPush.textContent = t('socialPushBtn');
      }).catch(function (er) {
        toast(er.message, true);
        sPush.disabled = false; sPush.textContent = t('socialPushBtn');
      });
    });
  }

  /* ---------- Éditeur WYSIWYG ---------- */
  if (window.tinymce && document.querySelector('textarea.wysiwyg')) {
    tinymce.init({
      selector: 'textarea.wysiwyg',
      license_key: 'gpl',
      height: 440,
      menubar: false,
      plugins: 'advlist autolink lists link image table code fullscreen searchreplace visualblocks',
      toolbar: 'undo redo | styles | bold italic | bullist numlist | link image table | removeformat | code fullscreen',
      style_formats: [
        { title: t('styP'), format: 'p' },
        { title: t('styH2'), format: 'h2' },
        { title: t('styH3'), format: 'h3' },
        { title: t('styLead'), selector: 'p', classes: 'lead' },
        { title: t('styQuote'), format: 'blockquote' }
      ],
      content_css: [A.base.replace(/\/admin$/, '') + '/assets/css/fonts.css', A.base + '/assets/editor.css'],
      link_list: A.linkList,
      link_default_target: null,
      convert_urls: false,
      branding: false,
      promotion: false,
      entity_encoding: 'raw',
      images_upload_handler: function (blobInfo) {
        var ta = document.querySelector('textarea.wysiwyg');
        var owner = ta ? ta.getAttribute('data-owner') : 'page:0';
        var fd = new FormData();
        fd.append('owner', owner);
        fd.append('zone', 'content');
        fd.append('file', blobInfo.blob(), blobInfo.filename());
        return apiUpload('upload-image.php', fd).then(function (j) {
          return (A.base.replace(/\/admin$/, '')) + '/uploads/i/' + j.id + '/content.jpg';
        });
      },
      setup: function (ed) {
        ed.on('change input', function () { dirty = true; });
      }
    });
  }
})();

/* Le Voisin — comportements du site public */
(function () {
  'use strict';

  /* ---------- Menu mobile ---------- */
  var burger = document.getElementById('burger');
  var nav = document.getElementById('nav');
  if (burger && nav) {
    burger.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.style.overflow = open ? 'hidden' : '';
    });
  }

  /* ---------- Consentement cookies ---------- */
  var COOKIE = 'lv_consent';

  function readConsent() {
    var m = document.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=([^;]*)'));
    if (!m) return null;
    try { return JSON.parse(decodeURIComponent(m[1])); } catch (e) { return null; }
  }
  function writeConsent(c) {
    var d = new Date(); d.setMonth(d.getMonth() + 6);
    document.cookie = COOKIE + '=' + encodeURIComponent(JSON.stringify(c)) +
      '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
  }

  var banner = document.getElementById('cookies');
  var prefs = document.getElementById('cookiePrefs');
  var consent = readConsent();

  function applyConsent() {
    if (!consent) return;
    /* Google Analytics */
    if (consent.analytics && window.LV_GA && !window.__lvGaLoaded) {
      window.__lvGaLoaded = true;
      var s = document.createElement('script');
      s.async = true;
      s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(window.LV_GA);
      document.head.appendChild(s);
      window.dataLayer = window.dataLayer || [];
      window.gtag = function () { window.dataLayer.push(arguments); };
      window.gtag('js', new Date());
      window.gtag('config', window.LV_GA, { anonymize_ip: true });
    }
    /* Vidéos externes */
    if (consent.media) {
      document.querySelectorAll('.js-video').forEach(function (fig) {
        if (fig.querySelector('iframe')) return;
        var iframe = document.createElement('iframe');
        iframe.src = fig.getAttribute('data-embed');
        iframe.title = fig.getAttribute('data-title') || 'Video';
        iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen');
        iframe.setAttribute('allowfullscreen', '');
        iframe.loading = 'lazy';
        var locked = fig.querySelector('.video-locked');
        if (locked) locked.replaceWith(iframe);
      });
    }
  }

  function setConsent(analytics, media) {
    consent = { necessary: true, analytics: !!analytics, media: !!media, at: Date.now() };
    writeConsent(consent);
    if (banner) banner.hidden = true;
    applyConsent();
  }

  if (banner) {
    if (!consent) banner.hidden = false;
    var all = banner.querySelector('.js-ck-all');
    var none = banner.querySelector('.js-ck-none');
    var custom = banner.querySelector('.js-ck-custom');
    if (all) all.addEventListener('click', function () { setConsent(true, true); });
    if (none) none.addEventListener('click', function () { setConsent(false, false); });
    if (custom && prefs) custom.addEventListener('click', function () { openPrefs(); });
  }

  function openPrefs() {
    if (!prefs) return;
    var a = document.getElementById('ckAnalytics');
    var m = document.getElementById('ckMedia');
    if (a) a.checked = consent ? !!consent.analytics : false;
    if (m) m.checked = consent ? !!consent.media : false;
    prefs.showModal();
  }
  if (prefs) {
    var save = prefs.querySelector('.js-ck-save');
    if (save) save.addEventListener('click', function () {
      var a = document.getElementById('ckAnalytics');
      var m = document.getElementById('ckMedia');
      setConsent(a && a.checked, m && m.checked);
    });
  }
  document.querySelectorAll('.js-cookie-open').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (window.LV && window.LV.cookiesMode === 'simple') { if (banner) banner.hidden = false; }
      else openPrefs();
    });
  });

  /* Bouton « autoriser » sur une vidéo verrouillée */
  document.querySelectorAll('.js-video-allow').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setConsent(consent ? consent.analytics : false, true);
    });
  });

  /* [V28-INSTAGRAM] Instagram annonce lui-même la hauteur exacte de la
     publication dès qu'elle est chargée — c'est le mécanisme qu'utilise son
     propre script d'intégration. On écoute ce message plutôt que de charger ce
     script tiers : la carte se règle au pixel, qu'il s'agisse d'une photo
     carrée ou d'une vidéo verticale. Si le message n'arrive jamais, la hauteur
     estimée dans la feuille de style tient toute seule. */
  window.addEventListener('message', function (ev) {
    if (!/^https:\/\/(www\.)?instagram\.com$/.test(String(ev.origin))) return;
    var d = ev.data;
    if (typeof d === 'string') { try { d = JSON.parse(d); } catch (e) { return; } }
    if (!d || d.type !== 'MEASURE' || !d.details) return;
    var h = parseInt(d.details.height, 10);
    if (!h || h < 120 || h > 2000) return;
    document.querySelectorAll('.insta iframe').forEach(function (f) {
      if (f.contentWindow === ev.source) f.style.height = h + 'px';
    });
  });

  applyConsent();

  /* ---------- Visionneuse de galerie ---------- */
  var lb = document.getElementById('lightbox');
  if (lb) {
    var lbImg = lb.querySelector('img');
    var items = [];
    var idx = 0;
    function openLb(i) {
      idx = i;
      lbImg.src = items[idx].href;
      lbImg.alt = items[idx].getAttribute('data-alt') || '';
      lb.hidden = false;
      document.body.style.overflow = 'hidden';
    }
    function closeLb() { lb.hidden = true; document.body.style.overflow = ''; }
    function move(d) { if (items.length) openLb((idx + d + items.length) % items.length); }

    document.querySelectorAll('.js-gallery').forEach(function (gal) {
      var links = Array.prototype.slice.call(gal.querySelectorAll('.gallery-item'));
      links.forEach(function (a, i) {
        a.addEventListener('click', function (ev) {
          ev.preventDefault();
          items = links;
          openLb(i);
        });
      });
    });
    lb.querySelector('.lightbox-close').addEventListener('click', closeLb);
    lb.querySelector('.lightbox-prev').addEventListener('click', function () { move(-1); });
    lb.querySelector('.lightbox-next').addEventListener('click', function () { move(1); });
    lb.addEventListener('click', function (e) { if (e.target === lb) closeLb(); });
    document.addEventListener('keydown', function (e) {
      if (lb.hidden) return;
      if (e.key === 'Escape') closeLb();
      if (e.key === 'ArrowLeft') move(-1);
      if (e.key === 'ArrowRight') move(1);
    });
  }

  /* ---------- Carrousel d'images (fiche projet) ---------- */
  document.querySelectorAll('.js-carousel').forEach(function (car) {
    var track = car.querySelector('.carousel-track');
    var slides = track.children.length;
    var count = car.querySelector('.carousel-count');
    var prev = car.querySelector('.carousel-btn.prev');
    var next = car.querySelector('.carousel-btn.next');
    if (!track || slides < 2) return;

    function index() {
      return Math.round(track.scrollLeft / track.clientWidth);
    }
    function go(i) {
      if (i < 0) i = slides - 1;
      if (i >= slides) i = 0;
      track.scrollTo({ left: i * track.clientWidth, behavior: 'smooth' });
    }
    if (prev) prev.addEventListener('click', function () { go(index() - 1); });
    if (next) next.addEventListener('click', function () { go(index() + 1); });
    track.addEventListener('scroll', function () {
      if (count) count.textContent = (index() + 1) + ' / ' + slides;
    }, { passive: true });
  });

  /* ---------- Champs conditionnels des formulaires ---------- */
  var condFields = document.querySelectorAll('[data-show-if]');
  if (condFields.length) {
    function refreshConds() {
      condFields.forEach(function (el) {
        var rule;
        try { rule = JSON.parse(el.getAttribute('data-show-if')); } catch (e) { return; }
        var dep = rule[0], values = rule[1];
        var form = el.closest('form');
        if (!form) return;
        /* [V35-FICHE-ONGLET] Une question sans réponse ne vaut pas « oui ».
           On lisait auparavant le premier bouton du groupe quand aucun n'était
           coché : « Avez-vous un permis ? » restée vide ouvrait donc la case du
           permis, comme si l'on avait répondu oui. Tant que la fiche vivait
           derrière un bouton la chose passait inaperçue ; elle s'ouvre
           maintenant la première, et c'est la première impression. */
        var champs = form.querySelectorAll('[name="' + dep + '"]');
        var val = '';
        if (champs.length) {
          var t = champs[0].type;
          if (t === 'radio' || t === 'checkbox') {
            var coche = form.querySelector('[name="' + dep + '"]:checked');
            val = coche ? coche.value : '';
          } else {
            val = champs[0].value;
          }
        }
        el.classList.toggle('shown', values.indexOf(val) !== -1);
      });
    }
    document.addEventListener('change', refreshConds);
    refreshConds();
  }

  /* ---------- Double envoi ---------- */
  document.querySelectorAll('form.form').forEach(function (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('button[type=submit]');
      if (btn) { btn.disabled = true; btn.style.opacity = '.6'; }
    });
  });

  /* ---------- Carrousel du bandeau d'accueil ---------- */
  document.querySelectorAll('.js-hero-carousel').forEach(function (car) {
    var slides = Array.prototype.slice.call(car.querySelectorAll('.hero-slide'));
    var dots = Array.prototype.slice.call(car.querySelectorAll('.hero-dot'));
    if (slides.length < 2) return;
    var idx = 0, timer = null;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function render() {
      slides.forEach(function (s, k) { s.classList.toggle('on', k === idx); });
      dots.forEach(function (d, k) { d.classList.toggle('on', k === idx); });
    }
    function go(n) { idx = (n + slides.length) % slides.length; render(); }
    function slideMs(n) { return (parseInt(slides[n].getAttribute('data-secs'), 10) || 6) * 1000; }
    function stop() { if (timer) { clearTimeout(timer); timer = null; } }
    function play() {
      stop();
      if (reduce) return;
      /* Après consentement, les vignettes deviennent des lecteurs : on n'avance plus automatiquement. */
      if (car.querySelector('.hero-slide iframe')) return;
      timer = setTimeout(function () { go(idx + 1); play(); }, slideMs(idx));
    }

    var next = car.querySelector('.hero-nav.next');
    var prev = car.querySelector('.hero-nav.prev');
    if (next) next.addEventListener('click', function () { go(idx + 1); play(); });
    if (prev) prev.addEventListener('click', function () { go(idx - 1); play(); });
    dots.forEach(function (d) {
      d.addEventListener('click', function () { go(parseInt(d.getAttribute('data-i'), 10) || 0); play(); });
    });
    car.addEventListener('mouseenter', stop);
    car.addEventListener('mouseleave', play);
    document.addEventListener('visibilitychange', function () { if (document.hidden) { stop(); } else { play(); } });

    render();
    play();
  });

  /* En-tête transparent sur le bandeau d'accueil → plein blanc au défilement */
  var lvHeader = document.querySelector('body.has-hero .site-header');
  var lvHero = document.querySelector('.hero-video');
  if (lvHeader && lvHero) {
    var lvToggle = function () {
      lvHeader.classList.toggle('scrolled', lvHero.getBoundingClientRect().bottom <= lvHeader.offsetHeight);
    };
    window.addEventListener('scroll', lvToggle, { passive: true });
    window.addEventListener('resize', lvToggle);
    lvToggle();
  }
})();

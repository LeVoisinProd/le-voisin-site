<?php
/* ---------------------------------------------------------------------------
   Page d'accueil — bandeau : carrousel d'images sur toute la largeur.

   Ce fichier est autonome : il contient le HTML, le style et l'animation du
   carrousel. Il n'a besoin d'aucune modification de layout.php ni de site.css.

   Les images se règlent dans l'administration :
   Structure & pages -> Accueil -> « Galerie photos ».
   Elles défilent dans l'ordre de la galerie ; glisser les vignettes pour les
   réordonner. Une seule image = bandeau fixe, sans flèches ni pastilles.
--------------------------------------------------------------------------- */
$heroImages = array_values(Img::gallery('page', (int)($page['id'] ?? 0)));
$heroSecs   = max(2, min(30, (int)setting('hero_secs', 6)));
$hasHero    = $heroImages || !empty($heroVideos);
?>
<?php if ($heroImages): $n = count($heroImages); ?>
<style>
/* Carrousel d'images du bandeau d'accueil (styles autonomes) */
.lvhero {
  position: relative; width: auto; max-width: none;
  margin-left: calc(50% - 50vw); margin-right: calc(50% - 50vw);
  background: #000; overflow: hidden;
}
.lvhero-slides { position: relative; height: clamp(340px, 44vw, 680px); }
.lvhero-slide  { position: absolute; inset: 0; opacity: 0; visibility: hidden; transition: opacity .8s ease; }
.lvhero-slide.on { opacity: 1; visibility: visible; }
/* Réglage « réduire les animations » du système : le bandeau défile toujours,
   mais l'image change d'un coup, sans fondu. */
.lvhero-net .lvhero-slide { transition: none; }
.lvhero-slide picture { display: block; width: 100%; height: 100%; }
.lvhero-slide img {
  display: block; width: 100%; height: 100%; max-width: none;
  object-fit: cover; object-position: center; margin: 0; border-radius: 0;
}
/* Voile sombre en bas : les flèches et pastilles restent lisibles sur une photo claire */
.lvhero-slides::after {
  content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 130px; z-index: 2;
  background: linear-gradient(to top, rgba(0,0,0,.34), rgba(0,0,0,0)); pointer-events: none;
}
.lvhero-nav {
  position: absolute; top: 50%; transform: translateY(-50%); z-index: 3;
  width: 48px; height: 48px; padding: 0; border-radius: 50%; border: 2px solid #fff;
  background: rgba(0,0,0,.32); color: #fff; font-size: 26px; line-height: 1;
  display: flex; align-items: center; justify-content: center; cursor: pointer;
  transition: .18s; box-shadow: 0 2px 16px rgba(0,0,0,.38);
}
.lvhero-nav:hover, .lvhero-nav:focus-visible { background: #f0c63f; color: #000; border-color: #000; }
.lvhero-nav.prev { left: clamp(10px, 2vw, 26px); }
.lvhero-nav.next { right: clamp(10px, 2vw, 26px); }
.lvhero-dots { position: absolute; left: 0; right: 0; bottom: 16px; z-index: 3; display: flex; gap: 9px; justify-content: center; }
.lvhero-dot {
  width: 11px; height: 11px; padding: 0; border-radius: 50%; border: 2px solid #fff;
  background: transparent; cursor: pointer; transition: .18s; box-shadow: 0 1px 6px rgba(0,0,0,.5);
}
.lvhero-dot.on, .lvhero-dot:hover { background: #f0c63f; border-color: #f0c63f; }
@media (max-width: 640px) {
  .lvhero-slides { height: max(56vw, 210px); }
  .lvhero-nav { display: none; }
}
</style>
<section class="lvhero" data-secs="<?= $heroSecs ?>" data-count="<?= $n ?>" aria-roledescription="carrousel">
  <div class="lvhero-slides">
    <?php foreach ($heroImages as $i => $im): ?>
    <div class="lvhero-slide<?= $i === 0 ? ' on' : '' ?>" role="group" aria-roledescription="image" aria-label="<?= $i + 1 ?> / <?= $n ?>"><?= Img::tag($im, 'gallery') ?></div>
    <?php endforeach; ?>
  </div>
  <?php if ($n > 1): ?>
  <button type="button" class="lvhero-nav prev" aria-label="Image précédente">&lsaquo;</button>
  <button type="button" class="lvhero-nav next" aria-label="Image suivante">&rsaquo;</button>
  <div class="lvhero-dots">
    <?php foreach ($heroImages as $i => $im): ?>
    <button type="button" class="lvhero-dot<?= $i === 0 ? ' on' : '' ?>" data-i="<?= $i ?>" aria-label="Image <?= $i + 1 ?>"></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php if ($n > 1): ?>
<script>
/* Défilement automatique du bandeau + flèches, pastilles et glissement au doigt. */
(function () {
  var car = document.currentScript.previousElementSibling;
  while (car && !car.classList.contains('lvhero')) car = car.previousElementSibling;
  if (!car || car.getAttribute('data-lv-init')) return;
  car.setAttribute('data-lv-init', '1');

  var slides = Array.prototype.slice.call(car.querySelectorAll('.lvhero-slide'));
  var dots   = Array.prototype.slice.call(car.querySelectorAll('.lvhero-dot'));
  if (slides.length < 2) return;

  var idx = 0, timer = null;
  var secs = parseInt(car.getAttribute('data-secs'), 10);
  if (!secs || secs < 2) secs = 6;
  /* Si le système d'exploitation demande moins d'animations, le bandeau continue
     de défiler, mais l'image change d'un coup au lieu d'un fondu. */
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    car.classList.add('lvhero-net');
  }

  function render() {
    slides.forEach(function (s, k) { s.classList.toggle('on', k === idx); });
    dots.forEach(function (d, k) { d.classList.toggle('on', k === idx); });
  }
  function go(n) { idx = (n + slides.length) % slides.length; render(); }
  function stop() { if (timer) { clearTimeout(timer); timer = null; } }
  function play() {
    stop();
    timer = setTimeout(function () { go(idx + 1); play(); }, secs * 1000);
  }

  var next = car.querySelector('.lvhero-nav.next');
  var prev = car.querySelector('.lvhero-nav.prev');
  if (next) next.addEventListener('click', function () { go(idx + 1); play(); });
  if (prev) prev.addEventListener('click', function () { go(idx - 1); play(); });
  dots.forEach(function (d) {
    d.addEventListener('click', function () { go(parseInt(d.getAttribute('data-i'), 10) || 0); play(); });
  });

  var x0 = null;
  car.addEventListener('touchstart', function (ev) { x0 = ev.touches[0].clientX; }, {passive: true});
  car.addEventListener('touchend', function (ev) {
    if (x0 === null) return;
    var dx = ev.changedTouches[0].clientX - x0;
    x0 = null;
    if (Math.abs(dx) > 40) { go(dx < 0 ? idx + 1 : idx - 1); play(); }
  }, {passive: true});

  /* Le bandeau occupe tout le haut de la page : la souris se trouve donc presque
     toujours dessus, et même déjà dessus à l'ouverture de la page. Mettre en
     pause au survol du bandeau entier empêchait le défilement de démarrer. Seuls
     les flèches et les pastilles mettent en pause, ainsi que la navigation au
     clavier : c'est là que l'on a besoin de prendre le temps de choisir. */
  Array.prototype.slice.call(car.querySelectorAll('.lvhero-nav, .lvhero-dot')).forEach(function (b) {
    b.addEventListener('mouseenter', stop);
    b.addEventListener('mouseleave', play);
  });
  car.addEventListener('focusin', stop);
  car.addEventListener('focusout', play);
  document.addEventListener('visibilitychange', function () { if (document.hidden) { stop(); } else { play(); } });

  render();
  play();
})();
</script>
<?php endif; ?>
<?php elseif (!empty($heroVideos)): ?>
<section class="hero-video"><?= hero_carousel($heroVideos) ?></section>
<?php endif; ?>

<section class="hero<?= $hasHero ? ' hero-tight' : '' ?>">
  <div class="wrap">
    <h1 class="sr"><?= e(setting('site_name', 'Le Voisin')) ?></h1>
    <div class="hero-text lead"><?= f($page, 'body') ?></div>
  </div>
</section>

<?php if ($events): $ap = Pages::moduleP('agenda'); ?>
<section class="section section-alt">
  <div class="wrap">
    <div class="section-head">
      <h2><?= e(t('next_dates')) ?></h2>
      <?php if ($ap): ?><a class="more" href="<?= e(Pages::url($ap)) ?>"><?= e(t('see_agenda')) ?> →</a><?php endif; ?>
    </div>
    <div class="events-grid">
      <?php foreach (array_slice($events, 0, 4) as $ev) echo event_card($ev); ?>
    </div>
  </div>
</section>
<?php endif; ?>

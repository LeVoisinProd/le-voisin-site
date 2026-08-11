<section class="section">
  <div class="wrap">
    <h1><?= e(f($page, 'title')) ?></h1>
    <?php if (f($page, 'body')): ?><div class="page-body about-intro"><div class="rich"><?= f($page, 'body') ?></div></div><?php endif; ?>
    <div class="team">
      <?php foreach ($members as $m): $img = !empty($m['image_id']) ? Img::row((int)$m['image_id']) : null; ?>
      <article class="member">
        <div class="member-photo">
          <?= $img ? Img::tag($img, 'team', ['alt' => $m['first_name'] . ' ' . $m['last_name']]) : '<span class="card-ph" aria-hidden="true">' . e(mb_substr($m['first_name'], 0, 1) . mb_substr($m['last_name'], 0, 1)) . '</span>' ?>
        </div>
        <div class="member-text">
          <h2 class="member-name"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></h2>
          <?php if (f($m, 'role')): ?><p class="member-role"><?= e(f($m, 'role')) ?></p><?php endif; ?>
          <div class="member-bio rich"><?= f($m, 'bio') ?></div>
          <?php if ($m['photo_credit']): ?><p class="member-credit">© <?= e($m['photo_credit']) ?></p><?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

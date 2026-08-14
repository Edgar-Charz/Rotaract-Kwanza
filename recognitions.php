<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/MonthlyRecognition.php';
require_once __DIR__ . '/includes/helpers.php';

$db = new Database();
$conn = $db->connect();
$recognitions = (new MonthlyRecognition($conn))->getPublished();
$recognitions = array_values(array_filter($recognitions, static fn(array $recognition): bool => !empty($recognition['members'])));

$page_title = site_title($conn, 'Rotaractors of the Month');
$page_description = 'Meet the Rotaractors of the Month recognized by Rotaract Club of Kwanza.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php require __DIR__ . '/includes/public_head.php'; ?>
</head>

<body>
  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <main id="recognitions" class="recognitions-archive-page">
    <section class="recognitions-archive-hero">
      <div class="container">
        <div class="section-eyebrow reveal">Member recognition</div>
        <h1 class="reveal reveal-delay-1">Rotaractors of the <em>Month</em></h1>
        <p class="reveal reveal-delay-2">Celebrating the members whose service, leadership, and commitment make a meaningful difference in our club and community.</p>
      </div>
    </section>

    <section class="recognitions-archive-content">
      <div class="container">
        <?php if ($recognitions): ?>
          <div class="recognition-month-picker reveal">
            <label for="recognition-month-select">Choose a month</label>
            <select id="recognition-month-select" data-month-select>
              <?php foreach ($recognitions as $monthIndex => $recognition): ?>
                <option value="<?= $monthIndex ?>"><?= e(date('F Y', strtotime($recognition['recognition_month']))) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="recognition-month-slider" data-month-slider>
            <?php foreach ($recognitions as $monthIndex => $recognition): ?>
              <section class="recognition-month-slide<?= $monthIndex === 0 ? ' is-active' : '' ?>" data-month-slide aria-hidden="<?= $monthIndex === 0 ? 'false' : 'true' ?>">
                <header class="recognition-month-heading reveal reveal-delay-1">
                  <div class="recognition-feature-kicker">Rotaractor of the Month</div>
                  <div class="recognition-month-title-row"><?php if (count($recognitions) > 1): ?><button type="button" data-month-prev aria-label="Previous month">&larr;</button><?php endif; ?><h2><?= e(date('F Y', strtotime($recognition['recognition_month']))) ?></h2><?php if (count($recognitions) > 1): ?><button type="button" data-month-next aria-label="Next month">&rarr;</button><?php endif; ?></div><?php if ($recognition['citation']): ?><p><?= e($recognition['citation']) ?></p><?php endif; ?>
                </header>
                <div class="recognition-recipient-slider reveal reveal-delay-2" data-recipient-slider>
                  <?php foreach ($recognition['members'] as $recipientIndex => $recipient):
                    $galleryPhotos = $recipient['photos'];
                    if (!$galleryPhotos && $recipient['photo_path']) $galleryPhotos[] = ['image_path' => $recipient['photo_path'], 'caption' => $recipient['first_name'] . ' ' . $recipient['last_name']];
                    $initials = strtoupper(substr($recipient['first_name'], 0, 1) . substr($recipient['last_name'], 0, 1));
                  ?>
                    <article class="recognition-recipient-slide<?= $recipientIndex === 0 ? ' is-active' : '' ?>" data-recipient-slide aria-hidden="<?= $recipientIndex === 0 ? 'false' : 'true' ?>">
                      <div class="recognition-recipient-gallery" data-photo-gallery>
                        <?php if ($galleryPhotos): foreach ($galleryPhotos as $photoIndex => $photo): ?><button type="button" class="recognition-recipient-photo<?= $photoIndex === 0 ? ' is-active' : '' ?>" data-gallery-photo data-full-image="<?= e(img_url($photo['image_path'])) ?>" data-caption="<?= e($photo['caption'] ?: $recipient['first_name'] . ' ' . $recipient['last_name']) ?>"><img src="<?= e(img_url($photo['image_path'])) ?>" alt="<?= e($photo['caption'] ?: $recipient['first_name'] . ' ' . $recipient['last_name']) ?>"></button><?php endforeach;
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        else: ?><div class="recognition-recipient-photo recognition-recipient-photo--empty"><?= e($initials) ?></div><?php endif; ?>
                        <?php if (count($galleryPhotos) > 1): ?><button type="button" class="recognition-gallery-arrow recognition-gallery-arrow--left" data-gallery-prev aria-label="Previous photo">&larr;</button><button type="button" class="recognition-gallery-arrow recognition-gallery-arrow--right" data-gallery-next aria-label="Next photo">&rarr;</button><span class="recognition-gallery-count" data-gallery-count>1 / <?= count($galleryPhotos) ?></span><?php endif; ?>
                      </div>
                      <div class="recognition-recipient-details">
                        <div class="recognition-recipient-title-row">
                          <?php if (count($recognition['members']) > 1): ?><button type="button" data-recipient-prev aria-label="Previous Rotaractor">&larr;</button><?php endif; ?>
                          <a href="member.php?source=directory&id=<?= (int) $recipient['id'] ?>">
                            <h3><?= e($recipient['first_name'] . ' ' . $recipient['last_name']) ?></h3>
                          </a>
                          <?php if (count($recognition['members']) > 1): ?><button type="button" data-recipient-next aria-label="Next Rotaractor">&rarr;</button><?php endif; ?>
                        </div>
                        <?php if ($recipient['occupation']): ?><p><span>Occupation</span><?= e($recipient['occupation']) ?></p><?php endif; ?>
                        <?php if ($recipient['year_of_study']): ?><p><span>Year of study</span><?= e($recipient['year_of_study']) ?></p><?php endif; ?>
                        <?php if ($recipient['email'] || ($recipient['linkedin_url'] ?? '') || ($recipient['instagram_url'] ?? '')): ?>
                          <div class="recognition-contact-row">
                          <?php if ($recipient['email']): ?><div class="team-email-row"><a href="mailto:<?= e($recipient['email']) ?>" class="team-social-link"><?= e($recipient['email']) ?></a></div><?php endif; ?>
                          <?php if (($recipient['linkedin_url'] ?? '') || ($recipient['instagram_url'] ?? '')): ?>
                            <div class="team-social-row recognition-social-row">
                            <?php if ($recipient['linkedin_url'] ?? ''): ?><a href="<?= e($recipient['linkedin_url']) ?>" target="_blank" rel="noopener" class="team-social-link"><svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                  <path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6z" />
                                  <rect x="2" y="9" width="4" height="12" />
                                  <circle cx="4" cy="4" r="2" />
                                </svg>LinkedIn</a><?php endif; ?>
                            <?php if ($recipient['instagram_url'] ?? ''): ?><a href="<?= e($recipient['instagram_url']) ?>" target="_blank" rel="noopener" class="team-social-link"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                  <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                                  <path d="M16 11.37A4 4 0 1112.63 8 4 0 0116 11.37z" />
                                  <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
                                </svg>Instagram</a><?php endif; ?>
                            </div>
                          <?php endif; ?>
                          </div>
                        <?php endif; ?>
                        <div class="recognition-recipient-note"><span>Personal recognition note</span>
                          <p><?= e($recipient['citation'] ?: 'No personal recognition note has been added yet.') ?></p>
                        </div>
                      </div>
                    </article>
                  <?php endforeach; ?>
                  <?php if (count($recognition['members']) > 1): ?><div class="recognition-recipient-count" data-recipient-count>1 / <?= count($recognition['members']) ?></div><?php endif; ?>
                </div>
              </section>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="recognitions-archive-empty reveal">No Rotaractor of the Month recognitions have been published yet.</div>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>
  <script src="assets/js/scripts.js"></script>
  <script>
    document.querySelectorAll('[data-photo-gallery]').forEach(function(gallery) {
      var photos = Array.prototype.slice.call(gallery.querySelectorAll('[data-gallery-photo]')),
        index = 0,
        count = gallery.querySelector('[data-gallery-count]');

      function show(i) {
        index = (i + photos.length) % photos.length;
        photos.forEach(function(photo, n) {
          photo.classList.toggle('is-active', n === index);
        });
        if (count) count.textContent = (index + 1) + ' / ' + photos.length;
      }
      var prev = gallery.querySelector('[data-gallery-prev]'),
        next = gallery.querySelector('[data-gallery-next]');
      if (prev) prev.addEventListener('click', function() {
        show(index - 1);
      });
      if (next) next.addEventListener('click', function() {
        show(index + 1);
      });
      photos.forEach(function(photo) {
        photo.addEventListener('click', function() {
          document.getElementById('recognition-lightbox-image').src = photo.dataset.fullImage;
          document.getElementById('recognition-lightbox-caption').textContent = photo.dataset.caption;
          document.getElementById('recognition-lightbox').classList.add('open');
        });
      });
    });
    document.querySelectorAll('[data-recipient-slider]').forEach(function(slider) {
      var slides = Array.prototype.slice.call(slider.querySelectorAll('[data-recipient-slide]')),
        index = 0,
        count = slider.querySelector('[data-recipient-count]');

      function show(i) {
        index = (i + slides.length) % slides.length;
        slides.forEach(function(slide, n) {
          var active = n === index;
          slide.classList.toggle('is-active', active);
          slide.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
        if (count) count.textContent = (index + 1) + ' / ' + slides.length;
      }
      slider.querySelectorAll('[data-recipient-prev]').forEach(function(button) {
        button.addEventListener('click', function() {
          show(index - 1);
        });
      });
      slider.querySelectorAll('[data-recipient-next]').forEach(function(button) {
        button.addEventListener('click', function() {
          show(index + 1);
        });
      });
    });
    document.querySelectorAll('[data-month-slider]').forEach(function(slider) {
      var slides = Array.prototype.slice.call(slider.querySelectorAll('[data-month-slide]')),
        index = 0,
        select = document.querySelector('[data-month-select]');

      function show(i) {
        index = (i + slides.length) % slides.length;
        slides.forEach(function(slide, n) {
          var active = n === index;
          slide.classList.toggle('is-active', active);
          slide.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
        if (select) select.value = index;
      }
      slider.querySelectorAll('[data-month-prev]').forEach(function(button) {
        button.addEventListener('click', function() {
          show(index + 1);
        });
      });
      slider.querySelectorAll('[data-month-next]').forEach(function(button) {
        button.addEventListener('click', function() {
          show(index - 1);
        });
      });
      if (select) select.addEventListener('change', function() {
        show(Number(select.value));
      });
    });
    document.addEventListener('DOMContentLoaded', function() {
      var recognitionLightboxClose = document.getElementById('recognition-lightbox-close');
      if (recognitionLightboxClose) recognitionLightboxClose.addEventListener('click', function() {
        document.getElementById('recognition-lightbox').classList.remove('open');
      });
    });
  </script>
  <div class="recognition-lightbox" id="recognition-lightbox"><button type="button" id="recognition-lightbox-close" aria-label="Close photo">&times;</button>
    <figure><img id="recognition-lightbox-image" alt="Recognition photo">
      <figcaption id="recognition-lightbox-caption"></figcaption>
    </figure>
  </div>
</body>

</html>

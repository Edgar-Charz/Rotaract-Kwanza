<?php
/**
 * Shared public <head> assets. Set $page_title before including.
 * Optional: $extra_css = ['assets/css/pages/news.css']
 */
$__page_title = $page_title ?? (isset($conn) ? site_title($conn) : 'Rotaract Club of Kwanza');
$__extra_css  = $extra_css ?? [];
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($__page_title) ?></title>
<link rel="icon" type="image/png" href="assets/img/logo1.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/kwanza.css">
<?php foreach ($__extra_css as $__css): ?>
<link rel="stylesheet" href="<?= e($__css) ?>">
<?php endforeach; ?>

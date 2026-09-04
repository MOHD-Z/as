<?php
// Expects (optionally) $page_title, $page_description, $page_image set by the including page.
$siteName = get_setting('site_name', 'AniStream');
$siteIcon = get_setting('site_icon', 'img/logo.png');
$siteLogo = get_setting('site_logo', 'img/logo.png');

$page_title = $page_title ?? (get_setting('seo_meta_title') ?: ($siteName . ' | Home'));
$page_description = $page_description ?? (get_setting('seo_meta_description') ?: ('Watch series, movies and episodes on ' . $siteName . '.'));
$page_keywords = get_setting('seo_meta_keywords', 'anime, watch anime, movies, series, episodes');
$page_image = $page_image ?? $siteLogo;
$active = $active ?? '';

$currLang = current_lang();
$isRtl = is_rtl();
?>
<!DOCTYPE html>
<html lang="<?= h($currLang) ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
  <head>
    <meta charset="UTF-8" />
    <meta name="description" content="<?= h($page_description) ?>" />
    <meta name="keywords" content="<?= h($page_keywords) ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title><?= h($page_title) ?></title>

    <meta property="og:type" content="website" />
    <meta property="og:title" content="<?= h($page_title) ?>" />
    <meta property="og:description" content="<?= h($page_description) ?>" />
    <meta property="og:image" content="<?= h($page_image) ?>" />
    <meta name="twitter:card" content="summary_large_image" />
    <link rel="icon" href="<?= (strpos($siteIcon, 'http') === 0 || strpos($siteIcon, '/') === 0) ? h($siteIcon) : h($siteIcon) ?>" type="image/png" />

    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Mulish:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />

    <link rel="stylesheet" href="css/bootstrap.min.css" type="text/css" />
    <link rel="stylesheet" href="css/font-awesome.min.css" type="text/css" />
    <link rel="stylesheet" href="css/elegant-icons.css" type="text/css" />
    <link rel="stylesheet" href="css/plyr.css" type="text/css" />
    <link rel="stylesheet" href="css/nice-select.css" type="text/css" />
    <link rel="stylesheet" href="css/owl.carousel.min.css" type="text/css" />
    <link rel="stylesheet" href="css/slicknav.min.css" type="text/css" />
    <link rel="stylesheet" href="css/style.css" type="text/css" />

    <?php if ($isRtl): ?>
    <style>
      body { text-align: right; direction: rtl; }
      .header__nav { text-align: right; }
      .header__right { text-align: left; }
      .hero__text { text-align: right; }
      .section-title { text-align: right; }
      .section-title h4::after { right: 0; left: auto; }
      .btn__all { text-align: left; }
      .arrow_right { transform: rotate(180deg); }
      .arrow_carrot-down { margin-right: 5px; margin-left: 0; }
    </style>
    <?php endif; ?>
  </head>

  <body>
    <header class="header">
      <div class="container">
        <div class="row align-items-center">
          <div class="col-lg-2 col-md-3">
            <div class="header__logo">
              <a href="index.php">
                <img src="<?= h($siteLogo) ?>" alt="<?= h($siteName) ?>" style="max-height:40px;" />
              </a>
            </div>
          </div>
          <div class="col-lg-7 col-md-6">
            <div class="header__nav">
              <nav class="header__menu mobile-menu">
                <ul>
                  <?php foreach (get_nav_links($pdo, 'header') as $link):
                      if (!nav_link_visible_now($link)) continue;
                      $activeKey = nav_link_active_key($link['url']);
                      $rawLabel = $link['label'];
                      
                      // Support translation of standard nav links
                      $translatedLabel = $rawLabel;
                      if (stripos($rawLabel, 'home') !== false) $translatedLabel = __('homepage', $rawLabel);
                      elseif (stripos($rawLabel, 'genr') !== false) $translatedLabel = __('genres', $rawLabel);
                      elseif (stripos($rawLabel, 'movie') !== false) $translatedLabel = __('movies', $rawLabel);
                      elseif (stripos($rawLabel, 'series') !== false) $translatedLabel = __('series', $rawLabel);
                      elseif (stripos($rawLabel, 'blog') !== false) $translatedLabel = __('our_blog', $rawLabel);
                      elseif (stripos($rawLabel, 'list') !== false) $translatedLabel = __('my_list', $rawLabel);

                      $label = ($link['url'] === 'logout.php') ? __('logout', 'Logout') . ' (' . h(current_user()['name']) . ')' : h($translatedLabel);
                  ?>
                    <?php if ($link['url'] === 'genrs.php'): ?>
                      <li class="<?= $active === 'genres' ? 'active' : '' ?>">
                        <a href="genrs.php"><?= __('genres', 'Genres') ?> <span class="arrow_carrot-down"></span></a>
                        <ul class="dropdown">
                          <li><a href="movies.php"><?= __('movies', 'Movies') ?></a></li>
                          <li><a href="series.php"><?= __('series', 'Series') ?></a></li>
                        </ul>
                      </li>
                    <?php else: ?>
                      <li class="<?= ($activeKey && $active === $activeKey) ? 'active' : '' ?>"><a href="<?= h($link['url']) ?>"><?= $label ?></a></li>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  <?php if (is_logged_in()): ?>
                    <li><a href="logout.php"><?= __('logout', 'Logout') ?> (<?= h(current_user()['name']) ?>)</a></li>
                  <?php endif; ?>
                </ul>
              </nav>
            </div>
          </div>
          <div class="col-lg-3 col-md-3">
            <div class="header__right" style="display:flex;align-items:center;justify-content:flex-end;gap:14px;">
              <!-- Language Switcher -->
              <div class="lang-switch" style="font-size:12px;display:flex;gap:6px;">
                <a href="?lang=en" style="color:<?= $currLang === 'en' ? '#e53637' : '#aaa' ?>;font-weight:<?= $currLang === 'en' ? 'bold' : 'normal' ?>;">EN</a>
                <span style="color:#555;">|</span>
                <a href="?lang=ar" style="color:<?= $currLang === 'ar' ? '#e53637' : '#aaa' ?>;font-weight:<?= $currLang === 'ar' ? 'bold' : 'normal' ?>;">عربي</a>
              </div>

              <a href="#" class="search-switch"><span class="icon_search"></span></a>
              <a href="<?= is_logged_in() ? 'logout.php' : 'login.php' ?>" title="<?= is_logged_in() ? __('logout', 'Logout') : __('login', 'Login') ?>"><span class="icon_profile"></span></a>
            </div>
          </div>
        </div>
      </div>
    </header>
    <!-- Header End -->

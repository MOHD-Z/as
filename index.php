<?php
require_once __DIR__ . '/includes/bootstrap.php';

$siteName = get_setting('site_name', 'AniStream');
$page_title = get_setting('seo_meta_title') ?: ($siteName . ' | Home');
$page_description = get_setting('seo_meta_description') ?: 'Watch series, movies and anime online for free in HD quality.';
$active = 'home';

$sidebarOn = setting_on('sidebar_enabled', '1');
$sidebarContent = get_setting('sidebar_content', 'views_and_blog');
$sliderOn = setting_on('slider_enabled', '1');
$sliderShowGenres = setting_on('slider_show_genres', '1');
$sliderShowSynopsisBtn = setting_on('slider_show_synopsis_btn', '1');

$sections = $pdo->query("SELECT * FROM homepage_sections WHERE visible = 1 ORDER BY sort_order")->fetchAll();

function fetch_section_items($pdo, $section) {
    $orderMap = [
        'newest' => 'created_at DESC',
        'oldest' => 'created_at ASC',
        'score'  => 'score DESC',
        'views'  => 'views DESC',
    ];
    $order = $orderMap[$section['sort_by']] ?? 'created_at DESC';
    $limit = (int)$section['posts_count'];

    if ($section['content_type'] === 'blog') {
        return ['blog', $pdo->query("SELECT * FROM blog_posts WHERE published = 1 ORDER BY created_at DESC LIMIT $limit")->fetchAll()];
    }
    if ($section['content_type'] === 'movies') {
        return ['movie', $pdo->query("SELECT m.*,
                (SELECT GROUP_CONCAT(g.name SEPARATOR ', ') FROM movie_genres mg JOIN genres g ON mg.genre_id=g.id WHERE mg.movie_id=m.id) AS genre_names
            FROM movies m WHERE m.archived = 0 ORDER BY m.$order LIMIT $limit")->fetchAll()];
    }
    if ($section['content_type'] === 'episodes') {
        $epOrder = ($section['sort_by'] === 'score') ? 's.score DESC, e.episode_number ASC' : 'e.' . ($orderMap[$section['sort_by']] ?? 'created_at DESC');
        return ['episodes', $pdo->query("SELECT e.*, se.season_number, s.title AS series_title, s.poster, s.slug AS series_slug, s.score,
                (SELECT GROUP_CONCAT(g.name SEPARATOR ', ') FROM series_genres sg JOIN genres g ON sg.genre_id=g.id WHERE sg.series_id=s.id) AS genre_names
            FROM episodes e
            JOIN seasons se ON e.season_id = se.id
            JOIN series s ON se.series_id = s.id
            WHERE e.archived = 0 AND s.archived = 0
            ORDER BY $epOrder LIMIT $limit")->fetchAll()];
    }
    if (in_array($section['content_type'], ['series', 'trending', 'popular'], true)) {
        return ['series', $pdo->query("SELECT s.*,
                (SELECT GROUP_CONCAT(g.name SEPARATOR ', ') FROM series_genres sg JOIN genres g ON sg.genre_id=g.id WHERE sg.series_id=s.id) AS genre_names,
                (SELECT COUNT(*) FROM episodes e JOIN seasons se ON e.season_id=se.id WHERE se.series_id=s.id) AS episode_count
            FROM series s WHERE s.archived = 0 ORDER BY s.$order LIMIT $limit")->fetchAll()];
    }
    return [null, []];
}

$heroSlides = $pdo->query("SELECT s.*, 
    (SELECT g.name FROM series_genres sg JOIN genres g ON sg.genre_id=g.id WHERE sg.series_id=s.id LIMIT 1) AS genre_name
    FROM series s WHERE s.archived = 0 ORDER BY s.score DESC LIMIT 4")->fetchAll();

// Queries for sidebar
$sidebarSeries = $pdo->query("SELECT * FROM series WHERE archived = 0 ORDER BY views DESC LIMIT 5")->fetchAll();
$sidebarMovies = $pdo->query("SELECT * FROM movies WHERE archived = 0 ORDER BY views DESC LIMIT 5")->fetchAll();
$blogPosts = $pdo->query("SELECT * FROM blog_posts WHERE published = 1 ORDER BY created_at DESC LIMIT 4")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

    <?php if ($sliderOn && $heroSlides): ?>
    <!-- Hero Section Begin -->
    <section class="hero">
      <div class="container">
        <div class="hero__slider owl-carousel">
          <?php foreach ($heroSlides as $slide): ?>
          <div class="hero__items set-bg" style="background-image: url('<?= h($slide['poster']) ?>')">
            <div class="row">
              <div class="col-lg-7">
                <div class="hero__text">
                  <?php if ($sliderShowGenres): ?>
                    <div class="label"><?= h($slide['genre_name'] ?? 'Featured') ?></div>
                  <?php endif; ?>
                  <h2><?= h($slide['title']) ?></h2>
                  <?php if ($sliderShowSynopsisBtn): ?>
                    <p><?= h(mb_strimwidth($slide['synopsis'] ?? '', 0, 140, '...')) ?></p>
                    <a href="tv-details.php?slug=<?= h($slide['slug']) ?>"><span><?= __('watch_now', 'Watch Now') ?></span> <i class="fa fa-angle-right"></i></a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <!-- Hero Section End -->
    <?php endif; ?>

    <section class="product spad">
      <div class="container-fluid">
        <div class="row">
          <div class="<?= $sidebarOn ? 'col-lg-9' : 'col-lg-12' ?> index1">

            <?php foreach ($sections as $section):
                [$type, $items] = fetch_section_items($pdo, $section);
                if (!$type) continue;
                $viewAllLink = $section['content_type'] === 'movies' ? 'movies.php' : ($section['content_type'] === 'blog' ? 'blog.php' : ($section['content_type'] === 'episodes' ? 'episodes.php' : 'series.php'));
                
                // Translated title mapping if standard
                $sTitle = $section['title'];
                if (stripos($sTitle, 'trend') !== false) $sTitle = __('trending_now', $sTitle);
                elseif (stripos($sTitle, 'movie') !== false) $sTitle = __('last_movies', $sTitle);
                elseif (stripos($sTitle, 'popular') !== false) $sTitle = __('popular_series', $sTitle);
                elseif (stripos($sTitle, 'episode') !== false) $sTitle = __('last_episodes', $sTitle);
            ?>
              <div class="trending__product mt-5">
                <div class="row">
                  <div class="col-lg-8 col-md-8 col-sm-8">
                    <div class="section-title"><h4><?= h($sTitle) ?></h4></div>
                  </div>
                  <div class="col-lg-4 col-md-4 col-sm-4">
                    <div class="btn__all"><a href="<?= $viewAllLink ?>" class="primary-btn"><?= __('view_all', 'View All') ?> <span class="arrow_right"></span></a></div>
                  </div>
                </div>
                <div class="row">
                  <?php if (!$items): ?>
                    <div class="col-12"><p class="muted"><?= __('no_results_found', 'Nothing to show here yet.') ?></p></div>
                  <?php endif; ?>
                  <?php if ($type === 'blog'): ?>
                    <?php foreach ($items as $post): ?>
                      <div class="col-lg-3 col-md-4 col-6 inp3">
                        <div class="blog__item">
                          <div class="blog__item__pic"><img src="<?= h($post['image']) ?>" alt="" style="width:100%;"></div>
                          <div class="blog__item__text">
                            <h6><a href="blog-details.php?slug=<?= h($post['slug']) ?>"><?= h($post['title']) ?></a></h6>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <?php foreach ($items as $item) render_card($item, $type, true); ?>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>

            <?php if (!$sections): ?>
              <p><?= __('no_results_found', 'No homepage sections are enabled. Add some from the admin panel.') ?></p>
            <?php endif; ?>
          </div>

          <?php if ($sidebarOn): ?>
          <div class="col-lg-3">
            <div class="sidebar">

              <!-- Top Views Series -->
              <div class="section-title"><h4><?= __('popular_series', 'Most Viewed Series') ?></h4></div>
              <?php foreach ($sidebarSeries as $sItem): ?>
                <div class="product__sidebar__view__item set-bg" style="background-image: url('<?= h($sItem['poster']) ?>');margin-bottom:14px;height:120px;position:relative;border-radius:6px;overflow:hidden;">
                  <div class="ep" style="position:absolute;top:10px;left:10px;background:#e53637;color:#fff;font-size:11px;padding:2px 8px;border-radius:4px;">
                    <?= (int)($sItem['episodes_count'] ?? 12) ?> EP
                  </div>
                  <div class="view" style="position:absolute;top:10px;right:10px;background:rgba(0,0,0,0.6);color:#fff;font-size:11px;padding:2px 8px;border-radius:4px;">
                    <i class="fa fa-eye"></i> <?= (int)$sItem['views'] ?>
                  </div>
                  <h5 style="position:absolute;bottom:10px;left:10px;right:10px;margin:0;font-size:14px;background:rgba(0,0,0,0.7);padding:4px 8px;border-radius:4px;">
                    <a href="tv-details.php?slug=<?= h($sItem['slug']) ?>" style="color:#fff;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h($sItem['title']) ?></a>
                  </h5>
                </div>
              <?php endforeach; ?>

              <?php if ($sidebarContent === 'views_only'): ?>
                <!-- Most Viewed Movies -->
                <div class="section-title" style="margin-top:30px;"><h4><?= __('last_movies', 'Most Viewed Movies') ?></h4></div>
                <?php foreach ($sidebarMovies as $mItem): ?>
                  <div class="product__sidebar__view__item set-bg" style="background-image: url('<?= h($mItem['poster']) ?>');margin-bottom:14px;height:120px;position:relative;border-radius:6px;overflow:hidden;">
                    <div class="ep" style="position:absolute;top:10px;left:10px;background:#e53637;color:#fff;font-size:11px;padding:2px 8px;border-radius:4px;">
                      <?= (int)($mItem['runtime'] ?? 90) ?> min
                    </div>
                    <div class="view" style="position:absolute;top:10px;right:10px;background:rgba(0,0,0,0.6);color:#fff;font-size:11px;padding:2px 8px;border-radius:4px;">
                      <i class="fa fa-eye"></i> <?= (int)$mItem['views'] ?>
                    </div>
                    <h5 style="position:absolute;bottom:10px;left:10px;right:10px;margin:0;font-size:14px;background:rgba(0,0,0,0.7);padding:4px 8px;border-radius:4px;">
                      <a href="movie-details.php?slug=<?= h($mItem['slug']) ?>" style="color:#fff;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h($mItem['title']) ?></a>
                    </h5>
                  </div>
                <?php endforeach; ?>

              <?php else: ?>
                <!-- Our Blog -->
                <div class="section-title" style="margin-top:30px;"><h4><?= __('our_blog', 'Our Blog') ?></h4></div>
                <?php foreach ($blogPosts as $post): ?>
                  <div class="sidebar__item" style="margin-bottom:18px;">
                    <a href="blog-details.php?slug=<?= h($post['slug']) ?>" style="display:block;">
                      <img src="<?= h($post['image']) ?>" alt="" style="width:100%;height:110px;object-fit:cover;border-radius:6px;">
                      <h6 style="margin-top:8px;color:#fff;font-size:13px;line-height:1.4;"><?= h($post['title']) ?></h6>
                    </a>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>

            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
<?php include __DIR__ . '/includes/footer.php'; ?>

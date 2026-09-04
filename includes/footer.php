    <footer class="footer">
      <div class="page-up">
        <a href="#" id="scrollToTopButton"><span class="arrow_carrot-up"></span></a>
      </div>
      <div class="container">
        <div class="row align-items-center">
          <div class="col-lg-3">
            <div class="footer__logo">
              <a href="index.php">
                <img src="<?= h(get_setting('site_logo', 'img/logo.png')) ?>" alt="<?= h(get_setting('site_name', 'AniStream')) ?>" style="max-height:36px;" />
              </a>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="footer__nav">
              <ul>
                <?php foreach (get_nav_links($pdo, 'footer') as $link):
                    if (!nav_link_visible_now($link)) continue;
                ?>
                  <li><a href="<?= h($link['url']) ?>"><?= h($link['label']) ?></a></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
          <div class="col-lg-3">
            <p>
              Copyright &copy; <?= date('Y') ?> All rights reserved | <?= h(get_setting('site_name', 'AniStream')) ?>
            </p>
          </div>
        </div>
      </div>
    </footer>

    <div class="search-model">
      <div class="h-100 d-flex align-items-center justify-content-center">
        <div class="search-close-switch"><i class="icon_close"></i></div>
        <form class="search-model-form" action="search.php" method="get">
          <input type="text" name="q" id="search-input" placeholder="<?= h(__('search_placeholder', 'Search anime or movie...')) ?>" />
        </form>
      </div>
    </div>

    <script src="js/jquery-3.3.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/player.js"></script>
    <script src="js/jquery.nice-select.min.js"></script>
    <script src="js/mixitup.min.js"></script>
    <script src="js/jquery.slicknav.js"></script>
    <script src="js/owl.carousel.min.js"></script>
    <script src="js/main.js"></script>
  </body>
</html>

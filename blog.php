<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page_title = 'AniStream | Blog';
$active = 'blog';

$posts = $pdo->query("SELECT * FROM blog_posts WHERE published = 1 ORDER BY created_at DESC")->fetchAll();

include __DIR__ . '/includes/header.php';
?>
    <section class="breadcrumb-option">
      <div class="container"><h2>Our Blog</h2></div>
    </section>
    <section class="blog spad">
      <div class="container">
        <div class="row">
          <?php if (!$posts): ?><div class="col-12"><p>No blog posts yet.</p></div><?php endif; ?>
          <?php foreach ($posts as $post): ?>
            <div class="col-lg-3 col-md-6 col-6 mb-4">
              <div class="blog__item">
                <div class="blog__item__pic">
                  <img src="<?= h($post['image']) ?>" alt="" style="width:100%;">
                </div>
                <div class="blog__item__text">
                  <h5><a href="blog-details.php?slug=<?= h($post['slug']) ?>"><?= h($post['title']) ?></a></h5>
                  <p><?= h($post['excerpt']) ?></p>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
<?php include __DIR__ . '/includes/footer.php'; ?>

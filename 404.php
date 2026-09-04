<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page_title = 'AniStream | Page Not Found';
include __DIR__ . '/includes/header.php';
?>
    <section class="error-page" style="padding:120px 0;text-align:center;">
      <div class="container">
        <h1 style="font-size:80px;">404</h1>
        <p>The page you're looking for doesn't exist.</p>
        <a href="index.php" class="primary-btn">Back to Home</a>
      </div>
    </section>
<?php include __DIR__ . '/includes/footer.php'; ?>

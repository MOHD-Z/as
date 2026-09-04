<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE slug = ?");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$page_title = 'AniStream | ' . $post['title'];
$page_description = mb_strimwidth(strip_tags($post['excerpt'] ?? ''), 0, 160, '...');
$page_image = $post['image'];
include __DIR__ . '/includes/header.php';
?>
    <section class="breadcrumb-option">
      <div class="container"><h2><?= h($post['title']) ?></h2></div>
    </section>
    <section class="blog-details spad">
      <div class="container">
        <img src="<?= h($post['image']) ?>" alt="" style="width:100%;border-radius:6px;margin-bottom:20px;">
        <div class="blog-post-body"><?= $post['body'] /* trusted admin-authored HTML from the rich text editor */ ?></div>
      </div>
    </section>
<?php include __DIR__ . '/includes/footer.php'; ?>

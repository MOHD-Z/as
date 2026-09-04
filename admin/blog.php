<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE blog_posts SET published = NOT published WHERE id = ?")->execute([(int)$_GET['toggle']]);
    header('Location: blog.php');
    exit;
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([(int)$_GET['delete']]);
    admin_flash('Post deleted.');
    header('Location: blog.php');
    exit;
}

$posts = $pdo->query("SELECT * FROM blog_posts ORDER BY created_at DESC")->fetchAll();

$admin_page_title = 'Blog';
$admin_active = 'blog';
$admin_page_actions = '<a href="blog-form.php" class="btn primary">+ Add Post</a>';
include __DIR__ . '/includes/layout_top.php';
?>
  <div class="panel">
    <div class="panel-body table-wrap">
      <table class="table">
        <thead><tr><th>Title</th><th>Excerpt</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
          <?php if (!$posts): ?><tr><td colspan="5" class="empty">No posts yet.</td></tr><?php endif; ?>
          <?php foreach ($posts as $p): ?>
            <tr>
              <td><?= h($p['title']) ?></td>
              <td class="muted"><?= h(mb_strimwidth($p['excerpt'] ?? '', 0, 60, '...')) ?></td>
              <td><span class="status <?= $p['published'] ? 'published' : 'archived' ?>"><?= $p['published'] ? 'Published' : 'Draft' ?></span></td>
              <td class="muted"><?= h($p['created_at']) ?></td>
              <td>
                <a href="blog-form.php?id=<?= (int)$p['id'] ?>" class="btn">Edit</a>
                <a href="blog.php?toggle=<?= (int)$p['id'] ?>" class="btn"><?= $p['published'] ? 'Unpublish' : 'Publish' ?></a>
                <a href="blog.php?delete=<?= (int)$p['id'] ?>" class="btn danger" onclick="return confirm('Delete this post?')">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

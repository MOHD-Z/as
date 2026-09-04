<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM homepage_sections")->fetchColumn();
    $stmt = $pdo->prepare("INSERT INTO homepage_sections (title, content_type, posts_count, sort_by, visible, sort_order) VALUES (?,?,?,?,1,?)");
    $stmt->execute([trim($_POST['title']), $_POST['content_type'], (int)$_POST['posts_count'], $_POST['sort_by'], $maxOrder + 1]);
    admin_flash('Section added.');
    header('Location: homepage_sections.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $stmt = $pdo->prepare("UPDATE homepage_sections SET title=?, content_type=?, posts_count=?, sort_by=?, sort_order=? WHERE id=?");
    $stmt->execute([trim($_POST['title']), $_POST['content_type'], (int)$_POST['posts_count'], $_POST['sort_by'], (int)$_POST['sort_order'], (int)$_POST['id']]);
    admin_flash('Section updated.');
    header('Location: homepage_sections.php');
    exit;
}

if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE homepage_sections SET visible = NOT visible WHERE id = ?")->execute([(int)$_GET['toggle']]);
    header('Location: homepage_sections.php');
    exit;
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM homepage_sections WHERE id = ?")->execute([(int)$_GET['delete']]);
    admin_flash('Section deleted.');
    header('Location: homepage_sections.php');
    exit;
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM homepage_sections WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editing = $stmt->fetch();
}

$sections = $pdo->query("SELECT * FROM homepage_sections ORDER BY sort_order")->fetchAll();
$contentOptions = ['series' => 'Series', 'movies' => 'Movies', 'trending' => 'Trending (series)', 'popular' => 'Popular (series)'];
$sortOptions = ['newest' => 'Newest First', 'oldest' => 'Oldest First', 'score' => 'Highest Score', 'views' => 'Most Views'];

$admin_page_title = 'Homepage Sections';
$admin_active = 'homepage';
include __DIR__ . '/includes/layout_top.php';
?>
  <p class="muted" style="margin-bottom:16px;">
    These sections control the homepage directly — add, rename, reorder, hide, or delete
    them here and the public homepage updates immediately. No code changes needed.
  </p>

  <div class="panel" style="margin-bottom:20px;">
    <div class="panel-head"><h2><?= $editing ? 'Edit Section' : 'Add Section' ?></h2></div>
    <div class="panel-body">
      <form method="post" class="form-grid">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
        <div class="field"><label>Section Title</label><input type="text" name="title" placeholder="e.g. Latest Movies" required value="<?= h($editing['title'] ?? '') ?>"></div>
        <div class="field">
          <label>Content</label>
          <select name="content_type">
            <?php foreach ($contentOptions as $val => $label): ?>
              <option value="<?= $val ?>" <?= ($editing['content_type'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Posts</label><input type="number" name="posts_count" value="<?= h($editing['posts_count'] ?? '8') ?>" min="1" max="24"></div>
        <div class="field">
          <label>Sort</label>
          <select name="sort_by">
            <?php foreach ($sortOptions as $val => $label): ?>
              <option value="<?= $val ?>" <?= ($editing['sort_by'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($editing): ?>
        <div class="field"><label>Order</label><input type="number" name="sort_order" value="<?= (int)$editing['sort_order'] ?>"></div>
        <?php endif; ?>
        <div class="field full">
          <button type="submit" class="btn primary"><?= $editing ? 'Save Changes' : '+ Add Section' ?></button>
          <?php if ($editing): ?><a href="homepage_sections.php" class="btn">Cancel</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body table-wrap">
      <table class="table">
        <thead><tr><th>Order</th><th>Title</th><th>Content</th><th>Posts</th><th>Sort</th><th>Visible</th><th></th></tr></thead>
        <tbody>
          <?php if (!$sections): ?><tr><td colspan="7" class="empty">No sections yet.</td></tr><?php endif; ?>
          <?php foreach ($sections as $s): ?>
            <tr>
              <td><?= (int)$s['sort_order'] ?></td>
              <td><?= h($s['title']) ?></td>
              <td class="muted"><?= h($contentOptions[$s['content_type']] ?? $s['content_type']) ?></td>
              <td><?= (int)$s['posts_count'] ?></td>
              <td class="muted"><?= h($sortOptions[$s['sort_by']] ?? $s['sort_by']) ?></td>
              <td><span class="status <?= $s['visible'] ? 'published' : 'archived' ?>"><?= $s['visible'] ? 'Visible' : 'Hidden' ?></span></td>
              <td>
                <a href="homepage_sections.php?edit=<?= (int)$s['id'] ?>" class="btn">Edit</a>
                <a href="homepage_sections.php?toggle=<?= (int)$s['id'] ?>" class="btn"><?= $s['visible'] ? 'Hide' : 'Show' ?></a>
                <a href="homepage_sections.php?delete=<?= (int)$s['id'] ?>" class="btn danger" onclick="return confirm('Delete this section?')">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

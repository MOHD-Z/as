<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

function slugify_genre($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name = trim($_POST['name'] ?? '');
    $nameSecondary = trim($_POST['name_secondary'] ?? '');
    if ($name !== '') {
        $stmt = $pdo->prepare("INSERT INTO genres (name, slug, name_secondary) VALUES (?, ?, ?)");
        $stmt->execute([$name, slugify_genre($name), $nameSecondary ?: null]);
        admin_flash('Genre added.');
    }
    header('Location: genres.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $stmt = $pdo->prepare("UPDATE genres SET name=?, name_secondary=? WHERE id=?");
    $stmt->execute([trim($_POST['name']), trim($_POST['name_secondary']) ?: null, (int)$_POST['id']]);
    admin_flash('Genre updated.');
    header('Location: genres.php');
    exit;
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM genres WHERE id = ?")->execute([(int)$_GET['delete']]);
    admin_flash('Genre deleted.');
    header('Location: genres.php');
    exit;
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM genres WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editing = $stmt->fetch();
}

$genres = $pdo->query("SELECT g.*,
        (SELECT COUNT(*) FROM series_genres WHERE genre_id = g.id) AS series_count,
        (SELECT COUNT(*) FROM movie_genres WHERE genre_id = g.id) AS movie_count
    FROM genres g ORDER BY g.name")->fetchAll();

$admin_page_title = 'Genres';
$admin_active = 'genres';
include __DIR__ . '/includes/layout_top.php';
?>
  <div class="panel" style="margin-bottom:20px;">
    <div class="panel-head"><h2><?= $editing ? 'Edit Genre' : 'Add Genre' ?></h2></div>
    <div class="panel-body">
      <form method="post" class="form-grid">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
        <div class="field"><label>Name</label><input type="text" name="name" placeholder="e.g. Action" required value="<?= h($editing['name'] ?? '') ?>"></div>
        <div class="field"><label>Name in another language (optional)</label><input type="text" name="name_secondary" placeholder="e.g. أكشن" value="<?= h($editing['name_secondary'] ?? '') ?>"></div>
        <div class="field full">
          <button type="submit" class="btn primary"><?= $editing ? 'Save Changes' : 'Add' ?></button>
          <?php if ($editing): ?><a href="genres.php" class="btn">Cancel</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body table-wrap">
      <table class="table">
        <thead><tr><th>Name</th><th>Other Language</th><th>Slug</th><th>Series</th><th>Movies</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($genres as $g): ?>
            <tr>
              <td><?= h($g['name']) ?></td>
              <td class="muted"><?= h($g['name_secondary'] ?? '—') ?></td>
              <td class="muted"><?= h($g['slug']) ?></td>
              <td><?= (int)$g['series_count'] ?></td>
              <td><?= (int)$g['movie_count'] ?></td>
              <td>
                <a href="genres.php?edit=<?= (int)$g['id'] ?>" class="btn">Edit</a>
                <a href="genres.php?delete=<?= (int)$g['id'] ?>" class="btn danger" onclick="return confirm('Delete this genre?')">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
